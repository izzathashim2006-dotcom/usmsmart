<?php
// Passkey (WebAuthn) routes: enrol/remove a credential while signed in, and sign in with one.
// Included by index.php in the same scope, so it reads $action, $pdo and $_SESSION directly.
if (!defined('USM_ROOT')) {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

require_once USM_ROOT . '/app/webauthn.php';

$usmPasskeyActions = array(
    'webauthn_login_options', 'webauthn_login_verify',
    'webauthn_register_options', 'webauthn_register_verify',
    'webauthn_list_credentials', 'webauthn_remove_credential'
);
if (!in_array($action, $usmPasskeyActions, true)) { return; }

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function usmPasskeyOut($status, array $payload) {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function usmPasskeyBody() {
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : array();
}

// The login endpoints run before authentication, so they check the session CSRF token themselves,
// exactly like the password login route. Signed-in endpoints are also covered by index.php's
// global check, but keeping one check here means the rules are visible in one place.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $passkeyCsrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($passkeyCsrf === '' || !hash_equals((string)($_SESSION['usmsmart_csrf_token'] ?? ''), $passkeyCsrf)) {
        usmPasskeyOut(403, array('success' => false, 'message' => 'Token keselamatan tidak sah. Muat semula halaman dan cuba lagi.'));
    }
}

// Mirrors the password login budgets in controllers/auth.php: five failures for one username/IP
// pair, thirty per IP per 15 minutes. The table and columns are shared on purpose, so switching
// between password and passkey cannot reset an attacker's budget.
function usmPasskeyThrottleState($pdo, $usernameKey, $ipHash) {
    $pdo->prepare('DELETE FROM usmsmart_login_attempts WHERE attempted_at < (CURRENT_TIMESTAMP - INTERVAL 1 DAY)')->execute();
    $stmt = $pdo->prepare('SELECT COUNT(*) AS ip_hits, COALESCE(SUM(username_key = ?), 0) AS pair_hits FROM usmsmart_login_attempts WHERE ip_hash = ? AND attempted_at >= (CURRENT_TIMESTAMP - INTERVAL 15 MINUTE)');
    $stmt->execute(array($usernameKey, $ipHash));
    $hits = $stmt->fetch(PDO::FETCH_ASSOC) ?: array();
    return array('pair' => (int)($hits['pair_hits'] ?? 0), 'ip' => (int)($hits['ip_hits'] ?? 0));
}

function usmPasskeyThrottleFail($pdo, $usernameKey, $ipHash, $state) {
    $pdo->prepare('INSERT INTO usmsmart_login_attempts (username_key, ip_hash) VALUES (?, ?)')->execute(array($usernameKey, $ipHash));
    if ($state['pair'] + 1 >= 5) {
        auditLog($pdo, 'login_throttled', 'user', null, null, 'Passkey login throttled after 5 failures for username attempt "' . $usernameKey . '" from source ' . substr($ipHash, 0, 12));
    }
    if ($state['ip'] + 1 >= 30) {
        auditLog($pdo, 'login_throttled', 'user', null, null, 'Passkey login throttled after 30 failures in 15 minutes from source ' . substr($ipHash, 0, 12));
    }
}

function usmPasskeyThrottleBlocked(array $state) {
    return $state['pair'] >= 5 || $state['ip'] >= 30;
}

function usmPasskeyDecodeFields(array $body, array $fields) {
    $out = array();
    foreach ($fields as $field) {
        $value = usmsmartB64UrlDecode((string)($body[$field] ?? ''));
        if ($value === false || $value === '') { return null; }
        $out[$field] = $value;
    }
    return $out;
}

