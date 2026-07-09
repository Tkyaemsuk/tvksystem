<?php
require 'db.php';

$db = new Database();

try {
    $db->query("ALTER TABLE students ADD COLUMN gmail VARCHAR(255) NULL");
    echo "Column 'gmail' added successfully to 'students' table.";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'gmail' already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
