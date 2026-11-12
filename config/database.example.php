<?php
/*
 * Copy to config/database.local.php and fill in the local values.
 * That file is gitignored; this example is the committed reference.
 * On a shared server, prefer keeping the file outside the web root or omitting
 * it entirely: USM_DB_HOST, USM_DB_NAME, USM_DB_USER and USM_DB_PASS in the
 * environment win over this file (an empty value is honoured on purpose), and
 * USM_DB_CONFIG may point at a credentials file outside the web root.
 */
return array(
    'host' => 'localhost',
    'database' => 'usmsmart_db',
    'username' => 'root',
    'password' => '',
);
