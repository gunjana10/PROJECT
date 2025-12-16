<?php
session_start();
include("db.php");

// Check login
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'user') {
    header("Location: signin.php");
    exit();
}

$user_id = $_SESSION['id'];
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'"));

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['cancel_booking'])) {
        $booking_id = mysqli_real_escape_string($conn, $_POST['booking_id']);
        $check = mysqli_query($conn, "SELECT * FROM bookings WHERE id='$booking_id' AND email='{$user['email']}'");
        
        if (mysqli_num_rows($check) > 0) {
            $booking = mysqli_fetch_assoc($check);
            $hours_until = (strtotime($booking['start_date']) - time()) / 3600;
            
            if ($hours_until > 48 && in_array($booking['status'], ['pending', 'confirmed'])) {
                mysqli_query($conn, "UPDATE bookings SET status='cancel' WHERE id='$booking_id'");
                $_SESSION['success'] = "✅ Booking #$booking_id cancelled successfully!";
            } else {
                $_SESSION['error'] = $hours_until <= 48 ? "❌ Cannot cancel within 48 hours of trip start." : "❌ Cannot cancel this booking.";
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
            $hours_until = (strtotime($booking['start_date']) - time()) / 3600;
            
            // Only allow editing if more than 24 hours before trip
            if ($hours_until > 24 && in_array($booking['status'], ['pending', 'confirmed'])) {
                // Recalculate price if travelers count changes
                $new_price = $booking['price'];
                if ($travelers != $booking['travelers']) {
                    $base_price_per_person = 21480; // Example base price
                    $new_price = $base_price_per_person * $travelers;
                }
                
                // Update booking
                $update_query = "UPDATE bookings SET 
                    fullname='$fullname',
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
                    $_SESSION['success'] = "✅ Booking #$booking_id updated successfully!";
                } else {
                    $_SESSION['error'] = "❌ Error updating booking: " . mysqli_error($conn);
                }
            } else {
                $_SESSION['error'] = "❌ Cannot edit booking within 24 hours of trip start.";
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
$confirmed = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM bookings WHERE email='{$user['email']}' AND status='confirmed'"));
$pending = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM bookings WHERE email='{$user['email']}' AND status='pending'"));
$cancelled = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM bookings WHERE email='{$user['email']}' AND status='cancel'"));
$total_spent_result = mysqli_query($conn, "SELECT COALESCE(SUM(price),0) as total FROM bookings WHERE email='{$user['email']}' AND status='confirmed'");
$total_spent_row = mysqli_fetch_assoc($total_spent_result);
$total_spent = $total_spent_row['total'];

// Get all bookings for the user
$bookings_query = mysqli_query($conn, "SELECT * FROM bookings WHERE email='{$user['email']}' ORDER BY booking_date DESC");

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
$titles = [
    'dashboard' => ['Dashboard', 'Your travel summary and overview'],
    'bookings' => ['My Bookings', 'Manage and view all your bookings'],
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
            border-left: 4px solid transparent;
        }
        
        .nav-link:hover { 
            background: rgba(0,109,109,0.05); 
            transform: translateX(5px);
        }
        
        .nav-link.active { 
            background: rgba(0,109,109,0.1); 
            color: var(--primary); 
            font-weight: 600;
            border-left: 4px solid var(--primary);
        }
        
        .nav-link i { 
            width: 24px; 
            margin-right: 12px; 
            font-size: 1.1rem;
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
        
        /* Stats Cards */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
            gap: 20px; 
            margin-bottom: 40px; 
        }
        
        .stat-card { 
            background: white; 
            padding: 25px; 
            border-radius: 12px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
            display: flex; 
            align-items: center; 
            gap: 20px; 
            transition: all 0.3s ease;
            border-top: 4px solid var(--primary);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .stat-icon { 
            width: 60px; 
            height: 60px; 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.5rem; 
            color: white; 
        }
        
        .stat-value { 
            font-size: 2rem; 
            font-weight: 700; 
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.9rem;
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
            padding: 8px 16px; 
            border-radius: 20px; 
            font-size: 0.85rem; 
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
        
        .badge-cancel { 
            background: #f8d7da; 
            color: #721c24; 
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
            padding: 14px 16px; 
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
            padding: 14px 28px; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .form-button:hover {
            background: #005a5a;
            transform: translateY(-2px);
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .view-btn, .edit-btn, .cancel-btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .view-btn {
            background: var(--primary);
            color: white;
        }
        
        .view-btn:hover {
            background: #005a5a;
            transform: translateY(-2px);
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
            padding: 25px 30px;
            background: var(--primary);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.5rem;
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
        
        /* Details Grid */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .detail-item {
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .detail-label {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-bottom: 8px;
            display: block;
            font-weight: 600;
        }
        
        .detail-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
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
            .table-container {
                overflow-x: auto;
            }
            table {
                min-width: 800px;
            }
            .detail-grid,
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
            .view-btn,
            .edit-btn,
            .cancel-btn {
                justify-content: center;
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
            <div style="text-align: right;">
                <div style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($user['fullname']); ?></div>
                <div style="font-size: 0.9rem; color: var(--text-light);"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
            <div class="avatar"><?php echo strtoupper(substr($user['fullname'], 0, 1)); ?></div>
            <a href="index.html" class="logout-btn" style="background: var(--secondary);">
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
                <?php foreach ($titles as $tab => $title): ?>
                <li class="nav-item">
                    <a href="?tab=<?php echo $tab; ?>" class="nav-link <?php echo $active_tab == $tab ? 'active' : ''; ?>">
                        <i class="fas fa-<?php 
                            echo $tab == 'dashboard' ? 'tachometer-alt' : 
                                ($tab == 'bookings' ? 'calendar-check' : 
                                ($tab == 'profile' ? 'user-cog' : 'key')); 
                        ?>"></i>
                        <span><?php echo $title[0]; ?></span>
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
                            ($active_tab == 'profile' ? 'user-cog' : 'key')); 
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
                    ['Total Bookings', $total_bookings, 'fa-calendar-alt', 'var(--primary)', 'All your bookings'],
                    ['Confirmed', $confirmed, 'fa-check-circle', '#28a745', 'Approved bookings'],
                    ['Pending', $pending, 'fa-clock', '#ffc107', 'Awaiting confirmation'],
                    ['Cancelled', $cancelled, 'fa-ban', 'var(--danger)', 'Cancelled trips'],
                    ['Total Spent', '₹' . number_format($total_spent), 'fa-rupee-sign', '#9d4edd', 'Total amount spent']
                ];
                foreach ($stats as $stat): ?>
                <div class="stat-card">
                    <div class="stat-icon" style="background: <?php echo $stat[3]; ?>">
                        <i class="fas <?php echo $stat[2]; ?>"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stat[1]; ?></div>
                        <div class="stat-label"><?php echo $stat[0]; ?></div>
                        <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> <?php echo $stat[4]; ?>
                        </div>
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
                            <th>Travel Dates</th>
                            <th>Travelers</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($b = mysqli_fetch_assoc($bookings_query) and $recent_count < 5): $recent_count++; ?>
                        <tr>
                            <td><strong>#<?php echo $b['id']; ?></strong></td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($b['destination']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-light);">
                                    <?php echo htmlspecialchars($b['package']); ?>
                                </div>
                            </td>
                            <td>
                                <?php echo date('d M', strtotime($b['start_date'])); ?> - <?php echo date('d M Y', strtotime($b['end_date'])); ?>
                                <?php 
                                $hours_until = (strtotime($b['start_date']) - time()) / 3600;
                                if ($hours_until < 0): ?>
                                <div style="font-size: 0.8rem; color: var(--success);"><i class="fas fa-check-circle"></i> Completed</div>
                                <?php elseif ($hours_until < 48 && $hours_until > 0): ?>
                                <div style="font-size: 0.8rem; color: var(--warning);"><i class="fas fa-exclamation-circle"></i> Starts soon</div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $b['travelers']; ?> person(s)</td>
                            <td><strong style="color: var(--primary);">₹<?php echo number_format($b['price']); ?></strong></td>
                            <td>
                                <span class="badge badge-<?php echo $b['status']; ?>">
                                    <i class="fas fa-circle" style="font-size: 0.6rem;"></i> 
                                    <?php echo ucfirst($b['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" onclick="viewBookingDetails(<?php echo $b['id']; ?>)" class="view-btn">
                                        <i class="fas fa-eye"></i> View
                                    </button>
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
                    <a href="index.html" class="form-button" style="text-decoration: none; display: inline-flex;">
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
                    <strong>Important Policies:</strong>
                    <ul style="margin: 8px 0 0 20px;">
                        <li>Cancellation allowed up to 48 hours before trip start for full refund</li>
                        <li>Booking editing allowed up to 24 hours before trip start</li>
                        <li>Changes to traveler count may affect the total price</li>
                    </ul>
                </div>
            </div>
            
            <div class="table-container">
                <?php 
                // Reset query pointer
                mysqli_data_seek($bookings_query, 0);
                $total = mysqli_num_rows($bookings_query);
                
                if ($total > 0): 
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Destination</th>
                            <th>Travel Dates</th>
                            <th>Package</th>
                            <th>Travelers</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Booked On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($b = mysqli_fetch_assoc($bookings_query)): 
                            $hours_until = (strtotime($b['start_date']) - time()) / 3600;
                            $can_cancel = in_array($b['status'], ['pending', 'confirmed']) && $hours_until > 48;
                            $can_edit = in_array($b['status'], ['pending', 'confirmed']) && $hours_until > 24;
                        ?>
                        <tr>
                            <td><strong>#<?php echo $b['id']; ?></strong></td>
                            <td>
                                <div style="font-weight: 600; color: var(--primary);"><?php echo htmlspecialchars($b['destination']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-light);">
                                    <i class="fas fa-hotel"></i> <?php echo htmlspecialchars($b['hotel']); ?> • 
                                    <i class="fas fa-bus"></i> <?php echo htmlspecialchars($b['transport']); ?>
                                </div>
                                <?php if (!empty($b['special_requests'])): ?>
                                <div style="font-size: 0.85rem; color: var(--text-light); margin-top: 3px;">
                                    <i class="fas fa-sticky-note"></i> Has special requests
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo date('d M', strtotime($b['start_date'])); ?> - <?php echo date('d M Y', strtotime($b['end_date'])); ?></div>
                                <?php if ($hours_until < 0): ?>
                                <div style="font-size: 0.8rem; color: var(--success);"><i class="fas fa-check-circle"></i> Trip completed</div>
                                <?php elseif ($hours_until < 48 && $hours_until > 0): ?>
                                <div style="font-size: 0.8rem; color: var(--warning);"><i class="fas fa-exclamation-circle"></i> Starts in <?php echo floor($hours_until); ?> hours</div>
                                <?php elseif ($hours_until > 0): ?>
                                <div style="font-size: 0.8rem; color: var(--text-light);"><i class="fas fa-clock"></i> Starts in <?php echo floor($hours_until/24); ?> days</div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($b['package']); ?></td>
                            <td><?php echo $b['travelers']; ?> person(s)</td>
                            <td><strong style="color: var(--primary);">₹<?php echo number_format($b['price']); ?></strong></td>
                            <td>
                                <span class="badge badge-<?php echo $b['status']; ?>">
                                    <i class="fas fa-circle" style="font-size: 0.6rem;"></i> 
                                    <?php echo ucfirst($b['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($b['booking_date'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" onclick="viewBookingDetails(<?php echo $b['id']; ?>)" class="view-btn">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    
                                    <?php if ($can_edit): ?>
                                    <button type="button" onclick="editBooking(<?php echo $b['id']; ?>)" class="edit-btn">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($can_cancel): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                        <button type="submit" name="cancel_booking" class="cancel-btn" 
                                                onclick="return confirm('Are you sure you want to cancel booking #<?php echo $b['id']; ?>?')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                    <?php elseif ($b['status'] == 'cancel'): ?>
                                    <span style="color: var(--danger); font-size: 0.9rem; padding: 8px 16px; background: #f8f9fa; border-radius: 6px;">
                                        <i class="fas fa-ban"></i> Cancelled
                                    </span>
                                    <?php elseif ($hours_until <= 48 && $hours_until > 0): ?>
                                    <span style="color: var(--text-light); font-size: 0.9rem; padding: 8px 16px; background: #f8f9fa; border-radius: 6px;">
                                        <i class="fas fa-info-circle"></i> Non-refundable
                                    </span>
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
                    <a href="index.html" class="form-button" style="text-decoration: none; display: inline-flex;">
                        <i class="fas fa-compass"></i> Explore Destinations
                    </a>
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
                                <i class="fas fa-info-circle"></i> Email cannot be changed
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                               placeholder="Enter your phone number">
                        <div style="font-size: 0.85rem; color: var(--text-light); margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> Used for booking confirmations and updates
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
                                <i class="fas fa-info-circle"></i> Minimum 6 characters
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
    
    <!-- Booking Details/Edit Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-calendar-check"></i>
                    <span id="modalTitle">Booking Details</span>
                </h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Details View -->
                <div id="detailsView">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Booking ID</span>
                            <span class="detail-value" id="detailId">#123</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status</span>
                            <span class="detail-value">
                                <span id="detailStatus" class="badge badge-confirmed">Confirmed</span>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Booked On</span>
                            <span class="detail-value" id="detailBookedDate">12 Dec 2025</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Total Amount</span>
                            <span class="detail-value" id="detailPrice">₹21,480</span>
                        </div>
                    </div>
                    
                    <h4 style="margin: 30px 0 20px 0; color: var(--primary); padding-bottom: 10px; border-bottom: 2px solid var(--border-color);">
                        <i class="fas fa-info-circle"></i> Booking Information
                    </h4>
                    
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Full Name</span>
                            <span class="detail-value" id="detailFullname">John Doe</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email Address</span>
                            <span class="detail-value" id="detailEmail">john@example.com</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Phone Number</span>
                            <span class="detail-value" id="detailPhone">+977 98XXXXXXXX</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Destination</span>
                            <span class="detail-value" id="detailDestination">Kedarnath Yatra</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Travel Start Date</span>
                            <span class="detail-value" id="detailStartDate">25 Dec 2025</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Travel End Date</span>
                            <span class="detail-value" id="detailEndDate">30 Dec 2025</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Number of Travelers</span>
                            <span class="detail-value" id="detailTravelers">2 persons</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Package Selected</span>
                            <span class="detail-value" id="detailPackage">Basic Pilgrimage (₹21,480)</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Hotel Category</span>
                            <span class="detail-value" id="detailHotel">Basic Hotel</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Transport Type</span>
                            <span class="detail-value" id="detailTransport">Bus</span>
                        </div>
                    </div>
                    
                    <div class="detail-item" style="margin-top: 20px;">
                        <span class="detail-label">Special Requests & Notes</span>
                        <span class="detail-value" id="detailRequests" style="font-weight: normal; white-space: pre-wrap;">None</span>
                    </div>
                    
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--border-color); display: flex; gap: 15px;">
                        <button type="button" onclick="switchToEdit()" class="form-button" id="editButton" style="background: var(--success);">
                            <i class="fas fa-edit"></i> Edit Booking
                        </button>
                        <button type="button" onclick="closeModal()" class="form-button" style="background: var(--text-light);">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </div>
                
                <!-- Edit Form -->
                <div id="editForm" style="display: none;">
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
                                    <option value="Basic Pilgrimage (Rs.21,480)">Basic Pilgrimage (₹21,480)</option>
                                    <option value="Standard Pilgrimage (Rs.32,480)">Standard Pilgrimage (₹32,480)</option>
                                    <option value="Premium Pilgrimage (Rs.45,480)">Premium Pilgrimage (₹45,480)</option>
                                    <option value="VIP Pilgrimage (Rs.65,480)">VIP Pilgrimage (₹65,480)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Hotel Category *</label>
                                <select name="hotel" id="editHotel" class="form-input" required>
                                    <option value="Basic Hotel">Basic Hotel</option>
                                    <option value="3 Star Hotel">3 Star Hotel</option>
                                    <option value="4 Star Hotel">4 Star Hotel</option>
                                    <option value="5 Star Hotel">5 Star Hotel</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Transport Type *</label>
                                <select name="transport" id="editTransport" class="form-input" required>
                                    <option value="Bus">Bus</option>
                                    <option value="Flight">Flight</option>
                                    <option value="Train">Train</option>
                                    <option value="Private Car">Private Car</option>
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
                                    <li>Booking can only be edited more than 24 hours before trip start</li>
                                    <li>Changes are subject to availability</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-top: 25px;">
                            <button type="submit" name="edit_booking" class="form-button">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" onclick="switchToView()" class="form-button" style="background: var(--text-light);">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
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
            switchToView();
        }
        
        function switchToEdit() {
            document.getElementById('detailsView').style.display = 'none';
            document.getElementById('editForm').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Edit Booking';
            
            if (currentBookingData) {
                populateEditForm(currentBookingData);
            }
        }
        
        function switchToView() {
            document.getElementById('editForm').style.display = 'none';
            document.getElementById('detailsView').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Booking Details';
        }
        
        // View booking details
        function viewBookingDetails(bookingId) {
            // Create a simple AJAX request to get booking details
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'get-booking-details.php?id=' + bookingId, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            currentBookingData = data.booking;
                            populateDetailsView(data.booking);
                            updateEditButtonVisibility(data.booking);
                            openModal();
                        } else {
                            alert('Error: ' + (data.message || 'Failed to load booking details'));
                        }
                    } catch (e) {
                        console.error('Error parsing JSON:', e);
                        alert('Error loading booking details');
                    }
                } else {
                    alert('Error loading booking details. Please try again.');
                }
            };
            xhr.onerror = function() {
                alert('Network error. Please check your connection.');
            };
            xhr.send();
        }
        
        // Direct edit booking
        function editBooking(bookingId) {
            viewBookingDetails(bookingId);
            setTimeout(switchToEdit, 300);
        }
        
        // Populate details view
        function populateDetailsView(booking) {
            // Format dates
            const formatDate = (dateString) => {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                });
            };
            
            // Set values
            document.getElementById('detailId').textContent = `#${booking.id}`;
            document.getElementById('detailStatus').textContent = booking.status;
            document.getElementById('detailStatus').className = `badge badge-${booking.status}`;
            document.getElementById('detailBookedDate').textContent = formatDate(booking.booking_date);
            document.getElementById('detailPrice').textContent = `₹${parseInt(booking.price).toLocaleString('en-IN')}`;
            document.getElementById('detailFullname').textContent = booking.fullname || '<?php echo $user["fullname"]; ?>';
            document.getElementById('detailEmail').textContent = booking.email;
            document.getElementById('detailPhone').textContent = booking.phone || 'Not provided';
            document.getElementById('detailDestination').textContent = booking.destination;
            document.getElementById('detailStartDate').textContent = formatDate(booking.start_date);
            document.getElementById('detailEndDate').textContent = formatDate(booking.end_date);
            document.getElementById('detailTravelers').textContent = `${booking.travelers} person(s)`;
            document.getElementById('detailPackage').textContent = booking.package;
            document.getElementById('detailHotel').textContent = booking.hotel || 'Basic Hotel';
            document.getElementById('detailTransport').textContent = booking.transport || 'Bus';
            document.getElementById('detailRequests').textContent = booking.special_requests || 'None';
        }
        
        // Populate edit form
        function populateEditForm(booking) {
            document.getElementById('editBookingId').value = booking.id;
            document.getElementById('editFullname').value = booking.fullname || '';
            document.getElementById('editPhone').value = booking.phone || '';
            document.getElementById('editStartDate').value = booking.start_date.split(' ')[0];
            document.getElementById('editEndDate').value = booking.end_date.split(' ')[0];
            document.getElementById('editTravelers').value = booking.travelers || '1';
            document.getElementById('editPackage').value = booking.package || 'Basic Pilgrimage (Rs.21,480)';
            document.getElementById('editHotel').value = booking.hotel || 'Basic Hotel';
            document.getElementById('editTransport').value = booking.transport || 'Bus';
            document.getElementById('editRequests').value = booking.special_requests || '';
            
            // Set min date for start date (today)
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('editStartDate').min = today;
        }
        
        // Update edit button visibility based on booking status and time
        function updateEditButtonVisibility(booking) {
            const editButton = document.getElementById('editButton');
            if (editButton) {
                const hoursUntil = (new Date(booking.start_date) - new Date()) / (1000 * 60 * 60);
                const canEdit = ['pending', 'confirmed'].includes(booking.status) && hoursUntil > 24;
                editButton.style.display = canEdit ? 'block' : 'none';
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('bookingModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Form validation for edit form
        document.getElementById('editBookingForm')?.addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('editStartDate').value);
            const endDate = new Date(document.getElementById('editEndDate').value);
            const today = new Date();
            
            // Clear time for comparison
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
            
            const hoursUntil = (startDate - new Date()) / (1000 * 60 * 60);
            if (hoursUntil <= 24) {
                e.preventDefault();
                alert('Cannot edit booking within 24 hours of trip start.');
                return false;
            }
            
            return true;
        });
        
        // Auto-hide alerts when clicked
        document.addEventListener('click', function(e) {
            if (e.target.closest('.alert')) {
                const closeBtn = e.target.closest('.alert button');
                if (closeBtn) {
                    closeBtn.closest('.alert').remove();
                }
            }
        });
    </script>
</body>
</html>