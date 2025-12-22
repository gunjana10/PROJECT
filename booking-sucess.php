<?php
session_start();
include("db.php");

$booking_id = $_GET['id'] ?? 0;
$type = $_GET['type'] ?? ''; // new_user, registered, or empty

// Get booking details
$booking = null;
if ($booking_id) {
    $query = mysqli_query($conn, "SELECT * FROM bookings WHERE id = '$booking_id'");
    $booking = mysqli_fetch_assoc($query);
}

// Set message based on type
if ($type == 'new_user') {
    $title = "Welcome & Booking Confirmed!";
    $message = "Your account has been created and your booking is confirmed!";
} elseif ($type == 'registered') {
    $title = "Booking Confirmed!";
    $message = "Your booking has been processed with your registered account.";
} else {
    $title = "Booking Confirmed!";
    $message = "Your booking has been successfully completed.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Booking Successful</title>
    <style>
        body { font-family: Arial; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .success-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-width: 600px; width: 100%; text-align: center; }
        .success-icon { font-size: 60px; color: #4CAF50; margin-bottom: 20px; }
        .booking-details { background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: left; }
        .detail { display: flex; justify-content: space-between; margin: 10px 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        .buttons { margin-top: 30px; }
        .btn { padding: 12px 25px; margin: 0 10px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; display: inline-block; }
        .btn:hover { background: #5a67d8; }
    </style>
</head>
<body>
    <div class="success-box">
        <div class="success-icon">✓</div>
        <h1><?php echo $title; ?></h1>
        <p><?php echo $message; ?></p>
        
        <?php if ($booking): ?>
        <div class="booking-details">
            <h3>Booking Details</h3>
            <div class="detail">
                <span>Booking ID:</span>
                <strong>#<?php echo $booking['id']; ?></strong>
            </div>
            <div class="detail">
                <span>Name:</span>
                <span><?php echo htmlspecialchars($booking['full_name']); ?></span>
            </div>
            <div class="detail">
                <span>Destination:</span>
                <span><?php echo htmlspecialchars($booking['destination']); ?></span>
            </div>
            <div class="detail">
                <span>Dates:</span>
                <span><?php echo $booking['start_date'] . ' to ' . $booking['end_date']; ?></span>
            </div>
            <div class="detail">
                <span>Total:</span>
                <strong>RS. <?php echo number_format($booking['price'], 2); ?></strong>
            </div>
        </div>
        <?php endif; ?>
        
        <p>A confirmation has been sent to your email.</p>
        
        <div class="buttons">
            <?php if (isset($_SESSION['id'])): ?>
                <a href="user-dashboard.php" class="btn">View Dashboard</a>
            <?php else: ?>
                <a href="signin.php" class="btn">Sign In</a>
            <?php endif; ?>
            <a href="index.php" class="btn">Home Page</a>
        </div>
    </div>
</body>
</html>