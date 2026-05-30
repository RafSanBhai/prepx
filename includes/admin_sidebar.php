<?php
$user = currentUser();
function isActive(string $f): string {
    return basename($_SERVER['PHP_SELF'])===$f ? 'active' : '';
}
?>
<div class="mobile-topbar">
  <button class="mobile-topbar__ham" id="sidebarHam" aria-label="Menu">☰</button>
  <span class="mobile-topbar__brand">⚖ PrepX</span>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<nav class="sidebar" id="sidebar">
  <div class="sidebar__brand">
    <span class="sidebar__logo">⚖</span>
    <div>
      <div class="sidebar__name">PrepX</div>
      <div class="sidebar__tagline">Admin Panel</div>
    </div>
  </div>
  <div class="sidebar__user">
    <div class="sidebar__avatar"><?= e(mb_substr($user['name'],0,1)) ?></div>
    <div>
      <div class="sidebar__uname"><?= e(mb_substr($user['name'],0,18)) ?></div>
      <div class="sidebar__role"><?= e(ucfirst(str_replace('_',' ',$user['role']))) ?></div>
    </div>
  </div>
  <ul class="sidebar__nav">
    <li class="sidebar__section">Overview</li>
    <li><a href="/admin/dashboard.php" class="<?= isActive('dashboard.php') ?>"><span class="nav-icon">⊞</span> Dashboard</a></li>

    <?php if (in_array($user['role'],['super_admin','teacher'])): ?>
    <li class="sidebar__section">Exam Management</li>
    <li><a href="/admin/exams.php" class="<?= isActive('exams.php') ?>"><span class="nav-icon">📋</span> Exams</a></li>
    <li><a href="/admin/questions.php" class="<?= isActive('questions.php') ?>"><span class="nav-icon">❓</span> Questions</a></li>
    <li><a href="/admin/categories.php" class="<?= isActive('categories.php') ?>"><span class="nav-icon">🗂</span> Categories</a></li>
    <li><a href="/admin/csv_import.php" class="<?= isActive('csv_import.php') ?>"><span class="nav-icon">📤</span> CSV Import</a></li>
    <?php endif; ?>

    <?php if ($user['role']==='super_admin'): ?>
    <li class="sidebar__section">Users &amp; Access</li>
    <li><a href="/admin/users.php" class="<?= isActive('users.php') ?>"><span class="nav-icon">👥</span> Manage Users</a></li>
    <li><a href="/admin/activation_codes.php" class="<?= isActive('activation_codes.php') ?>"><span class="nav-icon">🔑</span> Activation Codes</a></li>
    <li><a href="/admin/announcements.php" class="<?= isActive('announcements.php') ?>"><span class="nav-icon">📢</span> Announcements</a></li>
    <?php endif; ?>

    <li class="sidebar__section">Reports</li>
    <li><a href="/admin/results.php" class="<?= isActive('results.php') ?>"><span class="nav-icon">📊</span> Results</a></li>
    <li class="sidebar__divider"></li>
    <li><a href="/logout.php"><span class="nav-icon">⏻</span> Logout</a></li>
  </ul>
</nav>
