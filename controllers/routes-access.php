<?php
// Access policies, access locations, card policy and raw record routes.
// Included by index.php in the same scope, so it reads $action, $pdo,
// $authReady and $_SESSION directly. USM_ROOT always points at the app root.
if (!defined('USM_ROOT')) {
    // Reached directly instead of through index.php: these are includes, not endpoints.
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

    if ($action === 'manage_access_policies' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        try { echo json_encode(array('success'=>true,'policies'=>usmsmartGetManagedAccessPolicies($pdo)), JSON_UNESCAPED_UNICODE); }
        catch (Throwable $e) { error_log('USMSmart access policy list failed: '.$e->getMessage()); http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Senarai polisi akses tidak dapat dimuatkan.')); }
        exit;
    }
    if ($action === 'manage_access_locations' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        try { usmsmartEnsureAccessPolicyTables($pdo); $rows=$pdo->query('SELECT id,location_name,description,is_active,created_by,created_at,updated_at FROM usmsmart_access_locations ORDER BY location_name')->fetchAll(PDO::FETCH_ASSOC); echo json_encode(array('success'=>true,'locations'=>$rows), JSON_UNESCAPED_UNICODE); }
        catch (Throwable $e) { error_log('USMSmart access location list failed: '.$e->getMessage()); http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Senarai lokasi akses tidak dapat dimuatkan.')); }
        exit;
    }
    if ($action === 'get_access_policy_details' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        try { usmsmartEnsureAccessPolicyTables($pdo); $id=(int)($_GET['id']??0); $st=$pdo->prepare('SELECT p.id,p.policy_name,p.description,p.is_active FROM usmsmart_access_policies p WHERE p.id=?'); $st->execute(array($id)); $policy=$st->fetch(PDO::FETCH_ASSOC); if (!$policy) { http_response_code(404); echo json_encode(array('success'=>false,'message'=>'Polisi tidak ditemui.')); exit; } $st=$pdo->prepare('SELECT l.id,l.location_name,l.description,l.is_active FROM usmsmart_access_locations l INNER JOIN usmsmart_access_policy_locations pl ON pl.location_id=l.id WHERE pl.policy_id=? ORDER BY l.location_name'); $st->execute(array($id)); echo json_encode(array('success'=>true,'policy'=>$policy,'locations'=>$st->fetchAll(PDO::FETCH_ASSOC)),JSON_UNESCAPED_UNICODE); }
        catch (Throwable $e) { error_log('USMSmart access policy details failed: '.$e->getMessage()); http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Butiran polisi tidak dapat dimuatkan.')); }
        exit;
    }
    if ($action === 'manage_access_policies' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Akses pentadbir diperlukan.')); exit; }
        $input=json_decode(file_get_contents('php://input'),true); if (!is_array($input)) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Data permintaan tidak sah.')); exit; }
        $op=(string)($input['operation']??'save'); $id=(int)($input['id']??0); $name=trim((string)($input['policyName']??'')); $desc=trim((string)($input['description']??'')); $active=isset($input['isActive'])?(int)!!$input['isActive']:1; $locations=$input['locationIds']??[];
        try { usmsmartEnsureAccessPolicyTables($pdo); $actor=(string)($_SESSION['username']??'admin');
            if ($op==='save') { if ($name==='' || mb_strlen($name)>100) throw new RuntimeException('Nama polisi wajib diisi (maksimum 100 aksara).'); if (mb_strlen($desc)>500) throw new RuntimeException('Penerangan maksimum 500 aksara.'); if (!is_array($locations)) throw new RuntimeException('Senarai lokasi tidak sah.'); $pdo->beginTransaction(); if ($id>0) { $st=$pdo->prepare('UPDATE usmsmart_access_policies SET policy_name=?,description=?,is_active=?,updated_by=? WHERE id=?'); $st->execute(array($name,$desc!==''?$desc:null,$active,$actor,$id)); if (!$st->rowCount()) { $check=$pdo->prepare('SELECT id FROM usmsmart_access_policies WHERE id=?'); $check->execute(array($id)); if (!$check->fetchColumn()) throw new RuntimeException('Polisi tidak ditemui.'); } } else { $st=$pdo->prepare('INSERT INTO usmsmart_access_policies (policy_name,description,is_active,created_by,updated_by) VALUES (?,?,?,?,?)'); $st->execute(array($name,$desc!==''?$desc:null,$active,$actor,$actor)); $id=(int)$pdo->lastInsertId(); } $pdo->prepare('DELETE FROM usmsmart_access_policy_locations WHERE policy_id=?')->execute(array($id)); $add=$pdo->prepare('INSERT INTO usmsmart_access_policy_locations (policy_id,location_id,created_by) SELECT ?,id,? FROM usmsmart_access_locations WHERE id=? AND is_active=1'); foreach (array_unique(array_map('intval',$locations)) as $locId) { if ($locId<1) continue; $add->execute(array($id,$actor,$locId)); if ($add->rowCount()!==1) throw new RuntimeException('Lokasi tidak wujud atau tidak aktif.'); } $pdo->commit(); auditLog($pdo,'manage_access_policy','access_policy',(string)$id,null,'Polisi akses disimpan: '.$name.'; lokasi terpilih='.count($locations)); echo json_encode(array('success'=>true,'message'=>'Polisi akses berjaya disimpan.','id'=>$id));
            } elseif ($op==='set_active') { if ($id<1) throw new RuntimeException('ID polisi tidak sah.'); $st=$pdo->prepare('UPDATE usmsmart_access_policies SET is_active=?,updated_by=? WHERE id=?'); $st->execute(array($active,$actor,$id)); if (!$st->rowCount()) { $check=$pdo->prepare('SELECT id FROM usmsmart_access_policies WHERE id=?'); $check->execute(array($id)); if (!$check->fetchColumn()) throw new RuntimeException('Polisi tidak ditemui.'); } auditLog($pdo,'toggle_access_policy','access_policy',(string)$id,null,'Status polisi akses '.$id.' ditukar kepada '.($active?'aktif':'tidak aktif')); echo json_encode(array('success'=>true,'message'=>'Status polisi akses dikemas kini.'));
            } else { throw new RuntimeException('Operasi polisi tidak dikenali.'); }
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('USMSmart access policy save failed: '.$e->getMessage()); http_response_code(400); echo json_encode(array('success'=>false,'message'=>$e instanceof RuntimeException ? $e->getMessage() : (str_contains(strtolower($e->getMessage()),'duplicate') ? 'Nama polisi sudah digunakan.' : 'Polisi akses tidak dapat disimpan. Semak log ralat aplikasi.'))); }
        exit;
    }
    if ($action === 'manage_access_locations' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8'); if (($_SESSION['role']??'')!=='admin') { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Akses pentadbir diperlukan.')); exit; } $input=json_decode(file_get_contents('php://input'),true); if (!is_array($input)) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Data permintaan tidak sah.')); exit; } $op=(string)($input['operation']??'save'); $id=(int)($input['id']??0); $name=trim((string)($input['locationName']??'')); $desc=trim((string)($input['description']??'')); $active=isset($input['isActive'])?(int)!!$input['isActive']:1; try { usmsmartEnsureAccessPolicyTables($pdo); $actor=(string)($_SESSION['username']??'admin'); if ($op==='save') { if ($name===''||mb_strlen($name)>150) throw new RuntimeException('Nama lokasi wajib diisi (maksimum 150 aksara).'); if (mb_strlen($desc)>500) throw new RuntimeException('Penerangan maksimum 500 aksara.'); if ($id>0) { $st=$pdo->prepare('UPDATE usmsmart_access_locations SET location_name=?,description=?,is_active=? WHERE id=?'); $st->execute(array($name,$desc!==''?$desc:null,$active,$id)); } else { $st=$pdo->prepare('INSERT INTO usmsmart_access_locations (location_name,description,is_active,created_by) VALUES (?,?,?,?)'); $st->execute(array($name,$desc!==''?$desc:null,$active,$actor)); $id=(int)$pdo->lastInsertId(); } auditLog($pdo,'manage_access_location','access_location',(string)$id,null,'Lokasi akses disimpan: '.$name); echo json_encode(array('success'=>true,'message'=>'Lokasi akses berjaya disimpan.','id'=>$id)); } elseif ($op==='set_active') { if ($id<1) throw new RuntimeException('ID lokasi tidak sah.'); $st=$pdo->prepare('UPDATE usmsmart_access_locations SET is_active=? WHERE id=?'); $st->execute(array($active,$id)); echo json_encode(array('success'=>true,'message'=>'Status lokasi dikemas kini.')); } else throw new RuntimeException('Operasi lokasi tidak dikenali.'); } catch (Throwable $e) { error_log('USMSmart access location save failed: '.$e->getMessage()); http_response_code(400); echo json_encode(array('success'=>false,'message'=>$e instanceof RuntimeException?$e->getMessage():(str_contains(strtolower($e->getMessage()),'duplicate')?'Nama lokasi sudah digunakan.':'Lokasi akses tidak dapat disimpan. Semak log ralat aplikasi.'))); } exit;
    }

    if ($action === 'get_card_policies' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        try { echo json_encode(array('success'=>true, 'policies'=>usmsmartGetExistingPolicies($pdo)), JSON_UNESCAPED_UNICODE); }
        catch (Throwable $e) { error_log('USMSmart policy list failed: '.$e->getMessage()); http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Senarai polisi sedia ada tidak dapat dimuatkan.')); }
        exit;
    }

    if ($action === 'assign_card_policy' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Akses pentadbir diperlukan.')); exit; }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Data permintaan tidak sah.')); exit; }
        $type = strtolower(trim((string)($input['cardholderType'] ?? '')));
        $sourceId = trim((string)($input['sourceId'] ?? ''));
        $identifier = trim((string)($input['identifier'] ?? ''));
        $policy = trim((string)($input['policy'] ?? ''));
        if (!in_array($type, array('student','staff'), true) || $sourceId === '' || $identifier === '' || $policy === '') { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Jenis pemegang kad, pengenal dan polisi diperlukan.')); exit; }
        try {
            $policies = usmsmartGetExistingPolicies($pdo);
            if (!in_array($policy, $policies, true)) throw new RuntimeException('Polisi palang yang dipilih tidak wujud atau tidak aktif.');
            // Verify both the source row ID and identifier against the authoritative SQL Server table.
            $sqlServer = usmsmartGetSqlServerPdo();
            if ($type === 'student') {
                $verify = $sqlServer->prepare("SELECT TOP 1 CAST(id AS varchar(30)) AS sourceId, LTRIM(RTRIM(COALESCE(NoMatrik,''))) AS identifier FROM dbo.Pelajar WHERE CAST(id AS varchar(30))=? AND LTRIM(RTRIM(COALESCE(NoMatrik,'')))=?");
            } else {
                $verify = $sqlServer->prepare("SELECT TOP 1 CAST(id AS varchar(30)) AS sourceId, LTRIM(RTRIM(COALESCE(NoStaf,''))) AS identifier FROM dbo.Staf WHERE CAST(id AS varchar(30))=? AND LTRIM(RTRIM(COALESCE(NoStaf,'')))=?");
            }
            $verify->execute(array($sourceId, $identifier));
            if (!$verify->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('Rekod tidak sepadan dengan identiti semasa dalam SQL Server. Muat semula rekod dan cuba lagi.');
            usmsmartEnsurePolicyAssignmentTable($pdo);
            $find = $pdo->prepare('SELECT policy FROM usmsmart_card_policy_assignments WHERE cardholder_type=? AND UPPER(TRIM(source_identifier))=UPPER(?) LIMIT 1');
            $find->execute(array($type,$identifier)); $previous = $find->fetchColumn();
            $save = $pdo->prepare('INSERT INTO usmsmart_card_policy_assignments (cardholder_type,source_identifier,source_id,policy,assigned_by) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE source_id=VALUES(source_id), policy=VALUES(policy), assigned_by=VALUES(assigned_by)');
            $save->execute(array($type,$identifier,$sourceId,$policy,(string)$_SESSION['username']));
            auditLog($pdo, 'assign_card_policy', 'card_policy_assignment', $type.':'.$identifier, $identifier, 'Jenis='.$type.'; Polisi='.($previous === false ? '(belum ditetapkan)' : (string)$previous).' -> '.$policy.'; SQL Server source ID='.$sourceId);
            echo json_encode(array('success'=>true,'message'=>'Polisi '.$policy.' berjaya ditetapkan untuk '.$identifier.'.','policy'=>$policy));
        } catch (Throwable $e) {
            error_log('USMSmart assign card policy failed: '.$e->getMessage()); http_response_code(400); echo json_encode(array('success'=>false,'message'=>$e instanceof RuntimeException ? $e->getMessage() : 'Polisi tidak dapat disimpan. Semak log ralat aplikasi.'));
        }
        exit;
    }

    if ($action === 'get_school_options' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        $cardholderType = strtolower(trim((string)($_GET['cardholderType'] ?? 'student')));
        if (!in_array($cardholderType, array('student','staff'), true)) {
            http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Kategori pemegang kad tidak sah.')); exit;
        }
        try {
            $sqlServer = usmsmartGetSqlServerPdo();
            $column = $cardholderType === 'student' ? 'PPengajian' : 'NamaJabatan';
            $table = $cardholderType === 'student' ? 'dbo.Pelajar' : 'dbo.Staf';
            $stmt = $sqlServer->query("SELECT DISTINCT LTRIM(RTRIM(COALESCE({$column},''))) AS school FROM {$table} WHERE NULLIF(LTRIM(RTRIM(COALESCE({$column},''))),'') IS NOT NULL ORDER BY LTRIM(RTRIM(COALESCE({$column},'')))");
            $schools = array();
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $school) {
                $school = trim((string)$school);
                if ($school !== '' && !usmsmartIsScopedOutSchool($school)) $schools[] = $school;
            }
            echo json_encode(array('success'=>true,'schools'=>$schools), JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('USMSmart school options failed: '.$e->getMessage());
            http_response_code(503);
            echo json_encode(array('success'=>false,'message'=>'Senarai sekolah daripada pangkalan data langsung tidak dapat dimuatkan. Semak sambungan SQL Server.'));
        }
        exit;
    }

    if ($action === 'get_records') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        $startRaw = isset($_GET['startDate']) ? (string)$_GET['startDate'] : '';
        $endRaw = isset($_GET['endDate']) ? (string)$_GET['endDate'] : '';
        $cardholderType = isset($_GET['cardholderType']) ? strtolower(trim((string)$_GET['cardholderType'])) : 'student';
        $recordSearch = trim((string)($_GET['recordSearch'] ?? ''));
        $recordStatus = trim((string)($_GET['recordStatus'] ?? ''));
        $recordSchool = trim((string)($_GET['recordSchool'] ?? ''));
        // User-controlled pull count: intentionally no application-level maximum.
        $recordLimitRaw = trim((string)($_GET['recordLimit'] ?? '100'));
        if ($recordLimitRaw === '' || !ctype_digit($recordLimitRaw) || (float)$recordLimitRaw < 1 || (float)$recordLimitRaw > PHP_INT_MAX) {
            http_response_code(400); echo json_encode(array('error'=>'Masukkan bilangan rekod yang sah (nombor bulat lebih besar daripada sifar).')); exit;
        }
        $recordLimit = (int)$recordLimitRaw;
        if (mb_strlen($recordSearch) > 120 || mb_strlen($recordStatus) > 80 || mb_strlen($recordSchool) > 160) {
            http_response_code(400); echo json_encode(array('error'=>'Nilai penapis terlalu panjang.')); exit;
        }
        if (!in_array($cardholderType, array('student','staff'), true)) {
            http_response_code(400); echo json_encode(array('error'=>'Kategori pemegang kad tidak sah.')); exit;
        }
        if ($startRaw === '' || $endRaw === '') { echo json_encode(array()); exit; }
        if (!isValidIsoDate($startRaw) || !isValidIsoDate($endRaw) || $startRaw > $endRaw) {
            http_response_code(400); echo json_encode(array('error'=>'Julat tarikh tidak sah. Sila pilih tarikh mula dan tamat yang betul.')); exit;
        }
        try {
            $sqlServer = usmsmartGetSqlServerPdo();
            if ($cardholderType === 'student') {
                $sql = "SELECT CAST(id AS varchar(30)) AS sourceId, LTRIM(RTRIM(COALESCE(Nama,''))) AS name, LTRIM(RTRIM(COALESCE(NoMatrik,''))) AS identifier, LTRIM(RTRIM(COALESCE(PPengajian,''))) AS school, LTRIM(RTRIM(COALESCE(NoSiriKad,''))) AS usmUid, LTRIM(RTRIM(COALESCE(StatusKad,''))) AS cardStatus, CAST(COALESCE(TkhBeri,TkhCetak,TkhKemaskini,TkhMasuk,CreatedDate) AS date) AS dateJoined, CAST(TkhTamat AS date) AS expiryDate FROM dbo.Pelajar WHERE COALESCE(NULLIF(LTRIM(RTRIM(NoMatrik)),''),'') <> '' AND COALESCE(NULLIF(LTRIM(RTRIM(NoSiriKad)),''),'') <> '' AND CAST(COALESCE(TkhBeri,TkhCetak,TkhKemaskini,TkhMasuk,CreatedDate) AS date) BETWEEN ? AND ? ORDER BY COALESCE(TkhBeri,TkhCetak,TkhKemaskini,TkhMasuk,CreatedDate), Nama, id";
            } else {
                $sql = "SELECT CAST(id AS varchar(30)) AS sourceId, LTRIM(RTRIM(COALESCE(Nama,''))) AS name, LTRIM(RTRIM(COALESCE(NoStaf,''))) AS identifier, LTRIM(RTRIM(COALESCE(NamaJabatan,''))) AS school, LTRIM(RTRIM(COALESCE(NoSiriKad,''))) AS usmUid, LTRIM(RTRIM(COALESCE(StatusKad,''))) AS cardStatus, CAST(COALESCE(TkhBeri,TkhCetak,TkhKemaskini,CreatedDate) AS date) AS dateJoined, CAST(TkhTamat AS date) AS expiryDate FROM dbo.Staf WHERE COALESCE(NULLIF(LTRIM(RTRIM(NoStaf)),''),'') <> '' AND COALESCE(NULLIF(LTRIM(RTRIM(NoSiriKad)),''),'') <> '' AND CAST(COALESCE(TkhBeri,TkhCetak,TkhKemaskini,CreatedDate) AS date) BETWEEN ? AND ? ORDER BY COALESCE(TkhBeri,TkhCetak,TkhKemaskini,CreatedDate), Nama, id";
            }
            $searchColumn = $cardholderType === 'student' ? 'NoMatrik' : 'NoStaf';
            $schoolColumn = $cardholderType === 'student' ? 'PPengajian' : 'NamaJabatan';
            $sql = usmsmartApplyScopedOutSchools($sql, $schoolColumn);
            if ($recordSearch !== '') {
                // Case-insensitive partial search across name, identifier, card UID, and school/department.
                $sql = str_replace(
                    ' ORDER BY COALESCE(',
                    " AND (LOWER(COALESCE(Nama,'')) LIKE LOWER(?) OR LOWER(COALESCE({$searchColumn},'')) LIKE LOWER(?) OR LOWER(COALESCE(NoSiriKad,'')) LIKE LOWER(?) OR LOWER(COALESCE({$schoolColumn},'')) LIKE LOWER(?)) ORDER BY COALESCE(",
                    $sql
                );
            }
            if ($recordStatus !== '') {
                $sql = str_replace(' ORDER BY COALESCE(', " AND LTRIM(RTRIM(COALESCE(StatusKad,''))) = ? ORDER BY COALESCE(", $sql);
            }
            if ($recordSchool !== '') {
                $sql = str_replace(' ORDER BY COALESCE(', " AND COALESCE({$schoolColumn},'') LIKE ? ORDER BY COALESCE(", $sql);
            }
            $params = array($startRaw, $endRaw);
            if ($recordSearch !== '') { $like = '%' . $recordSearch . '%'; array_push($params, $like, $like, $like, $like); }
            if ($recordStatus !== '') $params[] = $recordStatus;
            if ($recordSchool !== '') $params[] = '%' . $recordSchool . '%';
            $sourceStmt = $sqlServer->prepare($sql);
            $sourceStmt->execute($params);
            // Stream source rows instead of loading the entire date-range result into RAM.
            // The loop stops as soon as the requested number of eligible records is collected.
            $alreadyStmt = $pdo->query("SELECT matric FROM uda_system_records");
            $alreadyInUda = array();
            foreach ($alreadyStmt->fetchAll(PDO::FETCH_COLUMN) as $existingKey) $alreadyInUda[strtoupper(trim((string)$existingKey))] = true;
            $rows = array();
            while ($source = $sourceStmt->fetch(PDO::FETCH_ASSOC)) {
                // Apply the requested limit AFTER excluding records already present in UDA.
                // Fetching only TOP $recordLimit source rows can return fewer than requested
                // whenever some of those rows are already exported.
                $identifier = trim((string)($source['identifier'] ?? ''));
                $uid = strtoupper(preg_replace('/\\s+/', '', trim((string)($source['usmUid'] ?? ''))));
                $key = strtoupper($identifier);
                if ($identifier === '' || isset($alreadyInUda[$key])) continue;
                $policyMatch = usmsmartFindExistingCardPolicy($pdo, $identifier, $uid, '', $cardholderType);
                $policy = $policyMatch ? trim((string)($policyMatch['policy'] ?? '')) : '';
                $serial = $policyMatch ? trim((string)($policyMatch['serial_no'] ?? '')) : '';
                $rows[] = array(
                    'id' => $cardholderType . ':' . (string)$source['sourceId'],
                    'sourceId' => (string)$source['sourceId'],
                    'cardholderType' => $cardholderType,
                    'name' => trim((string)($source['name'] ?? '')),
                    'matric' => $identifier,
                    'school' => trim((string)($source['school'] ?? '')),
                    'dateJoined' => !empty($source['dateJoined']) ? substr((string)$source['dateJoined'], 0, 10) : '',
                    'expiryDate' => !empty($source['expiryDate']) ? substr((string)$source['expiryDate'], 0, 10) : '',
                    'usmUid' => $uid,
                    'serialNo' => $serial,
                    'policy' => $policy,
                    'cardStatus' => trim((string)($source['cardStatus'] ?? '')),
                    'policyAvailable' => $policy !== ''
                );
                if (count($rows) >= $recordLimit) break;
            }
            $candidates = usmsmartUidEligibilityCandidates($rows);
            $uidCounts = usmsmartUidUsageCounts($sqlServer, array_column($candidates, 'uid'));
            $eligibility = usmsmartUidEligibilityPayload($rows, $uidCounts, date('Y-m-d'));
            echo json_encode(array('records' => $rows, 'eligibility' => $eligibility), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable $e) {
            error_log('USMSmart SQL Server get_records failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(array('error'=>'Tidak dapat memuatkan data pemegang kad daripada SQL Server. Semak sambungan SQL Server dan kebenaran baca dbo.Pelajar/dbo.Staf.'));
        }
        exit;
    }

