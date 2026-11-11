<?php
/*
 * Optional SMTP transport for self-service password reset e-mails.
 * Copy to config/mail.local.php and fill in the local values; that file is gitignored.
 *
 * Without this file the app tries PHP mail(), and when no transport can deliver,
 * reset requests wait in Pengurusan Akaun for an administrator to hand over the
 * one-time link — the feature works either way.
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