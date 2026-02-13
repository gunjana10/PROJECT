<?php
// dynamic-destination.php
session_start();
include("db.php");

// Get destination name from URL
$name = isset($_GET['name']) ? mysqli_real_escape_string($conn, $_GET['name']) : '';

// Get travelers and dates from URL if coming from search
$travelers_from_search = isset($_GET['travelers']) ? intval($_GET['travelers']) : 1;
$start_date_from_search = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date_from_search = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$total_price_from_search = isset($_GET['total_price']) ? $_GET['total_price'] : '';

// Fetch destination details
$query = "SELECT * FROM destinations WHERE name = '$name'";
$result = mysqli_query($conn, $query);
$destination = mysqli_fetch_assoc($result);

// If destination not found, redirect to homepage
if (!$destination) {
    header("Location: index.php");
    exit();
}

// Function to extract number of days from duration string
function extractDaysFromDuration($duration_text) {
    $text = strtolower(trim($duration_text));
    
    if (preg_match('/(\d+)\s*(?:day|d)/i', $text, $matches)) {
        return (int)$matches[1];
    } elseif (preg_match('/(\d+)\s*(?:night|n)/i', $text, $matches)) {
        return (int)$matches[1] + 1;
    } elseif (preg_match('/(\d+)/', $text, $matches)) {
        return (int)$matches[1];
    }
    
    return 5;
}

// Function to format price with RS. prefix
function formatPriceWithRs($price) {
    if (strpos($price, 'RS.') === false && strpos($price, 'Rs.') === false && strpos($price, 'rs.') === false) {
        return 'RS.' . $price;
    }
    return $price;
}

// Parse itinerary and other fields
$itinerary = !empty($destination['itinerary']) ? json_decode($destination['itinerary'], true) : [];
$included_services = !empty($destination['included_services']) ? explode("\n", $destination['included_services']) : [];
$not_included = !empty($destination['not_included']) ? explode("\n", $destination['not_included']) : [];

// Get duration, difficulty, best season from database
$duration = !empty($destination['duration']) ? $destination['duration'] : '5 Days';
$difficulty = !empty($destination['difficulty']) ? $destination['difficulty'] : 'Easy';
$best_season = !empty($destination['best_season']) ? $destination['best_season'] : 'Year-Round';
$highlights = !empty($destination['highlights']) ? explode(',', $destination['highlights']) : [];

// Calculate trip duration in days
$trip_duration_days = extractDaysFromDuration($duration);

// ============== FIX: ADD RS. PREFIX TO PRICE ==============
$price_raw = $destination['price'];
$formatted_price = formatPriceWithRs($price_raw);
$base_price_per_person = floatval(str_replace(['RS.', ',', ' ', 'Rs.', 'rs.', '₹', 'रू'], '', $formatted_price));
// ==========================================================

