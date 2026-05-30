<?php
require_once __DIR__.'/../config.php';
requireRole('super_admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action=$_POST['action']??'';

    if ($action==='generate') {
        $txId      = trim($_POST['transaction_id']??'');
        $accType   = in_array($_POST['access_type'],['full','category'])?$_POST['access_type']:'full';
        $catIds    = '';
        if ($accType==='category') {
            $cats   = array_filter(array_map('intval',explode(',',$_POST['category_ids']??'')));
            $catIds = implode(',',$cats);
        }
        do {
            $code=generateActivationCode($txId);
            $chk=$pdo->prepare('SELECT id FROM activation_codes WHERE code=? LIMIT 1');
            $chk->execute([$code]);
        } while($chk->fetchColumn());
        $pdo->prepare('INSERT INTO activation_codes(code,transaction_id,access_type,category_ids,created_by) VALUES(?,?,?,?,?)')
            ->execute([$code,$txId,$accType,$catIds,$_SESSION['user_id']]);
        setFlash('success',"Code generated: $code");
    }
    elseif ($action==='delete') {
        $pdo->prepare('DELETE FROM activation_codes WHERE id=? AND is_used=0')->execute([(int)$_POST['code_id']]);
        setFlash('info','Unused code deleted.');
    }
    redirect('/admin/activation_codes.php');
}

$page=$max=1; $perPage=20; $page=max(1,(int)($_GET['page']??1)); $offset=($page-1)*$perPage;
$total=(int)$pdo->query('SELECT COUNT(*) FROM activation_codes')->fetchColumn();
$codes=$pdo->query(
    "SELECT ac.*,u.name AS used_by_name,u.email AS used_by_email,cu.name AS creator_name
     FROM activation_codes ac
     LEFT JOIN users u ON u.id=ac.assigned_to
     LEFT JOIN users cu ON cu.id=ac.created_by
     ORDER BY ac.created_at DESC LIMIT $perPage OFFSET $offset"
)->fetchAll();
$categories=$pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$totalPages=(int)ceil($total/$perPage);
$pageTitle='Activation Codes';
?>
<?php require_once __DIR__.'/../includes/header.php'; ?>
<div class="layout">
<?php require_once __DIR__.'/../includes/admin_sidebar.php'; ?>
<main class="main-content">

<div class="topbar">
  <div>
    <h1 class="topbar__title">Activation Codes</h1>
    <p class="topbar__sub">Generate codes for bKash/Nagad subscribers to upgrade their PrepX account.</p>
  </div>
</div>

<!-- Generate form -->
<div class="card">
  <div class="card__header"><h2 class="card__title">🔑 Generate New Code</h2></div>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
    <input type="hidden" name="action" value="generate">
    <div class="form-row">
      <div class="form-group">
        <label>bKash / Nagad Transaction ID</label>
        <input type="text" name="transaction_id" placeholder="e.g. 8N7H2X5M" required>
      </div>
      <div class="form-group">
        <label>Access Type</label>
        <select name="access_type" id="accessType">
          <option value="full">Full Premium Access</option>
          <option value="category">Category-Specific Access</option>
        </select>
      </div>
    </div>
    <div class="form-group" id="catWrap" style="display:none;">
      <label>Select Categories</label>
      <div style="display:flex;flex-wrap:wrap;gap:.6rem;margin-bottom:.5rem;">
        <?php foreach($categories as $cat): ?>
        <label style="display:flex;align-items:center;gap:.3rem;font-size:.85rem;text-transform:none;letter-spacing:0;font-weight:normal;cursor:pointer;">
          <input type="checkbox" class="cat-cb" value="<?=$cat['id']?>" style="width:auto;">
          <?=e($cat['name'])?>
        </label>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="category_ids" id="catIds">
    </div>
    <button type="submit" class="btn btn--gold">⚡ Generate 16-Digit Code</button>
  </form>
</div>

<!-- Codes list -->
<div class="card">
  <div class="card__header">
    <h2 class="card__title">All Codes (<?=$total?>)</h2>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Code</th><th>Transaction ID</th><th>Access</th><th>Status</th><th>Used By</th><th>Created</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php foreach($codes as $i=>$c): ?>
      <tr>
        <td><?=$offset+$i+1?></td>
        <td><code style="font-family:monospace;font-weight:700;color:var(--navy);letter-spacing:.06em;font-size:.82rem;"><?=e($c['code'])?></code></td>
        <td style="font-size:.8rem;"><?=e($c['transaction_id']?:'—')?></td>
        <td>
          <?php if($c['access_type']==='full'): ?>
          <span class="badge badge--navy">Full</span>
          <?php else: ?>
          <span class="badge badge--gold" title="Categories: <?=e($c['category_ids'])?>">Category</span>
          <?php endif; ?>
        </td>
        <td><?=$c['is_used']?'<span class="badge badge--success">Used</span>':'<span class="badge badge--muted">Unused</span>'?></td>
        <td style="font-size:.78rem;">
          <?=$c['used_by_name']?e($c['used_by_name']).'<br><span style="color:var(--text-muted);">'.e($c['used_by_email']).'</span>':'—'?>
        </td>
        <td style="font-size:.75rem;">
          <?=date('d M Y',strtotime($c['created_at']))?><br>
          <span style="color:var(--text-muted);">by <?=e($c['creator_name']?:'—')?></span>
        </td>
        <td>
          <?php if(!$c['is_used']): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
            <input type="hidden" name="action"  value="delete">
            <input type="hidden" name="code_id" value="<?=$c['id']?>">
            <button class="btn btn--sm btn--danger" data-confirm="Delete this unused code?">Delete</button>
          </form>
          <?php else: echo '—'; endif; ?>
        </td>
      </tr>
      <?php endforeach; if(!$codes): ?>
      <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted);">No codes yet.</td></tr>
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

</main></div>
<script>
document.getElementById('accessType').addEventListener('change',function(){
  document.getElementById('catWrap').style.display=this.value==='category'?'block':'none';
});
document.querySelectorAll('.cat-cb').forEach(cb=>cb.addEventListener('change',()=>{
  document.getElementById('catIds').value=[...document.querySelectorAll('.cat-cb:checked')].map(c=>c.value).join(',');
}));
</script>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
