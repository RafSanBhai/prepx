<?php
require_once __DIR__.'/../config.php';
requireRole('student');
$pdo=db(); $user=currentUser();

$catF=(int)($_GET['cat']??0);
$search=trim($_GET['q']??'');
$page=max(1,(int)($_GET['page']??1)); $perPage=12; $offset=($page-1)*$perPage;

$where=["e.status='published'"]; $params=[];
if ($catF)   { $where[]='e.category_id=?'; $params[]=$catF; }
if ($search) { $where[]='e.title LIKE ?';  $params[]="%$search%"; }
$whereSQL='WHERE '.implode(' AND ',$where);

$cntSt=$pdo->prepare("SELECT COUNT(*) FROM exams e $whereSQL"); $cntSt->execute($params);
$total=(int)$cntSt->fetchColumn();

$st=$pdo->prepare("SELECT e.*,c.name AS cat_name,
     (SELECT COUNT(*) FROM questions q WHERE q.exam_id=e.id) AS q_count
     FROM exams e JOIN categories c ON c.id=e.category_id
     $whereSQL ORDER BY e.created_at DESC LIMIT $perPage OFFSET $offset");
$st->execute($params); $exams=$st->fetchAll();

$categories=$pdo->query("SELECT id,name FROM categories ORDER BY name")->fetchAll();
$totalPages=(int)ceil($total/$perPage);
$pageTitle='Browse Exams';
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
  <div class="topbar">
    <div>
      <h1 style="font-family:var(--font-serif);font-size:1.7rem;color:var(--navy);">Available Exams</h1>
      <p style="color:var(--text-muted);font-size:.85rem;"><?=$total?> exam(s) found on PrepX</p>
    </div>
  </div>

  <!-- Filters -->
  <div class="card" style="padding:.9rem 1.2rem;margin-bottom:1.4rem;">
    <form method="GET" style="display:flex;gap:.7rem;flex-wrap:wrap;align-items:flex-end;">
      <div style="flex:1;min-width:150px;" class="form-group" style="margin:0;">
        <label>Search</label>
        <input type="text" name="q" value="<?=e($search)?>" placeholder="Search exams…">
      </div>
      <div class="form-group" style="margin:0;">
        <label>Category</label>
        <select name="cat">
          <option value="">All Categories</option>
          <?php foreach($categories as $c): ?>
          <option value="<?=$c['id']?>" <?=$catF===$c['id']?'selected':''?>><?=e($c['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="padding-top:1.4rem;display:flex;gap:.5rem;">
        <button type="submit" class="btn btn--primary btn--sm">Search</button>
        <a href="/student/exams.php" class="btn btn--outline btn--sm">Reset</a>
      </div>
    </form>
  </div>

  <!-- Exam Cards -->
  <div class="exam-grid">
    <?php foreach($exams as $ex):
      $canAccess=$ex['type']==='free'||hasCategoryAccess((int)$ex['category_id']);
    ?>
    <div class="exam-card">
      <div class="exam-card__head">
        <div class="exam-card__cat"><?=e($ex['cat_name'])?> · <?=$ex['type']==='premium'?'⭐ Premium':'✓ Free'?></div>
        <div class="exam-card__title"><?=e($ex['title'])?></div>
      </div>
      <div class="exam-card__body">
        <div class="exam-card__meta">
          <span>⏱ <?=$ex['duration_minutes']?> min</span>
          <span>❓ <?=$ex['q_count']?> Q</span>
          <span>📊 <?=number_format($ex['total_marks'],0)?> marks</span>
          <?php if($ex['negative_marking']>0): ?><span style="color:var(--danger);">−<?=$ex['negative_marking']?>/wrong</span><?php endif; ?>
        </div>
        <?php if($ex['instructions']): ?>
        <p style="font-size:.78rem;color:var(--text-muted);"><?=e(mb_strimwidth($ex['instructions'],0,80,'…'))?></p>
        <?php endif; ?>
        <?php if($canAccess): ?>
          <a href="/exam_view.php?exam=<?=$ex['id']?>" class="btn btn--primary btn--block">Start Exam →</a>
        <?php else: ?>
          <div style="background:var(--navy);color:#fff;border-radius:var(--radius);padding:.9rem;text-align:center;">
            <div style="font-size:1.4rem;">🔒</div>
            <div style="font-weight:600;font-size:.85rem;margin:.2rem 0;">Premium Only</div>
            <a href="/student/profile.php" class="btn btn--gold btn--sm" style="margin-top:.4rem;">Activate Code</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; if(!$exams): ?>
    <div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--text-muted);">No exams found.</div>
    <?php endif; ?>
  </div>

  <?php if($totalPages>1): ?>
  <div class="pagination" style="margin-top:1.5rem;">
    <?php for($p=1;$p<=$totalPages;$p++): ?>
    <a href="?page=<?=$p?>&cat=<?=$catF?>&q=<?=urlencode($search)?>" class="<?=$p===$page?'active':''?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
