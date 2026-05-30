<?php
// admin/users.php — with remove premium, grant/revoke category, change role, delete
require_once __DIR__.'/../config.php';
requireRole('super_admin');
$pdo = db();

// ── Actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $uid    = (int)($_POST['user_id']??0);
    $action = $_POST['action']??'';

    if ($action==='set_premium' && $uid) {
        $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute(['premium',$uid]);
        setFlash('success','User upgraded to Premium.');
    }
    elseif ($action==='remove_premium' && $uid) {
        $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute(['free',$uid]);
        // Also wipe all their category-level grants
        $pdo->prepare('DELETE FROM user_category_access WHERE user_id=?')->execute([$uid]);
        setFlash('info','Premium access removed. User is now Free.');
    }
    elseif ($action==='change_role' && $uid) {
        $role=in_array($_POST['role'],['teacher','student'])?$_POST['role']:'student';
        $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role,$uid]);
        setFlash('success','Role updated.');
    }
    elseif ($action==='grant_cat' && $uid) {
        $catId=(int)($_POST['category_id']??0);
        if ($catId) {
            $pdo->prepare('INSERT IGNORE INTO user_category_access(user_id,category_id) VALUES(?,?)')->execute([$uid,$catId]);
            setFlash('success','Category access granted.');
        }
    }
    elseif ($action==='revoke_cat' && $uid) {
        $catId=(int)($_POST['category_id']??0);
        $pdo->prepare('DELETE FROM user_category_access WHERE user_id=? AND category_id=?')->execute([$uid,$catId]);
        setFlash('info','Category access revoked.');
    }
    elseif ($action==='delete_user' && $uid) {
        if ($uid!==$_SESSION['user_id']) {
            $pdo->prepare('DELETE FROM users WHERE id=? AND role!=?')->execute([$uid,'super_admin']);
            setFlash('info','User deleted.');
        }
    }
    redirect('/admin/users.php?'.http_build_query([
        'q'=>$_GET['q']??'','role'=>$_GET['role']??'','status'=>$_GET['status']??'','page'=>$_GET['page']??1
    ]));
}

// ── Search/Filter ─────────────────────────────────────────────
$search  = trim($_GET['q']??'');
$roleF   = $_GET['role']??'';
$statusF = $_GET['status']??'';
$page    = max(1,(int)($_GET['page']??1));
$perPage = 20; $offset=($page-1)*$perPage;

