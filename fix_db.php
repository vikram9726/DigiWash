<?php
$files = [
    'config.php',
    'api/orders.php',
    'api/admin.php',
    'api/auth.php',
    'api/user.php',
    'api/delivery.php',
    'user/dashboard.php',
    'admin/dashboard.php',
    'delivery/dashboard.php',
];
$allOk = true;
foreach ($files as $file) {
    $out = shell_exec("c:\\xampp\\php\\php.exe -l $file 2>&1");
    $ok = strpos($out, 'No syntax errors') !== false;
    if (!$ok) $allOk = false;
    echo ($ok ? '✓' : '✗') . " $file" . (!$ok ? ": $out" : '') . "\n";
}
echo $allOk ? "\nAll files OK!\n" : "\nSome files have errors!\n";
?>
