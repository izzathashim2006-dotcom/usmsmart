<?php
/*
 * Database connection values, resolved once at boot.
 *
 * config/database.local.php keeps a local installation self-contained, but the
 * environment wins so a deployment can avoid keeping a password on disk. Unlike
 * the mail and Sysweb resolvers, an explicitly empty variable is honoured here:
 * a local MySQL account with no password is a valid setup. Only an unset
 * variable falls back to the file.
 */
if (!function_exists('usmsmartDbConfigValue')) {
    function usmsmartDbConfigValue(array $config, $key, $envName, $default = '') {
        $value = getenv($envName);
        if ($value === false) {
            $value = $_SERVER[$envName] ?? ($_ENV[$envName] ?? null);
        }
        if ($value === null) {
            return (string)($config[$key] ?? $default);
        }
        return (string)$value;
    }
}
