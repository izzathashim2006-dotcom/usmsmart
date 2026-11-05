<?php
// UDA session dump, rename, list and archive routes.
// Included by index.php in the same scope, so it reads $action, $pdo,
// $authReady and $_SESSION directly. USM_ROOT always points at the app root.
if (!defined('USM_ROOT')) {
    // Reached directly instead of through index.php: these are includes, not endpoints.
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

    if ($action === 'dump_uda' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        if (($_SESSION['role'] ?? '') === 'viewer') {
            $raw = file_get_contents('php://input'); $payload = json_decode($raw, true);
            if (!is_array($payload)) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Data permintaan tidak sah.')); exit; }
            $csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
            if ($csrf === '' || !hash_equals((string)$_SESSION['usmsmart_csrf_token'], $csrf)) { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Token keselamatan tidak sah.')); exit; }
            $summary = 'Permintaan perubahan';
            if ($action === 'save_student') { $payload['_summary'] = ((int)($payload['id']??0)>0?'Kemaskini':'Tambah').' pelajar: '.substr(trim((string)($payload['name']??'')),0,100).' ('.substr(trim((string)($payload['matric']??'')),0,60).')'; }
            elseif ($action === 'delete_student') { $payload['_summary']='Padam pelajar ID '.(int)($payload['id']??0); }
            elseif ($action === 'dump_uda') { $payload['_summary']='Hantar '.count((array)($payload['records']??array())).' rekod ke UDA'; }
            elseif ($action === 'soft_delete_session') { $payload['_summary']='Padam sesi pengangkutan ID '.(int)($payload['id']??0); }
            $stmt=$pdo->prepare("INSERT INTO usmsmart_change_requests (request_type,payload,requested_by,requested_by_username) VALUES (?,?,?,?)");
            $stmt->execute(array($action,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$_SESSION['user_id'],(string)$_SESSION['username']));
            echo json_encode(array('success'=>true,'queued'=>true,'message'=>'Permintaan dihantar kepada Admin untuk kelulusan. Rekod asal belum diubah.')); exit;
        }
        $inputData = json_decode(file_get_contents('php://input'), true);
        $records = isset($inputData['records']) ? $inputData['records'] : array();

        if (empty($records)) {
            echo json_encode(array('success' => false, 'message' => 'Tiada rekod diterima.'));
            exit;
        }

        try {
            usmsmartEnsureUdaCardholderTypeColumn($pdo);

            // Fail before writing if either table cannot participate in a MySQL transaction.
            // MyISAM and other non-transactional engines can cause commit() to report
            // "There is no active transaction" after rows have already been written.
            $engineStmt = $pdo->prepare("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?)");
            $engineStmt->execute(array('uda_system_records', 'uda_transport_sessions'));
            $tableEngines = array();
            foreach ($engineStmt->fetchAll(PDO::FETCH_ASSOC) as $engineRow) {
                $tableEngines[strtolower((string)$engineRow['TABLE_NAME'])] = strtoupper((string)$engineRow['ENGINE']);
            }
            foreach (array('uda_system_records', 'uda_transport_sessions') as $requiredTable) {
                if (!isset($tableEngines[$requiredTable])) {
                    throw new RuntimeException('Jadual MySQL ' . $requiredTable . ' tidak ditemui dalam pangkalan data semasa. Tiada rekod dihantar.');
                }
                if ($tableEngines[$requiredTable] !== 'INNODB') {
                    throw new RuntimeException('Jadual ' . $requiredTable . ' menggunakan enjin ' . $tableEngines[$requiredTable] . ', bukan InnoDB. Eksport dihentikan sebelum perubahan dibuat. Tukar jadual kepada InnoDB selepas membuat sandaran pangkalan data.');
                }
            }

            // Prepare policy lookup schema before beginning the write transaction.
            // MySQL DDL can implicitly commit an active transaction.
            usmsmartEnsurePolicyAssignmentTable($pdo);
            $pdo->beginTransaction();
            if (!$pdo->inTransaction()) {
                throw new RuntimeException('MySQL tidak memulakan transaksi. Eksport dihentikan sebelum rekod diproses.');
            }
            $stmt = $pdo->prepare("INSERT INTO uda_system_records (matric, cardholder_type, converted_uid, serial_no, policy, status) VALUES (?, ?, ?, ?, ?, 'READY_TO_ISSUE') ON DUPLICATE KEY UPDATE cardholder_type = VALUES(cardholder_type), converted_uid = VALUES(converted_uid), serial_no = VALUES(serial_no), policy = VALUES(policy)");
            $namesList = array();
            foreach ($records as $r) {
                $cardError = validateCardFields(isset($r['usmUid']) ? $r['usmUid'] : '', isset($r['serialNo']) ? $r['serialNo'] : '');
                if ($cardError !== '') throw new Exception('Rekod ' . (isset($r['matric']) ? $r['matric'] : '') . ': ' . $cardError);
                $identifier = trim((string)($r['matric'] ?? ''));
                $claimedPolicy = trim((string)($r['policy'] ?? ''));
                if ($identifier === '' || $claimedPolicy === '') throw new Exception('Pengenal dan polisi diperlukan untuk pemindahan UDA.');
                $policyMatch = usmsmartFindExistingCardPolicy($pdo, $identifier, (string)($r['usmUid'] ?? ''), (string)($r['serialNo'] ?? ''), strtolower((string)($r['cardholderType'] ?? 'student')) === 'staff' ? 'staff' : 'student');
                $existingPolicy = $policyMatch ? trim((string)($policyMatch['policy'] ?? '')) : '';
                if ($existingPolicy === '') throw new Exception('Rekod ' . $identifier . ' disekat: tiada polisi palang yang ditetapkan.');
                if (!hash_equals($existingPolicy, $claimedPolicy)) throw new Exception('Rekod ' . $identifier . ' disekat: polisi palang tidak sepadan dengan tugasan pemegang kad.');
                $clean = strtoupper(preg_replace('/\s+/', '', (string)$r['usmUid']));
                if (!preg_match('/^(?:[0-9A-F]{2})+$/', $clean)) throw new Exception('Rekod ' . $identifier . ' disekat: UID hexadecimal tidak sah.');
                $pairs = str_split($clean, 2);
                $convertedUid = implode('', array_reverse($pairs));
                $cardholderType = strtolower((string)($r['cardholderType'] ?? 'student')) === 'staff' ? 'staff' : 'student';
                $stmt->execute(array($identifier, $cardholderType, $convertedUid, $r['serialNo'], $claimedPolicy));
                $namesList[] = array('name' => $r['name'], 'matric' => $r['matric'], 'cardholder_type' => (($r['cardholderType'] ?? 'student') === 'staff' ? 'staff' : 'student'));
            }
            $sessionStmt = $pdo->prepare("INSERT INTO uda_transport_sessions (total_records, details) VALUES (?, ?)");
            $sessionStmt->execute(array(count($records), json_encode($namesList)));
            $sessionId = $pdo->lastInsertId();
            if (!$pdo->inTransaction()) {
                throw new RuntimeException('Transaksi MySQL telah tamat sebelum commit. Sila semak enjin jadual dan trigger MySQL; status eksport tidak dapat disahkan.');
            }
            $pdo->commit();

            // Verify the committed records by reading them back from MySQL.
            // This report confirms the configured MySQL destination only; it does not
            // claim that a separate physical UDA controller/device has received them.
            $verificationItems = array();
            $verifiedCount = 0;
            $uidChecksPassed = 0;
            $policyChecksPassed = 0;
            $typeChecksPassed = 0;
            $readBackStmt = $pdo->prepare('SELECT matric, cardholder_type, converted_uid, serial_no, policy, status FROM uda_system_records WHERE UPPER(TRIM(matric)) = UPPER(TRIM(?)) LIMIT 1');
            foreach ($records as $r) {
                $identifier = trim((string)($r['matric'] ?? ''));
                $sourceUid = strtoupper(preg_replace('/\s+/', '', (string)($r['usmUid'] ?? '')));
                $bytePairs = str_split($sourceUid, 2);
                $expectedUid = implode('', array_reverse($bytePairs));
                $expectedPolicy = trim((string)($r['policy'] ?? ''));
                $expectedType = strtolower((string)($r['cardholderType'] ?? 'student')) === 'staff' ? 'staff' : 'student';
                $readBackStmt->execute(array($identifier));
                $saved = $readBackStmt->fetch(PDO::FETCH_ASSOC);
                $uidOk = is_array($saved) && strtoupper(trim((string)($saved['converted_uid'] ?? ''))) === $expectedUid;
                $policyOk = is_array($saved) && hash_equals($expectedPolicy, trim((string)($saved['policy'] ?? '')));
                $typeOk = is_array($saved) && strtolower(trim((string)($saved['cardholder_type'] ?? ''))) === $expectedType;
                if ($uidOk) $uidChecksPassed++;
                if ($policyOk) $policyChecksPassed++;
                if ($typeOk) $typeChecksPassed++;
                $itemPassed = is_array($saved) && $uidOk && $policyOk && $typeOk;
                if ($itemPassed) $verifiedCount++;
                $verificationItems[] = array(
                    'matric' => $identifier,
                    'saved' => is_array($saved),
                    'uid' => $uidOk ? 'PASS' : 'FAIL',
                    'policy' => $policyOk ? 'PASS' : 'FAIL',
                    'cardholder_type' => $typeOk ? 'PASS' : 'FAIL',
                    'status' => is_array($saved) ? (string)($saved['status'] ?? '') : '',
                    'result' => $itemPassed ? 'PASS' : 'FAIL'
                );
            }
            $sessionVerify = $pdo->prepare('SELECT total_records, details FROM uda_transport_sessions WHERE id = ? LIMIT 1');
            $sessionVerify->execute(array($sessionId));
            $savedSession = $sessionVerify->fetch(PDO::FETCH_ASSOC);
            $sessionDetails = is_array($savedSession) ? json_decode((string)($savedSession['details'] ?? ''), true) : null;
            $sessionCount = is_array($savedSession) ? (int)($savedSession['total_records'] ?? -1) : -1;
            $sessionDetailsCount = is_array($sessionDetails) ? count($sessionDetails) : -1;
            $sessionOk = is_array($savedSession) && $sessionCount === count($records) && $sessionDetailsCount === count($records);
            $overallPass = $verifiedCount === count($records) && $sessionOk;
            auditLog($pdo, 'dump_uda', 'uda_session', $sessionId, null, 'Transferred ' . count($records) . ' records to UDA; verification=' . ($overallPass ? 'PASS' : 'WARNING') . '; verified=' . $verifiedCount);
            $report = array(
                'result' => $overallPass ? 'PASS' : 'WARNING',
                'requested_count' => count($records),
                'saved_and_verified_count' => $verifiedCount,
                'failed_count' => count($records) - $verifiedCount,
                'uid_checks_passed' => $uidChecksPassed,
                'policy_checks_passed' => $policyChecksPassed,
                'cardholder_type_checks_passed' => $typeChecksPassed,
                'session' => array('id' => (int)$sessionId, 'exists' => is_array($savedSession), 'expected_count' => count($records), 'saved_count' => $sessionCount, 'details_count' => $sessionDetailsCount, 'result' => $sessionOk ? 'PASS' : 'FAIL'),
                'items' => $verificationItems,
                'scope_note' => 'Read-back verification is against the configured MySQL database. It does not confirm delivery to a separate UDA device/controller.'
            );
            echo json_encode(array('success' => true, 'message' => $overallPass ? 'Eksport disimpan dan pengesahan baca-semula lulus.' : 'Eksport disimpan tetapi pengesahan menemui ketidakpadanan. Semak laporan.', 'verification_report' => $report), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                try { $pdo->rollBack(); } catch (Throwable $rollbackError) { error_log('USMSmart UDA export rollback failed: ' . $rollbackError->getMessage()); }
            }
            error_log('USMSmart UDA export transaction failed: ' . $e->getMessage());
            echo json_encode(array('success' => false, 'message' => $e->getMessage()));
        }
        exit;
    }

    if ($action === 'rename_session' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Hanya Admin boleh menamakan semula sesi.')); exit; }
        $csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($csrf === '' || !hash_equals((string)($_SESSION['usmsmart_csrf_token'] ?? ''), $csrf)) { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Token keselamatan tidak sah. Muat semula halaman dan cuba lagi.')); exit; }
        $body = json_decode(file_get_contents('php://input'), true) ?: array();
        $sessionId = (int)($body['id'] ?? 0); $sessionName = trim((string)($body['session_name'] ?? ''));
        if ($sessionId < 1 || $sessionName === '' || mb_strlen($sessionName) > 120) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Nama sesi wajib diisi dan maksimum 120 aksara.')); exit; }
        try {
            $pdo->exec("ALTER TABLE uda_transport_sessions ADD COLUMN session_name VARCHAR(120) NULL");
        } catch (PDOException $e) { if (stripos($e->getMessage(), 'duplicate column') === false && stripos($e->getMessage(), 'duplicate') === false) { /* column may already exist; validate below */ } }
        try {
            $stmt = $pdo->prepare('UPDATE uda_transport_sessions SET session_name = ? WHERE id = ? AND (is_deleted = 0 OR is_deleted IS NULL)');
            $stmt->execute(array($sessionName, $sessionId));
            if ($stmt->rowCount() < 1) { $check=$pdo->prepare('SELECT id FROM uda_transport_sessions WHERE id=? AND (is_deleted=0 OR is_deleted IS NULL)'); $check->execute(array($sessionId)); if (!$check->fetchColumn()) throw new RuntimeException('Sesi tidak ditemui atau telah dipadam.'); }
            if (function_exists('auditLog')) auditLog($pdo, 'rename_session', 'uda_transport_session', (string)$sessionId, null, 'Nama sesi dikemas kini: '.$sessionName);
            echo json_encode(array('success'=>true,'message'=>'Nama sesi berjaya dikemas kini.'));
        } catch (Throwable $e) { error_log('USMSmart rename session failed: '.$e->getMessage()); http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Nama sesi tidak dapat disimpan. Pastikan lajur session_name tersedia.')); }
        exit;
    }

    if ($action === 'get_sessions') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            try { $pdo->exec("ALTER TABLE uda_transport_sessions ADD COLUMN session_name VARCHAR(120) NULL"); } catch (PDOException $ignored) {}
            $stmt = $pdo->query("SELECT * FROM uda_transport_sessions WHERE (is_deleted = 0 OR is_deleted IS NULL) ORDER BY session_date DESC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode(array());
        }
        exit;
    }

    if ($action === 'soft_delete_session' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        if (($_SESSION['role'] ?? '') === 'viewer') {
            $raw = file_get_contents('php://input'); $payload = json_decode($raw, true);
            if (!is_array($payload)) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Data permintaan tidak sah.')); exit; }
            $csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
            if ($csrf === '' || !hash_equals((string)$_SESSION['usmsmart_csrf_token'], $csrf)) { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Token keselamatan tidak sah.')); exit; }
            $summary = 'Permintaan perubahan';
            if ($action === 'save_student') { $payload['_summary'] = ((int)($payload['id']??0)>0?'Kemaskini':'Tambah').' pelajar: '.substr(trim((string)($payload['name']??'')),0,100).' ('.substr(trim((string)($payload['matric']??'')),0,60).')'; }
            elseif ($action === 'delete_student') { $payload['_summary']='Padam pelajar ID '.(int)($payload['id']??0); }
            elseif ($action === 'dump_uda') { $payload['_summary']='Hantar '.count((array)($payload['records']??array())).' rekod ke UDA'; }
            elseif ($action === 'soft_delete_session') { $payload['_summary']='Padam sesi pengangkutan ID '.(int)($payload['id']??0); }
            $stmt=$pdo->prepare("INSERT INTO usmsmart_change_requests (request_type,payload,requested_by,requested_by_username) VALUES (?,?,?,?)");
            $stmt->execute(array($action,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$_SESSION['user_id'],(string)$_SESSION['username']));
            echo json_encode(array('success'=>true,'queued'=>true,'message'=>'Permintaan dihantar kepada Admin untuk kelulusan. Rekod asal belum diubah.')); exit;
        }
        $inputData = json_decode(file_get_contents('php://input'), true);
        $sessionId = isset($inputData['id']) ? intval($inputData['id']) : 0;
        
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT details FROM uda_transport_sessions WHERE id = ?");
            $stmt->execute(array($sessionId));
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session && !empty($session['details'])) {
                $studentsList = json_decode($session['details'], true);
                if (is_array($studentsList)) {
                    $delRecordStmt = $pdo->prepare("DELETE FROM uda_system_records WHERE matric = ?");
                    foreach ($studentsList as $st) {
                        if (isset($st['matric'])) {
                            $delRecordStmt->execute(array($st['matric']));
                        }
                    }
                }
            }

            $updateStmt = $pdo->prepare("UPDATE uda_transport_sessions SET is_deleted = 1 WHERE id = ?");
            $updateStmt->execute(array($sessionId));

            $pdo->commit();
            auditLog($pdo, 'soft_delete_session', 'uda_session', $sessionId, null, 'Soft-deleted UDA transport session and released associated records');
            echo json_encode(array('success' => true, 'message' => 'Sesi dipadam dan status pelajar berjaya dilepaskan semula.'));
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(array('success' => false, 'message' => $e->getMessage()));
        }
        exit;
    }