$where=['u.role!=?']; $params=['super_admin'];
if ($search)  { $where[]='(u.name LIKE ? OR u.email LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; }
if ($roleF)   { $where[]='u.role=?';   $params[]=$roleF; }
if ($statusF) { $where[]='u.status=?'; $params[]=$statusF; }
$whereSQL='WHERE '.implode(' AND ',$where);

$cntSt=$pdo->prepare("SELECT COUNT(*) FROM users u $whereSQL");
$cntSt->execute($params); $total=(int)$cntSt->fetchColumn();

$st=$pdo->prepare("SELECT u.* FROM users u $whereSQL ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset");
$st->execute($params); $users=$st->fetchAll();
$totalPages=(int)ceil($total/$perPage);

$categories=$pdo->query("SELECT id,name FROM categories ORDER BY name")->fetchAll();
$pageTitle='Manage Users';
?>
<?php require_once __DIR__.'/../includes/header.php'; ?>
<div class="layout">
<?php require_once __DIR__.'/../includes/admin_sidebar.php'; ?>
<main class="main-content">

<div class="topbar">
  <div>
    <h1 class="topbar__title">Manage Users</h1>
    <p class="topbar__sub">Control premium access, roles, and category permissions.</p>
  </div>
  <div class="stats-grid" style="margin:0;flex:1;justify-content:flex-end;max-width:380px;">
    <div class="stat-card" style="margin:0;">
      <span class="stat-card__icon">👥</span>
      <div class="stat-card__label">Total Users</div>
      <div class="stat-card__value"><?=$total?></div>
    </div>
  </div>
</div>

<!-- Filter bar -->
<div class="card" style="padding:1rem 1.2rem;">
  <form method="GET" style="display:flex;gap:.7rem;flex-wrap:wrap;align-items:flex-end;">
    <div style="flex:1;min-width:160px;" class="form-group" style="margin:0;">
      <label>Search</label>
      <input type="text" name="q" value="<?=e($search)?>" placeholder="Name or email…">
    </div>
    <div class="form-group" style="margin:0;">
      <label>Role</label>
      <select name="role">
        <option value="">All Roles</option>
        <option value="teacher" <?=$roleF==='teacher'?'selected':''?>>Teacher</option>
        <option value="student" <?=$roleF==='student'?'selected':''?>>Student</option>
      </select>
    </div>
    <div class="form-group" style="margin:0;">
      <label>Status</label>
      <select name="status">
        <option value="">All</option>
        <option value="free"    <?=$statusF==='free'?'selected':''?>>Free</option>
        <option value="premium" <?=$statusF==='premium'?'selected':''?>>Premium</option>
      </select>
    </div>
    <div style="padding-top:1.4rem;display:flex;gap:.5rem;">
      <button type="submit" class="btn btn--primary btn--sm">Search</button>
      <a href="/admin/users.php" class="btn btn--outline btn--sm">Reset</a>
    </div>
  </form>
</div>

<!-- User cards (mobile-friendly card layout + table on desktop) -->
<div class="card" style="padding:0;overflow:hidden;">

  <!-- Desktop table -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Name / Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Joined</th><th style="min-width:260px;">Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach($users as $i=>$u):
        $catAcc=$pdo->prepare('SELECT c.id,c.name FROM user_category_access uca JOIN categories c ON c.id=uca.category_id WHERE uca.user_id=?');
        $catAcc->execute([$u['id']]); $userCats=$catAcc->fetchAll();
      ?>
      <tr>
        <td><?=$offset+$i+1?></td>
        <td>
          <div style="font-weight:600;font-size:.88rem;"><?=e($u['name'])?></div>
          <div style="font-size:.73rem;color:var(--text-muted);"><?=e($u['email'])?></div>
        </td>
        <td style="font-size:.82rem;"><?=e($u['phone']?:' — ')?></td>
        <td>
          <!-- Role change -->
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
            <input type="hidden" name="action" value="change_role">
            <input type="hidden" name="user_id" value="<?=$u['id']?>">
            <select name="role" onchange="this.form.submit()" style="font-size:.75rem;padding:.2rem .4rem;border-radius:6px;border:1px solid var(--border);background:#fff;">
              <option value="student" <?=$u['role']==='student'?'selected':''?>>Student</option>
              <option value="teacher" <?=$u['role']==='teacher'?'selected':''?>>Teacher</option>
            </select>
          </form>
        </td>
        <td>
          <?php if($u['status']==='premium'): ?>
            <span class="badge badge--navy">⭐ Premium</span>
            <?php if($userCats): ?>
            <div style="font-size:.68rem;color:var(--text-muted);margin-top:.2rem;">
              <?=e(implode(', ',array_column($userCats,'name')))?>
            </div>
            <?php endif; ?>
          <?php else: ?>
            <span class="badge badge--muted">Free</span>
          <?php endif; ?>
        </td>
        <td style="font-size:.75rem;"><?=date('d M Y',strtotime($u['created_at']))?></td>
        <td>
          <div style="display:flex;flex-wrap:wrap;gap:.35rem;">

            <?php if($u['status']!=='premium'): ?>
            <!-- Grant full premium -->
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
              <input type="hidden" name="action"  value="set_premium">
              <input type="hidden" name="user_id" value="<?=$u['id']?>">
              <button class="btn btn--sm btn--success" title="Grant full premium access">⭐ Grant Premium</button>
            </form>
            <?php else: ?>
            <!-- Remove premium -->
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
              <input type="hidden" name="action"  value="remove_premium">
              <input type="hidden" name="user_id" value="<?=$u['id']?>">
              <button class="btn btn--sm btn--danger"
                data-confirm="Remove premium access from <?=e($u['name'])?>? This will also revoke all category access.">
                🚫 Remove Premium
              </button>
            </form>
            <?php endif; ?>

            <!-- Category access button -->
            <button class="btn btn--sm btn--outline"
              onclick="document.getElementById('catModal_<?=$u['id']?>').classList.add('open')"
              title="Manage category access">🗂 Category</button>

            <!-- Delete -->
            <?php if($u['id']!==$_SESSION['user_id']): ?>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
              <input type="hidden" name="action"  value="delete_user">
              <input type="hidden" name="user_id" value="<?=$u['id']?>">
              <button class="btn btn--sm btn--danger"
                data-confirm="Permanently delete user <?=e($u['name'])?>? All their data will be lost.">🗑 Delete</button>
            </form>
            <?php endif; ?>

          </div>
        </td>
      </tr>

      <!-- Category Access Modal for this user -->
      <tr style="display:none;"><td colspan="7"><!-- spacer --></td></tr>
      <?php endforeach; ?>
      <?php if(!$users): ?>
      <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted);">No users found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if($totalPages>1): ?>
  <div class="pagination" style="padding:1rem;">
    <?php for($p=1;$p<=$totalPages;$p++): ?>
    <a href="?page=<?=$p?>&q=<?=urlencode($search)?>&role=<?=urlencode($roleF)?>&status=<?=urlencode($statusF)?>"
       class="<?=$p===$page?'active':''?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Category Access Modals (one per user) -->
