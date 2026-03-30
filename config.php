<?php
// Suppress PHP errors from being output (would break JSON API responses)
// Errors are still logged to PHP error log for debugging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

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

// ISSUE-005 FIX: Security HTTP headers
// Only send headers when not in CLI mode (e.g. cron.php)
if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://checkout.razorpay.com https://www.gstatic.com https://www.google.com/recaptcha/ https://cdnjs.cloudflare.com https://apis.google.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://api.razorpay.com https://fcm.googleapis.com https://identitytoolkit.googleapis.com https://securetoken.googleapis.com https://www.google.com https://www.gstatic.com; frame-src 'self' https://www.google.com https://recaptcha.net https://*.firebaseapp.com https://api.razorpay.com https://checkout.razorpay.com;");
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

// ── Google OAuth2 Access Token (for FCM v1 API) ──
function getGoogleAccessToken() {
    // Cache token in a temp file to avoid regenerating on every call
    $cacheFile = sys_get_temp_dir() . '/digiwash_fcm_token.json';
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached && isset($cached['access_token']) && $cached['expires_at'] > time()) {
            return $cached['access_token'];
        }
    }

    // Load service account JSON
    $saPath = getenv('FIREBASE_SERVICE_ACCOUNT_JSON');
    if (!$saPath) { error_log("FCM: FIREBASE_SERVICE_ACCOUNT_JSON env not set"); return null; }
    
    // Resolve relative to project root
    $fullPath = __DIR__ . '/' . $saPath;
    if (!file_exists($fullPath)) { error_log("FCM: Service account file not found: $fullPath"); return null; }
    
    $sa = json_decode(file_get_contents($fullPath), true);
    if (!$sa || !isset($sa['private_key']) || !isset($sa['client_email'])) {
        error_log("FCM: Invalid service account JSON"); return null;
    }

    // Build JWT
    $now = time();
    $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'iss' => $sa['client_email'],
        'sub' => $sa['client_email'],
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging'
    ]));
    
    // URL-safe base64
    $header = str_replace(['+', '/', '='], ['-', '_', ''], $header);
    $payload = str_replace(['+', '/', '='], ['-', '_', ''], $payload);
    
    $signInput = "$header.$payload";
    $signature = '';
    $privateKey = openssl_pkey_get_private($sa['private_key']);
    if (!$privateKey) { error_log("FCM: Failed to parse private key"); return null; }
    
    openssl_sign($signInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    $sig64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $jwt = "$signInput.$sig64";

    // Exchange JWT for access token
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        error_log("FCM: OAuth2 token exchange failed (HTTP $httpCode): $response");
        return null;
    }

    $tokenData = json_decode($response, true);
    if (!isset($tokenData['access_token'])) {
        error_log("FCM: No access_token in response"); return null;
    }

    // Cache for ~50 minutes (token lasts 60 min)
    file_put_contents($cacheFile, json_encode([
        'access_token' => $tokenData['access_token'],
        'expires_at' => $now + 3000
    ]));

    return $tokenData['access_token'];
}

// ── Send Firebase Push Notification (FCM v1 API) ──
function sendPushNotification($pdo, $userId, $title, $body) {
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("SELECT fcm_token FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $fcmToken = $stmt->fetchColumn();

    if (!$fcmToken) {
        error_log("FCM: No FCM token for user $userId");
        return false;
    }

    $accessToken = getGoogleAccessToken();
    if (!$accessToken) {
        error_log("FCM: Could not get access token, notification skipped for user $userId");
        return false;
    }

    $projectId = getenv('FIREBASE_PROJECT_ID') ?: 'digiwash-9c738';
    $url = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";

    $message = [
        'message' => [
            'token' => $fcmToken,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'webpush' => [
                'notification' => [
                    'icon' => '/assets/img/logo.png',
                    'badge' => '/assets/img/logo.png',
                    'requireInteraction' => true,
                ],
                'fcm_options' => [
                    'link' => '/user/dashboard.php'
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($message),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        error_log("FCM: Push sent to user $userId — $title");
        return true;
    }

    // Handle invalid/expired tokens
    if ($httpCode === 404 || $httpCode === 400) {
        $resp = json_decode($response, true);
        $errorCode = $resp['error']['details'][0]['errorCode'] ?? '';
        if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'])) {
            // Clear stale token
            $pdo->prepare("UPDATE users SET fcm_token = NULL WHERE id = ?")->execute([$userId]);
            error_log("FCM: Cleared stale token for user $userId ($errorCode)");
        }
    }

    error_log("FCM: Push failed for user $userId (HTTP $httpCode): $response");
    return false;
}
?>
