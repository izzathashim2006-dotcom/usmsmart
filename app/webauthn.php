<?php
// Passkey (WebAuthn) helpers shared by the login screen and the account endpoints.
// The cryptography lives in the vendored MIT library (app/vendor/webauthn, lbuchs/WebAuthn
// v2.2.0). This file only supplies the relying-party identity, the credential table and the
// session glue, so the endpoints stay small enough to audit.
if (!defined('USM_ROOT')) {
    // Reached directly instead of through index.php: these are includes, not endpoints.
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

// PSR-4 autoloader for the one namespace the vendored library uses. No Composer in this app.
if (!function_exists('usmsmartWebAuthnAutoload')) {
    function usmsmartWebAuthnAutoload($class) {
        $prefix = 'lbuchs\\WebAuthn\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) { return; }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = USM_ROOT . '/app/vendor/webauthn/src/' . $relative . '.php';
        if (is_file($file)) { require $file; }
    }
    spl_autoload_register('usmsmartWebAuthnAutoload');
}

// The relying-party id is the request host without a port. IP literals are rejected because
// WebAuthn only allows domain names; a host that is not localhost must also be served over HTTPS,
// which the library enforces again when it checks the client origin.
function usmsmartWebAuthnRpId() {
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    $host = (string)preg_replace('/:\d+$/', '', $host);
    $host = rtrim($host, '.');
    if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) { return ''; }
    return preg_match('/^[a-z0-9.-]+$/', $host) ? $host : '';
}

function usmsmartWebAuthnIsSecure() {
    $rpId = usmsmartWebAuthnRpId();
    if ($rpId === '') { return false; }
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    return $https || $rpId === 'localhost';
}

// Returns a configured server or null when the request cannot support WebAuthn at all.
// Attestation is 'none' on purpose: this deployment has no trust-anchor store, and the value
// of a passkey here is phishing-resistant login, not vendor provenance.
function usmsmartWebAuthnInstance() {
    $rpId = usmsmartWebAuthnRpId();
    if ($rpId === '' || !usmsmartWebAuthnIsSecure()) { return null; }
    try {
        return new \lbuchs\WebAuthn\WebAuthn('USMSmart', $rpId, array('none'), true);
    } catch (\Throwable $e) {
        error_log('USMSmart WebAuthn init failed: ' . $e->getMessage());
        return null;
    }
}

function usmsmartWebAuthnEnsureTable($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_webauthn_credentials (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        credential_id VARCHAR(255) NOT NULL,
        public_key TEXT NOT NULL,
        sign_count INT UNSIGNED NOT NULL DEFAULT 0,
        aaguid CHAR(36) NULL DEFAULT NULL,
        label VARCHAR(80) NOT NULL DEFAULT 'Passkey',
        transports VARCHAR(120) NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY uq_webauthn_credential (credential_id),
        INDEX idx_webauthn_user (user_id),
        CONSTRAINT fk_webauthn_user FOREIGN KEY (user_id) REFERENCES usmsmart_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// base64url without padding, the encoding the browser and the library both speak.
function usmsmartB64UrlEncode($binary) {
    return rtrim(strtr(base64_encode((string)$binary), '+/', '-_'), '=');
}

function usmsmartB64UrlDecode($value) {
    $value = (string)$value;
    if ($value === '' || preg_match('/[^A-Za-z0-9_-]/', $value)) { return false; }
    $padded = strtr($value, '-_', '+/');
    $padded .= str_repeat('=', (4 - (strlen($padded) % 4)) % 4);
    return base64_decode($padded, true);
}

// One challenge at a time, bound to a purpose and (for registration) to the signed-in user.
// It is removed when taken, so a failed signature cannot be retried against the same challenge.
function usmsmartWebAuthnChallengeStore($purpose, $challengeBinary, $userId = null, $usernameKey = '') {
    $_SESSION['usmsmart_passkey_challenge'] = array(
        'purpose' => (string)$purpose,
        'challenge' => base64_encode((string)$challengeBinary),
        'user_id' => $userId === null ? null : (int)$userId,
        'username_key' => (string)$usernameKey,
        'expires' => time() + 120
    );
}

function usmsmartWebAuthnChallengeTake($purpose, $userId = null) {
    $stored = $_SESSION['usmsmart_passkey_challenge'] ?? null;
    unset($_SESSION['usmsmart_passkey_challenge']);
    if (!is_array($stored) || ($stored['purpose'] ?? '') !== (string)$purpose) { return null; }
    if ((int)($stored['expires'] ?? 0) < time()) { return null; }
    if ($userId !== null && (int)($stored['user_id'] ?? 0) !== (int)$userId) { return null; }
    $challenge = base64_decode((string)($stored['challenge'] ?? ''), true);
    return $challenge === false || $challenge === '' ? null : $stored + array('challenge_binary' => $challenge);
}

// A stable, non-reversible 16-byte user handle. The authenticator stores it; it must never be
// the account id or the username.
function usmsmartWebAuthnUserHandle($userId) {
    return substr(hash('sha256', 'usmsmart-user-handle:' . (int)$userId, true), 0, 16);
}

// Formatted AAGUID for display; the raw bytes stay out of the UI.
function usmsmartWebAuthnAaguidLabel($binary) {
    if (!is_string($binary) || strlen($binary) !== 16) { return null; }
    $hex = bin2hex($binary);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
}

function usmsmartWebAuthnCredentialRows($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT id, credential_id, label, transports, created_at, last_used_at FROM usmsmart_webauthn_credentials WHERE user_id = ? ORDER BY id ASC');
    $stmt->execute(array((int)$userId));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
