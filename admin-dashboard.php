<?php
session_start();
include("db.php");
include("notification_helper.php");

if (!isset($_SESSION['id']) || $_SESSION['role'] != 'admin') {
    header("Location: signin.php");
    exit();
}

$admin_id = $_SESSION['id'];
$admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$admin_id'"));

$page = $_GET['page'] ?? 'dashboard';
$search = trim($_GET['search'] ?? '');

// Handle AJAX notification request
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_notifications') {
    header('Content-Type: application/json');
    
    $notifications = NotificationHelper::getRecent($conn, $admin_id, 'admin', 10);
    $notifications_array = [];
    
    while ($notif = mysqli_fetch_assoc($notifications)) {
        // Format time
        $created = strtotime($notif['created_at']);
        $now = time();
        $diff = $now - $created;
        
        if ($diff < 60) {
            $time_ago = "Just now";
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            $time_ago = $minutes . " min" . ($minutes > 1 ? "s" : "") . " ago";
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            $time_ago = $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            $time_ago = $days . " day" . ($days > 1 ? "s" : "") . " ago";
        } else {
            $time_ago = date('M d', $created);
        }
        
        // Get icon based on type
        $icon = 'fa-bell';
        $bg_color = '#6a11cb';
        
        switch($notif['type']) {
            case 'booking_created':
                $icon = 'fa-plus-circle';
                $bg_color = '#28a745';
                break;
            case 'booking_edited':
                $icon = 'fa-edit';
                $bg_color = '#ffc107';
                break;
            case 'booking_cancelled':
                $icon = 'fa-times-circle';
                $bg_color = '#dc3545';
                break;
            case 'booking_confirmed':
                $icon = 'fa-check-circle';
                $bg_color = '#17a2b8';
                break;
        }
        
        $notifications_array[] = [
            'id' => $notif['id'],
            'title' => htmlspecialchars($notif['title']),
            'message' => htmlspecialchars($notif['message']),
            'time_ago' => $time_ago,
            'icon' => $icon,
            'bg_color' => $bg_color,
            'related_id' => $notif['related_id'],
            'created_at' => $notif['created_at']
        ];
    }
    
    echo json_encode([
        'notifications' => $notifications_array
    ]);
    exit();
}

// Handle actions
if (isset($_POST['action'])) {
    $id = mysqli_real_escape_string($conn, $_POST['booking_id'] ?? $_POST['user_id'] ?? $_POST['destination_id'] ?? '');
    
    // Confirm Booking
    if ($_POST['action'] == 'confirm_booking') {
        // Get booking details before updating
        $booking_query = mysqli_query($conn, "SELECT * FROM bookings WHERE id='$id'");
        $booking = mysqli_fetch_assoc($booking_query);
        
        // Update booking status
        mysqli_query($conn, "UPDATE bookings SET status='confirmed' WHERE id='$id'");
        
        // Get user ID from email
        $user_query = mysqli_query($conn, "SELECT id FROM users WHERE email='{$booking['email']}'");
        if ($user_row = mysqli_fetch_assoc($user_query)) {
            // Create notification for user
            NotificationHelper::create(
                $conn,
                $user_row['id'],
                'user',
                'booking_confirmed',
                'Booking Confirmed',
                "Your booking #$id has been confirmed successfully.",
                $id
            );
        }
        
        $_SESSION['success'] = "Booking confirmed!";
    }
    
    // Cancel Booking (by admin)
    elseif ($_POST['action'] == 'cancel_booking') {
        // Get booking details before updating
        $booking_query = mysqli_query($conn, "SELECT * FROM bookings WHERE id='$id'");
        $booking = mysqli_fetch_assoc($booking_query);
        
        // Update booking status
        mysqli_query($conn, "UPDATE bookings SET status='cancelled' WHERE id='$id'");
        
        // Get user ID from email
        $user_query = mysqli_query($conn, "SELECT id FROM users WHERE email='{$booking['email']}'");
        if ($user_row = mysqli_fetch_assoc($user_query)) {
            // Create notification for user
            NotificationHelper::create(
                $conn,
                $user_row['id'],
                'user',
                'booking_cancelled',
                'Booking Cancelled',
                "Your booking #$id has been cancelled by admin.",
                $id
            );
        }
        
        $_SESSION['success'] = "Booking cancelled!";
    }
    
    elseif ($_POST['action'] == 'delete_booking') {
        // This action is kept for other parts of admin panel but not used in bookings table
        mysqli_query($conn, "DELETE FROM bookings WHERE id='$id'");
        $_SESSION['success'] = "Booking deleted!";
    }
    
    elseif ($_POST['action'] == 'toggle_user') {
        mysqli_query($conn, "UPDATE users SET status = IF(status='active','inactive','active') WHERE id='$id'");
        $_SESSION['success'] = "User status updated!";
    }
    
    elseif ($_POST['action'] == 'delete_user') {
        mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
        $_SESSION['success'] = "User deleted!";
    }
    
    elseif ($_POST['action'] == 'delete_destination') {
        mysqli_query($conn, "DELETE FROM destinations WHERE id='$id'");
        $_SESSION['success'] = "Destination deleted!";
    }
    
    header("Location: admin-dashboard.php?page=$page" . ($search ? "&search=".urlencode($search) : ""));
    exit();
}

