<?php
/*
 * Reports whether the optional ccadmin write login is ready. It never writes.
 *
 *     php tools/ccadmin-check.php             # configuration keys only
 *     php tools/ccadmin-check.php --connect   # login, column mapping, rights
 *
 * The private config is the same one the application reads;
 * USMSMART_SYSWEB_CONFIG overrides its path. Credentials are never printed.
 * --connect uses the write login once to read column metadata and to ask SQL
 * Server for its effective SELECT/INSERT/UPDATE rights on the table; no row is
 * touched. Exit code 0 means ready, 1 means something still has to change.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('USM_ROOT', dirname(__DIR__));
require USM_ROOT . '/app/functions.php';

$args = array_slice($argv, 1);
if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    echo "Usage: php tools/ccadmin-check.php [--connect]\n\n";
    echo "  (no option)  check that write_enabled and the required keys are set\n";
    echo "  --connect    also verify the write login, column mapping and rights\n";
    exit(0);
}
$unknown = array_diff($args, array('--connect'));
if (count($unknown)) {
    fwrite(STDERR, 'Unknown option(s): ' . implode(' ', $unknown) . ". Try --help.\n");
    exit(2);
}
$connect = in_array('--connect', $args, true);

$file = usmsmartSyswebLiveConfigFile();
$config = usmsmartSyswebLiveConfig();
echo "ccadmin write check\n";
echo 'config: ' . $file . (is_file($file) && is_readable($file) ? '' : ' (not readable)') . "\n";
if (!count($config)) {
    fwrite(STDERR, "status: no configuration found; the write path stays off\n");
    exit(1);
}

$missing = usmsmartSyswebWriteMissing($config);
$enabled = !empty($config['write_enabled']);
$table = usmsmartSyswebWriteTable($config);
$uidMode = strtolower(trim((string)($config['write_uid'] ?? 'source'))) === 'converted' ? 'converted' : 'source';
echo 'write_enabled: ' . ($enabled ? 'yes' : 'no') . "\n";
echo 'table: ' . ($table !== '' ? $table : '(unset)') . "\n";
echo 'uid stored: ' . $uidMode . ' (' . ($uidMode === 'converted' ? 'little-endian, as staged for UDA' : 'raw card UID, as the read side expects') . ")\n";

if ($missing === array('write_enabled')) {
    echo "status: disabled (optional); exports stay in MySQL\n";
    exit($connect ? 1 : 0);
}
if (count($missing)) {
    echo 'status: incomplete; missing ' . implode(', ', $missing) . "\n";
    echo "Set those keys in the private config, then run this check again.\n";
    exit(1);
}
echo "configuration: complete\n";

if (!$connect) {
    echo "Next: php tools/ccadmin-check.php --connect\n";
    exit(0);
}
if (!in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "error: the sqlsrv PDO driver is not enabled in this PHP CLI\n");
    exit(1);
}

$config['login_timeout'] = max(1, min(15, (int)($config['login_timeout'] ?? 5)));
try {
    $live = usmsmartGetSyswebWritePdo($config);
    $columns = usmsmartSyswebLiveColumns($live, $table);
    if (!count($columns)) {
        echo "table columns: unreadable; check SELECT and INFORMATION_SCHEMA access\n";
        exit(1);
    }
    $planMissing = array();
    $overrides = is_array($config['card_columns'] ?? null) ? $config['card_columns'] : array();
    $plan = usmsmartSyswebWritePlan($columns, $overrides, $planMissing);
    foreach (array('identifier', 'uid', 'serial', 'expiry', 'policy') as $field) {
        echo str_pad($field . ' column', 18) . ': ' . (($plan[$field] ?? '') !== '' ? $plan[$field] : '(unmapped)') . "\n";
    }
    if (count($planMissing)) {
        echo 'status: still needs ' . implode(', ', $planMissing) . " mapped in card_columns\n";
        exit(1);
    }
    $permissions = usmsmartSyswebWritePermissions($live, $table);
    if (!count($permissions)) {
        echo "status: rights could not be confirmed by the server\n";
        exit(1);
    }
    $granted = true;
    foreach (array('SELECT', 'INSERT', 'UPDATE') as $right) {
        $ok = !empty($permissions[strtolower($right)]);
        if (!$ok) $granted = false;
        echo str_pad($right, 7) . ': ' . ($ok ? 'granted' : 'NOT granted') . "\n";
    }
    if (!$granted) {
        echo "status: rights missing; grant them with the GRANT statement in README.md\n";
        exit(1);
    }
    echo 'status: ready; an administrator export will write to ' . $table . "\n";
} catch (Throwable $e) {
    $state = (string)$e->getCode();
    fwrite(STDERR, 'error: write login failed' . ($state !== '' && $state !== '0' ? ' (SQLSTATE ' . $state . ')' : '') . "\n");
    exit(1);
}
