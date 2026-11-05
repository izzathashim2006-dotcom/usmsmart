<?php
// Application root, resolved once so controllers and helpers can build paths
// without depending on their own directory.
define('USM_ROOT', __DIR__);
// Per-tab authentication: each tab gets its own session cookie name, selected by a
// random tab token held in sessionStorage and sent on every request.
$usmTabToken = (string)($_GET['usm_tab'] ?? $_SERVER['HTTP_X_USM_TAB'] ?? '');
$usmTabTokenValid = (bool)preg_match('/^[A-Za-z0-9_-]{32,64}$/', $usmTabToken);
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $requestIsHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    // No token means an unauthenticated bootstrap request; never reuse a shared login cookie.
    session_name($usmTabTokenValid ? ('USMTAB_' . substr(hash('sha256', $usmTabToken), 0, 32)) : 'USMBOOT');
    session_set_cookie_params(array(
        'lifetime' => 0, 'path' => '/', 'secure' => $requestIsHttps,
        'httponly' => true, 'samesite' => 'Lax'
    ));
    session_start();
    if (!$usmTabTokenValid) { $_SESSION = array(); }
}

// Expire inactive sessions after 30 minutes. Rotate the ID to prevent reuse of the expired session.
$sessionNow = time();
if (isset($_SESSION['last_activity']) && ($sessionNow - (int)$_SESSION['last_activity']) > 1800) {
    $_SESSION = array();
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = $sessionNow;
if (empty($_SESSION['usmsmart_csrf_token'])) { $_SESSION['usmsmart_csrf_token'] = bin2hex(random_bytes(32)); }
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: SAMEORIGIN');
header('X-Permitted-Cross-Domain-Policies: none');
header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
// Credentials are not kept in this file. config/database.local.php is the
// per-installation, gitignored file; environment variables are the fallback.
$usmDbConfigFile = __DIR__ . '/config/database.local.php';
$usmDbConfig = is_file($usmDbConfigFile) ? require $usmDbConfigFile : array();
$host = (string)($usmDbConfig['host'] ?? (getenv('USM_DB_HOST') ?: 'localhost'));
$dbname = (string)($usmDbConfig['database'] ?? (getenv('USM_DB_NAME') ?: 'usmsmart_db'));
$username = (string)($usmDbConfig['username'] ?? (getenv('USM_DB_USER') ?: ''));
$password = (string)($usmDbConfig['password'] ?? (getenv('USM_DB_PASS') ?: ''));

try {
    $pdo = new PDO("mysql:host=" . $host . ";dbname=" . $dbname . ";charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    if (isset($_GET['action']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        error_log('USMSmart DB connection error: ' . $e->getMessage());
        echo json_encode(array('error' => 'Sambungan pangkalan data gagal. Sila hubungi pentadbir.'));
        exit;
    }
}

$action = isset($_GET['action']) ? (string)$_GET['action'] : '';

// Authentication and role-based access control (RBAC).
// The first visit shows a one-time administrator setup screen when no accounts exist.
$authReady = false;
try {
    if (isset($pdo)) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','viewer') NOT NULL DEFAULT 'viewer',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_login_at TIMESTAMP NULL DEFAULT NULL,
            auth_version INT UNSIGNED NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Add security columns to existing installations without changing current accounts.
        $authVersionColumns = $pdo->query("SHOW COLUMNS FROM usmsmart_users LIKE 'auth_version'")->fetchAll(PDO::FETCH_ASSOC);
        if (!$authVersionColumns) {
            $pdo->exec("ALTER TABLE usmsmart_users ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER last_login_at");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_login_attempts (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, username_key VARCHAR(80) NOT NULL, ip_hash CHAR(64) NOT NULL, attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_login_attempt_lookup (username_key, ip_hash, attempted_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Add account status to existing installations without affecting current users.
        $userColumns = $pdo->query("SHOW COLUMNS FROM usmsmart_users LIKE 'is_active'")->fetchAll(PDO::FETCH_ASSOC);
        if (!$userColumns) {
            $pdo->exec("ALTER TABLE usmsmart_users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role");
        }
        // ===== NEW: profile columns (display_name, email) =====
        $profileNameColumns = $pdo->query("SHOW COLUMNS FROM usmsmart_users LIKE 'display_name'")->fetchAll(PDO::FETCH_ASSOC);
        if (!$profileNameColumns) {
            $pdo->exec("ALTER TABLE usmsmart_users ADD COLUMN display_name VARCHAR(100) NULL AFTER role");
        }
        $profileEmailColumns = $pdo->query("SHOW COLUMNS FROM usmsmart_users LIKE 'email'")->fetchAll(PDO::FETCH_ASSOC);
        if (!$profileEmailColumns) {
            $pdo->exec("ALTER TABLE usmsmart_users ADD COLUMN email VARCHAR(190) NULL AFTER display_name");
        }
        // ===== NEW: profile picture. The timestamp doubles as the cache-busting version in the URL,
        // so one nullable column is the whole schema: no path, no mime, no size to keep in sync.
        $avatarColumns = $pdo->query("SHOW COLUMNS FROM usmsmart_users LIKE 'avatar_updated_at'")->fetchAll(PDO::FETCH_ASSOC);
        if (!$avatarColumns) {
            $pdo->exec("ALTER TABLE usmsmart_users ADD COLUMN avatar_updated_at TIMESTAMP NULL DEFAULT NULL AFTER email");
        }
        // ===== END NEW =====
        $authReady = true;
    }
} catch (Exception $e) {
    error_log('USMSmart auth table setup failed: ' . $e->getMessage());
}

if (!$authReady) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'USMSmart authentication could not be initialized. Check the database connection and CREATE TABLE permissions; dashboard access is disabled for safety.';
    exit;
}

// Revalidate the account on every request. auth_version revokes sessions after password/role/status changes.
if ($authReady && !empty($_SESSION['user_id'])) {
    try {
        $sessionAccountStmt = $pdo->prepare('SELECT username, role, is_active, auth_version, avatar_updated_at FROM usmsmart_users WHERE id = ? LIMIT 1');
        $sessionAccountStmt->execute(array((int)$_SESSION['user_id']));
        $sessionAccount = $sessionAccountStmt->fetch(PDO::FETCH_ASSOC);
        $sessionInvalid = !$sessionAccount || !(int)$sessionAccount['is_active']
            || (int)$sessionAccount['auth_version'] !== (int)($_SESSION['auth_version'] ?? 0);
        if ($sessionInvalid) {
            $_SESSION = array();
            session_regenerate_id(true);
            $_SESSION['usmsmart_csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['last_activity'] = time();
        } else {
            // Keep role and username sourced from the database, never solely from stale session data.
            $_SESSION['username'] = $sessionAccount['username'];
            $_SESSION['role'] = $sessionAccount['role'];
            // Cached on the session because the header and the rail render server-side and must not
            // issue a second query just to know whether the signed-in user has a picture.
            $_SESSION['avatar_updated_at'] = (string)($sessionAccount['avatar_updated_at'] ?? '');
        }
    } catch (Exception $e) {
        error_log('USMSmart session revalidation failed: ' . $e->getMessage());
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success'=>false,'message'=>'Sesi tidak dapat disahkan. Cuba lagi sebentar.'));
        exit;
    }
} elseif ($authReady && !empty($_SESSION['username'])) {
    // Invalidate legacy sessions created before user IDs/auth versions were stored.
    $_SESSION = array();
    session_regenerate_id(true);
    $_SESSION['usmsmart_csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['last_activity'] = time();
}

// Helpers are required before any route: the auth routes call auditLog().
require USM_ROOT . '/app/functions.php';

if ($authReady && in_array($action, array('setup_admin', 'login', 'logout', 'create_user', 'list_users', 'manage_user'), true)) {
    require USM_ROOT . '/controllers/auth.php';
}

if (in_array($action, array('login', 'setup_admin', 'logout'), true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // These routes are only for POST actions; GET should render the proper login/setup screen.
    $action = '';
}

// Defense in depth: every authenticated POST request outside the dedicated auth routes
// must carry the session CSRF token. The frontend fetch wrapper adds this header to POSTs.
// This covers older and newly added mutation endpoints without changing their payloads.
if ($authReady && $_SERVER['REQUEST_METHOD'] === 'POST'
    && !in_array($action, array('login', 'setup_admin', 'logout', 'manifest'), true)) {
    $requestCsrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['user_id']) || $requestCsrf === ''
        || !hash_equals((string)($_SESSION['usmsmart_csrf_token'] ?? ''), $requestCsrf)) {
        http_response_code(empty($_SESSION['user_id']) ? 401 : 403);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        echo json_encode(array(
            'success' => false,
            'error' => empty($_SESSION['user_id']) ? 'Sesi log masuk diperlukan.' : 'Token keselamatan tidak sah.',
            'message' => empty($_SESSION['user_id'])
                ? 'Sesi log masuk telah tamat. Sila log masuk semula.'
                : 'Token keselamatan tidak sah. Muat semula halaman dan cuba lagi.'
        ));
        exit;
    }
}

// Local Sysweb integration prototype: mock receiver only; never contacts real Sysweb.
require USM_ROOT . '/controllers/sysweb.php';

// Block all dashboard APIs until authenticated. Viewers may use read-only GET actions;
// all POST mutations, backups/restores, and exports require administrator privileges.
if ($authReady) {
    require USM_ROOT . '/controllers/dashboard-api.php';
}

// Require a session-bound CSRF token for every state-changing POST request.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    $csrf = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string)$_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    if ($csrf === '' || !hash_equals((string)$_SESSION['usmsmart_csrf_token'], $csrf)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success' => false, 'message' => 'Permintaan ditolak: token keselamatan tidak sah atau telah tamat. Muat semula halaman dan cuba lagi.'));
        exit;
    }
}

// Best-effort daily automation: the first authenticated application request each day creates a backup.
// For guaranteed off-hours backups, configure Windows Task Scheduler/cron to visit the app or run a CLI job.
if (isset($pdo) && !empty($_SESSION['user_id']) && $action !== 'restore_database') {
    try {
        $dir = usmsmartBackupDirectory();
        $dailyMarker = $dir . DIRECTORY_SEPARATOR . '.last_auto_backup_date';
        $today = date('Y-m-d');
        $lastDate = is_file($dailyMarker) ? trim((string)@file_get_contents($dailyMarker)) : '';
        if ($lastDate !== $today) {
            $lock = @fopen($dir . DIRECTORY_SEPARATOR . '.auto_backup.lock', 'c');
            if ($lock && flock($lock, LOCK_EX | LOCK_NB)) {
                $lastDate = is_file($dailyMarker) ? trim((string)@file_get_contents($dailyMarker)) : '';
                if ($lastDate !== $today) {
                    usmsmartWriteServerBackup($pdo, true);
                    @file_put_contents($dailyMarker, $today, LOCK_EX);
                }
                flock($lock, LOCK_UN); fclose($lock);
            } elseif ($lock) fclose($lock);
        }
    } catch (Exception $backupError) { usmsmartBackupLog('automatic_backup_failed', $backupError->getMessage()); error_log('USMSmart automatic backup failed: ' . $backupError->getMessage()); }
}

require USM_ROOT . '/controllers/system.php';

if ($action !== '') {
    require USM_ROOT . '/controllers/routes-reports.php';
    require USM_ROOT . '/controllers/routes-people.php';
    require USM_ROOT . '/controllers/routes-access.php';
    require USM_ROOT . '/controllers/routes-cards.php';
    require USM_ROOT . '/controllers/routes-sessions.php';
    require USM_ROOT . '/controllers/routes-backups.php';
}
require __DIR__ . '/views/dashboard.php';
