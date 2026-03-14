<?php
include("../middleware/auth_check.php");

if($_SESSION['role'] != 'admin'){
echo "Access Denied";
exit();
}
?>

<h1>Admin Dashboard</h1>

<a href="../auth/logout.php">Logout</a>