<?php
// Card serial allocation and every student/card write route.
// Included by index.php in the same scope, so it reads $action, $pdo,
// $authReady and $_SESSION directly. USM_ROOT always points at the app root.
if (!defined('USM_ROOT')) {
    // Reached directly instead of through index.php: these are includes, not endpoints.
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

if ($action === 'next_card_serial' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    /* Only a preview: the form is allowed to show the number it would get, and save_student
       allocates again if that field reaches the server blank or edited. */
    echo json_encode(array('success'=>true,'serial'=>usmsmartAllocateCardSerial($pdo)));
    exit;
}

    if ($action === 'save_student' && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? intval($input['id']) : 0;
        $name = isset($input['name']) ? trim($input['name']) : '';
        $matric = isset($input['matric']) ? trim($input['matric']) : '';
        $school = isset($input['school']) ? trim($input['school']) : '';
        $usmUid = isset($input['usmUid']) ? trim($input['usmUid']) : '';
        $serialNo = isset($input['serialNo']) ? trim($input['serialNo']) : '';
        $policy = isset($input['policy']) ? trim($input['policy']) : '';
        $policy = usmsmartFillPolicyFromSchool($policy, $school);
        $dateJoined = isset($input['dateJoined']) ? trim($input['dateJoined']) : date('Y-m-d');
        $expiryDate = isset($input['expiryDate']) ? trim($input['expiryDate']) : date('Y-m-d', strtotime('+1 year'));
        $nationality = trim((string)($input['nationality'] ?? ''));
        $educationLevel = trim((string)($input['educationLevel'] ?? ''));
        $studentCategory = trim((string)($input['studentCategory'] ?? ''));
        if ($studentCategory === '' || $studentCategory === 'auto') { $studentCategory = $nationality === '' ? 'Unknown' : (strcasecmp($nationality, 'Malaysian') === 0 || strcasecmp($nationality, 'Malaysia') === 0 ? 'Local' : 'International'); }
        if (!in_array($studentCategory, array('Local','International','Unknown'), true)) $studentCategory = 'Unknown';

        if (!isValidIsoDate($dateJoined) || !isValidIsoDate($expiryDate)) {
            echo json_encode(array('success' => false, 'message' => 'Tarikh mula dan tarikh tamat mesti menggunakan format YYYY-MM-DD yang sah.'));
            exit;
        }

        if ($name === '' || $matric === '') {
            http_response_code(400);
            echo json_encode(array('success' => false, 'message' => 'Nama dan Matrik wajib diisi.'));
            exit;
        }
        if (mb_strlen($name) > 190 || mb_strlen($matric) > 100 || mb_strlen($school) > 255 || mb_strlen($policy) > 100 || mb_strlen($usmUid) > 100 || mb_strlen($serialNo) > 100) {
            http_response_code(400);
            echo json_encode(array('success' => false, 'message' => 'Salah satu medan melebihi panjang maksimum yang dibenarkan. Sila semak semula maklumat.'));
            exit;
        }
        if ($expiryDate < $dateJoined) {
            http_response_code(400);
            echo json_encode(array('success' => false, 'message' => 'Tarikh tamat tidak boleh lebih awal daripada tarikh mula.'));
            exit;
        }

        $cardError = validateCardFields($usmUid, $serialNo);
        if ($cardError !== '') {
            echo json_encode(array('success' => false, 'message' => $cardError));
            exit;
        }
        $usmUid = strtoupper(preg_replace('/\s+/', '', $usmUid));
        $serialNo = strtoupper($serialNo);
        /* Empty means "hand me the next number" on both branches, so a record never ends up
           without one and an operator is never asked to invent a card number. */
        if ($serialNo === '') $serialNo = usmsmartAllocateCardSerial($pdo, $id);
        if ($serialNo === '') {
            http_response_code(503);
            echo json_encode(array('success' => false, 'message' => 'Nombor siri kad automatik tidak dapat dijana. Isikan nombor siri, kemudian cuba lagi.'));
            exit;
        }

        try {
            usmsmartEnsureStudentClassificationColumns($pdo);
            usmsmartEnsureCardSerialUniqueness($pdo);
            if ($id > 0) {
                $dup = $pdo->prepare("SELECT id FROM students_view WHERE id != ? AND (UPPER(matric)=UPPER(?) OR UPPER(usm_uid)=UPPER(?) OR UPPER(serial_no)=UPPER(?)) LIMIT 1");
                $dup->execute(array($id, $matric, $usmUid, $serialNo));
                if ($dup->fetch(PDO::FETCH_ASSOC)) {
                    echo json_encode(array('success' => false, 'message' => 'Matrik, UID atau nombor kad sudah digunakan oleh rekod lain.')); exit;
                }
                $trackedFields = array(
                    'name' => 'Name', 'matric' => 'Matric', 'school' => 'School',
                    'usm_uid' => 'USM UID', 'serial_no' => 'Serial Number', 'policy' => 'Policy',
                    'date_joined' => 'Date Joined', 'expiry_date' => 'Expiry Date', 'nationality' => 'Nationality', 'education_level' => 'Education Level', 'student_category' => 'Student Category'
                );
                $oldStmt = $pdo->prepare("SELECT name, matric, school, usm_uid, serial_no, policy, date_joined, expiry_date, nationality, education_level, student_category FROM students_view WHERE id = ?");
                $oldStmt->execute(array($id));
                $old = $oldStmt->fetch(PDO::FETCH_ASSOC);
                if (!$old) { http_response_code(404); echo json_encode(array('success' => false, 'message' => 'Rekod pelajar tidak ditemui.')); exit; }
                $newValues = array('name'=>$name, 'matric'=>$matric, 'school'=>$school, 'usm_uid'=>$usmUid, 'serial_no'=>$serialNo, 'policy'=>$policy, 'date_joined'=>$dateJoined, 'expiry_date'=>$expiryDate, 'nationality'=>$nationality, 'education_level'=>$educationLevel, 'student_category'=>$studentCategory);
                $changes = array();
                foreach ($trackedFields as $field => $label) {
                    $before = trim((string)($old[$field] ?? ''));
                    $after = trim((string)($newValues[$field] ?? ''));
                    if ($before !== $after) $changes[$label] = array('before'=>$before, 'after'=>$after);
                }
                $stmt = $pdo->prepare("UPDATE students_view SET name = ?, matric = ?, school = ?, usm_uid = ?, serial_no = ?, policy = ?, date_joined = ?, expiry_date = ?, nationality = ?, education_level = ?, student_category = ? WHERE id = ?");
                $stmt->execute(array($name, $matric, $school, $usmUid, $serialNo, $policy, $dateJoined, $expiryDate, $nationality ?: null, $educationLevel ?: null, $studentCategory, $id));
                if ($changes) {
                    try { recordStudentCardHistory($pdo, $id, 'updated', $newValues, $old); }
                    catch (Exception $historyError) { error_log('USMSmart card history write failed: '.$historyError->getMessage()); }
                }
                if ($changes) {
                    $details = json_encode(array('type'=>'record_update', 'summary'=>'Student record updated', 'changes'=>$changes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    auditLog($pdo, 'update', 'student', $id, $matric, $details === false ? 'Student record updated; change details unavailable' : $details);
                }
                echo json_encode(array('success' => true, 'message' => 'Rekod pelajar berjaya dikemaskini.'));
            } else {
                $dup = $pdo->prepare("SELECT id FROM students_view WHERE UPPER(matric)=UPPER(?) OR UPPER(usm_uid)=UPPER(?) OR UPPER(serial_no)=UPPER(?) LIMIT 1");
                $dup->execute(array($matric, $usmUid, $serialNo));
                if ($dup->fetch(PDO::FETCH_ASSOC)) { echo json_encode(array('success' => false, 'message' => 'Matrik, UID atau nombor kad sudah digunakan.')); exit; }
                $stmt = $pdo->prepare("INSERT INTO students_view (name, matric, school, usm_uid, serial_no, policy, date_joined, expiry_date, nationality, education_level, student_category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute(array($name, $matric, $school, $usmUid, $serialNo, $policy, $dateJoined, $expiryDate, $nationality ?: null, $educationLevel ?: null, $studentCategory));
                $newId = $pdo->lastInsertId(); auditLog($pdo, 'create', 'student', $newId, $matric, 'Created student record');
                try { recordStudentCardHistory($pdo, $newId, 'created', array('name'=>$name,'matric'=>$matric,'school'=>$school,'usm_uid'=>$usmUid,'serial_no'=>$serialNo,'policy'=>$policy,'date_joined'=>$dateJoined,'expiry_date'=>$expiryDate)); }
                catch (Exception $historyError) { error_log('USMSmart card history write failed: '.$historyError->getMessage()); }
                echo json_encode(array('success' => true, 'message' => 'Pelajar baharu berjaya ditambah.'));
            }
        } catch (Exception $e) {
            error_log('USMSmart save_student failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(array('success' => false, 'message' => 'Rekod tidak dapat disimpan. Semak pendua dan konfigurasi pangkalan data, kemudian cuba lagi.'));
        }
        exit;
    }

    if ($action === 'import_csv' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Sila pilih fail CSV yang sah.')); exit;
        }
        $file = $_FILES['csv_file']['tmp_name'];
        if (!is_uploaded_file($file) || filesize($file) > 5 * 1024 * 1024) {
            http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Fail tidak sah atau melebihi had 5 MB.')); exit;
        }
        $mode = isset($_POST['mode']) && $_POST['mode'] === 'commit' ? 'commit' : 'preview';
        $handle = fopen($file, 'r');
        if ($handle === false) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Gagal membaca fail CSV.')); exit; }
        $headerRow = fgetcsv($handle, 0, ',');
        if ($headerRow === false || count($headerRow) < 2) { fclose($handle); http_response_code(400); echo json_encode(array('success'=>false,'message'=>'CSV kosong atau pengepala tidak sah.')); exit; }
        // Accept both the app's export headers and legacy positional CSVs.
        $normal = array(); foreach ($headerRow as $i=>$h) { $key = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$h))); $key = preg_replace('/[^a-z0-9]/', '', $key); $normal[$key] = $i; }
        $hasHeaders = isset($normal['name']) && (isset($normal['matric']) || isset($normal['matricno']));
        $map = array('name'=>0,'matric'=>1,'school'=>2,'usmuid'=>3,'serialnumber'=>4,'policy'=>5,'datejoined'=>6,'expirydate'=>7);
        if ($hasHeaders) {
            /* A header row means every column has to be found by name. With the positional defaults
               still in place a CSV that has no serial column keeps reading index 4, so the same wrong
               value - usually another row's policy - is stamped onto every imported card. */
            $map = array('name'=>-1,'matric'=>-1,'school'=>-1,'usmuid'=>-1,'serialnumber'=>-1,'policy'=>-1,'datejoined'=>-1,'expirydate'=>-1);
            $aliases = array('name'=>array('name','studentname'),'matric'=>array('matric','matricno','matricnumber'),'school'=>array('school','schoolname'),'usmuid'=>array('uid','usmuid'),'serialnumber'=>array('serialnumber','serialno','cardserialnumber'),'policy'=>array('policy'),'datejoined'=>array('datejoined','registrationdate'),'expirydate'=>array('expirydate','expiry'));
            foreach ($aliases as $field=>$keys) { foreach ($keys as $key) if (isset($normal[$key])) { $map[$field]=$normal[$key]; break; } }
        } else { rewind($handle); }
        $seen = array(); $results = array(); $validRows = array(); $line = $hasHeaders ? 1 : 0; $maxRows = 10000;
        /* Read once, then grown by every serial this file brings in, so a generated number cannot
           land on a value a later row of the same upload also carries. */
        $serialPool = array();
        try { foreach ($pdo->query("SELECT serial_no FROM students_view WHERE serial_no IS NOT NULL AND TRIM(serial_no) <> ''")->fetchAll(PDO::FETCH_COLUMN) as $stored) $serialPool[] = (string)$stored; }
        catch (Throwable $e) { error_log('USMSmart import serial pool read failed: ' . $e->getMessage()); }
        $defaultSchool = 'Pusat Pengajian Kejuruteraan Mekanikal (School of Mechanical Engineering)';
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            $line++; if (count($data) === 1 && trim((string)$data[0]) === '') continue;
            if (count($results) >= $maxRows) { fclose($handle); http_response_code(413); echo json_encode(array('success'=>false,'message'=>'CSV melebihi had 10,000 baris. Pecahkan fail dan cuba lagi.')); exit; }
            $get = function($field, $fallback='') use ($data,$map) { $i=$map[$field] ?? -1; return ($i >= 0 && isset($data[$i])) ? trim((string)$data[$i]) : $fallback; };
            $row = array('line'=>$line,'name'=>$get('name'),'matric'=>$get('matric'),'school'=>$get('school',$defaultSchool),'uid'=>$get('usmuid'),'serial'=>$get('serialnumber'),'policy'=>$get('policy',''),'joined'=>$get('datejoined',date('Y-m-d')),'expiry'=>$get('expirydate',date('Y-m-d',strtotime('+1 year'))));
            $row['policy'] = usmsmartFillPolicyFromSchool($row['policy'], $row['school']);
            if ($row['serial'] === '') $row['serial'] = usmsmartNextCardSerial($serialPool);
            if ($row['serial'] !== '') $serialPool[] = strtoupper($row['serial']);
            $issues = array();
            if ($row['name']==='') $issues[]='Nama wajib diisi';
            if ($row['matric']==='') $issues[]='Nombor matrik wajib diisi';
            if ($row['school']==='') $issues[]='Sekolah wajib diisi';
            if (!isValidIsoDate($row['joined'])) $issues[]='Tarikh daftar mesti YYYY-MM-DD yang sah';
            if (!isValidIsoDate($row['expiry'])) $issues[]='Tarikh luput mesti YYYY-MM-DD yang sah';
            if ($row['joined']!=='' && $row['expiry']!=='' && isValidIsoDate($row['joined']) && isValidIsoDate($row['expiry']) && $row['expiry'] < $row['joined']) $issues[]='Tarikh luput lebih awal daripada tarikh daftar';
            $cardIssue = validateCardFields($row['uid'],$row['serial']); if ($cardIssue!=='') $issues[]=$cardIssue;
            foreach (array('matric'=>'matrik','uid'=>'UID','serial'=>'nombor siri') as $field=>$label) {
                $value = strtoupper(preg_replace('/\s+/', '', (string)$row[$field]));
                if ($value==='') { if ($field!=='matric' && $field!=='uid' && $field!=='serial') continue; }
                $key=$field.':'.$value;
                if ($value!=='' && isset($seen[$key])) $issues[]='Pendua dalam fail: '.$label.' (baris '.$seen[$key].')';
                elseif ($value!=='') $seen[$key]=$line;
            }
            if (!$issues) {
                $chk=$pdo->prepare("SELECT id, matric, usm_uid, serial_no FROM students_view WHERE UPPER(TRIM(matric))=UPPER(?) OR UPPER(TRIM(usm_uid))=UPPER(?) OR UPPER(TRIM(serial_no))=UPPER(?) LIMIT 1");
                $chk->execute(array($row['matric'],$row['uid'],$row['serial'])); $existing=$chk->fetch(PDO::FETCH_ASSOC);
                if ($existing) $issues[]='Rekod sudah wujud dalam pangkalan data (matrik/UID/nombor siri bertindih)';
            }
            $row['status'] = $issues ? 'error' : 'ready'; $row['issues'] = $issues; $results[]=$row;
            if (!$issues) $validRows[]=$row;
        }
        fclose($handle);
        if ($mode === 'preview') {
            $errors = count($results)-count($validRows);
            echo json_encode(array('success'=>true,'mode'=>'preview','filename'=>basename((string)($_FILES['csv_file']['name'] ?? 'import.csv')),'total'=>count($results),'valid'=>count($validRows),'errors'=>$errors,'rows'=>$results), JSON_INVALID_UTF8_SUBSTITUTE); exit;
        }
        if (!$validRows) { echo json_encode(array('success'=>false,'message'=>'Tiada baris sah untuk diimport. Jalankan pratonton dan betulkan ralat dahulu.','total'=>count($results),'added'=>0,'errors'=>count($results),'rows'=>$results), JSON_INVALID_UTF8_SUBSTITUTE); exit; }
        $added=0; $commitIssues=array();
        try {
            $pdo->beginTransaction();
            $chk=$pdo->prepare("SELECT id FROM students_view WHERE UPPER(TRIM(matric))=UPPER(?) OR UPPER(TRIM(usm_uid))=UPPER(?) OR UPPER(TRIM(serial_no))=UPPER(?) LIMIT 1");
            $ins=$pdo->prepare("INSERT INTO students_view (name, matric, school, usm_uid, serial_no, policy, date_joined, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($validRows as $row) {
                $chk->execute(array($row['matric'],$row['uid'],$row['serial']));
                if ($chk->fetch(PDO::FETCH_ASSOC)) { $commitIssues[] = array('line'=>$row['line'],'matric'=>$row['matric'],'issues'=>array('Rekod telah wujud sejak pratonton dibuat')); continue; }
                $ins->execute(array($row['name'],$row['matric'],$row['school'],$row['uid'],$row['serial'],$row['policy'],$row['joined'],$row['expiry'])); $added++;
            }
            $pdo->commit();
            auditLog($pdo,'import_csv','student',null,null,'Imported '.$added.' student records; skipped '.(count($results)-count($validRows)+count($commitIssues)).' rows');
            echo json_encode(array('success'=>true,'mode'=>'commit','message'=>'Import selesai: '.$added.' rekod ditambah; '.(count($results)-count($validRows)+count($commitIssues)).' baris perlu disemak.','added'=>$added,'total'=>count($results),'errors'=>count($results)-count($validRows)+count($commitIssues),'commitIssues'=>$commitIssues), JSON_INVALID_UTF8_SUBSTITUTE); exit;
        } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('USMSmart CSV import failed: '.$e->getMessage()); http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Import gagal. Tiada perubahan disimpan. Semak log pelayan.')); exit; }
    }

    if ($action === 'delete_student' && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? intval($input['id']) : 0;

        try {
            $find = $pdo->prepare("SELECT matric FROM students_view WHERE id = ?"); $find->execute(array($id)); $deleted = $find->fetch(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("DELETE FROM students_view WHERE id = ?");
            $stmt->execute(array($id));
            if ($stmt->rowCount()) auditLog($pdo, 'delete', 'student', $id, $deleted['matric'] ?? null, 'Deleted student record');
            echo json_encode(array('success' => true, 'message' => 'Rekod pelajar berjaya dipadam.'));
        } catch (Exception $e) {
            echo json_encode(array('success' => false, 'message' => $e->getMessage()));
        }
        exit;
    }

    if ($action === 'bulk_delete_students' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        $input = json_decode(file_get_contents('php://input'), true);
        $ids = isset($input['ids']) && is_array($input['ids']) ? array_values(array_filter(array_map('intval', $input['ids']), function($v){ return $v > 0; })) : array();

        if (empty($ids)) {
            http_response_code(400);
            echo json_encode(array('success' => false, 'message' => 'Tiada pelajar dipilih untuk dipadam.'));
            exit;
        }

        if (count($ids) > 500) {
            http_response_code(400);
            echo json_encode(array('success' => false, 'message' => 'Had maksimum 500 rekod pada satu masa.'));
            exit;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $find = $pdo->prepare("SELECT id, matric FROM students_view WHERE id IN ($placeholders)");
            $find->execute($ids);
            $foundRecords = $find->fetchAll(PDO::FETCH_ASSOC);
            $deletedMatrics = array_column($foundRecords, 'matric');

            $delStmt = $pdo->prepare("DELETE FROM students_view WHERE id IN ($placeholders)");
            $delStmt->execute($ids);
            $deletedCount = $delStmt->rowCount();

            $auditDetails = json_encode(array(
                'type' => 'bulk_delete',
                'summary' => "Padam pukal $deletedCount rekod pelajar",
                'count' => $deletedCount,
                'matrics' => array_slice($deletedMatrics, 0, 50)
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            auditLog($pdo, 'bulk_delete', 'student', null, null, $auditDetails);
            echo json_encode(array('success' => true, 'count' => $deletedCount, 'message' => "$deletedCount rekod pelajar berjaya dipadam."));
        } catch (Exception $e) {
            error_log('USMSmart bulk_delete_students failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(array('success' => false, 'message' => 'Ralat memadam rekod pelajar secara pukal.'));
        }
        exit;
    }

    if ($action === 'bulk_update_students' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        $input = json_decode(file_get_contents('php://input'), true);
        $ids = isset($input['ids']) && is_array($input['ids']) ? array_values(array_filter(array_map('intval', $input['ids']), function($v){ return $v > 0; })) : array();
        $updateType = isset($input['update_type']) ? trim((string)$input['update_type']) : '';
        $value = isset($input['value']) ? trim((string)$input['value']) : '';

        if (empty($ids)) {
            http_response_code(400);
            echo json_encode(array('success' => false, 'message' => 'Tiada pelajar dipilih untuk dikemas kini.'));
            exit;
        }
        if (count($ids) > 500) {
            http_response_code(400);
            echo json_encode(array('success' => false, 'message' => 'Had maksimum 500 rekod pada satu masa.'));
            exit;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $updatedCount = 0;
            $detailSummary = '';

            if ($updateType === 'extend_days') {
                $days = intval($value);
                if ($days <= 0 || $days > 1825) {
                    http_response_code(400);
                    echo json_encode(array('success' => false, 'message' => 'Bilangan hari lanjutan tidak sah (1–1825 hari).'));
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE students_view SET expiry_date = DATE_ADD(GREATEST(expiry_date, CURDATE()), INTERVAL ? DAY) WHERE id IN ($placeholders)");
                $params = array_merge(array($days), $ids);
                $stmt->execute($params);
                $updatedCount = $stmt->rowCount();
                $detailSummary = "Lanjutan tempoh $days hari untuk $updatedCount pelajar";
            } elseif ($updateType === 'set_expiry') {
                if (!isValidIsoDate($value)) {
                    http_response_code(400);
                    echo json_encode(array('success' => false, 'message' => 'Format tarikh luput tidak sah (YYYY-MM-DD).'));
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE students_view SET expiry_date = ? WHERE id IN ($placeholders)");
                $params = array_merge(array($value), $ids);
                $stmt->execute($params);
                $updatedCount = $stmt->rowCount();
                $detailSummary = "Tetapkan tarikh luput ke $value untuk $updatedCount pelajar";
            } elseif ($updateType === 'set_policy') {
                if (empty($value) || mb_strlen($value) > 100) {
                    http_response_code(400);
                    echo json_encode(array('success' => false, 'message' => 'Pakej polisi tidak sah.'));
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE students_view SET policy = ? WHERE id IN ($placeholders)");
                $params = array_merge(array($value), $ids);
                $stmt->execute($params);
                $updatedCount = $stmt->rowCount();
                $detailSummary = "Tukar pakej polisi ke '$value' untuk $updatedCount pelajar";
            } elseif ($updateType === 'set_school') {
                if (empty($value) || mb_strlen($value) > 255) {
                    http_response_code(400);
                    echo json_encode(array('success' => false, 'message' => 'Pusat pengajian tidak sah.'));
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE students_view SET school = ? WHERE id IN ($placeholders)");
                $params = array_merge(array($value), $ids);
                $stmt->execute($params);
                $updatedCount = $stmt->rowCount();
                $linkedPolicy = usmsmartPolicyForSchool($value);
                $policiesFilled = 0;
                if ($linkedPolicy !== '') {
                    $fill = $pdo->prepare("UPDATE students_view SET policy = ? WHERE NULLIF(TRIM(COALESCE(policy,'')),'') IS NULL AND id IN ($placeholders)");
                    $fill->execute(array_merge(array($linkedPolicy), $ids));
                    $policiesFilled = $fill->rowCount();
                }
                $detailSummary = "Tukar pusat pengajian ke '$value' untuk $updatedCount pelajar";
                if ($policiesFilled > 0) $detailSummary .= "; polisi kosong diisi untuk $policiesFilled rekod";
            } else {
                http_response_code(400);
                echo json_encode(array('success' => false, 'message' => 'Jenis kemaskini tidak sah.'));
                exit;
            }

            // Keep card history in sync with bulk renewals/policy changes without altering students_view.
            if (in_array($updateType, array('extend_days','set_expiry','set_policy'), true)) {
                try {
                    $historySelect = $pdo->prepare("SELECT id,name,matric,school,usm_uid,serial_no,policy,date_joined,expiry_date FROM students_view WHERE id IN ($placeholders)");
                    $historySelect->execute($ids);
                    foreach ($historySelect->fetchAll(PDO::FETCH_ASSOC) as $historyRecord) {
                        recordStudentCardHistory($pdo, $historyRecord['id'], $updateType === 'set_expiry' || $updateType === 'extend_days' ? 'renewed' : 'updated', $historyRecord);
                    }
                } catch (Exception $historyError) { error_log('USMSmart bulk card history write failed: '.$historyError->getMessage()); }
            }

            auditLog($pdo, 'bulk_update', 'student', null, null, json_encode(array(
                'type' => 'bulk_update',
                'summary' => $detailSummary,
                'update_type' => $updateType,
                'value' => $value,
                'count' => $updatedCount
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            echo json_encode(array('success' => true, 'count' => $updatedCount, 'message' => "$updatedCount rekod pelajar berjaya dikemas kini."));
        } catch (Exception $e) {
            error_log('USMSmart bulk_update_students failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(array('success' => false, 'message' => 'Ralat mengemas kini rekod pelajar secara pukal.'));
        }
        exit;
    }

    if ($action === 'data_quality_scan' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        try {
            $stmt = $pdo->query("SELECT id, name, matric, school, usm_uid, serial_no, policy, date_joined, expiry_date FROM students_view ORDER BY id DESC");
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $issues = array();
            $duplicateFields = array('matric' => array(), 'usm_uid' => array(), 'serial_no' => array());
            $today = date('Y-m-d');
            foreach ($records as $r) {
                foreach (array('matric' => 'matric', 'usm_uid' => 'usm_uid', 'serial_no' => 'serial_no') as $key => $field) {
                    $normalized = strtoupper(preg_replace('/\s+/', '', trim((string)(isset($r[$field]) ? $r[$field] : ''))));
                    if ($normalized !== '') {
                        if (!isset($duplicateFields[$key][$normalized])) $duplicateFields[$key][$normalized] = array();
                        $duplicateFields[$key][$normalized][] = $r;
                    }
                }
            }
            $addIssue = function($r, $type, $label, $message, $severity = 'warning', $duplicateMatches = array()) use (&$issues) {
                $issues[] = array('id'=>(int)$r['id'], 'name'=>(string)(isset($r['name']) ? $r['name'] : ''), 'matric'=>(string)(isset($r['matric']) ? $r['matric'] : ''), 'school'=>(string)(isset($r['school']) ? $r['school'] : ''), 'usmUid'=>(string)(isset($r['usm_uid']) ? $r['usm_uid'] : ''), 'serialNo'=>(string)(isset($r['serial_no']) ? $r['serial_no'] : ''), 'policy'=>(string)(isset($r['policy']) ? $r['policy'] : ''), 'dateJoined'=>(string)(isset($r['date_joined']) ? $r['date_joined'] : ''), 'expiryDate'=>(string)(isset($r['expiry_date']) ? $r['expiry_date'] : ''), 'type'=>$type, 'label'=>$label, 'message'=>$message, 'severity'=>$severity, 'duplicateMatches'=>$duplicateMatches);
            };
            foreach ($records as $r) {
                $name = trim((string)(isset($r['name']) ? $r['name'] : ''));
                $matric = trim((string)(isset($r['matric']) ? $r['matric'] : ''));
                $school = trim((string)(isset($r['school']) ? $r['school'] : ''));
                $uid = trim((string)(isset($r['usm_uid']) ? $r['usm_uid'] : ''));
                $serial = trim((string)(isset($r['serial_no']) ? $r['serial_no'] : ''));
                $policy = trim((string)(isset($r['policy']) ? $r['policy'] : ''));
                $joined = substr(trim((string)(isset($r['date_joined']) ? $r['date_joined'] : '')), 0, 10);
                $expiry = substr(trim((string)(isset($r['expiry_date']) ? $r['expiry_date'] : '')), 0, 10);
                if ($name === '' || $matric === '' || $school === '' || $policy === '') $addIssue($r, 'missing_fields', 'Maklumat tidak lengkap', 'Nama, matrik, pusat pengajian atau polisi kosong.');
                if ($uid === '' || !preg_match('/^(?:[0-9A-Fa-f]{2})+$/', preg_replace('/\s+/', '', $uid))) $addIssue($r, 'invalid_uid', 'UID tidak sah', 'UID kosong atau bukan hexadecimal berpasangan.');
                if ($serial === '' || !preg_match('/^USM-[0-9]{3,6}$/i', $serial)) $addIssue($r, 'invalid_serial', 'Nombor kad tidak sah', 'Format nombor kad yang dijangka ialah USM-001 hingga USM-999999.');
                if (!isValidIsoDate($joined) || !isValidIsoDate($expiry)) {
                    $addIssue($r, 'invalid_dates', 'Tarikh tidak sah', 'Tarikh daftar atau tarikh luput kosong atau tidak menggunakan tarikh yang sah.');
                } elseif ($expiry < $joined) {
                    $addIssue($r, 'expiry_before_joined', 'Julat tarikh tidak sah', 'Tarikh luput lebih awal daripada tarikh daftar.');
                } elseif ($expiry < $today) {
                    $addIssue($r, 'expired', 'Kad telah luput', 'Tempoh sah kad telah tamat.', 'info');
                }
            }
            $duplicateLabels = array('matric'=>array('matric_duplicate','Matrik pendua'),'usm_uid'=>array('uid_duplicate','UID pendua'),'serial_no'=>array('serial_duplicate','Nombor kad pendua'));
            foreach ($duplicateFields as $field => $groups) {
                foreach ($groups as $group) {
                    if (count($group) > 1) {
                        foreach ($group as $r) {
                            $currentId = (int)$r['id'];
                            $currentValue = trim((string)(isset($r[$field]) ? $r[$field] : ''));
                            $matches = array();
                            foreach ($group as $other) {
                                if ((int)$other['id'] === $currentId) continue;
                                $matches[] = array(
                                    'id'=>(int)$other['id'],
                                    'name'=>(string)(isset($other['name']) ? $other['name'] : ''),
                                    'matric'=>(string)(isset($other['matric']) ? $other['matric'] : ''),
                                    'school'=>(string)(isset($other['school']) ? $other['school'] : ''),
                                    'usmUid'=>(string)(isset($other['usm_uid']) ? $other['usm_uid'] : ''),
                                    'serialNo'=>(string)(isset($other['serial_no']) ? $other['serial_no'] : ''),
                                    'policy'=>(string)(isset($other['policy']) ? $other['policy'] : ''),
                                    'dateJoined'=>(string)(isset($other['date_joined']) ? $other['date_joined'] : ''),
                                    'expiryDate'=>(string)(isset($other['expiry_date']) ? $other['expiry_date'] : ''),
                                    'matchedField'=>$field,
                                    'matchedValue'=>$currentValue
                                );
                            }
                            $displayField = $field === 'matric' ? 'matrik' : ($field === 'usm_uid' ? 'UID' : 'nombor kad');
                            $addIssue($r, $duplicateLabels[$field][0], $duplicateLabels[$field][1], 'Nilai ' . $displayField . ' "' . $currentValue . '" sama dengan ' . count($matches) . ' rekod lain.', 'warning', $matches);
                        }
                    }
                }
            }
            $counts = array('total_records'=>count($records),'total_issues'=>count($issues),'affected_records'=>count(array_unique(array_map(function($i){ return $i['id']; }, $issues))),'duplicates'=>0,'incomplete'=>0,'invalid_cards'=>0,'date_issues'=>0,'expired'=>0);
            foreach ($issues as $issue) {
                if (strpos($issue['type'], '_duplicate') !== false) $counts['duplicates']++;
                if ($issue['type'] === 'missing_fields') $counts['incomplete']++;
                if (in_array($issue['type'], array('invalid_uid','invalid_serial'), true)) $counts['invalid_cards']++;
                if (in_array($issue['type'], array('invalid_dates','expiry_before_joined'), true)) $counts['date_issues']++;
                if ($issue['type'] === 'expired') $counts['expired']++;
            }
            echo json_encode(array('success'=>true,'counts'=>$counts,'issues'=>$issues,'scanned_at'=>date('Y-m-d H:i:s')), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            error_log('USMSmart data quality scan failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(array('success'=>false,'message'=>'Imbasan kualiti data tidak dapat diselesaikan. Semak konfigurasi pangkalan data.'));
        }
        exit;
    }

    if ($action === 'get_monthly_comparison') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $stmt = $pdo->query("SELECT DATE_FORMAT(date_joined, '%Y-%m') AS month_key, school, COUNT(*) AS total FROM students_view GROUP BY month_key, school ORDER BY month_key ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode(array('error' => $e->getMessage()));
        }
        exit;
    }
    
