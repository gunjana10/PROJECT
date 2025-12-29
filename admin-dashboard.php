<?php
session_start();
include("db.php");
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'admin') header("Location: signin.php");

$page = $_GET['page'] ?? 'dashboard';
$search = trim($_GET['search'] ?? '');

// Handle actions
if (isset($_POST['action'])) {
    $id = mysqli_real_escape_string($conn, $_POST['booking_id'] ?? $_POST['user_id'] ?? $_POST['destination_id'] ?? '');
    $actions = [
        'confirm_booking' => "UPDATE bookings SET status='confirmed' WHERE id='$id'",
        'cancel_booking' => "UPDATE bookings SET status='cancelled' WHERE id='$id'",
        'delete_booking' => "DELETE FROM bookings WHERE id='$id'",
        'toggle_user' => "UPDATE users SET status = IF(status='active','inactive','active') WHERE id='$id'",
        'delete_user' => "DELETE FROM users WHERE id='$id'",
        'delete_destination' => "DELETE FROM destinations WHERE id='$id'"
    ];
    if (isset($actions[$_POST['action']])) {
        mysqli_query($conn, $actions[$_POST['action']]);
        $messages = ["✅ Booking confirmed!","⚠️ Booking cancelled!","🗑️ Booking deleted!","🔄 User status updated!","🗑️ User deleted!","🗑️ Destination deleted!"];
        $_SESSION['success'] = $messages[array_search($_POST['action'], array_keys($actions))];
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
    
    // NEW FIELDS for destination details
    $duration = mysqli_real_escape_string($conn, $_POST['duration'] ?? '5 Days');
    $difficulty = mysqli_real_escape_string($conn, $_POST['difficulty'] ?? 'Easy');
    $best_season = mysqli_real_escape_string($conn, $_POST['best_season'] ?? 'Year-Round');
    $highlights = mysqli_real_escape_string($conn, $_POST['highlights'] ?? '');
    
    // Package details
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
        $max_size = 5 * 1024 * 1024; // 5MB
        
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
                
                // If editing and has old image, delete it
                if (isset($_POST['edit_destination']) && isset($_POST['old_image']) && !empty($_POST['old_image']) && file_exists($_POST['old_image'])) {
                    unlink($_POST['old_image']);
                }
            } else {
                $_SESSION['error'] = "❌ Failed to upload image.";
                header("Location: admin-dashboard.php?page=destinations");
                exit();
            }
        } else {
            $_SESSION['error'] = "❌ Invalid image file. Only JPG, PNG, GIF, WebP up to 5MB allowed.";
            header("Location: admin-dashboard.php?page=destinations");
            exit();
        }
    } elseif (isset($_POST['old_image']) && !empty($_POST['old_image'])) {
        // Keep old image if no new image uploaded
        $image_url = $_POST['old_image'];
    } else {
        // Use image_url from form if no file upload
        $image_url = mysqli_real_escape_string($conn, $_POST['image_url'] ?? '');
    }
    
    if (isset($_POST['add_destination'])) {
        $query = "INSERT INTO destinations (name, description, price, image_url, page_link, duration, difficulty, best_season, highlights, package_details, itinerary, included_services, not_included, hotel_options, transport_options) 
                  VALUES ('$name', '$description', '$price', '$image_url', '$page_link', '$duration', '$difficulty', '$best_season', '$highlights', '$package_details', '$itinerary_json', '$included_services', '$not_included', '$hotel_options', '$transport_options')";
        $_SESSION['success'] = "✅ Destination added successfully!";
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
        $_SESSION['success'] = "✅ Destination updated successfully!";
    }
    
    if (!mysqli_query($conn, $query)) $_SESSION['error'] = "❌ Error: " . mysqli_error($conn);
    header("Location: admin-dashboard.php?page=destinations");
    exit();
}

// Get stats
function getStat($conn, $query) { 
    $res = mysqli_fetch_assoc(mysqli_query($conn, $query)); 
    return $res ? reset($res) : 0; 
}

