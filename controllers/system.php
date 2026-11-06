<?php
// PWA manifest and read-only source health probes (db_status, system_health).
// Included by index.php in the same scope, so it reads $action, $pdo,
// $authReady and $_SESSION directly. USM_ROOT always points at the app root.
if (!defined('USM_ROOT')) {
    // Reached directly instead of through index.php: these are includes, not endpoints.
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

// PWA Manifest Endpoint
if ($action === 'manifest') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'name' => 'USMSmart UDA Transporter',
        'short_name' => 'USMSmart',
        'start_url' => 'index.php',
        'display' => 'standalone',
        'background_color' => '#09090B',
        'theme_color' => '#6366f1',
        'icons' => array(
            array('src' => 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png', 'sizes' => '192x192', 'type' => 'image/png')
        )
    ));
    exit;
}

/* ---- The header pill asks "is the live database answering?" without a page reload. -----------
   No probe runs while the dashboard renders, so a dead or slow remote host cannot add latency to a
   page load: the pill paints "Checking" and fills in from this endpoint. The verdict is cached in the
   session for 60s because a timer that re-probes an unreachable server queues a fresh connect attempt
   behind the login timeout on every tick. Driver messages are never returned - they repeat the host
   and the login name - so only these generic details leave PHP, and the same two probe functions also
   answer action=system_health, which keeps the pill and the health panel from disagreeing. */
function usmsmartSqlServerSourceProbe() {
    $dbname = '';
    if (!is_file(USM_ROOT . '/sqlserver_connection.php')) {
        return array('status'=>'warning','kind'=>'not_installed','detail'=>'Source connection file is not installed','dbname'=>'');
    }
    try {
        $source = usmsmartGetSqlServerPdo();
        $source->query('SELECT 1')->fetchColumn();
        /* The badge has to name the database this panel pulls from, and the connection itself is the only
           honest source for that name: the DSN, host and credentials stay in sqlserver_connection.php, which
           is never read into the UI. A failure here costs the name, never the status. */
        try { $dbname = trim((string)$source->query('SELECT DB_NAME()')->fetchColumn()); } catch (Throwable $e) { $dbname = ''; }
    } catch (Throwable $e) {
        error_log('USMSmart SQL Server source probe failed: ' . $e->getMessage());
        return array('status'=>'warning','kind'=>'unreachable','detail'=>'Unreachable; MySQL features remain available','dbname'=>$dbname);
    }
    try {
        $source->query('SELECT TOP 0 * FROM dbo.Pelajar');
        $source->query('SELECT TOP 0 * FROM dbo.Staf');
    } catch (Throwable $e) {
        return array('status'=>'warning','kind'=>'unreadable','detail'=>'Connected, but dbo.Pelajar or dbo.Staf could not be read','dbname'=>$dbname);
    }
    return array('status'=>'ok','kind'=>'connected','detail'=>'Connected; dbo.Pelajar and dbo.Staf are readable','dbname'=>$dbname);
}

function usmsmartSyswebCardProbe() {
    $config = usmsmartSyswebLiveConfig();
    if (!usmsmartSyswebLiveReady($config)) {
        return array('status'=>'warning','mode'=>'not_configured','detail'=>'Not configured; card checks use the local snapshot');
    }
    /* Clamped hard: this runs on a timer in front of a person, so three seconds is the whole budget. */
    $config['login_timeout'] = max(1, min(3, (int)($config['login_timeout'] ?? 3)));
    $columns = array();
    try {
        $live = usmsmartGetSyswebLivePdo($config);
        $columns = usmsmartSyswebLiveColumns($live, (string)$config['card_table']);
    } catch (Throwable $e) {
        error_log('USMSmart Sysweb card database probe failed: ' . $e->getMessage());
        return array('status'=>'warning','mode'=>'unavailable','detail'=>'Unreachable; card checks use the local snapshot');
    }
    if (!count($columns)) {
        return array('status'=>'warning','mode'=>'unreadable','detail'=>'Connected, but the card table could not be read');
    }
    $overrides = is_array($config['card_columns'] ?? null) ? $config['card_columns'] : array();
    $plan = usmsmartSyswebLivePlan($columns, $overrides);
    if (($plan['identifier'] ?? '') === '') {
        return array('status'=>'warning','mode'=>'unreadable','detail'=>'Card table has no usable identifier column');
    }
    $unmapped = array_values(array_filter(array('uid', 'serial', 'expiry', 'policy'),
        static function ($field) use ($plan) { return ($plan[$field] ?? '') === ''; }));
    if (count($unmapped)) {
        return array('status'=>'warning','mode'=>'live','detail'=>'Connected; fields not mapped yet: ' . implode(', ', $unmapped));
    }
    return array('status'=>'ok','mode'=>'live','detail'=>'Connected; card records are read live');
}

