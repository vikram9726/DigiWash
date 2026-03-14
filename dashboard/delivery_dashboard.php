<?php
include("../middleware/auth_check.php");

if($_SESSION['role'] != 'delivery'){
echo "Access Denied";
exit();
}
?>

<h1>Delivery Dashboard</h1>

<a href="../auth/logout.php">Logout</a>