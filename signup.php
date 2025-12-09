<?php
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (fullname, email, password, role) 
            VALUES ('$fullname', '$email', '$password', 'user')";

    if (mysqli_query($conn, $sql)) {
        header("Location: signin.php");
    } else {
        $error = "Email already exists!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Sign Up</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="form-container">
<h2>Create Account</h2>

<?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>

<form method="POST">
    <input type="text" name="fullname" placeholder="Full Name" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Sign Up</button>
</form>

<p>Already have an account? <a href="signin.php">Sign In</a></p>
</div>

</body>
</html>
