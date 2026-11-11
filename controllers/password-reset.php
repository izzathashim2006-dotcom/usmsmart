<?php
// Self-service password reset routes, plus the administrator delivery queue.
// Included by index.php before the global CSRF gate; every handler exits, so the
// public endpoints verify the session CSRF token themselves (like controllers/auth.php)
// and the administrator endpoints verify both the token and the admin role.
if (!defined('USM_ROOT')) {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

$usmResetActions = array('forgot_password', 'reset_password', 'forgot_password_otp', 'verify_otp', 'reset_password_otp', 'list_reset_requests', 'admin_reset_link', 'cancel_reset_request');
if (!in_array($action, $usmResetActions, true)) { return; }

require_once USM_ROOT . '/app/password-reset.php';

$usmResetMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$usmResetIsPost = $usmResetMethod === 'POST';

// Every POST on this controller carries the session CSRF token, signed-in or not.
if ($usmResetIsPost) {
    $resetCsrf = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string)$_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    if ($resetCsrf === '' || !hash_equals((string)($_SESSION['usmsmart_csrf_token'] ?? ''), $resetCsrf)) {
        http_response_code(403); header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success' => false, 'message' => 'Token keselamatan tidak sah. Muat semula halaman dan cuba lagi.')); exit;
    }
}

// Administrator endpoints: role check plus the same CSRF gate the dashboard wrapper uses.
if (in_array($action, array('list_reset_requests', 'admin_reset_link', 'cancel_reset_request'), true)) {
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403); header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success' => false, 'message' => 'Akses pentadbir diperlukan.')); exit;
    }
}

