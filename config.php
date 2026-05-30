<?php
// config.php — PrepX (Updated for Supabase & Vercel)

// Use Vercel Environment Variables, fallback to local for testing
define('DB_HOST',    getenv('DB_HOST') ?: 'db.kfvxqjndcfbprxxapaqo.supabase.co');
define('DB_PORT',    getenv('DB_PORT') ?: '6543'); // Supabase Transaction Pooler Port
define('DB_NAME',    getenv('DB_NAME') ?: 'postgres');
define('DB_USER',    getenv('DB_USER') ?: 'postgres');
define('DB_PASS',    getenv('DB_PASSWORD') ?: 'shimaisadmin1819'); // Make sure you add this in Vercel!
define('DB_CHARSET', 'utf8');

define('SITE_NAME',  'PrepX');
define('SITE_URL',    getenv('SITE_URL') ?: 'https://yourdomain.com');
define('SESSION_LIFETIME', 3600 * 4);

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // Updated DSN for PostgreSQL
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        try { 
            // Postgres connection (Note: DB_CHARSET is handled differently in PgSQL, usually unnecessary in DSN)
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts); 
        }
        catch (PDOException $e) {
            // Clean error for production, hides sensitive connection strings
            error_log($e->getMessage());
            die('<p style="font-family:sans-serif;color:#c0392b;padding:2rem;">
                 Database connection failed. Please try again later.</p>');
        }
    }
    return $pdo;
}

// ... Rest of your functions (e, redirect, requireLogin, etc.) stay the same ...
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME, 'path' => '/',
        'secure'   => isset($_SERVER['HTTPS']) || getenv('VERCEL') === '1',
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();
}

// Keep the rest of your original functions exactly as they were
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $url): never { header('Location: '.$url); exit; }
function requireLogin(): void { if (empty($_SESSION['user_id'])) redirect('/login.php'); }
// ... (rest of your existing code)
