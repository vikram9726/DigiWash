<?php
require_once 'config.php';
// Add is_blocked column if missing
try {
    $pdo->query("ALTER TABLE users ADD COLUMN is_blocked TINYINT(1) DEFAULT 0");
    echo "Added is_blocked column.\n";
} catch(Exception $e) { echo "is_blocked already exists.\n"; }
echo "Done.\n";
