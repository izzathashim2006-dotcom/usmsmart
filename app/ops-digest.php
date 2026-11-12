<?php
/*
 * Operations digest: a weekly expiry + data-quality summary emailed to admins.
 *
 * Reuses the dependency-free SMTP transport already shipped for password reset
 * (usmsmartSmtpSend + usmsmartResetMailConfig in app/password-reset.php), so no new
 * library or credential is introduced. The summary is computed straight from
 * students_view; nothing here mutates a record. Delivery is best-effort and every
 * failure is logged, never fatal to the request that triggered it.
 */

if (!defined('USM_ROOT')) {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

require_once USM_ROOT . '/app/password-reset.php';

/* Aggregate the numbers the digest shows. One pass over students_view. */
function usmsmartOpsDigestData($pdo) {
    $data = array(
        'total' => 0, 'expired' => 0, 'd7' => 0, 'd30' => 0, 'd90' => 0,
        'invalid_uid' => 0, 'missing_fields' => 0, 'duplicates' => 0,
        'urgent' => array(), 'generated_at' => date('Y-m-d H:i:s'),
    );
    if (!($pdo instanceof PDO)) { return $data; }
    try {
        $sql = "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date <> '' AND expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired,
            SUM(CASE WHEN expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS d7,
            SUM(CASE WHEN expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS d30,
            SUM(CASE WHEN expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) AS d90,
            SUM(CASE WHEN usm_uid IS NOT NULL AND usm_uid <> '' AND usm_uid NOT REGEXP '^[0-9A-Fa-f]{8}$' THEN 1 ELSE 0 END) AS invalid_uid,
            SUM(CASE WHEN name IS NULL OR TRIM(name) = '' OR matric IS NULL OR TRIM(matric) = '' OR school IS NULL OR TRIM(school) = '' THEN 1 ELSE 0 END) AS missing_fields
            FROM students_view";
        $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            foreach (array('total','expired','d7','d30','d90','invalid_uid','missing_fields') as $key) {
                $data[$key] = (int)($row[$key] ?? 0);
            }
        }
    } catch (Exception $e) { error_log('USMSmart ops digest aggregate failed: ' . $e->getMessage()); }

    try {
        $dup = $pdo->query("SELECT COUNT(*) FROM students_view WHERE matric IS NOT NULL AND matric <> '' AND matric IN (SELECT matric FROM (SELECT matric FROM students_view WHERE matric <> '' GROUP BY matric HAVING COUNT(*) > 1) AS d)")->fetchColumn();
        $data['duplicates'] = (int)$dup;
    } catch (Exception $e) { error_log('USMSmart ops digest duplicate count failed: ' . $e->getMessage()); }

    try {
        $urgent = $pdo->query("SELECT name, matric, school, expiry_date FROM students_view WHERE expiry_date IS NOT NULL AND expiry_date <> '' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY expiry_date ASC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
        $data['urgent'] = is_array($urgent) ? $urgent : array();
    } catch (Exception $e) { error_log('USMSmart ops digest urgent list failed: ' . $e->getMessage()); }

    return $data;
}

function usmsmartOpsDigestEsc($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* Build the HTML + plain bodies. Malay-first, matching the portal's default voice. */
function usmsmartOpsDigestContent(array $data) {
    $e = 'usmsmartOpsDigestEsc';
    $subject = 'USMSmart — Ringkasan mingguan kad & kualiti data';
    $stat = function ($label, $value, $tone) use ($e) {
        return '<td style="padding:6px;vertical-align:top;width:25%;">'
            . '<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 12px;text-align:center;">'
            . '<div style="font:700 26px/1.1 Arial,Helvetica,sans-serif;color:' . $tone . ';">' . $e($value) . '</div>'
            . '<div style="font:600 11px/1.4 Arial,Helvetica,sans-serif;color:#64748b;margin-top:6px;text-transform:uppercase;letter-spacing:.04em;">' . $e($label) . '</div>'
            . '</div></td>';
    };
    $rows = '';
    foreach ($data['urgent'] as $r) {
        $exp = (string)($r['expiry_date'] ?? '');
        $expired = ($exp !== '' && $exp < date('Y-m-d'));
        $rows .= '<tr>'
            . '<td style="padding:8px 10px;border-bottom:1px solid #eef2f7;font:600 13px Arial;color:#0f172a;">' . $e($r['name'] ?? '') . '</td>'
            . '<td style="padding:8px 10px;border-bottom:1px solid #eef2f7;font:13px Arial;color:#475569;">' . $e($r['matric'] ?? '') . '</td>'
            . '<td style="padding:8px 10px;border-bottom:1px solid #eef2f7;font:13px Arial;color:#475569;">' . $e($r['school'] ?? '') . '</td>'
            . '<td style="padding:8px 10px;border-bottom:1px solid #eef2f7;font:700 13px Arial;color:' . ($expired ? '#e11d48' : '#d97706') . ';white-space:nowrap;">' . $e($exp) . ($expired ? ' • luput' : '') . '</td>'
            . '</tr>';
    }
    if ($rows === '') { $rows = '<tr><td colspan="4" style="padding:14px;font:13px Arial;color:#64748b;text-align:center;">Tiada kad luput dalam 30 hari terdekat.</td></tr>'; }

    $html = '<!DOCTYPE html><html lang="ms"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#eef2f7;">'
        . '<div style="max-width:640px;margin:0 auto;padding:24px 16px;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="background:linear-gradient(135deg,#4f46e5,#6366f1 55%,#8b5cf6);border-radius:18px 18px 0 0;padding:26px 28px;">'
        . '<div style="font:700 12px Arial;color:#c7d2fe;letter-spacing:.14em;text-transform:uppercase;">USMSmart • UDA Engineering Campus</div>'
        . '<div style="font:700 24px/1.25 Arial;color:#ffffff;margin-top:8px;">Ringkasan mingguan kad</div>'
        . '<div style="font:14px/1.5 Arial;color:#e0e7ff;margin-top:6px;">Status luput tempoh dan kualiti data rekod kad pelajar.</div>'
        . '</div>'
        . '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 18px 18px;padding:22px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
        . $stat('Jumlah rekod', $data['total'], '#4f46e5')
        . $stat('Telah luput', $data['expired'], '#e11d48')
        . $stat('≤ 7 hari', $data['d7'], '#ea580c')
        . $stat('≤ 30 hari', $data['d30'], '#d97706')
        . '</tr></table>'
        . '<div style="margin-top:18px;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:16px;">'
        . '<div style="font:700 13px Arial;color:#0f172a;margin-bottom:10px;">Kualiti data</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font:13px Arial;color:#475569;">'
        . '<tr><td style="padding:4px 0;">UID tidak sah (bukan 8 heks)</td><td style="padding:4px 0;text-align:right;font-weight:700;color:#0f172a;">' . $e($data['invalid_uid']) . '</td></tr>'
        . '<tr><td style="padding:4px 0;">Medan utama kosong</td><td style="padding:4px 0;text-align:right;font-weight:700;color:#0f172a;">' . $e($data['missing_fields']) . '</td></tr>'
        . '<tr><td style="padding:4px 0;">Rekod matrik pendua</td><td style="padding:4px 0;text-align:right;font-weight:700;color:#0f172a;">' . $e($data['duplicates']) . '</td></tr>'
        . '</table></div>'
        . '<div style="margin-top:18px;font:700 13px Arial;color:#0f172a;">Kad paling hampir luput (≤ 30 hari)</div>'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;border-collapse:collapse;overflow:hidden;">'
        . '<tr style="background:#f1f5f9;">'
        . '<th align="left" style="padding:9px 10px;font:700 11px Arial;color:#64748b;text-transform:uppercase;letter-spacing:.04em;">Nama</th>'
        . '<th align="left" style="padding:9px 10px;font:700 11px Arial;color:#64748b;text-transform:uppercase;">Matrik</th>'
        . '<th align="left" style="padding:9px 10px;font:700 11px Arial;color:#64748b;text-transform:uppercase;">Pusat pengajian</th>'
        . '<th align="left" style="padding:9px 10px;font:700 11px Arial;color:#64748b;text-transform:uppercase;">Luput</th>'
        . '</tr>' . $rows . '</table>'
        . '<div style="margin-top:20px;text-align:center;">'
        . '<a href="#" style="display:inline-block;background:#4f46e5;color:#ffffff;font:700 13px Arial;padding:12px 26px;border-radius:12px;text-decoration:none;">Log masuk untuk tindakan</a>'
        . '</div>'
        . '<div style="margin-top:18px;font:12px/1.6 Arial;color:#94a3b8;text-align:center;">Dijana automatik oleh USMSmart pada ' . $e($data['generated_at']) . '.<br>Anda menerima e-mel ini kerana anda pentadbir portal.</div>'
        . '</div></div></body></html>';

    $plain = "USMSmart — Ringkasan mingguan kad & kualiti data\n"
        . "Dijana: " . $data['generated_at'] . "\n\n"
        . "Jumlah rekod: " . $data['total'] . "\n"
        . "Telah luput: " . $data['expired'] . "\n"
        . "Luput <= 7 hari: " . $data['d7'] . "\n"
        . "Luput <= 30 hari: " . $data['d30'] . "\n"
        . "Luput <= 90 hari: " . $data['d90'] . "\n\n"
        . "Kualiti data:\n"
        . " - UID tidak sah: " . $data['invalid_uid'] . "\n"
        . " - Medan utama kosong: " . $data['missing_fields'] . "\n"
        . " - Rekod matrik pendua: " . $data['duplicates'] . "\n\n"
        . "Kad paling hampir luput:\n";
    foreach ($data['urgent'] as $r) {
        $plain .= ' - ' . ($r['name'] ?? '') . ' (' . ($r['matric'] ?? '') . ') — ' . ($r['expiry_date'] ?? '') . "\n";
    }
    $plain .= "\nLog masuk ke USMSmart untuk memperbaharui atau membetulkan rekod.\n";

    return array('subject' => $subject, 'html' => $html, 'plain' => $plain);
}

/* Admin recipients with a usable email address. */
function usmsmartOpsDigestRecipients($pdo) {
    $out = array();
    if (!($pdo instanceof PDO)) { return $out; }
    try {
        $stmt = $pdo->query("SELECT username, display_name, email FROM usmsmart_users WHERE role = 'admin' AND is_active = 1 AND email IS NOT NULL AND email <> ''");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $email = trim((string)$row['email']);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = array('email' => $email, 'name' => trim((string)($row['display_name'] ?? '')) !== '' ? $row['display_name'] : $row['username']);
            }
        }
    } catch (Exception $e) { error_log('USMSmart ops digest recipients failed: ' . $e->getMessage()); }
    return $out;
}

/* Send the digest to every admin. Returns a small result array; never throws. */
function usmsmartSendOpsDigest($pdo, $trigger = 'manual') {
    $result = array('success' => false, 'sent' => 0, 'failed' => 0, 'recipients' => 0, 'message' => '');
    try {
        $recipients = usmsmartOpsDigestRecipients($pdo);
        $result['recipients'] = count($recipients);
        if (empty($recipients)) {
            $result['message'] = 'Tiada alamat e-mel pentadbir yang sah. Tetapkan e-mel dalam Pengurusan Akaun dahulu.';
            return $result;
        }
        $cfg = usmsmartResetMailConfig();
        $data = usmsmartOpsDigestData($pdo);
        $content = usmsmartOpsDigestContent($data);
        $useSmtp = !empty($cfg['host']);
        foreach ($recipients as $rcpt) {
            $ok = false;
            if ($useSmtp && function_exists('usmsmartSmtpSend')) {
                $ok = usmsmartSmtpSend($cfg, $rcpt['email'], $rcpt['name'], $content);
            }
            if (!$ok) {
                $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: " . ($cfg['from_email'] ?? 'no-reply@localhost') . "\r\n";
                $ok = @mail($rcpt['email'], $content['subject'], $content['html'], $headers);
            }
            if ($ok) { $result['sent']++; } else { $result['failed']++; }
        }
        $result['success'] = $result['sent'] > 0;
        $result['message'] = $result['success']
            ? ('Ringkasan dihantar ke ' . $result['sent'] . ' pentadbir' . ($result['failed'] > 0 ? ('; ' . $result['failed'] . ' gagal') : '') . '.')
            : 'Penghantaran gagal. Semak konfigurasi SMTP (fail kredensial di luar web root atau pemboleh ubah USMSMART_SMTP_*).';
        if (function_exists('auditLog')) {
            auditLog($pdo, 'ops_digest', 'system', null, null, json_encode(array('trigger' => $trigger, 'sent' => $result['sent'], 'failed' => $result['failed']), JSON_UNESCAPED_UNICODE));
        }
    } catch (Exception $e) {
        error_log('USMSmart ops digest send failed: ' . $e->getMessage());
        $result['message'] = 'Ralat semasa menghantar ringkasan.';
    }
    return $result;
}

/* Weekly piggyback: first admin request of each ISO week sends the digest, once.
   Mirrors the daily-backup marker + flock pattern in index.php. Only runs when SMTP
   is configured so an unconfigured install never attempts delivery. */
function usmsmartMaybeSendWeeklyDigest($pdo) {
    try {
        if (!($pdo instanceof PDO)) { return; }
        $cfg = usmsmartResetMailConfig();
        if (empty($cfg['host'])) { return; }
        if (!function_exists('usmsmartBackupDirectory')) { return; }
        $dir = usmsmartBackupDirectory();
        if (!is_dir($dir)) { return; }
        $week = date('o-W');
        $marker = $dir . DIRECTORY_SEPARATOR . '.last_ops_digest_week';
        $last = is_file($marker) ? trim((string)@file_get_contents($marker)) : '';
        if ($last === $week) { return; }
        $lock = @fopen($dir . DIRECTORY_SEPARATOR . '.ops_digest.lock', 'c');
        if (!$lock) { return; }
        if (flock($lock, LOCK_EX | LOCK_NB)) {
            $last = is_file($marker) ? trim((string)@file_get_contents($marker)) : '';
            if ($last !== $week) {
                $recipients = usmsmartOpsDigestRecipients($pdo);
                if (!empty($recipients)) { usmsmartSendOpsDigest($pdo, 'weekly'); }
                @file_put_contents($marker, $week, LOCK_EX);
            }
            flock($lock, LOCK_UN);
        }
        fclose($lock);
    } catch (Exception $e) { error_log('USMSmart weekly digest failed: ' . $e->getMessage()); }
}