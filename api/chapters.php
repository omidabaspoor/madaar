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

        $chapters = chapters_for_subject($subjectName, $field);
        json_out(['ok'=>true,'chapters'=>$chapters,'subject_name'=>$subjectName,'field'=>$field]);
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
