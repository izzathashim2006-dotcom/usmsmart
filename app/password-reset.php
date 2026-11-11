<?php
/*
 * Self-service password reset: token storage, throttling and delivery.
 *
 * Tokens are 32 random bytes shown to the user exactly once (in an e-mail or by an
 * administrator). The database keeps only the SHA-256 hash, so a leaked table cannot
 * be used to reset anything. Every token is single-use, expires after 30 minutes and
 * replaces any earlier unused token for the same account.
 *
 * Delivery: this installation has no guaranteed mail transport, so a request is first
 * offered to the configured SMTP server (config/mail.local.php) or PHP mail(). When
 * neither can deliver, the request stays in the queue and an administrator hands the
 * one-time link to the user from Pengurusan Akaun. The requester always receives the
 * same generic answer, so the endpoint never reveals whether an account exists.
 */

if (!defined('USM_ROOT')) {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

define('USM_RESET_TTL_SECONDS', 1800);
define('USM_RESET_RESEND_COOLDOWN', 60);

// Created once per request lifecycle; old and consumed rows are purged so the queue
// the administrator sees only ever holds live requests.
function usmsmartPasswordResetReady($pdo) {
    static $ready = false;
    if ($ready) { return true; }
    if (!($pdo instanceof PDO)) { return false; }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_password_resets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            delivery VARCHAR(10) NOT NULL DEFAULT 'admin',
            ip_hash CHAR(64) NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL DEFAULT NULL,
            UNIQUE KEY uq_reset_token (token_hash),
            INDEX idx_reset_user (user_id, used_at),
            INDEX idx_reset_expiry (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $purge = $pdo->prepare('DELETE FROM usmsmart_password_resets WHERE expires_at < ? OR used_at < ?');
        $cutoff = date('Y-m-d H:i:s', time() - 86400);
        $purge->execute(array($cutoff, $cutoff));
        $ready = true;
        return true;
    } catch (Throwable $e) {
        error_log('USMSmart password reset table unavailable: ' . $e->getMessage());
        return false;
    }
}

// The public base URL of this installation, used to build links that work from e-mail
// and from the administrator's clipboard alike. The Host header is validated before it
// is ever embedded in a link.
function usmsmartPasswordResetBaseUrl() {
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) { $host = 'localhost'; }
    $dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    if ($dir === '/' || $dir === '.' || $dir === '\\') { $dir = ''; }
    return ($https ? 'https://' : 'http://') . $host . $dir;
}

function usmsmartPasswordResetLink($token) {
    return usmsmartPasswordResetBaseUrl() . '/index.php?action=reset_password&token=' . rawurlencode((string)$token);
}

// Issues a fresh token for the account, invalidating every earlier unused token so a
// user (or an attacker with an old link) can never choose between two live links.
// Returns the plaintext token and its expiry; the caller delivers it once and keeps no copy.
function usmsmartPasswordResetCreate($pdo, $userId, $ipHash, $delivery) {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + USM_RESET_TTL_SECONDS);
    $clear = $pdo->prepare('DELETE FROM usmsmart_password_resets WHERE user_id = ? AND used_at IS NULL');
    $clear->execute(array((int)$userId));
    $insert = $pdo->prepare('INSERT INTO usmsmart_password_resets (user_id, token_hash, delivery, ip_hash, expires_at) VALUES (?, ?, ?, ?, ?)');
    $insert->execute(array((int)$userId, hash('sha256', $token), $delivery === 'email' ? 'email' : 'admin', (string)$ipHash, $expiresAt));
    return array('token' => $token, 'expires_at' => $expiresAt, 'request_id' => (int)$pdo->lastInsertId());
}

