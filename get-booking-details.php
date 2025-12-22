<?php
session_start();
include("db.php");

// Set header for JSON response
header('Content-Type: application/json');

// Check if user is logged in - FIXED: Better session check
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['id'];
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
    exit();
}

// Get booking details - check that it belongs to the logged-in user
$query = "SELECT * FROM bookings WHERE id = '$booking_id' 
          AND (user_id = '$user_id' OR email = '$user_email') 
          LIMIT 1";
          
$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

if (mysqli_num_rows($result) > 0) {
    $booking = mysqli_fetch_assoc($result);
    
    // Format dates
    if (isset($booking['start_date'])) {
        $booking['start_date'] = date('Y-m-d', strtotime($booking['start_date']));
    }
    if (isset($booking['end_date'])) {
        $booking['end_date'] = date('Y-m-d', strtotime($booking['end_date']));
    }
    if (isset($booking['booking_date'])) {
        $booking['booking_date'] = date('Y-m-d H:i:s', strtotime($booking['booking_date']));
    }
    
    // Make sure we have fullname
    if (empty($booking['fullname']) && isset($_SESSION['fullname'])) {
        $booking['fullname'] = $_SESSION['fullname'];
    }
    
    // Make sure we have phone
    if (empty($booking['phone']) && isset($_SESSION['phone'])) {
        $booking['phone'] = $_SESSION['phone'];
    }
    
    echo json_encode([
        'success' => true,
        'booking' => $booking
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Booking not found. Please contact support.'
    ]);
}

exit();
?>