function usmPasskeyClientIpHash() {
    return hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

/* ---------------------------------------------------------------- login */

if ($action === 'webauthn_login_options') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { usmPasskeyOut(405, array('success' => false, 'message' => 'Kaedah tidak dibenarkan.')); }
    $webAuthn = usmsmartWebAuthnInstance();
    if ($webAuthn === null) { usmPasskeyOut(400, array('success' => false, 'message' => 'Passkey memerlukan sambungan HTTPS atau localhost.')); }
    $body = usmPasskeyBody();
    $username = trim((string)($body['username'] ?? ''));
    if ($username === '') { usmPasskeyOut(400, array('success' => false, 'message' => 'Masukkan nama pengguna dahulu.')); }
    $usernameKey = strtolower(substr($username, 0, 80));
    $ipHash = usmPasskeyClientIpHash();
    $state = usmPasskeyThrottleState($pdo, $usernameKey, $ipHash);
    if (usmPasskeyThrottleBlocked($state)) {
        header('Retry-After: 900');
        usmPasskeyOut(429, array('success' => false, 'message' => 'Terlalu banyak percubaan log masuk. Cuba semula selepas 15 minit.'));
    }
    try {
        usmsmartWebAuthnEnsureTable($pdo);
        $stmt = $pdo->prepare('SELECT id, username, is_active FROM usmsmart_users WHERE username = ? LIMIT 1');
        $stmt->execute(array($username));
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account || !(int)$account['is_active']) {
            usmPasskeyOut(404, array('success' => false, 'message' => 'Passkey tidak tersedia untuk akaun ini. Sila log masuk dengan kata laluan.'));
        }
        $rows = usmsmartWebAuthnCredentialRows($pdo, (int)$account['id']);
        if (!$rows) {
            usmPasskeyOut(404, array('success' => false, 'message' => 'Tiada passkey didaftarkan untuk akaun ini. Sila log masuk dengan kata laluan.'));
        }
        $credentialIds = array();
        foreach ($rows as $row) { $credentialIds[] = \lbuchs\WebAuthn\Binary\ByteBuffer::fromBase64Url($row['credential_id']); }
        $args = $webAuthn->getGetArgs($credentialIds, 60, false, false, false, false, true, true);
        usmsmartWebAuthnChallengeStore('login', $webAuthn->getChallenge()->getBinaryString(), (int)$account['id'], $usernameKey);
        usmPasskeyOut(200, array('success' => true, 'publicKey' => $args->publicKey));
    } catch (\Throwable $e) {
        error_log('USMSmart passkey login options failed: ' . $e->getMessage());
        usmPasskeyOut(500, array('success' => false, 'message' => 'Passkey tidak dapat dimulakan. Sila guna kata laluan.'));
    }
}

if ($action === 'webauthn_login_verify') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { usmPasskeyOut(405, array('success' => false, 'message' => 'Kaedah tidak dibenarkan.')); }
    $webAuthn = usmsmartWebAuthnInstance();
    if ($webAuthn === null) { usmPasskeyOut(400, array('success' => false, 'message' => 'Passkey memerlukan sambungan HTTPS atau localhost.')); }
    $body = usmPasskeyBody();
    $username = trim((string)($body['username'] ?? ''));
    $usernameKey = strtolower(substr($username, 0, 80));
    $credentialId = (string)($body['credentialId'] ?? '');
    $ipHash = usmPasskeyClientIpHash();
    $state = usmPasskeyThrottleState($pdo, $usernameKey, $ipHash);
    if (usmPasskeyThrottleBlocked($state)) {
        header('Retry-After: 900');
        usmPasskeyOut(429, array('success' => false, 'message' => 'Terlalu banyak percubaan log masuk. Cuba semula selepas 15 minit.'));
    }
    $taken = usmsmartWebAuthnChallengeTake('login');
    $fields = usmPasskeyDecodeFields($body, array('clientDataJSON', 'authenticatorData', 'signature'));
    if ($username === '' || $credentialId === '' || $taken === null || $fields === null
        || ($taken['username_key'] ?? '') !== $usernameKey) {
        usmPasskeyOut(400, array('success' => false, 'message' => 'Sesi passkey telah tamat. Cuba lagi.'));
    }
    try {
        usmsmartWebAuthnEnsureTable($pdo);
        $stmt = $pdo->prepare('SELECT c.id AS credential_row_id, c.public_key, c.sign_count, c.label, u.id AS user_id, u.username, u.role, u.is_active, u.auth_version
            FROM usmsmart_webauthn_credentials c
            JOIN usmsmart_users u ON u.id = c.user_id
            WHERE c.credential_id = ? AND u.username = ? LIMIT 1');
        $stmt->execute(array($credentialId, $username));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !(int)$row['is_active']) {
            usmPasskeyThrottleFail($pdo, $usernameKey, $ipHash, $state);
            usleep(250000);
            usmPasskeyOut(401, array('success' => false, 'message' => 'Pengesahan passkey gagal. Cuba lagi atau guna kata laluan.'));
        }
        $webAuthn->processGet(
            $fields['clientDataJSON'], $fields['authenticatorData'], $fields['signature'],
            $row['public_key'], $taken['challenge_binary'], (int)$row['sign_count'], true, true
        );
        $newCount = $webAuthn->getSignatureCounter();
        $counter = is_int($newCount) ? $newCount : (int)$row['sign_count'];
        $pdo->prepare('UPDATE usmsmart_webauthn_credentials SET sign_count = ?, last_used_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute(array($counter, (int)$row['credential_row_id']));
        $pdo->prepare('DELETE FROM usmsmart_login_attempts WHERE username_key = ? AND ip_hash = ?')->execute(array($usernameKey, $ipHash));
        $pdo->prepare('UPDATE usmsmart_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')->execute(array((int)$row['user_id']));
        session_regenerate_id(true);
        $_SESSION['usmsmart_csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['user_id'] = (int)$row['user_id'];
        $_SESSION['auth_version'] = (int)$row['auth_version'];
        $_SESSION['last_activity'] = time();
        auditLog($pdo, 'login_passkey', 'user', (int)$row['user_id'], null, 'Signed in with passkey "'.substr((string)$row['label'], 0, 60).'"');
        usmPasskeyOut(200, array('success' => true, 'role' => $row['role']));
    } catch (\lbuchs\WebAuthn\WebAuthnException $e) {
        usmPasskeyThrottleFail($pdo, $usernameKey, $ipHash, $state);
        usleep(250000);
        usmPasskeyOut(401, array('success' => false, 'message' => 'Pengesahan passkey gagal. Cuba lagi atau guna kata laluan.'));
    } catch (\Throwable $e) {
        error_log('USMSmart passkey login failed: ' . $e->getMessage());
        usmPasskeyOut(500, array('success' => false, 'message' => 'Passkey tidak dapat disahkan. Sila guna kata laluan.'));
    }
}

/* ----------------------------------------------------------- enrolment */

if (in_array($action, array('webauthn_register_options', 'webauthn_register_verify', 'webauthn_list_credentials', 'webauthn_remove_credential'), true)) {
    if (empty($_SESSION['user_id'])) {
        usmPasskeyOut(401, array('success' => false, 'message' => 'Sesi log masuk diperlukan.'));
    }
}

if ($action === 'webauthn_list_credentials') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') { usmPasskeyOut(405, array('success' => false, 'message' => 'Kaedah tidak dibenarkan.')); }
    try {
        usmsmartWebAuthnEnsureTable($pdo);
        $rows = array();
        foreach (usmsmartWebAuthnCredentialRows($pdo, (int)$_SESSION['user_id']) as $row) {
            $rows[] = array(
                'id' => (int)$row['id'],
                'label' => (string)$row['label'],
                'transports' => (string)($row['transports'] ?? ''),
                'created_at' => (string)$row['created_at'],
                'last_used_at' => $row['last_used_at'] === null ? null : (string)$row['last_used_at']
            );
        }
        usmPasskeyOut(200, array('success' => true, 'credentials' => $rows, 'supported' => usmsmartWebAuthnIsSecure()));
    } catch (\Throwable $e) {
        error_log('USMSmart passkey list failed: ' . $e->getMessage());
        usmPasskeyOut(500, array('success' => false, 'message' => 'Senarai passkey tidak dapat dibaca.'));
    }
}

