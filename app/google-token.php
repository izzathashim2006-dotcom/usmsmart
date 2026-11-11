<?php
/*
 * Google ID token verification for Sign in with Google / account linking.
 *
 * The browser obtains a credential (JWT) from Google Identity Services; nothing
 * about that token is trusted until this file verifies it. Primary path: RS256
 * signature check against Google's published JWKS (cached for an hour, refetched
 * once on an unknown kid). Fallback path: Google's tokeninfo endpoint, which
 * verifies the signature server-side at Google. Both paths end in the same
 * issuer / audience / expiry / email_verified claim checks, so a forged or
 * hand-typed email can never authenticate or link an account.
 */

if (!defined('USM_ROOT')) {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

function usmGoogleB64UrlDecode($value) {
    $value = strtr((string)$value, '-_', '+/');
    $pad = strlen($value) % 4;
    if ($pad > 0) { $value .= str_repeat('=', 4 - $pad); }
    return base64_decode($value, true);
}

function usmGoogleHttpGet($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'USMSmart/1.0',
        );
        // XAMPP ships a CA bundle but some SAPIs do not load curl.cainfo from php.ini.
        if (!ini_get('curl.cainfo')) {
            foreach (array('C:/xampp/apache/bin/curl-ca-bundle.crt', 'C:/xampp/php/extras/ssl/cacert.pem') as $caFile) {
                if (is_file($caFile)) { $options[CURLOPT_CAINFO] = $caFile; break; }
            }
        }
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        curl_close($ch);
        if ($body !== false) { return $body; }
    }
    // Some PHP builds (e.g. XAMPP CLI) ship without curl; HTTPS streams still verify
    // the peer through openssl.cafile, so the JWKS/tokeninfo paths keep working.
    if (!ini_get('allow_url_fopen')) { return null; }
    $context = stream_context_create(array(
        'http' => array('timeout' => 8, 'user_agent' => 'USMSmart/1.0', 'ignore_errors' => true),
        'ssl' => array('verify_peer' => true, 'verify_peer_name' => true),
    ));
    $body = @file_get_contents($url, false, $context);
    return $body === false ? null : $body;
}

function usmGoogleFetchJwks($forceRefresh = false) {
    $cacheFile = USM_ROOT . '/config/google-jwks.cache.json';
    if (!$forceRefresh && is_file($cacheFile)) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached) && (int)($cached['fetched_at'] ?? 0) > time() - 3600 && !empty($cached['keys'])) {
            return $cached['keys'];
        }
    }
    $json = json_decode((string)usmGoogleHttpGet('https://www.googleapis.com/oauth2/v3/certs'), true);
    if (!is_array($json) || empty($json['keys']) || !is_array($json['keys'])) { return null; }
    @file_put_contents($cacheFile, json_encode(array('fetched_at' => time(), 'keys' => $json['keys'])), LOCK_EX);
    return $json['keys'];
}

