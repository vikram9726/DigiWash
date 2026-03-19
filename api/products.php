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

// Determine action (JSON or FormData)
$isJson  = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
$data    = $isJson ? json_decode(file_get_contents('php://input'), true) : [];
$action  = $data['action'] ?? $_POST['action'] ?? '';

// CSRF check
$headers   = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? ($data['csrf_token'] ?? '') ?? $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    respond(false, 'Invalid CSRF token.');
}

// ─── PUBLIC: Get all active products with their prices ───────────────────────
if ($action === 'get_products') {
    // active_only defaults to true; admin passes false to see all
    $activeOnly = ($data['active_only'] ?? true) !== false;
    $where = $activeOnly ? "WHERE p.is_active = 1" : "";
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.description, p.image_url, p.is_active, p.sort_order,
               JSON_ARRAYAGG(
                   JSON_OBJECT('id', pp.id, 'size_label', pp.size_label, 'price', pp.price, 'unit', pp.unit)
               ) as prices
        FROM products p
        LEFT JOIN product_prices pp ON p.id = pp.product_id
        $where
        GROUP BY p.id
        ORDER BY p.sort_order ASC, p.created_at ASC
    ");
    $stmt->execute([]);
    $products = $stmt->fetchAll();
    foreach ($products as &$prod) {
        $decoded = json_decode($prod['prices'], true) ?? [];
        $prod['prices'] = array_values(array_filter($decoded, fn($p) => $p['id'] !== null));
    }
    unset($prod);
    respond(true, 'Products fetched', ['products' => $products]);
}


// ─── ADMIN ONLY beyond this point ─────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    respond(false, 'Admin access only.');
}

// ─── CREATE PRODUCT (with image upload) ──────────────────────────────────────
if ($action === 'create_product') {
    $name        = htmlspecialchars(strip_tags($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars(strip_tags($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
    $sortOrder   = (int)($_POST['sort_order'] ?? 0);

    if (empty($name)) respond(false, 'Product name is required.');

    // Handle image upload
    $imageUrl = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) respond(false, 'Image must be under 5MB.');

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mime     = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, ['image/jpeg','image/png','image/jpg','image/webp'])) {
            respond(false, 'Only JPG/PNG/WEBP images allowed.');
        }
        $ext      = match($mime) { 'image/png'=>'png','image/webp'=>'webp',default=>'jpg' };
        $filename = 'product_' . uniqid() . '.' . $ext;
        $destPath = __DIR__ . '/../uploads/products/' . $filename;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
            respond(false, 'Failed to save image.');
        }
        $imageUrl = 'uploads/products/' . $filename;
    }

    // Parse prices JSON
    $pricesJson = $_POST['prices'] ?? '[]';
    $prices = json_decode($pricesJson, true);
    if (!is_array($prices) || empty($prices)) respond(false, 'At least one pricing tier is required.');

    foreach ($prices as $tier) {
        if (empty($tier['size_label']) || !isset($tier['price']) || (float)$tier['price'] <= 0) {
            respond(false, 'Each pricing tier needs a size label and valid price.');
        }
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO products (name, description, image_url, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $description, $imageUrl, $sortOrder]);
        $productId = $pdo->lastInsertId();

        $priceStmt = $pdo->prepare("INSERT INTO product_prices (product_id, size_label, price, unit) VALUES (?, ?, ?, ?)");
        foreach ($prices as $tier) {
            $priceStmt->execute([$productId, trim($tier['size_label']), (float)$tier['price'], $tier['unit'] ?? 'per piece']);
        }
        $pdo->commit();
        respond(true, "Product \"$name\" created successfully!", ['product_id' => $productId]);
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, 'DB Error: ' . $e->getMessage());
    }
}

// ─── UPDATE PRODUCT ────────────────────────────────────────────────────────────
if ($action === 'update_product') {
    $productId   = (int)($data['product_id'] ?? 0);
    $name        = htmlspecialchars(strip_tags($data['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars(strip_tags($data['description'] ?? ''), ENT_QUOTES, 'UTF-8');
    $sortOrder   = (int)($data['sort_order'] ?? 0);
    if (!$productId || empty($name)) respond(false, 'Product ID and name required.');
    try {
        $pdo->prepare("UPDATE products SET name=?, description=?, sort_order=? WHERE id=?")->execute([$name,$description,$sortOrder,$productId]);
        respond(true, 'Product updated.');
    } catch(\Exception $e) { respond(false, 'DB Error: ' . $e->getMessage()); }
}

// ─── TOGGLE PRODUCT ACTIVE ─────────────────────────────────────────────────────
if ($action === 'toggle_product') {
    $productId = (int)($data['product_id'] ?? 0);
    if (!$productId) respond(false, 'Invalid product.');
    $pdo->prepare("UPDATE products SET is_active = NOT is_active WHERE id=?")->execute([$productId]);
    respond(true, 'Product visibility toggled.');
}

// ─── DELETE PRODUCT ────────────────────────────────────────────────────────────
if ($action === 'delete_product') {
    $productId = (int)($data['product_id'] ?? 0);
    if (!$productId) respond(false, 'Invalid product.');
    try {
        // Delete image file
        $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id=?");
        $stmt->execute([$productId]);
        $prod = $stmt->fetch();
        if ($prod && $prod['image_url']) {
            $imgPath = __DIR__ . '/../' . $prod['image_url'];
            if (file_exists($imgPath)) unlink($imgPath);
        }
        $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$productId]);
        respond(true, 'Product deleted.');
    } catch(\Exception $e) { respond(false, 'DB Error: ' . $e->getMessage()); }
}

// ─── ADD / UPDATE PRICE TIER ────────────────────────────────────────────────────
if ($action === 'upsert_price') {
    $priceId   = (int)($data['price_id'] ?? 0);
    $productId = (int)($data['product_id'] ?? 0);
    $sizeLabel = htmlspecialchars(strip_tags($data['size_label'] ?? ''), ENT_QUOTES, 'UTF-8');
    $price     = (float)($data['price'] ?? 0);
    $unit      = htmlspecialchars(strip_tags($data['unit'] ?? 'per piece'), ENT_QUOTES, 'UTF-8');
    if ((!$priceId && !$productId) || empty($sizeLabel) || $price <= 0) respond(false, 'Invalid data.');
    try {
        if ($priceId) {
            $pdo->prepare("UPDATE product_prices SET size_label=?, price=?, unit=? WHERE id=?")->execute([$sizeLabel,$price,$unit,$priceId]);
        } else {
            $pdo->prepare("INSERT INTO product_prices (product_id, size_label, price, unit) VALUES (?,?,?,?)")->execute([$productId,$sizeLabel,$price,$unit]);
        }
        respond(true, 'Price saved.');
    } catch(\Exception $e) { respond(false, 'DB Error: ' . $e->getMessage()); }
}

// ─── DELETE PRICE TIER ──────────────────────────────────────────────────────────
if ($action === 'delete_price') {
    $priceId = (int)($data['price_id'] ?? 0);
    if (!$priceId) respond(false, 'Invalid price ID.');
    $pdo->prepare("DELETE FROM product_prices WHERE id=?")->execute([$priceId]);
    respond(true, 'Price tier deleted.');
}

respond(false, 'Unknown action: ' . $action);
