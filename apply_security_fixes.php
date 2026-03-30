<?php
require_once __DIR__ . '/config.php';

$results = [];

// 1. Create otp_attempts table (ISSUE-002 prerequisite)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS otp_attempts (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            phone       VARCHAR(20),
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_phone_time (phone, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = "✅ otp_attempts table created (or already exists).";
} catch (Exception $e) {
    $results[] = "❌ otp_attempts: " . $e->getMessage();
}

// 2. Add composite indexes on orders table
$indexes = [
    ['orders',       'idx_user_status',     'user_id, status'],
    ['orders',       'idx_delivery_status', 'delivery_id, status'],
    ['orders',       'idx_market_status',   'market_id, status'],
    ['orders',       'idx_created_at',      'created_at'],
    ['payments',     'idx_user_status',     'user_id, status'],
    ['payments',     'idx_order_status',    'order_id, status'],
    ['notifications','idx_user_read',       'user_id, is_read'],
    ['users',        'idx_role_online',     'role, is_online'],
    ['users',        'idx_market_role',     'market_id, role'],
];

foreach ($indexes as [$table, $idxName, $cols]) {
    try {
        // Check if index already exists
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
              AND INDEX_NAME   = ?
        ");
        $stmt->execute([$table, $idxName]);
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("ALTER TABLE `$table` ADD INDEX `$idxName` ($cols)");
            $results[] = "✅ Index `$idxName` added to `$table`.";
        } else {
            $results[] = "ℹ️  Index `$idxName` on `$table` already exists.";
        }
    } catch (Exception $e) {
        $results[] = "❌ Index `$idxName` on `$table`: " . $e->getMessage();
    }
}

echo "<pre style='font-family:monospace;font-size:14px;padding:20px;'>";
echo "<strong>DigiWash — Security & DB Migration Script</strong>\n\n";
foreach ($results as $r) {
    echo $r . "\n";
}
echo "\n<strong>Done.</strong> <a href='index.php'>Back to site</a>";
echo "</pre>";
