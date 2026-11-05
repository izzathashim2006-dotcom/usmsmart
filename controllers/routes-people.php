<?php
// Student and staff listing routes, including live-source sync.
// Included by index.php in the same scope, so it reads $action, $pdo,
// $authReady and $_SESSION directly. USM_ROOT always points at the app root.
if (!defined('USM_ROOT')) {
    // Reached directly instead of through index.php: these are includes, not endpoints.
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

    if ($action === 'get_all_students') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            usmsmartEnsureStudentClassificationColumns($pdo);
            $studentsSql = usmsmartApplyScopedOutSchools("SELECT id, name, matric, school, date_joined AS dateJoined, expiry_date AS expiryDate, usm_uid AS usmUid, serial_no AS serialNo, policy, nationality, education_level AS educationLevel, student_category AS studentCategory FROM students_view ORDER BY id DESC", 'school');
            $stmt = $pdo->query($studentsSql);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Read-only classification enrichment from the authoritative live SQL Server.
            // The local app remains usable if SQL Server is unavailable; no live rows are modified.
            try {
                $livePdo = usmsmartGetSqlServerPdo();
                $liveStmt = $livePdo->query("SELECT LTRIM(RTRIM(COALESCE(NoMatrik,''))) AS liveIdentifier, LTRIM(RTRIM(COALESCE(ic,''))) AS liveIc FROM dbo.Pelajar WHERE NULLIF(LTRIM(RTRIM(COALESCE(NoMatrik,''))),'') IS NOT NULL");
                $liveByMatric = array();
                foreach ($liveStmt->fetchAll(PDO::FETCH_ASSOC) as $liveRow) {
                    $key = strtoupper(trim((string)($liveRow['liveIdentifier'] ?? '')));
                    if ($key !== '') $liveByMatric[$key] = trim((string)($liveRow['liveIc'] ?? ''));
                }
                foreach ($students as &$student) {
                    $key = strtoupper(trim((string)($student['matric'] ?? '')));
                    $hasLiveRecord = array_key_exists($key, $liveByMatric);
                    $ic = $hasLiveRecord ? $liveByMatric[$key] : '';
                    $student['liveIcAvailable'] = ($ic !== '');
                    $student['liveIcClassification'] = 'Unknown';
                    if ($ic !== '') {
                        $normalizedIc = strtoupper(preg_replace('/\s+/', '', $ic));
                        if (strpos($normalizedIc, 'USM') === 0) {
                            $student['liveIcClassification'] = 'International';
                        } elseif (preg_match('/^\d{6}-?\d{2}-?\d{4}$/', $normalizedIc)) {
                            $student['liveIcClassification'] = 'Local';
                        }
                        // Live IC classification is the source of truth for the displayed category.
                        if ($hasLiveRecord) {
                            $student['studentCategory'] = $student['liveIcClassification'];
                        }
                    }
                }
                unset($student);
            } catch (Throwable $liveError) {
                error_log('USMSmart live IC classification unavailable: ' . $liveError->getMessage());
                foreach ($students as &$student) {
                    $student['liveIcAvailable'] = false;
                    $student['liveIcClassification'] = 'Unknown';
                }
                unset($student);
            }
            echo json_encode($students, JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('USMSmart action error: ' . $e->getMessage());
            echo json_encode(array('error' => 'Permintaan gagal diproses. Sila semak log pelayan.'));
        }
        exit;
    }


    // Local MySQL staff directory used for CRUD testing. This table is independent from dbo.Staf.
    // The live SQL Server endpoint below remains read-only and is never written to by these actions.
    if (in_array($action, array('get_local_staff','save_local_staff','delete_local_staff'), true)) {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_staff_directory (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                staff_id VARCHAR(50) NULL,
                full_name VARCHAR(200) NULL,
                staff_no VARCHAR(80) NULL,
                name VARCHAR(180) NULL,
                department VARCHAR(180) NULL,
                job_title VARCHAR(150) NULL,
                email VARCHAR(254) NULL,
                phone VARCHAR(40) NULL,
                employment_status ENUM('Active','Inactive','On Leave') NOT NULL DEFAULT 'Active',
                card_serial VARCHAR(100) NULL,
                card_uid VARCHAR(50) NULL,
                card_status VARCHAR(50) NOT NULL DEFAULT 'Tidak dinyatakan',
                date_joined DATE NULL,
                expiry_date DATE NULL,
                notes VARCHAR(500) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_usmsmart_staff_id (staff_id),
                UNIQUE KEY uq_usmsmart_staff_no (staff_no),
                KEY idx_usmsmart_staff_name (name),
                KEY idx_usmsmart_staff_department (department)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            // Non-destructive migration: add compatibility columns when the newer schema already exists.
            $existingColumns = $pdo->query("SHOW COLUMNS FROM usmsmart_staff_directory")->fetchAll(PDO::FETCH_COLUMN, 0);
            $compatColumns = array(
                'staff_id' => "ALTER TABLE usmsmart_staff_directory ADD COLUMN staff_id VARCHAR(50) NULL",
                'full_name' => "ALTER TABLE usmsmart_staff_directory ADD COLUMN full_name VARCHAR(200) NULL",
                'staff_no' => "ALTER TABLE usmsmart_staff_directory ADD COLUMN staff_no VARCHAR(80) NULL",
                'name' => "ALTER TABLE usmsmart_staff_directory ADD COLUMN name VARCHAR(180) NULL",
                'job_title' => "ALTER TABLE usmsmart_staff_directory ADD COLUMN job_title VARCHAR(150) NULL",
                'email' => "ALTER TABLE usmsmart_staff_directory ADD COLUMN email VARCHAR(254) NULL",
                'phone' => "ALTER TABLE usmsmart_staff_directory ADD COLUMN phone VARCHAR(40) NULL",
                'employment_status' => "ALTER TABLE usmsmart_staff_directory ADD COLUMN employment_status ENUM('Active','Inactive','On Leave') NOT NULL DEFAULT 'Active'",
                'card_serial' => "ALTER TABLE usmsmart_staff_directory ADD COLUMN card_serial VARCHAR(100) NULL",
                'card_uid' => "ALTER TABLE usmsmart_staff_directory ADD COLUMN card_uid VARCHAR(50) NULL",
                'card_status' => "ALTER TABLE usmsmart_staff_directory ADD COLUMN card_status VARCHAR(50) NOT NULL DEFAULT 'Tidak dinyatakan'",
                'date_joined' => "ALTER TABLE usmsmart_staff_directory ADD COLUMN date_joined DATE NULL",
                'expiry_date' => "ALTER TABLE usmsmart_staff_directory ADD COLUMN expiry_date DATE NULL",
                'notes' => "ALTER TABLE usmsmart_staff_directory ADD COLUMN notes VARCHAR(500) NULL"
            );
            foreach ($compatColumns as $columnName => $alterSql) {
                if (!in_array($columnName, $existingColumns, true)) $pdo->exec($alterSql);
            }
            usmsmartMigrateStaffUidColumn($pdo);
            $pdo->exec("UPDATE usmsmart_staff_directory SET staff_no=COALESCE(NULLIF(staff_no,''),staff_id), name=COALESCE(NULLIF(name,''),full_name) WHERE (staff_no IS NULL OR staff_no='') OR (name IS NULL OR name='')");

            if ($action === 'get_local_staff' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                $rows = $pdo->query("SELECT id, staff_no AS staffNo, name, department,
                    card_serial AS cardSerial, card_uid AS cardUid, card_status AS cardStatus,
                    DATE_FORMAT(date_joined,'%Y-%m-%d') AS dateJoined,
                    DATE_FORMAT(expiry_date,'%Y-%m-%d') AS expiryDate, notes,
                    DATE_FORMAT(updated_at,'%Y-%m-%d %H:%i:%s') AS updatedAt
                    FROM usmsmart_staff_directory ORDER BY name, staff_no")->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(array('success'=>true,'records'=>$rows,'source'=>'local_mysql'), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                exit;
            }

            if (($_SESSION['role'] ?? '') !== 'admin') {
                http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Hanya Admin boleh mengurus rekod staf ujian tempatan.')); exit;
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405); echo json_encode(array('success'=>false,'message'=>'POST diperlukan.')); exit;
            }
            $csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
            if ($csrf === '' || !hash_equals((string)($_SESSION['usmsmart_csrf_token'] ?? ''), $csrf)) {
                http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Token keselamatan tidak sah. Muat semula halaman.')); exit;
            }
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Data permintaan tidak sah.')); exit; }

            if ($action === 'save_local_staff') {
                $id = (int)($body['id'] ?? 0);
                $staffNo = trim((string)($body['staffNo'] ?? ''));
                $name = trim((string)($body['name'] ?? ''));
                $department = trim((string)($body['department'] ?? ''));
                $cardSerial = trim((string)($body['cardSerial'] ?? ''));
                $cardUid = strtoupper(trim((string)($body['cardUid'] ?? '')));
                $cardStatus = trim((string)($body['cardStatus'] ?? 'Aktif'));
                $dateJoined = trim((string)($body['dateJoined'] ?? ''));
                $expiryDate = trim((string)($body['expiryDate'] ?? ''));
                $notes = trim((string)($body['notes'] ?? ''));
                if ($staffNo === '' || mb_strlen($staffNo) > 80 || $name === '' || mb_strlen($name) > 180) {
                    http_response_code(422); echo json_encode(array('success'=>false,'message'=>'Nombor staf dan nama wajib diisi (maksimum 80/180 aksara).')); exit;
                }
                if (mb_strlen($department) > 180 || mb_strlen($cardSerial) > 100 || mb_strlen($cardStatus) > 50 || mb_strlen($notes) > 500) {
                    http_response_code(422); echo json_encode(array('success'=>false,'message'=>'Salah satu medan melebihi had panjang yang dibenarkan.')); exit;
                }
                // Whole byte pairs only: storing a ragged or non-hex UID here would mean a Sysweb record
                // that the server-side byte-order proof can never validate.
                if ($cardUid !== '' && (mb_strlen($cardUid) > 50 || preg_match('/^(?:[0-9A-F]{2})+$/', $cardUid) !== 1)) {
                    http_response_code(422); echo json_encode(array('success'=>false,'message'=>'UID kad mesti pasangan heksadesimal (aksara genap).')); exit;
                }
                foreach (array('dateJoined'=>$dateJoined,'expiryDate'=>$expiryDate) as $field=>$dateValue) {
                    if ($dateValue !== '' && !isValidIsoDate($dateValue)) { http_response_code(422); echo json_encode(array('success'=>false,'message'=>'Format tarikh tidak sah.')); exit; }
                }
                if ($dateJoined !== '' && $expiryDate !== '' && $expiryDate < $dateJoined) {
                    http_response_code(422); echo json_encode(array('success'=>false,'message'=>'Tarikh tamat tidak boleh lebih awal daripada tarikh mula.')); exit;
                }
                try {
                    if ($id > 0) {
                        $check = $pdo->prepare('SELECT id FROM usmsmart_staff_directory WHERE id=?'); $check->execute(array($id));
                        if (!$check->fetchColumn()) { http_response_code(404); echo json_encode(array('success'=>false,'message'=>'Rekod staf tempatan tidak ditemui.')); exit; }
                        $st = $pdo->prepare('UPDATE usmsmart_staff_directory SET staff_no=?,staff_id=?,name=?,full_name=?,department=?,card_serial=?,card_uid=?,card_status=?,date_joined=?,expiry_date=?,notes=? WHERE id=?');
                        $st->execute(array($staffNo,$staffNo,$name,$name,$department ?: null,$cardSerial ?: null,$cardUid ?: null,$cardStatus ?: 'Tidak dinyatakan',$dateJoined ?: null,$expiryDate ?: null,$notes ?: null,$id));
                        $message = 'Rekod staf tempatan berjaya dikemas kini.';
                    } else {
                        $st = $pdo->prepare('INSERT INTO usmsmart_staff_directory (staff_no,staff_id,name,full_name,department,card_serial,card_uid,card_status,date_joined,expiry_date,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                        $st->execute(array($staffNo,$staffNo,$name,$name,$department ?: null,$cardSerial ?: null,$cardUid ?: null,$cardStatus ?: 'Tidak dinyatakan',$dateJoined ?: null,$expiryDate ?: null,$notes ?: null));
                        $id = (int)$pdo->lastInsertId();
                        $message = 'Rekod staf tempatan berjaya ditambah.';
                    }
                    echo json_encode(array('success'=>true,'message'=>$message,'id'=>$id), JSON_UNESCAPED_UNICODE); exit;
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate') !== false || (string)$e->getCode() === '23000') {
                        http_response_code(409); echo json_encode(array('success'=>false,'message'=>'Nombor staf itu sudah wujud dalam direktori ujian tempatan.')); exit;
                    }
                    throw $e;
                }
            }

            if ($action === 'delete_local_staff') {
                $id = (int)($body['id'] ?? 0);
                if ($id < 1) { http_response_code(422); echo json_encode(array('success'=>false,'message'=>'ID staf tidak sah.')); exit; }
                $st = $pdo->prepare('DELETE FROM usmsmart_staff_directory WHERE id=?'); $st->execute(array($id));
                if ($st->rowCount() !== 1) { http_response_code(404); echo json_encode(array('success'=>false,'message'=>'Rekod staf tempatan tidak ditemui.')); exit; }
                echo json_encode(array('success'=>true,'message'=>'Rekod staf ujian tempatan dipadam.')); exit;
            }
        } catch (Throwable $e) {
            error_log('USMSmart local staff CRUD failed: ' . $e->getMessage());
            http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Operasi staf tempatan gagal. Semak log Apache/MySQL.')); exit;
        }
    }

    // Read-only Staff Directory from the authoritative USM SQL Server.
    // This endpoint deliberately returns directory/card-status fields only (no IC or sensitive identifiers).
    // NoSiriKad means "card serial" in that schema but stores the card's raw UID, so it is aliased cardUid.
    if ($action === 'get_all_staff') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $livePdo = usmsmartGetSqlServerPdo();
            $staffSql = "SELECT CAST(id AS varchar(30)) AS sourceId,
                                LTRIM(RTRIM(COALESCE(Nama,''))) AS name,
                                LTRIM(RTRIM(COALESCE(NoStaf,''))) AS staffNo,
                                LTRIM(RTRIM(COALESCE(NamaJabatan,''))) AS department,
                                LTRIM(RTRIM(COALESCE(NoSiriKad,''))) AS cardUid,
                                LTRIM(RTRIM(COALESCE(StatusKad,''))) AS cardStatus,
                                CAST(COALESCE(TkhBeri,TkhCetak,TkhKemaskini,CreatedDate) AS date) AS dateJoined,
                                CAST(TkhTamat AS date) AS expiryDate
                         FROM dbo.Staf
                         WHERE NULLIF(LTRIM(RTRIM(COALESCE(NoStaf,''))),'') IS NOT NULL
                         ORDER BY Nama, NoStaf";
            $staffSql = usmsmartApplyScopedOutSchools($staffSql, 'NamaJabatan');
            $staff = $livePdo->query($staffSql)->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(array('success'=>true, 'records'=>$staff), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable $e) {
            error_log('USMSmart staff directory read failed: ' . $e->getMessage());
            http_response_code(503);
            echo json_encode(array('success'=>false, 'error'=>'Direktori staf tidak dapat dimuatkan. Semak sambungan SQL Server dan akses baca jadual dbo.Staf.'));
        }
        exit;
    }
    
    
