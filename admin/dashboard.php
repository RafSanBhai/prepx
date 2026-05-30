<?php
require_once __DIR__.'/../config.php';
requireRole('super_admin','teacher');
$pdo = db(); $user = currentUser();

$totalStudents   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$premiumStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student' AND status='premium'")->fetchColumn();
$totalExams      = (int)$pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
$totalAttempts   = (int)$pdo->query("SELECT COUNT(*) FROM exam_attempts WHERE status='completed'")->fetchColumn();
$totalQuestions  = (int)$pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();

$recentAttempts = $pdo->query(
    "SELECT ea.*, u.name AS student_name, e.title AS exam_title
     FROM exam_attempts ea
     JOIN users u ON u.id=ea.user_id
     JOIN exams e ON e.id=ea.exam_id
     WHERE ea.status='completed'
     ORDER BY ea.submitted_at DESC LIMIT 6"
)->fetchAll();

$recentExams = $pdo->query(
    "SELECT e.*, c.name AS cat_name
     FROM exams e JOIN categories c ON c.id=e.category_id
     ORDER BY e.created_at DESC LIMIT 6"
)->fetchAll();

$pageTitle='Dashboard';
?>
<?php require_once __DIR__.'/../includes/header.php'; ?>
<div class="layout">
<?php require_once __DIR__.'/../includes/admin_sidebar.php'; ?>
<main class="main-content">

<div class="topbar">
  <div>
    <h1 class="topbar__title">Dashboard</h1>
    <p class="topbar__sub">Welcome back, <?=e($user['name'])?>! Here's PrepX at a glance.</p>
  </div>
  <?php if($user['role']==='super_admin'): ?>
  <a href="/admin/activation_codes.php" class="btn btn--gold">🔑 Generate Code</a>
  <?php endif; ?>
</div>

<div class="stats-grid">
  <div class="stat-card"><span class="stat-card__icon">👥</span>
    <div class="stat-card__label">Students</div><div class="stat-card__value"><?=number_format($totalStudents)?></div></div>
  <div class="stat-card"><span class="stat-card__icon">⭐</span>
    <div class="stat-card__label">Premium</div><div class="stat-card__value"><?=number_format($premiumStudents)?></div></div>
  <div class="stat-card"><span class="stat-card__icon">📋</span>
    <div class="stat-card__label">Exams</div><div class="stat-card__value"><?=number_format($totalExams)?></div></div>
  <div class="stat-card"><span class="stat-card__icon">❓</span>
    <div class="stat-card__label">Questions</div><div class="stat-card__value"><?=number_format($totalQuestions)?></div></div>
  <div class="stat-card"><span class="stat-card__icon">📝</span>
    <div class="stat-card__label">Attempts</div><div class="stat-card__value"><?=number_format($totalAttempts)?></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.4rem;align-items:start;">
  <div class="card">
    <div class="card__header">
      <h2 class="card__title">Recent Exams</h2>
      <a href="/admin/exams.php" class="btn btn--sm btn--outline">View All</a>
    </div>
    <?php foreach($recentExams as $ex): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:.65rem 0;border-bottom:1px solid var(--border);">
      <div>
        <div style="font-weight:500;font-size:.88rem;"><?=e($ex['title'])?></div>
        <div style="font-size:.73rem;color:var(--text-muted);"><?=e($ex['cat_name'])?> · <?=$ex['duration_minutes']?> min</div>
      </div>
      <span class="badge <?=$ex['type']==='premium'?'badge--navy':'badge--gold'?>"><?=$ex['type']?></span>
    </div>
    <?php endforeach; if(!$recentExams): ?>
    <p style="color:var(--text-muted);font-size:.88rem;padding:.5rem 0;">No exams yet.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card__header">
      <h2 class="card__title">Recent Attempts</h2>
      <a href="/admin/results.php" class="btn btn--sm btn--outline">View All</a>
    </div>
    <?php foreach($recentAttempts as $at): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:.65rem 0;border-bottom:1px solid var(--border);">
      <div>
        <div style="font-weight:500;font-size:.88rem;"><?=e($at['student_name'])?></div>
        <div style="font-size:.73rem;color:var(--text-muted);"><?=e($at['exam_title'])?></div>
      </div>
      <div style="text-align:right;">
        <div style="font-weight:700;color:var(--navy);font-size:.9rem;"><?=number_format($at['score'],1)?>/<?=number_format($at['total_marks'],1)?></div>
        <div style="font-size:.7rem;color:var(--text-muted);"><?=$at['submitted_at']?date('d M',strtotime($at['submitted_at'])):'—'?></div>
      </div>
    </div>
    <?php endforeach; if(!$recentAttempts): ?>
    <p style="color:var(--text-muted);font-size:.88rem;padding:.5rem 0;">No attempts yet.</p>
    <?php endif; ?>
  </div>
</div>

<div class="card mt-2">
  <div class="card__header"><h2 class="card__title">Quick Actions</h2></div>
  <div style="display:flex;gap:.8rem;flex-wrap:wrap;">
    <a href="/admin/exams.php" class="btn btn--primary">📋 New Exam</a>
    <a href="/admin/csv_import.php" class="btn btn--outline">📤 Import CSV</a>
    <a href="/admin/categories.php" class="btn btn--outline">🗂 Categories</a>
    <?php if($user['role']==='super_admin'): ?>
    <a href="/admin/activation_codes.php" class="btn btn--gold">🔑 Activation Codes</a>
    <a href="/admin/users.php" class="btn btn--outline">👥 Users</a>
    <?php endif; ?>
    <a href="/admin/results.php" class="btn btn--outline">📊 Results</a>
  </div>
</div>

</main></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
