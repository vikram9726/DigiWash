<?php

session_start();

include("../config/db.php");

$email=$_POST['email'];
$password=$_POST['password'];

$stmt=$conn->prepare(
"SELECT * FROM users WHERE email=?"
);

$stmt->bind_param("s",$email);

$stmt->execute();

$result=$stmt->get_result();

$user=$result->fetch_assoc();

if($user && password_verify($password,$user['password'])){

$_SESSION['user_id']=$user['id'];
$_SESSION['role']=$user['role'];

if($user['role']=="admin"){

header("Location: ../admin/dashboard.php");

}

elseif($user['role']=="delivery"){

header("Location: ../delivery/dashboard.php");
exit();

}

else{

header("Location: ../user/dashboard.php");
exit();

}

}

else{

echo "Invalid credentials";

}

?>