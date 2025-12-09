<?php
session_start();
include("db.php");

// Check if admin is logged in
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'admin') {
    header("Location: signin.php");
    exit();
}

// Fetch bookings from database
$bookings_query = "SELECT * FROM bookings ORDER BY created_at DESC";
$bookings_result = mysqli_query($conn, $bookings_query);

// Fetch statistics
$total_bookings = mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings")->fetch_assoc()['total'];
$pending_bookings = mysqli_query($conn, "SELECT COUNT(*) as pending FROM bookings WHERE status='pending'")->fetch_assoc()['pending'];
$confirmed_bookings = mysqli_query($conn, "SELECT COUNT(*) as confirmed FROM bookings WHERE status='confirmed'")->fetch_assoc()['confirmed'];

// Calculate total revenue
$revenue_result = mysqli_query($conn, "SELECT SUM(price) as total_revenue FROM bookings WHERE status='confirmed'");
$total_revenue = $revenue_result->fetch_assoc()['total_revenue'] ?? 0;

// Handle booking actions
if (isset($_POST['action'])) {
    $booking_id = $_POST['booking_id'];
    
    if ($_POST['action'] == 'confirm') {
        $update_query = "UPDATE bookings SET status='confirmed' WHERE id='$booking_id'";
        mysqli_query($conn, $update_query);
    } elseif ($_POST['action'] == 'delete') {
        $delete_query = "DELETE FROM bookings WHERE id='$booking_id'";
        mysqli_query($conn, $delete_query);
    }
    
    // Refresh page to show updated data
    header("Location: admin-dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TripNext</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
        }

        .admin-header {
            background: linear-gradient(to right, #004d4d, #007272);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .admin-header h1 {
            font-size: 1.5rem;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .admin-welcome {
            font-size: 0.9rem;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid white;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .logout-btn:hover {
            background: white;
            color: #004d4d;
        }

        .dashboard-container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-card h3 {
            color: #004d4d;
            margin-bottom: 10px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #004d4d;
        }

        .bookings-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
        }

        .section-title {
            color: #004d4d;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }

        .bookings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bookings-table th,
        .bookings-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .bookings-table th {
            background-color: #f8f9fa;
            color: #004d4d;
            font-weight: 600;
        }

        .bookings-table tr:hover {
            background-color: #f8f9fa;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .status-confirmed {
            background-color: #d1edff;
            color: #004085;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .no-bookings {
            text-align: center;
            color: #666;
            padding: 40px;
            font-style: italic;
        }

        .action-form {
            display: inline;
        }

        .action-btn {
            background: #004d4d;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            margin-right: 5px;
        }

        .action-btn:hover {
            background: #003c3c;
        }

        .delete-btn {
            background: #dc3545;
        }

        .delete-btn:hover {
            background: #c82333;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <h1>TripNext Admin Dashboard</h1>
        <div class="admin-info">
            <span class="admin-welcome">Welcome, <?php echo $_SESSION['fullname']; ?> (Admin)</span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="dashboard-container">
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                Booking updated successfully!
            </div>
        <?php endif; ?>

        <div class="stats-cards">
            <div class="stat-card">
                <h3>Total Bookings</h3>
                <div class="stat-number"><?php echo $total_bookings; ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending Bookings</h3>
                <div class="stat-number"><?php echo $pending_bookings; ?></div>
            </div>
            <div class="stat-card">
                <h3>Confirmed Bookings</h3>
                <div class="stat-number"><?php echo $confirmed_bookings; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="stat-number">RS.<?php echo number_format($total_revenue); ?></div>
            </div>
        </div>

        <div class="bookings-section">
            <h2 class="section-title">All Bookings</h2>
            <div class="bookings-table-container">
                <?php if (mysqli_num_rows($bookings_result) > 0): ?>
                    <table class="bookings-table">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Customer Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Destination</th>
                                <th>Dates</th>
                                <th>Travelers</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Booking Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = mysqli_fetch_assoc($bookings_result)): ?>
                                <tr>
                                    <td>#<?php echo str_pad($booking['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['email']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['destination']); ?></td>
                                    <td><?php echo $booking['start_date']; ?> - <?php echo $booking['end_date']; ?></td>
                                    <td><?php echo $booking['travelers']; ?></td>
                                    <td>RS.<?php echo number_format($booking['price']); ?></td>
                                    <td>
                                        <span class="status-<?php echo $booking['status']; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($booking['created_at'])); ?></td>
                                    <td>
                                        <?php if ($booking['status'] == 'pending'): ?>
                                            <form method="POST" class="action-form">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <input type="hidden" name="action" value="confirm">
                                                <button type="submit" class="action-btn" onclick="return confirm('Are you sure you want to confirm this booking?')">Confirm</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="action-form">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this booking?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-bookings">
                        No bookings found. Bookings will appear here when users make reservations.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>