// Handle destination forms with image upload
if (isset($_POST['add_destination']) || isset($_POST['edit_destination'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $page_link = mysqli_real_escape_string($conn, $_POST['page_link']);
    
    // Destination details
    $duration = mysqli_real_escape_string($conn, $_POST['duration'] ?? '5 Days');
    $difficulty = mysqli_real_escape_string($conn, $_POST['difficulty'] ?? 'Easy');
    $best_season = mysqli_real_escape_string($conn, $_POST['best_season'] ?? 'Year-Round');
    $highlights = mysqli_real_escape_string($conn, $_POST['highlights'] ?? '');
    $package_details = mysqli_real_escape_string($conn, $_POST['package_details'] ?? '');
    
    // Handle itinerary as JSON
    if (isset($_POST['itinerary_day']) && isset($_POST['itinerary_desc'])) {
        $itinerary_days = $_POST['itinerary_day'];
        $itinerary_descs = $_POST['itinerary_desc'];
        $itinerary_data = [];
        
        for ($i = 0; $i < count($itinerary_days); $i++) {
            if (!empty($itinerary_days[$i]) && !empty($itinerary_descs[$i])) {
                $itinerary_data[] = [
                    'title' => mysqli_real_escape_string($conn, $itinerary_days[$i]),
                    'description' => mysqli_real_escape_string($conn, $itinerary_descs[$i])
                ];
            }
        }
        
        $itinerary_json = json_encode($itinerary_data);
    } else {
        $itinerary_json = '';
    }
    
    // Services and options
    $included_services = mysqli_real_escape_string($conn, $_POST['included_services'] ?? '');
    $not_included = mysqli_real_escape_string($conn, $_POST['not_included'] ?? '');
    $hotel_options = mysqli_real_escape_string($conn, $_POST['hotel_options'] ?? "Basic Hotel\n3-Star Hotel\n4-Star Hotel\n5-Star Hotel\nLuxury Resort");
    $transport_options = mysqli_real_escape_string($conn, $_POST['transport_options'] ?? "Bus\nFlight\nPrivate Vehicle\nJeep");
    
    // Handle image upload
    $image_url = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024;
        
        if (in_array($_FILES['image']['type'], $allowed_types) && $_FILES['image']['size'] <= $max_size) {
            $upload_dir = 'uploads/destinations/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = 'destination_' . time() . '_' . uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
                $image_url = $file_path;
                
                if (isset($_POST['edit_destination']) && isset($_POST['old_image']) && !empty($_POST['old_image']) && file_exists($_POST['old_image'])) {
                    unlink($_POST['old_image']);
                }
            } else {
                $_SESSION['error'] = "Failed to upload image.";
                header("Location: admin-dashboard.php?page=destinations");
                exit();
            }
        } else {
            $_SESSION['error'] = "Invalid image file. Only JPG, PNG, GIF, WebP up to 5MB allowed.";
            header("Location: admin-dashboard.php?page=destinations");
            exit();
        }
    } elseif (isset($_POST['old_image']) && !empty($_POST['old_image'])) {
        $image_url = $_POST['old_image'];
    } else {
        $image_url = mysqli_real_escape_string($conn, $_POST['image_url'] ?? '');
    }
    
    if (isset($_POST['add_destination'])) {
        $query = "INSERT INTO destinations (name, description, price, image_url, page_link, duration, difficulty, best_season, highlights, package_details, itinerary, included_services, not_included, hotel_options, transport_options) 
                  VALUES ('$name', '$description', '$price', '$image_url', '$page_link', '$duration', '$difficulty', '$best_season', '$highlights', '$package_details', '$itinerary_json', '$included_services', '$not_included', '$hotel_options', '$transport_options')";
        $_SESSION['success'] = "Destination added successfully!";
    } else {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $query = "UPDATE destinations SET 
                  name='$name', 
                  description='$description', 
                  price='$price', 
                  image_url='$image_url', 
                  page_link='$page_link',
                  duration='$duration',
                  difficulty='$difficulty',
                  best_season='$best_season',
                  highlights='$highlights',
                  package_details='$package_details',
                  itinerary='$itinerary_json',
                  included_services='$included_services',
                  not_included='$not_included',
                  hotel_options='$hotel_options',
                  transport_options='$transport_options',
                  updated_at=NOW() 
                  WHERE id='$id'";
        $_SESSION['success'] = "Destination updated successfully!";
    }
    
    if (!mysqli_query($conn, $query)) $_SESSION['error'] = "Error: " . mysqli_error($conn);
    header("Location: admin-dashboard.php?page=destinations");
    exit();
}

// Get stats
function getStat($conn, $query) { 
    $res = mysqli_fetch_assoc(mysqli_query($conn, $query)); 
    return $res ? reset($res) : 0; 
}

$stats = [
    'bookings' => getStat($conn, "SELECT COUNT(*) FROM bookings"),
    'pending' => getStat($conn, "SELECT COUNT(*) FROM bookings WHERE status='pending' OR status IS NULL OR status = ''"),
    'confirmed' => getStat($conn, "SELECT COUNT(*) FROM bookings WHERE status='confirmed'"),
    'cancelled' => getStat($conn, "SELECT COUNT(*) FROM bookings WHERE status='cancelled' OR status='cancel'"),
    'revenue' => getStat($conn, "SELECT COALESCE(SUM(price),0) FROM bookings WHERE status='confirmed'"),
    'users' => getStat($conn, "SELECT COUNT(*) FROM users WHERE role='user'"),
    'active' => getStat($conn, "SELECT COUNT(*) FROM users WHERE role='user' AND status='active'"),
    'destinations' => getStat($conn, "SELECT COUNT(*) FROM destinations")
];

// Get all notifications for admin
$all_notifications = NotificationHelper::getAll($conn, $admin_id, 'admin', 50);

// Get data for current page
$data = [];
if ($page == 'users') {
    $where = "WHERE role='user'";
    if ($search) $where .= " AND (fullname LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
    $data['users'] = mysqli_query($conn, "SELECT * FROM users $where ORDER BY created_at DESC");
} 
elseif ($page == 'bookings') {
    $where = $search ? " WHERE full_name LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR email LIKE '%$search%' OR destination LIKE '%$search%' OR package LIKE '%$search%'" : "";
    $data['bookings'] = mysqli_query($conn, "SELECT * FROM bookings $where ORDER BY booking_date DESC");
}
elseif ($page == 'destinations') {
    $where = $search ? " WHERE name LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR description LIKE '%$search%'" : "";
    $data['destinations'] = mysqli_query($conn, "SELECT * FROM destinations $where ORDER BY created_at DESC");
}
elseif ($page == 'dashboard') {
    $data['recent'] = mysqli_query($conn, "SELECT * FROM bookings ORDER BY booking_date DESC LIMIT 5");
}
elseif ($page == 'notifications') $data['notifications'] = NotificationHelper::getAll($conn, $admin_id, 'admin', 100);

