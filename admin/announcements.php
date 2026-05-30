<?php
require_once __DIR__.'/../config.php';
requireRole('super_admin');
$pdo=db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action=$_POST['action']??'';
    if ($action==='delete') {
        $pdo->prepare('DELETE FROM announcements WHERE id=?')->execute([(int)$_POST['ann_id']]);
        setFlash('info','Deleted.');
    } elseif (in_array($action,['create','update'])) {
        $title=trim($_POST['title']??''); $body=trim($_POST['body']??'');
        if ($title && $body) {
            if ($action==='create') {
                $pdo->prepare('INSERT INTO announcements(title,body,created_by) VALUES(?,?,?)')->execute([$title,$body,$_SESSION['user_id']]);
                setFlash('success','Published.');
            } else {
                $pdo->prepare('UPDATE announcements SET title=?,body=? WHERE id=?')->execute([$title,$body,(int)$_POST['ann_id']]);
                setFlash('success','Updated.');
            }
        } else setFlash('error','Title and body are required.');
    }
    redirect('/admin/announcements.php');
}
$editAnn=null;
if (isset($_GET['edit'])) {
    $st=$pdo->prepare('SELECT * FROM announcements WHERE id=? LIMIT 1');
    $st->execute([(int)$_GET['edit']]); $editAnn=$st->fetch();
}
$anns=$pdo->query("SELECT a.*,u.name AS author FROM announcements a LEFT JOIN users u ON u.id=a.created_by ORDER BY a.created_at DESC")->fetchAll();
$pageTitle='Announcements';
?>
<?php require_once __DIR__.'/../includes/header.php'; ?>
<div class="layout">
<?php require_once __DIR__.'/../includes/admin_sidebar.php'; ?>
<main class="main-content">

<div class="topbar">
  <div><h1 class="topbar__title">Announcements</h1><p class="topbar__sub">Publish notices on the student PrepX dashboard.</p></div>
  <button class="btn btn--primary" onclick="document.getElementById('annModal').classList.add('open')">📢 New Announcement</button>
</div>

<div class="card">
  <?php foreach($anns as $ann): ?>
  <div style="padding:.9rem 0;border-bottom:1px solid var(--border);">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.8rem;flex-wrap:wrap;">
      <div style="flex:1;">
        <div style="font-weight:700;font-size:.95rem;"><?=e($ann['title'])?></div>
        <div style="font-size:.83rem;color:var(--text-muted);margin:.3rem 0;"><?=nl2br(e($ann['body']))?></div>
        <div style="font-size:.72rem;color:var(--text-muted);">By <?=e($ann['author']?:'—')?> · <?=date('d M Y H:i',strtotime($ann['created_at']))?></div>
      </div>
      <div style="display:flex;gap:.4rem;flex-shrink:0;">
        <a href="?edit=<?=$ann['id']?>" class="btn btn--sm btn--primary">Edit</a>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <input type="hidden" name="action"  value="delete">
          <input type="hidden" name="ann_id"  value="<?=$ann['id']?>">
          <button class="btn btn--sm btn--danger" data-confirm="Delete this announcement?">Del</button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; if(!$anns): ?>
  <p style="color:var(--text-muted);padding:1rem 0;text-align:center;">No announcements yet.</p>
  <?php endif; ?>
</div>

<?php $isEdit=(bool)$editAnn; $m=$editAnn??[]; ?>
<div class="modal-backdrop <?=$isEdit?'open':''?>" id="annModal">
  <div class="modal-box" style="max-width:500px;">
    <div class="modal-header">
      <h2><?=$isEdit?'Edit':'New'?> Announcement</h2>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
      <input type="hidden" name="action" value="<?=$isEdit?'update':'create'?>">
      <?php if($isEdit): ?><input type="hidden" name="ann_id" value="<?=$m['id']?>"><?php endif; ?>
      <div class="form-group"><label>Title</label><input type="text" name="title" required value="<?=e($m['title']??'')?>"></div>
      <div class="form-group"><label>Message</label><textarea name="body" rows="4" required><?=e($m['body']??'')?></textarea></div>
      <div style="display:flex;gap:.7rem;justify-content:flex-end;">
        <button type="button" class="btn btn--outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn--primary">Publish</button>
      </div>
    </form>
  </div>
</div>

</main></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
