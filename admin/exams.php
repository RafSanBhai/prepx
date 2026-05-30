<?php
require_once __DIR__.'/../config.php';
requireRole('super_admin','teacher');
$pdo = db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action  = $_POST['action']??'';
    $title   = trim($_POST['title']??'');
    $catId   = (int)($_POST['category_id']??0);
    $dur     = (int)($_POST['duration_minutes']??60);
    $passM   = (float)($_POST['pass_marks']??0);
    $negM    = (float)($_POST['negative_marking']??0);
    $mpq     = (float)($_POST['mark_per_q']??1);
    $type    = in_array($_POST['type'],['free','premium'])?$_POST['type']:'free';
    $status  = in_array($_POST['status'],['draft','published','archived'])?$_POST['status']:'draft';
    $instr   = trim($_POST['instructions']??'');

    if ($action==='create') {
        $pdo->prepare('INSERT INTO exams(title,category_id,created_by,duration_minutes,pass_marks,negative_marking,mark_per_q,type,status,instructions) VALUES(?,?,?,?,?,?,?,?,?,?)')
            ->execute([$title,$catId,$_SESSION['user_id'],$dur,$passM,$negM,$mpq,$type,$status,$instr]);
        setFlash('success','Exam created!');
    } elseif ($action==='update') {
        $pdo->prepare('UPDATE exams SET title=?,category_id=?,duration_minutes=?,pass_marks=?,negative_marking=?,mark_per_q=?,type=?,status=?,instructions=? WHERE id=?')
            ->execute([$title,$catId,$dur,$passM,$negM,$mpq,$type,$status,$instr,(int)$_POST['exam_id']]);
        setFlash('success','Exam updated!');
    } elseif ($action==='delete') {
        $pdo->prepare('DELETE FROM exams WHERE id=?')->execute([(int)$_POST['exam_id']]);
        setFlash('info','Exam deleted.');
    }
    redirect('/admin/exams.php');
}

$editExam=null;
if (isset($_GET['edit'])) {
    $st=$pdo->prepare('SELECT * FROM exams WHERE id=? LIMIT 1');
    $st->execute([(int)$_GET['edit']]); $editExam=$st->fetch();
}

$page=max(1,(int)($_GET['page']??1)); $perPage=15; $offset=($page-1)*$perPage;
$total=(int)$pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
$exams=$pdo->query("SELECT e.*,c.name AS cat_name FROM exams e JOIN categories c ON c.id=e.category_id ORDER BY e.created_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();
$categories=$pdo->query("SELECT id,name FROM categories ORDER BY name")->fetchAll();
$totalPages=(int)ceil($total/$perPage);
$pageTitle='Exams';
?>
<?php require_once __DIR__.'/../includes/header.php'; ?>
<div class="layout">
<?php require_once __DIR__.'/../includes/admin_sidebar.php'; ?>
<main class="main-content">

<div class="topbar">
  <div><h1 class="topbar__title">Exams</h1><p class="topbar__sub">Create and manage PrepX exam sets.</p></div>
  <button class="btn btn--primary" onclick="document.getElementById('examModal').classList.add('open')">＋ New Exam</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Title</th><th>Category</th><th>Duration</th><th>Q</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($exams as $i=>$ex):
        $qc=(int)$pdo->query("SELECT COUNT(*) FROM questions WHERE exam_id={$ex['id']}")->fetchColumn();
      ?>
      <tr>
        <td><?=$offset+$i+1?></td>
        <td><strong style="font-size:.88rem;"><?=e($ex['title'])?></strong></td>
        <td style="font-size:.82rem;"><?=e($ex['cat_name'])?></td>
        <td style="white-space:nowrap;font-size:.82rem;"><?=$ex['duration_minutes']?> min</td>
        <td><span class="badge badge--navy"><?=$qc?></span></td>
        <td><span class="badge <?=$ex['type']==='premium'?'badge--navy':'badge--gold'?>"><?=$ex['type']?></span></td>
        <td><span class="badge <?=$ex['status']==='published'?'badge--success':($ex['status']==='draft'?'badge--muted':'badge--danger')?>"><?=$ex['status']?></span></td>
        <td style="white-space:nowrap;">
          <a href="/admin/questions.php?exam_id=<?=$ex['id']?>" class="btn btn--sm btn--outline">Qs</a>
          <a href="?edit=<?=$ex['id']?>" class="btn btn--sm btn--primary">Edit</a>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
            <input type="hidden" name="action"  value="delete">
            <input type="hidden" name="exam_id" value="<?=$ex['id']?>">
            <button class="btn btn--sm btn--danger" data-confirm="Delete this exam and all its questions?">Del</button>
          </form>
        </td>
      </tr>
      <?php endforeach; if(!$exams): ?>
      <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted);">No exams yet.</td></tr>
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

<?php $isEdit=(bool)$editExam; $m=$editExam??[]; ?>
<div class="modal-backdrop <?=$isEdit?'open':''?>" id="examModal">
  <div class="modal-box">
    <div class="modal-header">
      <h2><?=$isEdit?'Edit':'New'?> Exam</h2>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
      <input type="hidden" name="action"  value="<?=$isEdit?'update':'create'?>">
      <?php if($isEdit): ?><input type="hidden" name="exam_id" value="<?=$m['id']?>"><?php endif; ?>
      <div class="form-group">
        <label>Exam Title</label>
        <input type="text" name="title" required value="<?=e($m['title']??'')?>" placeholder="Bar Council — CrPC 2024">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Category</label>
          <select name="category_id" required>
            <?php foreach($categories as $c): ?>
            <option value="<?=$c['id']?>" <?=($m['category_id']??0)==$c['id']?'selected':''?>><?=e($c['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Duration (min)</label>
          <input type="number" name="duration_minutes" min="5" max="300" value="<?=$m['duration_minutes']??60?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Pass Marks</label>
          <input type="number" name="pass_marks" step="0.5" value="<?=$m['pass_marks']??0?>">
        </div>
        <div class="form-group">
          <label>Mark Per Question</label>
          <input type="number" name="mark_per_q" step="0.25" value="<?=$m['mark_per_q']??1?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Negative Marking</label>
          <input type="number" name="negative_marking" step="0.25" min="0" value="<?=$m['negative_marking']??0?>">
        </div>
        <div class="form-group">
          <label>Type</label>
          <select name="type">
            <option value="free"    <?=($m['type']??'')==='free'?'selected':''?>>Free</option>
            <option value="premium" <?=($m['type']??'')==='premium'?'selected':''?>>Premium</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <option value="draft"     <?=($m['status']??'')==='draft'?'selected':''?>>Draft</option>
          <option value="published" <?=($m['status']??'')==='published'?'selected':''?>>Published</option>
          <option value="archived"  <?=($m['status']??'')==='archived'?'selected':''?>>Archived</option>
        </select>
      </div>
      <div class="form-group">
        <label>Instructions</label>
        <textarea name="instructions" rows="2"><?=e($m['instructions']??'')?></textarea>
      </div>
      <div style="display:flex;gap:.7rem;justify-content:flex-end;">
        <button type="button" class="btn btn--outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn--primary"><?=$isEdit?'Update':'Create'?> Exam</button>
      </div>
    </form>
  </div>
</div>

</main></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
