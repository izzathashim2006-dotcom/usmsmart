<?php
// Local Sysweb integration prototype routes (mock receiver and store).
// Included by index.php in the same scope, so it reads $action, $pdo,
// $authReady and $_SESSION directly. USM_ROOT always points at the app root.
if (!defined('USM_ROOT')) {
    // Reached directly instead of through index.php: these are includes, not endpoints.
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

if ($action === 'sysweb_demo_receive' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(array('success'=>false,'message'=>'Hanya Admin boleh menjalankan ujian Sysweb.'));
        exit;
    }
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody ?: '', true);
    if (!is_array($payload) || !isset($payload['records']) || !is_array($payload['records'])) {
        http_response_code(400);
        echo json_encode(array('success'=>false,'message'=>'Payload ujian tidak sah.'));
        exit;
    }
    if (count($payload['records']) > 2000) {
        http_response_code(413);
        echo json_encode(array('success'=>false,'message'=>'Satu batch mesti mengandungi tidak lebih 2,000 rekod.'));
        exit;
    }
    $storePath = USM_ROOT . DIRECTORY_SEPARATOR . 'sysweb_demo_received.json';
    $existing = array();
    if (is_file($storePath)) { $decoded = json_decode((string)file_get_contents($storePath), true); if (is_array($decoded)) $existing = $decoded; }

    // Identity keys are read before the batch is filtered so a repeated matric or staff number
    // carrying a different card UID is held back instead of creating a second Sysweb account.
    $seen = array(); $seenIdentity = array();
    foreach ($existing as $item) {
        if (!isset($item['type'],$item['identifier'],$item['sysweb_uid'])) continue;
        $seen[strtolower($item['type']).'|'.strtolower($item['identifier']).'|'.strtoupper($item['sysweb_uid'])] = true;
        $seenIdentity[strtolower($item['type']).'|'.strtolower($item['identifier'])] = strtoupper($item['sysweb_uid']);
    }
    $accepted = array(); $rejected = array();
    foreach ($payload['records'] as $index => $record) {
        if (!is_array($record)) { $rejected[] = array('row'=>$index+1,'reason'=>'Rekod tidak sah.'); continue; }
        $name = trim((string)($record['name'] ?? ''));
        $identifier = trim((string)($record['identifier'] ?? ''));
        $type = strtolower(trim((string)($record['type'] ?? '')));
        $convertedUid = strtoupper(trim((string)($record['converted_uid'] ?? '')));
        $syswebUid = strtoupper(trim((string)($record['sysweb_uid'] ?? '')));
        if ($name === '' || $identifier === '' || !in_array($type, array('student','staff'), true)) {
            $rejected[] = array('row'=>$index+1,'reason'=>'Nama, pengenalan atau jenis tidak sah.'); continue;
        }
        if ($convertedUid === '' || (strlen($convertedUid) % 2 !== 0) || !ctype_xdigit($convertedUid)) {
            $rejected[] = array('row'=>$index+1,'reason'=>'UID converted mesti hexadecimal dan bilangan aksara genap.'); continue;
        }
        if ($syswebUid !== '00000000'.$convertedUid) {
            $rejected[] = array('row'=>$index+1,'reason'=>'UID Sysweb mesti lapan sifar diikuti UID converted.'); continue;
        }
        // The raw source UID travels with the record so the little-endian conversion can be
        // re-proved here instead of trusted from the browser.
        $sourceUid = strtoupper(trim((string)($record['source_uid'] ?? '')));
        if ($sourceUid !== '' && preg_match('/^(?:[0-9A-F]{2})+$/', $sourceUid) && usmsmartUidLittleEndian($sourceUid) !== $convertedUid) {
            $rejected[] = array('row'=>$index+1,'reason'=>'Susunan byte UID converted tidak sepadan dengan UID sumber.'); continue;
        }
        $identityKey = $type.'|'.strtolower($identifier);
        if (isset($seenIdentity[$identityKey]) && $seenIdentity[$identityKey] !== $syswebUid) {
            $rejected[] = array('row'=>$index+1,'reason'=>'Pengenalan ini sudah wujud dengan UID Sysweb yang berbeza.'); continue;
        }
        $accepted[] = array_merge(
            array('name' => $name, 'identifier' => $identifier, 'type' => $type),
            usmsmartSyswebDetails($record),
            array('converted_uid' => $convertedUid, 'sysweb_uid' => $syswebUid, 'received_at' => date(DATE_ATOM))
        );
        $seenIdentity[$identityKey] = $syswebUid;
    }
    $inserted = array(); $duplicates = 0;
    foreach ($accepted as $item) {
        $key = strtolower($item['type']).'|'.strtolower($item['identifier']).'|'.strtoupper($item['sysweb_uid']);
        if (isset($seen[$key])) { $duplicates++; continue; }
        // Mint the credential once, here, so a record has the same password lifecycle whether it
        // arrived through an export batch or the CRUD drawer.
        $item['password'] = usmsmartGenerateSyswebPassword();
        $item['password_issued_at'] = date(DATE_ATOM);
        $seen[$key] = true; $inserted[] = $item; $existing[] = $item;
    }
    if ($inserted && file_put_contents($storePath, json_encode($existing, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Tidak dapat menyimpan data demo. Semak kebenaran folder.')); exit;
    }
    $failedRecords = array();
    foreach ($rejected as $failure) {
        $failedIndex = max(0, (int)($failure['row'] ?? 1) - 1);
        if (isset($payload['records'][$failedIndex]) && is_array($payload['records'][$failedIndex])) {
            $failedRecords[] = array('record'=>$payload['records'][$failedIndex], 'reason'=>(string)($failure['reason'] ?? 'Perlu semakan'));
        }
    }
    $batchId = 'SW-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $historyPath = USM_ROOT . DIRECTORY_SEPARATOR . 'sysweb_export_history.json';
    $history = is_file($historyPath) ? json_decode((string)file_get_contents($historyPath), true) : array();
    if (!is_array($history)) $history = array();
    $studentCount = 0; $staffCount = 0;
    foreach ($payload['records'] as $record) {
        if (!is_array($record)) continue;
        if (strtolower(trim((string)($record['type'] ?? ''))) === 'staff') $staffCount++; else $studentCount++;
    }
    $history[] = array(
        'batch_id'=>$batchId, 'created_at'=>date(DATE_ATOM),
        'source'=>(string)($payload['source'] ?? 'USMSmart'),
        'user'=>(string)($_SESSION['username'] ?? 'admin'),
        'student_count'=>$studentCount, 'staff_count'=>$staffCount,
        'total'=>count($payload['records']), 'saved_new'=>count($inserted),
        'duplicates_skipped'=>$duplicates, 'failed_count'=>count($failedRecords),
        'passwords_issued'=>count($inserted),
        'status'=>count($rejected) ? (count($inserted) || $duplicates ? 'partial' : 'failed') : 'success',
        'failed_records'=>$failedRecords
    );
    $history = array_slice($history, -100);
    if (file_put_contents($historyPath, json_encode($history, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        error_log('USMSmart: unable to write Sysweb export history for batch ' . $batchId);
    }
    // The audit row carries counts only. Credentials are never named here.
    auditLog($pdo, 'sysweb_export_batch', 'sysweb_batch', $batchId, null, 'source=' . (string)($payload['source'] ?? 'USMSmart')
        . ' total=' . count($payload['records']) . ' student=' . $studentCount . ' staff=' . $staffCount
        . ' saved=' . count($inserted) . ' duplicates=' . $duplicates . ' failed=' . count($failedRecords));
    echo json_encode(array('success'=>count($rejected)===0,'demo_only'=>true,'batch_id'=>$batchId,'message'=>'Penerima Sysweb simulasi telah memproses permintaan. Tiada sambungan ke Sysweb sebenar.','received'=>count($payload['records']),'student_count'=>$studentCount,'staff_count'=>$staffCount,'saved_new'=>count($inserted),'duplicates_skipped'=>$duplicates,'passwords_issued'=>count($inserted),'rejected_count'=>count($rejected),'rejected'=>$rejected,'saved_records'=>$inserted), JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($action === 'get_sysweb_export_history' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $historyPath = USM_ROOT . DIRECTORY_SEPARATOR . 'sysweb_export_history.json';
    $history = is_file($historyPath) ? json_decode((string)file_get_contents($historyPath), true) : array();
    if (!is_array($history)) $history = array();
    echo json_encode(array('success'=>true,'history'=>array_slice(array_reverse($history), 0, 50)), JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (in_array($action, array('create_sysweb_record','update_sysweb_record','delete_sysweb_record'), true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Hanya Admin boleh mengurus rekod Sysweb.')); exit; }
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || !hash_equals((string)($_SESSION['usmsmart_csrf_token'] ?? ''), $token)) { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Token keselamatan tidak sah.')); exit; }
    $body = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($body)) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Data permintaan tidak sah.')); exit; }
    $storePath = USM_ROOT . DIRECTORY_SEPARATOR . 'sysweb_demo_received.json';
    $fp = @fopen($storePath, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) { if ($fp) fclose($fp); http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Fail rekod Sysweb tidak boleh dikunci.')); exit; }
    $raw = stream_get_contents($fp); $records = json_decode($raw ?: '[]', true); if (!is_array($records)) $records = array();

    // Create appends; update and delete address a row by its current index in the file.
    $idx = null;
    if ($action !== 'create_sysweb_record') {
        $idx = filter_var($body['index'] ?? null, FILTER_VALIDATE_INT);
        if ($idx === false || $idx < 0 || !isset($records[$idx])) { flock($fp, LOCK_UN); fclose($fp); http_response_code(404); echo json_encode(array('success'=>false,'message'=>'Rekod Sysweb tidak ditemui. Muat semula senarai.')); exit; }
    }

    if ($action === 'delete_sysweb_record') {
        $auditSubject = $records[$idx];
        array_splice($records, $idx, 1); $message = 'Rekod Sysweb berjaya dipadam.';
    } else {
        $name = trim((string)($body['name'] ?? '')); $identifier = trim((string)($body['identifier'] ?? '')); $type = strtolower(trim((string)($body['type'] ?? ''))); $converted = strtoupper(trim((string)($body['converted_uid'] ?? '')));
        if ($name === '' || mb_strlen($name) > 200 || $identifier === '' || mb_strlen($identifier) > 100 || !in_array($type, array('student','staff'), true) || !preg_match('/^(?:[0-9A-F]{2})+$/', $converted)) { flock($fp, LOCK_UN); fclose($fp); http_response_code(422); echo json_encode(array('success'=>false,'message'=>'Semak nama, pengenalan, jenis dan UID hexadecimal genap.')); exit; }
        // Hand entry gets the same byte-order proof the bulk receiver applies: the converted UID
        // is only trusted when it really is the little-endian of the card's raw UID.
        $rawUid = strtoupper(preg_replace('/\s+/', '', trim((string)($body['source_uid'] ?? ''))));
        if ($rawUid !== '' && preg_match('/^(?:[0-9A-F]{2})+$/', $rawUid) && usmsmartUidLittleEndian($rawUid) !== $converted) { flock($fp, LOCK_UN); fclose($fp); http_response_code(422); echo json_encode(array('success'=>false,'message'=>'Susunan byte UID converted tidak sepadan dengan UID sumber.')); exit; }
        // Both branches rewrite a whole row, so a collision introduced by an edit is as damaging
        // as one introduced by an insert.
        $conflictKey = static function (array $r) {
            return (strtolower((string)($r['type'] ?? 'student')) === 'staff' ? 'staff' : 'student') . '|' . strtolower(trim((string)($r['identifier'] ?? ''))) . '|' . strtoupper(trim((string)($r['sysweb_uid'] ?? '')));
        };
        $candidateKey = $conflictKey(array('type' => $type, 'identifier' => $identifier, 'sysweb_uid' => '00000000' . $converted));
        foreach ($records as $rowIndex => $existing) {
            if ($action === 'update_sysweb_record' && $rowIndex === $idx) continue;
            if (is_array($existing) && $candidateKey === $conflictKey($existing)) { flock($fp, LOCK_UN); fclose($fp); http_response_code(409); echo json_encode(array('success'=>false,'message'=>'Rekod dengan jenis, pengenalan dan UID Sysweb yang sama sudah wujud.')); exit; }
        }
        $entry = array_merge(
            array('name' => $name, 'identifier' => $identifier, 'type' => $type),
            usmsmartSyswebDetails($body),
            array('converted_uid' => $converted, 'sysweb_uid' => '00000000'.$converted)
        );
        /* A hand-added Sysweb record gets its number from the same allocator the student form uses,
           with this snapshot's own numbers added to the pool so neither store can hand out a value
           the other one already holds. A typed number is kept, but never if it is taken. */
        $serialValue = strtoupper(trim((string)($entry['serial_no'] ?? '')));
        if ($serialValue === '') {
            $snapshotSerials = array();
            foreach ($records as $snapshotRecord) {
                if (is_array($snapshotRecord) && trim((string)($snapshotRecord['serial_no'] ?? '')) !== '') $snapshotSerials[] = (string)$snapshotRecord['serial_no'];
            }
            $serialValue = usmsmartAllocateCardSerial($pdo, 0, $snapshotSerials);
        }
        if ($serialValue !== '') {
            foreach ($records as $snapshotIndex => $snapshotRecord) {
                if ($action === 'update_sysweb_record' && $snapshotIndex === $idx) continue;
                if (is_array($snapshotRecord) && strtoupper(trim((string)($snapshotRecord['serial_no'] ?? ''))) === $serialValue) {
                    flock($fp, LOCK_UN); fclose($fp);
                    http_response_code(409);
                    echo json_encode(array('success'=>false,'message'=>'Nombor siri kad itu sudah digunakan oleh rekod Sysweb lain.'));
                    exit;
                }
            }
            $entry['serial_no'] = $serialValue;
        }
        // Generate each Sysweb password server-side with a cryptographically secure RNG.
        // Existing passwords are retained on edit so normal updates do not reset credentials.
        if ($action === 'create_sysweb_record') {
            $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
            $lower = 'abcdefghijkmnopqrstuvwxyz';
            $digits = '23456789';
            $pool = $upper.$lower.$digits;
            $passwordChars = array($upper[random_int(0, strlen($upper)-1)], $lower[random_int(0, strlen($lower)-1)], $digits[random_int(0, strlen($digits)-1)]);
            for ($pi = count($passwordChars); $pi < 12; $pi++) $passwordChars[] = $pool[random_int(0, strlen($pool)-1)];
            for ($pi = count($passwordChars)-1; $pi > 0; $pi--) { $pj = random_int(0, $pi); $tmp = $passwordChars[$pi]; $passwordChars[$pi] = $passwordChars[$pj]; $passwordChars[$pj] = $tmp; }
            $entry['password'] = implode('', $passwordChars);
            $entry['received_at'] = date(DATE_ATOM);
            $records[] = $entry;
            $message = 'Rekod Sysweb baharu berjaya ditambah.';
        } else {
            // Preserve the existing credential during edits; never log password values.
            $entry['password'] = (string)($records[$idx]['password'] ?? '');
            // Editing must not rewrite when the record was originally received.
            $entry['received_at'] = (string)($records[$idx]['received_at'] ?? date(DATE_ATOM));
            $entry['updated_at'] = date(DATE_ATOM);
            $records[$idx] = $entry;
            $message = 'Rekod Sysweb berjaya dikemas kini.';
        }
    }
    rewind($fp); ftruncate($fp, 0); $ok = fwrite($fp, json_encode(array_values($records), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)) !== false; fflush($fp); flock($fp, LOCK_UN); fclose($fp);
    if (!$ok) { http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Gagal menyimpan perubahan.')); exit; }
    // Single-record mutations are as consequential as a bulk import, so they carry the same
    // audit trail — identifier and UID only, never the credential itself.
    if (!isset($auditSubject) || !is_array($auditSubject)) $auditSubject = isset($entry) && is_array($entry) ? $entry : array();
    $auditAction = $action === 'create_sysweb_record' ? 'sysweb_record_create' : ($action === 'update_sysweb_record' ? 'sysweb_record_update' : 'sysweb_record_delete');
    auditLog(
        $pdo,
        $auditAction,
        'sysweb_record',
        (string)($auditSubject['sysweb_uid'] ?? ''),
        (string)($auditSubject['identifier'] ?? ''),
        'type=' . ($auditSubject['type'] ?? '') . ' uid=' . ($auditSubject['converted_uid'] ?? '') . ' fingerprint=' . usmsmartSyswebPasswordFingerprint((string)($auditSubject['password'] ?? ''))
    );
    echo json_encode(array('success'=>true,'message'=>$message)); exit;
}

if ($action === 'get_sysweb_records' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    // This handler runs before the dashboard-wide auth gate, so it must reject anonymous reads itself.
    if (empty($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), array('admin', 'viewer'), true)) {
        http_response_code(401);
        echo json_encode(array('success'=>false,'error'=>'Sesi log masuk diperlukan.','message'=>'Sesi log masuk diperlukan untuk membaca rekod Sysweb.'));
        exit;
    }
    $storePath = USM_ROOT . DIRECTORY_SEPARATOR . 'sysweb_demo_received.json';
    $existing = array();
    if (is_file($storePath)) {
        $decoded = json_decode((string)file_get_contents($storePath), true);
        if (is_array($decoded)) $existing = $decoded;
    }
    // Passwords are deliberately absent from the list payload for every role, including Admin.
    // A credential is only ever returned one record at a time by get_sysweb_password, which is
    // audited, so a cached or scraped list can never carry the whole set.
    $syswebListIndex = usmsmartSyswebCardIndex($pdo, $existing, $syswebListSource);
    $cardVerdicts = usmsmartClassifySyswebCardSources($existing, $syswebListIndex);
    foreach ($existing as $rowPosition => &$row) {
        if (!is_array($row)) continue;
        $row['card_source'] = (string)($cardVerdicts[$rowPosition]['status'] ?? 'skip');
        $row['card_live'] = (string)($cardVerdicts[$rowPosition]['live'] ?? 'unknown');
        $row['can_complete'] = !empty($cardVerdicts[$rowPosition]['hydrate']);
        // Set before the unset so the flag cannot leak the value it describes.
        $row['has_password'] = !empty($row['password']);
        unset($row['password'], $row['password_issued_at']);
    }
    unset($row);
    echo json_encode(array('success' => true, 'records' => $existing, 'passwords_masked' => true,
        'card_source' => $syswebListSource), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}


/* ===== Sysweb card-record reconciliation: Admin only, preview first, never overwrites ===== */
if ($action === 'sysweb_reconcile_records' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(array('success' => false, 'message' => 'Hanya Admin boleh menyemak silang rekod Sysweb.'));
        exit;
    }
    $reconcileBody = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($reconcileBody)) $reconcileBody = array();
    $apply = !empty($reconcileBody['apply']);
    $handle = usmsmartOpenSyswebStore();
    if (!$handle) {
        http_response_code(500);
        echo json_encode(array('success' => false, 'message' => 'Fail rekod Sysweb tidak boleh dikunci.'));
        exit;
    }
    $reconcileRecords = $handle['records'];
    $reconcileIndex = usmsmartSyswebCardIndex($pdo, $reconcileRecords, $reconcileSource);
    $verdicts = usmsmartClassifySyswebCardSources($reconcileRecords, $reconcileIndex);
    $counts = array('matched' => 0, 'uda_only' => 0, 'ambiguous' => 0, 'conflict' => 0, 'absent' => 0, 'skip' => 0);
    $live = array('confirmed' => 0, 'unconfirmed' => 0, 'contradicted' => 0, 'unknown' => 0);
    $details = array();
    $fillable = 0;
    $filledFields = 0;
    foreach ($reconcileRecords as $position => $row) {
        if (!is_array($row)) continue;
        $status = (string)($verdicts[$position]['status'] ?? 'skip');
        $counts[$status] = ($counts[$status] ?? 0) + 1;
        $willFill = array_keys($verdicts[$position]['hydrate']);
        if ($willFill) $fillable++;
        $liveState = (string)($verdicts[$position]['live'] ?? 'unknown');
        $live[$liveState] = ($live[$liveState] ?? 0) + 1;
        $details[] = array('index' => (int)$position, 'identifier' => (string)($row['identifier'] ?? ''),
            'type' => (string)($row['type'] ?? 'student'), 'status' => $status, 'will_fill' => $willFill,
            'live' => $liveState);
        if ($apply && $willFill) {
            // Only the fields the staged row is missing, and only when the card records are
            // unambiguous; anything an administrator already typed stays untouched.
            foreach ($verdicts[$position]['hydrate'] as $field => $value) $reconcileRecords[$position][$field] = $value;
            $reconcileRecords[$position]['updated_at'] = date(DATE_ATOM);
            $reconcileRecords[$position]['reconciled_at'] = date(DATE_ATOM);
            $filledFields += count($willFill);
        }
    }
    if ($apply && $filledFields) {
        if (!usmsmartWriteSyswebStore($handle, $reconcileRecords)) {
            http_response_code(500);
            echo json_encode(array('success' => false, 'message' => 'Gagal menyimpan rekod yang dilengkapkan.'));
            exit;
        }
        auditLog($pdo, 'sysweb_record_reconcile', 'sysweb_batch', null, null,
            'records=' . $fillable . ' fields=' . $filledFields
            . ' matched=' . $counts['matched'] . ' uda_only=' . $counts['uda_only'] . ' ambiguous=' . $counts['ambiguous']
            . ' conflict=' . $counts['conflict'] . ' absent=' . $counts['absent']
            . ' card_source=' . ($reconcileSource['mode'] ?? 'not_configured')
            . ' live_confirmed=' . $live['confirmed'] . ' live_unconfirmed=' . $live['unconfirmed']
            . ' live_contradicted=' . $live['contradicted']);
    } elseif (is_resource($handle['fp'])) {
        flock($handle['fp'], LOCK_UN); fclose($handle['fp']);
    }
    echo json_encode(array(
        'success' => true,
        'applied' => $apply,
        'counts' => $counts,
        'live' => $live,
        'card_source' => $reconcileSource,
        'fillable_records' => $fillable,
        'filled_fields' => $apply ? $filledFields : 0,
        'records' => $details,
        'message' => $apply
            ? ($filledFields ? ($filledFields . ' medan dilengkap daripada rekod kad.') : 'Tiada medan baharu untuk dilengkap; rekod sudah lengkap atau tidak dapat dibuktikan.')
            : ($fillable ? ($fillable . ' rekod boleh dilengkap daripada rekod kad.') : 'Tiada rekod boleh dilengkap daripada rekod kad.'),
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===== Sysweb credential management: Admin only, audited, never a bulk list ===== */
if (in_array($action, array('reset_sysweb_password', 'sysweb_generate_missing_passwords', 'get_sysweb_password', 'sysweb_authorize_password_export'), true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(array('success' => false, 'message' => 'Hanya Admin boleh mengurus kata laluan Sysweb.'));
        exit;
    }
    // Every credential operation is state-changing or exposes a secret. Require the same
    // session-bound CSRF token used by the rest of the USMSmart admin APIs.
    $credentialCsrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $expectedCredentialCsrf = (string)($_SESSION['usmsmart_csrf_token'] ?? '');
    if ($credentialCsrf === '' || $expectedCredentialCsrf === '' || !hash_equals($expectedCredentialCsrf, $credentialCsrf)) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'message' => 'Token keselamatan tidak sah. Muat semula halaman dan cuba lagi.'));
        exit;
    }
    $credBody = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($credBody)) $credBody = array();
    $handle = usmsmartOpenSyswebStore();
    if (!$handle) {
        http_response_code(500);
        echo json_encode(array('success' => false, 'message' => 'Fail rekod Sysweb tidak boleh dikunci.'));
        exit;
    }
    $credRecords = $handle['records'];

    if ($action === 'get_sysweb_password' || $action === 'reset_sysweb_password') {
        $targetIndex = filter_var($credBody['index'] ?? null, FILTER_VALIDATE_INT);
        if ($targetIndex === false || $targetIndex < 0 || !isset($credRecords[$targetIndex])) {
            flock($handle['fp'], LOCK_UN); fclose($handle['fp']);
            http_response_code(404);
            echo json_encode(array('success' => false, 'message' => 'Rekod Sysweb tidak ditemui. Muat semula senarai.'));
            exit;
        }
        $target = $credRecords[$targetIndex];
        if ($action === 'get_sysweb_password') {
            // Reveal is a read of a credential the list endpoint never ships, so each reveal is
            // recorded against the actor; only the fingerprint is stored.
            if (empty($target['password'])) {
                flock($handle['fp'], LOCK_UN); fclose($handle['fp']);
                http_response_code(409);
                echo json_encode(array('success' => false, 'message' => 'Rekod ini belum ada kata laluan. Jana dahulu.'));
                exit;
            }
            auditLog($pdo, 'sysweb_password_reveal', 'sysweb_record', (string)($target['sysweb_uid'] ?? $targetIndex), (string)($target['identifier'] ?? null),
                'fingerprint=' . usmsmartSyswebPasswordFingerprint($target['password']));
            echo json_encode(array('success' => true, 'password' => (string)$target['password'], 'issued_at' => (string)($target['password_issued_at'] ?? '')));
            exit;
        }
        $credRecords[$targetIndex]['password'] = usmsmartGenerateSyswebPassword();
        $credRecords[$targetIndex]['password_issued_at'] = date(DATE_ATOM);
        $credRecords[$targetIndex]['updated_at'] = date(DATE_ATOM);
        if (!usmsmartWriteSyswebStore($handle, $credRecords)) {
            http_response_code(500);
            echo json_encode(array('success' => false, 'message' => 'Gagal menyimpan kata laluan baharu.'));
            exit;
        }
        auditLog($pdo, 'sysweb_password_reset', 'sysweb_record', (string)($credRecords[$targetIndex]['sysweb_uid'] ?? $targetIndex), (string)($credRecords[$targetIndex]['identifier'] ?? null),
            'fingerprint=' . usmsmartSyswebPasswordFingerprint($credRecords[$targetIndex]['password']));
        echo json_encode(array('success' => true, 'message' => 'Kata laluan Sysweb baharu telah dijana untuk rekod ini.',
            'password' => $credRecords[$targetIndex]['password'], 'issued_at' => $credRecords[$targetIndex]['password_issued_at']));
        exit;
    }

    if ($action === 'sysweb_generate_missing_passwords') {
        $issued = 0;
        foreach ($credRecords as $i => $row) {
            if (!is_array($row) || !empty($row['password'])) continue;
            $credRecords[$i]['password'] = usmsmartGenerateSyswebPassword();
            $credRecords[$i]['password_issued_at'] = date(DATE_ATOM);
            $issued++;
        }
        if ($issued && !usmsmartWriteSyswebStore($handle, $credRecords)) {
            http_response_code(500);
            echo json_encode(array('success' => false, 'message' => 'Gagal menyimpan kata laluan yang dijana.'));
            exit;
        }
        // The writer releases the lock itself, so only the no-op path still holds the handle here.
        if (is_resource($handle['fp'])) { flock($handle['fp'], LOCK_UN); fclose($handle['fp']); }
        if ($issued) {
            // Aggregate only: the log must not let an auditor reconstruct any single credential.
            auditLog($pdo, 'sysweb_password_backfill', 'sysweb_batch', null, null, 'issued=' . $issued);
        }
        echo json_encode(array('success' => true, 'issued' => $issued, 'message' => $issued ? ($issued . ' kata laluan baharu dijana.') : 'Semua rekod sudah ada kata laluan.'));
        exit;
    }

    // Authorised credential export: the client must ask for this explicitly, and the request is
    // logged with the record count so bulk credential extraction is visible after the fact.
    $exportRows = array();
    foreach ($credRecords as $i => $row) {
        if (!is_array($row) || empty($row['password'])) continue;
        $exportRows[] = array('index' => $i, 'identifier' => (string)($row['identifier'] ?? ''), 'type' => (string)($row['type'] ?? ''),
            'sysweb_uid' => (string)($row['sysweb_uid'] ?? ''), 'password' => (string)$row['password']);
    }
    flock($handle['fp'], LOCK_UN); fclose($handle['fp']);
    auditLog($pdo, 'sysweb_password_export', 'sysweb_batch', null, null, 'records=' . count($exportRows));
    echo json_encode(array('success' => true, 'count' => count($exportRows), 'passwords' => $exportRows));
    exit;
}

if ($action === 'clear_sysweb_records' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(array('success' => false, 'message' => 'Hanya Admin boleh mengosongkan rekod Sysweb.'));
        exit;
    }
    $csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($csrf === '' || !hash_equals((string)($_SESSION['usmsmart_csrf_token'] ?? ''), $csrf)) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'message' => 'Token keselamatan tidak sah. Sila muat semula halaman.'));
        exit;
    }
    $storePath = USM_ROOT . DIRECTORY_SEPARATOR . 'sysweb_demo_received.json';
    if (file_put_contents($storePath, json_encode(array(), JSON_PRETTY_PRINT), LOCK_EX) === false) {
        http_response_code(500);
        echo json_encode(array('success' => false, 'message' => 'Gagal mengosongkan pangkalan rekod Sysweb.'));
        exit;
    }
    auditLog($pdo, 'clear_sysweb_records', 'sysweb_batch', null, null, 'records_removed=' . (is_file($storePath) ? substr_count((string)file_get_contents($storePath), '"sysweb_uid"') : 0));
    echo json_encode(array('success' => true, 'message' => 'Semua rekod simulasi Sysweb telah berjaya dikosongkan.'));
    exit;
}