if (!isset($pdo) || !usmsmartPasswordResetReady($pdo)) {
    if ($usmResetIsPost || $action === 'list_reset_requests') {
        http_response_code(503); header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success' => false, 'message' => 'Perkhidmatan set semula tidak tersedia buat masa ini. Sila hubungi pentadbir.')); exit;
    }
    $resetMode = 'invalid';
    $resetInvalidReason = 'unavailable';
    $csrfForReset = htmlspecialchars((string)($_SESSION['usmsmart_csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
    require USM_ROOT . '/views/password-reset.php';
    exit;
}

/* ===== Request a reset =================================================----- */

if ($action === 'forgot_password' && !$usmResetIsPost) {
    $resetMode = 'request';
    $resetPrefillUsername = trim((string)($_GET['username'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_.\-]{0,80}$/', $resetPrefillUsername)) { $resetPrefillUsername = ''; }
    $csrfForReset = htmlspecialchars((string)($_SESSION['usmsmart_csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
    require USM_ROOT . '/views/password-reset.php';
    exit;
}

if ($action === 'forgot_password' && $usmResetIsPost) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    $user = trim((string)($_POST['username'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_.\-]{3,80}$/', $user)) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'Nama pengguna mesti 3–80 aksara (huruf/nombor/._-).')); exit;
    }
    $usernameKey = strtolower(substr($user, 0, 80));
    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $throttle = usmsmartPasswordResetThrottle($pdo, $usernameKey, $ipHash);
    if ($throttle['blocked']) {
        if ($throttle['pair_hits'] >= 3) {
            auditLog($pdo, 'reset_throttled', 'user', null, null, 'Password reset throttled for username attempt "' . $usernameKey . '" from source ' . substr($ipHash, 0, 12));
        }
        http_response_code(429); header('Retry-After: 3600');
        echo json_encode(array('success' => false, 'message' => 'Terlalu banyak permintaan set semula. Cuba semula selepas satu jam.')); exit;
    }
    // Identical answer whether or not the account exists: no enumeration, and a small
    // constant delay keeps the timing of known and unknown names alike.
    $genericMessage = 'Jika nama pengguna ini berdaftar, permintaan set semula telah direkodkan. Semak e-mel berdaftar anda; jika akaun tiada e-mel, pentadbir akan menyediakan pautan set semula.';
    $stmt = $pdo->prepare('SELECT id, username, display_name, email, is_active FROM usmsmart_users WHERE username = ? LIMIT 1');
    $stmt->execute(array($user));
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($account && (int)$account['is_active'] === 1) {
        $latest = usmsmartPasswordResetLatest($pdo, (int)$account['id']);
        $withinCooldown = $latest && (time() - strtotime((string)$latest['created_at'])) < USM_RESET_RESEND_COOLDOWN;
        if (!$withinCooldown) {
            $email = trim((string)($account['email'] ?? ''));
            $delivery = 'admin';
            $issued = usmsmartPasswordResetCreate($pdo, (int)$account['id'], $ipHash, 'admin');
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $expiresLabel = date('d/m/Y H:i', strtotime($issued['expires_at']));
                $link = usmsmartPasswordResetLink($issued['token']);
                if (usmsmartSendResetEmail($email, (string)($account['display_name'] ?? ''), $link, $expiresLabel)) {
                    $delivery = 'email';
                    $mark = $pdo->prepare('UPDATE usmsmart_password_resets SET delivery = ? WHERE id = ?');
                    $mark->execute(array('email', $issued['request_id']));
                }
            }
            auditLog($pdo, 'password_reset_requested', 'user', (int)$account['id'], null,
                $delivery === 'email'
                    ? 'Self-service password reset requested; one-time link sent by email'
                    : 'Self-service password reset requested; one-time link awaiting administrator delivery');
        }
    }
    usleep(250000);
    echo json_encode(array('success' => true, 'message' => $genericMessage)); exit;
}

/* ===== Present and consume a token ========================================== */

if ($action === 'reset_password' && !$usmResetIsPost) {
    $token = (string)($_GET['token'] ?? '');
    $resetMode = 'invalid';
    $resetInvalidReason = 'missing';
    $resetUsername = '';
    $resetToken = '';
    $resetExpiresAt = '';
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $row = usmsmartPasswordResetFind($pdo, $token);
        if ($row && (int)$row['is_active'] === 1) {
            if ($row['used_at'] !== null) {
                $resetInvalidReason = 'used';
            } elseif (strtotime((string)$row['expires_at']) <= time()) {
                $resetInvalidReason = 'expired';
            } else {
                $resetMode = 'reset';
                $resetUsername = (string)$row['username'];
                $resetToken = $token;
                $resetExpiresAt = (string)$row['expires_at'];
            }
        }
    }
    $csrfForReset = htmlspecialchars((string)($_SESSION['usmsmart_csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
    require USM_ROOT . '/views/password-reset.php';
    exit;
}

if ($action === 'reset_password' && $usmResetIsPost) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    $token = (string)($_POST['token'] ?? '');
    $new = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'Pautan set semula tidak sah. Minta pautan baharu.', 'invalid_token' => true)); exit;
    }
    if (strlen($new) < 12 || strlen($new) > 72) {
        http_response_code(422);
        echo json_encode(array('success' => false, 'message' => 'Kata laluan baharu mesti 12–72 aksara.')); exit;
    }
    if ($new !== $confirm) {
        http_response_code(422);
        echo json_encode(array('success' => false, 'message' => 'Kata laluan baharu tidak sepadan.')); exit;
    }
    $row = usmsmartPasswordResetFind($pdo, $token);
    if (!$row || (int)$row['is_active'] !== 1 || $row['used_at'] !== null || strtotime((string)$row['expires_at']) <= time()) {
        http_response_code(410);
        echo json_encode(array('success' => false, 'message' => 'Pautan set semula telah tamat atau digunakan. Minta pautan baharu.', 'invalid_token' => true)); exit;
    }
    if (password_verify($new, (string)$row['password_hash'])) {
        http_response_code(422);
        echo json_encode(array('success' => false, 'message' => 'Kata laluan baharu mesti berbeza daripada kata laluan semasa.')); exit;
    }
    try {
        // Consume the token first: a racing second submit finds used_at set and fails.
        $consume = $pdo->prepare('UPDATE usmsmart_password_resets SET used_at = ? WHERE id = ? AND used_at IS NULL');
        $consume->execute(array(date('Y-m-d H:i:s'), (int)$row['request_id']));
        if ($consume->rowCount() !== 1) {
            http_response_code(410);
            echo json_encode(array('success' => false, 'message' => 'Pautan set semula telah digunakan. Minta pautan baharu.', 'invalid_token' => true)); exit;
        }
        $upd = $pdo->prepare('UPDATE usmsmart_users SET password_hash = ?, auth_version = auth_version + 1 WHERE id = ?');
        $upd->execute(array(password_hash($new, PASSWORD_DEFAULT), (int)$row['user_id']));
        $sweep = $pdo->prepare('DELETE FROM usmsmart_password_resets WHERE user_id = ? AND used_at IS NULL');
        $sweep->execute(array((int)$row['user_id']));
        auditLog($pdo, 'password_reset_completed', 'user', (int)$row['user_id'], null, 'Password reset via one-time link (' . ($row['delivery'] === 'email' ? 'email' : 'administrator') . ' delivery); all sessions revoked');
        echo json_encode(array('success' => true, 'message' => 'Kata laluan berjaya ditetapkan. Sila log masuk dengan kata laluan baharu.')); exit;
    } catch (Throwable $e) {
        error_log('USMSmart password reset failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(array('success' => false, 'message' => 'Kata laluan tidak dapat ditetapkan. Sila cuba lagi.')); exit;
    }
}

/* ===== OTP Request (Forgot Password via linked Gmail) ===== */
if ($action === 'forgot_password_otp' && $usmResetIsPost) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    $identifier = trim((string)($_POST['username'] ?? ''));
    if ($identifier === '') {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'Sila masukkan nama pengguna atau alamat Gmail anda.')); exit;
    }
    $identifierKey = strtolower(substr($identifier, 0, 80));
    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $throttle = usmsmartPasswordResetThrottle($pdo, 'otp:' . $identifierKey, $ipHash);
    if ($throttle['blocked']) {
        http_response_code(429); header('Retry-After: 900');
        echo json_encode(array('success' => false, 'message' => 'Terlalu banyak percubaan OTP. Cuba semula sebentar lagi.')); exit;
    }

    // Look up user by username OR by linked gmail OR by primary email
    $stmt = $pdo->prepare('SELECT id, username, display_name, email, gmail, is_active FROM usmsmart_users WHERE username = ? OR LOWER(gmail) = LOWER(?) OR LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute(array($identifier, $identifier, $identifier));
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account || !(int)$account['is_active']) {
        usleep(250000);
        http_response_code(404);
        echo json_encode(array('success' => false, 'message' => 'Akaun pengguna tidak ditemui atau akaun ini tidak aktif. Pastikan nama pengguna atau alamat Gmail adalah betul.')); exit;
    }

    // Check linked Gmail first, then fall back to email
    $targetEmail = trim((string)($account['gmail'] ?? ''));
    if ($targetEmail === '') {
        $targetEmail = trim((string)($account['email'] ?? ''));
    }

    if ($targetEmail === '' || !filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
        usleep(250000);
        http_response_code(400);
        echo json_encode(array(
            'success' => false,
            'has_gmail' => false,
            'message' => 'Akaun ini belum memautkan akaun Gmail. Sila hubungi pentadbir untuk mendapatkan pautan set semula.'
        )); exit;
    }

    $otpData = usmsmartCreateOtp($pdo, (int)$account['id'], $ipHash);
    $sent = usmsmartSendOtpEmail($targetEmail, (string)($account['display_name'] ?? $account['username']), $otpData['otp']);

    auditLog($pdo, 'otp_requested', 'user', (int)$account['id'], null, 'OTP requested for password reset to ' . substr($targetEmail, 0, 3) . '***@' . (explode('@', $targetEmail)[1] ?? ''));

    // Mask the email for user privacy display: e.g. "a***@gmail.com"
    $parts = explode('@', $targetEmail);
    $maskedName = substr($parts[0], 0, 2) . str_repeat('*', max(1, strlen($parts[0]) - 2));
    $maskedEmail = $maskedName . '@' . ($parts[1] ?? 'gmail.com');

    usleep(200000);
    echo json_encode(array(
        'success' => true,
        'user_id' => (int)$account['id'],
        'masked_email' => $maskedEmail,
        'message' => 'Kod OTP telah dihantar ke ' . $maskedEmail . '. Sila semak peti masuk Gmail anda.'
    )); exit;
}

/* ===== Verify OTP ===== */
if ($action === 'verify_otp' && $usmResetIsPost) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $otp = trim((string)($_POST['otp'] ?? ''));

    if (!$userId || strlen($otp) < 4) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'Kod OTP tidak sah. Sila masukkan 6 digit OTP.')); exit;
    }

    $stmt = $pdo->prepare('SELECT id, otp_code, expires_at FROM usmsmart_password_otps WHERE user_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1');
    $stmt->execute(array($userId));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || (string)$row['otp_code'] !== $otp) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'Kod OTP salah. Sila semak semula.')); exit;
    }

    if (strtotime((string)$row['expires_at']) <= time()) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'Kod OTP telah tamat tempoh. Sila minta kod baharu.')); exit;
    }

    // Create a temporary verified reset token so the user can set a new password
    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $issued = usmsmartPasswordResetCreate($pdo, $userId, $ipHash, 'otp');
    // Mark OTP as used
    $upd = $pdo->prepare('UPDATE usmsmart_password_otps SET used_at = CURRENT_TIMESTAMP WHERE id = ?');
    $upd->execute(array((int)$row['id']));

    echo json_encode(array(
        'success' => true,
        'reset_token' => $issued['token'],
        'message' => 'Kod OTP disahkan. Sila tetapkan kata laluan baharu anda.'
    )); exit;
}

