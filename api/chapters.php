<?php
/**
 * API مدیریت فصل‌ها (Chapters) — CRUD + fetch برای برنامه‌ریز
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
boot_session();
require_login();
require_csrf();

$u = current_user();
$me = (int)$u['id'];
$role = $u['role'];
$json = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') ? body_json() : [];
$in = array_merge($_POST, $json);
$action = (string)($in['action'] ?? '');

function planner_exact_lesson_groups(string $subjectName): ?array {
    $key = normalize_subject_for_chapters($subjectName) ?? $subjectName;
    $make = function(string $book, array $lessons): array {
        $rows = [];
        foreach ($lessons as $i => $name) {
            $rows[] = ['book_name'=>$book, 'chapter_name'=>$name, 'sort_order'=>$i, 'display_order'=>$i + 1, 'is_lesson'=>1];
        }
        return $rows;
    };
    if ($key === 'فارسی') {
        return [
            'فارسی (۱)' => $make('فارسی (۱)', ['چشمه','از آموختن، ننگ مدار','پاسداری از حقیقت','درس آزاد','بیداد ظالمان','مهر و وفا','جمال و کمال','سفر به بصره','کلاس نقاشی','دریادلان صف‌شکن','خاک آزادگان','رستم و اشکبوس','گردآفرید','طوطی و بقال','درس آزاد','خسرو','سپیده‌دم','عظمت نگاه']),
            'فارسی (۲)' => $make('فارسی (۲)', ['نیکی','قاضی بُست','در امواج سند','درس آزاد','آغازگری تنها','پروردۀ عشق','باران محبّت','در کوی عاشقان','ذوق لطیف','بانگ جَرَس','یاران عاشق','کاوۀ دادخواه','درس آزاد','حملۀ حیدری','کبوتر طوق‌دار','قصّۀ عینکم','خاموشی دریا','خوان عدل']),
            'فارسی (۳)' => $make('فارسی (۳)', ['شکرِ نعمت','مست و هُشیار','آزادی','درس آزاد','دماوندیه','نی‌نامه','در حقیقت عشق','از پاریز تا پاریس','کویر','فصل شکوفایی','آن شب عزیز','گذر سیاوش از آتش','خوانِ هشتم','سی‌مرغ و سیمرغ','درس آزاد','کباب غاز','خندۀ تو','عشق جاودانی']),
        ];
    }
    if ($key === 'سلامت و بهداشت') {
        return [
            'سلامت و بهداشت' => $make('سلامت و بهداشت', ['سلامت چیست؟','سبک زندگی سالم','برنامه غذایی سالم','کنترل وزن و تناسب اندام','بهداشت و ایمنی مواد غذایی','بیماری‌های غیرواگیر','بیماری‌های واگیردار','بهداشت فردی','بهداشت ازدواج و فرزندآوری','بهداشت روان','مصرف دخانیات و الکل','اعتیاد به مواد مخدر و عوارض آن','پیشگیری از اختلالات اسکلتی-عضلانی (کمر درد)','پیشگیری از حوادث خانگی']),
        ];
    }
    return null;
}
function planner_mark_lesson_groups(array $chapters, string $subjectName): array {
    $key = normalize_subject_for_chapters($subjectName) ?? $subjectName;
    if (in_array($key, ['عربی، زبان قرآن','فارسی','سلامت و بهداشت'], true)) {
        foreach ($chapters as &$items) {
            foreach ($items as &$r) { $r['is_lesson'] = 1; $r['display_order'] = ((int)($r['sort_order'] ?? 0)) + 1; }
            unset($r);
        }
        unset($items);
    }
    return $chapters;
}

try {
    switch ($action) {

    /* ============ دریافت فصل‌ها برای برنامه‌ریز (بر اساس درس و مشخصات دانش‌آموز) ============ */
    case 'fetch': {
        if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
        $subjectId = (int)($in['subject_id'] ?? 0);
        $studentId = (int)($in['student_id'] ?? 0);
        if (!$subjectId || !$studentId) json_out(['ok'=>false,'error'=>'درس و دانش‌آموز را مشخص کنید'],422);

        $subj = db()->prepare('SELECT name FROM subjects WHERE id=?');
        $subj->execute([$subjectId]);
        $subjectName = (string)($subj->fetchColumn() ?? '');

        $stu = get_user($studentId);
        if (!$stu || $stu['role'] !== 'student') json_out(['ok'=>false,'error'=>'دانش‌آموز یافت نشد'],404);

        $field = $stu['field'] ?? '';

        $chapters = planner_exact_lesson_groups($subjectName) ?? chapters_for_subject($subjectName, $field);
        $chapters = planner_mark_lesson_groups($chapters, $subjectName);
        $subjectKey = normalize_subject_for_chapters($subjectName) ?? $subjectName;
        $isLessonMode = in_array($subjectKey, ['عربی، زبان قرآن','فارسی','سلامت و بهداشت'], true);
        json_out(['ok'=>true,'chapters'=>$chapters,'subject_name'=>$subjectName,'field'=>$field,'lesson_mode'=>$isLessonMode,'multi_select'=>$isLessonMode]);
    }

    /* ============ لیست فصل‌ها (برای صفحه تنظیمات) ============ */
    case 'list': {
        if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
        $field = isset($in['field']) && $in['field'] !== '' ? $in['field'] : null;
        $subject = isset($in['subject_name']) && $in['subject_name'] !== '' ? $in['subject_name'] : null;
        $grade = isset($in['grade']) && $in['grade'] !== '' ? (int)$in['grade'] : null;
        $search = isset($in['search']) ? trim($in['search']) : '';
        $rows = all_chapters($field, $subject, $grade);
        if ($search !== '') {
            $rows = array_filter($rows, fn($r) =>
                str_contains((string)($r['book_name'] ?? ''), $search) ||
                str_contains((string)($r['chapter_name'] ?? ''), $search) ||
                str_contains((string)($r['subject_name'] ?? ''), $search)
            );
            $rows = array_values($rows);
        }
        json_out(['ok'=>true,'items'=>$rows]);
    }

    /* ============ افزودن/ویرایش فصل ============ */
    case 'save': {
        if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
        $data = [
            'id' => $in['id'] ?? null,
            'subject_name' => trim($in['subject_name'] ?? ''),
            'grade' => (int)($in['grade'] ?? 12),
            'field' => trim($in['field'] ?? 'omumi'),
            'book_name' => trim($in['book_name'] ?? ''),
            'chapter_name' => trim($in['chapter_name'] ?? ''),
            'sort_order' => (int)($in['sort_order'] ?? 0),
            'advisor_id' => $me,
        ];
        if (!$data['subject_name'] || !$data['chapter_name'] || !$data['book_name']) {
            json_out(['ok'=>false,'error'=>'نام درس، کتاب و فصل الزامی است'],422);
        }
        $id = save_chapter($data);
        json_out(['ok'=>true,'id'=>$id]);
    }

    /* ============ حذف فصل ============ */
    case 'delete': {
        if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
        $id = (int)($in['id'] ?? 0);
        if (!$id) json_out(['ok'=>false,'error'=>'شناسه نامعتبر'],422);
        delete_chapter($id);
        json_out(['ok'=>true]);
    }

    /* ============ بازیابی پیش‌فرض‌های سیستمی ============ */
    case 'reset_system': {
        if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
        $count = seed_system_chapters();
        json_out(['ok'=>true,'added'=>$count]);
    }

    default:
        json_out(['ok'=>false,'error'=>'عملیات نامعتبر'],400);
    }
} catch (Throwable $ex) {
    json_out(['ok'=>false,'error'=> APP_ENV==='development' ? $ex->getMessage() : 'خطای داخلی سرور'],500);
}
