<?php
// dynamic-destination.php
session_start();
include("db.php");

// Get destination name from URL
$name = isset($_GET['name']) ? mysqli_real_escape_string($conn, $_GET['name']) : '';

// Fetch destination details
$query = "SELECT * FROM destinations WHERE name = '$name'";
$result = mysqli_query($conn, $query);
$destination = mysqli_fetch_assoc($result);

// If destination not found, redirect to homepage
if (!$destination) {
    header("Location: index.php");
    exit();
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

// Calculate base price from "RS.XX,XXX" format
$price_raw = $destination['price'];
$base_price = intval(str_replace(['RS.', ',', ' ', 'Rs.', 'rs.', '₹', 'रू'], '', $price_raw));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($destination['name']); ?> - Explore Nepal</title>
    <style>
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

        .input-hint {
            color: #666;
            font-size: 0.8rem;
            display: block;
            margin-top: 5px;
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
                <div class="price-tag">From <?php echo htmlspecialchars($destination['price']); ?></div>
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
                        <div class="stat-number"><?php echo count($itinerary); ?> Days</div>
                        <div class="stat-label">Duration</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo htmlspecialchars($destination['price']); ?></div>
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
                        <input type="tel" id="phone" name="phone" required placeholder="Enter your phone number" pattern="[\+\d\s\-\(\)]*">
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">Start Date *</label>
                        <input type="date" id="start_date" name="start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date *</label>
                        <input type="date" id="end_date" name="end_date" required readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="travelers">Number of Travelers *</label>
                        <select id="travelers" name="travelers" required>
                            <option value="">Select travelers</option>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?> Person<?php echo $i > 1 ? 's' : ''; ?></option>
                            <?php endfor; ?>
                            <option value="11">11+ People</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="package">Package *</label>
                        <select id="package" name="package" required>
                            <option value="Basic Pilgrimage (Rs.21,480)">Basic Package (<?php echo htmlspecialchars($destination['price']); ?>)</option>
                            <option value="Standard Package (Rs.28,500)">Standard Package (<?php 
                                $standard_price = $base_price * 1.3;
                                echo 'RS.' . number_format($standard_price);
                            ?>)</option>
                            <option value="Premium Experience (Rs.35,000)">Premium Experience (<?php 
                                $premium_price = $base_price * 1.6;
                                echo 'RS.' . number_format($premium_price);
                            ?>)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="hotel">Hotel Category *</label>
                        <select id="hotel" name="hotel" required>
                            <option value="basic">Basic Hotel</option>
                            <option value="3star">3-Star Hotel</option>
                            <option value="4star">4-Star Hotel</option>
                            <option value="5star">5-Star Hotel</option>
                            <option value="luxury">Luxury Resort</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="transport">Transport Type *</label>
                        <select id="transport" name="transport" required>
                            <option value="bus">Bus</option>
                            <option value="air">Flight</option>
                            <option value="private">Private Vehicle</option>
                            <option value="jeep">Jeep</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="special_requests">Special Requests</label>
                        <textarea id="special_requests" name="special_requests" placeholder="Any special requirements or additional requests..."></textarea>
                    </div>
                    
                    <input type="hidden" id="final_price" name="price" value="<?php echo $base_price; ?>">
                    
                    <button type="submit" class="book-now-btn" id="bookNowBtn">
                        Book Now - <?php echo htmlspecialchars($destination['price']); ?>
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
                    <p>+977-9849086473</p>
                </div>
                <div class="policy-item">
                    <h4>📧 Email</h4>
                    <p>info@tripnest.com</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Base price from PHP
        const basePrice = <?php echo $base_price; ?>;
        const travelersSelect = document.getElementById('travelers');
        const packageSelect = document.getElementById('package');
        const hotelSelect = document.getElementById('hotel');
        const transportSelect = document.getElementById('transport');
        const bookButton = document.getElementById('bookNowBtn');
        const priceInput = document.getElementById('final_price');

        // Hotel price multipliers (per night, 4 nights stay) - SAME AS STATIC
        const hotelPrices = {
            'basic': 400 * 4,
            '3star': 1800 * 4,
            '4star': 3500 * 4,
            '5star': 7000 * 4,
            'luxury': 10000 * 4
        };

        // Transport price multipliers - SAME AS STATIC
        const transportPrices = {
            'bus': 1200,
            'air': 7500,
            'private': 10000,
            'jeep': 2500
        };

        // Package multipliers
        const packageMultipliers = {
            'Basic Pilgrimage (Rs.21,480)': 1,
            'Standard Package (Rs.28,500)': 1.3,
            'Premium Experience (Rs.35,000)': 1.6
        };

        function updatePrice() {
            const travelers = parseInt(travelersSelect.value) || 1;
            const packageType = packageSelect.value;
            const hotelType = hotelSelect.value;
            const transportType = transportSelect.value;
            
            let packageMultiplier = packageMultipliers[packageType] || 1;
            
            // Calculate base price
            const calculatedBasePrice = basePrice * travelers * packageMultiplier;
            
            // Add hotel cost
            const hotelCost = (hotelPrices[hotelType] || 0) * travelers;
            
            // Add transport cost
            const transportCost = (transportPrices[transportType] || 0) * travelers;
            
            // Total price
            const totalPrice = Math.round(calculatedBasePrice + hotelCost + transportCost);
            
            bookButton.textContent = `Book Now - RS.${totalPrice.toLocaleString()}`;
            priceInput.value = totalPrice;
        }

        travelersSelect.addEventListener('change', updatePrice);
        packageSelect.addEventListener('change', updatePrice);
        hotelSelect.addEventListener('change', updatePrice);
        transportSelect.addEventListener('change', updatePrice);

        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('start_date').min = today;

        // Automatically calculate end date (based on itinerary length)
        document.getElementById('start_date').addEventListener('change', function() {
            if (this.value) {
                const startDate = new Date(this.value);
                const endDate = new Date(startDate);
                // Add days based on itinerary length (default 4 nights/5 days)
                const itineraryDays = <?php echo max(count($itinerary) - 1, 4); ?>;
                endDate.setDate(endDate.getDate() + itineraryDays);
                
                // Format to YYYY-MM-DD
                const endDateString = endDate.toISOString().split('T')[0];
                document.getElementById('end_date').value = endDateString;
                
                // Update price based on new dates if needed
                updatePrice();
            }
        });

        // Check URL for success/error parameters
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('booking') === 'success') {
            document.getElementById('successMessage').style.display = 'block';
            document.getElementById('successMessage').scrollIntoView({ behavior: 'smooth' });
        } else if (urlParams.get('booking') === 'error') {
            document.getElementById('errorMessage').style.display = 'block';
            document.getElementById('errorMessage').scrollIntoView({ behavior: 'smooth' });
        }

        // Initialize price calculation
        updatePrice();
    </script>
</body>
</html>