<?php
require_once '../config.php';
header('Content-Type: application/json');

function respond($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

$ct     = $_SERVER['CONTENT_TYPE'] ?? '';
$isJson = str_contains($ct, 'application/json');
$data   = $isJson ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];
$action = $data['action'] ?? $_POST['action'] ?? '';

// CSRF check
$headers    = getallheaders();
$csrfToken  = $headers['X-CSRF-Token'] ?? $data['csrf_token'] ?? $_POST['csrf_token'] ?? '';
$serverCsrf = $_SESSION['csrf_token'] ?? '';
if (empty($serverCsrf) || !hash_equals($serverCsrf, $csrfToken)) {
    respond(false, 'Invalid CSRF token.');
}

// ─── PUBLIC: get_products ─────────────────────────────────────────────────────
if ($action === 'get_products') {
    $activeOnly = ($data['active_only'] ?? true) !== false;
    // Admins can see inactive products if they want
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    
    $whereClause = "";
    if ($activeOnly && !$isAdmin) {
        $whereClause = "WHERE status = 'active'";
    } else if ($activeOnly && $isAdmin) {
        $whereClause = "WHERE status = 'active'"; // If admin explicitly asks for active only
    }

    $stmt = $pdo->prepare("SELECT * FROM marketplace_products $whereClause ORDER BY created_at DESC");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast numerics properly
    foreach ($products as &$prod) {
        $prod['id'] = (int)$prod['id'];
        $prod['price'] = (float)$prod['price'];
        $prod['stock'] = (int)$prod['stock'];
    }
    unset($prod);

    respond(true, 'Products fetched.', ['products' => $products]);
}

// ─── ADMIN ONLY beyond this point ─────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    respond(false, 'Admin access only.');
}

// ─── CREATE PRODUCT ───────────────────────────────────────────────────────────
if ($action === 'create_product') {
    $name     = trim(htmlspecialchars(strip_tags($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $category = trim(htmlspecialchars(strip_tags($_POST['category'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $size     = trim(htmlspecialchars(strip_tags($_POST['size'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $price    = (float)($_POST['price'] ?? 0);
    $stock    = (int)($_POST['stock'] ?? 0);

    if (empty($name) || empty($category) || empty($size) || $price <= 0) {
        respond(false, 'All fields (name, category, size, positive price) are required.');
    }

    $imageUrl = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) respond(false, 'Image must be under 5 MB.');
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, ['image/jpeg','image/png','image/jpg','image/webp'])) {
            respond(false, 'Only JPG/PNG/WEBP images allowed.');
        }
        $ext      = match($mime) { 'image/png'=>'png','image/webp'=>'webp', default=>'jpg' };
        $filename = 'mkt_product_' . uniqid() . '.' . $ext;
        $destPath = __DIR__ . '/../uploads/products/';
        
        if (!is_dir($destPath)) {
            mkdir($destPath, 0777, true);
        }
        
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath . $filename)) respond(false, 'Failed to save image.');
        $imageUrl = 'uploads/products/' . $filename;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO marketplace_products (name, category, size, price, image, stock) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$name, $category, $size, $price, $imageUrl, $stock]);
        respond(true, "Marketplace product created!", ['product_id' => $pdo->lastInsertId()]);
    } catch (\Exception $e) {
        respond(false, 'DB Error: ' . $e->getMessage());
    }
}

// ─── UPDATE PRODUCT ────────────────────────────────────────────────────────────
if ($action === 'update_product') {
    $pid      = (int)($_POST['product_id'] ?? $data['product_id'] ?? 0);
    $name     = trim(htmlspecialchars(strip_tags($_POST['name'] ?? $data['name'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $category = trim(htmlspecialchars(strip_tags($_POST['category'] ?? $data['category'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $size     = trim(htmlspecialchars(strip_tags($_POST['size'] ?? $data['size'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $price    = (float)($_POST['price'] ?? $data['price'] ?? 0);
    $stock    = (int)($_POST['stock'] ?? $data['stock'] ?? -1);

    if (!$pid || empty($name) || empty($category) || empty($size) || $price <= 0) {
        respond(false, 'Valid Product ID, Name, Category, Size, and Price are required.');
    }

    try {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $_FILES['image']['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime, ['image/jpeg','image/png','image/jpg','image/webp'])) respond(false, 'Invalid image format.');
            
            $ext = match($mime) { 'image/png'=>'png','image/webp'=>'webp', default=>'jpg' };
            $filename = 'mkt_product_' . uniqid() . '.' . $ext;
            $destPath = __DIR__ . '/../uploads/products/';
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath . $filename)) respond(false, 'Failed to save image.');
            $imageUrl = 'uploads/products/' . $filename;

            // Optional: delete old image here if needed
            
            $sql = "UPDATE marketplace_products SET name=?, category=?, size=?, price=?, stock=?, image=? WHERE id=?";
            $params = [$name, $category, $size, $price, $stock, $imageUrl, $pid];
        } else {
            // No image update
            if ($stock >= 0) {
                $sql = "UPDATE marketplace_products SET name=?, category=?, size=?, price=?, stock=? WHERE id=?";
                $params = [$name, $category, $size, $price, $stock, $pid];
            } else {
                $sql = "UPDATE marketplace_products SET name=?, category=?, size=?, price=? WHERE id=?";
                $params = [$name, $category, $size, $price, $pid];
            }
        }

        $pdo->prepare($sql)->execute($params);
        respond(true, 'Product updated.');
    } catch(\Exception $e) { respond(false, 'DB Error: '.$e->getMessage()); }
}

// ─── TOGGLE PRODUCT ────────────────────────────────────────────────────────────
if ($action === 'toggle_product') {
    $pid = (int)($data['product_id'] ?? 0);
    if (!$pid) respond(false, 'Invalid product.');
    
    // Switch between active and inactive
    $stmt = $pdo->prepare("UPDATE marketplace_products SET status = IF(status='active', 'inactive', 'active') WHERE id=?");
    $stmt->execute([$pid]);
    respond(true, 'Product toggled.');
}

// ─── DELETE PRODUCT ────────────────────────────────────────────────────────────
if ($action === 'delete_product') {
    $pid = (int)($data['product_id'] ?? 0);
    if (!$pid) respond(false, 'Invalid product.');
    
    // Soft delete or hard delete? Actually, just hard delete if no orders, else fail. Or maybe just set to inactive.
    try {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_order_items WHERE product_id = ?");
        $checkStmt->execute([$pid]);
        if ($checkStmt->fetchColumn() > 0) {
            respond(false, 'Cannot delete product: It is used in existing orders. Consider toggling it to inactive.');
        }

        $pdo->prepare("DELETE FROM marketplace_products WHERE id=?")->execute([$pid]);
        respond(true, 'Product deleted.');
    } catch(\Exception $e) { respond(false, 'DB Error: '.$e->getMessage()); }
}

respond(false, 'Unknown action.');