/* ===== Administrator delivery queue ========================================= */

if ($action === 'list_reset_requests' && !$usmResetIsPost) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode(array('success' => true, 'requests' => usmsmartPasswordResetPending($pdo))); exit;
}

if ($action === 'admin_reset_link' && $usmResetIsPost) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    $id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $stmt = $pdo->prepare('SELECT id, username, is_active FROM usmsmart_users WHERE id = ? LIMIT 1');
    $stmt->execute(array($id));
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$id || !$target) {
        http_response_code(404);
        echo json_encode(array('success' => false, 'message' => 'Akaun tidak ditemui.')); exit;
    }
    if (!(int)$target['is_active']) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'Akaun ini nyahaktif. Aktifkan akaun dahulu sebelum menjana pautan set semula.')); exit;
    }
    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $issued = usmsmartPasswordResetCreate($pdo, (int)$target['id'], $ipHash, 'admin');
    auditLog($pdo, 'admin_reset_link_generated', 'user', (int)$target['id'], null, 'Administrator generated a one-time password reset link for ' . $target['username']);
    echo json_encode(array(
        'success' => true,
        'link' => usmsmartPasswordResetLink($issued['token']),
        'username' => (string)$target['username'],
        'expires_at' => $issued['expires_at'],
        'message' => 'Pautan set semula dijana. Sah selama 30 minit, sekali guna.'
    )); exit;
}

if ($action === 'cancel_reset_request' && $usmResetIsPost) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    $id = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
    $stmt = $pdo->prepare('SELECT r.id, r.user_id, u.username FROM usmsmart_password_resets r JOIN usmsmart_users u ON u.id = r.user_id WHERE r.id = ? AND r.used_at IS NULL LIMIT 1');
    $stmt->execute(array($id));
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$id || !$request) {
        http_response_code(404);
        echo json_encode(array('success' => false, 'message' => 'Permintaan tidak ditemui atau telah selesai.')); exit;
    }
    $del = $pdo->prepare('DELETE FROM usmsmart_password_resets WHERE id = ? AND used_at IS NULL');
    $del->execute(array((int)$request['id']));
    auditLog($pdo, 'reset_request_cancelled', 'user', (int)$request['user_id'], null, 'Administrator cancelled the pending password reset request for ' . $request['username']);
    echo json_encode(array('success' => true, 'message' => 'Permintaan set semula dibatalkan.')); exit;
}

// Any other method on these actions is not supported.
http_response_code(405);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(array('success' => false, 'message' => 'Kaedah permintaan tidak disokong.'));
exit;