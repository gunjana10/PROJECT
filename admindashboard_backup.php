<?php
session_start();
include("db.php");
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'admin') header("Location: signin.php");

$page = $_GET['page'] ?? 'dashboard';
$search = trim($_GET['search'] ?? '');

// Handle actions
if (isset($_POST['action'])) {
    $id = mysqli_real_escape_string($conn, $_POST['booking_id'] ?? $_POST['user_id'] ?? '');
    $actions = [
        'confirm_booking' => "UPDATE bookings SET status='confirmed' WHERE id='$id'",
        'delete_booking' => "DELETE FROM bookings WHERE id='$id'",
        'toggle_user' => "UPDATE users SET status = IF(status='active','inactive','active') WHERE id='$id'",
        'delete_user' => "DELETE FROM users WHERE id='$id'"
    ];
    if (isset($actions[$_POST['action']])) {
        mysqli_query($conn, $actions[$_POST['action']]);
        $_SESSION['success'] = ["✅ Booking confirmed!","🗑️ Booking deleted!","🔄 User status updated!","🗑️ User deleted!"][array_search($_POST['action'], array_keys($actions))];
    }
    header("Location: admin-dashboard.php?page=$page" . ($search ? "&search=".urlencode($search) : ""));
    exit();
}

// Get stats
function getStat($conn, $query) { 
    $res = mysqli_fetch_assoc(mysqli_query($conn, $query)); 
    return $res ? reset($res) : 0; 
}
$stats = [
    'bookings' => getStat($conn, "SELECT COUNT(*) FROM bookings"),
    'pending' => getStat($conn, "SELECT COUNT(*) FROM bookings WHERE status='pending'"),
    'confirmed' => getStat($conn, "SELECT COUNT(*) FROM bookings WHERE status='confirmed'"),
    'revenue' => getStat($conn, "SELECT COALESCE(SUM(price),0) FROM bookings WHERE status='confirmed'"),
    'users' => getStat($conn, "SELECT COUNT(*) FROM users WHERE role='user'"),
    'active' => getStat($conn, "SELECT COUNT(*) FROM users WHERE role='user' AND status='active'")
];

