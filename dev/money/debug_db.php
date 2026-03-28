<?php
require_once __DIR__ . '/class/DBC.php';
$db = new DBC();
try {
    $columns = $db->select("SHOW COLUMNS FROM money_receipt_master");
    echo json_encode($columns, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
