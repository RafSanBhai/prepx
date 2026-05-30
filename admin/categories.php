<?php
require_once __DIR__.'/../config.php';
requireRole('super_admin','teacher');
$pdo=db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action=$_POST['action']??'';
    if ($action==='delete') {
        $id=(int)$_POST['cat_id'];
        $cnt=(int)$pdo->prepare('SELECT COUNT(*) FROM exams WHERE category_id=?')->execute([$id]) && $pdo->query("SELECT COUNT(*) FROM exams WHERE category_id=$id")->fetchColumn();
        if ($cnt>0) setFlash('error','Cannot delete — exams exist in this category.');
        else { $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]); setFlash('info','Deleted.'); }
    } elseif (in_array($action,['create','update'])) {
        $name=trim($_POST['name']??''); $desc=trim($_POST['description']??'');
        $type=in_array($_POST['type'],['free','premium'])?$_POST['type']:'free';
        if (!$name) { setFlash('error','Name is required.'); }
        elseif ($action==='create') {
            $slug=strtolower(preg_replace('/[^a-zA-Z0-9]+/','-',$name));
            $base=$slug; $n=1;
            while((int)$pdo->query("SELECT COUNT(*) FROM categories WHERE slug='".addslashes($slug)."'")->fetchColumn()>0) $slug=$base.'-'.$n++;
            $pdo->prepare('INSERT INTO categories(name,slug,description,type,created_by) VALUES(?,?,?,?,?)')
                ->execute([$name,$slug,$desc,$type,$_SESSION['user_id']]);
            setFlash('success','Category created.');
        } else {
            $pdo->prepare('UPDATE categories SET name=?,description=?,type=? WHERE id=?')
                ->execute([$name,$desc,$type,(int)$_POST['cat_id']]);
            setFlash('success','Updated.');
        }
    }
    redirect('/admin/categories.php');
}

$editCat=null;
if (isset($_GET['edit'])) {
    $st=$pdo->prepare('SELECT * FROM categories WHERE id=? LIMIT 1');
    $st->execute([(int)$_GET['edit']]); $editCat=$st->fetch();
}
$cats=$pdo->query("SELECT c.*,(SELECT COUNT(*) FROM exams e WHERE e.category_id=c.id) AS exam_count FROM categories c ORDER BY c.name")->fetchAll();
$pageTitle='Categories';
?>
<?php require_once __DIR__.'/../includes/header.php'; ?>
<div class="layout">
<?php require_once __DIR__.'/../includes/admin_sidebar.php'; ?>
<main class="main-content">

<div class="topbar">
  <div><h1 class="topbar__title">Categories</h1><p class="topbar__sub">Organise PrepX exams by subject.</p></div>
  <button class="btn btn--primary" onclick="document.getElementById('catModal').classList.add('open')">＋ New Category</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Name</th><th>Slug</th><th>Type</th><th>Exams</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($cats as $i=>$cat): ?>
      <tr>
        <td><?=$i+1?></td>
        <td><strong><?=e($cat['name'])?></strong><?=$cat['description']?'<div style="font-size:.73rem;color:var(--text-muted);">'.e(substr($cat['description'],0,55)).'</div>':''?></td>
        <td><code style="font-size:.75rem;"><?=e($cat['slug'])?></code></td>
        <td><span class="badge <?=$cat['type']==='premium'?'badge--navy':'badge--gold'?>"><?=$cat['type']?></span></td>
        <td><?=$cat['exam_count']?></td>
        <td style="white-space:nowrap;">
          <a href="?edit=<?=$cat['id']?>" class="btn btn--sm btn--primary">Edit</a>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
            <input type="hidden" name="action"  value="delete">
            <input type="hidden" name="cat_id"  value="<?=$cat['id']?>">
            <button class="btn btn--sm btn--danger" data-confirm="Delete '<?=e($cat['name'])?>'?">Del</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php $isEdit=(bool)$editCat; $m=$editCat??[]; ?>
<div class="modal-backdrop <?=$isEdit?'open':''?>" id="catModal">
  <div class="modal-box" style="max-width:480px;">
    <div class="modal-header">
      <h2><?=$isEdit?'Edit':'New'?> Category</h2>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
      <input type="hidden" name="action" value="<?=$isEdit?'update':'create'?>">
      <?php if($isEdit): ?><input type="hidden" name="cat_id" value="<?=$m['id']?>"><?php endif; ?>
      <div class="form-group"><label>Name</label><input type="text" name="name" required value="<?=e($m['name']??'')?>"></div>
      <div class="form-group">
        <label>Type</label>
        <select name="type">
          <option value="free"    <?=($m['type']??'')==='free'?'selected':''?>>Free</option>
          <option value="premium" <?=($m['type']??'')==='premium'?'selected':''?>>Premium</option>
        </select>
      </div>
      <div class="form-group"><label>Description</label><textarea name="description" rows="2"><?=e($m['description']??'')?></textarea></div>
      <div style="display:flex;gap:.7rem;justify-content:flex-end;">
        <button type="button" class="btn btn--outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn--primary"><?=$isEdit?'Update':'Create'?></button>
      </div>
    </form>
  </div>
</div>

</main></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
