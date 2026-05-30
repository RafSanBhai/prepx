<?php
require_once __DIR__.'/config.php';
if (!empty($_SESSION['user_id'])) redirect('/student/dashboard.php');
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $name=trim($_POST['name']??''); $email=trim($_POST['email']??'');
    $phone=trim($_POST['phone']??''); $pass=$_POST['password']??''; $conf=$_POST['confirm']??'';
    if (!$name||!$email||!$pass) { $error='Name, email and password are required.'; }
    elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) { $error='Invalid email address.'; }
    elseif (strlen($pass)<8) { $error='Password must be at least 8 characters.'; }
    elseif ($pass!==$conf) { $error='Passwords do not match.'; }
    else {
        $chk=db()->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $chk->execute([$email]);
        if ($chk->fetch()) { $error='An account with that email already exists.'; }
        else {
            $hash=password_hash($pass,PASSWORD_BCRYPT);
            db()->prepare('INSERT INTO users(name,email,password,phone,role,status) VALUES(?,?,?,?,?,?)')
               ->execute([$name,$email,$hash,$phone,'student','free']);
            setFlash('success','Account created! Please log in.');
            redirect('/login.php');
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0f1b2d">
<title>Register | PrepX</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/main.css">
</head><body>
<div class="auth-page">
  <div class="auth-card" style="max-width:480px;">
    <div class="auth-card__logo">
      <div class="logo-icon">⚖</div>
      <h1>PrepX</h1>
      <p>Create your student account — it's free</p>
    </div>
    <?php if($error): ?>
    <div class="flash flash--error" style="position:static;margin-bottom:1rem;max-width:100%;"><?=e($error)?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" required value="<?=e($_POST['name']??'')?>" placeholder="Md. Rahim Uddin">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" required value="<?=e($_POST['email']??'')?>" placeholder="you@example.com">
        </div>
        <div class="form-group">
          <label>Phone (bKash/Nagad)</label>
          <input type="tel" name="phone" value="<?=e($_POST['phone']??'')?>" placeholder="01XXXXXXXXX">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" required placeholder="Min 8 characters">
        </div>
        <div class="form-group">
          <label>Confirm Password</label>
          <input type="password" name="confirm" required placeholder="Repeat password">
        </div>
      </div>
      <button type="submit" class="btn btn--primary btn--block btn--lg" style="margin-top:.3rem;">Create Account</button>
    </form>
    <p class="auth-link">Already have an account? <a href="/login.php">Sign in</a></p>
  </div>
</div>
</body></html>
