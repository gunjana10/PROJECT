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
                mysqli_query($conn, "UPDATE bookings SET status='cancelled' WHERE id='$booking_id'");
                $_SESSION['success'] = "Booking #$booking_id cancelled!";
            } else {
                $_SESSION['error'] = $hours_until <= 48 ? "Cannot cancel within 48 hours." : "Cannot cancel.";
            }
        }
        header("Location: user-dashboard.php?tab=bookings");
        exit();
    }
    
    if (isset($_POST['update_profile'])) {
        $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        mysqli_query($conn, "UPDATE users SET fullname='$fullname', phone='$phone' WHERE id='$user_id'");
        $_SESSION['fullname'] = $fullname;
        $_SESSION['success'] = "Profile updated!";
        header("Location: user-dashboard.php");
        exit();
    }
    
    if (isset($_POST['change_password'])) {
        if ($_POST['current_password'] == $user['password']) {
            if ($_POST['new_password'] == $_POST['confirm_password']) {
                mysqli_query($conn, "UPDATE users SET password='{$_POST['new_password']}' WHERE id='$user_id'");
                $_SESSION['success'] = "Password changed!";
            } else {
                $_SESSION['error'] = "Passwords don't match!";
            }
        } else {
            $_SESSION['error'] = "Current password wrong!";
        }
        header("Location: user-dashboard.php?tab=password");
        exit();
    }
}

