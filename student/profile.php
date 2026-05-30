<?php
require_once __DIR__.'/../config.php';
requireRole('student');
$pdo=db(); $user=currentUser();

// Redeem code
if ($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='redeem') {
    verifyCsrf();
    $code=strtoupper(trim($_POST['activation_code']??''));
    if (!$code) { setFlash('error','Please enter an activation code.'); }
    else {
        $st=$pdo->prepare('SELECT * FROM activation_codes WHERE code=? LIMIT 1');
        $st->execute([$code]); $rec=$st->fetch();
        if (!$rec) { setFlash('error','Invalid activation code.'); }
        elseif ($rec['is_used']) { setFlash('error','This code has already been used.'); }
        else {
            if ($rec['access_type']==='full') {
                $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute(['premium',$user['id']]);
            } elseif ($rec['access_type']==='category'&&$rec['category_ids']) {
                $cats=array_filter(array_map('intval',explode(',',$rec['category_ids'])));
                $ins=$pdo->prepare('INSERT IGNORE INTO user_category_access(user_id,category_id) VALUES(?,?)');
                foreach($cats as $cid) $ins->execute([$user['id'],$cid]);
            }
            $pdo->prepare('UPDATE activation_codes SET is_used=1,assigned_to=?,used_at=NOW() WHERE id=?')
                ->execute([$user['id'],$rec['id']]);
            setFlash('success','🎉 Code activated! Your access has been upgraded.');
            redirect('/student/profile.php');
        }
    }
}
// Update profile
if ($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='update') {
    verifyCsrf();
    $name=trim($_POST['name']??''); $phone=trim($_POST['phone']??'');
    if ($name) {
        $pdo->prepare('UPDATE users SET name=?,phone=? WHERE id=?')->execute([$name,$phone,$user['id']]);
        $_SESSION['name']=$name; setFlash('success','Profile updated.'); redirect('/student/profile.php');
    }
}
// Change password
if ($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='password') {
    verifyCsrf();
    $cur=$_POST['current']??''; $new=$_POST['new']??''; $conf=$_POST['confirm']??'';
    if (!password_verify($cur,$user['password'])) { setFlash('error','Current password is incorrect.'); }
    elseif (strlen($new)<8) { setFlash('error','New password must be at least 8 characters.'); }
    elseif ($new!==$conf) { setFlash('error','Passwords do not match.'); }
    else {
        $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($new,PASSWORD_BCRYPT),$user['id']]);
        setFlash('success','Password changed.'); redirect('/student/profile.php');
    }
}

// Re-fetch user (after possible update)
$st=$pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1'); $st->execute([$user['id']]); $user=$st->fetch();
$myCats=$pdo->prepare('SELECT c.name FROM user_category_access uca JOIN categories c ON c.id=uca.category_id WHERE uca.user_id=?');
$myCats->execute([$user['id']]); $myCatNames=$myCats->fetchAll(PDO::FETCH_COLUMN);
$pageTitle='My Profile';
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

<div class="container" style="max-width:780px;padding-top:1.8rem;padding-bottom:3rem;">
  <h1 style="font-family:var(--font-serif);font-size:1.7rem;color:var(--navy);margin-bottom:1.4rem;">My Profile</h1>

  <!-- Status Banner -->
  <div style="background:<?=$user['status']==='premium'?'var(--navy)':'var(--gold-pale)'?>;color:<?=$user['status']==='premium'?'#fff':'var(--navy)'?>;border-radius:var(--radius-lg);padding:1.2rem 1.4rem;margin-bottom:1.4rem;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
      <div>
        <div style="font-weight:700;font-size:1rem;"><?=$user['status']==='premium'?'⭐ Premium Member':'🔓 Free Account'?></div>
        <div style="font-size:.82rem;opacity:.75;margin-top:.2rem;">
          <?=$user['status']==='premium'?'Full access to all PrepX premium exam sets.':'Upgrade to access Bar Council &amp; BJS premium sets.'?>
        </div>
        <?php if($myCatNames): ?>
        <div style="font-size:.78rem;margin-top:.4rem;opacity:.8;">Category access: <?=e(implode(', ',$myCatNames))?></div>
        <?php endif; ?>
      </div>
      <?php if($user['status']!=='premium'): ?>
      <div style="background:rgba(255,255,255,.85);border-radius:var(--radius);padding:.7rem 1rem;font-size:.8rem;color:var(--navy);min-width:180px;">
        <strong>How to upgrade:</strong><br>
        1. Pay via <strong>bKash/Nagad</strong><br>
        2. Share transaction ID with admin<br>
        3. Enter your code below ↓
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Activation Code -->
  <div class="card">
    <div class="card__header"><h2 class="card__title">🔑 Activate Code</h2></div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
      <input type="hidden" name="action" value="redeem">
      <div style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:flex-end;">
        <div style="flex:1;min-width:200px;" class="form-group" style="margin:0;">
          <label>16-Character Activation Code</label>
          <input type="text" name="activation_code" placeholder="e.g. A3F9B2C1D8E7F0A4"
                 maxlength="20" style="font-family:monospace;letter-spacing:.1em;font-size:.97rem;text-transform:uppercase;">
        </div>
        <button type="submit" class="btn btn--gold btn--lg" style="margin-bottom:1.1rem;">Activate</button>
      </div>
    </form>
  </div>

  <!-- Edit Profile -->
  <div class="card">
    <div class="card__header"><h2 class="card__title">Edit Profile</h2></div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
      <input type="hidden" name="action" value="update">
      <div class="form-row">
        <div class="form-group"><label>Full Name</label><input type="text" name="name" required value="<?=e($user['name'])?>"></div>
        <div class="form-group"><label>Phone (bKash/Nagad)</label><input type="tel" name="phone" value="<?=e($user['phone']??'')?>" placeholder="01XXXXXXXXX"></div>
      </div>
      <div class="form-group">
        <label>Email (read-only)</label>
        <input type="email" value="<?=e($user['email'])?>" disabled style="background:#f5f5f5;cursor:not-allowed;">
      </div>
      <button type="submit" class="btn btn--primary">Save Changes</button>
    </form>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="card__header"><h2 class="card__title">Change Password</h2></div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
      <input type="hidden" name="action" value="password">
      <div class="form-group"><label>Current Password</label><input type="password" name="current" required></div>
      <div class="form-row">
        <div class="form-group"><label>New Password</label><input type="password" name="new" required placeholder="Min 8 characters"></div>
        <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm" required></div>
      </div>
      <button type="submit" class="btn btn--primary">Change Password</button>
    </form>
  </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
