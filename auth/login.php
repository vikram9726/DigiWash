<?php
session_start();
include("../config/db.php");

$error="";

if(isset($_POST['login'])){

$email = $_POST['email'];
$password = $_POST['password'];

$stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
$stmt->bind_param("s",$email);
$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

if($user && password_verify($password,$user['password'])){

session_regenerate_id(true);

$_SESSION['user_id']=$user['id'];
$_SESSION['role']=$user['role'];

header("Location: ../dashboard/dashboard.php");
exit();

}else{
$error="Invalid credentials";
}

}
?>

<!DOCTYPE html>
<html>
<head>

<title>DigiWash Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container">

<div class="row justify-content-center mt-5">

<div class="col-md-4">

<div class="card shadow">

<div class="card-body">

<h3 class="text-center mb-4">DigiWash</h3>

<?php if($error!=""){ ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php } ?>

<form method="POST">

<div class="mb-3">
<label>Email</label>
<input type="email" name="email" class="form-control" required>
</div>

<div class="mb-3">
<label>Password</label>
<input type="password" name="password" class="form-control" required>
</div>

<button class="btn btn-primary w-100" name="login">
Login
</button>

</form>

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