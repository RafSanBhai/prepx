<?php
require_once __DIR__.'/../config.php';
requireRole('student');
$pdo=db(); $user=currentUser(); $uid=$user['id'];

$page=max(1,(int)($_GET['page']??1)); $perPage=15; $offset=($page-1)*$perPage;
$total=(int)$pdo->query("SELECT COUNT(*) FROM exam_attempts WHERE user_id=$uid AND status='completed'")->fetchColumn();

$attempts=$pdo->query(
    "SELECT ea.*,e.title AS exam_title,e.total_marks AS etotal,e.pass_marks,c.name AS cat_name
     FROM exam_attempts ea JOIN exams e ON e.id=ea.exam_id JOIN categories c ON c.id=e.category_id
     WHERE ea.user_id=$uid AND ea.status='completed'
     ORDER BY ea.submitted_at DESC LIMIT $perPage OFFSET $offset"
)->fetchAll();

$totalPages=(int)ceil($total/$perPage);
$avgPct=(float)($pdo->query("SELECT COALESCE(AVG(score/total_marks*100),0) FROM exam_attempts WHERE user_id=$uid AND status='completed' AND total_marks>0")->fetchColumn());
$passedCnt=(int)$pdo->query("SELECT COUNT(*) FROM exam_attempts ea JOIN exams e ON e.id=ea.exam_id WHERE ea.user_id=$uid AND ea.status='completed' AND ea.score>=e.pass_marks")->fetchColumn();
$pageTitle='Exam History';
?>
<?php require_once __DIR__.'/../includes/header.php'; ?>

<nav class="site-nav">
  <div class="container">
    <a href="/student/dashboard.php" class="site-nav__brand">⚖ PrepX</a>
    <div class="site-nav__links">
      <a href="/student/dashboard.php">Dashboard</a>
      <a href="/student/exams.php">Exams</a>
      <a href="/student/history.php">History</a>
      <a href="/student/profile.php">Profile</a>
      <a href="/logout.php" class="btn btn--outline btn--sm" style="color:#fff;border-color:rgba(255,255,255,.3);">Logout</a>
    </div>
    <button class="site-nav__ham" id="studentNavHam">☰</button>
  </div>
  <div class="site-nav__mobile" id="studentNavMobile">
    <a href="/student/dashboard.php">Dashboard</a>
    <a href="/student/exams.php">Exams</a>
    <a href="/student/history.php">History</a>
    <a href="/student/profile.php">Profile</a>
    <a href="/logout.php">Logout</a>
  </div>
</nav>

<div class="container" style="padding-top:1.8rem;padding-bottom:3rem;">
  <h1 style="font-family:var(--font-serif);font-size:1.7rem;color:var(--navy);margin-bottom:1.4rem;">Exam History</h1>

  <div class="stats-grid">
    <div class="stat-card"><span class="stat-card__icon">📝</span><div class="stat-card__label">Total Attempts</div><div class="stat-card__value"><?=$total?></div></div>
    <div class="stat-card"><span class="stat-card__icon">📊</span><div class="stat-card__label">Avg Score</div><div class="stat-card__value"><?=number_format($avgPct,1)?>%</div></div>
    <div class="stat-card"><span class="stat-card__icon">✅</span><div class="stat-card__label">Passed</div><div class="stat-card__value"><?=$passedCnt?></div></div>
    <div class="stat-card"><span class="stat-card__icon">❌</span><div class="stat-card__label">Failed</div><div class="stat-card__value"><?=max(0,$total-$passedCnt)?></div></div>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Exam</th><th>Score</th><th>%</th><th>Result</th><th>✔</th><th>✘</th><th>—</th><th>Date</th><th>Review</th></tr></thead>
        <tbody>
        <?php foreach($attempts as $i=>$at):
          $p=$at['etotal']>0?round($at['score']/$at['etotal']*100,1):0;
          $passed=$at['score']>=$at['pass_marks'];
        ?>
        <tr>
          <td><?=$offset+$i+1?></td>
          <td>
            <div style="font-weight:500;font-size:.87rem;"><?=e($at['exam_title'])?></div>
            <div style="font-size:.72rem;color:var(--text-muted);"><?=e($at['cat_name'])?></div>
          </td>
          <td><strong><?=number_format($at['score'],1)?></strong><span style="color:var(--text-muted);">/<?=number_format($at['etotal'],1)?></span></td>
          <td style="font-weight:700;color:<?=$p>=50?'var(--success)':'var(--danger)'?>"><?=$p?>%</td>
          <td><?=$passed?'<span class="badge badge--success">PASS</span>':'<span class="badge badge--danger">FAIL</span>'?></td>
          <td style="color:var(--success);font-weight:600;"><?=$at['correct_count']?></td>
          <td style="color:var(--danger);font-weight:600;"><?=$at['wrong_count']?></td>
          <td style="color:var(--warning);font-weight:600;"><?=$at['skipped_count']?></td>
          <td style="font-size:.75rem;white-space:nowrap;"><?=$at['submitted_at']?date('d M Y',strtotime($at['submitted_at'])):'—'?></td>
          <td><a href="/result.php?attempt=<?=$at['id']?>" class="btn btn--sm btn--outline">View</a></td>
        </tr>
        <?php endforeach; if(!$attempts): ?>
        <tr><td colspan="10" style="text-align:center;padding:2rem;color:var(--text-muted);">No exams taken yet. <a href="/student/exams.php">Start one →</a></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($totalPages>1): ?>
    <div class="pagination" style="padding:1rem;">
      <?php for($p=1;$p<=$totalPages;$p++): ?>
      <a href="?page=<?=$p?>" class="<?=$p===$page?'active':''?>"><?=$p?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
