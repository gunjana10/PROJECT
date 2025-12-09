<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['role'] != "user") {
    header("Location: signin.php");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>User Dashboard</title>
</head>
<body>

<h1>Welcome, <?php echo $_SESSION['fullname']; ?>!</h1>
<p>This is the USER dashboard.</p>

<a href="logout.php">Logout</a>

</body>
</html>
