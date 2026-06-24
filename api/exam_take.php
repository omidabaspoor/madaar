<?php
/**
 * API آزمون‌دادن (دانش‌آموز)
 * actions: answer (ذخیره پاسخ), flag, sync (دسته‌ای), submit, time
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
boot_session();
require_role('student');
require_csrf();
$u = current_user();
$me = (int)$u['id'];
$in = array_merge($_POST, body_json());
$action = (string)($in['action'] ?? '');
ensure_exam_question_schema();

/** attempt متعلق به این دانش‌آموز و باز؟ */
function my_attempt(int $attemptId, int $me): ?array {
    $st = db()->prepare('SELECT * FROM exam_attempts WHERE id=? AND student_id=?');
    $st->execute([$attemptId, $me]);
    return $st->fetch() ?: null;
}
/** بررسی انقضای زمان (سمت سرور = امن) */
function attempt_expired(array $a): bool {
    if (empty($a['deadline_at'])) return false;
    return strtotime($a['deadline_at']) < time();
}

function active_exam_question_ids(int $examId): array {
    $st = db()->prepare('SELECT id FROM exam_questions WHERE exam_id=? AND COALESCE(is_cancelled,0)=0');
    $st->execute([$examId]);
    return array_fill_keys(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)), true);
}

try {
switch ($action) {

case 'answer': {
    $attemptId = (int)($in['attempt_id'] ?? 0);
    $a = my_attempt($attemptId, $me);
    if (!$a) json_out(['ok'=>false,'error'=>'آزمون یافت نشد'],404);
    if ($a['status'] === 'submitted') json_out(['ok'=>false,'error'=>'این آزمون قبلاً پایان یافته'],409);
    if (attempt_expired($a)) { grade_attempt($attemptId); json_out(['ok'=>false,'expired'=>true,'error'=>'زمان آزمون تمام شد'],409); }
    $qid = (int)($in['question_id'] ?? 0);
    $sel = $in['selected_opt'] ?? null;
    $sel = ($sel === null || $sel === '' || $sel === 0 || $sel === '0') ? null : max(1,min(4,(int)$sel));
    // سوال متعلق به همین آزمون؟
    $chk = db()->prepare('SELECT id FROM exam_questions WHERE id=? AND exam_id=? AND COALESCE(is_cancelled,0)=0');
    $chk->execute([$qid, $a['exam_id']]);
    if (!$chk->fetch()) json_out(['ok'=>false,'error'=>'سوال نامعتبر'],422);
    db()->prepare('INSERT INTO exam_answers (attempt_id,question_id,selected_opt) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE selected_opt=VALUES(selected_opt)')
        ->execute([$attemptId,$qid,$sel]);
    json_out(['ok'=>true]);
}

case 'flag': {
    $attemptId = (int)($in['attempt_id'] ?? 0);
    $a = my_attempt($attemptId, $me);
    if (!$a || $a['status']==='submitted') json_out(['ok'=>false,'error'=>'نامعتبر'],409);
    $qid = (int)($in['question_id'] ?? 0);
    $chk = db()->prepare('SELECT id FROM exam_questions WHERE id=? AND exam_id=? AND COALESCE(is_cancelled,0)=0');
    $chk->execute([$qid, $a['exam_id']]);
    if (!$chk->fetch()) json_out(['ok'=>true,'ignored'=>true]);
    $fl = (int)((string)($in['flagged'] ?? '0')==='1');
    db()->prepare('INSERT INTO exam_answers (attempt_id,question_id,flagged) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE flagged=VALUES(flagged)')
        ->execute([$attemptId,$qid,$fl]);
    json_out(['ok'=>true]);
}

/* ---- ذخیره دسته‌ای (پشتیبان هر ۵ ثانیه) ---- */
case 'sync': {
    $attemptId = (int)($in['attempt_id'] ?? 0);
    $a = my_attempt($attemptId, $me);
    if (!$a) json_out(['ok'=>false,'error'=>'یافت نشد'],404);
    if ($a['status']==='submitted') json_out(['ok'=>false,'submitted'=>true]);
    if (attempt_expired($a)) { $r=grade_attempt($attemptId); json_out(['ok'=>true,'expired'=>true]); }
    $answers = $in['answers'] ?? [];
    if (is_array($answers)) {
        $stmt = db()->prepare('INSERT INTO exam_answers (attempt_id,question_id,selected_opt,flagged) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE selected_opt=VALUES(selected_opt), flagged=VALUES(flagged)');
        $activeIds = active_exam_question_ids((int)$a['exam_id']);
        db()->beginTransaction();
        try {
            foreach ($answers as $ans) {
                $qid=(int)($ans['q'] ?? 0); if(!$qid || empty($activeIds[$qid])) continue;
                $sel=$ans['s'] ?? null; $sel=($sel===null||$sel===''||(int)$sel===0)?null:max(1,min(4,(int)$sel));
                $fl=(int)!empty($ans['f']);
                $stmt->execute([$attemptId,$qid,$sel,$fl]);
            }
            db()->commit();
        } catch(Throwable $e){ db()->rollBack(); throw $e; }
    }
    $remain = $a['deadline_at'] ? max(0, strtotime($a['deadline_at'])-time()) : null;
    json_out(['ok'=>true,'remain'=>$remain]);
}

case 'time': {
    $attemptId = (int)($in['attempt_id'] ?? $_GET['attempt_id'] ?? 0);
    $a = my_attempt($attemptId, $me);
    if (!$a) json_out(['ok'=>false],404);
    if ($a['status']==='submitted') json_out(['ok'=>true,'submitted'=>true]);
    if (attempt_expired($a)) { grade_attempt($attemptId); json_out(['ok'=>true,'expired'=>true,'remain'=>0]); }
    $remain = $a['deadline_at'] ? max(0, strtotime($a['deadline_at'])-time()) : null;
    json_out(['ok'=>true,'remain'=>$remain]);
}

case 'save_diagnostic': {
    $attemptId = (int)($in['attempt_id'] ?? 0);
    $qid = (int)($in['question_id'] ?? 0);
    $a = my_attempt($attemptId, $me);
    if (!$a) json_out(['ok'=>false, 'error'=>'کارنامه یافت نشد'], 404);
    
    $reason   = trim((string)($in['diagnostic_reason'] ?? ''));
    $takeaway = trim((string)($in['diagnostic_takeaway'] ?? ''));

    db()->prepare('UPDATE exam_answers SET diagnostic_reason=?, diagnostic_takeaway=? WHERE attempt_id=? AND question_id=?')
        ->execute([$reason ?: null, $takeaway ?: null, $attemptId, $qid]);

    json_out(['ok'=>true]);
}

case 'submit': {
    $attemptId = (int)($in['attempt_id'] ?? 0);
    $a = my_attempt($attemptId, $me);
    if (!$a) json_out(['ok'=>false,'error'=>'یافت نشد'],404);
    if ($a['status']==='submitted') json_out(['ok'=>true,'already'=>true]);
    // ذخیره پاسخ‌های ارسالی نهایی (در صورت وجود)
    $answers = $in['answers'] ?? [];
    if (is_array($answers) && $answers) {
        $stmt = db()->prepare('INSERT INTO exam_answers (attempt_id,question_id,selected_opt,flagged) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE selected_opt=VALUES(selected_opt), flagged=VALUES(flagged)');
        $activeIds = active_exam_question_ids((int)$a['exam_id']);
        foreach ($answers as $ans) {
            $qid=(int)($ans['q'] ?? 0); if(!$qid || empty($activeIds[$qid])) continue;
            $sel=$ans['s'] ?? null; $sel=($sel===null||$sel===''||(int)$sel===0)?null:max(1,min(4,(int)$sel));
            $stmt->execute([$attemptId,$qid,$sel,(int)!empty($ans['f'])]);
        }
    }
    $res = grade_attempt($attemptId);
    $exam = get_exam((int)$a['exam_id']);
    notify((int)$exam['advisor_id'],'آزمون ثبت شد ✅', $u['full_name'].' آزمون «'.$exam['title'].'» را داد ('.fa_num(round($res['total'])).'٪)', 'clipboard', 'admin/exam_results.php?id='.$a['exam_id']);
    json_out(['ok'=>true,'redirect'=>url('student/exam_result.php?attempt='.$attemptId)]);
}

default: json_out(['ok'=>false,'error'=>'عملیات نامعتبر'],400);
}
} catch (Throwable $e) {
    json_out(['ok'=>false,'error'=> APP_ENV==='development' ? $e->getMessage() : 'خطای سرور'],500);
}
