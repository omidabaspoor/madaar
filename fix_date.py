import os
for path in ['admin/online_sessions.php', 'student/online_sessions.php']:
    text = open(path).read()
    text = text.replace("jalali_fa_num(date($s['scheduled_at']) . ' - ' . s['scheduled_at']))", "jalali_date($s['scheduled_at'], true)")
    open(path, 'w').write(text)
