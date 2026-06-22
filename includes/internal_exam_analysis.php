<?php
/** تحلیل آزمون داخلی مَدار — مبتنی بر نتیجه واقعی آزمون‌های داخل سامانه */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/mock_exam.php';

function internal_exam_analysis_schema_ready(): bool {
    static $ok = null; if ($ok !== null) return $ok;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS internal_exam_analyses (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          attempt_id INT UNSIGNED NOT NULL,
          exam_id INT UNSIGNED NOT NULL,
          student_id INT UNSIGNED NOT NULL,
          advisor_id INT UNSIGNED DEFAULT NULL,
          behavior_json LONGTEXT NULL,
          analysis_json LONGTEXT NULL,
          student_note TEXT NULL,
          advisor_note TEXT NULL,
          status ENUM('draft','submitted') NOT NULL DEFAULT 'submitted',
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_internal_attempt (attempt_id),
          KEY idx_student (student_id),
          KEY idx_advisor (advisor_id),
          KEY idx_exam (exam_id),
          CONSTRAINT fk_internal_attempt FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
          CONSTRAINT fk_internal_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
          CONSTRAINT fk_internal_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $cols=[]; foreach(db()->query('SHOW COLUMNS FROM internal_exam_analyses')->fetchAll() as $c) $cols[$c['Field']]=true;
        if(empty($cols['issues_json'])) {
            try { db()->exec("ALTER TABLE internal_exam_analyses ADD COLUMN issues_json LONGTEXT NULL AFTER analysis_json"); } catch(Throwable $e) {}
        }
        return $ok = true;
    } catch (Throwable $e) { return $ok = false; }
}

function internal_attempt_payload(int $attemptId): ?array {
    $rep = attempt_report($attemptId);
    if (!$rep || ($rep['attempt']['status'] ?? '') !== 'submitted') return null;
    $att = $rep['attempt']; $exam = $rep['exam'];
    $participants = 0; $rank = 1;
    $st = db()->prepare("SELECT id,total_score FROM exam_attempts WHERE exam_id=? AND status='submitted' ORDER BY total_score DESC, submitted_at ASC");
    $st->execute([(int)$exam['id']]);
    foreach ($st->fetchAll() as $i=>$row) { $participants++; if ((int)$row['id'] === $attemptId) $rank = $i + 1; }

    $subjects = [];
    foreach ($rep['sections'] as $sid=>$sec) {
        $nums = array_values(array_map(fn($x)=>(int)$x['gnum'], array_filter($rep['questions'], fn($q)=>$q['sec']===$sec['name'])));
        $subjects[] = [
            'name'=>$sec['name'], 'q_from'=>$nums ? min($nums) : null, 'q_to'=>$nums ? max($nums) : null,
            'correct'=>(int)$sec['correct'], 'wrong'=>(int)$sec['wrong'], 'blank'=>(int)$sec['blank'],
            'percent'=>(float)$sec['percent'], 'time_min'=>null, 'rank'=>null, 'note'=>''
        ];
    }
    $issues = [];
    foreach ($rep['questions'] as $q) {
        if (!in_array($q['state'], ['wrong','blank'], true)) continue;
        $issues[] = [
            'question_number'=>(int)$q['gnum'], 'subject'=>$q['sec'], 'type'=>$q['state'], 'reason'=>'unknown',
            'note'=>$q['state']==='wrong' ? ('پاسخ دانش‌آموز: '.($q['selected'] ?: '—').' / کلید: '.($q['q']['correct_opt'] ?? '—')) : 'نزده'
        ];
    }
    $report = [
        'total_score'=>(float)$att['total_score'], 'total_percent'=>(float)$att['total_score'],
        'rank_in_exam'=>$rank, 'participants'=>$participants, 'total_questions'=>count($rep['questions']), 'target_score'=>null,
    ];
    return ['rep'=>$rep,'report'=>$report,'subjects'=>$subjects,'issues'=>$issues,'rank'=>$rank,'participants'=>$participants];
}

function internal_analysis_by_attempt(int $attemptId): ?array {
    internal_exam_analysis_schema_ready();
    $st = db()->prepare('SELECT ia.*, u.full_name student_name, u.username, u.field student_field, u.grade student_grade, e.title exam_title, e.created_at exam_created_at, a.submitted_at, a.total_score, a.correct_count, a.wrong_count, a.blank_count FROM internal_exam_analyses ia JOIN users u ON u.id=ia.student_id JOIN exams e ON e.id=ia.exam_id JOIN exam_attempts a ON a.id=ia.attempt_id WHERE ia.attempt_id=? LIMIT 1');
    $st->execute([$attemptId]); $r = $st->fetch(); if (!$r) return null;
    $r['behavior'] = $r['behavior_json'] ? (json_decode($r['behavior_json'], true) ?: []) : [];
    $r['analysis'] = $r['analysis_json'] ? (json_decode($r['analysis_json'], true) ?: []) : [];
    $payload = internal_attempt_payload($attemptId);
    if ($payload) {
        $saved_issues = !empty($r['issues_json']) ? (json_decode($r['issues_json'], true) ?: []) : [];
        $reasons_lookup = [];
        foreach ($saved_issues as $si) {
            if (isset($si['question_number'])) {
                $reasons_lookup[(int)$si['question_number']] = $si['reason'] ?? 'unknown';
            }
        }
        foreach ($payload['issues'] as &$iss) {
            if (isset($reasons_lookup[$iss['question_number']])) {
                $iss['reason'] = $reasons_lookup[$iss['question_number']];
            }
        }
        unset($iss);
        
        $r['subjects'] = $payload['subjects'];
        $r['issues'] = $payload['issues'];
        $r['payload'] = $payload;
    }
    return $r;
}
function internal_analysis(int $id): ?array {
    internal_exam_analysis_schema_ready();
    $st=db()->prepare('SELECT attempt_id FROM internal_exam_analyses WHERE id=?'); $st->execute([$id]); $aid=(int)$st->fetchColumn();
    return $aid ? internal_analysis_by_attempt($aid) : null;
}

