<?php
require_once 'config.php';
$sql = "CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'resolved') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";
try {
    $pdo->exec($sql);
    echo "Table created successfully\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
file_put_contents('schema.sql', "\n-- Contact Messages Table\n" . $sql, FILE_APPEND);
?>
