<?php
require_once __DIR__.'/../config.php';
requireRole('student');
$pdo=db(); $user=currentUser();

$uid=$user['id'];
$recent=$pdo->query("SELECT ea.*,e.title AS exam_title,e.total_marks AS etotal,c.name AS cat_name
     FROM exam_attempts ea JOIN exams e ON e.id=ea.exam_id JOIN categories c ON c.id=e.category_id
     WHERE ea.user_id=$uid AND ea.status='completed' ORDER BY ea.submitted_at DESC LIMIT 5")->fetchAll();

$subPerf=$pdo->query("SELECT c.name AS cat_name,COUNT(ea.id) AS attempts,
     AVG(ea.score) AS avg_score,AVG(ea.total_marks) AS avg_total
     FROM exam_attempts ea JOIN exams e ON e.id=ea.exam_id JOIN categories c ON c.id=e.category_id
     WHERE ea.user_id=$uid AND ea.status='completed' GROUP BY c.id ORDER BY avg_score DESC")->fetchAll();

$latestExams=$pdo->query("SELECT e.*,c.name AS cat_name,c.type AS cat_type,
     (SELECT COUNT(*) FROM questions q WHERE q.exam_id=e.id) AS q_count
     FROM exams e JOIN categories c ON c.id=e.category_id
     WHERE e.status='published' ORDER BY e.created_at DESC LIMIT 9")->fetchAll();

$announcements=$pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3")->fetchAll();

$totalAtt=(int)$pdo->query("SELECT COUNT(*) FROM exam_attempts WHERE user_id=$uid AND status='completed'")->fetchColumn();
$avgPct=(float)($pdo->query("SELECT COALESCE(AVG(score/total_marks*100),0) FROM exam_attempts WHERE user_id=$uid AND status='completed' AND total_marks>0")->fetchColumn());
$bestPct=(float)($pdo->query("SELECT COALESCE(MAX(score/total_marks*100),0) FROM exam_attempts WHERE user_id=$uid AND status='completed' AND total_marks>0")->fetchColumn());
$pageTitle='Dashboard';
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
    <button class="site-nav__ham" id="studentNavHam" aria-label="Menu">☰</button>
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

  <!-- Welcome -->
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1.6rem;">
    <div>
      <h1 style="font-family:var(--font-serif);font-size:1.7rem;color:var(--navy);">Welcome, <?=e($user['name'])?>!</h1>
      <div style="margin-top:.4rem;display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;">
        <?php if($user['status']==='premium'): ?>
          <span class="badge badge--navy">⭐ Premium Member</span>
        <?php else: ?>
          <span class="badge badge--muted">Free Account</span>
          <a href="/student/profile.php" style="font-size:.82rem;color:var(--gold);">→ Activate Premium Code</a>
        <?php endif; ?>
      </div>
    </div>
    <a href="/student/exams.php" class="btn btn--primary">Browse Exams →</a>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card"><span class="stat-card__icon">📝</span><div class="stat-card__label">Exams Taken</div><div class="stat-card__value"><?=$totalAtt?></div></div>
    <div class="stat-card"><span class="stat-card__icon">📊</span><div class="stat-card__label">Avg Score</div><div class="stat-card__value"><?=number_format($avgPct,1)?>%</div></div>
    <div class="stat-card"><span class="stat-card__icon">🏆</span><div class="stat-card__label">Best Score</div><div class="stat-card__value"><?=number_format($bestPct,1)?>%</div></div>
    <div class="stat-card"><span class="stat-card__icon">🎯</span><div class="stat-card__label">Subjects</div><div class="stat-card__value"><?=count($subPerf)?></div></div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.4rem;align-items:start;">

    <div>
      <!-- Recent Scores -->
      <div class="card">
        <div class="card__header">
          <h2 class="card__title">Recent Scores</h2>
          <a href="/student/history.php" class="btn btn--sm btn--outline">View All</a>
        </div>
        <?php if($recent): foreach($recent as $at):
          $p=$at['etotal']>0?round($at['score']/$at['etotal']*100,1):0;
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.7rem 0;border-bottom:1px solid var(--border);gap:.8rem;flex-wrap:wrap;">
          <div style="min-width:0;">
            <div style="font-weight:500;font-size:.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=e($at['exam_title'])?></div>
            <div style="font-size:.73rem;color:var(--text-muted);"><?=e($at['cat_name'])?> · <?=$at['submitted_at']?date('d M Y',strtotime($at['submitted_at'])):'—'?></div>
          </div>
          <div style="display:flex;align-items:center;gap:.6rem;flex-shrink:0;">
            <div style="text-align:right;">
              <div style="font-weight:700;color:<?=$p>=50?'var(--success)':'var(--danger)'?>;"><?=$p?>%</div>
              <div style="font-size:.7rem;color:var(--text-muted);"><?=number_format($at['score'],1)?>/<?=number_format($at['etotal'],1)?></div>
            </div>
            <a href="/result.php?attempt=<?=$at['id']?>" class="btn btn--sm btn--outline">Review</a>
          </div>
        </div>
        <?php endforeach; else: ?>
        <p style="color:var(--text-muted);text-align:center;padding:1.5rem;">No exams taken yet. <a href="/student/exams.php">Start now →</a></p>
        <?php endif; ?>
      </div>

      <!-- Subject Performance -->
      <?php if($subPerf): ?>
      <div class="card">
        <div class="card__header"><h2 class="card__title">Subject-wise Performance</h2></div>
        <?php foreach($subPerf as $sp):
          $p=$sp['avg_total']>0?round($sp['avg_score']/$sp['avg_total']*100,1):0;
        ?>
        <div style="margin-bottom:.9rem;">
          <div style="display:flex;justify-content:space-between;font-size:.86rem;margin-bottom:.3rem;">
            <span><?=e($sp['cat_name'])?></span>
            <span style="font-weight:700;color:<?=$p>=50?'var(--success)':'var(--danger)'?>"><?=$p?>%</span>
          </div>
          <div class="progress-bar-wrap">
            <div class="progress-bar" style="width:<?=$p?>%;background:<?=$p>=50?'var(--success)':'var(--danger)'?>;"></div>
          </div>
          <div style="font-size:.7rem;color:var(--text-muted);margin-top:.15rem;"><?=$sp['attempts']?> attempt(s)</div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div>
      <!-- Announcements -->
      <?php if($announcements): ?>
      <div class="card">
        <div class="card__header"><h2 class="card__title">📢 Notices</h2></div>
        <?php foreach($announcements as $ann): ?>
        <div style="margin-bottom:.7rem;padding-bottom:.7rem;border-bottom:1px solid var(--border);">
          <div style="font-weight:600;font-size:.86rem;"><?=e($ann['title'])?></div>
          <div style="font-size:.78rem;color:var(--text-muted);margin-top:.25rem;"><?=e(mb_strimwidth($ann['body'],0,110,'…'))?></div>
          <div style="font-size:.7rem;color:var(--text-muted);margin-top:.2rem;"><?=date('d M Y',strtotime($ann['created_at']))?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Latest Exams -->
      <div class="card">
        <div class="card__header"><h2 class="card__title">Latest Exams</h2></div>
        <?php foreach(array_slice($latestExams,0,6) as $ex):
          $canAccess=$ex['type']==='free'||hasCategoryAccess((int)$ex['category_id']);
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem 0;border-bottom:1px solid var(--border);gap:.5rem;">
          <div style="min-width:0;flex:1;">
            <div style="font-size:.86rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=e($ex['title'])?></div>
            <div style="font-size:.7rem;color:var(--text-muted);"><?=e($ex['cat_name'])?></div>
          </div>
          <?php if($canAccess): ?>
            <a href="/exam_view.php?exam=<?=$ex['id']?>" class="btn btn--sm btn--gold" style="flex-shrink:0;">Start</a>
          <?php else: ?>
            <span class="badge badge--muted" style="flex-shrink:0;">🔒</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <a href="/student/exams.php" class="btn btn--outline btn--block" style="margin-top:.8rem;">View All Exams</a>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
