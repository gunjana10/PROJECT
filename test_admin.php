<?php
session_start();
include("db.php");

echo "<h3>Testing Admin Login</h3>";

// Test sign in
$_POST['email'] = 'admin@travel.com';
$_POST['password'] = 'admin123';

$email = 'admin@travel.com';
$password = 'admin123';

$result = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
$user = mysqli_fetch_assoc($result);

if ($user && password_verify($password, $user['password'])) {
    echo "✅ Login successful!<br>";
    echo "Role: " . $user['role'] . "<br>";
    
    // Set session
    $_SESSION['id'] = $user['id'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['role'] = $user['role'];
    
    echo "Session set: ✅<br>";
    
    // Test admin dashboard redirect
    if ($_SESSION['role'] == 'admin') {
        echo "✅ Would redirect to admin-dashboard.php<br>";
        echo '<a href="admin-dashboard.php">Test Admin Dashboard</a>';
    }
} else {
    echo "❌ Login failed!<br>";
}
?>