function internal_analysis_save(int $studentId, int $attemptId, array $in): int {
    internal_exam_analysis_schema_ready();
    $payload = internal_attempt_payload($attemptId);
    if (!$payload) throw new RuntimeException('آزمون برای تحلیل آماده نیست.');
    $rep = $payload['rep']; $att = $rep['attempt']; $exam = $rep['exam'];
    if ((int)$att['student_id'] !== $studentId) throw new RuntimeException('دسترسی ندارید.');
    $behavior = [
      'sleep_hours'=>mock_num($in['sleep_hours'] ?? null,0,16),
      'stress_score'=>mock_num($in['stress_score'] ?? null,1,10),
      'focus_score'=>mock_num($in['focus_score'] ?? null,1,10),
      'time_management'=>mock_txt($in['time_management'] ?? '',40),
      'main_cause'=>mock_txt($in['main_cause'] ?? '',200),
      'best_action'=>mock_txt($in['best_action'] ?? '',300),
      'worst_action'=>mock_txt($in['worst_action'] ?? '',300),
      'next_strategy'=>mock_txt($in['next_strategy'] ?? '',500),
      'mistake_pattern'=>mock_txt($in['mistake_pattern'] ?? '',500),
    ];
    
    // Map incoming reasons back into the issues array
    $in_issues = $in['issues'] ?? [];
    foreach ($payload['issues'] as &$iss) {
        $qNum = (int)$iss['question_number'];
        if (isset($in_issues[$qNum]['reason'])) {
            $iss['reason'] = mock_txt($in_issues[$qNum]['reason'], 40);
        }
    }
    unset($iss);
    
    // Clean and validate issues
    $cleaned_issues = mock_clean_issues($payload['issues']);
    
    $analysis = mock_build_analysis($payload['report'], $payload['subjects'], $behavior, $cleaned_issues);
    $student = get_user($studentId);
    $advisorId = (int)($exam['advisor_id'] ?? ($student['advisor_id'] ?? 0)) ?: null;
    
    db()->prepare('INSERT INTO internal_exam_analyses (attempt_id,exam_id,student_id,advisor_id,behavior_json,analysis_json,issues_json,student_note,status) VALUES (?,?,?,?,?,?,?,?,"submitted") ON DUPLICATE KEY UPDATE behavior_json=VALUES(behavior_json), analysis_json=VALUES(analysis_json), issues_json=VALUES(issues_json), student_note=VALUES(student_note), status="submitted"')
      ->execute([$attemptId,(int)$exam['id'],$studentId,$advisorId,json_encode($behavior,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($analysis,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($cleaned_issues,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),mock_txt($in['student_note']??'',1500)?:null]);
      
    $id = (int)db()->query('SELECT id FROM internal_exam_analyses WHERE attempt_id='.(int)$attemptId)->fetchColumn();
    if ($advisorId) notify($advisorId, 'تحلیل آزمون داخلی ثبت شد 🧠', ($student['full_name'] ?? 'دانش‌آموز').' تحلیل آزمون «'.$exam['title'].'» را تکمیل کرد.', 'chart', 'admin/internal_exam_reports.php');
    return $id;
}

function internal_analyses_for_student(int $studentId, int $limit=30): array {
    internal_exam_analysis_schema_ready();
    $st=db()->prepare('SELECT ia.*, e.title exam_title, a.submitted_at, a.total_score FROM internal_exam_analyses ia JOIN exams e ON e.id=ia.exam_id JOIN exam_attempts a ON a.id=ia.attempt_id WHERE ia.student_id=? ORDER BY a.submitted_at DESC, ia.id DESC LIMIT ?');
    $st->bindValue(1,$studentId,PDO::PARAM_INT); $st->bindValue(2,$limit,PDO::PARAM_INT); $st->execute(); return $st->fetchAll();
}
function internal_analyses_for_advisor(int $advisorId=0, ?int $studentId=null, int $limit=100): array {
    internal_exam_analysis_schema_ready();
    $sql='SELECT ia.*, e.title exam_title, a.submitted_at, a.total_score, u.full_name student_name, u.field, u.grade FROM internal_exam_analyses ia JOIN exams e ON e.id=ia.exam_id JOIN exam_attempts a ON a.id=ia.attempt_id JOIN users u ON u.id=ia.student_id WHERE 1=1'; $p=[];
    if($advisorId){$sql.=' AND (ia.advisor_id=? OR u.advisor_id=?)'; $p[]=$advisorId; $p[]=$advisorId;}
    if($studentId){$sql.=' AND ia.student_id=?'; $p[]=$studentId;}
    $sql.=' ORDER BY a.submitted_at DESC, ia.id DESC LIMIT '.(int)$limit;
    $st=db()->prepare($sql); $st->execute($p); return $st->fetchAll();
}
function internal_can_view(array $r, array $u): bool {
    if (($u['role'] ?? '') === 'student') return (int)$r['student_id']===(int)$u['id'];
    if (($u['role'] ?? '') === 'admin') return true;
    return (int)($r['advisor_id']??0)===(int)$u['id'] || (int)(get_user((int)$r['student_id'])['advisor_id']??0)===(int)$u['id'];
}
