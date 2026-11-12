<?php
// Record retention and archive routes.
// Graduated students and old transport sessions are copied to archive tables and then
// removed from the live tables, but only after a second Admin decision. Included by
// index.php in the same scope, so it reads $action, $pdo, $authReady and $_SESSION
// directly. USM_ROOT always points at the app root.
if (!defined('USM_ROOT')) {
    // Reached directly instead of through index.php: these are includes, not endpoints.
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

// The archive tables are created on first use. Every statement matches database/schema.sql
// so tools/schema-consistency.js can prove a fresh install declares the same columns.
function usmsmartArchiveEnsureTables($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_archive_runs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        policy_months SMALLINT UNSIGNED NOT NULL,
        include_sessions TINYINT(1) NOT NULL DEFAULT 1,
        cutoff_at DATETIME NOT NULL,
        student_count INT NOT NULL DEFAULT 0,
        session_count INT NOT NULL DEFAULT 0,
        student_ids LONGTEXT NOT NULL,
        session_ids LONGTEXT NOT NULL,
        manifest_hash CHAR(64) NOT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        requested_by BIGINT UNSIGNED NOT NULL,
        requested_by_username VARCHAR(80) NOT NULL,
        reviewed_by BIGINT UNSIGNED NULL,
        reviewed_by_username VARCHAR(80) NULL,
        review_note VARCHAR(500) NULL,
        self_approved TINYINT(1) NOT NULL DEFAULT 0,
        archived_students INT NOT NULL DEFAULT 0,
        archived_sessions INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at TIMESTAMP NULL DEFAULT NULL,
        completed_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_archive_status_created (status, created_at),
        INDEX idx_archive_requester (requested_by, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_archive_students (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        run_id BIGINT UNSIGNED NOT NULL,
        student_id INT NOT NULL,
        name VARCHAR(100) NULL,
        matric VARCHAR(50) NULL,
        school VARCHAR(150) NULL,
        date_joined DATE NULL,
        expiry_date DATE NULL,
        usm_uid VARCHAR(50) NULL,
        serial_no VARCHAR(50) NULL,
        policy VARCHAR(50) NULL,
        nationality VARCHAR(100) NULL,
        education_level VARCHAR(100) NULL,
        student_category VARCHAR(30) NULL,
        archived_by VARCHAR(80) NOT NULL,
        archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_archive_students_run (run_id),
        INDEX idx_archive_students_matric (matric)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_archive_sessions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        run_id BIGINT UNSIGNED NOT NULL,
        session_id INT NOT NULL,
        session_date DATETIME NULL,
        total_records INT NULL,
        details TEXT NULL,
        is_deleted TINYINT(1) NOT NULL DEFAULT 0,
        session_name VARCHAR(120) NULL,
        archived_by VARCHAR(80) NOT NULL,
        archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_archive_sessions_run (run_id),
        INDEX idx_archive_sessions_session (session_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function usmsmartArchiveMonths($value) {
    $months = (int)$value;
    if ($months < 12 || $months > 120) $months = 24;
    return $months;
}

function usmsmartArchivePolicyTableExists($pdo) {
    static $exists = null;
    if ($exists !== null) return $exists;
    try {
        $check = $pdo->prepare('SHOW TABLES LIKE ?');
        $check->execute(array('usmsmart_card_policy_assignments'));
        $exists = (bool)$check->fetchColumn();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

// A student is only eligible when the card has expired outside the policy window AND
// nothing still depends on the record: UDA staging rows and manual policy assignments
// would otherwise be orphaned by the purge.
function usmsmartArchiveStudentWhere($pdo, $months) {
    $where = "s.expiry_date IS NOT NULL AND s.expiry_date > '1000-01-01'"
        . " AND s.expiry_date < DATE_SUB(CURDATE(), INTERVAL " . (int)$months . " MONTH)"
        . " AND NOT EXISTS (SELECT 1 FROM uda_system_records u WHERE UPPER(TRIM(u.matric)) = UPPER(TRIM(s.matric)))";
    if (usmsmartArchivePolicyTableExists($pdo)) {
        $where .= " AND NOT EXISTS (SELECT 1 FROM usmsmart_card_policy_assignments a WHERE UPPER(TRIM(a.source_identifier)) = UPPER(TRIM(s.matric)))";
    }
    return $where;
}

function usmsmartArchiveSessionWhere($months) {
    return 'session_date IS NOT NULL AND session_date < DATE_SUB(NOW(), INTERVAL ' . (int)$months . ' MONTH)';
}

function usmsmartArchiveIds($rows) {
    $ids = array();
    foreach ($rows as $row) $ids[] = (int)$row['id'];
    sort($ids, SORT_NUMERIC);
    return $ids;
}

function usmsmartArchiveFail($code, $message) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode(array('success' => false, 'message' => $message));
    exit;
}

function usmsmartArchiveRequireAdmin() {
    if (($_SESSION['role'] ?? '') !== 'admin') usmsmartArchiveFail(403, 'Akses pentadbir diperlukan.');
}

function usmsmartArchiveDeleteIds($pdo, $table, $ids) {
    if (!$ids) return;
    foreach (array_chunk($ids, 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare('DELETE FROM ' . $table . ' WHERE id IN (' . $placeholders . ')');
        $stmt->execute(array_values($chunk));
    }
}

if ($action === 'archive_preview' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    usmsmartArchiveRequireAdmin();
    try {
        usmsmartArchiveEnsureTables($pdo);
        $months = usmsmartArchiveMonths($_GET['months'] ?? 24);
        $cutoffAt = (string)$pdo->query('SELECT DATE_SUB(NOW(), INTERVAL ' . (int)$months . ' MONTH)')->fetchColumn();
        $studentWhere = usmsmartArchiveStudentWhere($pdo, $months);

        $studentStmt = $pdo->query('SELECT s.id, s.name, s.matric, s.expiry_date FROM students_view s WHERE ' . $studentWhere . ' ORDER BY s.expiry_date ASC, s.id ASC');
        $studentRows = $studentStmt->fetchAll(PDO::FETCH_ASSOC);
        $expiredStmt = $pdo->query("SELECT COUNT(*) FROM students_view s WHERE s.expiry_date IS NOT NULL AND s.expiry_date > '1000-01-01' AND s.expiry_date < DATE_SUB(CURDATE(), INTERVAL " . (int)$months . " MONTH)");
        $expiredCount = (int)$expiredStmt->fetchColumn();

        $sessionWhere = usmsmartArchiveSessionWhere($months);
        $sessionStmt = $pdo->query('SELECT id, session_date, total_records, session_name, is_deleted FROM uda_transport_sessions WHERE ' . $sessionWhere . ' ORDER BY session_date ASC, id ASC');
        $sessionRows = $sessionStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(array(
            'success' => true,
            'months' => $months,
            'cutoff' => substr($cutoffAt, 0, 10),
            'students' => array(
                'count' => count($studentRows),
                'excluded' => max(0, $expiredCount - count($studentRows)),
                'samples' => array_slice($studentRows, 0, 10)
            ),
            'sessions' => array(
                'count' => count($sessionRows),
                'samples' => array_slice($sessionRows, 0, 10)
            )
        ));
    } catch (Throwable $e) {
        error_log('USMSmart archive preview failed: ' . $e->getMessage());
        usmsmartArchiveFail(500, 'Pratonton arkib tidak dapat dijana. Sila cuba lagi.');
    }
    exit;
}

if ($action === 'archive_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    usmsmartArchiveRequireAdmin();
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) usmsmartArchiveFail(400, 'Data permintaan tidak sah.');
    try {
        usmsmartArchiveEnsureTables($pdo);
        $months = usmsmartArchiveMonths($payload['months'] ?? 24);
        $includeSessions = !empty($payload['include_sessions']);

        $studentRows = $pdo->query('SELECT s.id FROM students_view s WHERE ' . usmsmartArchiveStudentWhere($pdo, $months) . ' ORDER BY s.id ASC')->fetchAll(PDO::FETCH_ASSOC);
        $studentIds = usmsmartArchiveIds($studentRows);
        $sessionIds = array();
        if ($includeSessions) {
            $sessionRows = $pdo->query('SELECT id FROM uda_transport_sessions WHERE ' . usmsmartArchiveSessionWhere($months) . ' ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
            $sessionIds = usmsmartArchiveIds($sessionRows);
        }
        if (!$studentIds && !$sessionIds) usmsmartArchiveFail(400, 'Tiada rekod yang layak diarkibkan untuk tempoh ini.');

        $cutoffAt = (string)$pdo->query('SELECT DATE_SUB(NOW(), INTERVAL ' . (int)$months . ' MONTH)')->fetchColumn();
        $hash = hash('sha256', json_encode(array('students' => $studentIds, 'sessions' => $sessionIds, 'cutoff' => substr($cutoffAt, 0, 10))));
        $dupe = $pdo->prepare("SELECT id FROM usmsmart_archive_runs WHERE status = 'pending' AND manifest_hash = ? LIMIT 1");
        $dupe->execute(array($hash));
        $existingId = $dupe->fetchColumn();
        if ($existingId) {
            echo json_encode(array('success' => true, 'id' => (int)$existingId, 'duplicate' => true, 'message' => 'Permintaan yang sama sudah menunggu kelulusan Admin.'));
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO usmsmart_archive_runs (policy_months, include_sessions, cutoff_at, student_count, session_count, student_ids, session_ids, manifest_hash, status, requested_by, requested_by_username) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute(array(
            $months,
            $includeSessions ? 1 : 0,
            $cutoffAt,
            count($studentIds),
            count($sessionIds),
            json_encode($studentIds),
            json_encode($sessionIds),
            $hash,
            'pending',
            (int)$_SESSION['user_id'],
            (string)$_SESSION['username']
        ));
        $runId = (int)$pdo->lastInsertId();
        if (function_exists('auditLog')) auditLog($pdo, 'archive_request', 'archive_run', (string)$runId, null, 'Archive requested: ' . count($studentIds) . ' students, ' . count($sessionIds) . ' sessions, window ' . $months . ' months');
        echo json_encode(array('success' => true, 'id' => $runId, 'message' => 'Permintaan arkib dihantar. Rekod asal belum diubah sehingga kelulusan Admin kedua.'));
    } catch (Throwable $e) {
        error_log('USMSmart archive request failed: ' . $e->getMessage());
        usmsmartArchiveFail(500, 'Permintaan arkib tidak dapat disimpan. Sila cuba lagi.');
    }
    exit;
}

if ($action === 'archive_approve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    usmsmartArchiveRequireAdmin();
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) usmsmartArchiveFail(400, 'Data permintaan tidak sah.');
    $runId = (int)($payload['id'] ?? 0);
    if ($runId < 1) usmsmartArchiveFail(400, 'Permintaan arkib tidak sah.');
    $confirm = trim((string)($payload['confirm'] ?? ''));
    $note = substr(trim((string)($payload['note'] ?? '')), 0, 500);
    $actorId = (int)$_SESSION['user_id'];
    $actorName = (string)$_SESSION['username'];

    try {
        // DDL before the transaction: MySQL DDL implicitly commits.
        usmsmartArchiveEnsureTables($pdo);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM usmsmart_archive_runs WHERE id = ? AND status = 'pending' FOR UPDATE");
        $stmt->execute(array($runId));
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$run) throw new RuntimeException('Permintaan ini tidak lagi menunggu kelulusan.');
        $selfApproval = ((int)$run['requested_by'] === $actorId);
        if ($selfApproval && $confirm !== 'ARKIB-' . $runId) {
            throw new RuntimeException('Permintaan ini dibuat oleh anda. Taip ARKIB-' . $runId . ' untuk mengesahkan kelulusan sendiri.');
        }

        $cutoffAt = (string)$run['cutoff_at'];
        $cutoffDate = substr($cutoffAt, 0, 10);
        $studentIds = json_decode((string)$run['student_ids'], true);
        $sessionIds = json_decode((string)$run['session_ids'], true);
        if (!is_array($studentIds)) $studentIds = array();
        if (!is_array($sessionIds)) $sessionIds = array();
        $studentIds = array_values(array_filter(array_map('intval', $studentIds)));
        $sessionIds = array_values(array_filter(array_map('intval', $sessionIds)));

        // Re-check eligibility inside the transaction. A renewal, a new UDA staging row or a
        // policy assignment since the request must keep that record out of the archive.
        $eligibleStudents = array();
        if ($studentIds) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $where = "s.expiry_date IS NOT NULL AND s.expiry_date > '1000-01-01' AND s.expiry_date < ?"
                . " AND NOT EXISTS (SELECT 1 FROM uda_system_records u WHERE UPPER(TRIM(u.matric)) = UPPER(TRIM(s.matric)))";
            if (usmsmartArchivePolicyTableExists($pdo)) {
                $where .= ' AND NOT EXISTS (SELECT 1 FROM usmsmart_card_policy_assignments a WHERE UPPER(TRIM(a.source_identifier)) = UPPER(TRIM(s.matric)))';
            }
            $select = $pdo->prepare('SELECT s.* FROM students_view s WHERE s.id IN (' . $placeholders . ') AND ' . $where);
            $select->execute(array_merge($studentIds, array($cutoffDate)));
            $eligibleStudents = $select->fetchAll(PDO::FETCH_ASSOC);
        }

        $eligibleSessions = array();
        if ($sessionIds) {
            $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
            $select = $pdo->prepare('SELECT * FROM uda_transport_sessions WHERE id IN (' . $placeholders . ') AND session_date IS NOT NULL AND session_date < ?');
            $select->execute(array_merge($sessionIds, array($cutoffAt)));
            $eligibleSessions = $select->fetchAll(PDO::FETCH_ASSOC);
        }

        if (!$eligibleStudents && !$eligibleSessions) throw new RuntimeException('Tiada rekod yang masih layak. Semua rekod dalam permintaan telah berubah.');

        $insertStudent = $pdo->prepare('INSERT INTO usmsmart_archive_students (run_id, student_id, name, matric, school, date_joined, expiry_date, usm_uid, serial_no, policy, nationality, education_level, student_category, archived_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $archivedStudentIds = array();
        foreach ($eligibleStudents as $row) {
            $insertStudent->execute(array(
                $runId, (int)$row['id'], $row['name'], $row['matric'], $row['school'], $row['date_joined'],
                $row['expiry_date'], $row['usm_uid'], $row['serial_no'], $row['policy'], $row['nationality'],
                $row['education_level'], $row['student_category'], $actorName
            ));
            $archivedStudentIds[] = (int)$row['id'];
        }
        usmsmartArchiveDeleteIds($pdo, 'students_view', $archivedStudentIds);

        $insertSession = $pdo->prepare('INSERT INTO usmsmart_archive_sessions (run_id, session_id, session_date, total_records, details, is_deleted, session_name, archived_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $archivedSessionIds = array();
        foreach ($eligibleSessions as $row) {
            $insertSession->execute(array(
                $runId, (int)$row['id'], $row['session_date'], $row['total_records'], $row['details'],
                (int)$row['is_deleted'], $row['session_name'], $actorName
            ));
            $archivedSessionIds[] = (int)$row['id'];
        }
        usmsmartArchiveDeleteIds($pdo, 'uda_transport_sessions', $archivedSessionIds);

        $update = $pdo->prepare("UPDATE usmsmart_archive_runs SET status = 'approved', reviewed_by = ?, reviewed_by_username = ?, review_note = ?, self_approved = ?, archived_students = ?, archived_sessions = ?, reviewed_at = NOW(), completed_at = NOW() WHERE id = ?");
        $update->execute(array($actorId, $actorName, ($note === '' ? null : $note), $selfApproval ? 1 : 0, count($archivedStudentIds), count($archivedSessionIds), $runId));
        $pdo->commit();

        $skippedStudents = max(0, count($studentIds) - count($archivedStudentIds));
        $skippedSessions = max(0, count($sessionIds) - count($archivedSessionIds));
        if (function_exists('auditLog')) auditLog($pdo, 'archive_approve', 'archive_run', (string)$runId, null, 'Archived ' . count($archivedStudentIds) . ' students and ' . count($archivedSessionIds) . ' sessions; skipped ' . $skippedStudents . ' students and ' . $skippedSessions . ' sessions');
        echo json_encode(array(
            'success' => true,
            'id' => $runId,
            'archived_students' => count($archivedStudentIds),
            'archived_sessions' => count($archivedSessionIds),
            'skipped_students' => $skippedStudents,
            'skipped_sessions' => $skippedSessions,
            'self_approved' => $selfApproval,
            'message' => 'Arkib selesai: ' . count($archivedStudentIds) . ' pelajar dan ' . count($archivedSessionIds) . ' sesi dipindahkan.'
        ));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('USMSmart archive approval failed: ' . $e->getMessage());
        usmsmartArchiveFail(400, $e->getMessage() !== '' ? $e->getMessage() : 'Kelulusan arkib gagal.');
    }
    exit;
}

if ($action === 'archive_reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    usmsmartArchiveRequireAdmin();
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) usmsmartArchiveFail(400, 'Data permintaan tidak sah.');
    $runId = (int)($payload['id'] ?? 0);
    $note = trim((string)($payload['note'] ?? ''));
    if ($runId < 1) usmsmartArchiveFail(400, 'Permintaan arkib tidak sah.');
    if ($note === '') usmsmartArchiveFail(400, 'Sebab penolakan diperlukan.');
    if (mb_strlen($note) > 500) usmsmartArchiveFail(400, 'Sebab penolakan maksimum 500 aksara.');
    try {
        usmsmartArchiveEnsureTables($pdo);
        $stmt = $pdo->prepare("UPDATE usmsmart_archive_runs SET status = 'rejected', reviewed_by = ?, reviewed_by_username = ?, review_note = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->execute(array((int)$_SESSION['user_id'], (string)$_SESSION['username'], $note, $runId));
        if ($stmt->rowCount() < 1) usmsmartArchiveFail(400, 'Permintaan ini tidak lagi menunggu kelulusan.');
        if (function_exists('auditLog')) auditLog($pdo, 'archive_reject', 'archive_run', (string)$runId, null, 'Archive rejected: ' . $note);
        echo json_encode(array('success' => true, 'message' => 'Permintaan arkib ditolak. Tiada rekod diubah.'));
    } catch (Throwable $e) {
        error_log('USMSmart archive rejection failed: ' . $e->getMessage());
        usmsmartArchiveFail(500, 'Permintaan tidak dapat ditolak. Sila cuba lagi.');
    }
    exit;
}

if ($action === 'archive_history' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    usmsmartArchiveRequireAdmin();
    try {
        usmsmartArchiveEnsureTables($pdo);
        $rows = $pdo->query('SELECT id, policy_months, include_sessions, cutoff_at, student_count, session_count, status, requested_by, requested_by_username, reviewed_by_username, review_note, self_approved, archived_students, archived_sessions, created_at, reviewed_at, completed_at FROM usmsmart_archive_runs ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(array('success' => true, 'runs' => $rows, 'current_user_id' => (int)$_SESSION['user_id']));
    } catch (Throwable $e) {
        error_log('USMSmart archive history failed: ' . $e->getMessage());
        usmsmartArchiveFail(500, 'Sejarah arkib tidak tersedia.');
    }
    exit;
}

if ($action === 'archive_manifest' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    usmsmartArchiveRequireAdmin();
    $runId = (int)($_GET['id'] ?? 0);
    $kind = (string)($_GET['kind'] ?? 'students');
    if ($runId < 1 || !in_array($kind, array('students', 'sessions'), true)) usmsmartArchiveFail(400, 'Permintaan manifest tidak sah.');
    try {
        usmsmartArchiveEnsureTables($pdo);
        $runStmt = $pdo->prepare("SELECT id, status FROM usmsmart_archive_runs WHERE id = ? AND status = 'approved' LIMIT 1");
        $runStmt->execute(array($runId));
        if (!$runStmt->fetch(PDO::FETCH_ASSOC)) usmsmartArchiveFail(404, 'Manifest hanya tersedia untuk permintaan yang telah diluluskan.');
        if ($kind === 'students') {
            $stmt = $pdo->prepare('SELECT student_id, name, matric, school, date_joined, expiry_date, usm_uid, serial_no, policy, nationality, education_level, student_category, archived_by, archived_at FROM usmsmart_archive_students WHERE run_id = ? ORDER BY id ASC');
            $stmt->execute(array($runId));
            $header = array('Student ID','Name','Matric','School','Date Joined','Expiry Date','USM UID','Serial Number','Policy','Nationality','Education Level','Student Category','Archived By','Archived At');
            $filename = 'usmsmart_archive_' . $runId . '_students.csv';
        } else {
            $stmt = $pdo->prepare('SELECT session_id, session_date, total_records, session_name, is_deleted, details, archived_by, archived_at FROM usmsmart_archive_sessions WHERE run_id = ? ORDER BY id ASC');
            $stmt->execute(array($runId));
            $header = array('Session ID','Session Date','Total Records','Session Name','Was Deleted','Details','Archived By','Archived At');
            $filename = 'usmsmart_archive_' . $runId . '_sessions.csv';
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, private');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, $header);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach ($row as $key => $value) {
                if (is_string($value) && preg_match('/^[=+@\-\t\r]/', $value)) $row[$key] = "'" . $value;
            }
            fputcsv($out, array_values($row));
        }
        fclose($out);
        if (function_exists('auditLog')) auditLog($pdo, 'archive_manifest', 'archive_run', (string)$runId, null, 'Downloaded ' . $kind . ' archive manifest');
    } catch (Throwable $e) {
        error_log('USMSmart archive manifest failed: ' . $e->getMessage());
        http_response_code(500);
        echo 'Manifest tidak dapat dijana.';
    }
    exit;
}
