<?php
session_start();
include("db.php");

if (!isset($_SESSION['id'])) {
    header("Location: signin.php");
    exit();
}

$user_id = $_SESSION['id'];
$user_result = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($user_result);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Profile - TripNext</title>
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
        }
        .header {
            background: linear-gradient(to right, #004d4d, #007272);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #004d4d;
            margin-bottom: 30px;
        }
        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .info-group {
            margin-bottom: 20px;
        }
        .info-group label {
            display: block;
            color: #666;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .info-group p {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #004d4d;
        }
        .btn {
            display: inline-block;
            background: #004d4d;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>TripNext - My Profile</h1>
        <div>
            <a href="index.php" style="color: white; margin-right: 15px;">← Back to Home</a>
            <a href="logout.php" style="color: white;">Logout</a>
        </div>
    </header>
    
    <div class="container">
        <h1>My Profile Information</h1>
        
        <div class="profile-info">
            <div class="info-group">
                <label>Full Name</label>
                <p><?php echo htmlspecialchars($user['fullname']); ?></p>
            </div>
            
            <div class="info-group">
                <label>Email Address</label>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            
            <div class="info-group">
                <label>Account Type</label>
                <p><?php echo ucfirst($user['role']); ?></p>
            </div>
            
            <div class="info-group">
                <label>Member Since</label>
                <p><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
            </div>
        </div>
        
        <a href="index.php" class="btn">Back to Home</a>
    </div>
</body>
</html>