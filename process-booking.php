<?php
session_start();
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get user_id from session if exists
    $user_id = isset($_SESSION['id']) ? $_SESSION['id'] : NULL;
    
    // Get form data - using YOUR exact form field names
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $destination = mysqli_real_escape_string($conn, trim($_POST['destination'] ?? ''));
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date'] ?? '');
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date'] ?? '');
    $travelers = intval($_POST['travelers'] ?? 1);
    
    // These should match your form fields
    $package = mysqli_real_escape_string($conn, $_POST['package'] ?? 'Basic Pilgrimage (Rs.21,480)');
    $hotel = mysqli_real_escape_string($conn, $_POST['hotel'] ?? 'Basic Hotel');
    $transport = mysqli_real_escape_string($conn, $_POST['transport'] ?? 'Bus');
    
    // Price handling for YOUR format "Rs.21,480"
    $price_raw = $_POST['price'] ?? '0';
    $price = floatval(str_replace(['RS.', ',', ' ', 'Rs.', 'rs.', '₹', 'रू'], '', $price_raw));
    
    $special_requests = mysqli_real_escape_string($conn, trim($_POST['special_requests'] ?? ''));

    // Validate required fields
    $errors = [];
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($phone)) $errors[] = "Phone is required";
    if (empty($destination)) $errors[] = "Destination is required";
    if (empty($start_date)) $errors[] = "Start date is required";
    if (empty($end_date)) $errors[] = "End date is required";
    if ($travelers < 1) $errors[] = "Number of travelers is required";
    if ($price <= 0) $errors[] = "Price is required";
    
    if (!empty($errors)) {
        $error_message = implode(", ", $errors);
        header("Location: " . $_SERVER['HTTP_REFERER'] . "?error=" . urlencode($error_message));
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: " . $_SERVER['HTTP_REFERER'] . "?error=Invalid email address");
        exit();
    }

    // Validate dates
    if (strtotime($end_date) < strtotime($start_date)) {
        header("Location: " . $_SERVER['HTTP_REFERER'] . "?error=End date must be after start date");
        exit();
    }

    // ============================================
    // CHECK IF EMAIL EXISTS IN users TABLE
    // ============================================
    
    if (!$user_id) {
        // User is not logged in - check if email exists
        $check_email_query = "SELECT id FROM users WHERE email = '$email' LIMIT 1";
        $email_result = mysqli_query($conn, $check_email_query);
        
        if (mysqli_num_rows($email_result) > 0) {
            // ✅ EMAIL EXISTS - Registered user
            $user_data = mysqli_fetch_assoc($email_result);
            $user_id = $user_data['id'];
            
            // Create booking for registered user
            $query = "INSERT INTO bookings (
                        user_id, full_name, email, phone, destination, 
                        start_date, end_date, travelers, package, hotel, transport, 
                        price, special_requests, status
                      ) VALUES (
                        '$user_id', '$full_name', '$email', '$phone', '$destination',
                        '$start_date', '$end_date', '$travelers', '$package', '$hotel', '$transport',
                        '$price', '$special_requests', 'confirmed'
                      )";
            
            if (mysqli_query($conn, $query)) {
                $booking_id = mysqli_insert_id($conn);
                // Success for registered user
                header("Location: booking-sucess.php?id=$booking_id&type=registered");
                exit();
            } else {
                header("Location: " . $_SERVER['HTTP_REFERER'] . "?error=Booking failed. Please try again.");
                exit();
            }
            
        } else {
            // ❌ EMAIL NOT REGISTERED
            // Save booking data to session
            $_SESSION['booking_data'] = [
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'destination' => $destination,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'travelers' => $travelers,
                'package' => $package,
                'hotel' => $hotel,
                'transport' => $transport,
                'price' => $price,
                'special_requests' => $special_requests
            ];
            
            // Redirect to signup with error message
            header("Location: signup.php?error=Email is not registered. Please sign up first.");
            exit();
        }
    } else {
        // User is already logged in
        $query = "INSERT INTO bookings (
                    user_id, full_name, email, phone, destination, 
                    start_date, end_date, travelers, package, hotel, transport, 
                    price, special_requests, status
                  ) VALUES (
                    '$user_id', '$full_name', '$email', '$phone', '$destination',
                    '$start_date', '$end_date', '$travelers', '$package', '$hotel', '$transport',
                    '$price', '$special_requests', 'confirmed'
                  )";
        
        if (mysqli_query($conn, $query)) {
            $booking_id = mysqli_insert_id($conn);
            header("Location: booking-sucess.php?id=$booking_id");
            exit();
        } else {
            header("Location: " . $_SERVER['HTTP_REFERER'] . "?error=Booking failed. Please try again.");
            exit();
        }
    }
    
} else {
    // Direct access without form submission
    header("Location: index.php");
    exit();
}
?>