// Fix: Handle empty/null status in stats
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
elseif ($page == 'dashboard') $data['recent'] = mysqli_query($conn, "SELECT * FROM bookings ORDER BY booking_date DESC LIMIT 5");

// Get destination for edit
$edit_destination = null;
if (isset($_GET['edit'])) {
    $edit_id = mysqli_real_escape_string($conn, $_GET['edit']);
    $result = mysqli_query($conn, "SELECT * FROM destinations WHERE id='$edit_id'");
    $edit_destination = mysqli_fetch_assoc($result);
    
    // Parse itinerary JSON if exists
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
            font-family: 'Segoe UI', sans-serif; 
        }
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); 
            display: flex; 
            min-height: 100vh; 
        }
        
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
        }
        .nav-link i { 
            width: 24px; 
            margin-right: 12px; 
        }
        
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
        }
        .stat-card:hover { 
            transform: translateY(-10px); 
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
        .search-btn { 
            background: linear-gradient(135deg, var(--primary), var(--secondary)); 
            color: white; 
            border: none; 
            padding: 16px 30px; 
            border-radius: 15px; 
            cursor: pointer; 
            font-weight: 600; 
            transition: all 0.3s ease; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .search-btn:hover { 
            transform: translateY(-3px); 
        }
        
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
        
        .btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 12px; 
            cursor: pointer; 
            font-weight: 600; 
            transition: all 0.3s ease; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            text-decoration: none; 
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
            padding: 6px 12px; 
            font-size: 0.85rem; 
        }
        
        .status-badge { 
            padding: 6px 12px; 
            border-radius: 20px; 
            font-size: 0.8rem; 
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
        
        .info-badge { 
            display: inline-block; 
            padding: 4px 10px; 
            border-radius: 12px; 
            font-size: 0.8rem; 
            font-weight: 600; 
            margin: 2px 0; 
        }
        .package-badge { 
            background: #e3f2fd; 
            color: #1565c0; 
        }
        .hotel-badge { 
            background: #f3e5f5; 
            color: #7b1fa2; 
        }
        .transport-badge { 
            background: #e8f5e8; 
            color: #2e7d32; 
        }
        .traveler-badge { 
            background: #fff3e0; 
            color: #ef6c00; 
        }
        
        .modal-overlay { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            z-index: 1001; 
            justify-content: center; 
            align-items: center; 
        }
        .modal-content { 
            background: white; 
            border-radius: 20px; 
            padding: 30px; 
            max-width: 600px; 
            width: 90%; 
            max-height: 80vh; 
            overflow-y: auto; 
        }
        
        /* Image upload styles */
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
        .image-info { 
            font-size: 0.85rem; 
            color: var(--text-light); 
            margin-top: 5px; 
        }
        
        .action-buttons { 
            display: flex; 
            gap: 8px; 
            flex-wrap: wrap; 
        }
        
        /* Form styles for destination management */
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
        .form-textarea:focus { 
            outline: none; 
            border-color: var(--primary); 
            box-shadow: 0 0 0 4px rgba(106, 17, 203, 0.1); 
        }
        .form-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
        }
        .form-help { 
            font-size: 0.85rem; 
            color: var(--text-light); 
            margin-top: 5px; 
        }
        
        /* Itinerary builder styles */
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
                min-width: 1200px; 
            }
            .form-grid {
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
                'destinations' => ['fas fa-map-marked-alt', 'Destinations']
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

    <div class="main-content">
        <div class="top-header">
            <div class="page-title">
                <div class="page-icon">
                    <i class="fas fa-<?php 
                        echo $page == 'dashboard' ? 'tachometer-alt' : 
                            ($page == 'bookings' ? 'calendar-check' : 
                            ($page == 'users' ? 'users-cog' : 'map-marked-alt')); 
                    ?>"></i>
                </div>
                <div>
                    <h1>
                        <?php 
                        echo [
                            'dashboard' => 'Dashboard',
                            'bookings' => 'Booking Management',
                            'users' => 'User Management',
                            'destinations' => 'Destination Management'
                        ][$page] ?? 'Admin Panel'; 
                        ?>
                    </h1>
                    <p style="color: var(--text-light); margin-top: 5px;">
                        <?php 
                        echo [
                            'dashboard' => 'System analytics and overview',
                            'bookings' => 'Manage all travel bookings',
                            'users' => 'Manage system users',
                            'destinations' => 'Add, edit, and remove destinations'
                        ][$page]; 
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
                    <div style="font-size: 1.4rem; font-weight: 700; color: var(--primary);"><?php echo $stats['destinations']; ?></div>
                    <div>Destinations</div>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert-success">
            <span><?php echo $_SESSION['success']; ?></span>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: #155724; font-size: 1.2rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php unset($_SESSION['success']); endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert-error">
            <span><?php echo $_SESSION['error']; ?></span>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: #721c24; font-size: 1.2rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php unset($_SESSION['error']); endif; ?>

        <?php if ($page == 'dashboard'): ?>
            <div class="stats-grid">
                <?php 
                $statCards = [
                    ['Total Bookings', $stats['bookings'], 'fas fa-calendar-alt', 'var(--primary)'],
                    ['Pending Bookings', $stats['pending'], 'fas fa-clock', 'var(--warning)'],
                    ['Confirmed Bookings', $stats['confirmed'], 'fas fa-check-circle', 'var(--success)'],
                    ['Cancelled Bookings', $stats['cancelled'], 'fas fa-times-circle', 'var(--danger)'],
                    ['Total Revenue', '₹' . number_format($stats['revenue']), 'fas fa-rupee-sign', '#9d4edd'],
                    ['Total Users', $stats['users'], 'fas fa-user-friends', '#ff6d00'],
                    ['Active Users', $stats['active'], 'fas fa-user-check', '#06d6a0'],
                    ['Destinations', $stats['destinations'], 'fas fa-map-marker-alt', '#118ab2']
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

            <div class="table-container">
                <div class="table-header"><h3>Recent Bookings</h3></div>
                <?php if (mysqli_num_rows($data['recent']) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Destination</th>
                            <th>Package</th>
                            <th>Hotel</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($data['recent'])): 
                            // Handle empty/null status
                            $row_status = !empty($row['status']) ? $row['status'] : 'pending';
                            if ($row_status == 'cancel') $row_status = 'cancelled';
                        ?>
                        <tr>
                            <td>#<?php echo $row['id']; ?></td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-light);">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($row['email']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['destination']); ?></td>
                            <td>
                                <span class="info-badge package-badge">
                                    <?php echo htmlspecialchars($row['package'] ?? 'Basic'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="info-badge hotel-badge">
                                    <?php echo htmlspecialchars($row['hotel_category'] ?? 'Basic'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $row_status; ?>">
                                    <i class="fas fa-circle" style="font-size: 0.6rem;"></i>
                                    <?php echo ucfirst($row_status); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-primary" onclick="viewBookingDetails(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    
                                    <!-- CONFIRM button shows for pending, empty, or cancelled status -->
                                    <?php if ($row_status == 'pending' || $row_status == 'cancelled' || empty($row['status'])): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="action" value="confirm_booking">
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Confirm booking #<?php echo $row['id']; ?>?')">
                                            <i class="fas fa-check"></i> Confirm
                                        </button>
                                    </form>
                                    <?php elseif ($row_status == 'confirmed'): ?>
                                    <!-- CANCEL button for confirmed bookings -->
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="action" value="cancel_booking">
                                        <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Cancel booking #<?php echo $row['id']; ?>?')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="text-align: center; padding: 60px 40px; color: var(--text-light);">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.3;"></i>
                    <h3>No Bookings Yet</h3>
                    <p>When customers make bookings, they will appear here.</p>
                </div>
                <?php endif; ?>
            </div>

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
                    <h3>All Bookings</h3>
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
                                // Handle empty/null status and 'cancel' typo
                                $row_status = !empty($row['status']) ? $row['status'] : 'pending';
                                if ($row_status == 'cancel') $row_status = 'cancelled';
                                $display_status = ucfirst($row_status);
                                if (empty($row['status'])) $display_status = 'Pending';
                            ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                    <div style="font-size: 0.85rem; color: var(--text-light);">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($row['email']); ?><br>
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['phone']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['destination']); ?></td>
                                <td>
                                    <span class="info-badge package-badge">
                                        <i class="fas fa-box"></i> <?php echo htmlspecialchars($row['package'] ?? 'Basic Pilgrimage'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="info-badge hotel-badge">
                                        <i class="fas fa-hotel"></i> <?php echo htmlspecialchars($row['hotel_category'] ?? 'Basic Hotel'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="info-badge transport-badge">
                                        <i class="fas fa-bus"></i> <?php echo htmlspecialchars($row['transport_type'] ?? 'Bus'); ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- FIXED: Changed from $row['num_travelers'] to $row['travelers'] -->
                                    <span class="info-badge traveler-badge">
                                        <i class="fas fa-users"></i> <?php echo htmlspecialchars($row['travelers'] ?? 1); ?> pax
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem;">
                                        <div><i class="fas fa-calendar-day"></i> <?php echo date('d M', strtotime($row['start_date'])); ?></div>
                                        <div><i class="fas fa-calendar-check"></i> <?php echo date('d M', strtotime($row['end_date'])); ?></div>
                                    </div>
                                </td>
                                <td><strong style="color: var(--primary);">₹<?php echo number_format($row['price']); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo $row_status; ?>">
                                        <i class="fas fa-circle" style="font-size: 0.6rem;"></i>
                                        <?php echo $display_status; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($row['booking_date'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- VIEW button -->
                                        <button class="btn btn-sm btn-primary" onclick="viewBookingDetails(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        
                                        <!-- CONFIRM button - shows for pending, empty, cancelled, or cancel status -->
                                        <?php if ($row_status == 'pending' || $row_status == 'cancelled' || empty($row['status'])): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="confirm_booking">
                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Confirm booking #<?php echo $row['id']; ?>?')">
                                                <i class="fas fa-check"></i> Confirm
                                            </button>
                                        </form>
                                        <?php elseif ($row_status == 'confirmed'): ?>
                                        <!-- CANCEL button for confirmed bookings -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="cancel_booking">
                                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Cancel booking #<?php echo $row['id']; ?>?')">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <!-- DELETE button - Always shows -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="delete_booking">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete booking #<?php echo $row['id']; ?>? This action cannot be undone.')">
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
                <div style="text-align: center; padding: 60px 40px; color: var(--text-light);">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.3;"></i>
                    <h3>No Bookings Found</h3>
                    <p><?php echo !empty($search) ? 'No bookings match your search.' : 'No bookings in system.'; ?></p>
                </div>
                <?php endif; ?>
            </div>

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
                    <h3>User Management</h3>
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
                            <th>Contact</th>
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
                                <div style="display: flex; gap: 10px;">
                                    <form method="POST">
                                        <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="action" value="toggle_user">
                                        <button type="submit" class="btn btn-warning" onclick="return confirm('<?php echo ($row['status'] == 'active' ? 'Deactivate' : 'Activate'); ?> user #<?php echo $row['id']; ?>?')">
                                            <i class="fas fa-power-off"></i> <?php echo $row['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Delete user #<?php echo $row['id']; ?>?')">
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
                <div style="text-align: center; padding: 60px 40px; color: var(--text-light);">
                    <i class="fas fa-user-slash" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.3;"></i>
                    <h3>No Users Found</h3>
                    <p><?php echo !empty($search) ? 'No users match your search.' : 'No users registered.'; ?></p>
                </div>
                <?php endif; ?>
            </div>

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
                            <div class="form-help">Name of the destination (e.g., Everest Base Camp)</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Price (₹) *</label>
                            <input type="text" name="price" class="form-input" value="<?php echo $edit_destination ? htmlspecialchars($edit_destination['price']) : ''; ?>" required placeholder="RS.45,000">
                            <div class="form-help">Price format: RS.21,480 or ₹45,000</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Short Description *</label>
                        <textarea name="description" class="form-textarea" required><?php echo $edit_destination ? htmlspecialchars($edit_destination['description']) : ''; ?></textarea>
                        <div class="form-help">Brief description shown on homepage (2-3 lines)</div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Duration</label>
                            <input type="text" name="duration" class="form-input" value="<?php echo $edit_destination ? htmlspecialchars($edit_destination['duration'] ?? '5 Days') : '5 Days'; ?>" placeholder="5 Days">
                            <div class="form-help">Trip duration (e.g., 5 Days, 7 Nights)</div>
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
                        <div class="form-help">This will be shown on the destination details page</div>
                    </div>
                    
                    <!-- Itinerary Builder Section -->
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
                        <div class="form-help">Create day-by-day itinerary for the destination page</div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Included Services</label>
                            <textarea name="included_services" class="form-textarea" placeholder="Services included in package (one per line)"><?php echo $edit_destination ? htmlspecialchars($edit_destination['included_services'] ?? '') : ''; ?></textarea>
                            <div class="form-help">One service per line</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Not Included</label>
                            <textarea name="not_included" class="form-textarea" placeholder="Services not included (one per line)"><?php echo $edit_destination ? htmlspecialchars($edit_destination['not_included'] ?? '') : ''; ?></textarea>
                            <div class="form-help">One item per line</div>
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
                            <div class="form-help">Link to detailed page (leave empty for auto-generated)</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Image URL</label>
                            <input type="text" name="image_url" class="form-input" value="<?php echo $edit_destination ? htmlspecialchars($edit_destination['image_url'] ?? '') : ''; ?>">
                            <div class="form-help">Or upload image below</div>
                        </div>
                    </div>
                    
                    <!-- Image Upload Section -->
                    <div class="form-group">
                        <label class="form-label">Upload Image</label>
                        <div class="image-upload-container">
                            <input type="file" id="imageInput" name="image" class="file-input" accept="image/*" onchange="previewImage(this)">
                            <label for="imageInput" class="upload-btn">
                                <i class="fas fa-cloud-upload-alt"></i> Choose Image
                            </label>
                            
                            <!-- Image preview -->
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
                            <div class="image-info">
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
                    <h3>All Destinations</h3>
                    <div style="background: rgba(106, 17, 203, 0.1); color: var(--primary); padding: 8px 16px; border-radius: 20px; font-weight: 600;">
                        <?php echo isset($data['destinations']) ? mysqli_num_rows($data['destinations']) : 0; ?> destinations
                    </div>
                </div>
                
                <?php if (isset($data['destinations']) && mysqli_num_rows($data['destinations']) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Destination</th>
                            <th>Description</th>
                            <th>Price</th>
                            <th>Duration</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($data['destinations'])): ?>
                        <tr>
                            <td>#<?php echo $row['id']; ?></td>
                            <td>
                                <?php if (!empty($row['image_url'])): ?>
                                    <?php if (filter_var($row['image_url'], FILTER_VALIDATE_URL)): ?>
                                        <img src="<?php echo htmlspecialchars($row['image_url']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" style="width: 80px; height: 60px; object-fit: cover; border-radius: 8px;">
                                    <?php else: ?>
                                        <img src="<?php echo htmlspecialchars($row['image_url']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" style="width: 80px; height: 60px; object-fit: cover; border-radius: 8px;" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 80 60%22><rect width=%2280%22 height=%2260%22 fill=%22%234d4d4d%22/><text x=%2240%22 y=%2230%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22white%22 font-size=%2210%22><?php echo urlencode(substr($row['name'], 0, 10)); ?></text></svg>'">
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div style="width: 80px; height: 60px; background: linear-gradient(135deg, #004d4d, #007272); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.9rem; font-weight: bold;">
                                        <?php echo substr($row['name'], 0, 3); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--primary);"><?php echo htmlspecialchars($row['name']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-light);">
                                    <?php echo !empty($row['page_link']) ? htmlspecialchars($row['page_link']) : 'dynamic-destination.php?id=' . $row['id']; ?>
                                </div>
                            </td>
                            <td>
                                <div style="max-width: 300px; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars(substr($row['description'], 0, 100)); ?>
                                    <?php echo strlen($row['description']) > 100 ? '...' : ''; ?>
                                </div>
                            </td>
                            <td><strong style="color: var(--primary);"><?php echo htmlspecialchars($row['price']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['duration'] ?? 'N/A'); ?></td>
                            <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <div style="display: flex; gap: 10px;">
                                    <a href="?page=destinations&edit=<?php echo $row['id']; ?>" class="btn btn-warning">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST">
                                        <input type="hidden" name="destination_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="action" value="delete_destination">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Delete destination \"<?php echo addslashes($row['name']); ?>\"?')">
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
                <div style="text-align: center; padding: 60px 40px; color: var(--text-light);">
                    <i class="fas fa-map-marked-alt" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.3;"></i>
                    <h3>No Destinations Found</h3>
                    <p><?php echo !empty($search) ? 'No destinations match your search.' : 'No destinations added yet.'; ?></p>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Booking Details Modal -->
    <div class="modal-overlay" id="bookingModal" onclick="if(event.target.id=='bookingModal')closeModal()">
        <div class="modal-content">
            <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="color: var(--primary);">Booking Details</h3>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; color: var(--text-light); cursor: pointer;">&times;</button>
            </div>
            <div id="bookingDetailsContent"></div>
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                <button class="btn btn-primary" onclick="printBookingDetails()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
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
        
        // Image preview functions
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
        
        function updateImagePreview(url) {
            const preview = document.getElementById('imagePreview');
            const placeholder = document.getElementById('previewPlaceholder');
            
            if (url && url.trim() !== '') {
                const img = document.getElementById('previewImage') || document.createElement('img');
                img.id = 'previewImage';
                img.src = url;
                img.style.maxWidth = '100%';
                img.style.maxHeight = '100%';
                img.style.objectFit = 'cover';
                img.onerror = function() {
                    preview.innerHTML = '<div id="previewPlaceholder" style="color: #999; text-align: center;"><i class="fas fa-image" style="font-size: 3rem; margin-bottom: 10px;"></i><br><span>Invalid image URL</span></div>';
                };
                if (placeholder) placeholder.style.display = 'none';
                preview.innerHTML = '';
                preview.appendChild(img);
            } else if (!document.getElementById('previewImage')) {
                preview.innerHTML = '<div id="previewPlaceholder" style="color: #999; text-align: center;"><i class="fas fa-image" style="font-size: 3rem; margin-bottom: 10px;"></i><br><span>No image selected</span></div>';
            }
        }
        
        // Form validation for destination form
        document.querySelector('form[enctype="multipart/form-data"]')?.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('imageInput');
            const imageUrl = document.querySelector('input[name="image_url"]').value;
            const itineraryDays = document.querySelectorAll('input[name="itinerary_day[]"]').length;
            
            // Check if itinerary has at least one day
            if (itineraryDays < 1) {
                e.preventDefault();
                alert('Please add at least one day to the itinerary.');
                return false;
            }
            
            // Check if at least one image source is provided
            if (!fileInput.files[0] && !imageUrl.trim()) {
                e.preventDefault();
                alert('Please either upload an image or provide an image URL.');
                return false;
            }
            
            // Validate file size if file is selected
            if (fileInput.files[0]) {
                const maxSize = 5 * 1024 * 1024; // 5MB
                if (fileInput.files[0].size > maxSize) {
                    e.preventDefault();
                    alert('Image file is too large. Maximum size is 5MB.');
                    return false;
                }
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(fileInput.files[0].type)) {
                    e.preventDefault();
                    alert('Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.');
                    return false;
                }
            }
            
            return true;
        });
        
        function viewBookingDetails(booking) {
            document.getElementById('bookingModal').style.display = 'flex';
            const content = document.getElementById('bookingDetailsContent');
            const startDate = new Date(booking.start_date).toLocaleDateString('en-US', {day:'numeric', month:'long', year:'numeric'});
            const endDate = new Date(booking.end_date).toLocaleDateString('en-US', {day:'numeric', month:'long', year:'numeric'});
            const bookedDate = new Date(booking.booking_date).toLocaleDateString('en-US', {day:'numeric', month:'long', year:'numeric'});
            
            content.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div>
                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;">Booking ID</div>
                        <div>#${booking.id}</div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;">Status</div>
                        <div>
                            <span class="status-badge status-${booking.status || 'pending'}">
                                ● ${booking.status ? booking.status.charAt(0).toUpperCase() + booking.status.slice(1) : 'Pending'}
                            </span>
                        </div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;">Customer Name</div>
                        <div>${booking.full_name}</div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;">Email</div>
                        <div>${booking.email}</div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;">Phone</div>
                        <div>${booking.phone}</div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;">Destination</div>
                        <div>${booking.destination}</div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;">Package</div>
                        <div><span class="info-badge package-badge">${booking.package || 'Basic Pilgrimage'}</span></div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;">Hotel Category</div>
                        <div><span class="info-badge hotel-badge">${booking.hotel_category || 'Basic Hotel'}</span></div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;">Transport Type</div>
                        <div><span class="info-badge transport-badge">${booking.transport_type || 'Bus'}</span></div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;">Travelers</div>
                        <!-- FIXED: Changed from booking.num_travelers to booking.travelers -->
                        <div><span class="info-badge traveler-badge">${booking.travelers || 1} persons</span></div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;">Travel Dates</div>
                        <div>${startDate} to ${endDate}</div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;">Total Price</div>
                        <div style="font-weight: 700; color: var(--primary);">₹${parseFloat(booking.price).toLocaleString('en-IN')}</div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;">Booked On</div>
                        <div>${bookedDate}</div>
                    </div>
                    ${booking.notes ? `
                    <div style="grid-column: 1 / -1;">
                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;">Special Notes</div>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 8px;">${booking.notes}</div>
                    </div>` : ''}
                </div>`;
        }
        
        function closeModal() { 
            document.getElementById('bookingModal').style.display = 'none'; 
        }
        
        function printBookingDetails() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Booking Details</title>
                        <style>
                            body {
                                font-family: sans-serif;
                                padding: 20px;
                                line-height: 1.6;
                            }
                            h3 {
                                color: #6a11cb;
                                border-bottom: 2px solid #6a11cb;
                                padding-bottom: 10px;
                            }
                            .booking-info {
                                display: grid;
                                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                                gap: 15px;
                                margin-top: 20px;
                            }
                            .info-item {
                                margin-bottom: 15px;
                            }
                            .label {
                                font-weight: bold;
                                color: #2d3748;
                                margin-bottom: 5px;
                            }
                            .value {
                                color: #718096;
                            }
                            .badge {
                                display: inline-block;
                                padding: 4px 10px;
                                border-radius: 12px;
                                font-size: 0.9rem;
                                font-weight: 600;
                            }
                            @media print {
                                body {
                                    font-size: 12px;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <h3>Booking Details</h3>
                        <div class="booking-info">
                            ${document.getElementById('bookingDetailsContent').innerHTML.replace(/style="[^"]*"/g, '').replace(/class="[^"]*"/g, '').replace(/<span[^>]*>/g, '<span class="badge">').replace(/<\/span>/g, '</span>')}
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        // Auto-remove alerts after 5 seconds
        setTimeout(() => { 
            document.querySelector('.alert-success')?.remove(); 
            document.querySelector('.alert-error')?.remove(); 
        }, 5000);
    </script>
</body>
</html>