// Get destination for edit
$edit_destination = null;
if (isset($_GET['edit'])) {
    $edit_id = mysqli_real_escape_string($conn, $_GET['edit']);
    $result = mysqli_query($conn, "SELECT * FROM destinations WHERE id='$edit_id'");
    $edit_destination = mysqli_fetch_assoc($result);
    
    if ($edit_destination && !empty($edit_destination['itinerary'])) {
        $edit_itinerary = json_decode($edit_destination['itinerary'], true);
        $edit_destination['itinerary_array'] = $edit_itinerary ?: [];
    } else {
        $edit_destination['itinerary_array'] = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TripNext</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #6a11cb; 
            --secondary: #2575fc; 
            --success: #00b09b; 
            --warning: #ffa726; 
            --danger: #ff416c; 
            --dark: #1e1e2d; 
            --light: #f8f9fc; 
            --text-dark: #2d3748; 
            --text-light: #718096; 
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Segoe UI', sans-serif; 
        }
        
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); 
            display: flex; 
            min-height: 100vh; 
        }
        
        /* Sidebar Styles */
        .sidebar { 
            width: 280px; 
            background: rgba(30, 30, 45, 0.95); 
            height: 100vh; 
            position: fixed; 
            padding: 30px 0; 
            border-right: 1px solid rgba(255, 255, 255, 0.1); 
            box-shadow: 5px 0 25px rgba(0, 0, 0, 0.2); 
        }
        
        .sidebar-header { 
            padding: 0 25px 25px; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.1); 
            margin-bottom: 25px; 
            color: white; 
        }
        
        .sidebar-header h2 { 
            font-size: 1.6rem; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
        }
        
        .admin-info { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 15px 25px; 
            background: rgba(255, 255, 255, 0.05); 
            border-radius: 12px; 
            margin: 0 25px 25px; 
        }
        
        .admin-avatar { 
            width: 45px; 
            height: 45px; 
            background: linear-gradient(135deg, var(--primary), var(--secondary)); 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: white; 
            font-weight: bold; 
            font-size: 1.2rem; 
        }
        
        .nav-menu { 
            list-style: none; 
            padding: 0 15px; 
        }
        
        .nav-item { 
            margin-bottom: 8px; 
        }
        
        .nav-link { 
            display: flex; 
            align-items: center; 
            padding: 15px 20px; 
            color: #a0aec0; 
            text-decoration: none; 
            border-radius: 12px; 
            transition: all 0.3s ease; 
            position: relative;
        }
        
        .nav-link:hover { 
            background: rgba(255, 255, 255, 0.08); 
            color: white; 
            transform: translateX(5px); 
        }
        
        .nav-link.active { 
            background: linear-gradient(135deg, rgba(106, 17, 203, 0.2), rgba(37, 117, 252, 0.2)); 
            color: white; 
        }
        
        .nav-link i { 
            width: 24px; 
            margin-right: 12px; 
        }
        
        .notification-dot {
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
        }
        
        /* Main Content */
        .main-content { 
            flex: 1; 
            margin-left: 280px; 
            padding: 30px; 
        }
        
        /* Header with Notification Bell */
        .top-header { 
            background: white; 
            padding: 25px 30px; 
            border-radius: 20px; 
            margin-bottom: 30px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        .page-title { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
        }
        
        .page-title h1 { 
            font-size: 2rem; 
            font-weight: 700; 
            background: linear-gradient(135deg, var(--primary), var(--secondary)); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
            background-clip: text; 
        }
        
        .page-icon { 
            width: 60px; 
            height: 60px; 
            background: linear-gradient(135deg, var(--primary), var(--secondary)); 
            border-radius: 15px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: white; 
            font-size: 1.8rem; 
            box-shadow: 0 8px 20px rgba(106, 17, 203, 0.3); 
        }
        
        /* Notification Bell */
        .notification-bell {
            position: relative;
            margin-right: 20px;
            cursor: pointer;
        }
        
        .bell-icon {
            font-size: 1.5rem;
            color: var(--primary);
            transition: all 0.3s ease;
        }
        
        .bell-icon:hover {
            transform: scale(1.1);
            color: var(--secondary);
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
            font-weight: bold;
            min-width: 20px;
            text-align: center;
            display: none;
        }
        
        .notification-dropdown {
            position: absolute;
            top: 40px;
            right: -20px;
            width: 350px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            display: none;
            z-index: 1000;
            border: 1px solid #e2e8f0;
        }
        
        .notification-dropdown.active {
            display: block;
        }
        
        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 12px 12px 0 0;
        }
        
        .notification-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }
        
        .view-all-link {
            color: white;
            text-decoration: none;
            font-size: 0.85rem;
            opacity: 0.9;
        }
        
        .view-all-link:hover {
            opacity: 1;
            text-decoration: underline;
        }
        
        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transition: all 0.3s ease;
        }
        
        .notification-item:hover {
            background: rgba(106, 17, 203, 0.05);
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--text-dark);
        }
        
        .notification-message {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-bottom: 4px;
        }
        
        .notification-time {
            font-size: 0.75rem;
            color: #a0aec0;
        }
        
        .notification-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--text-light);
        }
        
        .notification-empty i {
            font-size: 3rem;
            opacity: 0.3;
            margin-bottom: 15px;
        }
        
        /* Stats Grid - REMOVED THE LINE DESIGN */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 20px; 
            margin-bottom: 40px; 
        }
        
        .stat-card { 
            background: white; 
            padding: 20px 25px; 
            border-radius: 16px; 
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06); 
            transition: all 0.3s ease; 
        }
        
        .stat-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1); 
        }
        
        .stat-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 10px; 
        }
        
        .stat-value { 
            font-size: 2rem; 
            font-weight: 700; 
            color: var(--dark); 
            margin-top: 5px;
        }
        
        .stat-icon { 
            width: 50px; 
            height: 50px; 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.5rem; 
            color: white; 
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Search */
        .search-container { 
            background: white; 
            padding: 25px; 
            border-radius: 20px; 
            margin-bottom: 30px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); 
        }
        
        .search-box { 
            display: flex; 
            gap: 15px; 
            align-items: center; 
        }
        
        .search-input { 
            flex: 1; 
            padding: 16px 25px; 
            border: 2px solid #e2e8f0; 
            border-radius: 15px; 
            font-size: 1rem; 
            background: var(--light); 
        }
        
        .search-input:focus { 
            outline: none; 
            border-color: var(--primary); 
            box-shadow: 0 0 0 4px rgba(106, 17, 203, 0.1); 
        }
        
        /* Tables */
        .table-container { 
            background: white; 
            border-radius: 20px; 
            overflow: hidden; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); 
            margin-bottom: 40px; 
        }
        
        .table-header { 
            padding: 25px 30px; 
            border-bottom: 1px solid #e2e8f0; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        
        th { 
            padding: 15px 20px; 
            text-align: left; 
            font-weight: 600; 
            color: var(--text-dark); 
            border-bottom: 2px solid #e2e8f0; 
            background: #f8fafc; 
        }
        
        td { 
            padding: 12px 20px; 
            border-bottom: 1px solid #f1f5f9; 
        }
        
        tbody tr:hover { 
            background: #f8fafc; 
        }
        
        /* Notifications Page */
        .notifications-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .notifications-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .notification-item-full {
            padding: 20px 25px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            transition: all 0.3s ease;
        }
        
        .notification-item-full:hover {
            background: rgba(106, 17, 203, 0.05);
        }
        
        .notification-date-group {
            background: #f8fafc;
            padding: 15px 25px;
            font-weight: 600;
            color: var(--primary);
            border-bottom: 1px solid #e2e8f0;
        }
        
        /* Alerts */
        .alert-success, .alert-error { 
            padding: 20px 25px; 
            border-radius: 15px; 
            margin-bottom: 25px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        .alert-success { 
            background: linear-gradient(135deg, #d4edda, #c3e6cb); 
            color: #155724; 
            border-left: 5px solid #28a745; 
        }
        
        .alert-error { 
            background: linear-gradient(135deg, #f8d7da, #f5b7b1); 
            color: #721c24; 
            border-left: 5px solid #dc3545; 
        }
        
        /* Buttons */
        .btn { 
            padding: 8px 16px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 600; 
            transition: all 0.3s ease; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            text-decoration: none; 
            font-size: 0.85rem;
        }
        
        .btn-primary { 
            background: linear-gradient(135deg, var(--primary), var(--secondary)); 
            color: white; 
        }
        
        .btn-success { 
            background: linear-gradient(135deg, #28a745, #20c997); 
            color: white; 
        }
        
        .btn-danger { 
            background: linear-gradient(135deg, #dc3545, #fd7e14); 
            color: white; 
        }
        
        .btn-warning { 
            background: linear-gradient(135deg, #ffc107, #ffa726); 
            color: #212529; 
        }
        
        .btn-secondary { 
            background: #6c757d; 
            color: white; 
        }
        
        .btn-sm { 
            padding: 5px 12px; 
            font-size: 0.75rem; 
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Status Badges */
        .status-badge { 
            padding: 5px 12px; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center; 
            gap: 5px; 
        }
        
        .status-pending { 
            background: linear-gradient(135deg, #fff3cd, #ffeaa7); 
            color: #856404; 
        }
        
        .status-confirmed { 
            background: linear-gradient(135deg, #d1ecf1, #a8e6cf); 
            color: #0c5460; 
        }
        
        .status-cancelled { 
            background: linear-gradient(135deg, #f8d7da, #f5b7b1); 
            color: #721c24; 
        }
        
        .status-active { 
            background: linear-gradient(135deg, #d4edda, #c5e1a5); 
            color: #155724; 
        }
        
        .status-inactive { 
            background: linear-gradient(135deg, #f8d7da, #f5b7b1); 
            color: #721c24; 
        }
        
        /* Forms */
        .form-group { 
            margin-bottom: 20px; 
        }
        
        .form-label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: var(--text-dark); 
        }
        
        .form-input { 
            width: 100%; 
            padding: 14px 18px; 
            border: 2px solid #e2e8f0; 
            border-radius: 12px; 
            font-size: 1rem; 
        }
        
        .form-input:focus { 
            outline: none; 
            border-color: var(--primary); 
            box-shadow: 0 0 0 4px rgba(106, 17, 203, 0.1); 
        }
        
        .form-textarea { 
            width: 100%; 
            padding: 14px 18px; 
            border: 2px solid #e2e8f0; 
            border-radius: 12px; 
            font-size: 1rem; 
            min-height: 120px; 
            resize: vertical; 
        }
        
        .form-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
        }
        
        /* Image Upload */
        .image-upload-container { 
            margin: 15px 0; 
        }
        
        .image-preview { 
            width: 200px; 
            height: 150px; 
            border: 2px dashed #ddd; 
            border-radius: 10px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            overflow: hidden; 
            margin-top: 10px; 
            background: #f8f9fa; 
        }
        
        .image-preview img { 
            max-width: 100%; 
            max-height: 100%; 
            object-fit: cover; 
        }
        
        .file-input { 
            display: none; 
        }
        
        .upload-btn { 
            background: var(--primary); 
            color: white; 
            padding: 10px 20px; 
            border-radius: 8px; 
            cursor: pointer; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            margin-top: 10px; 
        }
        
        /* Itinerary Builder */
        .itinerary-day-form { 
            background: #f8f9fa; 
            border: 1px solid #e2e8f0; 
            border-radius: 10px; 
            padding: 15px; 
            margin-bottom: 15px; 
        }
        
        .itinerary-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 10px; 
        }
        
        .itinerary-day-input { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
        }
        
        .itinerary-desc-input { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            min-height: 60px; 
            resize: vertical; 
        }
        
        .action-buttons { 
            display: flex; 
            gap: 8px; 
            flex-wrap: wrap; 
        }
        
        .empty { 
            text-align: center; 
            padding: 60px 40px; 
            color: var(--text-light); 
        }
        
        .empty-icon {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 20px;
        }
        
        /* Booking detail badges - NO ICONS */
        .booking-detail-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .hotel-badge {
            background: #e3f2fd;
            color: #0d47a1;
        }
        
        .transport-badge {
            background: #e8f5e9;
            color: #1b5e20;
        }
        
        /* Responsive */
        @media (max-width: 992px) { 
            .sidebar { 
                width: 80px; 
            } 
            .sidebar-header h2 span, 
            .admin-info div:last-child, 
            .nav-link span { 
                display: none; 
            } 
            .main-content { 
                margin-left: 80px; 
            } 
            .notification-dropdown {
                right: -100px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) { 
            .main-content { 
                padding: 20px; 
                margin-left: 0; 
            } 
            .sidebar { 
                width: 100%; 
                height: auto; 
                position: relative; 
            } 
            .stats-grid { 
                grid-template-columns: 1fr; 
            } 
            .top-header { 
                flex-direction: column; 
                gap: 20px; 
            } 
            table { 
                min-width: 1400px; 
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .notification-dropdown {
                right: -150px;
                width: 300px;
            }
        }
        
        @media (max-width: 480px) {
            .notification-dropdown {
                right: -100px;
                width: 280px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-crown"></i> <span>Admin Panel</span></h2>
            <p style="color: #a0aec0; font-size: 0.9rem;">Travel Management</p>
        </div>
        <div class="admin-info">
            <div class="admin-avatar"><?php echo strtoupper(substr($_SESSION['fullname'], 0, 1)); ?></div>
            <div>
                <div style="font-weight: 600; color: white;"><?php echo htmlspecialchars($_SESSION['fullname']); ?></div>
                <div style="font-size: 0.85rem; color: #a0aec0;">Administrator</div>
            </div>
        </div>
        <ul class="nav-menu">
            <?php 
            $pages = [
                'dashboard' => ['fas fa-tachometer-alt', 'Dashboard'],
                'bookings' => ['fas fa-calendar-check', 'Bookings'],
                'users' => ['fas fa-users-cog', 'User Management'],
                'destinations' => ['fas fa-map-marked-alt', 'Destinations'],
                'notifications' => ['fas fa-bell', 'Notifications']
            ];
            
            foreach($pages as $p => $icon): 
                $has_notification_dot = ($p == 'notifications' && mysqli_num_rows($all_notifications) > 0) ? true : false;
            ?>
            <li class="nav-item">
                <a href="?page=<?php echo $p; ?>" class="nav-link <?php echo $page == $p ? 'active' : ''; ?>">
                    <i class="<?php echo $icon[0]; ?>"></i>
                    <span><?php echo $icon[1]; ?></span>
                    <?php if ($has_notification_dot && $page != 'notifications'): ?>
                        <span class="notification-dot" style="display: block;"></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
            <li class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="page-title">
                <div class="page-icon">
                    <i class="fas fa-<?php 
                        echo $page == 'dashboard' ? 'tachometer-alt' : 
                            ($page == 'bookings' ? 'calendar-check' : 
                            ($page == 'users' ? 'users-cog' : 
                            ($page == 'destinations' ? 'map-marked-alt' : 'bell'))); 
                    ?>"></i>
                </div>
                <div>
                    <h1>
                        <?php 
                        echo [
                            'dashboard' => 'Dashboard',
                            'bookings' => 'Booking Management',
                            'users' => 'User Management',
                            'destinations' => 'Destination Management',
                            'notifications' => 'Notifications'
                        ][$page] ?? 'Admin Panel'; 
                        ?>
                    </h1>
                    <p style="color: var(--text-light); margin-top: 5px;">
                        <?php 
                        echo [
                            'dashboard' => 'System analytics and overview',
                            'bookings' => 'Manage all travel bookings',
                            'users' => 'Manage system users',
                            'destinations' => 'Add, edit, and remove destinations',
                            'notifications' => 'View all system notifications'
                        ][$page]; 
                        ?>
                    </p>
                </div>
            </div>
            <div style="display: flex; gap: 20px; align-items: center;">
                <!-- Notification Bell -->
                <div class="notification-bell" id="notificationBell">
                    <i class="fas fa-bell bell-icon"></i>
                    <span class="notification-badge" id="notificationBadge">0</span>
                    
                    <!-- Notification Dropdown -->
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h3><i class="fas fa-bell"></i> Notifications</h3>
                            <a href="?page=notifications" class="view-all-link">View All</a>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div class="notification-empty">
                                <i class="fas fa-bell-slash"></i>
                                <p>No notifications yet</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center; padding: 10px 20px; background: rgba(106, 17, 203, 0.05); border-radius: 10px;">
                    <div style="font-size: 1.4rem; font-weight: 700; color: var(--primary);"><?php echo $stats['bookings']; ?></div>
                    <div style="font-size: 0.85rem; color: var(--text-light);">Total Bookings</div>
                </div>
                <div style="text-align: center; padding: 10px 20px; background: rgba(106, 17, 203, 0.05); border-radius: 10px;">
                    <div style="font-size: 1.4rem; font-weight: 700; color: var(--primary);"><?php echo $stats['destinations']; ?></div>
                    <div style="font-size: 0.85rem; color: var(--text-light);">Destinations</div>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert-success">
            <span><i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; ?></span>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: #155724; font-size: 1.2rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php unset($_SESSION['success']); endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert-error">
            <span><i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; ?></span>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: #721c24; font-size: 1.2rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php unset($_SESSION['error']); endif; ?>

        <!-- DASHBOARD PAGE -->
        <?php if ($page == 'dashboard'): ?>
            <div class="stats-grid">
                <?php 
                $statCards = [
                    ['Total Bookings', $stats['bookings'], 'fas fa-calendar-alt', 'var(--primary)'],
                    ['Pending', $stats['pending'], 'fas fa-clock', 'var(--warning)'],
                    ['Confirmed', $stats['confirmed'], 'fas fa-check-circle', 'var(--success)'],
                    ['Cancelled', $stats['cancelled'], 'fas fa-times-circle', 'var(--danger)'],
                    ['Revenue', 'Rs ' . number_format($stats['revenue']), 'fas fa-rupee-sign', '#9d4edd'],
                    ['Users', $stats['users'], 'fas fa-user-friends', '#ff6d00'],
                    ['Active Users', $stats['active'], 'fas fa-user-check', '#06d6a0'],
                    ['Destinations', $stats['destinations'], 'fas fa-map-marker-alt', '#118ab2']
                ];
                foreach ($statCards as $card): ?>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label"><?php echo $card[0]; ?></span>
                        <div class="stat-icon" style="background: <?php echo $card[3]; ?>;">
                            <i class="<?php echo $card[2]; ?>"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $card[1]; ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-history"></i> Recent Bookings</h3>
                    <a href="?page=bookings" class="btn btn-primary btn-sm">View All</a>
                </div>
                <?php if (mysqli_num_rows($data['recent']) > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Destination</th>
                                <th>Package</th>
                                <th>Hotel</th>
                                <th>Transport</th>
                                <th>Travelers</th>
                                <th>Dates</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($data['recent'])): 
                                $row_status = !empty($row['status']) ? $row['status'] : 'pending';
                                if ($row_status == 'cancel') $row_status = 'cancelled';
                            ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                    <div style="font-size: 0.85rem; color: var(--text-light);"><?php echo htmlspecialchars($row['email']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($row['destination']); ?></td>
                                <td><?php echo htmlspecialchars($row['package'] ?? 'Basic'); ?></td>
                                <td>
                                    <?php if (!empty($row['hotel'])): ?>
                                        <span class="booking-detail-badge hotel-badge">
                                            <?php echo htmlspecialchars($row['hotel']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-light);">Not selected</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['transport'])): ?>
                                        <span class="booking-detail-badge transport-badge">
                                            <?php echo htmlspecialchars($row['transport']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-light);">Not selected</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row['travelers'] ?? 1; ?></td>
                                <td>
                                    <div style="font-size: 0.85rem;">
                                        <?php echo date('d M', strtotime($row['start_date'])); ?> - 
                                        <?php echo date('d M', strtotime($row['end_date'])); ?>
                                    </div>
                                </td>
                                <td><strong>Rs <?php echo number_format($row['price']); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo $row_status; ?>">
                                        <?php echo ucfirst($row_status); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($row_status == 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="action" value="confirm_booking">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Confirm booking #<?php echo $row['id']; ?>?')">
                                                    Confirm
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="action" value="cancel_booking">
                                                <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Cancel booking #<?php echo $row['id']; ?>?')">
                                                    Cancel
                                                </button>
                                            </form>
                                        <?php elseif ($row_status == 'confirmed'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="action" value="cancel_booking">
                                                <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Cancel booking #<?php echo $row['id']; ?>?')">
                                                    Cancel
                                                </button>
                                            </form>
                                        <?php elseif ($row_status == 'cancelled'): ?>
                                            <span class="btn btn-sm btn-danger" style="opacity: 0.6; cursor: default;" disabled>
                                                <i class="fas fa-times-circle"></i> Cancelled
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty">
                    <i class="fas fa-calendar-times empty-icon"></i>
                    <h3>No Bookings Yet</h3>
                    <p>When customers make bookings, they will appear here.</p>
                </div>
                <?php endif; ?>
            </div>

        <!-- BOOKINGS PAGE -->
        <?php elseif ($page == 'bookings'): ?>
            <div class="search-container">
                <h3 style="color: var(--text-dark); margin-bottom: 15px;">
                    <i class="fas fa-search"></i> Search Bookings
                </h3>
                <form method="GET" class="search-box">
                    <input type="hidden" name="page" value="bookings">
                    <input type="text" name="search" class="search-input" placeholder="Search by customer name, email, or destination..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="?page=bookings" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-list"></i> All Bookings</h3>
                    <div style="background: rgba(106, 17, 203, 0.1); color: var(--primary); padding: 8px 16px; border-radius: 20px; font-weight: 600;">
                        <?php echo $stats['bookings']; ?> bookings
                    </div>
                </div>
                
                <?php if (isset($data['bookings']) && mysqli_num_rows($data['bookings']) > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Destination</th>
                                <th>Package</th>
                                <th>Hotel</th>
                                <th>Transport</th>
                                <th>Travelers</th>
                                <th>Dates</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Booked On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($data['bookings'])): 
                                $row_status = !empty($row['status']) ? $row['status'] : 'pending';
                                if ($row_status == 'cancel') $row_status = 'cancelled';
                                $display_status = ucfirst($row_status);
                            ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                    <div style="font-size: 0.85rem; color: var(--text-light);">
                                        <?php echo htmlspecialchars($row['email']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['destination']); ?></td>
                                <td><?php echo htmlspecialchars($row['package'] ?? 'Basic'); ?></td>
                                <td>
                                    <?php if (!empty($row['hotel'])): ?>
                                        <span class="booking-detail-badge hotel-badge">
                                            <?php echo htmlspecialchars($row['hotel']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-light);">Not selected</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['transport'])): ?>
                                        <span class="booking-detail-badge transport-badge">
                                            <?php echo htmlspecialchars($row['transport']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-light);">Not selected</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row['travelers'] ?? 1; ?></td>
                                <td>
                                    <div style="font-size: 0.85rem;">
                                        <?php echo date('d M', strtotime($row['start_date'])); ?> - 
                                        <?php echo date('d M', strtotime($row['end_date'])); ?>
                                    </div>
                                </td>
                                <td><strong>Rs <?php echo number_format($row['price']); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo $row_status; ?>">
                                        <?php echo $display_status; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($row['booking_date'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($row_status == 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="action" value="confirm_booking">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Confirm booking #<?php echo $row['id']; ?>?')">
                                                    Confirm
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="action" value="cancel_booking">
                                                <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Cancel booking #<?php echo $row['id']; ?>?')">
                                                    Cancel
                                                </button>
                                            </form>
                                        <?php elseif ($row_status == 'confirmed'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="action" value="cancel_booking">
                                                <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Cancel booking #<?php echo $row['id']; ?>?')">
                                                    Cancel
                                                </button>
                                            </form>
                                        <?php elseif ($row_status == 'cancelled'): ?>
                                            <span class="btn btn-sm btn-danger" style="opacity: 0.6; cursor: default;" disabled>
                                                <i class="fas fa-times-circle"></i> Cancelled
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty">
                    <i class="fas fa-calendar-times empty-icon"></i>
                    <h3>No Bookings Found</h3>
                    <p><?php echo !empty($search) ? 'No bookings match your search.' : 'No bookings in system.'; ?></p>
                </div>
                <?php endif; ?>
            </div>

        <!-- USERS PAGE -->
        <?php elseif ($page == 'users'): ?>
            <div class="search-container">
                <h3 style="color: var(--text-dark); margin-bottom: 15px;">
                    <i class="fas fa-search"></i> Search Users
                </h3>
                <form method="GET" class="search-box">
                    <input type="hidden" name="page" value="users">
                    <input type="text" name="search" class="search-input" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="?page=users" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-users"></i> User Management</h3>
                    <div style="background: rgba(106, 17, 203, 0.1); color: var(--primary); padding: 8px 16px; border-radius: 20px; font-weight: 600;">
                        <?php echo isset($data['users']) ? mysqli_num_rows($data['users']) : 0; ?> users
                    </div>
                </div>
                
                <?php if (isset($data['users']) && mysqli_num_rows($data['users']) > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email / Phone</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($data['users'])): ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($row['fullname']); ?></div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($row['email']); ?></div>
                                    <div style="font-size: 0.85rem; color: var(--text-light);">
                                        <?php echo htmlspecialchars($row['phone'] ?? 'Not provided'); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $row['status'] ?? 'active'; ?>">
                                        <?php echo ucfirst($row['status'] ?? 'active'); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <div style="display: flex; gap: 10px;">
                                        <form method="POST">
                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="toggle_user">
                                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('<?php echo ($row['status'] == 'active' ? 'Deactivate' : 'Activate'); ?> user #<?php echo $row['id']; ?>?')">
                                                <?php echo ($row['status'] ?? 'active') == 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete user #<?php echo $row['id']; ?>? This action cannot be undone.')">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty">
                    <i class="fas fa-user-slash empty-icon"></i>
                    <h3>No Users Found</h3>
                    <p><?php echo !empty($search) ? 'No users match your search.' : 'No users registered.'; ?></p>
                </div>
                <?php endif; ?>
            </div>

        <!-- DESTINATIONS PAGE -->
        <?php elseif ($page == 'destinations'): ?>
            <!-- Add/Edit Destination Form -->
            <div style="background: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); margin-bottom: 30px;">
                <h2 style="color: var(--text-dark); margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-<?php echo $edit_destination ? 'edit' : 'plus-circle'; ?>"></i>
                    <?php echo $edit_destination ? 'Edit Destination' : 'Add New Destination'; ?>
                </h2>
                <form method="POST" enctype="multipart/form-data">
                    <?php if ($edit_destination): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_destination['id']; ?>">
                        <input type="hidden" name="edit_destination" value="1">
                        <input type="hidden" name="old_image" value="<?php echo $edit_destination['image_url'] ?? ''; ?>">
                    <?php else: ?>
                        <input type="hidden" name="add_destination" value="1">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Destination Name *</label>
                            <input type="text" name="name" class="form-input" value="<?php echo $edit_destination ? htmlspecialchars($edit_destination['name']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Price (Rs) *</label>
                            <input type="text" name="price" class="form-input" value="<?php echo $edit_destination ? htmlspecialchars($edit_destination['price']) : ''; ?>" required placeholder="Rs 45,000">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Short Description *</label>
                        <textarea name="description" class="form-textarea" required><?php echo $edit_destination ? htmlspecialchars($edit_destination['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Duration</label>
                            <input type="text" name="duration" class="form-input" value="<?php echo $edit_destination ? htmlspecialchars($edit_destination['duration'] ?? '5 Days') : '5 Days'; ?>" placeholder="5 Days">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Difficulty Level</label>
                            <select name="difficulty" class="form-input">
                                <option value="Easy" <?php echo ($edit_destination && ($edit_destination['difficulty'] ?? '') == 'Easy') ? 'selected' : ''; ?>>Easy</option>
                                <option value="Moderate" <?php echo ($edit_destination && ($edit_destination['difficulty'] ?? '') == 'Moderate') ? 'selected' : ''; ?>>Moderate</option>
                                <option value="Challenging" <?php echo ($edit_destination && ($edit_destination['difficulty'] ?? '') == 'Challenging') ? 'selected' : ''; ?>>Challenging</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Best Season</label>
                            <input type="text" name="best_season" class="form-input" value="<?php echo $edit_destination ? htmlspecialchars($edit_destination['best_season'] ?? 'Year-Round') : 'Year-Round'; ?>" placeholder="Mar-Nov or Year-Round">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Highlights</label>
                            <textarea name="highlights" class="form-textarea" placeholder="Key features and attractions (separate with commas)"><?php echo $edit_destination ? htmlspecialchars($edit_destination['highlights'] ?? '') : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Detailed Package Description</label>
                        <textarea name="package_details" class="form-textarea" placeholder="Detailed description of what the package includes"><?php echo $edit_destination ? htmlspecialchars($edit_destination['package_details'] ?? '') : ''; ?></textarea>
                    </div>
                    
                    <!-- Itinerary Builder -->
                    <div class="form-group">
                        <label class="form-label">Itinerary Builder *</label>
                        <div id="itineraryBuilder">
                            <?php if ($edit_destination && !empty($edit_destination['itinerary_array'])): ?>
                                <?php foreach ($edit_destination['itinerary_array'] as $index => $day): ?>
                                <div class="itinerary-day-form">
                                    <div class="itinerary-header">
                                        <div style="font-weight: 600;">Day <?php echo $index + 1; ?></div>
                                        <?php if ($index > 0): ?>
                                        <button type="button" onclick="this.parentElement.parentElement.remove()" style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">
                                            Remove
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 4fr; gap: 10px; margin-top: 10px;">
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-size: 0.9rem;">Day Title</label>
                                            <input type="text" name="itinerary_day[]" class="itinerary-day-input" value="<?php echo htmlspecialchars($day['title']); ?>" required>
                                        </div>
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-size: 0.9rem;">Description</label>
                                            <textarea name="itinerary_desc[]" class="itinerary-desc-input" required><?php echo htmlspecialchars($day['description']); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="itinerary-day-form">
                                    <div class="itinerary-header">
                                        <div style="font-weight: 600;">Day 1</div>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 4fr; gap: 10px; margin-top: 10px;">
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-size: 0.9rem;">Day Title</label>
                                            <input type="text" name="itinerary_day[]" class="itinerary-day-input" placeholder="Day 1: Arrival" required>
                                        </div>
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-size: 0.9rem;">Description</label>
                                            <textarea name="itinerary_desc[]" class="itinerary-desc-input" placeholder="Activities and details for this day" required></textarea>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" onclick="addItineraryDay()" style="background: var(--primary); color: white; border: none; padding: 10px 15px; border-radius: 6px; cursor: pointer; margin-top: 10px;">
                            <i class="fas fa-plus"></i> Add Day
                        </button>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Included Services</label>
                            <textarea name="included_services" class="form-textarea" placeholder="Services included in package (one per line)"><?php echo $edit_destination ? htmlspecialchars($edit_destination['included_services'] ?? '') : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Not Included</label>
                            <textarea name="not_included" class="form-textarea" placeholder="Services not included (one per line)"><?php echo $edit_destination ? htmlspecialchars($edit_destination['not_included'] ?? '') : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Hotel Options</label>
                            <textarea name="hotel_options" class="form-textarea" placeholder="Available hotel categories (one per line)"><?php echo $edit_destination ? htmlspecialchars($edit_destination['hotel_options'] ?? "Basic Hotel\n3-Star Hotel\n4-Star Hotel\n5-Star Hotel\nLuxury Resort") : "Basic Hotel\n3-Star Hotel\n4-Star Hotel\n5-Star Hotel\nLuxury Resort"; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Transport Options</label>
                            <textarea name="transport_options" class="form-textarea" placeholder="Available transport types (one per line)"><?php echo $edit_destination ? htmlspecialchars($edit_destination['transport_options'] ?? "Bus\nFlight\nPrivate Vehicle\nJeep") : "Bus\nFlight\nPrivate Vehicle\nJeep"; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Page Link</label>
                            <input type="text" name="page_link" class="form-input" value="<?php echo $edit_destination ? htmlspecialchars($edit_destination['page_link'] ?? '') : ''; ?>" placeholder="dynamic-destination.php?id=ID">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Image URL</label>
                            <input type="text" name="image_url" class="form-input" value="<?php echo $edit_destination ? htmlspecialchars($edit_destination['image_url'] ?? '') : ''; ?>">
                        </div>
                    </div>
                    
                    <!-- Image Upload -->
                    <div class="form-group">
                        <label class="form-label">Upload Image</label>
                        <div class="image-upload-container">
                            <input type="file" id="imageInput" name="image" class="file-input" accept="image/*" onchange="previewImage(this)">
                            <label for="imageInput" class="upload-btn">
                                <i class="fas fa-cloud-upload-alt"></i> Choose Image
                            </label>
                            
                            <div class="image-preview" id="imagePreview">
                                <?php if ($edit_destination && !empty($edit_destination['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($edit_destination['image_url']); ?>" alt="Current Image" id="previewImage">
                                <?php else: ?>
                                    <div id="previewPlaceholder" style="color: #999; text-align: center;">
                                        <i class="fas fa-image" style="font-size: 3rem; margin-bottom: 10px;"></i><br>
                                        <span>No image selected</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="image-info" style="font-size: 0.85rem; color: var(--text-light); margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Supported: JPG, PNG, GIF, WebP | Max: 5MB
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-<?php echo $edit_destination ? 'save' : 'plus'; ?>"></i>
                            <?php echo $edit_destination ? 'Update Destination' : 'Add Destination'; ?>
                        </button>
                        <?php if ($edit_destination): ?>
                            <a href="?page=destinations" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Search Destinations -->
            <div class="search-container">
                <h3 style="color: var(--text-dark); margin-bottom: 15px;">
                    <i class="fas fa-search"></i> Search Destinations
                </h3>
                <form method="GET" class="search-box">
                    <input type="hidden" name="page" value="destinations">
                    <input type="text" name="search" class="search-input" placeholder="Search destination name or description..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="?page=destinations" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Destinations List -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-map-marked-alt"></i> All Destinations</h3>
                    <div style="background: rgba(106, 17, 203, 0.1); color: var(--primary); padding: 8px 16px; border-radius: 20px; font-weight: 600;">
                        <?php echo isset($data['destinations']) ? mysqli_num_rows($data['destinations']) : 0; ?> destinations
                    </div>
                </div>
                
                <?php if (isset($data['destinations']) && mysqli_num_rows($data['destinations']) > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Destination</th>
                                <th>Description</th>
                                <th>Price</th>
                                <th>Duration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($data['destinations'])): ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td>
                                    <?php if (!empty($row['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($row['image_url']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" style="width: 80px; height: 60px; object-fit: cover; border-radius: 8px;">
                                    <?php else: ?>
                                        <div style="width: 80px; height: 60px; background: linear-gradient(135deg, #6a11cb, #2575fc); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.9rem; font-weight: bold;">
                                            <?php echo substr($row['name'], 0, 3); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--primary);"><?php echo htmlspecialchars($row['name']); ?></div>
                                </td>
                                <td>
                                    <div style="max-width: 300px; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars(substr($row['description'], 0, 100)); ?>...
                                    </div>
                                </td>
                                <td><strong><?php echo htmlspecialchars($row['price']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['duration'] ?? 'N/A'); ?></td>
                                <td>
                                    <div style="display: flex; gap: 10px;">
                                        <a href="?page=destinations&edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form method="POST">
                                            <input type="hidden" name="destination_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="delete_destination">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete destination \"<?php echo addslashes($row['name']); ?>\"?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty">
                    <i class="fas fa-map-marked-alt empty-icon"></i>
                    <h3>No Destinations Found</h3>
                    <p><?php echo !empty($search) ? 'No destinations match your search.' : 'No destinations added yet.'; ?></p>
                </div>
                <?php endif; ?>
            </div>

        <!-- NOTIFICATIONS PAGE -->
        <?php elseif ($page == 'notifications'): ?>
            <div class="notifications-container">
                <div class="table-header">
                    <h3 style="margin: 0; color: var(--text-dark);">
                        <i class="fas fa-bell"></i> All Notifications
                    </h3>
                    <span style="background: rgba(106, 17, 203, 0.1); color: var(--primary); padding: 8px 16px; border-radius: 20px; font-weight: 600;">
                        <?php echo mysqli_num_rows($all_notifications); ?> notifications
                    </span>
                </div>
                
                <?php if (mysqli_num_rows($all_notifications) > 0): ?>
                <div class="notifications-list">
                    <?php 
                    $current_date = '';
                    mysqli_data_seek($all_notifications, 0);
                    while ($notif = mysqli_fetch_assoc($all_notifications)): 
                        $notif_date = date('Y-m-d', strtotime($notif['created_at']));
                        $display_date = date('F d, Y', strtotime($notif['created_at']));
                        
                        // Get icon based on type
                        $icon = 'fa-bell';
                        $bg_color = '#6a11cb';
                        
                        switch($notif['type']) {
                            case 'booking_created':
                                $icon = 'fa-plus-circle';
                                $bg_color = '#28a745';
                                break;
                            case 'booking_edited':
                                $icon = 'fa-edit';
                                $bg_color = '#ffc107';
                                break;
                            case 'booking_cancelled':
                                $icon = 'fa-times-circle';
                                $bg_color = '#dc3545';
                                break;
                            case 'booking_confirmed':
                                $icon = 'fa-check-circle';
                                $bg_color = '#17a2b8';
                                break;
                        }
                        
                        if ($current_date != $notif_date):
                            $current_date = $notif_date;
                    ?>
                        <div class="notification-date-group">
                            <i class="fas fa-calendar-alt"></i> <?php echo $display_date; ?>
                        </div>
                    <?php endif; ?>
                        
                        <div class="notification-item-full">
                            <div class="notification-icon" style="background: <?php echo $bg_color; ?>;">
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div class="notification-time">
                                    <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($notif['created_at'])); ?>
                                    <?php if ($notif['related_id']): ?>
                                        • <a href="?page=bookings&search=<?php echo $notif['related_id']; ?>" style="color: var(--primary); text-decoration: none;">View Booking #<?php echo $notif['related_id']; ?></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="empty">
                    <i class="fas fa-bell-slash empty-icon"></i>
                    <h3 style="color: var(--text-dark); margin-bottom: 10px;">No Notifications</h3>
                    <p style="margin-bottom: 20px; font-size: 1.1rem;">You don't have any notifications yet.</p>
                    <p style="color: var(--text-light);">When users book or edit bookings, you'll see notifications here.</p>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Itinerary Builder
        let dayCount = <?php echo $edit_destination && !empty($edit_destination['itinerary_array']) ? count($edit_destination['itinerary_array']) : 1; ?>;
        
        function addItineraryDay() {
            dayCount++;
            const builder = document.getElementById('itineraryBuilder');
            const dayDiv = document.createElement('div');
            dayDiv.className = 'itinerary-day-form';
            dayDiv.innerHTML = `
                <div class="itinerary-header">
                    <div style="font-weight: 600;">Day ${dayCount}</div>
                    <button type="button" onclick="this.parentElement.parentElement.remove()" style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">
                        Remove
                    </button>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 4fr; gap: 10px; margin-top: 10px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-size: 0.9rem;">Day Title</label>
                        <input type="text" name="itinerary_day[]" class="itinerary-day-input" placeholder="Day ${dayCount}: ..." required>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-size: 0.9rem;">Description</label>
                        <textarea name="itinerary_desc[]" class="itinerary-desc-input" placeholder="Activities and details for this day" required></textarea>
                    </div>
                </div>
            `;
            builder.appendChild(dayDiv);
        }
        
        // Image preview
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const placeholder = document.getElementById('previewPlaceholder');
            const img = document.getElementById('previewImage') || document.createElement('img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    img.id = 'previewImage';
                    img.src = e.target.result;
                    img.style.maxWidth = '100%';
                    img.style.maxHeight = '100%';
                    img.style.objectFit = 'cover';
                    
                    if (placeholder) placeholder.style.display = 'none';
                    preview.innerHTML = '';
                    preview.appendChild(img);
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Notification System
        function loadNotifications() {
            fetch('?ajax=get_notifications')
                .then(response => response.json())
                .then(data => {
                    const notificationList = document.getElementById('notificationList');
                    const notificationBadge = document.getElementById('notificationBadge');
                    
                    if (data.notifications.length > 0) {
                        notificationBadge.textContent = data.notifications.length;
                        notificationBadge.style.display = 'block';
                        
                        let html = '';
                        data.notifications.forEach(notif => {
                            html += `
                                <div class="notification-item">
                                    <div class="notification-icon" style="background: ${notif.bg_color};">
                                        <i class="fas ${notif.icon}"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title">${notif.title}</div>
                                        <div class="notification-message">${notif.message}</div>
                                        <div class="notification-time">${notif.time_ago}</div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        notificationList.innerHTML = html;
                    } else {
                        notificationBadge.style.display = 'none';
                        notificationList.innerHTML = `
                            <div class="notification-empty">
                                <i class="fas fa-bell-slash"></i>
                                <p>No notifications yet</p>
                            </div>
                        `;
                    }
                })
                .catch(error => console.error('Error loading notifications:', error));
        }
        
        // Toggle notification dropdown
        document.getElementById('notificationBell').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('active');
            loadNotifications();
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notificationDropdown');
            const bell = document.getElementById('notificationBell');
            
            if (!bell.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
        
        // Load notifications every 30 seconds
        setInterval(loadNotifications, 30000);
        
        // Initial load
        loadNotifications();
        
        // Form validation for destination form
        document.querySelector('form[enctype="multipart/form-data"]')?.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('imageInput');
            const imageUrl = document.querySelector('input[name="image_url"]')?.value;
            const itineraryDays = document.querySelectorAll('input[name="itinerary_day[]"]').length;
            
            if (itineraryDays < 1) {
                e.preventDefault();
                alert('Please add at least one day to the itinerary.');
                return false;
            }
            
            if (!fileInput.files[0] && !imageUrl?.trim()) {
                e.preventDefault();
                alert('Please either upload an image or provide an image URL.');
                return false;
            }
            
            if (fileInput.files[0]) {
                const maxSize = 5 * 1024 * 1024;
                if (fileInput.files[0].size > maxSize) {
                    e.preventDefault();
                    alert('Image file is too large. Maximum size is 5MB.');
                    return false;
                }
                
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(fileInput.files[0].type)) {
                    e.preventDefault();
                    alert('Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.');
                    return false;
                }
            }
            
            return true;
        });
        
        // Auto-remove alerts after 5 seconds
        setTimeout(() => { 
            document.querySelector('.alert-success')?.remove(); 
            document.querySelector('.alert-error')?.remove(); 
        }, 5000);
    </script>
</body>
</html>