if ($action === 'webauthn_register_options') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { usmPasskeyOut(405, array('success' => false, 'message' => 'Kaedah tidak dibenarkan.')); }
    $webAuthn = usmsmartWebAuthnInstance();
    if ($webAuthn === null) { usmPasskeyOut(400, array('success' => false, 'message' => 'Passkey memerlukan sambungan HTTPS atau localhost.')); }
    try {
        usmsmartWebAuthnEnsureTable($pdo);
        $stmt = $pdo->prepare('SELECT id, username, display_name, is_active FROM usmsmart_users WHERE id = ? LIMIT 1');
        $stmt->execute(array((int)$_SESSION['user_id']));
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account || !(int)$account['is_active']) { usmPasskeyOut(403, array('success' => false, 'message' => 'Akaun tidak aktif.')); }
        $existing = array();
        foreach (usmsmartWebAuthnCredentialRows($pdo, (int)$account['id']) as $row) {
            $existing[] = \lbuchs\WebAuthn\Binary\ByteBuffer::fromBase64Url($row['credential_id']);
        }
        $displayName = trim((string)($account['display_name'] ?? ''));
        if ($displayName === '') { $displayName = (string)$account['username']; }
        // requireResidentKey 'preferred' keeps phone passkeys discoverable without forcing it on
        // every authenticator; user verification is required because the point is biometrics/PIN.
        $args = $webAuthn->getCreateArgs(
            usmsmartWebAuthnUserHandle((int)$account['id']), (string)$account['username'], $displayName,
            60, 'preferred', true, false, $existing
        );
        usmsmartWebAuthnChallengeStore('register', $webAuthn->getChallenge()->getBinaryString(), (int)$account['id']);
        usmPasskeyOut(200, array('success' => true, 'publicKey' => $args->publicKey));
    } catch (\Throwable $e) {
        error_log('USMSmart passkey register options failed: ' . $e->getMessage());
        usmPasskeyOut(500, array('success' => false, 'message' => 'Pendaftaran passkey tidak dapat dimulakan.'));
    }
}

