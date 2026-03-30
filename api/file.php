<?php
require_once '../config.php';

// Secure file serving proxy to prevent unauthorized access to uploads
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Unauthorized');
}

$file = $_GET['path'] ?? '';
if (empty($file)) {
    http_response_code(400);
    die('Bad Request');
}

// Basic path traversal protection
if (strpos($file, '..') !== false || strpos($file, '/') === 0) {
    http_response_code(403);
    die('Forbidden');
}

// Ensure the file is inside the uploads directory
$baseDir = realpath(__DIR__ . '/../uploads/');
$requestedFile = realpath($baseDir . '/' . $file);

if ($requestedFile === false || strpos($requestedFile, $baseDir) !== 0 || !file_exists($requestedFile)) {
    http_response_code(404);
    die('File not found');
}

// Serve the file with correct MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $requestedFile);
finfo_close($finfo);

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($requestedFile));
// Optional: add cache headers
header('Cache-Control: private, max-age=86400');

readfile($requestedFile);
exit;
