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

// Determine if JSON or FormData
$ct     = $_SERVER['CONTENT_TYPE'] ?? '';
$isJson = str_contains($ct, 'application/json');
$data   = $isJson ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];
$action = $data['action'] ?? $_POST['action'] ?? '';

// CSRF check — handle null token gracefully
$headers = function_exists('getallheaders') ? getallheaders() : [];
$csrfToken = $headers['X-CSRF-Token']
    ?? $headers['x-csrf-token']
    ?? $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? (is_array($data ?? null) ? ($data['csrf_token'] ?? '') : '')
    ?? (is_array($body ?? null) ? ($body['csrf_token'] ?? '') : '')
    ?? $_POST['csrf_token']
    ?? '';
$serverCsrf = $_SESSION['csrf_token'] ?? '';
if (empty($serverCsrf) || !hash_equals($serverCsrf, $csrfToken)) {
    respond(false, 'Invalid CSRF token.');
}

// ─── Helper: fetch products using GROUP_CONCAT (compatible with MySQL 5.6+) ─
function fetchProducts(PDO $pdo, bool $activeOnly): array {
    $where = $activeOnly ? "WHERE p.is_active = 1" : "";

    // Step 1: fetch products
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.description, p.image_url,
               p.is_active, p.sort_order, p.created_at
        FROM products p
        $where
        ORDER BY p.sort_order ASC, p.created_at ASC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$products) return [];

    // Step 2: fetch all prices at once
    $ids      = array_column($products, 'id');
    $ph       = implode(',', array_fill(0, count($ids), '?'));
    $priceStmt = $pdo->prepare("
        SELECT product_id, id, size_label, price, unit
        FROM product_prices
        WHERE product_id IN ($ph)
        ORDER BY id ASC
    ");
    $priceStmt->execute($ids);
    $allPrices = $priceStmt->fetchAll(PDO::FETCH_ASSOC);

    // Step 3: group prices by product_id
    $priceMap = [];
    foreach ($allPrices as $pp) {
        $priceMap[$pp['product_id']][] = [
            'id'         => (int)$pp['id'],
            'size_label' => $pp['size_label'],
            'price'      => (float)$pp['price'],
            'unit'       => $pp['unit'],
        ];
    }

    // Step 4: attach prices
    foreach ($products as &$prod) {
        $prod['prices']    = $priceMap[$prod['id']] ?? [];
        $prod['is_active'] = (int)$prod['is_active'];
        $prod['id']        = (int)$prod['id'];
    }
    unset($prod);
    return $products;
}

// ─── PUBLIC: get_products ─────────────────────────────────────────────────────
if ($action === 'get_products') {
    $activeOnly = ($data['active_only'] ?? true) !== false;
    $products   = fetchProducts($pdo, $activeOnly);
    respond(true, 'Products fetched.', ['products' => $products]);
}

// ─── ADMIN ONLY beyond this point ─────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    respond(false, 'Admin access only.');
}