/* The write path is optional, so an unconfigured login reads as "off", not as a
   degraded system. Once write_enabled is set, however, every missing key or
   denied table right is a warning the administrator has to act on before an
   export can write. This probe never inserts or updates a row: the permissions
   come from SQL Server's own effective-rights function. */
function usmsmartSyswebWriteProbe() {
    $config = usmsmartSyswebLiveConfig();
    $missing = usmsmartSyswebWriteMissing($config);
    if ($missing === array('write_enabled')) {
        return array('status'=>'ok','mode'=>'off','detail'=>'Not configured (optional); exports stay in MySQL');
    }
    if (count($missing)) {
        return array('status'=>'warning','mode'=>'not_configured','detail'=>'Enabled, but missing: ' . implode(', ', $missing));
    }
    $config['login_timeout'] = max(1, min(3, (int)($config['login_timeout'] ?? 3)));
    $table = usmsmartSyswebWriteTable($config);
    try {
        $live = usmsmartGetSyswebWritePdo($config);
        $columns = usmsmartSyswebLiveColumns($live, $table);
        if (!count($columns)) {
            return array('status'=>'warning','mode'=>'unreadable','detail'=>'Write login connected, but the columns of ' . $table . ' could not be read');
        }
        $planMissing = array();
        usmsmartSyswebWritePlan($columns, is_array($config['card_columns'] ?? null) ? $config['card_columns'] : array(), $planMissing);
        if (count($planMissing)) {
            return array('status'=>'warning','mode'=>'unmapped','detail'=>'Write login connected; unmapped columns: ' . implode(', ', $planMissing));
        }
        $permissions = usmsmartSyswebWritePermissions($live, $table);
        if (!count($permissions)) {
            return array('status'=>'warning','mode'=>'permission','detail'=>'Write login connected, but its table rights could not be confirmed');
        }
        $denied = array();
        foreach (array('SELECT', 'INSERT', 'UPDATE') as $right) {
            if (!$permissions[strtolower($right)]) $denied[] = $right;
        }
        if (count($denied)) {
            return array('status'=>'warning','mode'=>'permission','detail'=>'Write login connected, but ' . implode(' and ', $denied) . ' is not granted on ' . $table);
        }
        return array('status'=>'ok','mode'=>'ready','detail'=>'Write login connected; SELECT, INSERT and UPDATE are permitted on ' . $table);
    } catch (Throwable $e) {
        error_log('USMSmart ccadmin write probe failed: ' . $e->getMessage());
        return array('status'=>'warning','mode'=>'unavailable','detail'=>'Write login unreachable; exports stay in MySQL');
    }
}

function usmsmartDbSources($pdo) {
    $mysql = array('status'=>'error','detail'=>'Query failed');
    try {
        $pdo->query('SELECT 1')->fetchColumn();
        $mysql = array('status'=>'ok','detail'=>'Connected');
    } catch (Throwable $e) {
        error_log('USMSmart MySQL status probe failed: ' . $e->getMessage());
    }
    $directory = usmsmartSqlServerSourceProbe();
    $card = usmsmartSyswebCardProbe();
    $sources = array(
        array('key'=>'mysql','name'=>'USMSmart database','status'=>$mysql['status'],'detail'=>$mysql['detail'],'mode'=>''),
        array('key'=>'directory','name'=>'Student and staff directory','status'=>$directory['status'],'detail'=>$directory['detail'],'mode'=>'','kind'=>(string)($directory['kind'] ?? ''),'dbname'=>(string)($directory['dbname'] ?? '')),
        array('key'=>'card','name'=>'Sysweb card database','status'=>$card['status'],'detail'=>$card['detail'],'mode'=>(string)($card['mode'] ?? '')),
    );
    /* The pill shows the worst source, because a silent fallback to the local snapshot must never
       read as healthy. Anything else would defeat the point of putting it in the chrome. */
    $worst = $sources[0];
    foreach ($sources as $source) {
        if ($source['status'] === 'error') { $worst = $source; break; }
        if ($source['status'] === 'warning' && $worst['status'] === 'ok') $worst = $source;
    }
    $label = 'Live data';
    if ($worst['status'] === 'error') {
        $label = 'Storage offline';
    } elseif ($worst['status'] === 'warning') {
        if ($worst['key'] === 'directory') $label = 'Directory off';
        elseif ($worst['key'] === 'card') $label = ($worst['mode'] === 'not_configured') ? 'Card DB unset' : 'Snapshot data';
        else $label = 'Degraded';
    }
    return array('overall'=>$worst['status'],'label'=>$label,'hint'=>$worst['name'] . ' - ' . $worst['detail'],
        'sources'=>$sources,'checked_at'=>date('Y-m-d H:i:s'));
}

