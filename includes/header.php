<?php
$pageTitle = $pageTitle ?? 'PrepX';
$flash     = getFlash();
$user      = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="theme-color" content="#0f1b2d">
<title><?= e($pageTitle) ?> | PrepX</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="role-<?= e($user['role'] ?? 'guest') ?>">
<?php if ($flash): ?>
<div class="flash flash--<?= e($flash['type']) ?>" id="flashMsg">
  <span><?= e($flash['msg']) ?></span>
  <button class="flash__close" onclick="this.parentElement.remove()">✕</button>
</div>
<?php endif; ?>
