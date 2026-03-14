<?php
include("../config/db.php");

$msg="";

if(isset($_POST['signup'])){

$name=$_POST['name'];
$email=$_POST['email'];
$phone=$_POST['phone'];

$password=password_hash($_POST['password'],PASSWORD_DEFAULT);

$stmt=$conn->prepare(
"INSERT INTO users(name,email,phone,password)
VALUES(?,?,?,?)"
);

$stmt->bind_param("ssss",$name,$email,$phone,$password);

if($stmt->execute()){
$msg="Account created successfully";
}else{
$msg="Email already exists";
}

}
?>

<!DOCTYPE html>
<html>

<head>

<title>DigiWash Signup</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="row justify-content-center">

<div class="col-md-4">

<div class="card shadow">

<div class="card-body">

<h3 class="text-center">Create Account</h3>

<?php if($msg!=""){ ?>
<div class="alert alert-info"><?php echo $msg; ?></div>
<?php } ?>

<form method="POST">

<input class="form-control mb-3" name="name" placeholder="Name" required>

<input class="form-control mb-3" name="email" placeholder="Email" required>

<input class="form-control mb-3" name="phone" placeholder="Phone">

<input type="password" class="form-control mb-3" name="password" placeholder="Password" required>

<button class="btn btn-success w-100" name="signup">
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