<?php
/*
 * Optional SMTP transport for self-service password reset e-mails.
 *
 * Preferred: copy this file to C:/xampp/private/usmsmart_mail.php (outside the
 * web root) and fill in the values there. An installation that still keeps
 * config/mail.local.php continues to work; that file is gitignored.
 *
 * Credentials can also come from the environment, which wins over the file and
 * keeps the secret off disk entirely:
 *   USMSMART_SMTP_HOST, _PORT, _SECURE, _USERNAME, _PASSWORD, _FROM_EMAIL, _FROM_NAME
 * USMSMART_MAIL_CONFIG may point at a different credentials file.
 *
 * Without any of these the app tries PHP mail(), and when no transport can
 * deliver, reset requests wait in Pengurusan Akaun for an administrator to hand
 * over the one-time link — the feature works either way.
 *
 * secure: 'tls' = STARTTLS on port 587, 'ssl' = implicit TLS on port 465.
 * Leave username empty for an unauthenticated relay.
 */
return array(
    'host' => 'smtp.example.edu.my',
    'port' => 587,
    'secure' => 'tls',
    'username' => '',
    'password' => '',
    'from_email' => 'no-reply@example.edu.my',
    'from_name' => 'USMSmart',
);