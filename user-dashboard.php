<?php
session_start();
include("db.php");
include("notification_helper.php");

// Check login
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'user') {
    header("Location: signin.php");
    exit();
}

$user_id = $_SESSION['id'];
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'"));

// Handle AJAX notification request
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_notifications') {
    header('Content-Type: application/json');
    
    $notifications = NotificationHelper::getRecent($conn, $user_id, 'user', 10);
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
        $bg_color = '#006d6d';
        
        switch($notif['type']) {
            case 'booking_confirmed':
                $icon = 'fa-check-circle';
                $bg_color = '#28a745';
                break;
            case 'booking_cancelled':
                $icon = 'fa-times-circle';
                $bg_color = '#dc3545';
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

// Handle all POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['cancel_booking'])) {
        $booking_id = mysqli_real_escape_string($conn, $_POST['booking_id']);
        $check = mysqli_query($conn, "SELECT * FROM bookings WHERE id='$booking_id' AND email='{$user['email']}'");
        
        if (mysqli_num_rows($check) > 0) {
            $booking = mysqli_fetch_assoc($check);
            $status = strtolower(trim($booking['status']));
            $start_date_timestamp = strtotime($booking['start_date']);
            $current_timestamp = time();
            $is_trip_completed = $start_date_timestamp < $current_timestamp;
            
            // Only allow cancellation for pending/confirmed bookings that are not completed
            if (($status == 'pending' || $status == 'confirmed') && !$is_trip_completed) {
                mysqli_query($conn, "UPDATE bookings SET status='cancelled' WHERE id='$booking_id'");
                
                // Create notification for user about cancellation
                NotificationHelper::create(
                    $conn,
                    $user_id,
                    'user',
                    'booking_cancelled',
                    'Booking Cancelled',
                    "Your booking #$booking_id has been cancelled successfully.",
                    $booking_id
                );
                
                // Notify admins about cancellation
                NotificationHelper::createForAdmins(
                    $conn,
                    'booking_cancelled',
                    'Booking Cancelled by User',
                    "User {$user['fullname']} cancelled booking #$booking_id",
                    $booking_id
                );
                
                $_SESSION['success'] = "✅ Booking #$booking_id cancelled successfully!";
            } else {
                $_SESSION['error'] = "❌ Cannot cancel this booking.";
            }
        }
        header("Location: user-dashboard.php?tab=bookings");
        exit();
    }
    
    if (isset($_POST['edit_booking'])) {
        $booking_id = mysqli_real_escape_string($conn, $_POST['booking_id']);
        $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
        $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
        $travelers = mysqli_real_escape_string($conn, $_POST['travelers']);
        $package = mysqli_real_escape_string($conn, $_POST['package']);
        $hotel = mysqli_real_escape_string($conn, $_POST['hotel']);
        $transport = mysqli_real_escape_string($conn, $_POST['transport']);
        $special_requests = mysqli_real_escape_string($conn, $_POST['special_requests']);
        
        // Check if booking belongs to user
        $check = mysqli_query($conn, "SELECT * FROM bookings WHERE id='$booking_id' AND email='{$user['email']}'");
        
        if (mysqli_num_rows($check) > 0) {
            $booking = mysqli_fetch_assoc($check);
            $status = strtolower(trim($booking['status']));
            $start_date_timestamp = strtotime($booking['start_date']);
            $current_timestamp = time();
            $is_trip_completed = $start_date_timestamp < $current_timestamp;
            
            // Only allow editing for pending/confirmed bookings that are not completed
            if (($status == 'pending' || $status == 'confirmed') && !$is_trip_completed) {
                // Recalculate price if travelers count changes
                $new_price = $booking['price'];
                if ($travelers != $booking['travelers']) {
                    $base_price_per_person = 21480;
                    $new_price = $base_price_per_person * $travelers;
                }
                
                // Update booking
                $update_query = "UPDATE bookings SET 
                    full_name='$fullname',
                    phone='$phone',
                    start_date='$start_date',
                    end_date='$end_date',
                    travelers='$travelers',
                    price='$new_price',
                    package='$package',
                    hotel='$hotel',
                    transport='$transport',
                    special_requests='$special_requests',
                    updated_at=NOW()
                    WHERE id='$booking_id'";
                
                if (mysqli_query($conn, $update_query)) {
                    // Notify admins about booking edit
                    NotificationHelper::createForAdmins(
                        $conn,
                        'booking_edited',
                        'Booking Updated',
                        "User {$user['fullname']} updated booking #$booking_id",
                        $booking_id
                    );
                    
                    $_SESSION['success'] = "✅ Booking #$booking_id updated successfully!";
                } else {
                    $_SESSION['error'] = "❌ Error updating booking: " . mysqli_error($conn);
                }
            } else {
                $_SESSION['error'] = "❌ Cannot edit this booking.";
            }
        } else {
            $_SESSION['error'] = "❌ Booking not found!";
        }
        header("Location: user-dashboard.php?tab=bookings");
        exit();
    }
    
    if (isset($_POST['update_profile'])) {
        $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        mysqli_query($conn, "UPDATE users SET fullname='$fullname', phone='$phone', updated_at=NOW() WHERE id='$user_id'");
        $_SESSION['fullname'] = $fullname;
        $_SESSION['success'] = "✅ Profile updated successfully!";
        header("Location: user-dashboard.php");
        exit();
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = mysqli_real_escape_string($conn, $_POST['current_password']);
        $new_password = mysqli_real_escape_string($conn, $_POST['new_password']);
        $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);
        
        if ($current_password == $user['password']) {
            if ($new_password == $confirm_password) {
                mysqli_query($conn, "UPDATE users SET password='$new_password', updated_at=NOW() WHERE id='$user_id'");
                $_SESSION['success'] = "✅ Password changed successfully!";
            } else {
                $_SESSION['error'] = "❌ New passwords don't match!";
            }
        } else {
            $_SESSION['error'] = "❌ Current password is incorrect!";
        }
        header("Location: user-dashboard.php?tab=password");
        exit();
    }
}

