<?php
// admin/results.php — FIXED: proper WHERE/AND construction
require_once __DIR__.'/../config.php';
requireRole('super_admin','teacher');
$pdo = db();

$page      = max(1,(int)($_GET['page']??1));
$perPage   = 25;
$offset    = ($page-1)*$perPage;
$examFilter= (int)($_GET['exam_id']??0);

// Build WHERE safely — no bare AND
$where  = ['ea.status=?'];
$params = ['completed'];
if ($examFilter) { $where[]='ea.exam_id=?'; $params[]=$examFilter; }
$whereSQL = 'WHERE '.implode(' AND ',$where);

$cntSt = $pdo->prepare("SELECT COUNT(*) FROM exam_attempts ea $whereSQL");
$cntSt->execute($params); $total=(int)$cntSt->fetchColumn();

$resSt = $pdo->prepare(
    "SELECT ea.*, u.name AS student_name, u.email AS student_email,
            e.title AS exam_title, e.total_marks AS exam_total, e.pass_marks,
            c.name AS cat_name
     FROM exam_attempts ea
     JOIN users u ON u.id=ea.user_id
     JOIN exams e ON e.id=ea.exam_id
     JOIN categories c ON c.id=e.category_id
     $whereSQL
     ORDER BY ea.submitted_at DESC
     LIMIT $perPage OFFSET $offset"
);
$resSt->execute($params);
$results = $resSt->fetchAll();

$exams      = $pdo->query("SELECT id,title FROM exams ORDER BY title")->fetchAll();
$totalPages = (int)ceil($total/$perPage);
$avgScore   = (float)($pdo->query("SELECT COALESCE(AVG(score),0) FROM exam_attempts WHERE status='completed'")->fetchColumn());
$passCount  = (int)$pdo->query("SELECT COUNT(*) FROM exam_attempts ea JOIN exams e ON e.id=ea.exam_id WHERE ea.status='completed' AND ea.score>=e.pass_marks")->fetchColumn();
$pageTitle  = 'Results';
?>
<?php require_once __DIR__.'/../includes/header.php'; ?>
<div class="layout">
<?php require_once __DIR__.'/../includes/admin_sidebar.php'; ?>
<main class="main-content">

<div class="topbar">
  <div>
    <h1 class="topbar__title">Results &amp; Analytics</h1>
    <p class="topbar__sub">All completed exam attempts across PrepX.</p>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card"><span class="stat-card__icon">📝</span>
    <div class="stat-card__label">Total Attempts</div>
    <div class="stat-card__value"><?=number_format($total)?></div></div>
  <div class="stat-card"><span class="stat-card__icon">📊</span>
    <div class="stat-card__label">Avg Score</div>
    <div class="stat-card__value"><?=number_format($avgScore,1)?></div></div>
  <div class="stat-card"><span class="stat-card__icon">✅</span>
    <div class="stat-card__label">Passed</div>
    <div class="stat-card__value"><?=number_format($passCount)?></div></div>
  <div class="stat-card"><span class="stat-card__icon">❌</span>
    <div class="stat-card__label">Failed</div>
    <div class="stat-card__value"><?=number_format(max(0,$total-$passCount))?></div></div>
</div>

<!-- Filter -->
<div class="card" style="padding:1rem 1.2rem;">
  <form method="GET" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:flex-end;">
    <div style="flex:1;min-width:180px;" class="form-group" style="margin:0;">
      <label>Filter by Exam</label>
      <select name="exam_id">
        <option value="">All Exams</option>
        <?php foreach($exams as $ex): ?>
        <option value="<?=$ex['id']?>" <?=$examFilter===$ex['id']?'selected':''?>><?=e($ex['title'])?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="padding-top:1.4rem;display:flex;gap:.5rem;">
      <button type="submit" class="btn btn--primary btn--sm">Filter</button>
      <a href="/admin/results.php" class="btn btn--outline btn--sm">Reset</a>
    </div>
  </form>
</div>

<div class="card">
  <?php if(!$results): ?>
  <p style="text-align:center;padding:2rem;color:var(--text-muted);">No results found.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Student</th><th>Exam</th><th>Score</th><th>%</th><th>Result</th><th>✔</th><th>✘</th><th>—</th><th>Date</th><th>Detail</th></tr>
      </thead>
      <tbody>
      <?php foreach($results as $i=>$r):
        $pct=$r['exam_total']>0?round($r['score']/$r['exam_total']*100,1):0;
        $passed=$r['score']>=$r['pass_marks'];
      ?>
      <tr>
        <td><?=$offset+$i+1?></td>
        <td>
          <div style="font-weight:600;font-size:.85rem;"><?=e($r['student_name'])?></div>
          <div style="font-size:.72rem;color:var(--text-muted);"><?=e($r['student_email'])?></div>
        </td>
        <td>
          <div style="font-size:.85rem;font-weight:500;"><?=e($r['exam_title'])?></div>
          <div style="font-size:.72rem;color:var(--text-muted);"><?=e($r['cat_name'])?></div>
        </td>
        <td><strong><?=number_format($r['score'],1)?></strong><span style="color:var(--text-muted);">/<?=number_format($r['exam_total'],1)?></span></td>
        <td><span style="font-weight:700;color:<?=$pct>=50?'var(--success)':'var(--danger)'?>"><?=$pct?>%</span></td>
        <td><?=$passed?'<span class="badge badge--success">PASS</span>':'<span class="badge badge--danger">FAIL</span>'?></td>
        <td style="color:var(--success);font-weight:600;"><?=$r['correct_count']?></td>
        <td style="color:var(--danger);font-weight:600;"><?=$r['wrong_count']?></td>
        <td style="color:var(--warning);font-weight:600;"><?=$r['skipped_count']?></td>
        <td style="font-size:.75rem;"><?=$r['submitted_at']?date('d M Y',strtotime($r['submitted_at'])):'—'?></td>
        <td><a href="/result.php?attempt=<?=$r['id']?>" class="btn btn--sm btn--outline">View</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if($totalPages>1): ?>
  <div class="pagination mt-2">
    <?php for($p=1;$p<=$totalPages;$p++): ?>
    <a href="?page=<?=$p?>&exam_id=<?=$examFilter?>" class="<?=$p===$page?'active':''?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

</main></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