<?php
// Re-fetch users for modals
$st2=$pdo->prepare("SELECT u.* FROM users u $whereSQL ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset");
$st2->execute($params); $usersM=$st2->fetchAll();
foreach($usersM as $u):
    $catAcc=$pdo->prepare('SELECT c.id,c.name FROM user_category_access uca JOIN categories c ON c.id=uca.category_id WHERE uca.user_id=?');
    $catAcc->execute([$u['id']]); $userCats=$catAcc->fetchAll();
?>
<div class="modal-backdrop" id="catModal_<?=$u['id']?>">
  <div class="modal-box" style="max-width:480px;">
    <div class="modal-header">
      <h2>🗂 Category Access — <?=e($u['name'])?></h2>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:1rem;">
      Grant access to specific premium categories without making the user fully Premium.
    </p>

    <!-- Current grants -->
    <?php if($userCats): ?>
    <div style="margin-bottom:1rem;">
      <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:.5rem;">Current Access</div>
      <?php foreach($userCats as $uc): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:.4rem .6rem;background:var(--gold-pale);border-radius:8px;margin-bottom:.3rem;">
        <span style="font-size:.85rem;font-weight:500;"><?=e($uc['name'])?></span>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="csrf_token"  value="<?=csrfToken()?>">
          <input type="hidden" name="action"      value="revoke_cat">
          <input type="hidden" name="user_id"     value="<?=$u['id']?>">
          <input type="hidden" name="category_id" value="<?=$uc['id']?>">
          <button type="submit" class="btn btn--sm btn--danger"
            data-confirm="Revoke '<?=e($uc['name'])?>' access?">✕ Revoke</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="font-size:.83rem;color:var(--text-muted);margin-bottom:1rem;">No category-level access granted yet.</p>
    <?php endif; ?>

    <!-- Grant new -->
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
      <input type="hidden" name="action"     value="grant_cat">
      <input type="hidden" name="user_id"    value="<?=$u['id']?>">
      <div class="form-group">
        <label>Grant Access To Category</label>
        <select name="category_id" required>
          <option value="">— Select Category —</option>
          <?php foreach($categories as $cat): ?>
          <option value="<?=$cat['id']?>"><?=e($cat['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:.6rem;justify-content:flex-end;">
        <button type="button" class="btn btn--outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn--primary">Grant Access</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

</main></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