// Get data for current page
$data = [];
if ($page == 'users') {
    $where = "WHERE role='user'";
    if ($search) $where .= " AND (fullname LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
    $data['users'] = mysqli_query($conn, "SELECT * FROM users $where ORDER BY created_at DESC");
} 
elseif ($page == 'bookings') $data['bookings'] = mysqli_query($conn, "SELECT * FROM bookings ORDER BY booking_date DESC");
elseif ($page == 'dashboard') $data['recent'] = mysqli_query($conn, "SELECT * FROM bookings ORDER BY booking_date DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex;
            min-height: 100vh;
        }
        
        /* ========== SIDEBAR ========== */
        .sidebar {
            width: 280px;
            background: rgba(30, 30, 45, 0.95);
            backdrop-filter: blur(10px);
            height: 100vh;
            position: fixed;
            padding: 30px 0;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 5px 0 25px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 0 25px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 25px;
        }
        
        .sidebar-header h2 {
            color: white;
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar-header h2 i {
            color: #00d2ff;
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
            box-shadow: 0 4px 10px rgba(106, 17, 203, 0.3);
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
            border-left: 3px solid transparent;
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.08);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, rgba(106, 17, 203, 0.2), rgba(37, 117, 252, 0.2));
            color: white;
            border-left: 3px solid #00d2ff;
            box-shadow: 0 4px 12px rgba(0, 210, 255, 0.15);
        }
        
        .nav-link i {
            width: 24px;
            font-size: 1.2rem;
            margin-right: 12px;
        }
        
        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }
        
        .top-header {
            background: white;
            padding: 25px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
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
        
        /* ========== STATS GRID ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }
        
        /* ========== SEARCH CONTAINER ========== */
        .search-container {
            background: white;
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
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
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(106, 17, 203, 0.1);
            background: white;
        }
        
        .search-btn {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 16px 30px;
            border-radius: 15px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }
        
        .search-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(106, 17, 203, 0.4);
        }
        
        /* ========== TABLES ========== */
        .table-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            margin-bottom: 40px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .table-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h3 {
            color: var(--text-dark);
            font-size: 1.3rem;
            font-weight: 700;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            padding: 20px 25px;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.95rem;
            background: #f8fafc;
        }
        
        td {
            padding: 18px 25px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.95rem;
        }
        
        tbody tr {
            transition: all 0.2s ease;
        }
        
        tbody tr:hover {
            background: #f8fafc;
        }
        
        /* ========== STATUS BADGES ========== */
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
        }
        
        .status-confirmed {
            background: linear-gradient(135deg, #d1ecf1, #a8e6cf);
            color: #0c5460;
        }
        
        .status-active {
            background: linear-gradient(135deg, #d4edda, #c5e1a5);
            color: #155724;
        }
        
        .status-inactive {
            background: linear-gradient(135deg, #f8d7da, #f5b7b1);
            color: #721c24;
        }
        
        /* ========== BUTTONS ========== */
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-confirm {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
            color: white;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        
        .btn-toggle {
            background: linear-gradient(135deg, #ffc107, #ffa726);
            color: #212529;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }
        
        .btn:hover {
            transform: translateY(-3px);
        }
        
        /* ========== ALERTS ========== */
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            padding: 20px 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            border-left: 5px solid #28a745;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.2);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: var(--text-light);
        }
        
        .empty-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-header h2 span,
            .admin-info div:last-child,
            .nav-link span {
                display: none;
            }
            
            .nav-link {
                justify-content: center;
                padding: 15px;
            }
            
            .nav-link i {
                margin-right: 0;
                font-size: 1.4rem;
            }
            
            .admin-info {
                justify-content: center;
                padding: 15px;
            }
            
            .main-content {
                margin-left: 80px;
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
                margin-bottom: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .top-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .search-box {
                flex-direction: column;
            }
            
            .search-input,
            .search-btn {
                width: 100%;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-crown"></i> <span>Admin Panel</span></h2>
            <p style="color: #a0aec0; font-size: 0.9rem;">Travel Management</p>
        </div>
        
        <div class="admin-info">
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['fullname'], 0, 1)); ?>
            </div>
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
                'users' => ['fas fa-users-cog', 'User Management']
            ];
            foreach($pages as $p => $icon): ?>
            <li class="nav-item">
                <a href="?page=<?php echo $p; ?>" class="nav-link <?php echo $page == $p ? 'active' : ''; ?>">
                    <i class="<?php echo $icon[0]; ?>"></i>
                    <span><?php echo $icon[1]; ?></span>
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

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-header">
            <div class="page-title">
                <div class="page-icon">
                    <i class="fas fa-<?php 
                        echo $page == 'dashboard' ? 'tachometer-alt' : 
                              ($page == 'bookings' ? 'calendar-check' : 'users-cog'); 
                    ?>"></i>
                </div>
                <div>
                    <h1>
                        <?php 
                        $titles = [
                            'dashboard' => 'Dashboard',
                            'bookings' => 'Booking Management',
                            'users' => 'User Management'
                        ];
                        echo $titles[$page] ?? 'Admin Panel';
                        ?>
                    </h1>
                    <p style="color: var(--text-light); margin-top: 5px;">
                        <?php 
                        if ($page == 'dashboard') echo 'System analytics and overview';
                        elseif ($page == 'bookings') echo 'Manage all travel bookings';
                        elseif ($page == 'users') echo 'Manage system users and permissions';
                        ?>
                    </p>
                </div>
            </div>
            
            <div style="display: flex; gap: 20px;">
                <div style="text-align: center; padding: 10px 20px; background: rgba(106, 17, 203, 0.05); border-radius: 10px;">
                    <div style="font-size: 1.4rem; font-weight: 700; color: var(--primary);"><?php echo $stats['bookings']; ?></div>
                    <div>Bookings</div>
                </div>
                <div style="text-align: center; padding: 10px 20px; background: rgba(106, 17, 203, 0.05); border-radius: 10px;">
                    <div style="font-size: 1.4rem; font-weight: 700; color: var(--primary);"><?php echo $stats['users']; ?></div>
                    <div>Users</div>
                </div>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert-success">
            <span><?php echo $_SESSION['success']; ?></span>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: #155724; font-size: 1.2rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php unset($_SESSION['success']); endif; ?>

        <?php if ($page == 'dashboard'): ?>
            <!-- Dashboard -->
            <div class="stats-grid">
                <?php 
                $statCards = [
                    ['Total Bookings', $stats['bookings'], 'fas fa-calendar-alt', 'var(--primary)'],
                    ['Pending Bookings', $stats['pending'], 'fas fa-clock', 'var(--warning)'],
                    ['Confirmed Bookings', $stats['confirmed'], 'fas fa-check-circle', 'var(--success)'],
                    ['Total Revenue', '₹' . number_format($stats['revenue']), 'fas fa-rupee-sign', '#9d4edd'],
                    ['Total Users', $stats['users'], 'fas fa-user-friends', '#ff6d00'],
                    ['Active Users', $stats['active'], 'fas fa-user-check', '#06d6a0']
                ];
                foreach ($statCards as $card): ?>
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <h3 style="color: var(--text-light); font-size: 0.95rem; margin-bottom: 8px;"><?php echo $card[0]; ?></h3>
                            <div class="stat-value"><?php echo $card[1]; ?></div>
                        </div>
                        <div class="stat-icon" style="background: <?php echo $card[3]; ?>">
                            <i class="<?php echo $card[2]; ?>"></i>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Recent Bookings -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Recent Bookings</h3>
                </div>
                
                <?php if (mysqli_num_rows($data['recent']) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Destination</th>
                            <th>Date</th>
                            <th>Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($data['recent'])): ?>
                        <tr>
                            <td>#<?php echo $row['id']; ?></td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-light);"><?php echo $row['email']; ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($row['destination']); ?></td>
                            <td><?php echo date('d M Y', strtotime($row['start_date'])); ?></td>
                            <td><strong style="color: var(--primary);">₹<?php echo number_format($row['price']); ?></strong></td>
                            <td>
                                <span class="status-badge status-<?php echo $row['status']; ?>">
                                    <i class="fas fa-circle" style="font-size: 0.6rem;"></i>
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times empty-icon"></i>
                    <h3>No Bookings Yet</h3>
                    <p>When customers make bookings, they will appear here.</p>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif ($page == 'bookings'): ?>
            <!-- Bookings Management -->
            <div class="table-container">
                <div class="table-header">
                    <h3>All Bookings</h3>
                    <div style="background: rgba(106, 17, 203, 0.1); color: var(--primary); padding: 8px 16px; border-radius: 20px; font-weight: 600;">
                        <?php echo $stats['bookings']; ?> bookings
                    </div>
                </div>
                
                <?php if (isset($data['bookings']) && mysqli_num_rows($data['bookings']) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Destination</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Booked On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($data['bookings'])): ?>
                        <tr>
                            <td>#<?php echo $row['id']; ?></td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-light);">
                                    <?php echo $row['email']; ?><br>
                                    <?php echo $row['phone']; ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['destination']); ?></td>
                            <td><strong style="color: var(--primary);">₹<?php echo number_format($row['price']); ?></strong></td>
                            <td>
                                <span class="status-badge status-<?php echo $row['status']; ?>">
                                    <i class="fas fa-circle" style="font-size: 0.6rem;"></i>
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($row['booking_date'])); ?></td>
                            <td>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <?php if ($row['status'] == 'pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="action" value="confirm_booking">
                                        <button type="submit" class="btn btn-confirm" onclick="return confirm('Confirm booking #<?php echo $row['id']; ?>?')">
                                            <i class="fas fa-check"></i> Confirm
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="action" value="delete_booking">
                                        <button type="submit" class="btn btn-delete" onclick="return confirm('Delete booking #<?php echo $row['id']; ?>?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times empty-icon"></i>
                    <h3>No Bookings Found</h3>
                    <p>There are no bookings in the system yet.</p>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif ($page == 'users'): ?>
            <!-- User Management -->
            <div class="search-container">
                <h3 style="color: var(--text-dark); margin-bottom: 15px;">
                    <i class="fas fa-search"></i> Search Users
                </h3>
                <form method="GET" class="search-box">
                    <input type="hidden" name="page" value="users">
                    <input type="text" name="search" class="search-input" 
                           placeholder="Search by name, email, or phone number..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search Users
                    </button>
                    <?php if (!empty($search)): ?>
                    <a href="?page=users" style="background: #f8f9fa; color: var(--text-light); border: 2px solid #e2e8f0; padding: 16px 25px; border-radius: 15px; text-decoration: none; font-weight: 500;">
                        <i class="fas fa-times"></i> Clear Search
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <div>
                        <h3>User Management</h3>
                        <p style="color: var(--text-light); margin-top: 5px; font-size: 0.95rem;">
                            Total: <?php echo $stats['users']; ?> users • 
                            Active: <?php echo $stats['active']; ?> • 
                            Inactive: <?php echo $stats['users'] - $stats['active']; ?>
                        </p>
                    </div>
                    <div style="background: rgba(106, 17, 203, 0.1); color: var(--primary); padding: 8px 16px; border-radius: 20px; font-weight: 600;">
                        <?php echo isset($data['users']) ? mysqli_num_rows($data['users']) : 0; ?> users
                    </div>
                </div>
                
                <?php if (isset($data['users']) && mysqli_num_rows($data['users']) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Contact Info</th>
                            <th>Status</th>
                            <th>Joined Date</th>
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
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($row['email']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-light);">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['phone'] ?? 'Not provided'); ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $row['status']; ?>">
                                    <i class="fas fa-circle" style="font-size: 0.6rem;"></i>
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="action" value="toggle_user">
                                        <button type="submit" class="btn btn-toggle" 
                                                onclick="return confirm('<?php echo ($row['status'] == 'active' ? 'Deactivate' : 'Activate'); ?> user #<?php echo $row['id']; ?>?')">
                                            <i class="fas fa-power-off"></i> 
                                            <?php echo $row['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <button type="submit" class="btn btn-delete" 
                                                onclick="return confirm('Delete user #<?php echo $row['id']; ?>?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash empty-icon"></i>
                    <h3>No Users Found</h3>
                    <p><?php echo !empty($search) ? 'No users match your search criteria.' : 'No users registered in the system yet.'; ?></p>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-hide success message
        setTimeout(() => {
            const successMsg = document.querySelector('.alert-success');
            if (successMsg) {
                successMsg.style.opacity = '0';
                setTimeout(() => successMsg.remove(), 300);
            }
        }, 5000);

        // Highlight search terms
        document.addEventListener('DOMContentLoaded', function() {
            const searchTerm = "<?php echo addslashes($search); ?>";
            if (searchTerm && <?php echo $page == 'users' ? 'true' : 'false'; ?>) {
                document.querySelectorAll('tbody td').forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(searchTerm.toLowerCase())) {
                        cell.style.backgroundColor = 'rgba(255, 235, 59, 0.2)';
                        cell.innerHTML = cell.innerHTML.replace(
                            new RegExp(`(${searchTerm})`, 'gi'),
                            '<mark style="background: #FFEB3B; padding: 2px 4px; border-radius: 4px; font-weight: bold;">$1</mark>'
                        );
                    }
                });
            }
        });
    </script>
</body>
</html>