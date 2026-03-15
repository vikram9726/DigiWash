<?php
// Secure Session Settings before starting session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);

// Simple .env parser function
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;
        
        $name = trim($parts[0]);
        $value = trim($parts[1], " \t\n\r\0\x0B\"'"); // Strip whitespace and quotes
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load environment variables from the root directory
loadEnv(dirname(__DIR__) . '/DigiWash/.env'); // Adjust path based on project root. Assuming config.php is in DigiWash/
// Fallback if the path logic above is off
if(!isset($_ENV['DB_HOST'])) { loadEnv(__DIR__ . '/.env'); }

// Database Configuration from Environment
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('DB_NAME') ?: 'digiwash';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Maintain exceptions for catching
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // In production, we don't output $e->getMessage() explicitly to users
    error_log("Database Connection Failed: " . $e->getMessage()); // Log error to PHP error log
    die("Database Connection Failed. Please contact administrator or check server logs.");
}

// Start Session globally
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate a CSRF token if one doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper function to render env keys to frontend JS safely
function getFirebaseConfigJs() {
    return json_encode([
        'apiKey' => getenv('FIREBASE_API_KEY') ?: '',
        'authDomain' => getenv('FIREBASE_AUTH_DOMAIN') ?: '',
        'projectId' => getenv('FIREBASE_PROJECT_ID') ?: '',
        'storageBucket' => getenv('FIREBASE_STORAGE_BUCKET') ?: '',
        'messagingSenderId' => getenv('FIREBASE_MESSAGING_SENDER_ID') ?: '',
        'appId' => getenv('FIREBASE_APP_ID') ?: ''
    ]);
}

// Helper to send Firebase Push Notifications (FCM v1)
function sendPushNotification($pdo, $userId, $title, $body) {
    if (!$pdo) return false;
    
    // Search in all three tables since we don't know the role here
    $token = null;
    
    // 1. Try Customers
    $stmt = $pdo->prepare("SELECT fcm_token FROM customers WHERE id = ?");
    $stmt->execute([$userId]);
    $token = $stmt->fetchColumn();
    
    // 2. Try Delivery
    if (!$token) {
        $stmt = $pdo->prepare("SELECT fcm_token FROM delivery_partners WHERE id = ?");
        $stmt->execute([$userId]);
        $token = $stmt->fetchColumn();
    }
    
    // 3. Try Admins
    if (!$token) {
        $stmt = $pdo->prepare("SELECT fcm_token FROM admins WHERE id = ?");
        $stmt->execute([$userId]);
        $token = $stmt->fetchColumn();
    }

    if (!$token) return false;

    // This is a placeholder for the actual FCM V1 HTTP call.
    error_log("FCM Push to User $userId: $title - $body");
    return true;
}
?>
