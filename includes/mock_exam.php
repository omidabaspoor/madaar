<?php
/** آزمون آزمایشی/کنکور بیرونی + تحلیل هوشمند */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/models.php';

const MOCK_PROVIDERS = ['قلمچی','گزینه دو','سنجش','ماز','مرآت','مدرسه','کنکور سراسری','سایر'];
const MOCK_SUBJECTS = ['ریاضی','فیزیک','شیمی','زیست','ادبیات','عربی','دینی','زبان','زمین‌شناسی','اقتصاد','فلسفه و منطق','تاریخ و جغرافیا','روان‌شناسی'];

function mock_exam_schema_ready(): bool {
    static $ok = null; if ($ok !== null) return $ok;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS mock_exam_reports (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          student_id INT UNSIGNED NOT NULL,
          advisor_id INT UNSIGNED DEFAULT NULL,
          provider VARCHAR(80) DEFAULT NULL,
          provider_other VARCHAR(120) DEFAULT NULL,
          exam_title VARCHAR(180) DEFAULT NULL,
          exam_date DATE DEFAULT NULL,
          field VARCHAR(60) DEFAULT NULL,
          grade VARCHAR(40) DEFAULT NULL,
          total_score DECIMAL(8,2) DEFAULT NULL,
          total_percent DECIMAL(6,2) DEFAULT NULL,
          rank_in_exam INT UNSIGNED DEFAULT NULL,
          participants INT UNSIGNED DEFAULT NULL,
          total_questions INT UNSIGNED DEFAULT NULL,
          target_score DECIMAL(8,2) DEFAULT NULL,
          subjects_json LONGTEXT NULL,
          behavior_json LONGTEXT NULL,
          analysis_json LONGTEXT NULL,
          student_note TEXT NULL,
          advisor_note TEXT NULL,
          status ENUM('draft','submitted') NOT NULL DEFAULT 'submitted',
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_mock_student (student_id, exam_date),
          KEY idx_mock_advisor (advisor_id, exam_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $cols=[]; foreach(db()->query('SHOW COLUMNS FROM mock_exam_reports')->fetchAll() as $c) $cols[$c['Field']]=true;
        $adds=[
          'advisor_id'=>'ALTER TABLE mock_exam_reports ADD COLUMN advisor_id INT UNSIGNED DEFAULT NULL AFTER student_id',
          'provider_other'=>'ALTER TABLE mock_exam_reports ADD COLUMN provider_other VARCHAR(120) DEFAULT NULL AFTER provider',
          'total_questions'=>'ALTER TABLE mock_exam_reports ADD COLUMN total_questions INT UNSIGNED DEFAULT NULL AFTER participants',
          'target_score'=>'ALTER TABLE mock_exam_reports ADD COLUMN target_score DECIMAL(8,2) DEFAULT NULL AFTER total_questions',
          'subjects_json'=>'ALTER TABLE mock_exam_reports ADD COLUMN subjects_json LONGTEXT NULL AFTER target_score',
          'behavior_json'=>'ALTER TABLE mock_exam_reports ADD COLUMN behavior_json LONGTEXT NULL AFTER subjects_json',
          'analysis_json'=>'ALTER TABLE mock_exam_reports ADD COLUMN analysis_json LONGTEXT NULL AFTER behavior_json',
          'issues_json'=>'ALTER TABLE mock_exam_reports ADD COLUMN issues_json LONGTEXT NULL AFTER analysis_json',
          'advisor_note'=>'ALTER TABLE mock_exam_reports ADD COLUMN advisor_note TEXT NULL AFTER student_note',
          'status'=>"ALTER TABLE mock_exam_reports ADD COLUMN status ENUM('draft','submitted') NOT NULL DEFAULT 'submitted' AFTER advisor_note",
        ];
        foreach($adds as $col=>$sql) if(empty($cols[$col])) { try{ db()->exec($sql); }catch(Throwable $e){} }
        return $ok = true;
    } catch (Throwable $e) { return $ok = false; }
}

function mock_num($v, ?float $min=null, ?float $max=null): ?float {
    if ($v === null || $v === '') return null;
    $v = strtr((string)$v, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٫'=>'.',','=>'.']);
    if (!is_numeric($v)) return null;
    $f = (float)$v;
    if ($min !== null) $f = max($min, $f);
    if ($max !== null) $f = min($max, $f);
    return $f;
}
function mock_int($v, int $min=0, ?int $max=null): ?int { $n = mock_num($v,$min,$max); return $n===null ? null : (int)round($n); }
function mock_txt($v, int $max=900): string { return mb_substr(trim((string)($v ?? '')),0,$max); }

function mock_clean_subjects(array $rows): array {
    $out=[];
    foreach($rows as $r){
        if (!is_array($r)) continue;
        $name = mock_txt($r['name'] ?? '', 80);
        $c = mock_int($r['correct'] ?? null,0,300);
        $w = mock_int($r['wrong'] ?? null,0,300);
        $b = mock_int($r['blank'] ?? null,0,300);
        $pct = mock_num($r['percent'] ?? null,-100,100);
        $time = mock_int($r['time_min'] ?? null,0,600);
        $rank = mock_int($r['rank'] ?? null,0,1000000);
        $qFrom = mock_int($r['q_from'] ?? null,1,10000);
        $qTo = mock_int($r['q_to'] ?? null,1,10000);
        $note = mock_txt($r['note'] ?? '', 300);
        if ($name==='' && $c===null && $w===null && $b===null && $pct===null && $time===null && $note==='') continue;
        $total = (int)($c??0)+(int)($w??0)+(int)($b??0);
        $acc = (($c??0)+($w??0))>0 ? round(($c??0)/max(1,($c??0)+($w??0))*100) : null;
        $out[]=['name'=>$name ?: 'درس بدون نام','correct'=>$c,'wrong'=>$w,'blank'=>$b,'percent'=>$pct,'time_min'=>$time,'rank'=>$rank,'q_from'=>$qFrom,'q_to'=>$qTo,'note'=>$note,'total'=>$total,'accuracy'=>$acc];
    }
    return $out;
}

function mock_clean_issues(array $rows): array {
    $types = ['wrong'=>'غلط','blank'=>'نزده'];
    $reasons = [
        'concept' => 'ضعف علمی و مفهومی',
        'careless_calc' => 'بی‌دقتی در محاسبات عددی',
        'careless_read' => 'بی‌دقتی در خواندن صورت سوال یا گزینه‌ها',
        'forgot' => 'فراموشی فرمول، فرضیه یا نکته کلیدی',
        'doubt' => 'شک بین دو گزینه (انتخاب اشتباه)',
        'trap' => 'افتادن در تله آموزشی/علمی طراح',
        'time_rush' => 'کمبود زمان و حل شتاب‌زده',
        'bubble_err' => 'اشتباه در وارد کردن گزینه در پاسخبرگ',
        'not_studied' => 'عدم مطالعه یا حذف مبحث از قبل',
        'not_mastered' => 'عدم تسلط کافی (با وجود مطالعه مبحث)',
        'no_time' => 'کمبود زمان (اصلاً به سوال نرسیدم)',
        'too_hard' => 'دشواری بیش از حد سوال (ارزش ریسک نداشت)',
        'doubt_many' => 'شک بین سه یا چهار گزینه',
        'strategy' => 'استراتژی اشتباه در اولویت‌بندی سوال',
        'careless' => 'بی‌دقتی',
        'time' => 'کمبود زمان',
        'unknown' => 'نامشخص'
    ];
    $out=[];
    foreach($rows as $r){
        if(!is_array($r)) continue;
        $q = mock_int($r['question_number'] ?? null,1,10000);
        $subject = mock_txt($r['subject'] ?? '',80);
        $type = in_array(($r['type'] ?? ''), array_keys($types), true) ? (string)$r['type'] : 'wrong';
        $reason = in_array(($r['reason'] ?? ''), array_keys($reasons), true) ? (string)$r['reason'] : 'unknown';
        $note = mock_txt($r['note'] ?? '',500);
        if(!$q && $subject==='' && $note==='') continue;
        $out[]=['question_number'=>$q,'subject'=>$subject,'type'=>$type,'type_label'=>$types[$type],'reason'=>$reason,'reason_label'=>$reasons[$reason],'note'=>$note];
    }
    return $out;
}

function mock_build_analysis(array $report, array $subjects, array $behavior, array $issues=[]): array {
    $percent = $report['total_percent'] !== null ? (float)$report['total_percent'] : null;
    $score = $report['total_score'] !== null ? (float)$report['total_score'] : null;
    $target = $report['target_score'] !== null ? (float)$report['target_score'] : null;
    $rank = $report['rank_in_exam'] !== null ? (int)$report['rank_in_exam'] : null;
    $participants = $report['participants'] !== null ? max(1,(int)$report['participants']) : null;
    $totalQuestionsInput = $report['total_questions'] !== null ? max(1,(int)$report['total_questions']) : null;

    $answered=0; $correct=0; $wrong=0; $blank=0; $time=0; $hasSubject=false;
    $weak=[]; $strong=[];
    foreach($subjects as $s){
        $hasSubject=true;
        $correct += (int)($s['correct']??0); $wrong += (int)($s['wrong']??0); $blank += (int)($s['blank']??0); $time += (int)($s['time_min']??0);
        $p = $s['percent'];
        if ($p === null && (($s['correct']??0)+($s['wrong']??0)+($s['blank']??0))>0) {
            $p = round(((($s['correct']??0) - (($s['wrong']??0)/3)) / max(1,(($s['correct']??0)+($s['wrong']??0)+($s['blank']??0))))*100,1);
        }
        if ($p !== null && $p < 35) $weak[]=['name'=>$s['name'],'percent'=>$p,'reason'=>'درصد پایین'];
        if (($s['wrong']??0) >= max(3, ($s['correct']??0))) $weak[]=['name'=>$s['name'],'percent'=>$p,'reason'=>'غلط زیاد نسبت به درست'];
        if ($p !== null && $p >= 65) $strong[]=['name'=>$s['name'],'percent'=>$p];
    }
    $answered = $correct + $wrong;
    $totalQ = $totalQuestionsInput ?: ($correct + $wrong + $blank);
    $accuracy = $answered>0 ? round($correct/$answered*100) : null;
    $blankRate = $totalQ>0 ? round($blank/$totalQ*100) : null;
    $wrongRate = $totalQ>0 ? round($wrong/$totalQ*100) : null;
    if ($percent === null && $totalQ>0) $percent = round((($correct - $wrong/3)/max(1,$totalQ))*100,1);

    // Calculate issue counts
    $issueCounts = [];
    $issueSubjectCounts = [];
    foreach ($issues as $iss) {
        $r_key = $iss['reason'] ?? 'unknown';
        $sub = $iss['subject'] ?? '';
        if ($r_key !== '') {
            $issueCounts[$r_key] = ($issueCounts[$r_key] ?? 0) + 1;
        }
        if ($sub !== '') {
            $issueSubjectCounts[$sub] = ($issueSubjectCounts[$sub] ?? 0) + 1;
        }
    }
    arsort($issueCounts);
    arsort($issueSubjectCounts);

    $rankScore = null;
    if ($rank && $participants) $rankScore = round(max(0, min(100, (1 - (($rank-1)/$participants))*100)));
    $execution = $percent !== null ? max(0,min(100, round($percent))) : ($score ? max(0,min(100,round(($score-3000)/70))) : 0);
    $targetScore = ($target && $score) ? max(0,min(100, round(65 + (($score-$target)/max(1,$target))*120))) : ($percent!==null ? $execution : 0);
    $accuracyScore = $accuracy !== null ? $accuracy : 0;
    $risk = 18;
    if ($wrongRate !== null && $wrongRate > 30) $risk += 25;
    if ($blankRate !== null && $blankRate > 35) $risk += 18;
    if (!empty($behavior['stress_score']) && (float)$behavior['stress_score'] >= 7) $risk += 14;
    if (!empty($behavior['sleep_hours']) && (float)$behavior['sleep_hours'] < 6) $risk += 12;
    if (!empty($behavior['time_management']) && in_array($behavior['time_management'], ['ضعیف','خیلی بد'], true)) $risk += 14;
    $risk = max(0,min(100,$risk));
    $overall = round(($execution*.36)+(($rankScore??$execution)*.20)+($accuracyScore*.18)+($targetScore*.14)+((100-$risk)*.12));
    $overall = max(0,min(100,$overall));

    $alerts=[];
    if ($wrongRate !== null && $wrongRate > 30) $alerts[]=['level'=>'danger','title'=>'غلط‌های پرهزینه','text'=>'نسبت پاسخ غلط بالاست؛ باید علت غلط‌ها به تفکیک بی‌دقتی، ضعف مفهوم و زمان بررسی شود.'];
    if ($blankRate !== null && $blankRate > 35) $alerts[]=['level'=>'warn','title'=>'سفید زیاد','text'=>'تعداد سوال‌های نزده زیاد است؛ احتمالاً مدیریت زمان یا اولویت‌بندی سوال‌ها نیاز به اصلاح دارد.'];
    if ($target && $score && $score < $target) $alerts[]=['level'=>'warn','title'=>'فاصله با هدف','text'=>'نتیجه آزمون پایین‌تر از هدف ثبت‌شده است و نیاز به برنامه جبرانی کوتاه دارد.'];
    if (!$hasSubject) $alerts[]=['level'=>'warn','title'=>'داده درسی ناقص','text'=>'برای تحلیل دقیق‌تر، ریزنتیجه درس‌ها را وارد کنید.'];

    usort($weak, fn($a,$b)=>($a['percent']??0)<=>($b['percent']??0));
    usort($strong, fn($a,$b)=>($b['percent']??0)<=>($a['percent']??0));
    $weak = array_values(array_unique($weak, SORT_REGULAR));

    $recs=[];
    if ($weak) $recs[]='اولویت تحلیل بعد از آزمون: '.implode('، ', array_map(fn($x)=>$x['name'], array_slice($weak,0,3))).'؛ برای هرکدام ۱۰ سوال غلط/نزده را ریشه‌یابی کنید.';
    if ($wrongRate !== null && $wrongRate > 25) $recs[]='قبل از افزایش حجم تست، یک چک‌لیست بی‌دقتی و دام تستی بسازید و در آزمون بعدی اجرا کنید.';
    if ($blankRate !== null && $blankRate > 30) $recs[]='استراتژی سه‌مرحله‌ای آزمون اجرا شود: دور اول سوالات ساده، دور دوم متوسط، دور سوم فقط سوالات زمان‌بر منتخب.';
    if (!empty($behavior['main_cause'])) $recs[]='علت اصلی اعلام‌شده توسط دانش‌آموز («'.$behavior['main_cause'].'») باید در برنامه هفته آینده به اقدام قابل اندازه‌گیری تبدیل شود.';

    // Custom smart rules based on top reason
    if (!empty($issueCounts)) {
        $top = array_key_first($issueCounts);
        $labels = [
            'concept' => 'ضعف علمی و مفهومی',
            'careless_calc' => 'بی‌دقتی در محاسبات عددی',
            'careless_read' => 'بی‌دقتی در خواندن صورت سوال یا گزینه‌ها',
            'forgot' => 'فراموشی فرمول، فرضیه یا نکته کلیدی',
            'doubt' => 'شک بین دو گزینه (انتخاب اشتباه)',
            'trap' => 'افتادن در تله آموزشی/علمی طراح',
            'time_rush' => 'کمبود زمان و حل شتاب‌زده',
            'bubble_err' => 'اشتباه در وارد کردن گزینه در پاسخبرگ',
            'not_studied' => 'عدم مطالعه یا حذف مبحث از قبل',
            'not_mastered' => 'عدم تسلط کافی (با وجود مطالعه مبحث)',
            'no_time' => 'کمبود زمان (اصلاً به سوال نرسیدم)',
            'too_hard' => 'دشواری بیش از حد سوال (ارزش ریسک نداشت)',
            'doubt_many' => 'شک بین سه یا چهار گزینه',
            'strategy' => 'استراتژی اشتباه در اولویت‌بندی سوال',
            'careless' => 'بی‌دقتی',
            'time' => 'کمبود زمان',
            'unknown' => 'نامشخص'
        ];
        $top_lbl = $labels[$top] ?? $top;
        $recs[] = 'ریشه پرتکرار خطاها: «' . $top_lbl . '»؛ برنامه هفته آینده باید مستقیم برای کاهش همین عامل طراحی شود.';

        if ($top === 'careless_calc' || $top === 'careless_read' || $top === 'careless') {
            $recs[] = '💡 تحلیل رفتاری نشان می‌دهد بخش عمده کسر نمره شما از بی‌دقتی است. در آزمون بعدی سرعت خواندن دفترچه را ۱۰٪ کاهش دهید و محاسبات را در فضای مشخص چرک‌نویس کنید.';
        } elseif ($top === 'concept' || $top === 'forgot') {
            $recs[] = '💡 ضعف علمی یا فراموشی عامل اصلی خطاهای شماست. برای دروس درگیر، اختصاص ۲ واحد تست آموزشی و مرور خلاصه‌ها در اول هفته توصیه می‌شود.';
        } elseif ($top === 'time_rush' || $top === 'no_time' || $top === 'time') {
            $recs[] = '💡 چالش جدی مدیریت زمان دارید. تست‌های خانگی را به صورت مجموعه‌ای و زمان‌دار بزنید تا مغز شما به مدیریت تایمر عادت کند.';
        } elseif ($top === 'doubt' || $top === 'doubt_many') {
            $recs[] = '💡 شک بین گزینه‌ها به شما آسیب زده است. پیشنهاد می‌شود تکنیک حذف گزینه را دقیق‌تر پیاده کنید و در شک‌های ۵۰-۵۰، گزینه اولی که به چشمتان آمد را تغییر ندهید یا کلاً نزنید.';
        } elseif ($top === 'trap') {
            $recs[] = '💡 شما مکرراً در دام‌های طراحان آزمون افتاده‌اید. حتماً گزینه‌های انحرافی آزمون را در دفترچه خود یادداشت کرده و نکات دام‌های تستی را مرور کنید.';
        } elseif ($top === 'not_studied' || $top === 'not_mastered') {
            $recs[] = '💡 علت سفیدی سوالات، مباحث مطالعه‌نشده یا تسلط کم است. در هماهنگی با مشاور، پیش‌نیازها و بخش‌های کلیدی این فصول را به برنامه اضافه کنید.';
        } elseif ($top === 'too_hard') {
            $recs[] = '💡 تصمیم هوشمندانه برای رها کردن سوالات بیش از حد دشوار؛ این تفکر حرفه‌ای باعث نجات تراز و جلوگیری از نمره منفی شما شده است.';
        }
    }

    if (!$recs) $recs[]='روند کلی قابل قبول است؛ تمرکز اصلی روی حفظ نقاط قوت و تحلیل جزئی غلط‌ها باشد.';

    $summary = [];
    $summary[] = 'شاخص کلی تحلیل آزمون '.$overall.'٪ ارزیابی شد.';
    if ($rankScore !== null) $summary[] = 'جایگاه نسبی دانش‌آموز در این آزمون حدود '.$rankScore.'٪ از جامعه آماری است.';
    if ($accuracy !== null) $summary[] = 'دقت پاسخ‌گویی '.$accuracy.'٪ و نرخ سوال‌های نزده '.($blankRate??0).'٪ ثبت شده است.';
    if ($weak) $summary[] = 'درس‌های نیازمند پیگیری فوری: '.implode('، ', array_map(fn($x)=>$x['name'], array_slice($weak,0,3))).'.';
    if ($strong) $summary[] = 'نقاط قوت آزمون: '.implode('، ', array_map(fn($x)=>$x['name'], array_slice($strong,0,3))).'.';

    return [
      'overall'=>$overall,
      'overall_label'=>$overall>=80?'عالی':($overall>=65?'خوب':($overall>=45?'متوسط':'نیازمند پیگیری جدی')),
      'scores'=>[
        'result'=>$execution,'rank'=>$rankScore,'accuracy'=>$accuracyScore,'target'=>$targetScore,'risk'=>$risk,
        'blank_rate'=>$blankRate,'wrong_rate'=>$wrongRate,'accuracy_raw'=>$accuracy
      ],
      'weak_subjects'=>array_slice($weak,0,5),'strong_subjects'=>array_slice($strong,0,4),
      'alerts'=>array_slice($alerts,0,6),'recommendations'=>array_slice($recs,0,7),
      'issue_breakdown'=>['reasons'=>$issueCounts??[], 'subjects'=>$issueSubjectCounts??[]],
      'action_plan'=>array_slice([
        $weak ? 'تحلیل عمیق '.($weak[0]['name']).' و استخراج ۵ علت اصلی افت' : 'مرور نقاط قوت و حفظ روتین فعلی',
        'ثبت دفترچه غلط‌ها در سه دسته: بی‌دقتی، مفهوم، زمان',
        'طراحی هدف عددی برای آزمون بعدی: کاهش غلط یا سفید حداقل ۲۰٪',
      ],0,4),
      'summary'=>implode(' ', $summary),
    ];
}

function mock_report_save(int $studentId, array $in): int {
    mock_exam_schema_ready();
    $student = get_user($studentId);
    $subjects = mock_clean_subjects($in['subjects'] ?? []);
    $issues = mock_clean_issues($in['issues'] ?? []);
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
    $report = [
      'total_score'=>mock_num($in['total_score'] ?? null,0,20000),
      'total_percent'=>mock_num($in['total_percent'] ?? null,-100,100),
      'rank_in_exam'=>mock_int($in['rank_in_exam'] ?? null,0,1000000),
      'participants'=>mock_int($in['participants'] ?? null,0,10000000),
      'total_questions'=>mock_int($in['total_questions'] ?? null,0,10000),
      'target_score'=>mock_num($in['target_score'] ?? null,0,20000),
    ];
    $analysis = ((int)($student['advisor_id'] ?? 0) && function_exists('advisor_feature_enabled') && !advisor_feature_enabled((int)$student['advisor_id'], 'mock_analysis_enabled'))
      ? ['overall'=>0,'overall_label'=>'غیرفعال','summary'=>'تحلیل هوشمند آزمون آزمایشی توسط مشاور غیرفعال شده است.','scores'=>[],'alerts'=>[],'recommendations'=>[],'action_plan'=>[]]
      : mock_build_analysis($report, $subjects, $behavior, $issues);
    $id = (int)($in['id'] ?? 0);
    $provider = in_array(($in['provider'] ?? ''), MOCK_PROVIDERS, true) ? (string)$in['provider'] : 'سایر';
    $date = mock_txt($in['exam_date'] ?? '',20) ?: date('Y-m-d');
    if ($id) {
      $old = mock_report($id);
      if (!$old || (int)$old['student_id'] !== $studentId) return 0;
      db()->prepare('UPDATE mock_exam_reports SET provider=?,provider_other=?,exam_title=?,exam_date=?,field=?,grade=?,total_score=?,total_percent=?,rank_in_exam=?,participants=?,total_questions=?,target_score=?,subjects_json=?,behavior_json=?,analysis_json=?,issues_json=?,student_note=?,status="submitted" WHERE id=?')
        ->execute([$provider,mock_txt($in['provider_other']??'',120)?:null,mock_txt($in['exam_title']??'',180)?:null,$date,$student['field']??null,$student['grade']??null,$report['total_score'],$report['total_percent'],$report['rank_in_exam'],$report['participants'],$report['total_questions'],$report['target_score'],json_encode($subjects,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($behavior,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($analysis,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($issues,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),mock_txt($in['student_note']??'',1500)?:null,$id]);
      return $id;
    }
    db()->prepare('INSERT INTO mock_exam_reports (student_id,advisor_id,provider,provider_other,exam_title,exam_date,field,grade,total_score,total_percent,rank_in_exam,participants,total_questions,target_score,subjects_json,behavior_json,analysis_json,issues_json,student_note,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"submitted")')
      ->execute([$studentId,(int)($student['advisor_id']??0)?:null,$provider,mock_txt($in['provider_other']??'',120)?:null,mock_txt($in['exam_title']??'',180)?:null,$date,$student['field']??null,$student['grade']??null,$report['total_score'],$report['total_percent'],$report['rank_in_exam'],$report['participants'],$report['total_questions'],$report['target_score'],json_encode($subjects,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($behavior,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($analysis,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($issues,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),mock_txt($in['student_note']??'',1500)?:null]);
    return (int)db()->lastInsertId();
}

function mock_report(int $id): ?array { mock_exam_schema_ready(); $st=db()->prepare('SELECT r.*, u.full_name student_name, u.username, u.field student_field, u.grade student_grade FROM mock_exam_reports r JOIN users u ON u.id=r.student_id WHERE r.id=? LIMIT 1'); $st->execute([$id]); $r=$st->fetch(); if(!$r) return null; $r['subjects']=$r['subjects_json']?(json_decode($r['subjects_json'],true)?:[]):[]; $r['behavior']=$r['behavior_json']?(json_decode($r['behavior_json'],true)?:[]):[]; $r['analysis']=$r['analysis_json']?(json_decode($r['analysis_json'],true)?:[]):[]; $r['issues']=$r['issues_json']?(json_decode($r['issues_json'],true)?:[]):[]; return $r; }
function mock_reports_for_student(int $studentId, int $limit=30): array { mock_exam_schema_ready(); $st=db()->prepare('SELECT * FROM mock_exam_reports WHERE student_id=? ORDER BY exam_date DESC, id DESC LIMIT ?'); $st->bindValue(1,$studentId,PDO::PARAM_INT); $st->bindValue(2,$limit,PDO::PARAM_INT); $st->execute(); return $st->fetchAll(); }
function mock_reports_for_advisor(int $advisorId, ?int $studentId=null, int $limit=80): array { mock_exam_schema_ready(); $sql='SELECT r.*,u.full_name student_name,u.field,u.grade FROM mock_exam_reports r JOIN users u ON u.id=r.student_id WHERE 1=1'; $p=[]; if($advisorId){$sql.=' AND (r.advisor_id=? OR u.advisor_id=?)'; $p[]=$advisorId; $p[]=$advisorId;} if($studentId){$sql.=' AND r.student_id=?'; $p[]=$studentId;} $sql.=' ORDER BY r.exam_date DESC,r.id DESC LIMIT '.(int)$limit; $st=db()->prepare($sql); $st->execute($p); return $st->fetchAll(); }
function mock_can_view(array $r, array $u): bool { if($u['role']==='student') return (int)$r['student_id']===(int)$u['id']; if($u['role']==='admin') return true; return (int)($r['advisor_id']??0)===(int)$u['id'] || (int)(get_user((int)$r['student_id'])['advisor_id']??0)===(int)$u['id']; }