if ($action === 'sync_live_staff_to_local') {
    header('Content-Type: application/json; charset=utf-8');
    if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Hanya Admin boleh menyegerakkan direktori staf.')); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(array('success'=>false,'message'=>'POST diperlukan.')); exit; }
    $csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($csrf === '' || !hash_equals((string)($_SESSION['usmsmart_csrf_token'] ?? ''), $csrf)) { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Token keselamatan tidak sah. Muat semula halaman.')); exit; }
    try {
        // dbo.Staf remains read-only; only the local MySQL directory is written.
        $livePdo = usmsmartGetSqlServerPdo();
        $staffSql = "SELECT LTRIM(RTRIM(COALESCE(Nama,''))) AS full_name,
            LTRIM(RTRIM(COALESCE(NoStaf,''))) AS staff_id,
            LTRIM(RTRIM(COALESCE(NamaJabatan,''))) AS department,
            LTRIM(RTRIM(COALESCE(NoSiriKad,''))) AS card_uid,
            LTRIM(RTRIM(COALESCE(StatusKad,''))) AS card_status,
            CAST(COALESCE(TkhBeri,TkhCetak,TkhKemaskini,CreatedDate) AS date) AS date_joined,
            CAST(TkhTamat AS date) AS expiry_date FROM dbo.Staf
            WHERE NULLIF(LTRIM(RTRIM(COALESCE(NoStaf,''))),'') IS NOT NULL";
        $staffSql = usmsmartApplyScopedOutSchools($staffSql, 'NamaJabatan');
        $liveStaff = $livePdo->query($staffSql)->fetchAll(PDO::FETCH_ASSOC);
        // Create/migrate safely so both the CRUD UI and current schema can read the same rows.
        $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_staff_directory (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, staff_id VARCHAR(50) NULL,
            full_name VARCHAR(200) NULL, staff_no VARCHAR(80) NULL, name VARCHAR(180) NULL,
            department VARCHAR(180) NULL, job_title VARCHAR(150) NULL, email VARCHAR(254) NULL,
            phone VARCHAR(40) NULL, employment_status ENUM('Active','Inactive','On Leave') NOT NULL DEFAULT 'Active',
            card_serial VARCHAR(100) NULL, card_uid VARCHAR(50) NULL, card_status VARCHAR(50) NOT NULL DEFAULT 'Tidak dinyatakan',
            date_joined DATE NULL, expiry_date DATE NULL, notes VARCHAR(500) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_usmsmart_staff_id (staff_id), UNIQUE KEY uq_usmsmart_staff_no (staff_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $columns = $pdo->query("SHOW COLUMNS FROM usmsmart_staff_directory")->fetchAll(PDO::FETCH_COLUMN,0);
        $needed = array(
            'staff_id'=>"ALTER TABLE usmsmart_staff_directory ADD COLUMN staff_id VARCHAR(50) NULL",
            'full_name'=>"ALTER TABLE usmsmart_staff_directory ADD COLUMN full_name VARCHAR(200) NULL",
            'staff_no'=>"ALTER TABLE usmsmart_staff_directory ADD COLUMN staff_no VARCHAR(80) NULL",
            'name'=>"ALTER TABLE usmsmart_staff_directory ADD COLUMN name VARCHAR(180) NULL",
            'card_serial'=>"ALTER TABLE usmsmart_staff_directory ADD COLUMN card_serial VARCHAR(100) NULL",
            'card_uid'=>"ALTER TABLE usmsmart_staff_directory ADD COLUMN card_uid VARCHAR(50) NULL",
            'card_status'=>"ALTER TABLE usmsmart_staff_directory ADD COLUMN card_status VARCHAR(50) NOT NULL DEFAULT 'Tidak dinyatakan'",
            'date_joined'=>"ALTER TABLE usmsmart_staff_directory ADD COLUMN date_joined DATE NULL",
            'expiry_date'=>"ALTER TABLE usmsmart_staff_directory ADD COLUMN expiry_date DATE NULL",
            'notes'=>"ALTER TABLE usmsmart_staff_directory ADD COLUMN notes VARCHAR(500) NULL",
            'employment_status'=>"ALTER TABLE usmsmart_staff_directory ADD COLUMN employment_status ENUM('Active','Inactive','On Leave') NOT NULL DEFAULT 'Active'"
        );
        foreach ($needed as $col=>$sql) if (!in_array($col,$columns,true)) $pdo->exec($sql);
        usmsmartMigrateStaffUidColumn($pdo);
        $pdo->beginTransaction();
        $upsert = $pdo->prepare("INSERT INTO usmsmart_staff_directory
            (staff_id,full_name,staff_no,name,department,card_uid,card_status,date_joined,expiry_date)
            VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE
            full_name=VALUES(full_name),staff_no=VALUES(staff_no),name=VALUES(name),department=VALUES(department),
            card_uid=VALUES(card_uid),card_status=VALUES(card_status),date_joined=VALUES(date_joined),expiry_date=VALUES(expiry_date)");
        $processed = 0;
        foreach ($liveStaff as $staff) {
            $staffId = trim((string)($staff['staff_id'] ?? '')); $name = trim((string)($staff['full_name'] ?? ''));
            if ($staffId === '' || $name === '') continue;
            $upsert->execute(array($staffId,$name,$staffId,$name,
                trim((string)($staff['department'] ?? '')) ?: null,
                strtoupper(trim((string)($staff['card_uid'] ?? ''))) ?: null,
                trim((string)($staff['card_status'] ?? '')) ?: 'Tidak dinyatakan',
                !empty($staff['date_joined']) ? $staff['date_joined'] : null,
                !empty($staff['expiry_date']) ? $staff['expiry_date'] : null));
            $processed++;
        }
        $pdo->commit();
        echo json_encode(array('success'=>true,'message'=>'Penyegerakan staf selesai. Rekod kini disimpan dalam direktori MySQL tempatan.','source_records'=>count($liveStaff),'processed_records'=>$processed), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        error_log('USMSmart staff sync failed: '.$e->getMessage()); http_response_code(500);
        echo json_encode(array('success'=>false,'message'=>'Penyegerakan staf gagal. Semak log ralat Apache.'), JSON_UNESCAPED_UNICODE);
    }
    exit;
}


    if ($action === 'get_student_card_history') {
        header('Content-Type: application/json; charset=utf-8');
        $studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($studentId < 1) { http_response_code(400); echo json_encode(array('error'=>'ID pelajar tidak sah.')); exit; }
        try {
            $studentStmt = $pdo->prepare("SELECT id,name,matric,school,usm_uid,serial_no,policy,date_joined,expiry_date FROM students_view WHERE id=? LIMIT 1");
            $studentStmt->execute(array($studentId));
            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$student) { http_response_code(404); echo json_encode(array('error'=>'Pelajar tidak ditemui.')); exit; }
            ensureStudentCardHistoryTable($pdo);
            $historyStmt = $pdo->prepare("SELECT id,student_id,matric,event_type,name_snapshot,school_snapshot,uid_snapshot,serial_snapshot,policy_snapshot,joined_snapshot,expiry_snapshot,previous_snapshot,actor,created_at FROM usmsmart_student_card_history WHERE student_id=? ORDER BY created_at DESC,id DESC LIMIT 100");
            $historyStmt->execute(array($studentId));
            $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
            // Seed a baseline for existing students the first time their history is opened.
            if (!$history) {
                recordStudentCardHistory($pdo, $studentId, 'baseline', $student);
                $historyStmt->execute(array($studentId));
                $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            foreach ($history as &$entry) {
                if (!empty($entry['previous_snapshot'])) {
                    $decoded = json_decode($entry['previous_snapshot'], true);
                    $entry['previous_snapshot'] = is_array($decoded) ? $decoded : null;
                } else { $entry['previous_snapshot'] = null; }
            }
            unset($entry);
            echo json_encode(array('student'=>$student,'history'=>$history));
        } catch (Exception $e) {
            error_log('USMSmart card history retrieval failed: '.$e->getMessage());
            http_response_code(500); echo json_encode(array('error'=>'Sejarah kad tidak dapat dimuatkan. Semak kebenaran pangkalan data.'));
        }
        exit;
    }

