<?php
/*
 * Optional Google OAuth / One Tap Sign-In Configuration for USMSmart.
 *
 * To enable the official Google Sign-In button:
 * 1. Visit Google Cloud Console: https://console.cloud.google.com/apis/credentials
 * 2. Create an OAuth 2.0 Client ID (Web Application).
 * 3. Add Authorized JavaScript origins: http://localhost and http://127.0.0.1
 * 4. Copy the Client ID below:
 */
return array(
    'client_id' => '', // e.g. '123456789012-abcdefghijklmnopqrstuvwxyz.apps.googleusercontent.com'
    // Google sign-in (and Google linking) may grant the Admin role only to
    // verified emails on these domains. Subdomains match; lookalikes do not.
    'admin_email_domains' => array('usm.my', 'student.usm.my'),
);
