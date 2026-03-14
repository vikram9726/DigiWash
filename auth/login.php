<?php
session_start();
?>

<!DOCTYPE html>
<html>

<head>

<title>DigiWash Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="row justify-content-center">

<div class="col-md-4">

<div class="card shadow">

<div class="card-body">

<h3 class="text-center mb-4">
Login to DigiWash
</h3>

<form method="POST" action="login_process.php">

<input class="form-control mb-3" name="email" placeholder="Email" required>

<input type="password" class="form-control mb-3" name="password" placeholder="Password" required>

<button class="btn btn-primary w-100">
Login
</button>

</form>

<hr>

<a href="google_login.php" class="btn btn-danger w-100">
Login with Google
</a>

<div class="text-center mt-3">
<a href="signup.php">Create account</a>
</div>

</div>

</div>

</div>

</div>

</div>

</body>

</html>