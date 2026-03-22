<?php
require 'config.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['admin_id'] = 1;
$_SESSION['csrf_token'] = 'x';
$_POST['action'] = 'get_users';
$_POST['csrf_token'] = 'x';
$_POST['search'] = '';
$_POST['filter'] = 'all';

// Simulate JSON input
$data = ['action' => 'get_users', 'csrf_token' => 'x', 'search' => '', 'filter' => 'all'];

// Intercept api/admin.php
// api/admin.php uses $data = json_decode(file_get_contents('php://input'), true);
// it falls back to $_POST if needed? No, it hardcodes file_get_contents('php://input')
// So we can just require our own mini version if we want, OR:

ob_start();
$adminId = 1;

try {
	$whereClause = "WHERE u.role = 'customer' AND (u.name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
    $search = '%%';

    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.phone, u.email, u.shop_address, u.created_at, u.is_blocked, u.pay_later_plan, u.pay_later_status,
               COUNT(o.id) as total_orders,
               COALESCE(SUM(p.amount),0) as total_spent
        FROM users u
        LEFT JOIN orders o ON o.user_id = u.id AND o.status = 'delivered'
        LEFT JOIN payments p ON p.user_id = u.id AND p.status = 'completed'
        $whereClause
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$search, $search, $search]);
    $users = $stmt->fetchAll();
    echo json_encode(['success' => true, 'error' => null]);
} catch (Exception $e) {
	echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$out = ob_get_clean();
echo $out;