// ─── CREATE PRODUCT ───────────────────────────────────────────────────────────
if ($action === 'create_product') {
    $name        = trim(htmlspecialchars(strip_tags($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $description = trim(htmlspecialchars(strip_tags($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $sortOrder   = (int)($_POST['sort_order'] ?? 0);

    if (empty($name)) respond(false, 'Product name is required.');

    // Image upload
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
        $filename = 'product_' . uniqid() . '.' . $ext;
        $destPath = __DIR__ . '/../uploads/products/' . $filename;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) respond(false, 'Failed to save image.');
        $imageUrl = 'uploads/products/' . $filename;
    }

    // Parse prices
    $prices = json_decode($_POST['prices'] ?? '[]', true);
    if (!is_array($prices) || empty($prices)) respond(false, 'At least one pricing tier is required.');
    foreach ($prices as $t) {
        if (empty($t['size_label']) || !isset($t['price']) || (float)$t['price'] <= 0) {
            respond(false, 'Each tier needs a label and valid price.');
        }
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO products (name, description, image_url, sort_order) VALUES (?,?,?,?)")
            ->execute([$name, $description, $imageUrl, $sortOrder]);
        $productId = (int)$pdo->lastInsertId();

        $ps = $pdo->prepare("INSERT INTO product_prices (product_id, size_label, price, unit) VALUES (?,?,?,?)");
        foreach ($prices as $t) {
            $ps->execute([$productId, trim($t['size_label']), (float)$t['price'], $t['unit'] ?? 'per piece']);
        }
        $pdo->commit();
        respond(true, "Product \"$name\" created!", ['product_id' => $productId]);
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, 'DB Error: ' . $e->getMessage());
    }
}

// ─── UPDATE PRODUCT ────────────────────────────────────────────────────────────
if ($action === 'update_product') {
    $pid  = (int)($data['product_id'] ?? 0);
    $name = trim(htmlspecialchars(strip_tags($data['name'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $desc = trim(htmlspecialchars(strip_tags($data['description'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $sort = (int)($data['sort_order'] ?? 0);
    if (!$pid || empty($name)) respond(false, 'Product ID and name required.');
    try {
        $pdo->prepare("UPDATE products SET name=?,description=?,sort_order=? WHERE id=?")->execute([$name,$desc,$sort,$pid]);
        respond(true, 'Product updated.');
    } catch(\Exception $e) { respond(false, 'DB Error: '.$e->getMessage()); }
}

// ─── TOGGLE PRODUCT ────────────────────────────────────────────────────────────
if ($action === 'toggle_product') {
    $pid = (int)($data['product_id'] ?? 0);
    if (!$pid) respond(false, 'Invalid product.');
    $pdo->prepare("UPDATE products SET is_active = !is_active WHERE id=?")->execute([$pid]);
    respond(true, 'Product toggled.');
}

// ─── DELETE PRODUCT ────────────────────────────────────────────────────────────
if ($action === 'delete_product') {
    $pid = (int)($data['product_id'] ?? 0);
    if (!$pid) respond(false, 'Invalid product.');
    try {
        $row = $pdo->prepare("SELECT image_url FROM products WHERE id=?");
        $row->execute([$pid]); $prod = $row->fetch();
        if ($prod && $prod['image_url']) {
            $imgPath = __DIR__ . '/../' . $prod['image_url'];
            if (file_exists($imgPath)) unlink($imgPath);
        }
        $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$pid]);
        respond(true, 'Product deleted.');
    } catch(\Exception $e) { respond(false, 'DB Error: '.$e->getMessage()); }
}

// ─── UPSERT PRICE TIER ────────────────────────────────────────────────────────
if ($action === 'upsert_price') {
    $priceId   = (int)($data['price_id'] ?? 0);
    $pid       = (int)($data['product_id'] ?? 0);
    $sizeLabel = trim(htmlspecialchars(strip_tags($data['size_label'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $price     = (float)($data['price'] ?? 0);
    $unit      = trim(htmlspecialchars(strip_tags($data['unit'] ?? 'per piece'), ENT_QUOTES, 'UTF-8'));
    if ((!$priceId && !$pid) || empty($sizeLabel) || $price <= 0) respond(false, 'Invalid price data.');
    try {
        if ($priceId) {
            $pdo->prepare("UPDATE product_prices SET size_label=?,price=?,unit=? WHERE id=?")->execute([$sizeLabel,$price,$unit,$priceId]);
        } else {
            $pdo->prepare("INSERT INTO product_prices (product_id,size_label,price,unit) VALUES (?,?,?,?)")->execute([$pid,$sizeLabel,$price,$unit]);
        }
        respond(true, 'Price saved.');
    } catch(\Exception $e) { respond(false, 'DB Error: '.$e->getMessage()); }
}

// ─── DELETE PRICE TIER ────────────────────────────────────────────────────────
if ($action === 'delete_price') {
    $priceId = (int)($data['price_id'] ?? 0);
    if (!$priceId) respond(false, 'Invalid price ID.');
    $pdo->prepare("DELETE FROM product_prices WHERE id=?")->execute([$priceId]);
    respond(true, 'Price tier deleted.');
}

respond(false, 'Unknown action.');
