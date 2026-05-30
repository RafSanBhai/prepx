<?php
require_once __DIR__.'/config.php';
if (!empty($_SESSION['user_id']))
    redirect(in_array($_SESSION['role'],['super_admin','teacher'])
        ? '/admin/dashboard.php' : '/student/dashboard.php');
redirect('/login.php');