// Get stats
$bookings = mysqli_query($conn, "SELECT * FROM bookings WHERE email='{$user['email']}' ORDER BY booking_date DESC");
$total_bookings = mysqli_num_rows($bookings);
$confirmed = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM bookings WHERE email='{$user['email']}' AND status='confirmed'"));
$pending = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM bookings WHERE email='{$user['email']}' AND status='pending'"));
$cancelled = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM bookings WHERE email='{$user['email']}' AND status='cancelled'"));
$total_spent = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(price),0) as total FROM bookings WHERE email='{$user['email']}' AND status='confirmed'"))['total'];

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
$titles = [
    'dashboard' => ['Dashboard', 'Your travel summary'],
    'bookings' => ['My Bookings', 'Manage your bookings'],
    'profile' => ['Profile', 'Update your info'],
    'password' => ['Password', 'Change password']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #006d6d;
            --secondary: #00a8a8;
            --accent: #ff5a00;
            --danger: #ff416c;
            --text-light: #718096;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f5f7fa; min-height: 100vh; }
        
        /* Header */
        .header { background: white; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid var(--primary); }
        .logo { font-size: 1.8rem; font-weight: bold; color: var(--primary); display: flex; align-items: center; gap: 10px; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .avatar { width: 45px; height: 45px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .logout-btn { background: var(--accent); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        
        /* Layout */
        .container { display: flex; min-height: calc(100vh - 85px); }
        .sidebar { width: 250px; background: white; border-right: 1px solid #e2e8f0; padding: 25px 0; }
        .sidebar-header { padding: 0 20px 20px; border-bottom: 1px solid #e2e8f0; margin-bottom: 20px; }
        .nav-menu { list-style: none; padding: 0 15px; }
        .nav-link { display: flex; align-items: center; padding: 12px 15px; color: #333; text-decoration: none; border-radius: 8px; margin-bottom: 5px; }
        .nav-link:hover { background: rgba(0,109,109,0.05); }
        .nav-link.active { background: rgba(0,109,109,0.1); color: var(--primary); font-weight: 600; }
        .nav-link i { width: 20px; margin-right: 10px; }
        
        .main-content { flex: 1; padding: 25px; }
        .page-header { margin-bottom: 25px; }
        .page-title { font-size: 1.8rem; color: var(--primary); margin-bottom: 5px; }
        .page-subtitle { color: var(--text-light); }
        
        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; }
        .stat-value { font-size: 1.8rem; font-weight: 700; }
        
        /* Tables */
        .table-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .table-header { padding: 20px; border-bottom: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; }
        
        /* Status Badges */
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d1ecf1; color: #0c5460; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        
        /* Forms */
        .form-container { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-input { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem; }
        .form-input:focus { outline: none; border-color: var(--primary); }
        .form-button { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        
        /* Alerts */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        
        /* Cancel Button */
        .cancel-btn { background: var(--danger); color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 5px; }
        
        /* Policy Note */
        .policy-note { background: #fff3cd; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; color: #856404; }
        
        /* Empty State */
        .empty { text-align: center; padding: 40px; color: var(--text-light); }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; }
            .nav-menu { display: flex; overflow-x: auto; }
            .nav-item { margin-right: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .table-container { overflow-x: auto; }
            table { min-width: 700px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo"><i class="fas fa-plane"></i> TripNext</div>
        <div class="user-info">
            <div style="text-align: right;">
                <div style="font-weight: 600;"><?php echo htmlspecialchars($user['fullname']); ?></div>
                <div style="font-size: 0.85rem; color: var(--text-light);"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
            <div class="avatar"><?php echo strtoupper(substr($user['fullname'], 0, 1)); ?></div>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div style="color: var(--text-light); font-size: 0.9rem;">Welcome back,</div>
                <div style="font-size: 1.3rem; font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($user['fullname']); ?></div>
            </div>
            
            <ul class="nav-menu">
                <?php foreach ($titles as $tab => $title): ?>
                <li><a href="?tab=<?php echo $tab; ?>" class="nav-link <?php echo $active_tab == $tab ? 'active' : ''; ?>">
                    <i class="fas fa-<?php echo $tab == 'dashboard' ? 'tachometer-alt' : ($tab == 'bookings' ? 'calendar-check' : ($tab == 'profile' ? 'user-cog' : 'key')); ?>"></i>
                    <?php echo $title[0]; ?>
                </a></li>
                <?php endforeach; ?>
                <li><a href="index.html" class="nav-link"><i class="fas fa-home"></i> Back Home</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Messages -->
            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <span><?php echo $_SESSION['success']; ?></span>
                <button onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer;">×</button>
            </div>
            <?php unset($_SESSION['success']); endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <span><?php echo $_SESSION['error']; ?></span>
                <button onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer;">×</button>
            </div>
            <?php unset($_SESSION['error']); endif; ?>
            
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title"><?php echo $titles[$active_tab][0]; ?></h1>
                <p class="page-subtitle"><?php echo $titles[$active_tab][1]; ?></p>
            </div>
            
            <!-- Dashboard Tab -->
            <?php if ($active_tab == 'dashboard'): ?>
            <div class="stats-grid">
                <?php 
                $stats = [
                    ['Total Bookings', $total_bookings, 'fa-calendar-alt', 'var(--primary)'],
                    ['Confirmed', $confirmed, 'fa-check-circle', '#00b09b'],
                    ['Pending', $pending, 'fa-clock', '#ffa726'],
                    ['Cancelled', $cancelled, 'fa-ban', 'var(--danger)'],
                    ['Total Spent', '₹' . number_format($total_spent), 'fa-rupee-sign', '#9d4edd']
                ];
                foreach ($stats as $stat): ?>
                <div class="stat-card">
                    <div class="stat-icon" style="background: <?php echo $stat[3]; ?>">
                        <i class="fas <?php echo $stat[2]; ?>"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; color: var(--text-light);"><?php echo $stat[0]; ?></div>
                        <div class="stat-value"><?php echo $stat[1]; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="table-container">
                <div class="table-header">
                    <h3 style="margin: 0;">Recent Bookings</h3>
                    <a href="?tab=bookings" style="background: rgba(0,109,109,0.1); color: var(--primary); padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: 600;">View All</a>
                </div>
                
                <?php if ($total_bookings > 0): 
                    mysqli_data_seek($bookings, 0);
                    $recent = array_slice(mysqli_fetch_all($bookings, MYSQLI_ASSOC), 0, 5);
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>Destination</th><th>Dates</th><th>Price</th><th>Status</th><th>Booked</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $b): ?>
                        <tr>
                            <td>#<?php echo $b['id']; ?></td>
                            <td><?php echo htmlspecialchars($b['destination']); ?></td>
                            <td><?php echo date('d M', strtotime($b['start_date'])); ?> - <?php echo date('d M', strtotime($b['end_date'])); ?></td>
                            <td><strong>₹<?php echo number_format($b['price']); ?></strong></td>
                            <td><span class="badge badge-<?php echo $b['status']; ?>"><i class="fas fa-circle" style="font-size: 0.6rem;"></i> <?php echo ucfirst($b['status']); ?></span></td>
                            <td><?php echo date('d M', strtotime($b['booking_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; opacity: 0.3; margin-bottom: 15px;"></i>
                    <h3>No Bookings Yet</h3>
                    <p>Start planning your next adventure!</p>
                    <a href="index.html" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: var(--accent); color: white; border-radius: 8px; text-decoration: none;">Explore Destinations</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Bookings Tab -->
            <?php if ($active_tab == 'bookings'): ?>
            <div class="policy-note">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Cancellation Policy:</strong> Cancel up to 48 hours before trip for full refund.
            </div>
            
            <div class="table-container">
                <?php 
                $bookings_query = mysqli_query($conn, "SELECT * FROM bookings WHERE email='{$user['email']}' ORDER BY booking_date DESC");
                $total = mysqli_num_rows($bookings_query);
                
                if ($total > 0): 
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>Destination</th><th>Dates</th><th>Travelers</th><th>Price</th><th>Status</th><th>Booked</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($b = mysqli_fetch_assoc($bookings_query)): 
                            $hours = (strtotime($b['start_date']) - time()) / 3600;
                            $can_cancel = in_array($b['status'], ['pending', 'confirmed']) && $hours > 48;
                        ?>
                        <tr>
                            <td>#<?php echo $b['id']; ?></td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($b['destination']); ?></div>
                                <?php if (!empty($b['special_requests'])): ?>
                                <div style="font-size: 0.85rem; color: var(--text-light);"><i class="fas fa-sticky-note"></i> Special request</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date('d M', strtotime($b['start_date'])); ?> - <?php echo date('d M Y', strtotime($b['end_date'])); ?>
                                <?php if ($hours < 0): ?>
                                <div style="font-size: 0.8rem; color: #757575;"><i class="fas fa-check-circle"></i> Completed</div>
                                <?php elseif ($hours < 48): ?>
                                <div style="font-size: 0.8rem; color: #f44336;"><i class="fas fa-exclamation-circle"></i> Starts soon</div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $b['travelers']; ?> person(s)</td>
                            <td><strong>₹<?php echo number_format($b['price']); ?></strong></td>
                            <td><span class="badge badge-<?php echo $b['status']; ?>"><?php echo ucfirst($b['status']); ?></span></td>
                            <td><?php echo date('d M Y', strtotime($b['booking_date'])); ?></td>
                            <td>
                                <?php if ($can_cancel): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                    <button type="submit" name="cancel_booking" class="cancel-btn" 
                                            onclick="return confirm('Cancel booking #<?php echo $b['id']; ?>?')">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </form>
                                <?php elseif ($b['status'] == 'cancelled'): ?>
                                <span style="color: var(--danger); font-size: 0.9rem;"><i class="fas fa-ban"></i> Cancelled</span>
                                <?php elseif ($hours <= 48 && $hours > 0): ?>
                                <span style="color: var(--text-light); font-size: 0.9rem;"><i class="fas fa-info-circle"></i> Non-refundable</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; opacity: 0.3; margin-bottom: 15px;"></i>
                    <h3>No Bookings Found</h3>
                    <p>You haven't made any bookings yet.</p>
                    <a href="index.html" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: var(--accent); color: white; border-radius: 8px; text-decoration: none;">Explore Destinations</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Profile Tab -->
            <?php if ($active_tab == 'profile'): ?>
            <div class="form-container">
                <form method="POST">
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Full Name</label>
                        <input type="text" name="fullname" class="form-input" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email</label>
                        <input type="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        <div style="font-size: 0.85rem; color: var(--text-light); margin-top: 5px;">Email cannot be changed</div>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Phone</label>
                        <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
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
                <form method="POST">
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Current Password</label>
                        <input type="password" name="current_password" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">New Password</label>
                        <input type="password" name="new_password" class="form-input" minlength="6" required>
                        <div style="font-size: 0.85rem; color: var(--text-light); margin-top: 5px;">Min 6 characters</div>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-input" required>
                    </div>
                    
                    <button type="submit" name="change_password" class="form-button">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 4000);
    </script>
</body>
</html>