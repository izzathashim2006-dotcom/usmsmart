<?php
/*
 * Prints the structure of every USMSmart table, without rows, as SQL that can
 * be committed and replayed. Reads the same config/database.local.php the app
 * uses; it never writes to the database.
 *
 *     php tools/dump-schema.php > database/schema.sql
 *
 * AUTO_INCREMENT counters are stripped so the file only changes when the
 * schema does, not every time a row is inserted.
 */

$configFile = __DIR__ . '/../config/database.local.php';
$config = is_file($configFile) ? require $configFile : array();
$host = (string)($config['host'] ?? (getenv('USM_DB_HOST') ?: 'localhost'));
$dbname = (string)($config['database'] ?? (getenv('USM_DB_NAME') ?: 'usmsmart_db'));
$username = (string)($config['username'] ?? (getenv('USM_DB_USER') ?: ''));
$password = (string)($config['password'] ?? (getenv('USM_DB_PASS') ?: ''));

$pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
$definitions = array();
foreach ($tables as $table) {
    $name = (string)$table[0];
    // Only the tables this application owns; leave anything else in the
    // database (other tools, scratch copies) out of the dump.
    if (!preg_match('/^(usmsmart_|students_view$|uda_)/', $name)) {
        continue;
    }
    $row = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $name) . '`')->fetch(PDO::FETCH_NUM);
    $sql = preg_replace('/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', (string)$row[1]);
    $sql = preg_replace('/ AUTO_INCREMENT=\d+/', '', $sql);
    // SHOW CREATE TABLE returns no terminator. Without one the mysql client
    // reads the whole file as a single statement and every import fails.
    $definitions[$name] = rtrim($sql, "; \t\n\r\0\x0B") . ';';
}
ksort($definitions);

echo implode("\n\n", $definitions), "\n";
fwrite(STDERR, 'dumped ' . count($definitions) . " tables\n");
