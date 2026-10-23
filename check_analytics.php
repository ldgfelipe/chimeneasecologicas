<?php
require 'api/db.php';
$cfg = db_load_settings();
if ($cfg === null) {
    echo "NULL\n";
    exit;
}
echo isset($cfg['analyticsId']) ? $cfg['analyticsId'] : "MISSING";
echo "\n";