// If coming from search with total price, use that
if (!empty($total_price_from_search)) {
    $display_total_price = $total_price_from_search;
    $total_price_numeric = floatval(str_replace(['RS.', ',', ' ', 'Rs.', 'rs.', '₹', 'रू'], '', $total_price_from_search));
} else {
    // Default to per person price with RS. prefix
    $display_total_price = $formatted_price;
    $total_price_numeric = $base_price_per_person;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($destination['name']); ?> - TripNext</title>
    <style>
        /* ALL YOUR EXISTING STYLES HERE - Keep them exactly as they are */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1e5799 0%, #2989d8 100%);
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.9);
            color: #1e5799;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .back-btn:hover {
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .hero-header {
            position: relative;
            border-radius: 20px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            height: 400px;
        }

        .hero-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.7);
        }

        .hero-content {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            padding: 30px;
            background: linear-gradient(135deg, rgba(30, 87, 153, 0.3) 0%, rgba(41, 137, 216, 0.3) 100%);
        }

        .hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 15px;
            font-weight: 700;
            text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.5);
        }

        .hero-content p {
            font-size: 1.4rem;
            margin-bottom: 10px;
            text-shadow: 1px 1px 5px rgba(0, 0, 0, 0.5);
        }

        .price-tag {
            background: rgba(255, 255, 255, 0.9);
            color: #1e5799;
            padding: 10px 25px;
            border-radius: 25px;
            font-size: 1.5rem;
            font-weight: 700;
            margin-top: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .hero-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .introduction-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .introduction-card h2 {
            color: #1e5799;
            margin-bottom: 20px;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .introduction-card p {
            color: #5d6d7e;
            margin-bottom: 15px;
            font-size: 1.1rem;
            line-height: 1.8;
        }

        .highlights-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .highlight-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: rgba(30, 87, 153, 0.1);
            border-radius: 10px;
            font-weight: 500;
            color: #1e5799;
        }

        .stats-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .stats-card h2 {
            color: #1e5799;
            margin-bottom: 25px;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #1e5799, #2989d8);
            border-radius: 15px;
            color: white;
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .content-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .itinerary-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .itinerary-card h3 {
            color: #1e5799;
            margin-bottom: 20px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .itinerary-card h3::before {
            content: '🗓️';
            font-size: 1.3rem;
        }

        .itinerary-day {
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(30, 87, 153, 0.05);
            border-radius: 12px;
            border-left: 4px solid #1e5799;
            transition: all 0.3s ease;
        }

        .itinerary-day:hover {
            background: rgba(30, 87, 153, 0.1);
            transform: translateX(5px);
        }

        .itinerary-day h4 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .itinerary-day p {
            color: #5d6d7e;
            margin: 0;
            font-size: 0.95rem;
        }

        .booking-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .booking-card h3 {
            color: #1e5799;
            margin-bottom: 20px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .booking-card h3::before {
            content: '🎯';
            font-size: 1.3rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e8eaf0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1e5799;
            box-shadow: 0 0 0 3px rgba(30, 87, 153, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .book-now-btn {
            background: linear-gradient(135deg, #1e5799, #2989d8);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .book-now-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(30, 87, 153, 0.3);
        }

        .policy-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .policy-section h3 {
            color: #1e5799;
            margin-bottom: 25px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .policy-section h3::before {
            content: '📝';
            font-size: 1.3rem;
        }

        .policy-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .policy-item {
            padding: 20px;
            background: rgba(30, 87, 153, 0.05);
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(30, 87, 153, 0.1);
        }

        .policy-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .policy-item h4 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .policy-item p {
            color: #5d6d7e;
            margin: 0;
            font-size: 0.9rem;
        }

        .success-message {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
            display: none;
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        .error-message {
            background: linear-gradient(135deg, #f44336, #da190b);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
            display: none;
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.3);
        }

        @media (max-width: 768px) {
            .hero-section,
            .content-section {
                grid-template-columns: 1fr;
            }
            
            .stats-grid,
            .highlights-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .hero-header {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-btn">← Back to All Destinations</a>
        
        <!-- Success/Error Messages -->
        <div class="success-message" id="successMessage">
            ✅ Booking submitted successfully! We will contact you within 24 hours.
        </div>
        <div class="error-message" id="errorMessage">
            ❌ There was an error submitting your booking. Please try again.
        </div>
        
        <!-- Hero Header with Image -->
        <div class="hero-header">
            <img src="<?php echo htmlspecialchars($destination['image_url']); ?>" 
                 alt="<?php echo htmlspecialchars($destination['name']); ?>" 
                 class="hero-image"
                 onerror="this.src='https://via.placeholder.com/1200x400/1e5799/ffffff?text=<?php echo urlencode($destination['name']); ?>'">
            <div class="hero-content">
                <h1><?php echo htmlspecialchars($destination['name']); ?></h1>
                <p><?php echo htmlspecialchars($destination['description']); ?></p>
                <div class="price-tag"><?php echo $formatted_price; ?></div>
                <?php if (!empty($travelers_from_search) && $travelers_from_search > 1): ?>
                <div class="price-tag" style="margin-top: 10px; background: #ff8c00; color: white;">
                    Total for <?php echo $travelers_from_search; ?> travelers: <?php echo $display_total_price; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Hero Section -->
        <div class="hero-section">
            <div class="introduction-card">
                <h2>Destination Overview</h2>
                <p><?php echo nl2br(htmlspecialchars($destination['package_details'])); ?></p>
                
                <?php if (!empty($included_services)): ?>
                <div class="highlights-grid">
                    <?php foreach ($included_services as $service): 
                        if (trim($service)): ?>
                    <div class="highlight-item">✅ <?php echo htmlspecialchars(trim($service)); ?></div>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="stats-card">
                <h2>Trip Information</h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $trip_duration_days; ?> Days</div>
                        <div class="stat-label">Duration</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $formatted_price; ?></div>
                        <div class="stat-label">Starting Price</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo htmlspecialchars($difficulty); ?></div>
                        <div class="stat-label">Difficulty Level</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo htmlspecialchars($best_season); ?></div>
                        <div class="stat-label">Best Season</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="content-section">
            <?php if (!empty($itinerary)): ?>
            <div class="itinerary-card">
                <h3>Journey Itinerary</h3>
                <?php foreach ($itinerary as $day): ?>
                <div class="itinerary-day">
                    <h4><?php echo htmlspecialchars($day['title']); ?></h4>
                    <p><?php echo htmlspecialchars($day['description']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="itinerary-card">
                <h3>Journey Itinerary</h3>
                <div class="itinerary-day">
                    <h4>Custom Itinerary</h4>
                    <p>Contact us for a personalized itinerary for your journey to <?php echo htmlspecialchars($destination['name']); ?>.</p>
                </div>
            </div>
            <?php endif; ?>

            <div class="booking-card">
                <h3>Book Your Journey</h3>
                
                <form method="POST" action="process-booking.php" id="booking-form">
                    <input type="hidden" name="destination" value="<?php echo htmlspecialchars($destination['name']); ?>">
                    
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" required placeholder="Enter your full name">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required placeholder="your.email@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" required placeholder="Enter your phone number">
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">Start Date *</label>
                        <input type="date" id="start_date" name="start_date" required 
                               value="<?php echo htmlspecialchars($start_date_from_search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date *</label>
                        <input type="date" id="end_date" name="end_date" required readonly
                               value="<?php echo htmlspecialchars($end_date_from_search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="travelers">Number of Travelers *</label>
                        <select id="travelers" name="travelers" required>
                            <option value="">Select travelers</option>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($travelers_from_search == $i) ? 'selected' : ''; ?>>
                                <?php echo $i; ?> Person<?php echo $i > 1 ? 's' : ''; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="package">Package *</label>
                        <select id="package" name="package" required>
                            <option value="Basic Package">Basic Package</option>
                            <option value="Standard Package">Standard Package</option>
                            <option value="Premium Package">Premium Package</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="hotel">Hotel Category *</label>
                        <select id="hotel" name="hotel" required>
                            <option value="Basic Hotel">Basic Hotel</option>
                            <option value="3-Star Hotel">3-Star Hotel</option>
                            <option value="4-Star Hotel">4-Star Hotel</option>
                            <option value="5-Star Hotel">5-Star Hotel</option>
                            <option value="Luxury Resort">Luxury Resort</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="transport">Transport Type *</label>
                        <select id="transport" name="transport" required>
                            <option value="Bus">Bus</option>
                            <option value="Private Car">Private Car</option>
                            <option value="Flight">Flight</option>
                            <option value="Jeep">Jeep</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="special_requests">Special Requests</label>
                        <textarea id="special_requests" name="special_requests" placeholder="Any special requirements or additional requests..."></textarea>
                    </div>
                    
                    <!-- IMPORTANT: This is the TOTAL PRICE for all travelers -->
                    <input type="hidden" id="final_price" name="price" value="<?php echo $total_price_numeric; ?>">
                    
                    <button type="submit" class="book-now-btn" id="bookNowBtn">
                        Book Now - <?php echo $display_total_price; ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Policy Section -->
        <div class="policy-section">
            <h3>Booking Policy</h3>
            <div class="policy-grid">
                <div class="policy-item">
                    <h4>✅ Free Cancellation</h4>
                    <p>Full refund up to 30 days before travel</p>
                </div>
                <div class="policy-item">
                    <h4>⚠️ 15-30 Days</h4>
                    <p>50% refund of total amount</p>
                </div>
                <div class="policy-item">
                    <h4>❌ 7-14 Days</h4>
                    <p>25% refund available</p>
                </div>
                <div class="policy-item">
                    <h4>🚫 Last Week</h4>
                    <p>No refund available</p>
                </div>
                <div class="policy-item">
                    <h4>📞 Support</h4>
                    <p>+977-9765340620</p>
                </div>
                <div class="policy-item">
                    <h4>📧 Email</h4>
                    <p>tripnext@explorenepal.com</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Base price per person from PHP
        const basePricePerPerson = <?php echo $base_price_per_person; ?>;
        const tripDurationDays = <?php echo $trip_duration_days; ?>;
        
        // Get form elements
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        const travelersSelect = document.getElementById('travelers');
        const packageSelect = document.getElementById('package');
        const hotelSelect = document.getElementById('hotel');
        const transportSelect = document.getElementById('transport');
        const bookButton = document.getElementById('bookNowBtn');
        const priceInput = document.getElementById('final_price');
        
        // Package multipliers
        const packageMultipliers = {
            'Basic Package': 1.0,
            'Standard Package': 1.3,
            'Premium Package': 1.6
        };
        
        // Hotel multipliers
        const hotelMultipliers = {
            'Basic Hotel': 1.0,
            '3-Star Hotel': 1.4,
            '4-Star Hotel': 1.8,
            '5-Star Hotel': 2.3,
            'Luxury Resort': 3.0
        };
        
        // Transport multipliers
        const transportMultipliers = {
            'Bus': 1.0,
            'Private Car': 1.5,
            'Jeep': 1.3,
            'Flight': 2.0
        };
        
        // Function to format price in RS.XX,XXX format
        function formatPrice(amount) {
            return 'RS.' + Math.round(amount).toLocaleString('en-IN');
        }
        
        // Function to calculate total price
        function calculateTotalPrice() {
            const travelers = parseInt(travelersSelect.value) || 1;
            const packageMultiplier = packageMultipliers[packageSelect.value] || 1.0;
            const hotelMultiplier = hotelMultipliers[hotelSelect.value] || 1.0;
            const transportMultiplier = transportMultipliers[transportSelect.value] || 1.0;
            
            // Calculate per person price with all multipliers
            const perPersonPrice = basePricePerPerson * packageMultiplier * hotelMultiplier * transportMultiplier;
            
            // Calculate total for all travelers
            const totalPrice = perPersonPrice * travelers;
            
            return {
                perPerson: perPersonPrice,
                total: totalPrice,
                travelers: travelers
            };
        }
        
        // Function to update price display
        function updatePrice() {
            const priceData = calculateTotalPrice();
            
            // Update button text
            bookButton.textContent = `Book Now - ${formatPrice(priceData.total)}`;
            
            // Update hidden input with total price
            priceInput.value = priceData.total;
        }
        
        // Set minimum date for start date to today
        const today = new Date().toISOString().split('T')[0];
        startDateInput.min = today;
        
        // WHEN START DATE IS SELECTED - Calculate and show end date
        startDateInput.addEventListener('change', function() {
            if (this.value) {
                const startDate = new Date(this.value);
                const endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + (tripDurationDays - 1));
                
                // Format end date as YYYY-MM-DD
                const year = endDate.getFullYear();
                const month = String(endDate.getMonth() + 1).padStart(2, '0');
                const day = String(endDate.getDate()).padStart(2, '0');
                const formattedEndDate = `${year}-${month}-${day}`;
                
                // Set end date
                endDateInput.value = formattedEndDate;
            }
        });
        
        // Add event listeners for price calculation
        travelersSelect.addEventListener('change', updatePrice);
        packageSelect.addEventListener('change', updatePrice);
        hotelSelect.addEventListener('change', updatePrice);
        transportSelect.addEventListener('change', updatePrice);
        
        // Check URL for success/error parameters
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('booking') === 'success') {
            document.getElementById('successMessage').style.display = 'block';
            document.getElementById('successMessage').scrollIntoView({ behavior: 'smooth' });
        } else if (urlParams.get('booking') === 'error') {
            document.getElementById('errorMessage').style.display = 'block';
            document.getElementById('errorMessage').scrollIntoView({ behavior: 'smooth' });
        }
        
        // If start date is set from search, trigger end date calculation
        if (startDateInput.value) {
            const event = new Event('change');
            startDateInput.dispatchEvent(event);
        }
        
        // Initialize price calculation
        setTimeout(updatePrice, 100);
    </script>
</body>
</html>