<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('student');
$u = get_user((int)current_user()['id']);
$advisorId = (int)($u['advisor_id'] ?? 0);
$students = $advisorId ? advisor_students($advisorId, 'active') : [];

$rows = [];
foreach ($students as $s) {
    $total = (int)($s['total_tasks'] ?? 0);
    $done = (float)($s['done_tasks'] ?? 0);
    $pct = $total ? round($done / $total * 100) : 0;
    $rows[] = [
        'id' => (int)$s['id'],
        'name' => (string)$s['full_name'],
        'field' => (string)($s['field'] ?: '—'),
        'grade' => (string)($s['grade'] ?: ''),
        'streak' => (int)($s['streak'] ?? 0),
        'total' => $total,
        'done' => $done,
        'done_display' => score_display($done),
        'pct' => $pct,
    ];
}

usort($rows, function($a, $b) {
    $cmp = $b['pct'] <=> $a['pct'];
    if ($cmp) return $cmp;
    $cmp = $b['done'] <=> $a['done'];
    if ($cmp) return $cmp;
    $cmp = $b['streak'] <=> $a['streak'];
    if ($cmp) return $cmp;
    return strcmp($a['name'], $b['name']);
});

$myRank = null;
foreach ($rows as $i => &$r) {
    $r['rank'] = $i + 1;
    if ($r['id'] === (int)$u['id']) $myRank = $r;
}
unset($r);

$top3 = array_slice($rows, 0, 3);

panel_start('رتبه‌ها', 'جایگاه شما در میان دانش‌آموزان فعال', 'student', 'ranks', ['student.css']);
?>
<style>
.rank-hero{background:radial-gradient(circle at 15% 0%,rgba(203,172,128,.22),transparent 38%),linear-gradient(135deg,rgba(107,136,114,.18),rgba(12,21,18,.88));border:1px solid rgba(203,172,128,.34);border-radius:26px;padding:26px;margin-bottom:20px;box-shadow:0 18px 52px rgba(0,0,0,.28)}
.rank-hero h2{font-size:1.45rem;font-weight:1000;color:var(--gold-light);margin:6px 0}.rank-hero p{color:var(--text-2);line-height:1.9;margin:0}.rank-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-top:20px}.rank-sum{background:rgba(12,21,18,.42);border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:14px}.rank-sum b{display:block;font-size:1.55rem;color:var(--text-1)}.rank-sum span{font-size:.82rem;color:var(--text-3);font-weight:800}.podium{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin-bottom:20px}.pod-card{background:linear-gradient(160deg,var(--card),var(--surface));border:1px solid var(--border-soft);border-radius:22px;padding:18px;position:relative;overflow:hidden}.pod-card.first{border-color:var(--gold);box-shadow:0 14px 44px rgba(203,172,128,.14)}.pod-medal{width:46px;height:46px;border-radius:16px;display:grid;place-items:center;background:var(--gold-glass);color:var(--gold-light);font-weight:1000;font-size:1.25rem;margin-bottom:12px}.pod-name{font-weight:1000;color:var(--text-1);font-size:1rem}.pod-meta{color:var(--text-3);font-size:.8rem;margin-top:4px}.rank-table-wrap{overflow-x:auto}.rank-row-me{background:rgba(203,172,128,.10)!important}.rank-num{width:34px;height:34px;border-radius:12px;display:grid;place-items:center;background:var(--surface-3);font-weight:1000}.rank-num.top{background:var(--grad-gold);color:#171006}.mini-progress{height:8px;background:var(--surface-3);border-radius:99px;overflow:hidden}.mini-progress span{display:block;height:100%;background:linear-gradient(90deg,var(--sage),var(--gold));border-radius:99px}
</style>

<section class="rank-hero">
  <span class="badge badge-gold"><?= icon('trophy',14) ?> جدول رتبه‌بندی</span>
  <h2><?= e(explode(' ', (string)$u['full_name'])[0]) ?> عزیز، جایگاه فعلی شما</h2>
  <p>رتبه‌بندی بر اساس درصد پیشرفت برنامه‌های منتشرشده، تعداد تسک‌های انجام‌شده و استریک محاسبه می‌شود.</p>
  <div class="rank-summary">
    <div class="rank-sum"><b><?= $myRank ? fa_num($myRank['rank']) : '—' ?></b><span>رتبه شما</span></div>
    <div class="rank-sum"><b><?= $myRank ? fa_num($myRank['pct']).'٪' : '—' ?></b><span>پیشرفت شما</span></div>
    <div class="rank-sum"><b><?= fa_num(count($rows)) ?></b><span>دانش‌آموز فعال</span></div>
  </div>
</section>

<?php if (!$rows): ?>
  <div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('users',34) ?></div>هنوز رتبه‌ای برای نمایش وجود ندارد.</div></div>
<?php else: ?>
  <div class="podium">
    <?php foreach($top3 as $r): ?>
      <div class="pod-card <?= $r['rank']===1?'first':'' ?>">
        <div class="pod-medal"><?= fa_num($r['rank']) ?></div>
        <div class="pod-name"><?= e($r['name']) ?><?= $r['id']===(int)$u['id'] ? ' <span class="badge badge-gold">شما</span>' : '' ?></div>
        <div class="pod-meta"><?= e($r['field']) ?> <?= $r['grade'] ? '· '.e($r['grade']) : '' ?></div>
        <div class="between mt-3" style="gap:10px"><div class="mini-progress" style="flex:1"><span style="width:<?= $r['pct'] ?>%"></span></div><b><?= fa_num($r['pct']) ?>٪</b></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="panel">
    <div class="panel-head"><h3><?= icon('list',20) ?> جدول کامل رتبه‌ها</h3></div>
    <div class="rank-table-wrap">
      <table class="tbl">
        <thead><tr><th>رتبه</th><th>دانش‌آموز</th><th>رشته</th><th>تسک‌ها</th><th>استریک</th><th>پیشرفت</th></tr></thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr class="<?= $r['id']===(int)$u['id']?'rank-row-me':'' ?>">
            <td><span class="rank-num <?= $r['rank']<=3?'top':'' ?>"><?= fa_num($r['rank']) ?></span></td>
            <td><div class="u-row"><span class="u-ava"><?= e(avatar_letters($r['name'])) ?></span><b><?= e($r['name']) ?></b><?= $r['id']===(int)$u['id'] ? '<span class="badge badge-gold">شما</span>' : '' ?></div></td>
            <td><span class="badge"><?= e($r['field']) ?></span></td>
            <td><?= fa_num($r['done_display']) ?> / <?= fa_num($r['total']) ?></td>
            <td><span class="badge badge-gold"><?= icon('fire',13) ?> <?= fa_num($r['streak']) ?></span></td>
            <td style="min-width:170px"><div class="between" style="gap:10px"><div class="mini-progress" style="flex:1"><span style="width:<?= $r['pct'] ?>%"></span></div><b><?= fa_num($r['pct']) ?>٪</b></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php panel_end(); ?>
