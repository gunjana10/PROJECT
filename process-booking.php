<?php
session_start();
include("db.php");
include("notification_helper.php"); // 🔴 ADDED: Include notification helper

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get user_id from session if exists
    $user_id = isset($_SESSION['id']) ? $_SESSION['id'] : NULL;
    
    // Get form data
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $destination = mysqli_real_escape_string($conn, trim($_POST['destination'] ?? ''));
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date'] ?? '');
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date'] ?? '');
    $travelers = intval($_POST['travelers'] ?? 1);
    
    // Package, hotel, transport
    $package = mysqli_real_escape_string($conn, $_POST['package'] ?? 'Basic Package');
    $hotel = mysqli_real_escape_string($conn, $_POST['hotel'] ?? 'Basic Hotel');
    $transport = mysqli_real_escape_string($conn, $_POST['transport'] ?? 'Bus');
    
    // IMPORTANT: This is the TOTAL PRICE for all travelers
    $price_raw = $_POST['price'] ?? '0';
    
    // Clean the price string - remove RS., commas, etc.
    $price_cleaned = str_replace(['RS.', ',', ' ', 'Rs.', 'rs.', '₹', 'रू'], '', $price_raw);
    $total_price = floatval($price_cleaned);
    
    // VERIFICATION: Get base price from destinations table
    $base_query = "SELECT price FROM destinations WHERE name = '$destination' LIMIT 1";
    $base_result = mysqli_query($conn, $base_query);
    
    if (mysqli_num_rows($base_result) > 0) {
        $base_row = mysqli_fetch_assoc($base_result);
        $base_cleaned = str_replace(['RS.', ',', ' ', 'Rs.', 'rs.', '₹', 'रू'], '', $base_row['price']);
        $base_price_per_person = floatval($base_cleaned);
        
        // Calculate expected minimum price (base price * travelers)
        $expected_min_price = $base_price_per_person * $travelers;
        
        // If total price is less than expected minimum, use expected minimum
        if ($total_price < $expected_min_price) {
            $total_price = $expected_min_price;
        }
    }
    
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
    if ($total_price <= 0) $errors[] = "Price is required";
    
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

    // Check if email exists in users table
    if (!$user_id) {
        // User is not logged in
        $check_email_query = "SELECT id FROM users WHERE email = '$email' LIMIT 1";
        $email_result = mysqli_query($conn, $check_email_query);
        
        if (mysqli_num_rows($email_result) > 0) {
            // Email exists - Registered user
            $user_data = mysqli_fetch_assoc($email_result);
            $user_id = $user_data['id'];
            
            // Insert booking with TOTAL PRICE
            $query = "INSERT INTO bookings (
                        user_id, full_name, email, phone, destination, 
                        start_date, end_date, travelers, package, hotel, transport, 
                        price, special_requests, status
                      ) VALUES (
                        '$user_id', '$full_name', '$email', '$phone', '$destination',
                        '$start_date', '$end_date', '$travelers', '$package', '$hotel', '$transport',
                        '$total_price', '$special_requests', 'pending'
                      )";
            
            if (mysqli_query($conn, $query)) {
                $booking_id = mysqli_insert_id($conn);
                
                // 🔴 🔴 🔴 ADDED: Notify admins about new booking
                NotificationHelper::createForAdmins($conn, 'booking_created', '🆕 New Booking', "New booking #$booking_id from $full_name", $booking_id);
                
                header("Location: booking-sucess.php?id=$booking_id&type=registered&total=$total_price&travelers=$travelers");
                exit();
            } else {
                header("Location: " . $_SERVER['HTTP_REFERER'] . "?error=Booking failed. Please try again.");
                exit();
            }
            
        } else {
            // Email NOT registered
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
                'price' => $total_price, // This is TOTAL PRICE
                'special_requests' => $special_requests
            ];
            
            header("Location: signup.php?error=Email is not registered. Please sign up first.&booking_data=1");
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
                    '$total_price', '$special_requests', 'pending'
                  )";
        
        if (mysqli_query($conn, $query)) {
            $booking_id = mysqli_insert_id($conn);
            
            // 🔴 🔴 🔴 ADDED: Notify admins about new booking
            NotificationHelper::createForAdmins($conn, 'booking_created', '🆕 New Booking', "New booking #$booking_id from $full_name", $booking_id);
            
            header("Location: booking-sucess.php?id=$booking_id&total=$total_price&travelers=$travelers");
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