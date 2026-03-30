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
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

    $whereClause = "";
    if ($activeOnly && !$isAdmin) {
        $whereClause = "WHERE mp.status = 'active'";
    } else if ($activeOnly && $isAdmin) {
        $whereClause = "WHERE mp.status = 'active'";
    }

    $stmt = $pdo->prepare("SELECT mp.* FROM marketplace_products mp $whereClause ORDER BY mp.created_at DESC");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as &$prod) {
        $prod['id']    = (int)$prod['id'];
        $prod['price'] = (float)$prod['price'];
        $prod['stock'] = (int)$prod['stock'];

        // Fetch widths for this product
        $ws = $pdo->prepare("SELECT * FROM marketplace_product_widths WHERE product_id = ? ORDER BY id ASC");
        $ws->execute([$prod['id']]);
        $prod['widths'] = $ws->fetchAll(PDO::FETCH_ASSOC);
        foreach ($prod['widths'] as &$w) {
            $w['id']             = (int)$w['id'];
            $w['price_per_meter'] = (float)$w['price_per_meter'];
        }
        unset($w);
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
    $widths   = json_decode($_POST['widths'] ?? '[]', true) ?: [];

    if (empty($name) || empty($category) || empty($size) || $price < 0) {
        respond(false, 'All fields (name, category, size) are required.');
    }

    $imageUrl = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) respond(false, 'Image must be under 5 MB.');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ['image/jpeg','image/png','image/jpg','image/webp'])) respond(false, 'Only JPG/PNG/WEBP images allowed.');
        $ext      = match($mime) { 'image/png'=>'png','image/webp'=>'webp', default=>'jpg' };
        $filename = 'mkt_product_' . uniqid() . '.' . $ext;
        $destPath = __DIR__ . '/../uploads/products/';
        if (!is_dir($destPath)) mkdir($destPath, 0777, true);
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath . $filename)) respond(false, 'Failed to save image.');
        $imageUrl = 'uploads/products/' . $filename;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO marketplace_products (name, category, size, price, image, stock) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$name, $category, $size, $price, $imageUrl, $stock]);
        $pid = (int)$pdo->lastInsertId();

        // Insert widths
        foreach ($widths as $w) {
            $wLabel = trim(htmlspecialchars(strip_tags($w['label'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $wPrice = (float)($w['price_per_meter'] ?? 0);
            if (!$wLabel || $wPrice <= 0) continue;
            $pdo->prepare("INSERT INTO marketplace_product_widths (product_id, label, price_per_meter) VALUES (?,?,?)")
                ->execute([$pid, $wLabel, $wPrice]);
        }

        respond(true, "Marketplace product created!", ['product_id' => $pid]);
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
    $widths   = json_decode($_POST['widths'] ?? '[]', true) ?: [];

    if (!$pid || empty($name) || empty($category) || empty($size) || $price < 0) {
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
            $sql    = "UPDATE marketplace_products SET name=?, category=?, size=?, price=?, stock=?, image=? WHERE id=?";
            $params = [$name, $category, $size, $price, $stock, $imageUrl, $pid];
        } else {
            if ($stock >= 0) {
                $sql    = "UPDATE marketplace_products SET name=?, category=?, size=?, price=?, stock=? WHERE id=?";
                $params = [$name, $category, $size, $price, $stock, $pid];
            } else {
                $sql    = "UPDATE marketplace_products SET name=?, category=?, size=?, price=? WHERE id=?";
                $params = [$name, $category, $size, $price, $pid];
            }
        }

        $pdo->prepare($sql)->execute($params);

        // Replace widths: delete old, insert new
        $pdo->prepare("DELETE FROM marketplace_product_widths WHERE product_id=?")->execute([$pid]);
        foreach ($widths as $w) {
            $wLabel = trim(htmlspecialchars(strip_tags($w['label'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $wPrice = (float)($w['price_per_meter'] ?? 0);
            if (!$wLabel || $wPrice <= 0) continue;
            $pdo->prepare("INSERT INTO marketplace_product_widths (product_id, label, price_per_meter) VALUES (?,?,?)")
                ->execute([$pid, $wLabel, $wPrice]);
        }

        respond(true, 'Product updated.');
    } catch(\Exception $e) { respond(false, 'DB Error: '.$e->getMessage()); }
}

// ─── TOGGLE PRODUCT ────────────────────────────────────────────────────────────
if ($action === 'toggle_product') {
    $pid = (int)($data['product_id'] ?? $_POST['id'] ?? $data['id'] ?? 0);
    if (!$pid) respond(false, 'Invalid product.');
    $stmt = $pdo->prepare("UPDATE marketplace_products SET status = IF(status='active', 'inactive', 'active') WHERE id=?");
    $stmt->execute([$pid]);
    respond(true, 'Product toggled.');
}

// ─── DELETE PRODUCT ────────────────────────────────────────────────────────────
if ($action === 'delete_product') {
    $pid = (int)($data['product_id'] ?? 0);
    if (!$pid) respond(false, 'Invalid product.');
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
