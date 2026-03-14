<?php
include("../middleware/auth_check.php");
?>

<!DOCTYPE html>
<html>

<head>

<title>DigiWash Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-5">

<h2>Welcome to DigiWash</h2>

<p>You are logged in.</p>

<a class="btn btn-danger" href="../auth/logout.php">Logout</a>

</div>

</body>

</html>