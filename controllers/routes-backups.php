<?php
// Server backup listing, verification, download and restore routes.
// Included by index.php in the same scope, so it reads $action, $pdo,
// $authReady and $_SESSION directly. USM_ROOT always points at the app root.
if (!defined('USM_ROOT')) {
    // Reached directly instead of through index.php: these are includes, not endpoints.
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

    if ($action === 'backup_status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        try { echo json_encode(array('success'=>true,'backups'=>usmsmartListServerBackups(),'retention_days'=>14,'automation'=>'daily-on-request')); }
        catch (Exception $e) { http_response_code(500); echo json_encode(array('success'=>false,'message'=>$e->getMessage())); }
        exit;
    }
    if ($action === 'verify_server_backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $name = (string)($_POST['file'] ?? '');
            if (!preg_match('/^usmsmart_(?:auto|manual)_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_[a-f0-9]{6}\.json$/', $name)) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Nama fail sandaran tidak sah.')); exit; }
            $dir = usmsmartBackupDirectory(); $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (!is_file($path) || realpath(dirname($path)) !== realpath($dir)) { http_response_code(404); echo json_encode(array('success'=>false,'message'=>'Fail sandaran tidak ditemui.')); exit; }
            $check = usmsmartVerifyBackupFile($path);
            if (empty($check['valid'])) { http_response_code(422); echo json_encode(array('success'=>false,'verification'=>$check,'message'=>$check['message'])); exit; }
            echo json_encode(array('success'=>true,'verification'=>$check,'message'=>$check['message']));
        } catch (Exception $e) { usmsmartBackupLog('backup_verification_failed', $e->getMessage(), $name ?? null); http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Pengesahan gagal: ' . $e->getMessage())); }
        exit;
    }
    if ($action === 'create_server_backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $info = usmsmartWriteServerBackup($pdo, false);
            auditLog($pdo, 'create_server_backup', 'database', null, null, 'Created server-side backup ' . $info['file']);
            usmsmartBackupLog('backup_created', 'Backup created and verified; SHA-256 ' . $info['sha256'], $info['file']);
            echo json_encode(array('success'=>true,'message'=>'Sandaran pelayan berjaya disimpan.','backup'=>$info));
        } catch (Exception $e) { usmsmartBackupLog('backup_creation_failed', $e->getMessage()); http_response_code(500); echo json_encode(array('success'=>false,'message'=>$e->getMessage())); }
        exit;
    }
    if ($action === 'download_server_backup' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $name = (string)($_GET['file'] ?? '');
        if (!preg_match('/^usmsmart_(?:auto|manual)_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_[a-f0-9]{6}\.json$/', $name)) { http_response_code(400); echo 'Nama fail sandaran tidak sah.'; exit; }
        $path = usmsmartBackupDirectory() . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path) || realpath(dirname($path)) !== realpath(usmsmartBackupDirectory())) { http_response_code(404); echo 'Fail sandaran tidak ditemui.'; exit; }
        header('Content-Type: application/json; charset=utf-8'); header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $name . '"'); header('Content-Length: ' . filesize($path));
        readfile($path); exit;
    }
    if ($action === 'delete_server_backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        $name = (string)($_POST['file'] ?? '');
        if (!preg_match('/^usmsmart_(?:auto|manual)_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_[a-f0-9]{6}\.json$/', $name)) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Nama fail tidak sah.')); exit; }
        $path = usmsmartBackupDirectory() . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) { http_response_code(404); echo json_encode(array('success'=>false,'message'=>'Fail sandaran tidak ditemui.')); exit; }
        if (!@unlink($path)) { http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Fail sandaran tidak dapat dipadam.')); exit; }
        auditLog($pdo, 'delete_server_backup', 'database', null, null, 'Deleted server-side backup ' . $name);
        echo json_encode(array('success'=>true,'message'=>'Fail sandaran dipadam.')); exit;
    }

    if ($action === 'backup_database') {
        try {
            $students = $pdo->query("SELECT * FROM students_view")->fetchAll(PDO::FETCH_ASSOC);
            $udaRecords = $pdo->query("SELECT * FROM uda_system_records")->fetchAll(PDO::FETCH_ASSOC);
            $sessions = $pdo->query("SELECT * FROM uda_transport_sessions")->fetchAll(PDO::FETCH_ASSOC);
            try { ensureAuditTable($pdo); $audit = $pdo->query("SELECT * FROM usmsmart_audit_log")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $ignored) { $audit = array(); }

            $backupData = array(
                'backup_format' => 'USMSmart JSON backup',
                'schema_version' => 2,
                'export_date' => date('Y-m-d H:i:s'),
                'counts' => array('students' => count($students), 'uda_records' => count($udaRecords), 'sessions' => count($sessions), 'audit_events' => count($audit)),
                'database' => 'usmsmart_db',
                'students_view' => $students,
                'uda_system_records' => $udaRecords,
                'uda_transport_sessions' => $sessions,
                'usmsmart_audit_log' => $audit
            );

            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="usmsmart_full_backup_' . date('Y-m-d_H-i-s') . '.json"');
            echo json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('success' => false, 'message' => $e->getMessage()));
        }
        exit;
    }

    if ($action === 'validate_restore_backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            usmsmartBackupLog('restore_preflight_failed', 'No valid uploaded backup file.');
            http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Sila pilih fail sandaran JSON yang sah.')); exit;
        }
        if ((int)($_FILES['backup_file']['size'] ?? 0) > 15 * 1024 * 1024) {
            usmsmartBackupLog('restore_preflight_failed', 'Uploaded backup exceeds 15 MB.', $_FILES['backup_file']['name'] ?? null);
            http_response_code(413); echo json_encode(array('success'=>false,'message'=>'Fail sandaran melebihi had 15 MB.')); exit;
        }
        $raw = @file_get_contents($_FILES['backup_file']['tmp_name']);
        $data = $raw === false ? null : json_decode($raw, true);
        $errors = array();
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) $errors[] = 'Fail bukan JSON yang sah atau rosak.';
        else {
            if (($data['backup_format'] ?? '') !== 'USMSmart JSON backup') $errors[] = 'Format sandaran tidak dikenali.';
            if (isset($data['database']) && $data['database'] !== 'usmsmart_db') $errors[] = 'Sandaran ini ditandakan untuk pangkalan data yang berbeza.';
            if (!isset($data['students_view']) || !is_array($data['students_view'])) $errors[] = 'Bahagian rekod pelajar tiada atau tidak sah.';
            foreach (array('uda_system_records','uda_transport_sessions','usmsmart_audit_log') as $key) if (isset($data[$key]) && !is_array($data[$key])) $errors[] = 'Struktur tidak sah: ' . $key;
            if (isset($data['students_view']) && is_array($data['students_view'])) {
                foreach ($data['students_view'] as $i => $record) {
                    if (!is_array($record) || empty($record['matric']) || empty($record['name'])) { $errors[] = 'Rekod pelajar #' . ($i + 1) . ' tidak lengkap.'; break; }
                }
            }
            if (isset($data['counts']) && is_array($data['counts']) && isset($data['students_view']) && is_array($data['students_view'])) {
                $actualCounts = array('students'=>count($data['students_view']), 'uda_records'=>count($data['uda_system_records'] ?? array()), 'sessions'=>count($data['uda_transport_sessions'] ?? array()), 'audit_events'=>count($data['usmsmart_audit_log'] ?? array()));
                foreach ($actualCounts as $key=>$count) if (isset($data['counts'][$key]) && (int)$data['counts'][$key] !== $count) { $errors[] = 'Jumlah rekod tidak sepadan untuk ' . $key . '.'; break; }
            }
        }
        if ($errors) {
            $msg = implode(' ', $errors); usmsmartBackupLog('restore_preflight_failed', $msg, $_FILES['backup_file']['name'] ?? null);
            http_response_code(422); echo json_encode(array('success'=>false,'message'=>$msg,'preflight'=>true)); exit;
        }
        $summary = array('students'=>count($data['students_view']), 'uda_records'=>count($data['uda_system_records'] ?? array()), 'sessions'=>count($data['uda_transport_sessions'] ?? array()), 'audit_events'=>count($data['usmsmart_audit_log'] ?? array()));
        usmsmartBackupLog('restore_preflight_passed', 'Structure and record counts passed; no database changes made.', $_FILES['backup_file']['name'] ?? null);
        echo json_encode(array('success'=>true,'message'=>'Pemeriksaan awal lulus. Pangkalan data belum diubah.','counts'=>$summary,'preflight'=>true)); exit;
    }

    if ($action === 'restore_database' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(array('success' => false, 'message' => 'Sila pilih fail sandaran JSON yang sah.'));
            exit;
        }

        if (!empty($_FILES['backup_file']['size']) && $_FILES['backup_file']['size'] > 15 * 1024 * 1024) { echo json_encode(array('success'=>false,'message'=>'Fail sandaran melebihi had 15 MB.')); exit; }
        $jsonContent = file_get_contents($_FILES['backup_file']['tmp_name']);
        $backupData = json_decode($jsonContent, true);

        if (!$backupData || !is_array($backupData) || !isset($backupData['students_view']) || !is_array($backupData['students_view']) || (isset($backupData['database']) && $backupData['database'] !== 'usmsmart_db') || (isset($backupData['backup_format']) && $backupData['backup_format'] !== 'USMSmart JSON backup')) {
            echo json_encode(array('success' => false, 'message' => 'Format fail sandaran tidak sah, rosak atau daripada pangkalan data yang berbeza.'));
            exit;
        }
        foreach ($backupData['students_view'] as $record) {
            if (!is_array($record) || empty($record['matric']) || empty($record['name'])) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Sandaran mengandungi rekod pelajar tidak lengkap; restore dibatalkan.')); exit; }
        }
        /* One repeated number in the file would make the unique index reject the whole restore at
           the database, so the file is read here first and the operator is told what to fix. */
        $restoreSerials = array();
        foreach ($backupData['students_view'] as $record) {
            $restoreSerial = strtoupper(trim((string)(isset($record['serial_no']) ? $record['serial_no'] : (isset($record['serialNo']) ? $record['serialNo'] : ''))));
            if ($restoreSerial === '') continue;
            if (isset($restoreSerials[$restoreSerial])) {
                http_response_code(400);
                echo json_encode(array('success'=>false,'message'=>'Sandaran mengandungi nombor siri kad berkembar (' . $restoreSerial . '); restore dibatalkan.'));
                exit;
            }
            $restoreSerials[$restoreSerial] = true;
        }
        foreach (array('uda_system_records','uda_transport_sessions') as $optionalKey) {
            if (isset($backupData[$optionalKey]) && !is_array($backupData[$optionalKey])) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Struktur fail sandaran tidak sah: ' . $optionalKey)); exit; }
        }

        try {
            $pdo->beginTransaction();

            // DELETE is transactional on InnoDB; unlike TRUNCATE it can be rolled back.
            $pdo->exec("DELETE FROM uda_system_records");
            $pdo->exec("DELETE FROM uda_transport_sessions");
            $pdo->exec("DELETE FROM students_view");

            if (!empty($backupData['students_view'])) {
                $stmt = $pdo->prepare("INSERT INTO students_view (id, name, matric, school, usm_uid, serial_no, policy, date_joined, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($backupData['students_view'] as $s) {
                    $stmt->execute(array(
                        $s['id'], $s['name'], $s['matric'], $s['school'],
                        isset($s['usm_uid']) ? $s['usm_uid'] : (isset($s['usmUid']) ? $s['usmUid'] : ''),
                        usmsmartSerialOrNull(isset($s['serial_no']) ? $s['serial_no'] : (isset($s['serialNo']) ? $s['serialNo'] : '')),
                        $s['policy'],
                        isset($s['date_joined']) ? $s['date_joined'] : (isset($s['dateJoined']) ? $s['dateJoined'] : date('Y-m-d')),
                        isset($s['expiry_date']) ? $s['expiry_date'] : (isset($s['expiryDate']) ? $s['expiryDate'] : date('Y-m-d'))
                    ));
                }
            }

            if (!empty($backupData['uda_system_records'])) {
                $stmt = $pdo->prepare("INSERT INTO uda_system_records (id, matric, converted_uid, serial_no, policy, status) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($backupData['uda_system_records'] as $u) {
                    $stmt->execute(array(
                        $u['id'], $u['matric'], $u['converted_uid'], $u['serial_no'], $u['policy'], $u['status']
                    ));
                }
            }

            if (!empty($backupData['uda_transport_sessions'])) {
                $stmt = $pdo->prepare("INSERT INTO uda_transport_sessions (id, session_date, total_records, details, is_deleted) VALUES (?, ?, ?, ?, ?)");
                foreach ($backupData['uda_transport_sessions'] as $ts) {
                    $stmt->execute(array(
                        $ts['id'], $ts['session_date'], $ts['total_records'], $ts['details'], isset($ts['is_deleted']) ? $ts['is_deleted'] : 0
                    ));
                }
            }

            $pdo->commit();
            // Keep existing audit history intact and record the restore as a new event.
            auditLog($pdo, 'restore_database', 'database', null, null, 'Database restored from uploaded backup; audit history preserved');
            usmsmartBackupLog('restore_succeeded', 'Restore transaction committed; audit history preserved.', $_FILES['backup_file']['name'] ?? null);
            echo json_encode(array('success' => true, 'message' => 'Pangkalan data berjaya dipulihkan dari fail sandaran!'));
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            usmsmartBackupLog('restore_failed', $e->getMessage(), $_FILES['backup_file']['name'] ?? null);
            echo json_encode(array('success' => false, 'message' => 'Ralat Pemulihan DB: ' . $e->getMessage()));
        }
        exit;
    }