if ($action === 'db_status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    /* The pill polls this once a minute, and the byte-order mark that opens the file is sitting in the
       buffer PHP started with; every cached and fresh answer is JSON, so it goes. */
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    if (empty($_SESSION['user_id']) || !in_array((string)($_SESSION['role'] ?? ''), array('admin', 'viewer'), true)) {
        http_response_code(401);
        echo json_encode(array('success'=>false,'message'=>'Sesi log masuk diperlukan.'));
        exit;
    }
    $cache = is_array($_SESSION['usmsmart_db_status'] ?? null) ? $_SESSION['usmsmart_db_status'] : array();
    if (!isset($_GET['force']) && isset($cache['data']['sources']) && (time() - (int)($cache['at'] ?? 0)) < 60) {
        echo json_encode(array('success'=>true,'cached'=>true) + $cache['data'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $status = usmsmartDbSources($pdo);
    $_SESSION['usmsmart_db_status'] = array('at'=>time(),'data'=>$status);
    echo json_encode(array('success'=>true,'cached'=>false) + $status, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'system_health' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    /* Nothing a probe prints may land in front of this JSON, and the byte-order mark at the top of
       the file is already sitting in the buffer PHP started with. Drain it, then collect anything the
       probes emit and throw it away: a notice becomes a parse error in the panel otherwise. */
    while (ob_get_level() > 0) { ob_end_clean(); }
    ob_start();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    $checks = array();
    $checks[] = array('name'=>'PHP runtime','status'=>PHP_VERSION_ID >= 70400 ? 'ok' : 'warning','detail'=>'PHP '.PHP_VERSION);
    try {
        $pdo->query('SELECT 1')->fetchColumn();
        $checks[] = array('name'=>'MySQL database','status'=>'ok','detail'=>'Database connection is working');
    } catch (Throwable $e) {
        $checks[] = array('name'=>'MySQL database','status'=>'error','detail'=>'Database query failed');
    }
    $tables = array();
    try {
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $checks[] = array('name'=>'Database tables','status'=>count($tables) > 0 ? 'ok' : 'warning','detail'=>count($tables).' tables detected');
    } catch (Throwable $e) {
        $checks[] = array('name'=>'Database tables','status'=>'error','detail'=>'Could not inspect database tables');
    }
    $requiredTables = array('students_view', 'uda_system_records', 'uda_transport_sessions');
    $missingTables = array_values(array_diff($requiredTables, $tables));
    $checks[] = array(
        'name'=>'USMSmart UDA tables',
        'status'=>empty($missingTables) ? 'ok' : 'warning',
        'detail'=>empty($missingTables) ? 'Student, UDA record, and session tables are present' : 'Missing table(s): '.implode(', ', $missingTables)
    );
    try {
        $sourcePdo = usmsmartGetSqlServerPdo();
        $sourcePdo->query('SELECT 1')->fetchColumn();
        $checks[] = array('name'=>'SQL Server source','status'=>'ok','detail'=>'Source connection is working');
        try {
            $sourcePdo->query('SELECT TOP 0 * FROM dbo.Pelajar');
            $sourcePdo->query('SELECT TOP 0 * FROM dbo.Staf');
            $checks[] = array('name'=>'SQL Server source tables','status'=>'ok','detail'=>'dbo.Pelajar and dbo.Staf are accessible');
        } catch (Throwable $e) {
            $checks[] = array('name'=>'SQL Server source tables','status'=>'warning','detail'=>'Connection works, but one or more expected source tables could not be checked');
        }
    } catch (Throwable $e) {
        /* A driver failure is reported by its SQLSTATE, never by its message, because the message a
           rejected login carries names the account it failed for. Everything else this function
           throws is one of ours and says what is missing. */
        $sourceState = (string)$e->getCode();
        $sourceReason = ($e instanceof PDOException)
            ? ($sourceState !== '' && $sourceState !== '0' ? 'SQLSTATE ' . $sourceState : 'the driver reported no state')
            : usmsmartProbeError($e->getMessage());
        $checks[] = array('name'=>'SQL Server source','status'=>'warning','detail'=>'Source connection unavailable (' . $sourceReason . '); MySQL features remain available');
    }
    /* Same function the header pill uses, so the panel cannot claim a source is fine while the pill
       says it is not. */
    $cardProbe = usmsmartSyswebCardProbe();
    $checks[] = array('name'=>'Sysweb card database','status'=>$cardProbe['status'],'detail'=>$cardProbe['detail']);
    $writeProbe = usmsmartSyswebWriteProbe();
    $checks[] = array('name'=>'ccadmin write login','status'=>$writeProbe['status'],'detail'=>$writeProbe['detail']);
    $ollamaUrl = getenv('USMSMART_OLLAMA_URL');
    if (!$ollamaUrl) $ollamaUrl = 'http://127.0.0.1:11434/api/generate';
    $ollamaBase = rtrim(preg_replace('~/api/(generate|chat|tags)/?$~', '', $ollamaUrl), '/');
    /* /api/tags is both the liveness answer and the model list, so one request is a real check. */
    $ollamaProbe = usmsmartHttpFetch($ollamaBase . '/api/tags', 4);
    if ($ollamaProbe['transport'] === 'none') {
        $ollamaStatus = 'warning';
        $ollamaDetail = 'PHP has no HTTP transport enabled, so Ollama could not be probed';
    } elseif ($ollamaProbe['ok'] && $ollamaProbe['code'] >= 200 && $ollamaProbe['code'] < 300) {
        $ollamaData = json_decode($ollamaProbe['body'], true);
        $ollamaModels = (isset($ollamaData['models']) && is_array($ollamaData['models'])) ? $ollamaData['models'] : array();
        $ollamaNames = array();
        foreach (array_slice($ollamaModels, 0, 3) as $ollamaModel) {
            $ollamaName = trim((string)(is_array($ollamaModel) ? ($ollamaModel['name'] ?? '') : $ollamaModel));
            if ($ollamaName !== '') $ollamaNames[] = $ollamaName;
        }
        $ollamaStatus = 'ok';
        $ollamaDetail = count($ollamaModels) . ' local model(s) ready' . ($ollamaNames ? ': ' . implode(', ', $ollamaNames) : '');
    } elseif ($ollamaProbe['ok']) {
        $ollamaStatus = 'error';
        $ollamaDetail = 'Ollama answered HTTP ' . (int)$ollamaProbe['code'] . ' at ' . $ollamaBase;
    } else {
        $ollamaStatus = 'error';
        $ollamaDetail = 'Ollama is not answering at ' . $ollamaBase
            . ($ollamaProbe['error'] !== '' ? ' (' . usmsmartProbeError($ollamaProbe['error']) . ')' : '');
    }
    $checks[] = array('name'=>'Ollama service','status'=>$ollamaStatus,'detail'=>$ollamaDetail);
    $backupDir = usmsmartBackupDirectory();
    $backupFiles = glob($backupDir . DIRECTORY_SEPARATOR . 'usmsmart_*.json') ?: array();
    $latestBackup = null;
    foreach ($backupFiles as $backupFile) {
        if (is_file($backupFile) && ($latestBackup === null || filemtime($backupFile) > filemtime($latestBackup))) $latestBackup = $backupFile;
    }
    if ($latestBackup) {
        $age = max(0, time() - filemtime($latestBackup));
        $checks[] = array('name'=>'Latest backup','status'=>$age <= 86400*2 ? 'ok' : 'warning','detail'=>'Last backup '.date('Y-m-d H:i', filemtime($latestBackup)));
    } else {
        $checks[] = array('name'=>'Latest backup','status'=>'warning','detail'=>'No JSON backup file found yet');
    }
    $hasError = false; $hasWarning = false;
    foreach ($checks as $check) { if ($check['status'] === 'error') $hasError = true; if ($check['status'] === 'warning') $hasWarning = true; }
    /* Drop whatever a probe printed; only the JSON is allowed to reach the caller. */
    ob_end_clean();
    echo json_encode(array('success'=>true,'overall'=>$hasError?'error':($hasWarning?'warning':'ok'),'checked_at'=>date('Y-m-d H:i:s'),'checks'=>$checks), JSON_UNESCAPED_UNICODE);
    exit;
}