if ($action === 'webauthn_register_verify') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { usmPasskeyOut(405, array('success' => false, 'message' => 'Kaedah tidak dibenarkan.')); }
    $webAuthn = usmsmartWebAuthnInstance();
    if ($webAuthn === null) { usmPasskeyOut(400, array('success' => false, 'message' => 'Passkey memerlukan sambungan HTTPS atau localhost.')); }
    $body = usmPasskeyBody();
    $taken = usmsmartWebAuthnChallengeTake('register', (int)$_SESSION['user_id']);
    $fields = usmPasskeyDecodeFields($body, array('clientDataJSON', 'attestationObject'));
    if ($taken === null || $fields === null) {
        usmPasskeyOut(400, array('success' => false, 'message' => 'Sesi pendaftaran tamat. Cuba lagi.'));
    }
    $label = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string)($body['label'] ?? '')));
    if (function_exists('mb_substr')) { $label = mb_substr($label, 0, 80); } else { $label = substr($label, 0, 80); }
    if ($label === '') { $label = 'Passkey'; }
    $transports = array();
    foreach ((array)($body['transports'] ?? array()) as $transport) {
        if (is_string($transport) && in_array($transport, array('usb', 'nfc', 'ble', 'hybrid', 'internal'), true)) { $transports[] = $transport; }
    }
    try {
        usmsmartWebAuthnEnsureTable($pdo);
        $data = $webAuthn->processCreate($fields['clientDataJSON'], $fields['attestationObject'], $taken['challenge_binary'], true, true, false, false);
        $credentialId = usmsmartB64UrlEncode($data->credentialId);
        if ($credentialId === '') { usmPasskeyOut(400, array('success' => false, 'message' => 'Peranti tidak memulangkan kelayakan yang sah.')); }
        $stmt = $pdo->prepare('INSERT INTO usmsmart_webauthn_credentials (user_id, credential_id, public_key, sign_count, aaguid, label, transports) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute(array(
            (int)$_SESSION['user_id'], $credentialId, (string)$data->credentialPublicKey,
            max(0, (int)$data->signatureCounter), usmsmartWebAuthnAaguidLabel($data->AAGUID), $label,
            $transports ? implode(',', array_unique($transports)) : null
        ));
        auditLog($pdo, 'register_passkey', 'user', (int)$_SESSION['user_id'], null, 'Registered passkey "'.$label.'"');
        usmPasskeyOut(200, array('success' => true, 'message' => 'Passkey berjaya didaftarkan.'));
    } catch (\PDOException $e) {
        if ((string)$e->getCode() === '23000') {
            usmPasskeyOut(409, array('success' => false, 'message' => 'Peranti ini sudah didaftarkan pada akaun anda.'));
        }
        error_log('USMSmart passkey register failed: ' . $e->getMessage());
        usmPasskeyOut(500, array('success' => false, 'message' => 'Passkey tidak dapat disimpan.'));
    } catch (\lbuchs\WebAuthn\WebAuthnException $e) {
        usmPasskeyOut(400, array('success' => false, 'message' => 'Pendaftaran passkey gagal: ' . $e->getMessage()));
    } catch (\Throwable $e) {
        error_log('USMSmart passkey register failed: ' . $e->getMessage());
        usmPasskeyOut(500, array('success' => false, 'message' => 'Passkey tidak dapat disimpan.'));
    }
}

if ($action === 'webauthn_remove_credential') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { usmPasskeyOut(405, array('success' => false, 'message' => 'Kaedah tidak dibenarkan.')); }
    $body = usmPasskeyBody();
    $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) { usmPasskeyOut(400, array('success' => false, 'message' => 'Passkey tidak sah.')); }
    try {
        usmsmartWebAuthnEnsureTable($pdo);
        $stmt = $pdo->prepare('DELETE FROM usmsmart_webauthn_credentials WHERE id = ? AND user_id = ?');
        $stmt->execute(array($id, (int)$_SESSION['user_id']));
        if ($stmt->rowCount() !== 1) { usmPasskeyOut(404, array('success' => false, 'message' => 'Passkey tidak ditemui.')); }
        auditLog($pdo, 'remove_passkey', 'user', (int)$_SESSION['user_id'], null, 'Removed passkey #'.$id);
        usmPasskeyOut(200, array('success' => true, 'message' => 'Passkey dibuang.'));
    } catch (\Throwable $e) {
        error_log('USMSmart passkey remove failed: ' . $e->getMessage());
        usmPasskeyOut(500, array('success' => false, 'message' => 'Passkey tidak dapat dibuang.'));
    }
}
