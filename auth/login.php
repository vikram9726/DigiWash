<?php
session_start();
include("../config/db.php");

if(isset($_POST['login'])){

$email = $_POST['email'];
$password = $_POST['password'];

$query = "SELECT * FROM users WHERE email='$email'";
$result = mysqli_query($conn,$query);

$user = mysqli_fetch_assoc($result);

if($user && password_verify($password,$user['password'])){

$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role'];

if($user['role'] == 'admin'){
header("Location: ../dashboard/admin_dashboard.php");
}
elseif($user['role'] == 'delivery'){
header("Location: ../dashboard/delivery_dashboard.php");
}
else{
header("Location: ../dashboard/user_dashboard.php");
}

}else{
echo "Invalid login credentials";
}

}
?>

<!DOCTYPE html>
<html>
<head>
<title>DigiWash Login</title>
</head>

<body>

<h2>Login</h2>

<form method="POST">

<input type="email" name="email" placeholder="Email" required><br><br>

<input type="password" name="password" placeholder="Password" required><br><br>

<button type="submit" name="login">Login</button>

</form>

<a href="signup.php">Create account</a>

</body>
</html>