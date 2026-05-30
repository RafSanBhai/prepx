<?php
require_once __DIR__.'/config.php';
if (!empty($_SESSION['user_id']))
    redirect(in_array($_SESSION['role'],['super_admin','teacher'])
        ? '/admin/dashboard.php' : '/student/dashboard.php');
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $email=trim($_POST['email']??''); $pass=$_POST['password']??'';
    if ($email && $pass) {
        $st=db()->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
        $st->execute([$email]); $u=$st->fetch();
        if ($u && password_verify($pass,$u['password'])) {
            db()->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$u['id']]);
            $_SESSION['user_id']=$u['id']; $_SESSION['role']=$u['role']; $_SESSION['name']=$u['name'];
            setFlash('success','Welcome back, '.$u['name'].'!');
            redirect(in_array($u['role'],['super_admin','teacher'])
                ? '/admin/dashboard.php' : '/student/dashboard.php');
        } else { $error='Invalid email or password.'; }
    } else { $error='Please enter your email and password.'; }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0f1b2d">
<title>Login | PrepX</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/main.css">
</head><body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-card__logo">
      <div class="logo-icon">⚖</div>
      <h1>PrepX</h1>
      <p>Bangladesh Bar Council &amp; BJS Exam Portal</p>
    </div>
    <?php if($error): ?>
    <div class="flash flash--error" style="position:static;margin-bottom:1rem;max-width:100%;"><?=e($error)?></div>
    <?php endif; $f=getFlash(); if($f): ?>
    <div class="flash flash--<?=e($f['type'])?>" style="position:static;margin-bottom:1rem;max-width:100%;"><?=e($f['msg'])?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required autocomplete="email"
               value="<?=e($_POST['email']??'')?>" placeholder="you@example.com">
      </div>
      <div class="form-group">
        <label for="pass">Password</label>
        <input type="password" id="pass" name="password" required autocomplete="current-password" placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn--primary btn--block btn--lg" style="margin-top:.4rem;">Sign In</button>
    </form>
    <p class="auth-link">No account? <a href="/register.php">Register free</a></p>
  </div>
</div>
</body></html>
