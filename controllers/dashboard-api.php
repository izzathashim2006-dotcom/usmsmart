<?php
// Gate every dashboard API behind authentication, then render the shell.
// Included by index.php in the same scope, so it reads $action, $pdo,
// $authReady and $_SESSION directly. USM_ROOT always points at the app root.
if (!defined('USM_ROOT')) {
    // Reached directly instead of through index.php: these are includes, not endpoints.
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

    $userCount = (int)$pdo->query('SELECT COUNT(*) FROM usmsmart_users')->fetchColumn();
    $isAuthenticated = !empty($_SESSION['user_id']) && !empty($_SESSION['username']) && in_array(($_SESSION['role'] ?? ''), array('admin','viewer'), true);
    $publicActions = array('manifest', 'login', 'login_page', 'setup_admin', 'logout');
    if ($action !== '' && !in_array($action, $publicActions, true) && !$isAuthenticated) {
        http_response_code(401); header('Content-Type: application/json; charset=utf-8'); echo json_encode(array('success'=>false,'error'=>'Sesi log masuk diperlukan.','message'=>'Sesi log masuk telah tamat. Sila log masuk semula.')); exit;
    }
    if ($action !== '' && $isAuthenticated) {
        $adminOnlyActions = array('export_csv','export_audit_csv','backup_database','restore_database','import_csv','create_user','list_users','manage_user','get_audit_log_page','get_audit_log','get_audit_stats','get_sessions','backup_status','create_server_backup','download_server_backup','delete_server_backup','verify_server_backup','validate_restore_backup','bulk_delete_students','bulk_update_students','get_card_policies','assign_card_policy','manage_access_policies','manage_access_locations','get_access_policy_details','save_local_staff','delete_local_staff','create_sysweb_record','update_sysweb_record','delete_sysweb_record','reset_sysweb_password','sysweb_generate_missing_passwords','get_sysweb_password','sysweb_authorize_password_export','merge_students','bulk_restore_fields','send_ops_digest','sysweb_write_state','sysweb_write_save','sysweb_write_test','push_cards_ccadmin');
        if (in_array($action, $adminOnlyActions, true) && ($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403); header('Content-Type: application/json; charset=utf-8'); echo json_encode(array('success'=>false,'error'=>'Akses pentadbir diperlukan.','message'=>'Akses ditolak — tindakan ini memerlukan kebenaran Admin.')); exit;
        }
    }

    /* ===== Admin approval workflow: viewer changes are queued, never applied immediately ===== */
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_change_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            request_type VARCHAR(40) NOT NULL,
            payload LONGTEXT NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            requested_by BIGINT UNSIGNED NOT NULL,
            requested_by_username VARCHAR(80) NOT NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_by_username VARCHAR(80) NULL,
            review_note VARCHAR(500) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_change_status_created (status, created_at),
            INDEX idx_change_requester (requested_by, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) { error_log('USMSmart approval table initialization failed: '.$e->getMessage()); }

    // Persistent in-app notifications for request outcomes.
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_notifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(160) NOT NULL,
            message VARCHAR(500) NOT NULL,
            request_id BIGINT UNSIGNED NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notification_user_read (user_id, is_read, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) { error_log('USMSmart notifications table initialization failed: '.$e->getMessage()); }

    // Internal messaging tables; separate from student and approval data for compatibility.
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_conversations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            admin_user_id BIGINT UNSIGNED NOT NULL,
            viewer_user_id BIGINT UNSIGNED NOT NULL,
            request_id BIGINT UNSIGNED NULL,
            subject VARCHAR(160) NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_conversation_admin_updated (admin_user_id, updated_at),
            INDEX idx_conversation_viewer_updated (viewer_user_id, updated_at),
            INDEX idx_conversation_request (request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            conversation_id BIGINT UNSIGNED NOT NULL,
            sender_id BIGINT UNSIGNED NOT NULL,
            sender_username VARCHAR(80) NOT NULL,
            recipient_id BIGINT UNSIGNED NOT NULL,
            message_body TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_message_conversation (conversation_id, id),
            INDEX idx_message_recipient_read (recipient_id, is_read, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) { error_log('USMSmart messaging table initialization failed: '.$e->getMessage()); }

    if (in_array($action, array('get_notifications','mark_notifications_read'), true)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        if ($action === 'mark_notifications_read') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(array('success'=>false,'message'=>'POST diperlukan.')); exit; }
            $notificationCsrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
            if ($notificationCsrf === '' || !hash_equals((string)$_SESSION['usmsmart_csrf_token'], $notificationCsrf)) { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Token keselamatan tidak sah.')); exit; }
            try { $st = $pdo->prepare('UPDATE usmsmart_notifications SET is_read=1 WHERE user_id=? AND is_read=0'); $st->execute(array($currentUserId)); echo json_encode(array('success'=>true)); }
            catch (Exception $e) { http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Tidak dapat mengemas kini notifikasi.')); }
            exit;
        }
        try {
            $st = $pdo->prepare('SELECT id,title,message,request_id,is_read,created_at FROM usmsmart_notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 30');
            $st->execute(array($currentUserId)); $items = $st->fetchAll(PDO::FETCH_ASSOC);
            $unreadSt = $pdo->prepare('SELECT COUNT(*) FROM usmsmart_notifications WHERE user_id=? AND is_read=0'); $unreadSt->execute(array($currentUserId));
            $pendingCount = 0;
            if (($_SESSION['role'] ?? '') === 'admin') $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM usmsmart_change_requests WHERE status='pending'")->fetchColumn();
            echo json_encode(array('success'=>true,'notifications'=>$items,'unread'=>(int)$unreadSt->fetchColumn(),'pending_count'=>$pendingCount));
        } catch (Exception $e) { http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Tidak dapat memuatkan notifikasi.')); }
        exit;
    }

    // Internal messaging API. Every read/write checks conversation membership on the server.
    if (in_array($action, array('get_message_contacts','get_message_conversations','create_message_conversation','get_conversation_messages','send_message'), true)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        $messageUserId = (int)($_SESSION['user_id'] ?? 0);
        $messageRole = (string)($_SESSION['role'] ?? 'viewer');
        $isMessageAdmin = $messageRole === 'admin';
        $readBody = static function () { $decoded = json_decode(file_get_contents('php://input') ?: '', true); return is_array($decoded) ? $decoded : array(); };
        $requireMessagePost = static function () { if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(array('success'=>false,'message'=>'POST diperlukan.')); exit; } $token=(string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''); if ($token==='' || !hash_equals((string)($_SESSION['usmsmart_csrf_token'] ?? ''),$token)) { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Token keselamatan tidak sah.')); exit; } };
        try {
            if ($action === 'get_message_contacts') {
                if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); echo json_encode(array('success'=>false,'message'=>'GET diperlukan.')); exit; }
                if ($isMessageAdmin) {
                    $users=$pdo->query("SELECT id, username FROM usmsmart_users WHERE role='viewer' AND is_active=1 ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
                    $req=$pdo->query("SELECT id, requested_by AS viewer_user_id, requested_by_username, request_type, status FROM usmsmart_change_requests ORDER BY created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $users=$pdo->query("SELECT id, username FROM usmsmart_users WHERE role='admin' AND is_active=1 ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
                    $st=$pdo->prepare("SELECT id, requested_by AS viewer_user_id, requested_by_username, request_type, status FROM usmsmart_change_requests WHERE requested_by=? ORDER BY created_at DESC LIMIT 100"); $st->execute(array($messageUserId)); $req=$st->fetchAll(PDO::FETCH_ASSOC);
                }
                echo json_encode(array('success'=>true,'contacts'=>$users,'requests'=>$req)); exit;
            }
            if ($action === 'get_message_conversations') {
                if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); echo json_encode(array('success'=>false,'message'=>'GET diperlukan.')); exit; }
                $sql = $isMessageAdmin
                    ? "SELECT c.id,c.subject,c.request_id,c.updated_at,c.admin_user_id,c.viewer_user_id, v.username AS viewer_username, a.username AS admin_username, v.avatar_updated_at AS viewer_avatar, a.avatar_updated_at AS admin_avatar, (SELECT message_body FROM usmsmart_messages m WHERE m.conversation_id=c.id ORDER BY m.id DESC LIMIT 1) AS last_message, (SELECT COUNT(*) FROM usmsmart_messages m2 WHERE m2.conversation_id=c.id AND m2.recipient_id=? AND m2.is_read=0) AS unread FROM usmsmart_conversations c JOIN usmsmart_users v ON v.id=c.viewer_user_id JOIN usmsmart_users a ON a.id=c.admin_user_id WHERE c.admin_user_id=? ORDER BY c.updated_at DESC LIMIT 200"
                    : "SELECT c.id,c.subject,c.request_id,c.updated_at,c.admin_user_id,c.viewer_user_id, v.username AS viewer_username, a.username AS admin_username, v.avatar_updated_at AS viewer_avatar, a.avatar_updated_at AS admin_avatar, (SELECT message_body FROM usmsmart_messages m WHERE m.conversation_id=c.id ORDER BY m.id DESC LIMIT 1) AS last_message, (SELECT COUNT(*) FROM usmsmart_messages m2 WHERE m2.conversation_id=c.id AND m2.recipient_id=? AND m2.is_read=0) AS unread FROM usmsmart_conversations c JOIN usmsmart_users v ON v.id=c.viewer_user_id JOIN usmsmart_users a ON a.id=c.admin_user_id WHERE c.viewer_user_id=? ORDER BY c.updated_at DESC LIMIT 200";
                $st=$pdo->prepare($sql); $st->execute(array($messageUserId,$messageUserId)); echo json_encode(array('success'=>true,'conversations'=>$st->fetchAll(PDO::FETCH_ASSOC))); exit;
            }
            if ($action === 'create_message_conversation') {
                $requireMessagePost(); $b=$readBody(); $subject=trim((string)($b['subject']??'')); $body=trim((string)($b['message']??'')); $contactId=(int)($b['contact_id']??0); $requestId=(int)($b['request_id']??0);
                if ($subject==='' || mb_strlen($subject)>160) { http_response_code(422); echo json_encode(array('success'=>false,'message'=>'Subjek wajib diisi dan maksimum 160 aksara.')); exit; }
                if ($body==='' || mb_strlen($body)>4000) { http_response_code(422); echo json_encode(array('success'=>false,'message'=>'Mesej wajib diisi dan maksimum 4,000 aksara.')); exit; }
                $contact=$pdo->prepare('SELECT id,username,role,is_active FROM usmsmart_users WHERE id=? LIMIT 1'); $contact->execute(array($contactId)); $target=$contact->fetch(PDO::FETCH_ASSOC);
                if (!$target || !(int)$target['is_active'] || (int)$target['id']===$messageUserId || ($isMessageAdmin ? $target['role']!=='viewer' : $target['role']!=='admin')) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Penerima mesej tidak sah.')); exit; }
                $adminId=$isMessageAdmin?$messageUserId:$contactId; $viewerId=$isMessageAdmin?$contactId:$messageUserId;
                if ($requestId>0) { $rq=$pdo->prepare('SELECT id,requested_by FROM usmsmart_change_requests WHERE id=? LIMIT 1'); $rq->execute(array($requestId)); $requestRow=$rq->fetch(PDO::FETCH_ASSOC); if (!$requestRow || (int)$requestRow['requested_by']!==$viewerId) { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Permintaan ini bukan milik pengguna dalam perbualan.')); exit; } } else { $requestId=0; }
                $pdo->beginTransaction();
                $st=$pdo->prepare('INSERT INTO usmsmart_conversations (admin_user_id,viewer_user_id,request_id,subject,created_by) VALUES (?,?,?,?,?)'); $st->execute(array($adminId,$viewerId,$requestId?:null,$subject,$messageUserId)); $conversationId=(int)$pdo->lastInsertId();
                $ins=$pdo->prepare('INSERT INTO usmsmart_messages (conversation_id,sender_id,sender_username,recipient_id,message_body) VALUES (?,?,?,?,?)'); $ins->execute(array($conversationId,$messageUserId,(string)$_SESSION['username'],$contactId,$body));
                $notif=$pdo->prepare('INSERT INTO usmsmart_notifications (user_id,title,message,request_id) VALUES (?,?,?,?)'); $notif->execute(array($contactId,'New message from '.(string)$_SESSION['username'],substr($subject.': '.$body,0,500),$requestId?:null));
                $pdo->commit(); echo json_encode(array('success'=>true,'conversation_id'=>$conversationId,'message'=>'Conversation started.')); exit;
            }
            if ($action === 'get_conversation_messages') {
                if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); echo json_encode(array('success'=>false,'message'=>'GET diperlukan.')); exit; }
                $conversationId=(int)($_GET['id']??0); $where=$isMessageAdmin?'admin_user_id':'viewer_user_id'; $check=$pdo->prepare("SELECT id,admin_user_id,viewer_user_id,request_id,subject FROM usmsmart_conversations WHERE id=? AND {$where}=? LIMIT 1"); $check->execute(array($conversationId,$messageUserId)); $conversation=$check->fetch(PDO::FETCH_ASSOC);
                if (!$conversation) { http_response_code(404); echo json_encode(array('success'=>false,'message'=>'Perbualan tidak ditemui atau akses ditolak.')); exit; }
                $mark=$pdo->prepare('UPDATE usmsmart_messages SET is_read=1 WHERE conversation_id=? AND recipient_id=? AND is_read=0'); $mark->execute(array($conversationId,$messageUserId));
                $st=$pdo->prepare('SELECT m.id,m.sender_id,m.sender_username,m.message_body,m.is_read,m.created_at,u.avatar_updated_at AS sender_avatar FROM usmsmart_messages m LEFT JOIN usmsmart_users u ON u.id=m.sender_id WHERE m.conversation_id=? ORDER BY m.id ASC LIMIT 500'); $st->execute(array($conversationId)); echo json_encode(array('success'=>true,'conversation'=>$conversation,'messages'=>$st->fetchAll(PDO::FETCH_ASSOC))); exit;
            }
            if ($action === 'send_message') {
                $requireMessagePost(); $b=$readBody(); $conversationId=(int)($b['conversation_id']??0); $body=trim((string)($b['message']??''));
                if ($body==='' || mb_strlen($body)>4000) { http_response_code(422); echo json_encode(array('success'=>false,'message'=>'Mesej wajib diisi dan maksimum 4,000 aksara.')); exit; }
                $where=$isMessageAdmin?'admin_user_id':'viewer_user_id'; $check=$pdo->prepare("SELECT id,admin_user_id,viewer_user_id,request_id,subject FROM usmsmart_conversations WHERE id=? AND {$where}=? LIMIT 1"); $check->execute(array($conversationId,$messageUserId)); $conversation=$check->fetch(PDO::FETCH_ASSOC);
                if (!$conversation) { http_response_code(404); echo json_encode(array('success'=>false,'message'=>'Perbualan tidak ditemui atau akses ditolak.')); exit; }
                $recipientId=$isMessageAdmin?(int)$conversation['viewer_user_id']:(int)$conversation['admin_user_id'];
                $ins=$pdo->prepare('INSERT INTO usmsmart_messages (conversation_id,sender_id,sender_username,recipient_id,message_body) VALUES (?,?,?,?,?)'); $ins->execute(array($conversationId,$messageUserId,(string)$_SESSION['username'],$recipientId,$body));
                $pdo->prepare('UPDATE usmsmart_conversations SET updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute(array($conversationId));
                $notif=$pdo->prepare('INSERT INTO usmsmart_notifications (user_id,title,message,request_id) VALUES (?,?,?,?)'); $notif->execute(array($recipientId,'New message from '.(string)$_SESSION['username'],substr((string)$conversation['subject'].': '.$body,0,500),$conversation['request_id']?:null));
                echo json_encode(array('success'=>true,'message'=>'Message sent.')); exit;
            }
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('USMSmart messaging API failed: '.$e->getMessage()); http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Messaging action failed. Please try again.')); exit; }
    }

    if (in_array($action, array('get_change_requests','review_change_request'), true)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        $isAdmin = (($_SESSION['role'] ?? '') === 'admin');
        if ($action === 'get_change_requests') {
            try {
                if ($isAdmin) {
                    $stmt = $pdo->query("SELECT id, request_type, payload, status, requested_by_username, reviewed_by_username, review_note, created_at, reviewed_at FROM usmsmart_change_requests ORDER BY CASE status WHEN 'pending' THEN 0 ELSE 1 END, created_at DESC LIMIT 200");
                } else {
                    $stmt = $pdo->prepare("SELECT id, request_type, payload, status, requested_by_username, reviewed_by_username, review_note, created_at, reviewed_at FROM usmsmart_change_requests WHERE requested_by = ? ORDER BY created_at DESC LIMIT 100");
                    $stmt->execute(array((int)$_SESSION['user_id']));
                }
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as &$row) { $decoded = json_decode($row['payload'], true); $row['summary'] = is_array($decoded) ? (isset($decoded['_summary']) ? $decoded['_summary'] : ('Permintaan '.$row['request_type'])) : 'Permintaan perubahan'; if (is_array($decoded)) unset($decoded['_summary']); $row['details'] = is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) : ''; unset($row['payload']); }
                unset($row);
                echo json_encode(array('success'=>true,'requests'=>$rows));
            } catch (Exception $e) { http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Tidak dapat memuatkan permintaan.')); }
            exit;
        }
        if ($action === 'edit_rejected_request') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(array('success'=>false,'message'=>'POST diperlukan.')); exit; }
            $csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
            if ($csrf === '' || !hash_equals((string)$_SESSION['usmsmart_csrf_token'], $csrf)) { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Token keselamatan tidak sah.')); exit; }
            if ($isAdmin) { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Fungsi ini hanya untuk Viewer.')); exit; }
            $body = json_decode(file_get_contents('php://input'), true) ?: array();
            $requestId = (int)($body['id'] ?? 0); $edited = $body['payload'] ?? null;
            if ($requestId < 1 || !is_array($edited)) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Data permintaan tidak sah.')); exit; }
            try {
                $q = $pdo->prepare('SELECT id, request_type, payload, status FROM usmsmart_change_requests WHERE id = ? AND requested_by = ? LIMIT 1');
                $q->execute(array($requestId, (int)$_SESSION['user_id'])); $existing = $q->fetch(PDO::FETCH_ASSOC);
                if (!$existing || $existing['status'] !== 'rejected') { http_response_code(409); echo json_encode(array('success'=>false,'message'=>'Hanya permintaan ditolak milik anda boleh diedit dan dihantar semula.')); exit; }
                $oldPayload = json_decode($existing['payload'], true);
                if (!is_array($oldPayload)) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Payload asal tidak sah.')); exit; }
                if (isset($edited['_summary'])) unset($edited['_summary']);
                $edited['_summary'] = isset($oldPayload['_summary']) ? $oldPayload['_summary'] : ('Permintaan '.$existing['request_type']);
                $upd = $pdo->prepare("UPDATE usmsmart_change_requests SET payload = ?, status = 'pending', reviewed_by = NULL, reviewed_by_username = NULL, review_note = NULL, reviewed_at = NULL, created_at = CURRENT_TIMESTAMP WHERE id = ? AND requested_by = ? AND status = 'rejected'");
                $upd->execute(array(json_encode($edited, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $requestId, (int)$_SESSION['user_id']));
                if ($upd->rowCount() !== 1) { http_response_code(409); echo json_encode(array('success'=>false,'message'=>'Permintaan berubah semasa disimpan. Muat semula dan cuba lagi.')); exit; }
                echo json_encode(array('success'=>true,'message'=>'Permintaan dikemas kini dan dihantar semula kepada Admin untuk kelulusan.'));
            } catch (Exception $e) { error_log('USMSmart resubmit request failed: '.$e->getMessage()); http_response_code(500); echo json_encode(array('success'=>false,'message'=>'Tidak dapat menghantar semula permintaan.')); }
            exit;
        }
        if (!$isAdmin) { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Hanya Admin boleh meluluskan permintaan.')); exit; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(array('success'=>false,'message'=>'POST diperlukan.')); exit; }
        $csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($csrf === '' || !hash_equals((string)$_SESSION['usmsmart_csrf_token'], $csrf)) { http_response_code(403); echo json_encode(array('success'=>false,'message'=>'Token keselamatan tidak sah.')); exit; }
        $body = json_decode(file_get_contents('php://input'), true) ?: array();
        $requestId = (int)($body['id'] ?? 0); $decision = (string)($body['decision'] ?? ''); $note = trim((string)($body['note'] ?? ''));
        if ($requestId < 1 || !in_array($decision, array('approve','reject'), true)) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Permintaan tidak sah.')); exit; }
        if ($decision === 'reject' && $note === '') { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Sila berikan sebab penolakan supaya pengguna tahu apa yang perlu diperbaiki.')); exit; }
        if (strlen($note) > 500) { http_response_code(400); echo json_encode(array('success'=>false,'message'=>'Sebab penolakan tidak boleh melebihi 500 aksara.')); exit; }
        try {
            // MySQL ALTER TABLE can implicitly commit; prepare the optional UDA type column before locking a request.
            if ($decision === 'approve') {
                $preflight = $pdo->prepare("SELECT request_type FROM usmsmart_change_requests WHERE id = ?");
                $preflight->execute(array($requestId));
                if ((string)$preflight->fetchColumn() === 'dump_uda') usmsmartEnsureUdaCardholderTypeColumn($pdo);
            }
            $pdo->beginTransaction();
            $q = $pdo->prepare("SELECT * FROM usmsmart_change_requests WHERE id = ? FOR UPDATE"); $q->execute(array($requestId)); $req = $q->fetch(PDO::FETCH_ASSOC);
            if (!$req || $req['status'] !== 'pending') throw new Exception('Permintaan ini telah diproses atau tidak ditemui.');
            if ($decision === 'approve') {
                $data = json_decode($req['payload'], true); if (!is_array($data)) throw new Exception('Data permintaan tidak sah.');
                $type = $req['request_type'];
                if ($type === 'save_student') {
                    $id=(int)($data['id']??0); $name=trim((string)($data['name']??'')); $matric=trim((string)($data['matric']??'')); $school=trim((string)($data['school']??'')); $uid=strtoupper(preg_replace('/\s+/', '', trim((string)($data['usmUid']??'')))); $serial=strtoupper(trim((string)($data['serialNo']??''))); $policy=usmsmartFillPolicyFromSchool(trim((string)($data['policy']??'')),$school); $joined=trim((string)($data['dateJoined']??date('Y-m-d'))); $expiry=trim((string)($data['expiryDate']??date('Y-m-d',strtotime('+1 year'))));
                    if ($name==='' || $matric==='' || !isValidIsoDate($joined) || !isValidIsoDate($expiry) || $expiry<$joined) throw new Exception('Data pelajar tidak sah; semak nama, matrik dan tarikh.');
                    $cardError=validateCardFields($uid,$serial); if ($cardError!=='') throw new Exception($cardError);
                    /* A queued viewer request can honestly arrive without a number; the record that
                       lands in the table must still carry one, so it is minted at approval. */
                    if ($serial === '') $serial = usmsmartAllocateCardSerial($pdo, $id);
                    if ($serial === '') throw new Exception('Nombor siri kad automatik tidak dapat dijana. Isikan nombor siri, kemudian hantar semula.');
                                        $dup=$pdo->prepare("SELECT id FROM students_view WHERE (UPPER(matric)=UPPER(?) OR UPPER(usm_uid)=UPPER(?) OR UPPER(serial_no)=UPPER(?)) AND id <> ? LIMIT 1"); $dup->execute(array($matric,$uid,$serial,$id)); if($dup->fetch()) throw new Exception('Matrik, UID atau nombor kad sudah digunakan oleh rekod lain.');
                    if($id>0){ $oldQ=$pdo->prepare('SELECT matric FROM students_view WHERE id=?');$oldQ->execute(array($id));$old=$oldQ->fetch(PDO::FETCH_ASSOC);if(!$old)throw new Exception('Rekod pelajar telah dipadam atau tidak ditemui.');$st=$pdo->prepare('UPDATE students_view SET name=?,matric=?,school=?,usm_uid=?,serial_no=?,policy=?,date_joined=?,expiry_date=? WHERE id=?');$st->execute(array($name,$matric,$school,$uid,$serial,$policy,$joined,$expiry,$id));}
                    else {$st=$pdo->prepare('INSERT INTO students_view (name,matric,school,usm_uid,serial_no,policy,date_joined,expiry_date) VALUES (?,?,?,?,?,?,?,?)');$st->execute(array($name,$matric,$school,$uid,$serial,$policy,$joined,$expiry));$id=(int)$pdo->lastInsertId();}
                } elseif ($type === 'delete_student') {
                    $id=(int)($data['id']??0);$st=$pdo->prepare('SELECT matric FROM students_view WHERE id=?');$st->execute(array($id));$row=$st->fetch(PDO::FETCH_ASSOC);if(!$row)throw new Exception('Rekod pelajar sudah tiada.');$del=$pdo->prepare('DELETE FROM students_view WHERE id=?');$del->execute(array($id));
                } elseif ($type === 'dump_uda') {
                    $records=$data['records']??array();if(!is_array($records)||!count($records))throw new Exception('Tiada rekod UDA untuk diproses.');usmsmartEnsureUdaCardholderTypeColumn($pdo);$st=$pdo->prepare("INSERT INTO uda_system_records (matric, cardholder_type, converted_uid, serial_no, policy, status) VALUES (?, ?, ?, ?, ?, 'READY_TO_ISSUE') ON DUPLICATE KEY UPDATE cardholder_type=VALUES(cardholder_type), converted_uid=VALUES(converted_uid), serial_no=VALUES(serial_no), policy=VALUES(policy)");$names=array();$ccadminCards=array();
                    foreach($records as $r){$mat=trim((string)($r['matric']??''));$u=strtoupper(preg_replace('/\s+/', '', (string)($r['usmUid']??'')));$sn=strtoupper(trim((string)($r['serialNo']??'')));$pol=trim((string)($r['policy']??''));$err=validateCardFields($u,$sn);if($mat===''||$pol===''||$err!=='')throw new Exception('Data UDA tidak sah untuk pengenal '.$mat.'. '.$err);if(!preg_match('/^(?:[0-9A-F]{2})+$/',$u))throw new Exception('UID hexadecimal tidak sah untuk '.$mat.'.');$policyMatch=usmsmartFindExistingCardPolicy($pdo,$mat,$u,$sn,strtolower((string)($r['cardholderType']??'student'))==='staff'?'staff':'student');$existingPolicy=$policyMatch?trim((string)($policyMatch['policy']??'')):'';if($existingPolicy==='')throw new Exception('Rekod '.$mat.' disekat: tiada polisi palang yang ditetapkan.');if(!hash_equals($existingPolicy,$pol))throw new Exception('Rekod '.$mat.' disekat: polisi palang tidak sepadan dengan tugasan pemegang kad.');$pairs=str_split($u,2);$converted=implode('',array_reverse($pairs));$cardholderType=strtolower((string)($r['cardholderType']??'student'))==='staff'?'staff':'student';$st->execute(array($mat,$cardholderType,$converted,$sn,$pol));$names[]=array('name'=>(string)($r['name']??''),'matric'=>$mat,'cardholder_type'=>$cardholderType);$ccadminCards[]=array('identifier'=>$mat,'source_uid'=>$u,'converted_uid'=>$converted,'serial'=>$sn,'policy'=>$pol,'expiry'=>'');}
                    $ss=$pdo->prepare('INSERT INTO uda_transport_sessions (total_records, details) VALUES (?, ?)');$ss->execute(array(count($records),json_encode($names)));
                } elseif ($type === 'soft_delete_session') {
                    $sid=(int)($data['id']??0);$st=$pdo->prepare('SELECT details,is_deleted FROM uda_transport_sessions WHERE id=? FOR UPDATE');$st->execute(array($sid));$sess=$st->fetch(PDO::FETCH_ASSOC);if(!$sess || (int)($sess['is_deleted']??0)===1)throw new Exception('Sesi tidak ditemui atau telah dipadam.');$names=json_decode((string)$sess['details'],true);if(is_array($names)){$del=$pdo->prepare('DELETE FROM uda_system_records WHERE matric=?');foreach($names as $person)if(isset($person['matric']))$del->execute(array($person['matric']));}$up=$pdo->prepare('UPDATE uda_transport_sessions SET is_deleted=1 WHERE id=?');$up->execute(array($sid));
                } else { throw new Exception('Jenis permintaan tidak disokong untuk kelulusan.'); }
            }
            $newStatus = $decision === 'approve' ? 'approved' : 'rejected';
            $upd=$pdo->prepare('UPDATE usmsmart_change_requests SET status=?, reviewed_by=?, reviewed_by_username=?, review_note=?, reviewed_at=NOW() WHERE id=?');$upd->execute(array($newStatus,(int)$_SESSION['user_id'],(string)$_SESSION['username'],$note,$requestId));
            $notificationTitle = $newStatus === 'approved' ? 'Change request approved' : 'Change request rejected';
            $notificationMessage = $newStatus === 'approved' ? ('Your request #'.$requestId.' was approved and the change was applied.') : ('Your request #'.$requestId.' was rejected. Reason: '.$note);
            $notify = $pdo->prepare('INSERT INTO usmsmart_notifications (user_id,title,message,request_id) VALUES (?,?,?,?)');
            $notify->execute(array((int)$req['requested_by'],$notificationTitle,substr($notificationMessage,0,500),$requestId));
            $pdo->commit();
            auditLog($pdo,'change_request_'.$newStatus,'change_request',$requestId,null,'Request #'.$requestId.' '.$newStatus.' by admin');
            // A viewer's queued export is written to the card database after the
            // approval commits, for the same reason as the direct export: the
            // MySQL change must survive a card-database outage.
            $ccadmin = null;
            if ($decision === 'approve' && isset($type) && $type === 'dump_uda' && !empty($ccadminCards)) {
                $ccadmin = usmsmartSyswebPushCards($ccadminCards);
                if ($ccadmin['status'] !== 'not_configured') {
                    auditLog($pdo,'uda_ccadmin_push','change_request',$requestId,null,'ccadmin '.$ccadmin['status'].': '.(int)$ccadmin['written'].'/'.(int)$ccadmin['total']);
                }
            }
            $approveMessage = 'Permintaan diluluskan dan perubahan telah digunakan.';
            if ($ccadmin && $ccadmin['status'] === 'failed') $approveMessage = 'Permintaan diluluskan, tetapi penulisan ke ccadmin gagal dan telah dibatalkan.';
            echo json_encode(array('success'=>true,'message'=>$decision==='approve'?$approveMessage:'Permintaan ditolak.'));
        } catch (Exception $e) { if($pdo->inTransaction())$pdo->rollBack(); http_response_code(400); echo json_encode(array('success'=>false,'message'=>$e->getMessage())); }
        exit;
    }

    /* ===== NEW: My Profile endpoints (authenticated users) ===== */
    if ($isAuthenticated && in_array($action, array('get_my_profile', 'update_my_profile', 'change_my_password', 'my_avatar', 'update_my_avatar', 'remove_my_avatar', 'link_google_account', 'unlink_google_account'), true)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');

        // CSRF required for all state-changing profile actions.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $profileCsrf = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string)$_SERVER['HTTP_X_CSRF_TOKEN'] : '';
            if ($profileCsrf === '' || !hash_equals((string)$_SESSION['usmsmart_csrf_token'], $profileCsrf)) {
                http_response_code(403);
                echo json_encode(array('success'=>false,'message'=>'Token keselamatan tidak sah. Muat semula halaman dan cuba lagi.'));
                exit;
            }
        }

        $profileUserId = (int)$_SESSION['user_id'];

        // ---- GET: fetch current profile ----
        if ($action === 'get_my_profile') {
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                echo json_encode(array('success'=>false,'message'=>'GET diperlukan.'));
                exit;
            }
            try {
                $stmt = $pdo->prepare(
                    'SELECT username, role, is_active, display_name, email, gmail, google_id, created_at, last_login_at, avatar_updated_at
                     FROM usmsmart_users WHERE id = ? LIMIT 1'
                );
                $stmt->execute(array($profileUserId));
                $profile = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$profile) {
                    http_response_code(404);
                    echo json_encode(array('success'=>false,'message'=>'Akaun tidak ditemui.'));
                    exit;
                }
                echo json_encode(array('success'=>true,'profile'=>$profile));
            } catch (Throwable $e) {
                error_log('USMSmart get_my_profile failed: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(array('success'=>false,'message'=>'Profil tidak dapat dimuatkan.'));
            }
            exit;
        }

        // ---- GET: serve a profile picture to anyone who is signed in ----
        if ($action === 'my_avatar') {
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                echo json_encode(array('success'=>false,'message'=>'GET diperlukan.'));
                exit;
            }
            $avatarId = (int)($_GET['id'] ?? 0);
            $avatarFile = usmsmartAvatarFile($avatarId);
            if ($avatarId <= 0 || $avatarFile === null || !is_file($avatarFile)) {
                header('Content-Type: text/plain; charset=utf-8');
                http_response_code(404);
                echo 'No picture stored.';
                exit;
            }
            // This file begins with a UTF-8 BOM, so every response would otherwise start with those
            // three bytes. JSON survives it and browsers ignore it, but a JPEG must begin with its
            // own magic number, so drop everything buffered - including the BOM - before the body.
            while (ob_get_level() > 0) { ob_end_clean(); }
            // The version is already in the URL, so the body can be cached hard and privately.
            $avatarStamp = gmdate('D, d M Y H:i:s', (int)@filemtime($avatarFile)) . ' GMT';
            $avatarEtag = '"' . dechex((int)@filesize($avatarFile)) . '-' . (int)@filemtime($avatarFile) . '"';
            header('Cache-Control: private, max-age=31536000, immutable');
            header('Content-Type: image/jpeg');
            header('X-Content-Type-Options: nosniff');
            header('Last-Modified: ' . $avatarStamp);
            header('ETag: ' . $avatarEtag);
            if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string)$_SERVER['HTTP_IF_NONE_MATCH']) === $avatarEtag) {
                http_response_code(304);
                exit;
            }
            header('Content-Length: ' . (string)(int)@filesize($avatarFile));
            readfile($avatarFile);
            exit;
        }

        // ---- POST required for remaining actions ----
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(array('success'=>false,'message'=>'POST diperlukan untuk tindakan ini.'));
            exit;
        }

        $rawBody = file_get_contents('php://input');
        $input = json_decode($rawBody ?: '', true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        // ---- Update display_name / email / gmail ----
        if ($action === 'update_my_profile') {
            $displayName = trim((string)($input['display_name'] ?? ''));
            $email       = trim((string)($input['email'] ?? ''));
            $gmail       = strtolower(trim((string)($input['gmail'] ?? '')));

            if (mb_strlen($displayName) > 100) {
                http_response_code(422);
                echo json_encode(array('success'=>false,'message'=>'Nama paparan terlalu panjang (maksimum 100 aksara).'));
                exit;
            }
            if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190)) {
                http_response_code(422);
                echo json_encode(array('success'=>false,'message'=>'Sila masukkan alamat e-mel yang sah.'));
                exit;
            }
            if ($gmail !== '') {
                if (!filter_var($gmail, FILTER_VALIDATE_EMAIL) || strlen($gmail) > 190) {
                    http_response_code(422);
                    echo json_encode(array('success'=>false,'message'=>'Sila masukkan alamat Gmail yang sah.'));
                    exit;
                }
                if (!preg_match('/@gmail\.com$/i', $gmail) && !preg_match('/@(student\.)?usm\.my$/i', $gmail)) {
                    http_response_code(422);
                    echo json_encode(array('success'=>false,'message'=>'Akaun mestilah alamat Gmail (@gmail.com atau @usm.my).'));
                    exit;
                }
            }

            try {
                $stmt = $pdo->prepare(
                    'UPDATE usmsmart_users SET display_name = ?, email = ?, gmail = ? WHERE id = ?'
                );
                $stmt->execute(array(
                    $displayName !== '' ? $displayName : null,
                    $email !== '' ? $email : null,
                    $gmail !== '' ? $gmail : null,
                    $profileUserId
                ));
                auditLog($pdo, 'update_profile', 'user', $profileUserId, null, 'Updated display name / email / gmail');
                echo json_encode(array('success'=>true,'message'=>'Profil dan akaun Gmail berjaya dikemas kini.'));
            } catch (Throwable $e) {
                error_log('USMSmart update_my_profile failed: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(array('success'=>false,'message'=>'Profil tidak dapat dikemas kini.'));
            }
            exit;
        }

                // ---- Link Google Account via verified Google ID token (One Tap / chooser) ----
        if ($action === 'link_google_account') {
            $credential = trim((string)($input['credential'] ?? ''));
            if ($usmGoogleClientId === '') {
                http_response_code(503);
                echo json_encode(array('success' => false, 'message' => 'Pautan Google belum diaktifkan oleh pentadbir. Gunakan ruangan Gmail manual di bawah sebagai alternatif.'));
                exit;
            }
            if ($credential === '') {
                http_response_code(422);
                echo json_encode(array('success' => false, 'message' => 'Kredensial Google diperlukan. Sila pilih akaun Google anda melalui butang Google.'));
                exit;
            }
            $payload = usmGoogleVerifyIdToken($credential, $usmGoogleClientId);
            if (!$payload) {
                http_response_code(401);
                echo json_encode(array('success' => false, 'message' => 'Token Google tidak sah atau telah tamat tempoh. Sila pilih akaun Google anda semula.'));
                exit;
            }
            // Email and Google id come only from the verified token, never from the request body.
            $email = strtolower(trim((string)$payload['email']));
            $googleId = trim((string)$payload['sub']);

            // Admin accounts may only be linked to verified USM email domains,
            // matching the Google sign-in gate in controllers/auth.php.
            if (($_SESSION['role'] ?? '') === 'admin' && !usmGoogleEmailIsUsmDomain($email, $usmGoogleAdminDomains)) {
                http_response_code(403);
                echo json_encode(array('success' => false, 'message' => 'Akaun Admin hanya boleh dipautkan dengan e-mel USM (@' . implode(', @', $usmGoogleAdminDomains) . '). Akaun Google ini bukan e-mel USM.'));
                exit;
            }

            try {
                // Check if this Google account is already linked to another user
                $chk = $pdo->prepare('SELECT id, username FROM usmsmart_users WHERE (LOWER(gmail) = LOWER(?) OR (google_id = ? AND google_id IS NOT NULL AND google_id != "")) AND id != ? LIMIT 1');
                $chk->execute(array($email, $googleId, $profileUserId));
                $existing = $chk->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    http_response_code(409);
                    echo json_encode(array('success' => false, 'message' => 'Akaun Google ini (' . $email . ') telah dipautkan ke akaun lain (' . $existing['username'] . ').'));
                    exit;
                }

                $stmt = $pdo->prepare('UPDATE usmsmart_users SET gmail = ?, google_id = ? WHERE id = ?');
                $stmt->execute(array($email, $googleId, $profileUserId));
                auditLog($pdo, 'link_google_account', 'user', $profileUserId, null, 'Linked Google account ' . $email);

                echo json_encode(array('success' => true, 'gmail' => $email, 'message' => 'Akaun Google (' . $email . ') berjaya dipautkan!'));
            } catch (Throwable $e) {
                error_log('USMSmart link_google_account failed: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(array('success' => false, 'message' => 'Gagal memautkan akaun Google. Sila cuba lagi.'));
            }
            exit;
        }

        // ---- Unlink Google Account ----
        if ($action === 'unlink_google_account') {
            try {
                $stmt = $pdo->prepare('UPDATE usmsmart_users SET gmail = NULL, google_id = NULL WHERE id = ?');
                $stmt->execute(array($profileUserId));
                auditLog($pdo, 'unlink_google_account', 'user', $profileUserId, null, 'Unlinked Google account');
                echo json_encode(array('success' => true, 'message' => 'Pautan akaun Google berjaya diputuskan.'));
            } catch (Throwable $e) {
                error_log('USMSmart unlink_google_account failed: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(array('success' => false, 'message' => 'Gagal memutuskan pautan akaun Google.'));
            }
            exit;
        }

        // ---- POST: replace or remove the caller's own picture ----
        if ($action === 'update_my_avatar') {
            $decoded = usmsmartDecodeAvatarDataUrl($input['image'] ?? '');
            if (!$decoded['ok']) {
                http_response_code(422);
                echo json_encode(array('success'=>false,'message'=>$decoded['message']));
                exit;
            }
            $avatarFile = usmsmartAvatarFile($profileUserId);
            if ($avatarFile === null) {
                error_log('USMSmart avatar directory is unavailable: ' . (getenv('USMSMART_AVATAR_DIR') ?: 'C:/xampp/private/usmsmart_avatars'));
                http_response_code(500);
                echo json_encode(array('success'=>false,'message'=>'Folder gambar profil tidak tersedia di pelayan.'));
                exit;
            }
            // Write beside the target, then rename: a half-written file must never be servable.
            $avatarTemp = $avatarFile . '.part';
            if (@file_put_contents($avatarTemp, $decoded['bytes'], LOCK_EX) === false) {
                http_response_code(500);
                echo json_encode(array('success'=>false,'message'=>'Gambar tidak dapat disimpan.'));
                exit;
            }
            if (!@rename($avatarTemp, $avatarFile)) {
                @unlink($avatarTemp);
                http_response_code(500);
                echo json_encode(array('success'=>false,'message'=>'Gambar tidak dapat disimpan.'));
                exit;
            }
            // Written from PHP rather than NOW() so the value the client is told is the value stored.
            $avatarStamp = date('Y-m-d H:i:s');
            try {
                $stmt = $pdo->prepare('UPDATE usmsmart_users SET avatar_updated_at = ? WHERE id = ?');
                $stmt->execute(array($avatarStamp, $profileUserId));
                $_SESSION['avatar_updated_at'] = $avatarStamp;
                auditLog($pdo, 'update_profile_picture', 'user', $profileUserId, null, 'Profile picture replaced');
                echo json_encode(array('success'=>true,'message'=>'Gambar profil dikemas kini.','avatar_updated_at'=>$avatarStamp));
            } catch (Throwable $e) {
                error_log('USMSmart update_my_avatar failed: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(array('success'=>false,'message'=>'Gambar profil tidak dapat disimpan dalam pangkalan data.'));
            }
            exit;
        }

        if ($action === 'remove_my_avatar') {
            $avatarFile = usmsmartAvatarFile($profileUserId);
            try {
                $stmt = $pdo->prepare('UPDATE usmsmart_users SET avatar_updated_at = NULL WHERE id = ?');
                $stmt->execute(array($profileUserId));
                $_SESSION['avatar_updated_at'] = '';
                if ($avatarFile !== null && is_file($avatarFile)) @unlink($avatarFile);
                auditLog($pdo, 'remove_profile_picture', 'user', $profileUserId, null, 'Profile picture removed');
                echo json_encode(array('success'=>true,'message'=>'Gambar profil dibuang.','avatar_updated_at'=>''));
            } catch (Throwable $e) {
                error_log('USMSmart remove_my_avatar failed: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(array('success'=>false,'message'=>'Gambar profil tidak dapat dibuang.'));
            }
            exit;
        }

        // ---- Change password ----
        if ($action === 'change_my_password') {
            $current = (string)($input['current_password'] ?? '');
            $new     = (string)($input['new_password'] ?? '');
            $confirm = (string)($input['confirm_password'] ?? '');

            if (strlen($new) < 12 || strlen($new) > 72) {
                http_response_code(422);
                echo json_encode(array('success'=>false,'message'=>'Kata laluan baharu mesti 12–72 aksara.'));
                exit;
            }
            if ($new !== $confirm) {
                http_response_code(422);
                echo json_encode(array('success'=>false,'message'=>'Kata laluan baharu tidak sepadan.'));
                exit;
            }

            try {
                $stmt = $pdo->prepare(
                    'SELECT id, password_hash FROM usmsmart_users WHERE id = ? LIMIT 1'
                );
                $stmt->execute(array($profileUserId));
                $account = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$account || !password_verify($current, $account['password_hash'])) {
                    http_response_code(403);
                    echo json_encode(array('success'=>false,'message'=>'Kata laluan semasa tidak betul.'));
                    exit;
                }

                $hash = password_hash($new, PASSWORD_DEFAULT);
                $upd = $pdo->prepare(
                    'UPDATE usmsmart_users SET password_hash = ?, auth_version = auth_version + 1 WHERE id = ?'
                );
                $upd->execute(array($hash, $profileUserId));

                auditLog($pdo, 'change_my_password', 'user', $profileUserId, null, 'User changed own password');

                // Invalidate this session to force re-login on all devices.
                $_SESSION = array();
                session_regenerate_id(true);
                $_SESSION['usmsmart_csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['last_activity'] = time();

                echo json_encode(array(
                    'success' => true,
                    'message' => 'Kata laluan berjaya ditukar. Sila log masuk semula.',
                    'force_logout' => true
                ));
            } catch (Throwable $e) {
                error_log('USMSmart change_my_password failed: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(array('success'=>false,'message'=>'Kata laluan tidak dapat ditukar.'));
            }
            exit;
        }
    }
    /* ===== END My Profile ===== */

    /* Public entry pages. "" is the marketing homepage; "login_page" is the sign-in form.
       Signed-in users never see either: they go straight to the dashboard, tab token intact. */
    if ($action === 'login_page' && $isAuthenticated) {
        header('Location: index.php' . ($usmTabTokenValid ? '?usm_tab=' . rawurlencode($usmTabToken) : ''));
        exit;
    }
    if (($action === '' || $action === 'login_page') && !$isAuthenticated) {
        $setupMode = ($userCount === 0);
        if ($action === 'login_page') {
            $csrfForAuth = htmlspecialchars((string)$_SESSION['usmsmart_csrf_token'], ENT_QUOTES, 'UTF-8');
            // Passkeys need a relying-party domain and a secure context (HTTPS, or localhost for dev).
            // Anything else gets the password form alone instead of a button that can only fail.
            $usmPasskeyAvailable = usmsmartWebAuthnIsSecure();
            // Accounts, presence and today's sign-ins, rendered under the form and refreshed by a
            // small poll on the login page.
            $usmLoginStats = usmsmartLoginStats($pdo);

            require USM_ROOT . '/views/login.php';
            exit;
        }
        // The homepage shows the same live counters as the sign-in page, plus the guide clip
        // when one is installed. Counts only, never a list; the clip uses sample data.
        $usmHomeStats = usmsmartLoginStats($pdo);
        require USM_ROOT . '/views/home.php';
        exit;
    }
