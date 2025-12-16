<?php
session_start();
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get user_id from session if exists, otherwise use NULL for guest booking
    $user_id = isset($_SESSION['id']) ? $_SESSION['id'] : NULL;
    
    // Get all form data with proper validation
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $destination = mysqli_real_escape_string($conn, trim($_POST['destination'] ?? ''));
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date'] ?? '');
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date'] ?? '');
    $travelers = intval($_POST['travelers'] ?? 1);
    
    // Package options
    $package = mysqli_real_escape_string($conn, $_POST['package'] ?? 'basic');
    $hotel = mysqli_real_escape_string($conn, $_POST['hotel'] ?? 'basic');
    $transport = mysqli_real_escape_string($conn, $_POST['transport'] ?? 'bus');
    
  
    $price_raw = $_POST['price'] ?? '0';
    $price = floatval(str_replace(['RS.', ',', ' ', 'Rs.', 'rs.', '₹', 'रू'], '', $price_raw));
    
    $special_requests = mysqli_real_escape_string($conn, trim($_POST['special_requests'] ?? ''));

    // Validate required fields
    if (empty($full_name) || empty($email) || empty($phone) || empty($destination) || 
        empty($start_date) || empty($end_date) || $travelers < 1 || $price <= 0) {
        header("Location: " . $_SERVER['HTTP_REFERER'] . "?booking=error&message=Please fill all required fields correctly");
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: " . $_SERVER['HTTP_REFERER'] . "?booking=error&message=Invalid email address");
        exit();
    }

    // Validate dates
    if (strtotime($end_date) < strtotime($start_date)) {
        header("Location: " . $_SERVER['HTTP_REFERER'] . "?booking=error&message=End date must be after start date");
        exit();
    }

    // Insert booking - user_id can be NULL for guest bookings
    if ($user_id) {
        // For logged-in users
        $query = "INSERT INTO bookings (
                    user_id, full_name, email, phone, destination, 
                    start_date, end_date, travelers, package, hotel, transport, 
                    price, special_requests, booking_date
                  ) VALUES (
                    '$user_id', '$full_name', '$email', '$phone', '$destination',
                    '$start_date', '$end_date', '$travelers', '$package', '$hotel', '$transport',
                    '$price', '$special_requests', NOW()
                  )";
    } else {
        // For guest bookings (no user_id)
        $query = "INSERT INTO bookings (
                    full_name, email, phone, destination, 
                    start_date, end_date, travelers, package, hotel, transport, 
                    price, special_requests, booking_date
                  ) VALUES (
                    '$full_name', '$email', '$phone', '$destination',
                    '$start_date', '$end_date', '$travelers', '$package', '$hotel', '$transport',
                    '$price', '$special_requests', NOW()
                  )";
    }

    if (mysqli_query($conn, $query)) {
        // Success - get booking ID
        $booking_id = mysqli_insert_id($conn);
        
        // For guest users, offer account creation
        if (!$user_id) {
            // Store booking reference in session for potential account linking
            $_SESSION['guest_booking_id'] = $booking_id;
            $_SESSION['guest_email'] = $email;
        }
        
        // You can add email notification here
        // sendBookingConfirmation($email, $booking_id, $full_name, $destination, $price);
        
        // Redirect with success message
        header("Location: " . $_SERVER['HTTP_REFERER'] . "?booking=success&id=$booking_id");
    } else {
        // Log the error for debugging
        error_log("Booking failed: " . mysqli_error($conn));
        header("Location: " . $_SERVER['HTTP_REFERER'] . "?booking=error&message=Database error. Please try again.");
    }
    exit();
} else {
    // If someone tries to access this page directly without form submission
    header("Location: lumbini.html");
    exit();
}
?>