// Resolves a presented token to its account. Returns null when the hash is unknown;
// the caller decides whether an expired, consumed or disabled row is a distinct state.
function usmsmartPasswordResetFind($pdo, $token) {
    $stmt = $pdo->prepare('SELECT r.id AS request_id, r.user_id, r.delivery, r.expires_at, r.used_at, u.username, u.is_active, u.password_hash FROM usmsmart_password_resets r JOIN usmsmart_users u ON u.id = r.user_id WHERE r.token_hash = ? LIMIT 1');
    $stmt->execute(array(hash('sha256', (string)$token)));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// Live requests for the administrator queue: unused and unexpired, newest first.
function usmsmartPasswordResetPending($pdo) {
    $stmt = $pdo->prepare('SELECT r.id, r.user_id, r.delivery, r.created_at, r.expires_at, u.username FROM usmsmart_password_resets r JOIN usmsmart_users u ON u.id = r.user_id WHERE r.used_at IS NULL AND r.expires_at > ? ORDER BY r.created_at DESC, r.id DESC');
    $stmt->execute(array(date('Y-m-d H:i:s')));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Two budgets share the login-attempts table with a distinct key prefix: three requests
// per username/source pair per hour stop targeted spam, ten per source per hour stop
// username rotation. Honest campus NAT traffic stays far below the source budget.
function usmsmartPasswordResetThrottle($pdo, $usernameKey, $ipHash) {
    $pairLimit = 3;
    $ipLimit = 10;
    $key = 'reset:' . substr($usernameKey, 0, 74);
    $countStmt = $pdo->prepare('SELECT COUNT(*) AS ip_hits, COALESCE(SUM(username_key = ?), 0) AS pair_hits FROM usmsmart_login_attempts WHERE ip_hash = ? AND attempted_at >= (CURRENT_TIMESTAMP - INTERVAL 1 HOUR)');
    $countStmt->execute(array($key, $ipHash));
    $hits = $countStmt->fetch(PDO::FETCH_ASSOC) ?: array();
    $pairHits = (int)($hits['pair_hits'] ?? 0);
    $ipHits = (int)($hits['ip_hits'] ?? 0);
    if ($pairHits >= $pairLimit || $ipHits >= $ipLimit) {
        return array('blocked' => true, 'pair_hits' => $pairHits, 'ip_hits' => $ipHits);
    }
    $record = $pdo->prepare('INSERT INTO usmsmart_login_attempts (username_key, ip_hash) VALUES (?, ?)');
    $record->execute(array($key, $ipHash));
    return array('blocked' => false, 'pair_hits' => $pairHits + 1, 'ip_hits' => $ipHits + 1, 'pair_limit' => $pairLimit, 'ip_limit' => $ipLimit);
}

// The most recent unused request for an account, used for the 60-second resend cooldown.
function usmsmartPasswordResetLatest($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT id, created_at, expires_at FROM usmsmart_password_resets WHERE user_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1');
    $stmt->execute(array((int)$userId));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/* ===== Delivery =================================================------------ */

function usmsmartResetMailConfig() {
    static $config = null;
    if ($config !== null) { return $config; }
    $file = USM_ROOT . '/config/mail.local.php';
    $config = is_file($file) ? (array)require $file : array();
    return $config;
}

// Builds the message once so SMTP and mail() send identical content. The link appears
// as a button and as plain text, because campus mail clients strip both differently.
function usmsmartResetMailContent($displayName, $link, $expiresLabel) {
    $name = trim((string)$displayName) !== '' ? trim((string)$displayName) : 'pengguna USMSmart';
    $subject = 'USMSmart — Set semula kata laluan';
    $safeLink = htmlspecialchars((string)$link, ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeExpiry = htmlspecialchars((string)$expiresLabel, ENT_QUOTES, 'UTF-8');
    $html = '<!doctype html><html lang="ms"><body style="margin:0;padding:0;background:#f6f7fc;font-family:Segoe UI,Arial,sans-serif;color:#182033">'
        . '<div style="max-width:520px;margin:0 auto;padding:32px 16px">'
        . '<div style="background:#ffffff;border:1px solid #e6e8f2;border-radius:18px;overflow:hidden;box-shadow:0 12px 34px rgba(24,32,51,.08)">'
        . '<div style="height:5px;background:linear-gradient(90deg,#6d28d9,#8b5cf6 55%,#ddd6fe)"></div>'
        . '<div style="padding:28px 28px 24px">'
        . '<div style="font-size:12px;font-weight:700;letter-spacing:.08em;color:#6d28d9;text-transform:uppercase">USMSmart &middot; Kampus Kejuruteraan UDA</div>'
        . '<h1 style="margin:12px 0 10px;font-size:21px;letter-spacing:-.02em">Set semula kata laluan</h1>'
        . '<p style="margin:0 0 14px;font-size:14px;line-height:1.65;color:#3a4459">Hai ' . $safeName . ',</p>'
        . '<p style="margin:0 0 20px;font-size:14px;line-height:1.65;color:#3a4459">Kami menerima permintaan set semula kata laluan untuk akaun anda. Tekan butang di bawah untuk menetapkan kata laluan baharu. Pautan ini sah sehingga <strong>' . $safeExpiry . '</strong> dan hanya boleh digunakan sekali.</p>'
        . '<p style="margin:0 0 22px"><a href="' . $safeLink . '" style="display:inline-block;padding:13px 22px;border-radius:11px;background:#6d28d9;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none">Tetapkan kata laluan baharu</a></p>'
        . '<p style="margin:0 0 8px;font-size:12px;line-height:1.6;color:#64708a">Jika butang tidak berfungsi, salin pautan ini ke pelayar anda:</p>'
        . '<p style="margin:0 0 20px;font-size:12px;line-height:1.6;word-break:break-all"><a href="' . $safeLink . '" style="color:#6d28d9">' . $safeLink . '</a></p>'
        . '<p style="margin:0;padding-top:16px;border-top:1px solid #eef0f7;font-size:12px;line-height:1.6;color:#64708a">Jika anda tidak membuat permintaan ini, abaikan e-mel ini. Kata laluan anda tidak berubah selagi pautan tidak digunakan.</p>'
        . '</div></div>'
        . '<p style="margin:16px 0 0;text-align:center;font-size:10px;letter-spacing:.06em;color:#9aa4b8">USMSMART &bull; PORTAL PENGURUSAN KAD</p>'
        . '</div></body></html>';
    $plain = "Hai " . $name . ",\r\n\r\n"
        . "Kami menerima permintaan set semula kata laluan untuk akaun USMSmart anda.\r\n"
        . "Buka pautan berikut untuk menetapkan kata laluan baharu (sah sehingga " . $expiresLabel . ", sekali guna):\r\n\r\n"
        . $link . "\r\n\r\n"
        . "Jika anda tidak membuat permintaan ini, abaikan e-mel ini. Kata laluan anda tidak berubah selagi pautan tidak digunakan.\r\n\r\n"
        . "— USMSmart, Kampus Kejuruteraan UDA";
    return array('subject' => $subject, 'html' => $html, 'plain' => $plain);
}

function usmsmartResetMimeMessage(array $content, $fromEmail, $fromName, $toEmail, $toName) {
    $boundary = 'usm-reset-' . bin2hex(random_bytes(12));
    $headers = array(
        'From: ' . usmsmartResetEncodeHeader($fromName) . ' <' . $fromEmail . '>',
        'To: ' . usmsmartResetEncodeHeader($toName) . ' <' . $toEmail . '>',
        'Subject: ' . usmsmartResetEncodeHeader($content['subject']),
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@usmsmart.local>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    );
    $body = '--' . $boundary . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n"
        . $content['plain'] . "\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . 'Content-Type: text/html; charset=UTF-8' . "\r\n"
        . 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n"
        . $content['html'] . "\r\n\r\n"
        . '--' . $boundary . '--';
    return array(implode("\r\n", $headers), $body);
}

function usmsmartResetEncodeHeader($text) {
    $text = (string)$text;
    if (preg_match('/[\x80-\xFF]/', $text)) { return '=?UTF-8?B?' . base64_encode($text) . '?='; }
    return $text;
}

// Minimal dependency-free SMTP client: implicit SSL (465) or STARTTLS (587), AUTH LOGIN.
// Any failure returns false so the request falls back to the administrator queue.
function usmsmartSmtpSend(array $cfg, $toEmail, $toName, array $content) {
    $host = trim((string)($cfg['host'] ?? ''));
    $fromEmail = trim((string)($cfg['from_email'] ?? ''));
    if ($host === '' || $fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) { return false; }
    $port = (int)($cfg['port'] ?? 587);
    $secure = strtolower((string)($cfg['secure'] ?? 'tls'));
    $fromName = (string)($cfg['from_name'] ?? 'USMSmart');
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $context = stream_context_create(array('ssl' => array('verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true)));
    $socket = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) { error_log('USMSmart reset SMTP connect failed: ' . $errstr); return false; }
    stream_set_timeout($socket, 10);
    $readResponse = function () use ($socket) {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') { break; }
        }
        return $data;
    };
    $expect = function ($command, array $codes) use ($socket, $readResponse) {
        if ($command !== null) { fwrite($socket, $command . "\r\n"); }
        $response = $readResponse();
        return in_array((int)substr($response, 0, 3), $codes, true) ? $response : false;
    };
    $ok = false;
    try {
        if ($expect(null, array(220)) === false) { throw new RuntimeException('greeting'); }
        if ($expect('EHLO usmsmart.local', array(250)) === false) { throw new RuntimeException('EHLO'); }
        if ($secure === 'tls') {
            if ($expect('STARTTLS', array(220)) === false) { throw new RuntimeException('STARTTLS'); }
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { throw new RuntimeException('TLS handshake'); }
            if ($expect('EHLO usmsmart.local', array(250)) === false) { throw new RuntimeException('EHLO after TLS'); }
        }
        $smtpUser = (string)($cfg['username'] ?? '');
        if ($smtpUser !== '') {
            if ($expect('AUTH LOGIN', array(334)) === false) { throw new RuntimeException('AUTH LOGIN'); }
            if ($expect(base64_encode($smtpUser), array(334)) === false) { throw new RuntimeException('AUTH username'); }
            if ($expect(base64_encode((string)($cfg['password'] ?? '')), array(235)) === false) { throw new RuntimeException('AUTH password'); }
        }
        if ($expect('MAIL FROM:<' . $fromEmail . '>', array(250)) === false) { throw new RuntimeException('MAIL FROM'); }
        if ($expect('RCPT TO:<' . $toEmail . '>', array(250, 251)) === false) { throw new RuntimeException('RCPT TO'); }
        if ($expect('DATA', array(354)) === false) { throw new RuntimeException('DATA'); }
        list($headerText, $body) = usmsmartResetMimeMessage($content, $fromEmail, $fromName, $toEmail, $toName);
        $message = preg_replace('/^\./m', '..', str_replace("\r\n", "\n", $headerText . "\r\n\r\n" . $body));
        $message = str_replace("\n", "\r\n", $message);
        if ($expect($message . "\r\n.", array(250)) === false) { throw new RuntimeException('message body'); }
        $ok = true;
    } catch (Throwable $e) {
        error_log('USMSmart reset SMTP failed: ' . $e->getMessage());
    }
    @fwrite($socket, "QUIT\r\n");
    fclose($socket);
    return $ok;
}

// Sends the reset e-mail through the configured transport. Returns true only when a
// transport accepted the message; false sends the request to the administrator queue.
function usmsmartSendResetEmail($toEmail, $displayName, $link, $expiresLabel) {
    if (!filter_var((string)$toEmail, FILTER_VALIDATE_EMAIL)) { return false; }
    $content = usmsmartResetMailContent($displayName, $link, $expiresLabel);
    $cfg = usmsmartResetMailConfig();
    if (trim((string)($cfg['host'] ?? '')) !== '') {
        return usmsmartSmtpSend($cfg, (string)$toEmail, (string)$displayName, $content);
    }
    // No SMTP configured: try the platform mailer. On hosts without one this fails
    // quickly and the administrator queue takes over.
    $host = preg_replace('/^www\./', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if (!preg_match('/^[A-Za-z0-9.\-]+$/', $host)) { $host = 'localhost'; }
    list($headerText, $body) = usmsmartResetMimeMessage($content, 'no-reply@' . $host, 'USMSmart', (string)$toEmail, (string)$displayName);
    // mail() takes the headers separately and forbids Subject/To inside the body string.
    $headerLines = array_filter(explode("\r\n", $headerText), function ($line) {
        return stripos($line, 'Subject:') !== 0 && stripos($line, 'To:') !== 0;
    });
    return @mail((string)$toEmail, $content['subject'], $body, implode("\r\n", $headerLines));
}

/* ===== OTP-based Reset for Linked Gmail / Email ===== */
define('USM_OTP_TTL_SECONDS', 600); // 10 minutes

function usmsmartGenerateOtpCode($length = 6) {
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= (string)random_int(0, 9);
    }
    return $code;
}

function usmsmartCreateOtp($pdo, $userId, $ipHash) {
    $otp = usmsmartGenerateOtpCode(6);
    $expiresAt = date('Y-m-d H:i:s', time() + USM_OTP_TTL_SECONDS);
    $clear = $pdo->prepare('DELETE FROM usmsmart_password_otps WHERE user_id = ? AND used_at IS NULL');
    $clear->execute(array((int)$userId));
    $stmt = $pdo->prepare('INSERT INTO usmsmart_password_otps (user_id, otp_code, expires_at, ip_hash) VALUES (?, ?, ?, ?)');
    $stmt->execute(array((int)$userId, $otp, $expiresAt, (string)$ipHash));
    return array('otp' => $otp, 'expires_at' => $expiresAt);
}

function usmsmartVerifyOtp($pdo, $userId, $otp) {
    $stmt = $pdo->prepare('SELECT id, user_id, expires_at FROM usmsmart_password_otps WHERE user_id = ? AND otp_code = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1');
    $stmt->execute(array((int)$userId, trim((string)$otp)));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    if (strtotime((string)$row['expires_at']) <= time()) return false;
    // Mark as used
    $upd = $pdo->prepare('UPDATE usmsmart_password_otps SET used_at = ? WHERE id = ?');
    $upd->execute(array(date('Y-m-d H:i:s'), (int)$row['id']));
    return true;
}

function usmsmartOtpMailContent($displayName, $otp, $expiresMinutes = 10) {
    $name = trim((string)$displayName) !== '' ? trim((string)$displayName) : 'pengguna USMSmart';
    $subject = 'USMSmart — Kod OTP Set Semula Kata Laluan';
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeOtp = htmlspecialchars((string)$otp, ENT_QUOTES, 'UTF-8');
    
    $html = '<!doctype html><html lang="ms"><body style="margin:0;padding:0;background:#f6f7fc;font-family:Segoe UI,Arial,sans-serif;color:#182033">'
        . '<div style="max-width:520px;margin:0 auto;padding:32px 16px">'
        . '<div style="background:#ffffff;border:1px solid #e6e8f2;border-radius:18px;overflow:hidden;box-shadow:0 12px 34px rgba(24,32,51,.08)">'
        . '<div style="height:5px;background:linear-gradient(90deg,#ea4335,#fbbc05 50%,#34a853 100%)"></div>'
        . '<div style="padding:28px 28px 24px">'
        . '<div style="font-size:12px;font-weight:700;letter-spacing:.08em;color:#ea4335;text-transform:uppercase">USMSmart &middot; Pautan Akaun Gmail</div>'
        . '<h1 style="margin:12px 0 10px;font-size:21px;letter-spacing:-.02em">Kod Pengesahan (OTP)</h1>'
        . '<p style="margin:0 0 14px;font-size:14px;line-height:1.65;color:#3a4459">Hai ' . $safeName . ',</p>'
        . '<p style="margin:0 0 20px;font-size:14px;line-height:1.65;color:#3a4459">Gunakan kod OTP di bawah untuk mengesahkan penetapan semula kata laluan anda. Kod ini sah selama <strong>' . $expiresMinutes . ' minit</strong> dan hanya boleh digunakan sekali.</p>'
        . '<div style="margin:24px 0;padding:16px;background:#fef2f2;border:2px dashed #f87171;border-radius:12px;text-align:center">'
        . '<span style="font-size:32px;font-weight:800;letter-spacing:6px;color:#dc2626;font-family:monospace">' . $safeOtp . '</span>'
        . '</div>'
        . '<p style="margin:0;padding-top:16px;border-top:1px solid #eef0f7;font-size:12px;line-height:1.6;color:#64708a">Jika anda tidak membuat permintaan ini, sila abaikan e-mel ini. Jangan berkongsi kod OTP ini dengan sesiapa demi keselamatan akaun anda.</p>'
        . '</div></div>'
        . '<p style="margin:16px 0 0;text-align:center;font-size:10px;letter-spacing:.06em;color:#9aa4b8">USMSMART &bull; KAMPUS KEJURUTERAAN UDA</p>'
        . '</div></body></html>';
        
    $plain = "Hai " . $name . ",\r\n\r\n"
        . "Kod OTP pengesahan set semula kata laluan akaun USMSmart anda ialah:\r\n\r\n"
        . "    " . $otp . "\r\n\r\n"
        . "Kod ini sah selama " . $expiresMinutes . " minit. Jangan kongsi kod ini dengan sesiapa.\r\n\r\n"
        . "— USMSmart, Kampus Kejuruteraan UDA";
        
    return array('subject' => $subject, 'html' => $html, 'plain' => $plain);
}

function usmsmartSendOtpEmail($toEmail, $displayName, $otp) {
    if (!filter_var((string)$toEmail, FILTER_VALIDATE_EMAIL)) { return false; }
    $content = usmsmartOtpMailContent($displayName, $otp);
    $cfg = usmsmartResetMailConfig();
    if (trim((string)($cfg['host'] ?? '')) !== '') {
        return usmsmartSmtpSend($cfg, (string)$toEmail, (string)$displayName, $content);
    }
    $host = preg_replace('/^www\./', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if (!preg_match('/^[A-Za-z0-9.\-]+$/', $host)) { $host = 'localhost'; }
    list($headerText, $body) = usmsmartResetMimeMessage($content, 'no-reply@' . $host, 'USMSmart', (string)$toEmail, (string)$displayName);
    $headerLines = array_filter(explode("\r\n", $headerText), function ($line) {
        return stripos($line, 'Subject:') !== 0 && stripos($line, 'To:') !== 0;
    });
    return @mail((string)$toEmail, $content['subject'], $body, implode("\r\n", $headerLines));
}
