<?php
// config.php — PrepX
define('DB_HOST',    'localhost');
define('DB_NAME',    'your_database_name');
define('DB_USER',    'your_db_user');
define('DB_PASS',    'your_db_password');
define('DB_CHARSET', 'utf8mb4');
define('SITE_NAME',  'PrepX');
define('SITE_URL',   'https://yourdomain.com');
define('SESSION_LIFETIME', 3600 * 4);

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn  = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try { $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts); }
        catch (PDOException $e) {
            die('<p style="font-family:sans-serif;color:#c0392b;padding:2rem;">
                 Database connection failed. Check config.php.</p>');
        }
    }
    return $pdo;
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME, 'path' => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $url): never { header('Location: '.$url); exit; }
function requireLogin(): void { if (empty($_SESSION['user_id'])) redirect('/login.php'); }
function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) redirect('/login.php?err=access');
}
function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user === null) {
        $st = db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
        $st->execute([$_SESSION['user_id']]);
        $user = $st->fetch() ?: null;
    }
    return $user;
}
function isPremium(): bool { $u = currentUser(); return $u && $u['status']==='premium'; }
function hasCategoryAccess(int $catId): bool {
    $u = currentUser();
    if (!$u) return false;
    if (in_array($u['role'], ['super_admin','teacher'], true)) return true;
    if ($u['status'] === 'premium') return true;
    $st = db()->prepare('SELECT 1 FROM user_category_access WHERE user_id=? AND category_id=? LIMIT 1');
    $st->execute([$u['id'], $catId]);
    return (bool)$st->fetchColumn();
}
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verifyCsrf(): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? ''))
        die('CSRF validation failed.');
}
function generateActivationCode(string $txId=''): string {
    return strtoupper(substr(md5($txId.microtime(true)),0,8).bin2hex(random_bytes(4)));
}
function setFlash(string $type, string $msg): void { $_SESSION['flash']=['type'=>$type,'msg'=>$msg]; }
function getFlash(): ?array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }
