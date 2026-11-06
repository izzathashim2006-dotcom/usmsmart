<?php
// Shared helper library used by every controller and view.
// Included by index.php in the same scope, so it reads $action, $pdo,
// $authReady and $_SESSION directly. USM_ROOT always points at the app root.
if (!defined('USM_ROOT')) {
    // Reached directly instead of through index.php: these are includes, not endpoints.
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('USM_ROOT', dirname(__DIR__));
}

function isValidIsoDate($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    $d = DateTime::createFromFormat('!Y-m-d', $value);
    return $d && $d->format('Y-m-d') === $value;
}

/* ---- Card serial numbers --------------------------------------------------
   A serial is printed on a physical card, so the number is handed out here rather than invented
   at the keyboard. Allocation goes above the highest number already in use instead of into the
   gaps deleted records leave behind: a re-issued number would put a second card in circulation
   carrying a number a real card already has. */
function usmsmartCardSerialTail($serial) {
    preg_match('/([0-9]+)$/', strtoupper(trim((string)$serial)), $digits);
    return isset($digits[1]) ? (int)$digits[1] : null;
}

function usmsmartNextCardSerial(array $existing) {
    $tails = array();
    foreach ($existing as $serial) {
        $tail = usmsmartCardSerialTail($serial);
        if ($tail !== null) $tails[$tail] = true;
    }
    $next = count($tails) ? max(array_keys($tails)) + 1 : 1;
    while (isset($tails[$next])) $next++;
    /* Padding keeps hand-typed short numbers from looking different in kind from generated ones,
       and the cap keeps a long sequence inside students_view.serial_no, which is varchar(50). */
    $value = 'USM-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    return strlen($value) > 50 ? substr($value, -50) : $value;
}

function usmsmartSerialOrNull($value) {
    $value = strtoupper(trim((string)$value));
    return $value === '' ? null : $value;
}

function usmsmartAllocateCardSerial(PDO $pdo, $ignoreId = 0, array $extra = array()) {
    $existing = $extra;
    try {
        if ($ignoreId > 0) {
            $stmt = $pdo->prepare("SELECT serial_no FROM students_view WHERE id <> ? AND serial_no IS NOT NULL AND TRIM(serial_no) <> ''");
            $stmt->execute(array((int)$ignoreId));
        } else {
            $stmt = $pdo->prepare("SELECT serial_no FROM students_view WHERE serial_no IS NOT NULL AND TRIM(serial_no) <> ''");
            $stmt->execute(array());
        }
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $serial) $existing[] = (string)$serial;
    } catch (Throwable $e) {
        error_log('USMSmart card serial pool read failed: ' . $e->getMessage());
        return '';
    }
    /* Checked again here rather than trusted from the pool above: two tabs that opened the add
       form in the same minute were offered the same number, and only the table knows whether the
       first of them has already committed. */
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $candidate = usmsmartNextCardSerial($existing);
        $chk = $pdo->prepare('SELECT id FROM students_view WHERE UPPER(TRIM(serial_no)) = UPPER(?) LIMIT 1');
        $chk->execute(array($candidate));
        if (!$chk->fetch(PDO::FETCH_ASSOC)) return $candidate;
        $existing[] = $candidate;
    }
    return '';
}

/* The allocator is a promise until the column can enforce it, so the first card write of a request
   adds a unique index. Blank serials become NULL on the way in: a unique index accepts any number
   of NULLs but rejects a second empty string, and records imported from the live source genuinely
   do not have a serial yet. */
function usmsmartEnsureCardSerialUniqueness(PDO $pdo) {
    static $ready = false;
    if ($ready) return;
    $ready = true;
    try {
        $index = $pdo->prepare("SHOW INDEX FROM students_view WHERE Key_name = 'usmsmart_serial_unique'");
        $index->execute(array());
        if ($index->fetch(PDO::FETCH_ASSOC)) return;
        $pdo->exec("UPDATE students_view SET serial_no = NULL WHERE serial_no IS NOT NULL AND TRIM(serial_no) = ''");
        $dupes = $pdo->query("SELECT COUNT(*) FROM (SELECT serial_no FROM students_view WHERE serial_no IS NOT NULL AND TRIM(serial_no) <> '' GROUP BY serial_no HAVING COUNT(*) > 1) repeats");
        if ((int)$dupes->fetchColumn() > 0) {
            error_log('USMSmart: duplicate card serials are already stored; the unique index waits until they are resolved.');
            return;
        }
        $pdo->exec('ALTER TABLE students_view ADD UNIQUE KEY usmsmart_serial_unique (serial_no)');
    } catch (Throwable $e) {
        error_log('USMSmart card serial uniqueness setup failed: ' . $e->getMessage());
    }
}

// Validate UID as byte-paired hexadecimal. Card serials are variable-format; blank is allowed
// when the live source does not supply a separate serial-number field.
function validateCardFields($uid, $serialNo) {
    $uid = strtoupper(preg_replace('/\s+/', '', trim((string)$uid)));
    $serialNo = strtoupper(trim((string)$serialNo));
    if ($uid === '' || !preg_match('/^(?:[0-9A-F]{2})+$/', $uid)) {
        return 'UID mesti mengandungi aksara hexadecimal sahaja dan mempunyai bilangan digit genap (sekurang-kurangnya 2).';
    }
    if ($serialNo !== '' && (mb_strlen($serialNo) > 100 || !preg_match('/^[A-Z0-9][A-Z0-9 ._\/-]*$/i', $serialNo))) {
        return 'Nombor kad mengandungi aksara tidak disokong; gunakan huruf, nombor, ruang, titik, garis atau garis bawah.';
    }
    return '';
}

// Descriptive fields carried alongside a Sysweb record so the portal can be filtered on the
// same details as the source directory. Students contribute school/policy/nationality, staff
// contribute department/card status/notes; both spellings are accepted because the transport
// payload and the manual editor use different key cases.
function usmsmartSyswebDetails($source) {
    $pick = static function (array $keys) use ($source) {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_scalar($source[$key]) && trim((string)$source[$key]) !== '') return (string)$source[$key];
        }
        return '';
    };
    $text = static function ($value, $limit) {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', trim((string)$value));
        return $clean === null ? '' : mb_substr($clean, 0, $limit);
    };
    $date = static function ($value) {
        $match = array();
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', trim((string)$value), $match) && isValidIsoDate($match[1])) return $match[1];
        return '';
    };
    return array(
        'school'      => $text($pick(array('school', 'department', 'PPengajian', 'NamaJabatan')), 180),
        'policy'      => $text($pick(array('policy', 'cardStatus', 'card_status', 'StatusKad')), 60),
        // Neither cardSerial nor NoSiriKad is a serial number - the source directory has no serial
        // column at all, so both spellings carry the card's raw UID. Picking one up here writes a UID
        // into the Sysweb record's serial field, which is exactly what the byte-order gate then fails.
        'serial_no'   => $text($pick(array('serial_no', 'serialNo')), 100),
        'date_joined' => $date($pick(array('date_joined', 'dateJoined'))),
        'expiry_date' => $date($pick(array('expiry_date', 'expiryDate', 'TkhTamat'))),
        'nationality' => $text($pick(array('nationality', 'Kewarganegaraan')), 60),
        'notes'       => $text($pick(array('notes', 'Nota')), 500),
        'source_uid'  => strtoupper($text($pick(array('source_uid', 'usmUid', 'uidRaw', 'cardUid', 'NoSiriKad')), 40)),
    );
}

// Sysweb credentials are minted by the server only, so an exported batch and a manually added
// record behave identically. The plaintext is never written to a log, the audit trail or the
// record list; consumers store and compare this short fingerprint instead.
function usmsmartGenerateSyswebPassword() {
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $digits = '23456789';
    $pool = $upper.$lower.$digits;
    $chars = array($upper[random_int(0, strlen($upper)-1)], $lower[random_int(0, strlen($lower)-1)], $digits[random_int(0, strlen($digits)-1)]);
    for ($i = count($chars); $i < 12; $i++) $chars[] = $pool[random_int(0, strlen($pool)-1)];
    for ($i = count($chars)-1; $i > 0; $i--) { $j = random_int(0, $i); $tmp = $chars[$i]; $chars[$i] = $chars[$j]; $chars[$j] = $tmp; }
    return implode('', $chars);
}
function usmsmartSyswebPasswordFingerprint($password) {
    return $password === '' ? '' : substr(hash('sha256', (string) $password), 0, 12);
}

// Mirrors convertUidToLittleEndian() in the browser so a wrong byte order is caught on the
// server too instead of being trusted because the client formatted it.
function usmsmartUidLittleEndian($hex) {
    $clean = strtoupper((string) preg_replace('/\s+/', '', trim((string) $hex)));
    if ($clean === '' || !preg_match('/^(?:[0-9A-F]{2})+$/', $clean)) return '';
    return implode('', array_reverse(str_split($clean, 2)));
}

// One-time relocation for the value the source directory calls NoSiriKad: the name means "card serial",
// the contents are the card's raw UID. Printed serials in this app are alphanumeric and never byte-pair
// hex, so restricting the move to 4-, 7- and 8-byte hex values cannot swallow a genuine serial number.
// Idempotent - it only touches rows whose card_uid is still empty, and it never runs against the live
// server, whose schema stays read-only. It adds the column itself so a caller that has not been through
// a staff-directory schema check still gets a readable table.
function usmsmartMigrateStaffUidColumn(PDO $pdo) {
    try {
        if (!count($pdo->query("SHOW COLUMNS FROM usmsmart_staff_directory LIKE 'card_uid'")->fetchAll())) {
            $pdo->exec("ALTER TABLE usmsmart_staff_directory ADD COLUMN card_uid VARCHAR(50) NULL");
        }
        $pdo->exec("UPDATE usmsmart_staff_directory SET card_uid = UPPER(TRIM(card_serial)), card_serial = NULL"
            . " WHERE (card_uid IS NULL OR TRIM(card_uid) = '')"
            . " AND TRIM(COALESCE(card_serial,'')) <> '' AND CHAR_LENGTH(TRIM(card_serial)) IN (8,14,16)"
            . " AND TRIM(card_serial) REGEXP '^([0-9A-Fa-f]{2})+$'");
    } catch (Throwable $e) { error_log('USMSmart staff UID migration skipped: ' . $e->getMessage()); }
}

// Shared exclusive lock for every Sysweb store writer, so a password reset cannot interleave
// with an import and lose records. Callers must always pass the handle back to the writer.
function usmsmartOpenSyswebStore() {
    $storePath = USM_ROOT . DIRECTORY_SEPARATOR . 'sysweb_demo_received.json';
    $fp = @fopen($storePath, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) { if ($fp) fclose($fp); return null; }
    $records = json_decode((string) stream_get_contents($fp), true);
    return array('fp' => $fp, 'records' => is_array($records) ? $records : array());
}
function usmsmartWriteSyswebStore($handle, $records) {
    rewind($handle['fp']); ftruncate($handle['fp'], 0);
    $ok = fwrite($handle['fp'], json_encode(array_values($records), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)) !== false;
    fflush($handle['fp']); flock($handle['fp'], LOCK_UN); fclose($handle['fp']);
    return $ok;
}

// The AI assistant gets a counts-only picture of the Sysweb integration store. It takes no write
// lock because it reads, and it must never return names, identifiers, card UIDs, serial numbers or
// credentials, since everything it hands back is sent to the language model.
function usmsmartSyswebReadStore() {
    $storePath = USM_ROOT . DIRECTORY_SEPARATOR . 'sysweb_demo_received.json';
    if (!is_file($storePath)) return array();
    $records = json_decode((string)file_get_contents($storePath), true);
    return is_array($records) ? $records : array();
}

function usmsmartSyswebExpiryBucket($record) {
    $raw = substr(trim((string)($record['expiry_date'] ?? '')), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) || !isValidIsoDate($raw)) return 'none';
    $days = (int)round((strtotime($raw . ' 00:00:00') - strtotime('today')) / 86400);
    if ($days < 0) return 'expired';
    if ($days <= 30) return 'soon';
    return 'valid';
}

function usmsmartSyswebStoreSummary(array $records, array $classifications = array()) {
    $schools = array();
    $summary = array(
        'total_records'=>0, 'students'=>0, 'staff'=>0,
        'card_active'=>0, 'card_expiring_30_days'=>0, 'card_expired'=>0, 'card_expiry_unknown'=>0,
        'missing_name'=>0, 'missing_identifier'=>0, 'missing_serial'=>0,
        'missing_generated_password'=>0, 'byte_order_unverified'=>0,
        'card_source_matched'=>0, 'card_source_uda_only'=>0, 'card_source_ambiguous'=>0,
        'card_source_conflict'=>0, 'card_source_absent'=>0, 'card_source_skip'=>0, 'completable_records'=>0
    );
    foreach ($records as $recordSlot => $record) {
        if (!is_array($record)) continue;
        $summary['total_records']++;
        $cardVerdict = isset($classifications[$recordSlot]) ? strtolower((string)($classifications[$recordSlot]['status'] ?? '')) : '';
        if ($cardVerdict !== '' && isset($summary['card_source_' . $cardVerdict])) $summary['card_source_' . $cardVerdict]++;
        if (!empty($classifications[$recordSlot]['hydrate'])) $summary['completable_records']++;
        if (strtolower(trim((string)($record['type'] ?? 'student'))) === 'staff') $summary['staff']++; else $summary['students']++;
        $bucket = usmsmartSyswebExpiryBucket($record);
        if ($bucket === 'expired') $summary['card_expired']++;
        elseif ($bucket === 'soon') $summary['card_expiring_30_days']++;
        elseif ($bucket === 'valid') $summary['card_active']++;
        else $summary['card_expiry_unknown']++;
        if (trim((string)($record['name'] ?? '')) === '') $summary['missing_name']++;
        if (trim((string)($record['identifier'] ?? '')) === '') $summary['missing_identifier']++;
        if (trim((string)($record['serial_no'] ?? '')) === '') $summary['missing_serial']++;
        if ((string)($record['password'] ?? '') === '') $summary['missing_generated_password']++;
        $converted = strtoupper(trim((string)($record['converted_uid'] ?? '')));
        $source = strtoupper(trim((string)($record['source_uid'] ?? '')));
        // An empty raw UID is not a defect, it simply means nobody can re-prove the byte order.
        if ($source === '' || preg_match('/^(?:[0-9A-F]{2})+$/', $source) !== 1) $summary['byte_order_unverified']++;
        $school = trim((string)($record['school'] ?? ''));
        if ($school !== '') $schools[$school] = ($schools[$school] ?? 0) + 1;
    }
    arsort($schools);
    $summary['top_schools'] = array();
    foreach (array_slice($schools, 0, 5, true) as $name => $count) $summary['top_schools'][] = array('school'=>$name, 'records'=>$count);
    return $summary;
}

// Mirrors validateSyswebBatch(records, [], { requirePassword: true }) in the browser so the panel
// and the assistant report the same verdicts. The parity test in the sandbox compares both.
// The figure block is read next to the question and a small model replies with whichever number sits
// closest to it, so the line the user actually asked about must be last. Only tokens that are long and
// rare inside the block count: almost every line contains "rekod" and "kad", which cannot tell two
// questions apart. The question is untrusted text but it only reorders aggregate figures, never adds
// any, so it cannot pull a value into the prompt that was not already there.
function usmsmartRankSyswebHeadlineLines(array $lines, $question) {
    $tokens = static function ($text) {
        $lower = strtolower(preg_replace('/[^a-z0-9_]+/i', ' ', (string)$text));
        $parts = preg_split('/\s+/', trim($lower), -1, PREG_SPLIT_NO_EMPTY);
        return is_array($parts) ? $parts : array();
    };
    $asked = array_fill_keys($tokens($question), true);
    // Only a "label = number" line may move to the end. The prose lines list rule labels that repeat
    // words from almost any question, so ranking them puts the issue list next to the question and the
    // model quotes that instead of the figure.
    $descriptive = array();
    $figures = array();
    foreach ($lines as $line) {
        if (preg_match('/= \d+$/', trim((string)$line))) $figures[] = $line; else $descriptive[] = $line;
    }
    $inLines = array();
    foreach ($figures as $line) {
        foreach (array_unique($tokens($line)) as $token) $inLines[$token] = ($inLines[$token] ?? 0) + 1;
    }
    $scored = array();
    foreach ($figures as $position => $line) {
        $score = 0;
        foreach (array_unique($tokens($line)) as $token) {
            // Count only a token that belongs to this one line. Words such as "rekod" and "kad" sit in
            // almost every label and in every question, so scoring them ranks the longest label highest
            // no matter what was asked.
            if ($inLines[$token] !== 1) continue;
            if (isset($asked[$token])) $score++;
        }
        $scored[] = array($score, $position, $line);
    }
    usort($scored, static function ($a, $b) { return $a[0] === $b[0] ? $a[1] - $b[1] : $a[0] - $b[0]; });
    $ordered = array_map(static function ($entry) { return $entry[2]; }, $scored);
    return implode("\n", array_merge($descriptive, $ordered));
}

function usmsmartSyswebQualitySummary(array $records, array $classifications = array(), array $cardSource = array()) {
    $invalidCodes = array('name', 'identifier', 'type', 'uid', 'prefix', 'byteorder', 'duplicate-id', 'duplicate-uid', 'date', 'card-conflict');
    $labels = array(
        'name'=>'Nama tiada', 'identifier'=>'Nombor pengenalan tiada', 'type'=>'Kategori tidak dikenali',
        'uid'=>'UID converted bukan hexadecimal bait-pair', 'prefix'=>'Prefiks Sysweb (00000000) tidak sepadan',
        'byteorder'=>'Susunan byte UID converted terbalik', 'duplicate-id'=>'No. pengenalan dipakai dengan UID Sysweb berbeza',
        'duplicate-uid'=>'UID kad dipakai oleh pengenalan lain', 'date'=>'Tarikh tamat kad bukan format ISO',
        'serial'=>'No. siri kad tiada', 'duplicate-serial'=>'No. siri kad berkembar', 'expired'=>'Kad sudah luput',
        'card-absent'=>'Pengenalan tiada dalam rekod kad', 'card-ambiguous'=>'Lebih dari satu rekod kad ditemui untuk pengenalan ini',
        'card-conflict'=>'UID kad bercanggah dengan rekod kad', 'card-uda-only'=>'Hanya rekod UDA tersedia, rekod kad rasmi tiada',
        'card-unconfirmed'=>'Pangkalan kad Sysweb langsung tidak mengesahkan pengenalan ini',
        'card-live-differs'=>'Salinan tempatan dan pangkalan kad Sysweb langsung tidak sependapat',
        'expiring'=>'Kad tamat dalam 30 hari', 'expiry-missing'=>'Tiada rekod tarikh tamat', 'password'=>'Kata laluan belum dijana'
    );
    $identityOwners = array(); $uidOwners = array(); $serialOwners = array();
    $keyOf = static function (array $r) {
        return array(
            'ident'=>(strtolower(trim((string)($r['type'] ?? 'student'))) === 'staff' ? 'staff' : 'student') . '|' . strtolower(trim((string)($r['identifier'] ?? ''))),
            'uid'=>strtoupper(trim((string)($r['sysweb_uid'] ?? ''))),
            'serial'=>strtolower(trim((string)($r['serial_no'] ?? '')))
        );
    };
    foreach ($records as $record) {
        if (!is_array($record)) continue;
        $key = $keyOf($record);
        $identityOwners[$key['ident']][] = $key['uid'];
        if ($key['uid'] !== '') $uidOwners[$key['uid']][] = $key['ident'];
        if ($key['serial'] !== '') $serialOwners[$key['serial']][] = 1;
    }
    $verdicts = array('valid'=>0, 'review'=>0, 'invalid'=>0);
    $issueCounts = array();
    foreach ($records as $recordPosition => $record) {
        if (!is_array($record)) continue;
        $key = $keyOf($record);
        $codes = array();
        $converted = strtoupper(trim((string)($record['converted_uid'] ?? '')));
        $sysUid = strtoupper(trim((string)($record['sysweb_uid'] ?? '')));
        $source = strtoupper(preg_replace('/\s+/', '', trim((string)($record['source_uid'] ?? ''))));
        $source = preg_match('/^(?:[0-9A-F]{2})+$/', $source) === 1 ? $source : '';
        $expiry = substr(trim((string)($record['expiry_date'] ?? '')), 0, 10);
        $hexOk = preg_match('/^(?:[0-9A-F]{2})+$/', $converted) === 1;
        if (trim((string)($record['name'] ?? '')) === '') $codes[] = 'name';
        if (trim((string)($record['identifier'] ?? '')) === '') $codes[] = 'identifier';
        $type = strtolower(trim((string)($record['type'] ?? 'student')));
        if ($type !== 'student' && $type !== 'staff') $codes[] = 'type';
        if (!$hexOk) $codes[] = 'uid';
        elseif ($sysUid !== '00000000' . $converted) $codes[] = 'prefix';
        if ($hexOk && $source !== '' && usmsmartUidLittleEndian($source) !== $converted) $codes[] = 'byteorder';
        foreach ($identityOwners[$key['ident']] ?? array() as $otherUid) {
            if ($otherUid !== '' && $key['uid'] !== '' && $otherUid !== $key['uid']) { $codes[] = 'duplicate-id'; break; }
        }
        foreach ($uidOwners[$key['uid']] ?? array() as $otherIdent) {
            if ($otherIdent !== '' && $key['ident'] !== '' && $otherIdent !== $key['ident']) { $codes[] = 'duplicate-uid'; break; }
        }
        if ($key['serial'] !== '' && count($serialOwners[$key['serial']] ?? array()) > 1) $codes[] = 'duplicate-serial';
        if ($expiry !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry) !== 1) $codes[] = 'date';
        if (trim((string)($record['serial_no'] ?? '')) === '') $codes[] = 'serial';
        $bucket = usmsmartSyswebExpiryBucket($record);
        if ($bucket === 'expired') $codes[] = 'expired';
        elseif ($bucket === 'soon') $codes[] = 'expiring';
        elseif ($expiry === '') $codes[] = 'expiry-missing';
        if ((string)($record['password'] ?? '') === '') $codes[] = 'password';
        $cardStatus = isset($classifications[$recordPosition]) ? $classifications[$recordPosition]['status'] : '';
        if ($cardStatus === 'uda_only') $cardStatus = 'uda-only';
        if (in_array($cardStatus, array('absent', 'ambiguous', 'conflict', 'uda-only'), true)) $codes[] = 'card-' . $cardStatus;
        // Both rules only fire when the live Sysweb database answered; with the snapshot alone an
        // absent row proves nothing about the real card system, so the panel stays silent.
        $liveState = (string)($classifications[$recordPosition]['live'] ?? 'unknown');
        if ($liveState === 'unconfirmed') $codes[] = 'card-unconfirmed';
        elseif ($liveState === 'contradicted') $codes[] = 'card-live-differs';
        $codes = array_values(array_unique($codes));
        $isInvalid = false;
        foreach ($codes as $code) {
            $issueCounts[$code] = ($issueCounts[$code] ?? 0) + 1;
            if (in_array($code, $invalidCodes, true)) $isInvalid = true;
        }
        if ($isInvalid) $verdicts['invalid']++;
        elseif ($codes) $verdicts['review']++;
        else $verdicts['valid']++;
    }
    arsort($issueCounts);
    $issues = array();
    foreach ($issueCounts as $code => $count) $issues[] = array('rule'=>$code, 'label'=>$labels[$code] ?? $code, 'records'=>$count);
    $checked = array_sum($verdicts);
    // A small local model reads a plain sentence far more reliably than nested JSON counters,
    // and the sentence keeps it from claiming the verdicts are unavailable.
    $summary = $checked === 0
        ? 'The Sysweb integration store is empty, so no record has been checked.'
        : 'Checked ' . $checked . ' records in the Sysweb integration store: ' . $verdicts['valid'] . ' valid, ' . $verdicts['review'] . ' needs review, ' . $verdicts['invalid'] . ' invalid.'
;
    if ($checked > 0 && !empty($issues)) {
        $top = array_slice($issues, 0, 3);
        $summary .= ' Most frequent issues: ' . implode('; ', array_map(static function ($issue) { return $issue['label'] . ' (' . $issue['records'] . ' records)'; }, $top)) . '.';
    } elseif ($checked > 0) {
        $summary .= ' No issues found.';
    }
    if (!empty($classifications)) {
        $tally = array('matched' => 0, 'uda_only' => 0, 'ambiguous' => 0, 'conflict' => 0, 'absent' => 0);
        $completable = 0;
        foreach ($classifications as $classification) {
            if (!is_array($classification)) continue;
            $state = (string)($classification['status'] ?? '');
            if (isset($tally[$state])) $tally[$state]++;
            if (!empty($classification['hydrate'])) $completable++;
        }
        $summary .= ' Card-record cross-check: ' . $tally['matched'] . ' matched the issued card records, '
            . $tally['uda_only'] . ' exist only in the UDA staging table, ' . $tally['ambiguous']
            . ' have more than one conflicting card record, ' . $tally['conflict']
            . ' have a UID that contradicts the card record, ' . $tally['absent']
            . ' are not in the card records at all. '
            . ($completable ? ($completable . ' record(s) can be completed from the card records.') : 'No record can be completed from the card records.');
    }
    // One figure per line, labelled in both languages: the model copies the line whose label
    // matches the question, but abandons a long compound sentence after its first numbers.
    $lines = array();
    $lines[] = 'Sah (valid) = ' . $verdicts['valid'];
    $lines[] = 'Perlu semakan (review) = ' . $verdicts['review'];
    $lines[] = 'Rosak (invalid) = ' . $verdicts['invalid'];
    $lines[] = 'Jumlah diperiksa (checked_records) = ' . $checked;
    if (!empty($classifications)) {
        $lines[] = 'Sepadan rekod kad (card_source_matched) = ' . $tally['matched'];
        $lines[] = 'Hanya dalam UDA (card_source_uda_only) = ' . $tally['uda_only'];
        $lines[] = 'Lebih satu rekod kad (card_source_ambiguous) = ' . $tally['ambiguous'];
        $lines[] = 'UID bercanggah (card_source_conflict) = ' . $tally['conflict'];
        $lines[] = 'Tiada rekod kad (card_source_absent) = ' . $tally['absent'];
        $lines[] = 'Boleh dilengkap (completable_records) = ' . $completable;
        $lines[] = (($cardSource['mode'] ?? '') === 'live'
            ? 'Sumber rekod kad (card_source_mode) = pangkalan data kad Sysweb langsung, '
                . (int)($cardSource['confirmed'] ?? 0) . ' daripada ' . (int)($cardSource['queried'] ?? 0) . ' pengenalan ditemui'
            : 'Sumber rekod kad (card_source_mode) = salinan tempatan, pangkalan data kad Sysweb langsung tidak digunakan');
    }
    if (!empty($issues)) {
        $lines[] = 'Isu paling kerap / top issues: ' . implode('; ', array_slice(array_map(static function ($issue) { return $issue['label'] . ' (' . $issue['records'] . ')'; }, $issues), 0, 3));
    }
    return array('verdict_summary'=>$summary, 'headline'=>$lines, 'verdicts'=>$verdicts, 'issues_by_rule'=>$issues, 'checked_records'=>$checked);
}

// Cross-source reconciliation. The Sysweb store holds staged rows, while students_view (students)
// and usmsmart_staff_directory (staff) hold the issued card records that carry the serial number and
// expiry date Sysweb needs. uda_system_records is only a fallback for UID and policy: it has no
// expiry column, so a row that exists there alone cannot be fully proven.
/* ===== The Sysweb card database: a separate SQL Server from the dbkadpintar identity source ===== */
// Credentials live in a file outside the web root. It ships with CHANGE_ME placeholders, and while
// any placeholder remains this code never attempts a connection: reconciliation stays on the local
// snapshot and the UI says so. Nothing here ever writes to that server.
// Functions rather than constants: the action handlers near the top of index.php answer and exit long
// before execution reaches this point, and PHP makes a top-level function callable everywhere but runs
// a top-level const only after its own line has executed.
// Only the path is overridable, never a credential: the harness needs to point at a stub config to
// exercise the unreachable-host fallback without touching the file the operator fills in.
function usmsmartSyswebLiveConfigFile() {
    $override = trim((string)(getenv('USMSMART_SYSWEB_CONFIG') ?: ''));
    return $override !== '' ? $override : 'C:/xampp/private/usmsmart_sysweb.php';
}
function usmsmartSyswebLivePlaceholder() { return 'CHANGE_ME'; }

function usmsmartSyswebLiveConfig() {
    $file = usmsmartSyswebLiveConfigFile();
    if (!is_file($file) || !is_readable($file)) return array();
    $config = @require $file;
    return is_array($config) ? $config : array();
}

function usmsmartSyswebLiveFilled($value) {
    $text = trim((string)$value);
    return !($text === '' || strcasecmp($text, usmsmartSyswebLivePlaceholder()) === 0 || strcasecmp($text, 'UNKNOWN') === 0);
}

function usmsmartSyswebLiveReady($config) {
    foreach (array('server', 'database', 'username', 'password') as $key) {
        if (!usmsmartSyswebLiveFilled($config[$key] ?? '')) return false;
    }
    return usmsmartSyswebLiveFilled($config['card_table'] ?? '');
}

// One handle per request. A dead host must not be re-probed by every caller, so the failure is
// remembered and the snapshot answers immediately instead.
function usmsmartGetSyswebLivePdo($config) {
    static $connection = null;
    static $broken = false;
    if ($broken) throw new RuntimeException('Sysweb database connection already failed on this request.');
    if ($connection instanceof PDO) return $connection;
    try {
        $timeout = max(1, min(15, (int)($config['login_timeout'] ?? 5)));
        $connection = new PDO(
            sprintf('sqlsrv:Server=%s;Database=%s;Encrypt=yes;TrustServerCertificate=yes;LoginTimeout=%d',
                $config['server'], $config['database'], $timeout),
            $config['username'], $config['password'],
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false));
    } catch (Throwable $e) {
        $broken = true;
        throw $e;
    }
    return $connection;
}

// Nothing from another team's schema is ever pasted into SQL raw: characters that cannot appear in a
// bracketed identifier are dropped, so the result cannot break out of the brackets.
function usmsmartSafeLiveColumnName($name) {
    $safe = preg_replace('/[^A-Za-z0-9_]/', '', (string)$name);
    return ($safe === '' || preg_match('/^\d/', $safe) === 1) ? '' : $safe;
}

function usmsmartSyswebLiveTableName($table) {
    $parts = preg_split('/[.\[\]]+/', trim((string)$table), -1, PREG_SPLIT_NO_EMPTY);
    if (!count($parts)) return '';
    $safe = array();
    foreach ($parts as $part) {
        $clean = usmsmartSafeLiveColumnName($part);
        if ($clean === '') return '';
        $safe[] = $clean;
    }
    return count($safe) === 1 ? 'dbo.[' . $safe[0] . ']' : '[' . implode('].[', $safe) . ']';
}

function usmsmartSyswebLiveColumns(PDO $live, $table) {
    $parts = preg_split('/[.\[\]]+/', trim((string)$table), -1, PREG_SPLIT_NO_EMPTY);
    if (!count($parts)) return array();
    $name = usmsmartSafeLiveColumnName(array_pop($parts));
    $schema = count($parts) ? usmsmartSafeLiveColumnName(array_shift($parts)) : 'dbo';
    if ($name === '') return array();
    $statement = $live->prepare('SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $statement->execute(array($schema === '' ? 'dbo' : $schema, $name));
    $columns = array();
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $real = usmsmartSafeLiveColumnName($row['COLUMN_NAME'] ?? '');
        if ($real !== '') $columns[strtolower($real)] = $real;
    }
    return $columns;
}

// Only unambiguous fields are guessed. The card number and the UID are never auto-detected: choosing
// the wrong column there produces a confidently wrong verdict rather than an honest abstain, so the
// operator maps them. An unmapped field is simply not compared, and the screen says which.
function usmsmartSyswebLiveCandidates() {
    return array(
        'identifier' => array('nomatrik', 'nostaf', 'identifier', 'matric', 'no_matrik', 'staffno'),
        'uid' => array(), 'serial' => array(),
        'expiry' => array('tkhtamat', 'tarikhtamat', 'expirydate', 'expiry_date', 'tamat'),
        'policy' => array('policy', 'polisi', 'produkkad', 'jeniskad'),
    );
}

function usmsmartSyswebLivePlan(array $columns, array $overrides) {
    $candidates = usmsmartSyswebLiveCandidates();
    $plan = array();
    foreach (array('identifier', 'uid', 'serial', 'expiry', 'policy') as $field) {
        $override = usmsmartSafeLiveColumnName($overrides[$field] ?? '');
        if ($override !== '') { $plan[$field] = isset($columns[strtolower($override)]) ? $override : ''; continue; }
        $plan[$field] = '';
        foreach ($candidates[$field] as $candidate) {
            if (isset($columns[$candidate])) { $plan[$field] = $columns[$candidate]; break; }
        }
    }
    return $plan;
}

// Identifiers reach the query only as bound parameters; the field list is built from columns this
// server reported, each cast to text so a date or numeric column yields the same shape either way.
function usmsmartSyswebLiveSql($table, array $plan, array $identifiers) {
    $target = usmsmartSyswebLiveTableName($table);
    $ident = usmsmartSafeLiveColumnName($plan['identifier'] ?? '');
    if ($target === '' || $ident === '' || !count($identifiers)) return '';
    $select = array();
    foreach (array('identifier', 'uid', 'serial', 'expiry', 'policy') as $field) {
        $column = usmsmartSafeLiveColumnName($field === 'identifier' ? $ident : ($plan[$field] ?? ''));
        $select[] = $column === ''
            ? "'' AS " . $field
            : 'LTRIM(RTRIM(COALESCE(CAST([' . $column . '] AS varchar(120)),\'\'))) AS ' . $field;
    }
    return 'SELECT ' . implode(', ', $select) . ' FROM ' . $target
        . ' WHERE LTRIM(RTRIM(COALESCE(CAST([' . $ident . '] AS varchar(120)),\'\'))) IN ('
        . implode(', ', array_fill(0, count($identifiers), '?')) . ')';
}

// Keyed by the lowercased identifier alone: the staged row already knows whether it is a student or a
// member of staff, and the Sysweb card table is not assumed to separate them.
function usmsmartSyswebLiveCardIndex(array $records, &$source) {
    $index = array();
    $source = array('mode' => 'not_configured', 'queried' => 0, 'confirmed' => 0, 'unmapped' => array(), 'reason' => 'Pangkalan data kad Sysweb belum dikonfigurasi.');
    $config = usmsmartSyswebLiveConfig();
    if (!usmsmartSyswebLiveReady($config)) return $index;
    $needles = array();
    foreach ($records as $record) {
        if (!is_array($record)) continue;
        $ident = strtolower(trim((string)($record['identifier'] ?? '')));
        if ($ident !== '') $needles[$ident] = $ident;
    }
    if (!count($needles)) { $source['mode'] = 'idle'; $source['reason'] = 'Tiada pengenalan untuk disemak.'; return $index; }
    try {
        $live = usmsmartGetSyswebLivePdo($config);
        $columns = usmsmartSyswebLiveColumns($live, (string)$config['card_table']);
        if (!count($columns)) {
            $source['mode'] = 'unreadable';
            $source['reason'] = 'Jadual kad Sysweb tidak dapat dibaca dari pangkalan data tersebut.';
            return $index;
        }
        $overrides = is_array($config['card_columns'] ?? null) ? $config['card_columns'] : array();
        $plan = usmsmartSyswebLivePlan($columns, $overrides);
        if (($plan['identifier'] ?? '') === '') {
            $source['mode'] = 'unreadable';
            $source['reason'] = 'Lajur pengenalan kad Sysweb tidak dapat dikenal pasti.';
            return $index;
        }
        $table = (string)$config['card_table'];
        $values = array_values($needles);
        $source['queried'] = count($values);
        $source['unmapped'] = array_values(array_filter(array('uid', 'serial', 'expiry', 'policy'),
            static function ($field) use ($plan) { return ($plan[$field] ?? '') === ''; }));
        foreach (array_chunk($values, 100) as $chunk) {
            $sql = usmsmartSyswebLiveSql($table, $plan, $chunk);
            if ($sql === '') continue;
            $statement = $live->prepare($sql);
            $statement->execute($chunk);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ident = strtolower(trim((string)($row['identifier'] ?? '')));
                if ($ident === '') continue;
                $uid = strtoupper(preg_replace('/\s+/', '', (string)($row['uid'] ?? '')));
                if (!isset($index[$ident])) $index[$ident] = array();
                $index[$ident][] = array(
                    'serial' => trim((string)($row['serial'] ?? '')),
                    'expiry' => substr(trim((string)($row['expiry'] ?? '')), 0, 10),
                    'policy' => trim((string)($row['policy'] ?? '')),
                    'converted' => preg_match('/^(?:[0-9A-F]{2})+$/', $uid) === 1 ? usmsmartUidLittleEndian($uid) : '',
                );
            }
        }
        $source['mode'] = 'live';
        $source['confirmed'] = count($index);
        $source['reason'] = '';
    } catch (Throwable $e) {
        error_log('USMSmart Sysweb card database unavailable: ' . $e->getMessage());
        // The driver message repeats the host and the login name, so only a generic reason is shown.
        $source['mode'] = 'unavailable';
        $source['reason'] = 'Pangkalan data kad Sysweb tidak dapat dihubungi.';
    }
    return $index;
}

/* ---- Card-system write path ------------------------------------------------
   Everything above reads the Sysweb card database. This is the only code that
   writes to it, and it is opt-in: the private config must set write_enabled and
   carry a login distinct from the read-only one. A failed write never rolls
   back a committed MySQL export; the operator is told, and re-running the same
   export repairs it because every write is keyed on the identifier. */

function usmsmartSyswebWriteTable(array $config) {
    $table = trim((string)($config['write_table'] ?? ''));
    return $table !== '' ? $table : trim((string)($config['card_table'] ?? ''));
}

function usmsmartSyswebWriteReady(array $config) {
    if (empty($config['write_enabled'])) return false;
    foreach (array('server', 'database', 'write_username', 'write_password') as $key) {
        if (!usmsmartSyswebLiveFilled($config[$key] ?? '')) return false;
    }
    return usmsmartSyswebWriteTable($config) !== '';
}

// Deliberately a separate handle from the read snapshot: the writer is another
// login, so it must not inherit the reader's cached connection.
function usmsmartGetSyswebWritePdo(array $config) {
    static $connection = null;
    if ($connection instanceof PDO) return $connection;
    $timeout = max(1, min(15, (int)($config['login_timeout'] ?? 5)));
    $connection = new PDO(
        sprintf('sqlsrv:Server=%s;Database=%s;Encrypt=yes;TrustServerCertificate=yes;LoginTimeout=%d',
            $config['server'], $config['database'], $timeout),
        $config['write_username'], $config['write_password'],
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false));
    return $connection;
}

// Column names come from the server's own catalogue, so a renamed column fails
// this preflight instead of silently writing elsewhere. The UID column is never
// guessed: the read side abstains there, and a wrong guess on a write would
// corrupt a card rather than a report. $missing names what the operator has to
// map.
function usmsmartSyswebWritePlan(array $columns, array $overrides, &$missing) {
    $plan = usmsmartSyswebLivePlan($columns, $overrides);
    $missing = array();
    foreach (array('identifier', 'uid') as $field) {
        if (($plan[$field] ?? '') === '') $missing[] = $field;
    }
    return $plan;
}

// Values are bound; only identifiers are interpolated, and those have been
// stripped to [A-Za-z0-9_] and bracketed. Empty optional fields are omitted so
// the card table's own defaults apply, except the UID: without it nothing is
// written, because that is the whole point of this path.
function usmsmartSyswebWriteSql($table, array $plan, array $values) {
    $target = usmsmartSyswebLiveTableName($table);
    $ident = usmsmartSafeLiveColumnName($plan['identifier'] ?? '');
    $uid = usmsmartSafeLiveColumnName($plan['uid'] ?? '');
    $identifier = trim((string)($values['identifier'] ?? ''));
    if ($target === '' || $ident === '' || $uid === '' || $identifier === '' || $ident === $uid) return array();
    $fields = array();
    foreach (array('uid', 'serial', 'expiry', 'policy') as $field) {
        $column = usmsmartSafeLiveColumnName($plan[$field] ?? '');
        $value = trim((string)($values[$field] ?? ''));
        // One column cannot carry two meanings, and no field may overwrite the
        // key: a mis-mapped config is refused rather than written.
        if ($column === '' || $value === '' || $column === $ident || isset($fields[$column])) continue;
        $fields[$column] = $value;
    }
    if (!isset($fields[$uid])) return array();
    $assignments = array();
    $updateParams = array();
    foreach ($fields as $column => $value) {
        $assignments[] = '[' . $column . '] = ?';
        $updateParams[] = $value;
    }
    $updateParams[] = $identifier;
    $columns = array_keys($fields);
    return array(
        'update' => array(
            'UPDATE ' . $target . ' SET ' . implode(', ', $assignments) . ' WHERE [' . $ident . '] = ?',
            $updateParams,
        ),
        'exists' => array('SELECT 1 FROM ' . $target . ' WHERE [' . $ident . '] = ?', array($identifier)),
        'insert' => array(
            'INSERT INTO ' . $target . ' ([' . $ident . '], [' . implode('], [', $columns) . ']) VALUES (?, ' . implode(', ', array_fill(0, count($columns), '?')) . ')',
            array_merge(array($identifier), array_values($fields)),
        ),
    );
}

// $cards: identifier, source_uid, converted_uid, serial, expiry, policy.
// Returns status not_configured | written | failed plus written/total and the
// resolved table, so the export report and the audit trail can state what
// actually happened. write_uid selects which UID form reaches the column:
// "source" is the raw card UID (what the read side expects to find there),
// "converted" is the little-endian value this app stages for UDA.
function usmsmartSyswebPushCards(array $cards) {
    $config = usmsmartSyswebLiveConfig();
    $table = usmsmartSyswebWriteTable($config);
    $uidMode = strtolower(trim((string)($config['write_uid'] ?? 'source'))) === 'converted' ? 'converted' : 'source';
    $result = array('status' => 'not_configured', 'written' => 0, 'total' => count($cards), 'table' => $table, 'uid' => $uidMode, 'message' => '');
    if (!usmsmartSyswebWriteReady($config)) {
        $result['message'] = 'Penulisan ccadmin belum dikonfigurasi; eksport MySQL sahaja.';
        return $result;
    }
    $live = null;
    try {
        $live = usmsmartGetSyswebWritePdo($config);
        $columns = usmsmartSyswebLiveColumns($live, $table);
        $missing = array();
        $plan = usmsmartSyswebWritePlan($columns, is_array($config['card_columns'] ?? null) ? $config['card_columns'] : array(), $missing);
        if (count($missing)) {
            $result['message'] = 'Lajur ccadmin belum dipetakan: ' . implode(', ', $missing) . '. Tetapkan card_columns dalam konfigurasi.';
            return $result;
        }
        $prepared = array();
        foreach ($cards as $index => $card) {
            $values = array(
                'identifier' => trim((string)($card['identifier'] ?? '')),
                'uid' => $uidMode === 'converted' ? trim((string)($card['converted_uid'] ?? '')) : trim((string)($card['source_uid'] ?? '')),
                'serial' => trim((string)($card['serial'] ?? '')),
                'expiry' => trim((string)($card['expiry'] ?? '')),
                'policy' => trim((string)($card['policy'] ?? '')),
            );
            $statements = usmsmartSyswebWriteSql($table, $plan, $values);
            if (!count($statements)) {
                throw new RuntimeException('Rekod ' . ($values['identifier'] !== '' ? $values['identifier'] : '#' . ($index + 1)) . ' tidak lengkap untuk ditulis ke ' . $table . '.');
            }
            $prepared[] = $statements;
        }
        // One transaction for the batch: the card system receives all rows or
        // none, and re-running the export is the retry.
        $live->beginTransaction();
        foreach ($prepared as $statements) {
            $update = $live->prepare($statements['update'][0]);
            $update->execute($statements['update'][1]);
            if ($update->rowCount() >= 1) continue;
            // NOCOUNT and unchanged rows can report 0; confirm before inserting.
            $exists = $live->prepare($statements['exists'][0]);
            $exists->execute($statements['exists'][1]);
            if ($exists->fetchColumn()) continue;
            $insert = $live->prepare($statements['insert'][0]);
            $insert->execute($statements['insert'][1]);
        }
        $live->commit();
        $result['status'] = 'written';
        $result['written'] = count($prepared);
    } catch (Throwable $e) {
        if ($live instanceof PDO && $live->inTransaction()) {
            try { $live->rollBack(); } catch (Throwable $rollbackError) { error_log('USMSmart ccadmin rollback failed: ' . $rollbackError->getMessage()); }
        }
        // The driver message repeats the host and login name, so it goes to the
        // log only; the operator gets a message they can act on.
        error_log('USMSmart ccadmin write failed: ' . $e->getMessage());
        $result['status'] = 'failed';
        $result['message'] = 'Penulisan ke ccadmin gagal dan telah dibatalkan. Eksport MySQL kekal; jalankan semula eksport selepas menyemak sambungan dan kebenaran tulis.';
    }
    return $result;
}

// Snapshot rows plus live rows, with the provenance the callers have to report.
function usmsmartSyswebCardIndex($pdo, array $records, &$source) {
    $index = usmsmartGetCardRecordIndex($pdo);
    $index['live'] = usmsmartSyswebLiveCardIndex($records, $source);
    $index['live_mode'] = (($source['mode'] ?? '') === 'live') ? 'live' : 'unavailable';
    return $index;
}

function usmsmartGetCardRecordIndex($pdo) {
    $index = array('card' => array(), 'uda' => array());
    $bump = static function (&$bucket, $slot, $entry) {
        if (!isset($bucket[$slot])) $bucket[$slot] = array();
        $bucket[$slot][] = $entry;
    };
    try {
        $rows = $pdo->query("SELECT TRIM(matric) AS ident, TRIM(COALESCE(serial_no,'')) AS serial_no,
            SUBSTR(TRIM(COALESCE(expiry_date,'')),1,10) AS expiry_date, TRIM(COALESCE(policy,'')) AS policy,
            UPPER(TRIM(COALESCE(usm_uid,''))) AS usm_uid FROM students_view WHERE TRIM(COALESCE(matric,'')) <> ''")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $uid = (string)$row['usm_uid'];
            $bump($index['card'], 'student|' . strtolower((string)$row['ident']), array(
                'serial' => (string)$row['serial_no'],
                'expiry' => (string)$row['expiry_date'],
                'policy' => (string)$row['policy'],
                // students_view keeps the printed big-endian UID; the store keeps the converted form.
                'converted' => preg_match('/^(?:[0-9A-F]{2})+$/', $uid) === 1 ? usmsmartUidLittleEndian($uid) : '',
            ));
        }
    } catch (Throwable $e) { error_log('USMSmart reconciliation student source unavailable: ' . $e->getMessage()); }
    try {
        // This reader runs on a table that may predate the column, and before any staff endpoint
        // has had a chance to add it, so it creates and relocates first rather than losing the source.
        usmsmartMigrateStaffUidColumn($pdo);
        $rows = $pdo->query("SELECT TRIM(staff_id) AS staff_id, TRIM(COALESCE(staff_no,'')) AS staff_no,
            TRIM(COALESCE(card_serial,'')) AS card_serial, TRIM(COALESCE(card_uid,'')) AS card_uid,
            SUBSTR(TRIM(COALESCE(expiry_date,'')),1,10) AS expiry_date
            FROM usmsmart_staff_directory WHERE TRIM(COALESCE(staff_id,'')) <> '' OR TRIM(COALESCE(staff_no,'')) <> ''")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $storedSerial = trim((string)$row['card_serial']);
            $storedUid = strtoupper(trim((string)$row['card_uid']));
            // A byte-pair hex value sitting in card_serial is a UID the live sync had nowhere else to
            // put, so it is not offered as a serial; the UID is what proves the converted form instead.
            $serialIsUid = $storedSerial !== '' && usmsmartUidLittleEndian($storedSerial) !== '';
            $entry = array(
                'serial' => ($storedUid === '' && $serialIsUid) ? '' : $storedSerial,
                'expiry' => (string)$row['expiry_date'], 'policy' => '',
                'converted' => usmsmartUidLittleEndian($storedUid !== '' ? $storedUid : $storedSerial),
            );
            foreach (array($row['staff_id'], $row['staff_no']) as $alias) {
                $alias = strtolower(trim((string)$alias));
                if ($alias !== '') $bump($index['card'], 'staff|' . $alias, $entry);
            }
        }
    } catch (Throwable $e) { error_log('USMSmart reconciliation staff source unavailable: ' . $e->getMessage()); }
    try {
        $rows = $pdo->query("SELECT TRIM(matric) AS ident, LOWER(TRIM(COALESCE(cardholder_type,'student'))) AS holder_type,
            UPPER(TRIM(COALESCE(converted_uid,''))) AS converted_uid, TRIM(COALESCE(serial_no,'')) AS serial_no,
            TRIM(COALESCE(policy,'')) AS policy FROM uda_system_records WHERE TRIM(COALESCE(matric,'')) <> ''")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $type = strtolower((string)$row['holder_type']) === 'staff' ? 'staff' : 'student';
            $bump($index['uda'], $type . '|' . strtolower((string)$row['ident']), array(
                'serial' => (string)$row['serial_no'], 'expiry' => '', 'policy' => (string)$row['policy'],
                'converted' => (string)$row['converted_uid'],
            ));
        }
    } catch (Throwable $e) { error_log('USMSmart reconciliation UDA source unavailable: ' . $e->getMessage()); }
    return $index;
}

// One classification per staged record, shared by the validation centre, the reconcile endpoint and
// the AI aggregates, so the three can never disagree about what the card records say.
// Statuses: matched, uda_only, ambiguous, conflict, absent, skip (no identifier to look up).
// What one card source says about one staged row: is there a single unambiguous card for it, and
// which empty fields can be filled from it? Statuses: matched, uda_only, ambiguous, conflict, absent.
function usmsmartCardSourceVerdict(array $candidates, $origin, $converted, array $record) {
    if ($origin === 'none' || !count($candidates)) return array('status' => 'absent', 'hydrate' => array(), 'origin' => 'none');
    $variants = array();
    foreach ($candidates as $candidate) {
        $variants[strtolower((string)($candidate['serial'] ?? '')) . '|' . (string)($candidate['expiry'] ?? '') . '|' . strtoupper((string)($candidate['converted'] ?? ''))] = true;
    }
    $expected = '';
    foreach ($candidates as $candidate) { if ((string)($candidate['converted'] ?? '') !== '') { $expected = strtoupper((string)$candidate['converted']); break; } }
    $status = count($variants) > 1 ? 'ambiguous'
        : (($expected !== '' && $converted !== '' && $expected !== $converted) ? 'conflict'
        : ($origin === 'uda' ? 'uda_only' : 'matched'));
    // Only an unprovable row is worth completing: never fill anything when the card records
    // disagree, contradict the UID, or are missing entirely.
    $hydrate = array();
    if ($status === 'matched' || $status === 'uda_only') {
        foreach (array('serial_no' => 'serial', 'expiry_date' => 'expiry', 'policy' => 'policy') as $field => $key) {
            if (trim((string)($record[$field] ?? '')) !== '') continue;
            $values = array();
            foreach ($candidates as $candidate) if ((string)($candidate[$key] ?? '') !== '') $values[strtolower((string)$candidate[$key])] = (string)$candidate[$key];
            if (count($values) === 1) $hydrate[$field] = array_values($values)[0];
        }
    }
    return array('status' => $status, 'hydrate' => $hydrate, 'origin' => $origin);
}

function usmsmartClassifySyswebCardSources(array $records, array $index) {
    $out = array();
    foreach ($records as $position => $record) {
        if (!is_array($record)) { $out[$position] = array('status' => 'skip', 'hydrate' => array(), 'origin' => 'none'); continue; }
        $type = strtolower(trim((string)($record['type'] ?? 'student'))) === 'staff' ? 'staff' : 'student';
        $ident = strtolower(trim((string)($record['identifier'] ?? '')));
        if ($ident === '') { $out[$position] = array('status' => 'skip', 'hydrate' => array(), 'origin' => 'none'); continue; }
        $slot = $type . '|' . $ident;
        $card = isset($index['card'][$slot]) ? $index['card'][$slot] : array();
        $uda = isset($index['uda'][$slot]) ? $index['uda'][$slot] : array();
        $candidates = empty($card) ? $uda : $card;
        $origin = empty($candidates) ? 'none' : (empty($card) ? 'uda' : 'card');
        $converted = strtoupper(trim((string)($record['converted_uid'] ?? '')));
        $verdict = usmsmartCardSourceVerdict($candidates, $origin, $converted, $record);
        // The Sysweb card database is the system of record, so when it answers its verdict replaces
        // the local snapshot's. A disagreement between the two is reported, never silently merged,
        // and a live lookup that failed leaves the snapshot verdict untouched.
        $liveState = 'unknown';
        if ((string)($index['live_mode'] ?? 'unavailable') === 'live') {
            $liveRows = isset($index['live'][$ident]) ? $index['live'][$ident] : array();
            if (count($liveRows)) {
                $liveVerdict = usmsmartCardSourceVerdict($liveRows, 'sysweb', $converted, $record);
                $liveState = $liveVerdict['status'] === $verdict['status'] ? 'confirmed' : 'contradicted';
                $verdict = $liveVerdict;
            } else {
                $liveState = $verdict['status'] === 'absent' ? 'confirmed' : 'unconfirmed';
            }
        }
        $out[$position] = array('status' => $verdict['status'], 'hydrate' => $verdict['hydrate'],
            'origin' => $verdict['origin'], 'live' => $liveState);
    }
    return $out;
}

// Batch metadata only. The failed_records field in the history file keeps whole row payloads,
// so it is deliberately never read into anything that leaves this server.
function usmsmartSyswebHistorySummary() {
    $historyPath = USM_ROOT . DIRECTORY_SEPARATOR . 'sysweb_export_history.json';
    $history = is_file($historyPath) ? json_decode((string)file_get_contents($historyPath), true) : array();
    if (!is_array($history)) $history = array();
    $totals = array('batches'=>count($history), 'success'=>0, 'partial'=>0, 'failed'=>0);
    $recent = array();
    foreach ($history as $batch) {
        if (!is_array($batch)) continue;
        $status = (string)($batch['status'] ?? '');
        if (isset($totals[$status])) $totals[$status]++;
        $recent[] = array(
            'batch_id'=>(string)($batch['batch_id'] ?? ''),
            'timestamp'=>substr((string)($batch['created_at'] ?? ''), 0, 16),
            'exported_by'=>(string)($batch['user'] ?? ''),
            'source'=>(string)($batch['source'] ?? ''),
            'student_count'=>(int)($batch['student_count'] ?? 0),
            'staff_count'=>(int)($batch['staff_count'] ?? 0),
            'total'=>(int)($batch['total'] ?? 0),
            'saved_new'=>(int)($batch['saved_new'] ?? 0),
            'duplicates_skipped'=>(int)($batch['duplicates_skipped'] ?? 0),
            'failed_count'=>(int)($batch['failed_count'] ?? 0),
            'passwords_issued'=>(int)($batch['passwords_issued'] ?? 0),
            'status'=>$status
        );
    }
    return array('totals'=>$totals, 'recent_batches'=>array_slice(array_reverse($recent), 0, 4));
}

/* ---- One HTTP GET for the read-only service probes. -----------------------------------
   This php.ini has cURL commented out, so the stream wrapper is the transport that actually runs.
   Both are kept: a server that turns the extension back on gets it, and one that has neither is told
   plainly that the probe was skipped instead of being shown a false green. */
function usmsmartHttpFetch($url, $timeoutSeconds = 4) {
    $result = array('ok' => false, 'code' => 0, 'body' => '', 'error' => '', 'transport' => 'none');
    $timeoutSeconds = max(1, min(30, (int)$timeoutSeconds));
    if (function_exists('curl_init')) {
        $result['transport'] = 'curl';
        $handle = curl_init($url);
        curl_setopt_array($handle, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => $timeoutSeconds, CURLOPT_NOSIGNAL => 1));
        $body = curl_exec($handle);
        $result['code'] = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $result['error'] = trim((string)curl_error($handle));
        curl_close($handle);
        if ($body !== false) { $result['ok'] = true; $result['body'] = (string)$body; }
        return $result;
    }
    if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) return $result;
    $result['transport'] = 'stream';
    $context = stream_context_create(array('http' => array(
        'method' => 'GET',
        'timeout' => $timeoutSeconds,
        'follow_location' => 0,
        'ignore_errors' => true,
    )));
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        $last = error_get_last();
        $result['error'] = (is_array($last) && isset($last['message'])) ? (string)$last['message'] : 'The request did not complete';
        return $result;
    }
    $result['ok'] = true;
    $result['body'] = (string)$body;
    /* The wrapper hands back the body only; the status line is in the response header array PHP
       builds in the calling scope. ignore_errors means a 4xx or 5xx still gets here, so the code is
       worth reading rather than assuming 200. */
    $result['code'] = 200;
    if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
        $status = explode(' ', trim((string)$http_response_header[0]));
        if (count($status) >= 2 && ctype_digit((string)$status[1])) $result['code'] = (int)$status[1];
    }
    return $result;
}

/* Transports prefix their own name and the URL in front of the reason, which is the only part an
   operator reading a 340px panel needs. */
function usmsmartProbeError($message) {
    $message = trim(str_replace(array(chr(13), chr(10)), ' ', (string)$message));
    $pos = strrpos($message, ':');
    if ($pos !== false) $message = trim(substr($message, $pos + 1));
    if (strlen($message) > 96) $message = substr($message, 0, 96);
    return $message === '' ? 'the request failed' : $message;
}

// Open SQL Server only for endpoints that need the authoritative Pelajar/Staf source.
// A missing/unavailable SQL Server must not take down the existing MySQL application.
// ---------------------------------------------------------------------------
// Kampus Kejuruteraan scope. These schools exist in the source data but are not part of this portal's card
// programme, so every list and pull leaves them out. The rows themselves are never touched - both databases
// keep the original school value, and removing a name from this array puts the school straight back.
// ---------------------------------------------------------------------------
function usmsmartScopedOutSchools() {
    return array(
        'School of Computer Sciences',
        'School of Engineering',
        'School of Management',
        'School of Mathematical Sciences'
    );
}

function usmsmartIsScopedOutSchool($name) {
    $needle = strtolower(preg_replace('/\s+/', ' ', trim((string)$name)));
    foreach (usmsmartScopedOutSchools() as $blocked) {
        if ($needle === strtolower($blocked)) return true;
    }
    return false;
}

// The column name comes from this file, never from a request, and the values are code constants, so the
// predicate is assembled as a literal rather than pushing placeholders through the existing param arrays.
function usmsmartScopedOutPredicate($column) {
    $quoted = array();
    foreach (usmsmartScopedOutSchools() as $blocked) $quoted[] = "'" . str_replace("'", "''", $blocked) . "'";
    return "LTRIM(RTRIM(COALESCE({$column},''))) NOT IN (" . implode(', ', $quoted) . ')';
}

// Splice the predicate into a statement that is already written, instead of rewriting five SQL literals.
// students_view is selected whole, so it needs a WHERE opened rather than an AND prepended - guessing that
// wrong is a syntax error, and it takes the whole directory down with it.
function usmsmartApplyScopedOutSchools($sql, $column) {
    $need = (strpos($sql, ' WHERE ') === false ? ' WHERE ' : ' AND ') . usmsmartScopedOutPredicate($column);
    $at = stripos($sql, ' ORDER BY ');
    if ($at === false) return rtrim($sql) . $need;
    return substr_replace($sql, $need, $at, 0);
}

// ---------------------------------------------------------------------------
// Pusat pengajian -> polisi palang. One table, four links. Kejuruteraan Elektrik & Elektronik,
// Mekanikal and PPBLT are absent on purpose: those students genuinely have no package, so they keep an
// empty policy and still count under "Tiada polisi". usmsmartFillPolicyFromSchool writes into an empty
// field only, so a policy an operator chose is never overwritten. Removing a line here stops that link -
// nothing is stored anywhere and no existing record is touched.
// The keywords mirror sanitizeSchoolName() on the client so the same school string resolves to the same
// package in PHP and in the browser; the order matters for the same reason it does there.
// ---------------------------------------------------------------------------
function usmsmartSchoolPolicyRules() {
    return array(
        array(array('aero'), 'sa-stud_normal'),
        array(array('awam', 'civil'), 'sc-stud_normal'),
        array(array('bahan', 'materials', 'mineral'), 'sr-stud_undergrad'),
        array(array('kimia', 'chemical'), 'sce-stud_labcom')
    );
}

function usmsmartPolicyForSchool($school) {
    $needle = strtolower(preg_replace('/\s+/', ' ', trim((string)$school)));
    if ($needle === '') return '';
    foreach (usmsmartSchoolPolicyRules() as $rule) {
        foreach ($rule[0] as $keyword) {
            if (strpos($needle, $keyword) !== false) return $rule[1];
        }
    }
    return '';
}

// Empty in, derived out; anything else in, untouched out.
function usmsmartFillPolicyFromSchool($policy, $school) {
    if (trim((string)$policy) !== '') return $policy;
    $derived = usmsmartPolicyForSchool($school);
    return $derived === '' ? $policy : $derived;
}

function usmsmartGetSqlServerPdo() {
    static $connection = null;
    if ($connection instanceof PDO) return $connection;
    $connection = null;
    /* The connection file is optional: this application runs on MySQL alone. A bare require of a
       missing file prints a wrapper warning into the response before throwing, and System health then
       shows that warning where the JSON should start. */
    $connectionFile = USM_ROOT . '/sqlserver_connection.php';
    if (!is_file($connectionFile)) {
        throw new RuntimeException('sqlserver_connection.php is not installed next to index.php.');
    }
    include_once $connectionFile;
    if (!isset($sqlServerPdo) || !($sqlServerPdo instanceof PDO)) {
        throw new RuntimeException('Sambungan SQL Server tidak tersedia.');
    }
    $connection = $sqlServerPdo;
    return $connection;
}

