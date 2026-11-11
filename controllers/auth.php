<?php
// Login, logout, first-run setup and user administration routes.
// Included by index.php in the same scope, so it reads $action, $pdo,
// $authReady and $_SESSION directly. USM_ROOT always points at the app root.
if (!defined('USM_ROOT')) {
    // Reached directly instead of through index.php: these are includes, not endpoints.
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $authCsrf = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string)$_SERVER['HTTP_X_CSRF_TOKEN'] : '';
        if ($authCsrf === '' || !hash_equals((string)$_SESSION['usmsmart_csrf_token'], $authCsrf)) {
            http_response_code(403); header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('success'=>false,'message'=>'Token keselamatan tidak sah. Muat semula halaman dan cuba lagi.')); exit;
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    if ($action === 'setup_admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM usmsmart_users')->fetchColumn();
        $user = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        if ($count > 0) { http_response_code(409); echo json_encode(array('success'=>false,'message'=>'Pentadbir telah disediakan. Sila log masuk.')); exit; }
        if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $user) || strlen($pass) < 12) {
            http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Nama pengguna mesti 3–80 aksara (huruf/nombor/._-); kata laluan minimum 12 aksara.')); exit;
        }
        $stmt = $pdo->prepare('INSERT INTO usmsmart_users (username,password_hash,role) VALUES (?,?,?)');
        $stmt->execute(array($user, password_hash($pass, PASSWORD_DEFAULT), 'admin'));
        session_regenerate_id(true); $_SESSION['usmsmart_csrf_token'] = bin2hex(random_bytes(32)); $_SESSION['username'] = $user; $_SESSION['role'] = 'admin'; $_SESSION['user_id'] = (int)$pdo->lastInsertId(); $_SESSION['auth_version'] = 1; $_SESSION['last_activity'] = time();
        auditLog($pdo, 'setup_admin', 'user', $pdo->lastInsertId(), null, 'Initial administrator account created');
        echo json_encode(array('success'=>true,'message'=>'Akaun pentadbir berjaya dibuat.')); exit;
    }
    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = trim((string)($_POST['username'] ?? '')); $pass = (string)($_POST['password'] ?? '');
        $usernameKey = strtolower(substr($user, 0, 80));
        $remoteAddress = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ipHash = hash('sha256', $remoteAddress);
        // Two budgets. Five failures against one username/IP pair stop that pair; a wider per-IP
        // budget stops username rotation, which a per-pair limit never sees. The IP budget is much
        // looser on purpose: a campus NAT can put many honest users behind a single address.
        $pairLimit = 5;
        $ipLimit = 30;
        $cleanup = $pdo->prepare('DELETE FROM usmsmart_login_attempts WHERE attempted_at < (CURRENT_TIMESTAMP - INTERVAL 1 DAY)');
        $cleanup->execute();
        $countStmt = $pdo->prepare('SELECT COUNT(*) AS ip_hits, COALESCE(SUM(username_key = ?), 0) AS pair_hits FROM usmsmart_login_attempts WHERE ip_hash = ? AND attempted_at >= (CURRENT_TIMESTAMP - INTERVAL 15 MINUTE)');
        $countStmt->execute(array($usernameKey, $ipHash));
        $hits = $countStmt->fetch(PDO::FETCH_ASSOC) ?: array();
        $pairHits = (int)($hits['pair_hits'] ?? 0);
        $ipHits = (int)($hits['ip_hits'] ?? 0);
        if ($pairHits >= $pairLimit || $ipHits >= $ipLimit) {
            http_response_code(429); header('Retry-After: 900');
            echo json_encode(array('success'=>false,'message'=>'Terlalu banyak percubaan log masuk. Cuba semula selepas 15 minit.')); exit;
        }
        $stmt = $pdo->prepare('SELECT id, username, password_hash, role, is_active, auth_version FROM usmsmart_users WHERE username = ? OR LOWER(gmail) = LOWER(?) LIMIT 1');
        $stmt->execute(array($user, $user)); $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account || !(int)$account['is_active'] || !password_verify($pass, $account['password_hash'])) {
            $fail = $pdo->prepare('INSERT INTO usmsmart_login_attempts (username_key, ip_hash) VALUES (?, ?)');
            $fail->execute(array($usernameKey, $ipHash));
            // Mark the attempt that exhausts a budget, not the blocked retries that follow: once a
            // cap is reached the checks above exit early, so each row records the start of a lockout
            // instead of flooding the log for the rest of the window.
            if ($pairHits + 1 >= $pairLimit) {
                auditLog($pdo, 'login_throttled', 'user', null, null, 'Login throttled after ' . $pairLimit . ' failures for username attempt "' . $usernameKey . '" from source ' . substr($ipHash, 0, 12));
            }
            if ($ipHits + 1 >= $ipLimit) {
                auditLog($pdo, 'login_throttled', 'user', null, null, 'Login throttled after ' . $ipLimit . ' failures in 15 minutes from source ' . substr($ipHash, 0, 12));
            }
            usleep(250000); http_response_code(401); echo json_encode(array('success'=>false,'message'=>'Nama pengguna atau kata laluan tidak betul.')); exit;
        }
        $clearAttempts = $pdo->prepare('DELETE FROM usmsmart_login_attempts WHERE username_key = ? AND ip_hash = ?');
        $clearAttempts->execute(array($usernameKey, $ipHash));
        if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
            $rehash = $pdo->prepare('UPDATE usmsmart_users SET password_hash = ? WHERE id = ?');
            $rehash->execute(array(password_hash($pass, PASSWORD_DEFAULT), $account['id']));
        }
        session_regenerate_id(true); $_SESSION['usmsmart_csrf_token'] = bin2hex(random_bytes(32)); $_SESSION['username'] = $account['username']; $_SESSION['role'] = $account['role']; $_SESSION['user_id'] = (int)$account['id']; $_SESSION['auth_version'] = (int)$account['auth_version']; $_SESSION['last_activity'] = time();
        $upd = $pdo->prepare('UPDATE usmsmart_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?'); $upd->execute(array($account['id']));
        auditLog($pdo, 'login', 'user', $account['id'], null, 'Successful login');
        echo json_encode(array('success'=>true,'role'=>$account['role'])); exit;
    }
        /* ===== Sign in with Google Account (verified Google ID token only) ===== */
    if ($action === 'google_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $credential = trim((string)($_POST['credential'] ?? ''));
        if ($usmGoogleClientId === '') {
            http_response_code(503);
            echo json_encode(array('success' => false, 'message' => 'Log masuk Google belum diaktifkan oleh pentadbir. Sila log masuk dengan nama pengguna dan kata laluan.')); exit;
        }
        if ($credential === '') {
            http_response_code(422);
            echo json_encode(array('success' => false, 'message' => 'Kredensial Google diperlukan. Sila pilih akaun Google anda melalui butang Google.')); exit;
        }
        $payload = usmGoogleVerifyIdToken($credential, $usmGoogleClientId);
        if (!$payload) {
            usleep(250000); http_response_code(401);
            echo json_encode(array('success' => false, 'message' => 'Token Google tidak sah atau telah tamat tempoh. Sila pilih akaun Google anda semula.')); exit;
        }
        $googleEmail = strtolower(trim((string)$payload['email']));
        $googleId = trim((string)$payload['sub']);

        // Match the verified Google identity: linked google_id first, then the linked Gmail/email.
        $stmt = $pdo->prepare('SELECT id, username, role, is_active, auth_version, google_id FROM usmsmart_users WHERE google_id = ? LIMIT 1');
        $stmt->execute(array($googleId)); $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account) {
            $stmt = $pdo->prepare('SELECT id, username, role, is_active, auth_version, google_id FROM usmsmart_users WHERE LOWER(gmail) = LOWER(?) OR LOWER(email) = LOWER(?) LIMIT 1');
            $stmt->execute(array($googleEmail, $googleEmail)); $account = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$account || !(int)$account['is_active']) {
            usleep(250000);
            http_response_code(404);
            echo json_encode(array(
                'success' => false,
                'message' => 'Akaun Google (' . htmlspecialchars($googleEmail, ENT_QUOTES, 'UTF-8') . ') belum dipautkan ke mana-mana pengguna USMSmart. Sila log masuk dengan nama pengguna dahulu dan pautkan Google anda di Profil Saya.'
            )); exit;
        }

        // Admin via Google is reserved for verified USM email domains. This runs
        // after token verification and account lookup but BEFORE the session is
        // created, so a non-USM Google account can never obtain an admin session —
        // including when matched by the email fallback or after a role promotion.
        if (($account['role'] ?? '') === 'admin' && !usmGoogleEmailIsUsmDomain($googleEmail, $usmGoogleAdminDomains)) {
            usleep(250000);
            http_response_code(403);
            auditLog($pdo, 'google_login_denied', 'user', (int)$account['id'], null, 'Admin Google sign-in denied for non-USM email ' . $googleEmail);
            echo json_encode(array(
                'success' => false,
                'message' => 'Akses Admin melalui Google hanya untuk e-mel USM (@' . implode(', @', $usmGoogleAdminDomains) . '). Akaun Google ini bukan e-mel USM — sila log masuk dengan nama pengguna dan kata laluan.'
            )); exit;
        }

        session_regenerate_id(true);
        $_SESSION['usmsmart_csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['username'] = $account['username'];
        $_SESSION['role'] = $account['role'];
        $_SESSION['user_id'] = (int)$account['id'];
        $_SESSION['auth_version'] = (int)$account['auth_version'];
        $_SESSION['last_activity'] = time();

        // First Google sign-in for an account linked by email only: pin the Google id now.
        if (empty($account['google_id'])) {
            $pin = $pdo->prepare('UPDATE usmsmart_users SET google_id = ? WHERE id = ?');
            $pin->execute(array($googleId, $account['id']));
        }
        $upd = $pdo->prepare('UPDATE usmsmart_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?');
        $upd->execute(array($account['id']));
        auditLog($pdo, 'google_login', 'user', $account['id'], null, 'Successful login via linked Google account ' . $googleEmail);

        echo json_encode(array('success' => true, 'role' => $account['role'])); exit;
    }
    if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_SESSION['username'])) auditLog($pdo, 'logout', 'user', null, null, 'User logged out');
        $_SESSION = array();
        if (ini_get('session.use_cookies')) { $params = session_get_cookie_params(); setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']); }
        session_destroy(); echo json_encode(array('success'=>true)); exit;
    }
    if ($action === 'create_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Akses pentadbir diperlukan.')); exit; }
        $user = trim((string)($_POST['username'] ?? '')); $pass = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'viewer');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $user) || strlen($pass) < 12 || !in_array($role, array('admin','viewer'), true)) {
            http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Nama pengguna tidak sah, kata laluan mesti sekurang-kurangnya 12 aksara, dan peranan mesti admin/viewer.')); exit;
        }
        try { $stmt = $pdo->prepare('INSERT INTO usmsmart_users (username,password_hash,role) VALUES (?,?,?)'); $stmt->execute(array($user,password_hash($pass,PASSWORD_DEFAULT),$role)); auditLog($pdo,'create_user','user',$pdo->lastInsertId(),null,'Created account with role '.$role); echo json_encode(array('success'=>true,'message'=>'Akaun berjaya dibuat.')); }
        catch (PDOException $e) { http_response_code(409); echo json_encode(array('success'=>false,'message'=>'Nama pengguna telah digunakan atau akaun tidak dapat dibuat.')); }
        exit;
    }
    if ($action === 'list_users' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if (empty($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Akses pentadbir diperlukan.')); exit; }
        $rows = $pdo->query('SELECT id, username, role, is_active, created_at, last_login_at FROM usmsmart_users ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(array('success'=>true,'users'=>$rows)); exit;
    }
    if ($action === 'manage_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Akses pentadbir diperlukan.')); exit; }
        $id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        $operation = (string)($_POST['operation'] ?? '');
        $targetStmt = $pdo->prepare('SELECT id, username, role, is_active FROM usmsmart_users WHERE id = ? LIMIT 1');
        $targetStmt->execute(array($id)); $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
        if (!$id || !$target) { http_response_code(404); echo json_encode(array('success'=>false,'message'=>'Akaun tidak ditemui.')); exit; }
        $isSelf = strcasecmp((string)$target['username'], (string)$_SESSION['username']) === 0;
        if ($operation === 'update_role') {
            $newRole = (string)($_POST['role'] ?? '');
            if (!in_array($newRole, array('admin','viewer'), true)) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Peranan tidak sah.')); exit; }
            if ($isSelf && $newRole !== 'admin') { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Anda tidak boleh menurunkan peranan akaun sendiri.')); exit; }
            if ($target['role'] === 'admin' && $newRole !== 'admin') {
                $admins = (int)$pdo->query("SELECT COUNT(*) FROM usmsmart_users WHERE role='admin' AND is_active=1")->fetchColumn();
                if ($admins <= 1) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Sekurang-kurangnya seorang Admin aktif mesti dikekalkan.')); exit; }
            }
            $stmt = $pdo->prepare('UPDATE usmsmart_users SET role=?, auth_version=auth_version+1 WHERE id=?'); $stmt->execute(array($newRole,$id));
            auditLog($pdo,'update_user_role','user',$id,null,'Changed role to '.$newRole);
        } elseif ($operation === 'reset_password') {
            $newPass = (string)($_POST['password'] ?? '');
            if (strlen($newPass) < 12) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Kata laluan baharu mesti sekurang-kurangnya 12 aksara.')); exit; }
            $stmt = $pdo->prepare('UPDATE usmsmart_users SET password_hash=?, auth_version=auth_version+1 WHERE id=?'); $stmt->execute(array(password_hash($newPass,PASSWORD_DEFAULT),$id));
            auditLog($pdo,'reset_user_password','user',$id,null,'Administrator reset account password');
        } elseif ($operation === 'set_active') {
            $active = (int)($_POST['is_active'] ?? 0) === 1 ? 1 : 0;
            if ($isSelf && !$active) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Anda tidak boleh menyahaktifkan akaun sendiri.')); exit; }
            if ((int)$target['is_active'] === 1 && !$active && $target['role'] === 'admin') {
                $admins = (int)$pdo->query("SELECT COUNT(*) FROM usmsmart_users WHERE role='admin' AND is_active=1")->fetchColumn();
                if ($admins <= 1) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Sekurang-kurangnya seorang Admin aktif mesti dikekalkan.')); exit; }
            }
            $stmt = $pdo->prepare('UPDATE usmsmart_users SET is_active=?, auth_version=auth_version+1 WHERE id=?'); $stmt->execute(array($active,$id));
            auditLog($pdo,$active ? 'enable_user' : 'disable_user','user',$id,null,$active ? 'Account enabled' : 'Account disabled');
        } elseif ($operation === 'delete_user') {
            // Permanent: there is no undo, so the caller must echo the exact username back.
            $confirm = trim((string)($_POST['confirm_username'] ?? ''));
            if ($isSelf) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Anda tidak boleh memadam akaun sendiri.')); exit; }
            if (strcasecmp($confirm, (string)$target['username']) !== 0) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Taip nama pengguna dengan tepat untuk mengesahkan pemadaman.')); exit; }
            if ((int)$target['is_active'] === 1 && $target['role'] === 'admin') {
                $admins = (int)$pdo->query("SELECT COUNT(*) FROM usmsmart_users WHERE role='admin' AND is_active=1")->fetchColumn();
                if ($admins <= 1) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Sekurang-kurangnya seorang Admin aktif mesti dikekalkan.')); exit; }
            }
            $stmt = $pdo->prepare('DELETE FROM usmsmart_users WHERE id = ?'); $stmt->execute(array($id));
            if ($stmt->rowCount() !== 1) { http_response_code(409); echo json_encode(array('success'=>false,'message'=>'Akaun tidak dapat dipadam. Muat semula senarai dan cuba lagi.')); exit; }
            // The avatar is a file keyed by user id; remove it so a reused id cannot inherit a face.
            $avatarFile = usmsmartAvatarFile($id);
            if ($avatarFile !== null && is_file($avatarFile)) @unlink($avatarFile);
            auditLog($pdo,'delete_user','user',$id,null,'Deleted account '.$target['username'].' (role '.$target['role'].', active '.$target['is_active'].')');
        } else { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Operasi tidak sah.')); exit; }
        echo json_encode(array('success'=>true,'message'=>'Akaun berjaya dikemas kini.')); exit;
    }
