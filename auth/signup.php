<?php
include("../config/db.php");

if(isset($_POST['signup'])){

$name = $_POST['name'];
$email = $_POST['email'];
$phone = $_POST['phone'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

$query = "INSERT INTO users(name,email,phone,password)
VALUES('$name','$email','$phone','$password')";

$result = mysqli_query($conn,$query);

if($result){
echo "Signup successful";
}else{
echo "Error creating account";
}

}
?>

<!DOCTYPE html>
<html>
<head>
<title>DigiWash Signup</title>
</head>

<body>

<h2>Signup</h2>

<form method="POST">

<input type="text" name="name" placeholder="Name" required><br><br>

<input type="email" name="email" placeholder="Email" required><br><br>

<input type="text" name="phone" placeholder="Phone"><br><br>

<input type="password" name="password" placeholder="Password" required><br><br>

<button type="submit" name="signup">Signup</button>

</form>

<a href="login.php">Already have account? Login</a>

</body>
</html>