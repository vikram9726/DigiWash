<!DOCTYPE html>
<html>

<head>

<title>Signup</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="row justify-content-center">

<div class="col-md-4">

<div class="card shadow">

<div class="card-body">

<h3 class="text-center">Create Account</h3>

<form method="POST" action="signup_process.php">

<input class="form-control mb-3" name="name" placeholder="Name" required>

<input class="form-control mb-3" name="email" placeholder="Email" required>

<input class="form-control mb-3" name="phone" placeholder="Phone">

<input type="password" class="form-control mb-3" name="password" placeholder="Password" required>

<button class="btn btn-success w-100">
Signup
</button>

</form>

</div>

</div>

</div>

</div>

</div>

</body>
</html>