// Get stats for dashboard
$total_bookings = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM bookings WHERE email='{$user['email']}'"));
$confirmed = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM bookings WHERE email='{$user['email']}' AND LOWER(status)='confirmed'"));
$pending = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM bookings WHERE email='{$user['email']}' AND (LOWER(status)='pending' OR status='' OR status IS NULL)"));
$cancelled = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM bookings WHERE email='{$user['email']}' AND LOWER(status)='cancelled'"));
$total_spent_result = mysqli_query($conn, "SELECT COALESCE(SUM(price),0) as total FROM bookings WHERE email='{$user['email']}' AND LOWER(status)='confirmed'");
$total_spent_row = mysqli_fetch_assoc($total_spent_result);
$total_spent = $total_spent_row['total'];

// Get all bookings for the user
$bookings_query = mysqli_query($conn, "SELECT * FROM bookings WHERE email='{$user['email']}' ORDER BY booking_date DESC");

// Get notifications for notification page
$all_notifications = NotificationHelper::getAll($conn, $user_id, 'user', 50);

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
$titles = [
    'dashboard' => ['Dashboard', 'Your travel summary and overview'],
    'bookings' => ['My Bookings', 'Manage and view all your bookings'],
    'notifications' => ['Notifications', 'All your notifications'],
    'profile' => ['Profile Settings', 'Update your personal information'],
    'password' => ['Change Password', 'Update your account password']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - TripNext</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #006d6d;
            --secondary: #00a8a8;
            --accent: #ff5a00;
            --danger: #ff416c;
            --success: #28a745;
            --warning: #ffc107;
            --text-dark: #2d3748;
            --text-light: #718096;
            --light-bg: #f5f7fa;
            --border-color: #e2e8f0;
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        }
        
        body { 
            background: var(--light-bg); 
            min-height: 100vh; 
            color: var(--text-dark);
        }
        
        /* Header Styles */
        .header { 
            background: white; 
            padding: 20px 30px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 3px solid var(--primary); 
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .logo { 
            font-size: 1.8rem; 
            font-weight: bold; 
            color: var(--primary); 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        
        .user-info { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
        }
        
        .avatar { 
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
        
        /* Notification Bell */
        .notification-bell {
            position: relative;
            margin-right: 15px;
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
            border: 1px solid var(--border-color);
        }
        
        .notification-dropdown.active {
            display: block;
        }
        
        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
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
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transition: all 0.3s ease;
        }
        
        .notification-item:hover {
            background: rgba(0,109,109,0.05);
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
        
        .logout-btn { 
            background: var(--accent); 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #e04a00;
            transform: translateY(-2px);
        }
        
        /* Main Layout */
        .container { 
            display: flex; 
            min-height: calc(100vh - 85px); 
        }
        
        .sidebar { 
            width: 280px; 
            background: white; 
            border-right: 1px solid var(--border-color); 
            padding: 30px 0; 
            box-shadow: 2px 0 8px rgba(0,0,0,0.05);
        }
        
        .sidebar-header { 
            padding: 0 25px 25px; 
            border-bottom: 1px solid var(--border-color); 
            margin-bottom: 20px; 
        }
        
        .nav-menu { 
            list-style: none; 
            padding: 0 15px; 
        }
        
        .nav-link { 
            display: flex; 
            align-items: center; 
            padding: 15px 20px; 
            color: var(--text-dark); 
            text-decoration: none; 
            border-radius: 10px; 
            margin-bottom: 8px; 
            transition: all 0.3s ease;
            position: relative;
        }
        
        .nav-link:hover { 
            background: rgba(0,109,109,0.05); 
            transform: translateX(5px);
        }
        
        .nav-link.active { 
            background: rgba(0,109,109,0.1); 
            color: var(--primary); 
            font-weight: 600;
        }
        
        .nav-link i { 
            width: 24px; 
            margin-right: 12px; 
            font-size: 1.1rem;
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
        
        .main-content { 
            flex: 1; 
            padding: 30px; 
            max-width: calc(100% - 280px);
        }
        
        /* Page Header */
        .page-header { 
            margin-bottom: 30px; 
        }
        
        .page-title { 
            font-size: 2rem; 
            color: var(--primary); 
            margin-bottom: 8px; 
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-subtitle { 
            color: var(--text-light);
            font-size: 1rem;
        }
        
        /* Stats Cards - NO LINE DESIGN */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            margin-bottom: 40px; 
        }
        
        .stat-card { 
            background: white; 
            padding: 20px 25px; 
            border-radius: 12px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
            display: flex; 
            align-items: center; 
            gap: 20px; 
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .stat-icon { 
            width: 50px; 
            height: 50px; 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.3rem; 
            color: white; 
        }
        
        .stat-value { 
            font-size: 1.8rem; 
            font-weight: 700; 
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 5px;
        }
        
        /* Tables */
        .table-container { 
            background: white; 
            border-radius: 12px; 
            overflow: hidden; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
            margin-bottom: 30px; 
        }
        
        .table-header { 
            padding: 25px; 
            border-bottom: 1px solid var(--border-color); 
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        
        th, td { 
            padding: 16px 20px; 
            text-align: left; 
            border-bottom: 1px solid var(--border-color); 
        }
        
        th { 
            background: #f8fafc; 
            font-weight: 600; 
            color: var(--text-dark);
        }
        
        tbody tr:hover { 
            background: #f8fafc; 
        }
        
        /* Status Badges */
        .badge { 
            padding: 6px 12px; 
            border-radius: 20px; 
            font-size: 0.8rem; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
        }
        
        .badge-pending { 
            background: #fff3cd; 
            color: #856404; 
        }
        
        .badge-confirmed { 
            background: #d1ecf1; 
            color: #0c5460; 
        }
        
        .badge-cancelled { 
            background: #f8d7da; 
            color: #721c24; 
        }
        
        /* Booking Detail Badges - NO ICONS */
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
        
        /* Forms */
        .form-container { 
            background: white; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
            margin-bottom: 30px; 
            max-width: 800px;
        }
        
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
            padding: 12px 16px; 
            border: 2px solid var(--border-color); 
            border-radius: 8px; 
            font-size: 1rem; 
            transition: all 0.3s ease;
        }
        
        .form-input:focus { 
            outline: none; 
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,109,109,0.1);
        }
        
        .form-button { 
            background: var(--primary); 
            color: white; 
            border: none; 
            padding: 12px 24px; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            transition: all 0.3s ease;
            font-size: 1rem;
            text-decoration: none;
        }
        
        .form-button:hover {
            background: #005a5a;
            transform: translateY(-2px);
        }
        
        /* Action Buttons - NO ICONS */
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .edit-btn, .cancel-btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .edit-btn {
            background: var(--success);
            color: white;
        }
        
        .edit-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .cancel-btn {
            background: var(--danger);
            color: white;
        }
        
        .cancel-btn:hover {
            background: #e0355c;
            transform: translateY(-2px);
        }
        
        /* Disabled Button Styles - NO TOOLTIPS */
        .edit-btn:disabled, .cancel-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
            transform: none;
        }
        
        .edit-btn:disabled:hover, .cancel-btn:disabled:hover {
            transform: none;
            background: var(--success);
        }
        
        .cancel-btn:disabled:hover {
            background: var(--danger);
        }
        
        /* Alerts */
        .alert { 
            padding: 16px 20px; 
            border-radius: 8px; 
            margin-bottom: 25px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success { 
            background: #d4edda; 
            color: #155724; 
            border-left: 4px solid #28a745; 
        }
        
        .alert-error { 
            background: #f8d7da; 
            color: #721c24; 
            border-left: 4px solid #dc3545; 
        }
        
        /* Policy Note */
        .policy-note { 
            background: #fff3cd; 
            padding: 16px 20px; 
            border-radius: 8px; 
            margin-bottom: 25px; 
            font-size: 0.95rem; 
            color: #856404;
            border-left: 4px solid #ffc107;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        /* Empty State */
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
        
        /* Notification Page */
        .notifications-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .notifications-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .notification-item-full {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: flex-start;
            gap: 15px;
            transition: all 0.3s ease;
        }
        
        .notification-item-full:hover {
            background: rgba(0,109,109,0.05);
        }
        
        .notification-date-group {
            background: #f8fafc;
            padding: 15px 25px;
            font-weight: 600;
            color: var(--primary);
            border-bottom: 1px solid var(--border-color);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: white;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            padding: 20px 30px;
            background: var(--primary);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.3rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close-modal {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .close-modal:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .modal-body {
            padding: 30px;
            overflow-y: auto;
            flex: 1;
        }
        
        /* Edit Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .container { 
                flex-direction: column; 
            }
            .sidebar { 
                width: 100%; 
                padding: 20px 0;
            }
            .nav-menu { 
                display: flex; 
                overflow-x: auto; 
                padding-bottom: 10px;
            }
            .nav-item { 
                margin-right: 10px; 
                flex-shrink: 0;
            }
            .main-content {
                max-width: 100%;
                padding: 20px;
            }
            .notification-dropdown {
                right: -100px;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid { 
                grid-template-columns: 1fr; 
            }
            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px 20px;
            }
            .user-info {
                width: 100%;
                justify-content: space-between;
            }
            .notification-dropdown {
                right: -150px;
                width: 300px;
            }
            .table-container {
                overflow-x: auto;
            }
            table {
                min-width: 900px;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .modal-body {
                padding: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            .edit-btn,
            .cancel-btn {
                width: 100%;
            }
            .notification-dropdown {
                right: -100px;
                width: 280px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">
            <i class="fas fa-plane"></i> TripNext
        </div>
        <div class="user-info">
            <!-- Notification Bell -->
            <div class="notification-bell" id="notificationBell">
                <i class="fas fa-bell bell-icon"></i>
                <span class="notification-badge" id="notificationBadge">0</span>
                
                <!-- Notification Dropdown -->
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h3><i class="fas fa-bell"></i> Notifications</h3>
                        <a href="?tab=notifications" class="view-all-link">View All</a>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="notification-empty">
                            <i class="fas fa-bell-slash"></i>
                            <p>No notifications yet</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="text-align: right;">
                <div style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($user['fullname']); ?></div>
                <div style="font-size: 0.9rem; color: var(--text-light);"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
            <div class="avatar"><?php echo strtoupper(substr($user['fullname'], 0, 1)); ?></div>
            <a href="index.php" class="logout-btn" style="background: var(--secondary);">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Main Container -->
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div style="color: var(--text-light); font-size: 0.95rem; margin-bottom: 5px;">Welcome back,</div>
                <div style="font-size: 1.4rem; font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($user['fullname']); ?></div>
                <div style="font-size: 0.9rem; color: var(--text-light); margin-top: 5px;">Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></div>
            </div>
            
            <ul class="nav-menu">
                <?php 
                $nav_tabs = [
                    'dashboard' => ['tachometer-alt', 'Dashboard'],
                    'bookings' => ['calendar-check', 'My Bookings'],
                    'notifications' => ['bell', 'Notifications'],
                    'profile' => ['user-cog', 'Profile Settings'],
                    'password' => ['key', 'Change Password']
                ];
                
                foreach ($nav_tabs as $tab => $nav_item): 
                    $has_notification_dot = ($tab == 'notifications' && mysqli_num_rows($all_notifications) > 0) ? true : false;
                ?>
                <li class="nav-item">
                    <a href="?tab=<?php echo $tab; ?>" class="nav-link <?php echo $active_tab == $tab ? 'active' : ''; ?>">
                        <i class="fas fa-<?php echo $nav_item[0]; ?>"></i>
                        <span><?php echo $nav_item[1]; ?></span>
                        <?php if ($has_notification_dot && $active_tab != 'notifications'): ?>
                            <span class="notification-dot" style="display: block;"></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Messages -->
            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <span><i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; ?></span>
                <button onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; color: inherit;">×</button>
            </div>
            <?php unset($_SESSION['success']); endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <span><i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; ?></span>
                <button onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; color: inherit;">×</button>
            </div>
            <?php unset($_SESSION['error']); endif; ?>
            
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-<?php 
                        echo $active_tab == 'dashboard' ? 'tachometer-alt' : 
                            ($active_tab == 'bookings' ? 'calendar-check' : 
                            ($active_tab == 'notifications' ? 'bell' :
                            ($active_tab == 'profile' ? 'user-cog' : 'key'))); 
                    ?>"></i>
                    <?php echo $titles[$active_tab][0]; ?>
                </h1>
                <p class="page-subtitle"><?php echo $titles[$active_tab][1]; ?></p>
            </div>
            
            <!-- Dashboard Tab -->
            <?php if ($active_tab == 'dashboard'): ?>
            <div class="stats-grid">
                <?php 
                $stats = [
                    ['Total Bookings', $total_bookings, 'fa-calendar-alt', 'var(--primary)'],
                    ['Confirmed', $confirmed, 'fa-check-circle', '#28a745'],
                    ['Pending', $pending, 'fa-clock', '#ffc107'],
                    ['Cancelled', $cancelled, 'fa-ban', 'var(--danger)'],
                    ['Total Spent', 'Rs ' . number_format($total_spent), 'fa-rupee-sign', '#9d4edd']
                ];
                foreach ($stats as $stat): ?>
                <div class="stat-card">
                    <div class="stat-icon" style="background: <?php echo $stat[3]; ?>">
                        <i class="fas <?php echo $stat[2]; ?>"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stat[1]; ?></div>
                        <div class="stat-label"><?php echo $stat[0]; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="table-container">
                <div class="table-header">
                    <h3 style="margin: 0; color: var(--text-dark);"><i class="fas fa-history"></i> Recent Bookings</h3>
                    <a href="?tab=bookings" class="form-button" style="padding: 10px 20px; text-decoration: none;">
                        <i class="fas fa-list"></i> View All
                    </a>
                </div>
                
                <?php 
                if ($total_bookings > 0): 
                    mysqli_data_seek($bookings_query, 0);
                    $recent_count = 0;
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Destination</th>
                            <th>Package</th>
                            <th>Hotel</th>
                            <th>Transport</th>
                            <th>Travel Dates</th>
                            <th>Travelers</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($b = mysqli_fetch_assoc($bookings_query) and $recent_count < 5): $recent_count++; 
                            $status = strtolower(trim($b['status']));
                            if (empty($status)) $status = 'pending';
                            
                            // Check if trip is completed (start date is in the past)
                            $start_date_timestamp = strtotime($b['start_date']);
                            $current_timestamp = time();
                            $is_trip_completed = $start_date_timestamp < $current_timestamp;
                            
                            // Determine if buttons should be enabled
                            $can_edit_cancel = ($status == 'pending' || $status == 'confirmed') && !$is_trip_completed;
                        ?>
                        <tr>
                            <td><strong>#<?php echo $b['id']; ?></strong></td>
                            <td>
                                <div style="font-weight: 600; color: var(--primary);"><?php echo htmlspecialchars($b['destination']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($b['package']); ?></td>
                            <td>
                                <?php if (!empty($b['hotel'])): ?>
                                    <span class="booking-detail-badge hotel-badge">
                                        <?php echo htmlspecialchars($b['hotel']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-light);">Not selected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($b['transport'])): ?>
                                    <span class="booking-detail-badge transport-badge">
                                        <?php echo htmlspecialchars($b['transport']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-light);">Not selected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date('d M', strtotime($b['start_date'])); ?> - <?php echo date('d M Y', strtotime($b['end_date'])); ?>
                                <?php if ($is_trip_completed): ?>
                                <div style="font-size: 0.8rem; color: var(--success);">Trip completed</div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $b['travelers']; ?> person(s)</td>
                            <td><strong style="color: var(--primary);">Rs <?php echo number_format($b['price']); ?></strong></td>
                            <td>
                                <span class="badge badge-<?php echo $status; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($status == 'cancelled'): ?>
                                        <span style="color: var(--danger); font-size: 0.85rem; padding: 8px 16px; background: #f8f9fa; border-radius: 6px;">
                                            Cancelled
                                        </span>
                                    <?php else: ?>
                                        <?php if ($can_edit_cancel): ?>
                                            <button type="button" onclick="editBooking(<?php echo $b['id']; ?>)" class="edit-btn">
                                                Edit
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                <button type="submit" name="cancel_booking" class="cancel-btn" 
                                                        onclick="return confirm('Are you sure you want to cancel booking #<?php echo $b['id']; ?>?')">
                                                    Cancel
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="edit-btn" disabled>
                                                Edit
                                            </button>
                                            <button type="button" class="cancel-btn" disabled>
                                                Cancel
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty">
                    <i class="fas fa-calendar-times empty-icon"></i>
                    <h3 style="color: var(--text-dark); margin-bottom: 10px;">No Bookings Yet</h3>
                    <p style="margin-bottom: 20px; font-size: 1.1rem;">Start planning your next adventure!</p>
                    <a href="index.php" class="form-button" style="text-decoration: none; display: inline-flex;">
                        <i class="fas fa-compass"></i> Explore Destinations
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Bookings Tab -->
            <?php if ($active_tab == 'bookings'): ?>
            <div class="policy-note">
                <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem;"></i>
                <div>
                    <strong>Booking Policies:</strong>
                    <ul style="margin: 8px 0 0 20px;">
                        <li>✅ You can edit or cancel <strong>PENDING</strong> and <strong>CONFIRMED</strong> bookings before trip starts</li>
                        <li>❌ <strong>CANCELLED</strong> bookings cannot be edited or cancelled</li>
                        <li>❌ <strong>COMPLETED</strong> trips cannot be edited or cancelled</li>
                        <li>💰 Changes to traveler count will recalculate the total price</li>
                    </ul>
                </div>
            </div>
            
            <div class="table-container">
                <?php 
                mysqli_data_seek($bookings_query, 0);
                $total = mysqli_num_rows($bookings_query);
                
                if ($total > 0): 
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Destination</th>
                            <th>Package</th>
                            <th>Hotel</th>
                            <th>Transport</th>
                            <th>Travel Dates</th>
                            <th>Travelers</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Booked On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($b = mysqli_fetch_assoc($bookings_query)): 
                            $status = strtolower(trim($b['status']));
                            if (empty($status)) $status = 'pending';
                            
                            // Check if trip is completed (start date is in the past)
                            $start_date_timestamp = strtotime($b['start_date']);
                            $current_timestamp = time();
                            $is_trip_completed = $start_date_timestamp < $current_timestamp;
                            
                            // Determine if buttons should be enabled
                            $can_edit_cancel = ($status == 'pending' || $status == 'confirmed') && !$is_trip_completed;
                        ?>
                        <tr>
                            <td><strong>#<?php echo $b['id']; ?></strong></td>
                            <td>
                                <div style="font-weight: 600; color: var(--primary);"><?php echo htmlspecialchars($b['destination']); ?></div>
                                <?php if (!empty($b['special_requests'])): ?>
                                <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 3px;">
                                    Has special requests
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($b['package']); ?></td>
                            <td>
                                <?php if (!empty($b['hotel'])): ?>
                                    <span class="booking-detail-badge hotel-badge">
                                        <?php echo htmlspecialchars($b['hotel']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-light);">Not selected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($b['transport'])): ?>
                                    <span class="booking-detail-badge transport-badge">
                                        <?php echo htmlspecialchars($b['transport']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-light);">Not selected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo date('d M', strtotime($b['start_date'])); ?> - <?php echo date('d M Y', strtotime($b['end_date'])); ?></div>
                                <?php if ($is_trip_completed): ?>
                                <div style="font-size: 0.8rem; color: var(--success);">Trip completed</div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $b['travelers']; ?> person(s)</td>
                            <td><strong style="color: var(--primary);">Rs <?php echo number_format($b['price']); ?></strong></td>
                            <td>
                                <span class="badge badge-<?php echo $status; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($b['booking_date'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($status == 'cancelled'): ?>
                                        <span style="color: var(--danger); font-size: 0.85rem; padding: 8px 16px; background: #f8f9fa; border-radius: 6px;">
                                            Cancelled
                                        </span>
                                    <?php else: ?>
                                        <?php if ($can_edit_cancel): ?>
                                            <button type="button" onclick="editBooking(<?php echo $b['id']; ?>)" class="edit-btn">
                                                Edit
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                <button type="submit" name="cancel_booking" class="cancel-btn" 
                                                        onclick="return confirm('Are you sure you want to cancel booking #<?php echo $b['id']; ?>?')">
                                                    Cancel
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="edit-btn" disabled>
                                                Edit
                                            </button>
                                            <button type="button" class="cancel-btn" disabled>
                                                Cancel
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty">
                    <i class="fas fa-calendar-times empty-icon"></i>
                    <h3 style="color: var(--text-dark); margin-bottom: 10px;">No Bookings Found</h3>
                    <p style="margin-bottom: 20px; font-size: 1.1rem;">You haven't made any bookings yet.</p>
                    <a href="index.php" class="form-button" style="text-decoration: none; display: inline-flex;">
                        <i class="fas fa-compass"></i> Explore Destinations
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Notifications Tab -->
            <?php if ($active_tab == 'notifications'): ?>
            <div class="notifications-container">
                <div class="table-header">
                    <h3 style="margin: 0; color: var(--text-dark);">
                        <i class="fas fa-bell"></i> All Notifications
                    </h3>
                    <span style="background: rgba(0,109,109,0.1); color: var(--primary); padding: 8px 16px; border-radius: 20px; font-weight: 600;">
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
                        $bg_color = '#006d6d';
                        
                        switch($notif['type']) {
                            case 'booking_confirmed':
                                $icon = 'fa-check-circle';
                                $bg_color = '#28a745';
                                break;
                            case 'booking_cancelled':
                                $icon = 'fa-times-circle';
                                $bg_color = '#dc3545';
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
                                        • <a href="?tab=bookings" style="color: var(--primary); text-decoration: none;">View Booking #<?php echo $notif['related_id']; ?></a>
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
                    <p style="color: var(--text-light);">When your bookings are confirmed or cancelled, you'll see notifications here.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Profile Tab -->
            <?php if ($active_tab == 'profile'): ?>
            <div class="form-container">
                <h3 style="color: var(--primary); margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-user-circle"></i> Personal Information
                </h3>
                
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="fullname" class="form-input" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="background: #f8f9fa;">
                            <div style="font-size: 0.85rem; color: var(--text-light); margin-top: 5px;">
                                Email cannot be changed
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                               placeholder="Enter your phone number">
                        <div style="font-size: 0.85rem; color: var(--text-light); margin-top: 5px;">
                            Used for booking confirmations and updates
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Account Created</label>
                        <input type="text" class="form-input" value="<?php echo date('F d, Y', strtotime($user['created_at'])); ?>" disabled style="background: #f8f9fa;">
                    </div>
                    
                    <button type="submit" name="update_profile" class="form-button">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Password Tab -->
            <?php if ($active_tab == 'password'): ?>
            <div class="form-container">
                <h3 style="color: var(--primary); margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-lock"></i> Change Password
                </h3>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Current Password *</label>
                        <input type="password" name="current_password" class="form-input" required>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">New Password *</label>
                            <input type="password" name="new_password" class="form-input" minlength="6" required>
                            <div style="font-size: 0.85rem; color: var(--text-light); margin-top: 5px;">
                                Minimum 6 characters
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirm New Password *</label>
                            <input type="password" name="confirm_password" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="policy-note" style="margin: 25px 0;">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <strong>Password Security Tips:</strong>
                            <ul style="margin: 8px 0 0 20px;">
                                <li>Use a combination of letters, numbers, and symbols</li>
                                <li>Avoid using personal information like birth dates</li>
                                <li>Don't reuse passwords from other websites</li>
                            </ul>
                        </div>
                    </div>
                    
                    <button type="submit" name="change_password" class="form-button">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Booking Edit Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-edit"></i>
                    <span id="modalTitle">Edit Booking</span>
                </h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Edit Form -->
                <div id="editForm">
                    <form id="editBookingForm" method="POST">
                        <input type="hidden" name="booking_id" id="editBookingId">
                        
                        <h4 style="margin: 0 0 25px 0; color: var(--primary);">
                            <i class="fas fa-edit"></i> Edit Booking Details
                        </h4>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="fullname" id="editFullname" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" name="phone" id="editPhone" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Travel Start Date *</label>
                                <input type="date" name="start_date" id="editStartDate" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Travel End Date *</label>
                                <input type="date" name="end_date" id="editEndDate" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Number of Travelers *</label>
                                <select name="travelers" id="editTravelers" class="form-input" required>
                                    <option value="1">1 person</option>
                                    <option value="2">2 persons</option>
                                    <option value="3">3 persons</option>
                                    <option value="4">4 persons</option>
                                    <option value="5">5 persons</option>
                                    <option value="6">6+ persons</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Package *</label>
                                <select name="package" id="editPackage" class="form-input" required>
                                    <option value="Basic Pilgrimage (Rs.21,480)">Basic Pilgrimage (Rs 21,480)</option>
                                    <option value="Standard Pilgrimage (Rs.32,480)">Standard Pilgrimage (Rs 32,480)</option>
                                    <option value="Premium Pilgrimage (Rs.45,480)">Premium Pilgrimage (Rs 45,480)</option>
                                    <option value="VIP Pilgrimage (Rs.65,480)">VIP Pilgrimage (Rs 65,480)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Hotel Category *</label>
                                <select name="hotel" id="editHotel" class="form-input" required>
                                    <option value="Basic Hotel">Basic Hotel</option>
                                    <option value="3 Star Hotel">3 Star Hotel</option>
                                    <option value="4 Star Hotel">4 Star Hotel</option>
                                    <option value="5 Star Hotel">5 Star Hotel</option>
                                    <option value="Luxury Resort">Luxury Resort</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Transport Type *</label>
                                <select name="transport" id="editTransport" class="form-input" required>
                                    <option value="Bus">Bus</option>
                                    <option value="Flight">Flight</option>
                                    <option value="Private Vehicle">Private Vehicle</option>
                                    <option value="Jeep">Jeep</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Special Requests & Notes</label>
                            <textarea name="special_requests" id="editRequests" class="form-input" rows="4" placeholder="Any special requirements, dietary restrictions, or additional requests..."></textarea>
                        </div>
                        
                        <div class="policy-note" style="margin: 25px 0;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <strong>Important:</strong> 
                                <ul style="margin: 8px 0 0 20px;">
                                    <li>Changing the number of travelers will recalculate the total price</li>
                                    <li>You can edit pending and confirmed bookings before trip start</li>
                                    <li>Completed trips cannot be edited</li>
                                    <li>Changes are subject to availability</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-top: 25px;">
                            <button type="submit" name="edit_booking" class="form-button">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" onclick="closeModal()" class="form-button" style="background: var(--text-light);">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Store booking data from PHP
        let bookingData = {
            <?php 
            mysqli_data_seek($bookings_query, 0);
            while ($booking = mysqli_fetch_assoc($bookings_query)): 
                $booking['fullname'] = $booking['full_name'];
            ?>
            <?php echo $booking['id']; ?>: <?php echo json_encode($booking); ?>,
            <?php endwhile; ?>
        };
        
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
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
        
        // Modal management
        let currentBookingData = null;
        
        function openModal() {
            document.getElementById('bookingModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.getElementById('bookingModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        function editBooking(bookingId) {
            if (bookingData[bookingId]) {
                const booking = bookingData[bookingId];
                currentBookingData = booking;
                populateEditForm(booking);
                openModal();
            } else {
                alert('Booking data not found. Please refresh the page.');
            }
        }
        
        function populateEditForm(booking) {
            document.getElementById('editBookingId').value = booking.id;
            document.getElementById('editFullname').value = booking.full_name || booking.fullname || '';
            document.getElementById('editPhone').value = booking.phone || '';
            
            const formatDateForInput = (dateString) => {
                const date = new Date(dateString);
                return date.toISOString().split('T')[0];
            };
            
            document.getElementById('editStartDate').value = formatDateForInput(booking.start_date);
            document.getElementById('editEndDate').value = formatDateForInput(booking.end_date);
            document.getElementById('editTravelers').value = booking.travelers || '1';
            document.getElementById('editPackage').value = booking.package || 'Basic Pilgrimage (Rs.21,480)';
            document.getElementById('editHotel').value = booking.hotel || 'Basic Hotel';
            document.getElementById('editTransport').value = booking.transport || 'Bus';
            document.getElementById('editRequests').value = booking.special_requests || '';
            
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('editStartDate').min = today;
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('bookingModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        document.getElementById('editBookingForm')?.addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('editStartDate').value);
            const endDate = new Date(document.getElementById('editEndDate').value);
            const today = new Date();
            
            today.setHours(0, 0, 0, 0);
            
            if (startDate < today) {
                e.preventDefault();
                alert('Start date cannot be in the past.');
                return false;
            }
            
            if (endDate <= startDate) {
                e.preventDefault();
                alert('End date must be after start date.');
                return false;
            }
            
            return true;
        });
        
        document.addEventListener('click', function(e) {
            if (e.target.closest('.alert')) {
                const closeBtn = e.target.closest('.alert button');
                if (closeBtn) {
                    closeBtn.closest('.alert').remove();
                }
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const editBookingId = urlParams.get('edit_id');
            
            if (editBookingId && bookingData[editBookingId]) {
                const booking = bookingData[editBookingId];
                currentBookingData = booking;
                populateEditForm(booking);
                openModal();
                
                const newUrl = window.location.pathname + '?tab=bookings';
                window.history.replaceState({}, '', newUrl);
            }
        });
    </script>
</body>
</html>