function usmGoogleDerLength($length) {
    if ($length < 128) { return chr($length); }
    $bytes = '';
    while ($length > 0) { $bytes = chr($length & 0xff) . $bytes; $length >>= 8; }
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function usmGoogleDerInteger($binary) {
    $binary = ltrim($binary, "\x00");
    if ($binary === '') { $binary = "\x00"; }
    if (ord($binary[0]) & 0x80) { $binary = "\x00" . $binary; }
    return "\x02" . usmGoogleDerLength(strlen($binary)) . $binary;
}

/* Build a PEM SubjectPublicKeyInfo from a JWK RSA modulus/exponent pair. */
function usmGoogleJwkToPem($modulusB64Url, $exponentB64Url) {
    $modulus = usmGoogleB64UrlDecode($modulusB64Url);
    $exponent = usmGoogleB64UrlDecode($exponentB64Url);
    if ($modulus === false || $exponent === false || $modulus === '' || $exponent === '') { return null; }
    $rsaPublicKey = usmGoogleDerInteger($modulus) . usmGoogleDerInteger($exponent);
    $rsaPublicKey = "\x30" . usmGoogleDerLength(strlen($rsaPublicKey)) . $rsaPublicKey;
    // AlgorithmIdentifier: rsaEncryption (1.2.840.113549.1.1.1) with NULL parameters.
    $algorithmId = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
    $bitString = "\x03" . usmGoogleDerLength(strlen($rsaPublicKey) + 1) . "\x00" . $rsaPublicKey;
    $spki = "\x30" . usmGoogleDerLength(strlen($algorithmId . $bitString)) . $algorithmId . $bitString;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/*
 * Returns the verified claim set (email lowercased, sub present) or null.
 * $clientId must be this installation's OAuth client ID; tokens minted for
 * any other audience are rejected even when the signature is valid.
 */
function usmGoogleVerifyIdToken($credential, $clientId) {
    $credential = trim((string)$credential);
    $clientId = trim((string)$clientId);
    if ($credential === '' || $clientId === '') { return null; }
    $parts = explode('.', $credential);
    if (count($parts) !== 3) { return null; }
    $header = json_decode((string)usmGoogleB64UrlDecode($parts[0]), true);
    $payload = json_decode((string)usmGoogleB64UrlDecode($parts[1]), true);
    $signature = usmGoogleB64UrlDecode($parts[2]);
    if (!is_array($header) || !is_array($payload) || $signature === false || $signature === '') { return null; }
    if (($header['alg'] ?? '') !== 'RS256') { return null; }
    $kid = (string)($header['kid'] ?? '');

    $signatureVerified = false;
    if ($kid !== '' && function_exists('openssl_verify')) {
        // Second pass forces a JWKS refetch: Google rotates keys, and a cached
        // set may predate the kid on a perfectly legitimate fresh token.
        foreach (array(false, true) as $forceRefresh) {
            $keys = usmGoogleFetchJwks($forceRefresh);
            if (!$keys) { break; }
            foreach ($keys as $jwk) {
                if (($jwk['kid'] ?? '') !== $kid || ($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) { continue; }
                $pem = usmGoogleJwkToPem($jwk['n'], $jwk['e']);
                if ($pem === null) { continue; }
                if (openssl_verify($parts[0] . '.' . $parts[1], $signature, $pem, OPENSSL_ALGO_SHA256) === 1) {
                    $signatureVerified = true;
                    break 2;
                }
            }
        }
    }

    if (!$signatureVerified) {
        // Fallback: let Google verify the signature. A forged token returns an
        // error object here, which fails the same claim checks below.
        $info = json_decode((string)usmGoogleHttpGet('https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credential)), true);
        if (!is_array($info) || empty($info['email'])) { return null; }
        $payload = $info;
    }

    $issuer = (string)($payload['iss'] ?? '');
    if (!in_array($issuer, array('accounts.google.com', 'https://accounts.google.com'), true)) { return null; }
    if ((string)($payload['aud'] ?? '') !== $clientId) { return null; }
    if (isset($payload['azp']) && (string)$payload['azp'] !== $clientId) { return null; }
    $now = time();
    if ((int)($payload['exp'] ?? 0) < $now - 30) { return null; }
    if ((int)($payload['iat'] ?? 0) > $now + 30) { return null; }
    $email = strtolower(trim((string)($payload['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { return null; }
    $emailVerified = $payload['email_verified'] ?? false;
    if (!($emailVerified === true || $emailVerified === 'true' || $emailVerified === 1 || $emailVerified === '1')) { return null; }
    if (empty($payload['sub'])) { return null; }
    $payload['email'] = $email;
    return $payload;
}

/*
 * Domain allowlist for the Admin role via Google: the email must sit exactly on
 * a listed domain or on a subdomain of it. "usm.my.example.com" never matches
 * "usm.my", so a lookalike domain cannot inherit the privilege.
 */
function usmGoogleEmailIsUsmDomain($email, array $domains) {
    $email = strtolower(trim((string)$email));
    $at = strrpos($email, '@');
    if ($at === false) { return false; }
    $domain = substr($email, $at + 1);
    if ($domain === '') { return false; }
    foreach ($domains as $allowed) {
        $allowed = strtolower(trim((string)$allowed));
        if ($allowed === '') { continue; }
        if ($domain === $allowed || substr($domain, -strlen('.' . $allowed)) === '.' . $allowed) { return true; }
    }
    return false;
}