// Persistent policy assignments live in MySQL; SQL Server remains the identity source.
function usmsmartEnsurePolicyAssignmentTable($pdo) {
    // Avoid executing CREATE TABLE (which can implicitly commit a MySQL transaction)
    // on every policy lookup during the UDA export loop.
    $check = $pdo->prepare("SHOW TABLES LIKE ?");
    $check->execute(array('usmsmart_card_policy_assignments'));
    if ($check->fetchColumn()) return;

    $pdo->exec("CREATE TABLE usmsmart_card_policy_assignments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        cardholder_type ENUM('student','staff') NOT NULL,
        source_identifier VARCHAR(100) NOT NULL,
        source_id VARCHAR(30) NOT NULL,
        policy VARCHAR(100) NOT NULL,
        assigned_by VARCHAR(80) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_policy_source (cardholder_type, source_identifier),
        INDEX idx_policy_name (policy)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function usmsmartGetExistingPolicies($pdo) {
    $st = $pdo->query("SELECT DISTINCT TRIM(policy) AS policy FROM students_view WHERE TRIM(COALESCE(policy,'')) <> '' ORDER BY policy");
    $legacy = $st->fetchAll(PDO::FETCH_COLUMN);
    try { usmsmartEnsureAccessPolicyTables($pdo); $managed = $pdo->query('SELECT policy_name FROM usmsmart_access_policies WHERE is_active=1 ORDER BY policy_name')->fetchAll(PDO::FETCH_COLUMN); return array_values(array_unique(array_merge($legacy,$managed))); } catch (Throwable $e) { error_log('USMSmart managed policy catalogue unavailable: '.$e->getMessage()); return $legacy; }
}

// Configurable smartcard access policy catalogue. Locations and rules are intentionally
// empty until an administrator enters the university-approved access requirements.
function usmsmartEnsureAccessPolicyTables($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_access_policies (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        policy_name VARCHAR(100) NOT NULL,
        description VARCHAR(500) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by VARCHAR(80) NOT NULL,
        updated_by VARCHAR(80) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_access_policy_name (policy_name),
        INDEX idx_access_policy_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_access_locations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        location_name VARCHAR(150) NOT NULL,
        description VARCHAR(500) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by VARCHAR(80) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_access_location_name (location_name),
        INDEX idx_access_location_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_access_policy_locations (
        policy_id BIGINT UNSIGNED NOT NULL,
        location_id BIGINT UNSIGNED NOT NULL,
        created_by VARCHAR(80) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (policy_id, location_id),
        CONSTRAINT fk_usm_apl_policy FOREIGN KEY (policy_id) REFERENCES usmsmart_access_policies(id) ON DELETE CASCADE,
        CONSTRAINT fk_usm_apl_location FOREIGN KEY (location_id) REFERENCES usmsmart_access_locations(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function usmsmartGetManagedAccessPolicies($pdo, $activeOnly = false) {
    usmsmartEnsureAccessPolicyTables($pdo);
    $sql = "SELECT p.id,p.policy_name,p.description,p.is_active,p.created_by,p.updated_by,p.created_at,p.updated_at,COUNT(pl.location_id) AS location_count FROM usmsmart_access_policies p LEFT JOIN usmsmart_access_policy_locations pl ON pl.policy_id=p.id";
    if ($activeOnly) $sql .= " WHERE p.is_active=1";
    $sql .= " GROUP BY p.id ORDER BY p.policy_name";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
// Reuse an explicit assignment first, then legacy policy mapping. Never invent a policy.
function usmsmartFindExistingCardPolicy($pdo, $identifier, $uid = '', $serialNo = '', $cardholderType = 'student') {
    $identifier = trim((string)$identifier);
    $uid = strtoupper(preg_replace('/\s+/', '', trim((string)$uid)));
    $serialNo = strtoupper(trim((string)$serialNo));
    $cardholderType = strtolower(trim((string)$cardholderType)) === 'staff' ? 'staff' : 'student';
    if ($identifier === '' && $uid === '' && $serialNo === '') return null;
    try {
        usmsmartEnsurePolicyAssignmentTable($pdo);
        if ($identifier !== '') {
            $assigned = $pdo->prepare('SELECT policy, NULL AS serial_no FROM usmsmart_card_policy_assignments WHERE cardholder_type=? AND UPPER(TRIM(source_identifier))=UPPER(?) LIMIT 1');
            $assigned->execute(array($cardholderType, $identifier));
            $assignment = $assigned->fetch(PDO::FETCH_ASSOC);
            if ($assignment) return $assignment;
        }
    } catch (Throwable $e) { error_log('USMSmart policy assignment lookup failed: '.$e->getMessage()); }
    $stmt = $pdo->prepare("SELECT policy, serial_no FROM students_view WHERE TRIM(COALESCE(policy,'')) <> '' AND ((? <> '' AND UPPER(TRIM(matric)) = UPPER(?)) OR (? <> '' AND UPPER(REPLACE(TRIM(usm_uid),' ','')) = UPPER(?)) OR (? <> '' AND UPPER(TRIM(serial_no)) = UPPER(?))) ORDER BY id DESC LIMIT 1");
    $stmt->execute(array($identifier,$identifier,$uid,$uid,$serialNo,$serialNo));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// Store the Student/Staff distinction on each converted UDA record without replacing the existing table.
function usmsmartEnsureUdaCardholderTypeColumn($pdo) {
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM uda_system_records LIKE 'cardholder_type'")->fetchAll(PDO::FETCH_ASSOC);
        if (!$columns) {
            $pdo->exec("ALTER TABLE uda_system_records ADD COLUMN cardholder_type ENUM('student','staff') NOT NULL DEFAULT 'student' AFTER matric");
        }
        return true;
    } catch (Throwable $e) {
        error_log('USMSmart UDA cardholder_type schema check failed: ' . $e->getMessage());
        throw new RuntimeException('Tidak dapat menyediakan medan jenis pemegang kad dalam uda_system_records. Semak kebenaran ALTER TABLE MySQL.');
    }
}

// Audit history is created automatically if the DB account has CREATE permission.
function ensureAuditTable($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_audit_log (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, action VARCHAR(60) NOT NULL, entity_type VARCHAR(60) NOT NULL, entity_id VARCHAR(100) NULL, matric VARCHAR(100) NULL, details TEXT NULL, actor VARCHAR(190) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_audit_created (created_at), INDEX idx_audit_matric (matric)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function auditLog($pdo, $actionName, $entityType, $entityId = null, $matric = null, $details = '') {
    try {
        ensureAuditTable($pdo);
        $stmt = $pdo->prepare("INSERT INTO usmsmart_audit_log (action, entity_type, entity_id, matric, details, actor) VALUES (?, ?, ?, ?, ?, ?)");
        $actor = isset($_SESSION['username']) ? (string)$_SESSION['username'] : 'system';
        $stmt->execute(array($actionName, $entityType, $entityId, $matric, $details, $actor));
    } catch (Exception $e) { error_log('USMSmart audit log failed: ' . $e->getMessage()); }
}

// The audit list and the audit statistics must agree on what a filter means, so the WHERE clause for
// usmsmart_audit_log is built in exactly one place: a search across the six text columns, an optional
// comma-separated action list, and an inclusive ISO date range.
function usmsmartAuditLogFilters(array $query) {
    $where = array(); $params = array();
    $search = trim((string)($query['search'] ?? ''));
    $actionFilter = trim((string)($query['actionFilter'] ?? ''));
    $startDate = trim((string)($query['startDate'] ?? ''));
    $endDate = trim((string)($query['endDate'] ?? ''));
    if ($search !== '') {
        $where[] = '(action LIKE ? OR entity_type LIKE ? OR COALESCE(entity_id, "") LIKE ? OR COALESCE(matric, "") LIKE ? OR COALESCE(details, "") LIKE ? OR COALESCE(actor, "") LIKE ?)';
        for ($i = 0; $i < 6; $i++) $params[] = '%' . $search . '%';
    }
    if ($actionFilter !== '') {
        $actList = array_values(array_filter(array_map('trim', explode(',', $actionFilter))));
        if (count($actList) === 1) {
            $where[] = 'action = ?';
            $params[] = $actList[0];
        } else if (count($actList) > 1) {
            $placeholders = implode(',', array_fill(0, count($actList), '?'));
            $where[] = 'action IN (' . $placeholders . ')';
            foreach ($actList as $act) $params[] = $act;
        }
    }
    if ($startDate !== '' && isValidIsoDate($startDate)) { $where[] = 'created_at >= ?'; $params[] = $startDate . ' 00:00:00'; }
    if ($endDate !== '' && isValidIsoDate($endDate)) { $where[] = 'created_at <= ?'; $params[] = $endDate . ' 23:59:59'; }
    return array('whereSql' => ($where ? (' WHERE ' . implode(' AND ', $where)) : ''), 'params' => $params);
}

// Charts colour events by what happened, not by the literal action string, because the log holds several
// names for one idea (create and save_student both add a record). The client colours from the category
// this function returns, so the mapping exists once and cannot drift out of the SQL.
function usmsmartAuditCategory($action) {
    $act = strtolower(trim((string)$action));
    if ($act === 'update') return 'update';
    if ($act === 'create' || $act === 'save_student') return 'create';
    if ($act === 'delete' || $act === 'delete_user' || $act === 'soft_delete_session') return 'delete';
    if ($act === 'import_csv') return 'import';
    if ($act === 'export_csv' || $act === 'export_audit_csv' || $act === 'dump_uda' || $act === 'uda_ccadmin_push') return 'export';
    if ($act === 'login' || $act === 'logout' || $act === 'login_throttled') return 'session';
    if (strpos($act, 'backup') !== false) return 'backup';
    if (strpos($act, 'restore') !== false) return 'restore';
    return 'other';
}


// Card snapshots are stored separately so older cards remain visible after a student record is edited.
function ensureStudentCardHistoryTable($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS usmsmart_student_card_history (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        student_id BIGINT UNSIGNED NOT NULL,
        matric VARCHAR(100) NULL,
        event_type VARCHAR(40) NOT NULL,
        name_snapshot VARCHAR(255) NULL,
        school_snapshot VARCHAR(255) NULL,
        uid_snapshot VARCHAR(190) NULL,
        serial_snapshot VARCHAR(100) NULL,
        policy_snapshot VARCHAR(100) NULL,
        joined_snapshot DATE NULL,
        expiry_snapshot DATE NULL,
        previous_snapshot JSON NULL,
        actor VARCHAR(190) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_card_history_student (student_id, created_at),
        INDEX idx_card_history_matric (matric)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function recordStudentCardHistory($pdo, $studentId, $eventType, $record, $previous = null) {
    ensureStudentCardHistoryTable($pdo);
    $stmt = $pdo->prepare("INSERT INTO usmsmart_student_card_history
        (student_id,matric,event_type,name_snapshot,school_snapshot,uid_snapshot,serial_snapshot,policy_snapshot,joined_snapshot,expiry_snapshot,previous_snapshot,actor)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $previousJson = $previous === null ? null : json_encode($previous, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt->execute(array((int)$studentId, $record['matric'] ?? null, $eventType, $record['name'] ?? null,
        $record['school'] ?? null, $record['usm_uid'] ?? null, $record['serial_no'] ?? null,
        $record['policy'] ?? null, $record['date_joined'] ?? null, $record['expiry_date'] ?? null,
        $previousJson, $_SESSION['username'] ?? 'system'));
}


/* ---- Profile pictures live outside the webroot, like the credential files. -----------
   This XAMPP install has GD commented out in php.ini, so the server cannot resize anything and must
   not try: the browser crops the pick to a 256px square on a canvas and posts a data URL, and this
   side only ever accepts what a canvas could have produced - a small JPEG with no EXIF, no GPS and
   no filename of its own. Files are written to C:/xampp/private/usmsmart_avatars/<user id>.jpg, a
   name built from an integer, so there is no path to traverse and nothing here can be executed as
   PHP. They are served by the my_avatar endpoint below rather than by URL, so no picture is
   reachable without a session. */
function usmsmartAvatarDirectory() {
    $override = trim((string)(getenv('USMSMART_AVATAR_DIR') ?: ''));
    $dir = $override !== '' ? $override : 'C:/xampp/private/usmsmart_avatars';
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) return null;
    return (is_dir($dir) && is_writable($dir)) ? $dir : null;
}

/* Renders one avatar chip in PHP, for the chrome that has to be correct before any script runs.
   The markup is exactly what paintAvatar() mutates on the client - an img plus an initials span inside
   the chip - so a picture set later in the session repaints in place instead of being rebuilt, and the
   two renderers cannot drift apart. An img tag cannot use the fetch wrapper, so the per-tab token rides
   in the URL; without it PHP resolves the boot session and answers 401. */
function usmsmartAvatarChipHtml($elementId, $userId, $version, $name, $extraClass, $tabToken) {
    $initial = strtoupper(substr(trim((string)$name), 0, 2));
    if ($initial === '') $initial = '--';
    $hasPicture = ((int)$userId > 0 && $version !== '');
    $src = '';
    if ($hasPicture) {
        $src = 'index.php?action=my_avatar&id=' . (int)$userId . '&v=' . rawurlencode($version);
        if (preg_match('/^[A-Za-z0-9_-]{32,64}$/', (string)$tabToken)) $src .= '&usm_tab=' . $tabToken;
    }
    $classes = 'usm-avatar ' . $extraClass . ($hasPicture ? ' has-picture' : '');
    $html = '<span class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '"';
    if ($elementId !== '') $html .= ' id="' . htmlspecialchars($elementId, ENT_QUOTES, 'UTF-8') . '"';
    // An empty src attribute makes the browser re-request the page, so the attribute stays off.
    $html .= '>' . ($hasPicture ? '<img alt="" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '">' : '<img alt="" hidden>');
    $html .= '<span>' . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') . '</span></span>';
    return $html;
}

function usmsmartAvatarFile($userId) {
    $dir = usmsmartAvatarDirectory();
    return $dir === null ? null : $dir . DIRECTORY_SEPARATOR . ((int)$userId) . '.jpg';
}

/* Never trust the data URL: the header must be exactly what the canvas emits, the payload must
   decode strictly, and the bytes must still be a real JPEG of a sane size after that. */
function usmsmartDecodeAvatarDataUrl($value) {
    $reject = static function ($message) { return array('ok'=>false,'bytes'=>null,'message'=>$message); };
    if (!is_string($value) || $value === '') return $reject('Tiada imej diterima.');
    $parts = explode(',', $value, 2);
    if (count($parts) !== 2) return $reject('Format imej tidak sah.');
    if (strtolower(trim($parts[0])) !== 'data:image/jpeg;base64') return $reject('Imej mesti dikonversi kepada JPEG.');
    $bytes = base64_decode($parts[1], true);
    if ($bytes === false) return $reject('Data imej rosak.');
    if (strlen($bytes) < 1024) return $reject('Imej terlalu kecil atau rosak.');
    if (strlen($bytes) > 400 * 1024) return $reject('Imej terlalu besar (maksimum 400 KB selepas saiz semula).');
    $info = @getimagesizefromstring($bytes);
    if (!is_array($info)) return $reject('Fail itu bukan imej yang boleh dibaca.');
    if ((int)$info[2] !== IMAGETYPE_JPEG) return $reject('Hanya JPEG diterima untuk gambar profil.');
    if ((int)$info[0] < 32 || (int)$info[1] < 32 || (int)$info[0] > 1024 || (int)$info[1] > 1024) {
        return $reject('Saiz imej tidak disokong (32-1024 px).');
    }
    return array('ok'=>true,'bytes'=>$bytes,'message'=>'');
}

// Server-side JSON backups are stored outside the document root by default, so a
// web-server misconfiguration cannot expose them. USMSMART_BACKUP_DIR overrides the
// location for deployments that mount backups elsewhere.
function usmsmartBackupDirectory() {
    $override = trim((string)getenv('USMSMART_BACKUP_DIR'));
    $dir = $override !== ''
        ? rtrim($override, "/\\")
        : dirname(USM_ROOT, 2) . DIRECTORY_SEPARATOR . 'usmsmart_private_backups';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Folder sandaran tidak dapat dibuat. Semak kebenaran folder projek.');
    }
    @chmod($dir, 0700);
    // Defense in depth only when an override puts the folder back inside the web root.
    $root = str_replace('\\', '/', (string)realpath(USM_ROOT));
    $target = str_replace('\\', '/', (string)realpath($dir));
    if ($root !== '' && $target !== '' && strpos($target . '/', $root . '/') === 0) {
        $deny = "Require all denied\nDeny from all\n";
        if (!is_file($dir . DIRECTORY_SEPARATOR . '.htaccess')) @file_put_contents($dir . DIRECTORY_SEPARATOR . '.htaccess', $deny, LOCK_EX);
        if (!is_file($dir . DIRECTORY_SEPARATOR . 'index.php')) @file_put_contents($dir . DIRECTORY_SEPARATOR . 'index.php', "<?php http_response_code(404); exit;", LOCK_EX);
    }
    return $dir;
}
function usmsmartBackupLog($event, $message, $file = null) {
    try {
        $dir = usmsmartBackupDirectory();
        $entry = array(
            'time' => date('Y-m-d H:i:s'),
            'event' => preg_replace('/[^a-z0-9_.-]/i', '_', (string)$event),
            'user' => (string)($_SESSION['username'] ?? 'system'),
            'file' => $file ? basename((string)$file) : null,
            'message' => substr(preg_replace('/[\r\n\t]+/', ' ', (string)$message), 0, 700)
        );
        @file_put_contents($dir . DIRECTORY_SEPARATOR . 'backup-operations.log', json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Exception $ignored) {
        error_log('USMSmart backup logging failed: ' . $event . ' - ' . $message);
    }
}
function usmsmartBuildBackupPayload($pdo) {
    $students = $pdo->query('SELECT * FROM students_view')->fetchAll(PDO::FETCH_ASSOC);
    $udaRecords = $pdo->query('SELECT * FROM uda_system_records')->fetchAll(PDO::FETCH_ASSOC);
    $sessions = $pdo->query('SELECT * FROM uda_transport_sessions')->fetchAll(PDO::FETCH_ASSOC);
    try { ensureAuditTable($pdo); $audit = $pdo->query('SELECT * FROM usmsmart_audit_log')->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $ignored) { $audit = array(); }
    return array(
        'backup_format' => 'USMSmart JSON backup', 'schema_version' => 2,
        'export_date' => date('Y-m-d H:i:s'), 'backup_type' => 'server',
        'counts' => array('students'=>count($students),'uda_records'=>count($udaRecords),'sessions'=>count($sessions),'audit_events'=>count($audit)),
        'database' => 'usmsmart_db', 'students_view'=>$students,
        'uda_system_records'=>$udaRecords, 'uda_transport_sessions'=>$sessions,
        'usmsmart_audit_log'=>$audit
    );
}
function usmsmartWriteServerBackup($pdo, $automatic = false) {
    $dir = usmsmartBackupDirectory();
    $payload = usmsmartBuildBackupPayload($pdo);
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) throw new RuntimeException('Data sandaran tidak dapat ditukar kepada JSON.');
    $stamp = date('Y-m-d_H-i-s');
    $prefix = $automatic ? 'usmsmart_auto_' : 'usmsmart_manual_';
    $file = $dir . DIRECTORY_SEPARATOR . $prefix . $stamp . '_' . bin2hex(random_bytes(3)) . '.json';
    $tmp = $file . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $file)) {
        @unlink($tmp); throw new RuntimeException('Fail sandaran tidak dapat disimpan. Semak ruang cakera dan kebenaran folder.');
    }
    @chmod($file, 0600);
    // Store a SHA-256 sidecar so later checks can detect accidental file changes.
    $hash = hash_file('sha256', $file);
    $hashPath = $file . '.sha256';
    if ($hash === false || @file_put_contents($hashPath, $hash . "  " . basename($file) . "\n", LOCK_EX) === false) {
        @unlink($file); @unlink($hashPath);
        throw new RuntimeException('Sandaran ditulis tetapi checksum tidak dapat disimpan; sandaran dibatalkan. Semak kebenaran folder.');
    }
    @chmod($hashPath, 0600);
    $verification = usmsmartVerifyBackupFile($file);
    if (empty($verification['valid'])) {
        @unlink($file); @unlink($hashPath);
        throw new RuntimeException('Pengesahan sandaran selepas simpan gagal: ' . ($verification['message'] ?? 'ralat tidak diketahui'));
    }
    // Retain the latest 14 days of server-side backups and their checksum files.
    foreach (glob($dir . DIRECTORY_SEPARATOR . 'usmsmart_*.json') ?: array() as $oldFile) {
        if (is_file($oldFile) && filemtime($oldFile) < time() - (14 * 86400)) { @unlink($oldFile); @unlink($oldFile . '.sha256'); }
    }
    return array('file'=>basename($file), 'size'=>filesize($file), 'created_at'=>date('Y-m-d H:i:s', filemtime($file)), 'counts'=>$payload['counts'], 'automatic'=>$automatic, 'verified'=>true, 'sha256'=>$hash);
}
function usmsmartVerifyBackupFile($file) {
    if (!is_file($file) || !is_readable($file)) return array('valid'=>false,'message'=>'Fail tiada atau tidak boleh dibaca.');
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') return array('valid'=>false,'message'=>'Fail kosong atau gagal dibaca.');
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) return array('valid'=>false,'message'=>'JSON rosak atau tidak lengkap.');
    if (($data['backup_format'] ?? '') !== 'USMSmart JSON backup' || !isset($data['students_view']) || !is_array($data['students_view'])) return array('valid'=>false,'message'=>'Format sandaran tidak dikenali.');
    foreach (array('uda_system_records','uda_transport_sessions','usmsmart_audit_log') as $key) {
        if (isset($data[$key]) && !is_array($data[$key])) return array('valid'=>false,'message'=>'Struktur tidak sah: ' . $key);
    }
    foreach ($data['students_view'] as $record) {
        if (!is_array($record) || empty($record['matric']) || empty($record['name'])) return array('valid'=>false,'message'=>'Terdapat rekod pelajar yang tidak lengkap.');
    }
    $counts = array('students'=>count($data['students_view']), 'uda_records'=>count($data['uda_system_records'] ?? array()), 'sessions'=>count($data['uda_transport_sessions'] ?? array()), 'audit_events'=>count($data['usmsmart_audit_log'] ?? array()));
    if (isset($data['counts']) && is_array($data['counts'])) {
        foreach ($counts as $key=>$count) if (isset($data['counts'][$key]) && (int)$data['counts'][$key] !== $count) return array('valid'=>false,'message'=>'Bilangan rekod tidak sepadan (' . $key . ').');
    }
    $hashPath = $file . '.sha256';
    if (is_file($hashPath)) {
        $expected = trim((string)@file_get_contents($hashPath));
        $expected = preg_split('/\s+/', $expected)[0] ?? '';
        $actual = hash('sha256', $raw);
        if (!preg_match('/^[a-f0-9]{64}$/i', $expected) || !hash_equals(strtolower($expected), strtolower($actual))) return array('valid'=>false,'message'=>'Checksum SHA-256 tidak sepadan; fail mungkin berubah atau rosak.','counts'=>$counts);
        return array('valid'=>true,'message'=>'Lulus struktur JSON, rekod dan checksum SHA-256.','counts'=>$counts,'sha256'=>$actual,'checksum'=>true);
    }
    return array('valid'=>true,'message'=>'Struktur dan rekod lulus; sandaran lama tiada checksum SHA-256.','counts'=>$counts,'checksum'=>false);
}
function usmsmartListServerBackups() {
    $dir = usmsmartBackupDirectory(); $items = array();
    foreach (glob($dir . DIRECTORY_SEPARATOR . 'usmsmart_*.json') ?: array() as $file) {
        if (!is_file($file)) continue;
        $check = usmsmartVerifyBackupFile($file);
        $items[] = array('file'=>basename($file),'size'=>filesize($file),'created_at'=>date('Y-m-d H:i:s',filemtime($file)),'automatic'=>strpos(basename($file),'usmsmart_auto_') === 0,'verified'=>!empty($check['valid']),'verification_message'=>$check['message']);
    }
    usort($items, function($a,$b){ return strcmp($b['created_at'],$a['created_at']); });
    return $items;
}

        function usmsmartEnsureStudentClassificationColumns(PDO $pdo): void {
            $columns = $pdo->query("SHOW COLUMNS FROM students_view")->fetchAll(PDO::FETCH_COLUMN, 0);
            $map = array_change_key_case(array_flip($columns), CASE_LOWER);
            if (!isset($map['nationality'])) $pdo->exec("ALTER TABLE students_view ADD COLUMN nationality VARCHAR(100) NULL");
            if (!isset($map['education_level'])) $pdo->exec("ALTER TABLE students_view ADD COLUMN education_level VARCHAR(100) NULL");
            if (!isset($map['student_category'])) $pdo->exec("ALTER TABLE students_view ADD COLUMN student_category VARCHAR(30) NULL");
        }
