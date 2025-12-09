<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TripNext</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    
  <style>
    /* ALL THE SAME CSS STYLES AS BEFORE */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    /* ... (keep all your CSS exactly the same) ... */
  </style>
</head>
<body>

  <!-- Navbar -->
  <header>
    <div class="logo">TripNext</div>
    <nav>
      <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="#">Discover</a></li>
        <li><a href="#">Plan</a></li>
        <li><a href="#">Blog</a></li>
        <li><a href="#">About</a></li>
      </ul>
    </nav>
    <div class="nav-right">
      <button class="signin-btn"><a href="signin.php">Sign In</a></button>
      <button class="getstarted-btn"><a href="signup.php">Get Started</a></button>
    </div>
  </header>

  <!-- Hero Section -->
  <section class="project">
    <div class="hero">
      <h1>Your Next Adventure Awaits</h1>
      <p>Discover breathtaking destinations, plan unforgettable trips, and create memories that last a lifetime.</p> <br> <br>
      <div class="search-box">
        <input type="text" placeholder="Where do you want to go?">
        <button>🔍</button>
      </div>
      <button class="start-btn">Start Your Adventure</button>
    </div>
  </section>
  
  <!-- Search Container -->
  <div class="search-container">
    <h2>Where do you want to go?</h2>
    <div class="search-bar">
      <!-- Start Date -->
      <div class="search-field">
        <label for="startDate">Start Date</label>
        <div class="search-field-input">
          <i class="fa fa-calendar"></i>
          <input type="date" id="startDate" placeholder="Start Date">
        </div>
      </div>
      
      <!-- End Date -->
      <div class="search-field">
        <label for="endDate">End Date</label>
        <div class="search-field-input">
          <i class="fa fa-calendar"></i>
          <input type="date" id="endDate" placeholder="End Date">
        </div>
      </div>
      
      <!-- Destination Dropdown -->
      <div class="search-field">
        <label for="destination">Destination</label>
        <div class="search-field-input">
          <i class="fa fa-map-marker"></i>
          <select id="destination">
            <option value="">Select Destination</option>
            <option value="Pokhara">Pokhara</option>
            <option value="Chitwan">Chitwan</option>
            <option value="Lumbini">Lumbini</option>
            <option value="Annapurna">Annapurna</option>
          </select>
        </div>
      </div>
      
      <button class="search-button" id="searchButton"><i class="fa fa-search"></i> Search</button>
    </div>
    
    <!-- Search Results Section -->
    <div class="search-results" id="searchResults">
      <div class="results-header">
        <h2 class="results-title">Search Results</h2>
        <div class="results-count" id="resultsCount">0 destinations found</div>
      </div>
      
      <div class="destination-cards-results" id="destinationCardsResults">
        <!-- Results will be populated here by JavaScript -->
      </div>
    </div>
  </div>

  <!-- Explore Section -->
  <section class="explore-section">
    <h2>Explore Nepal</h2>
    <p>Discover the breathtaking beauty and spiritual richness of Nepal's most iconic destinations</p>

    <div class="card-container">
      <!-- Lumbini -->
      <div class="card">
        <img src="images/lumbini.jpg" alt="Lumbini">
        <div class="rating">⭐ 4.9</div>
        <div class="card-content">
          <h3>Lumbini</h3>
          <p>Birthplace of Lord Buddha, UNESCO World Heritage Site</p>
          <p class="price">From RS.21,480</p>
          <button class="btn"><a href="lumbini.html" class="btn view-details-btn">View Details</a></button>
        </div>
      </div>

      <!-- Chitwan National Park -->
      <div class="card">
        <img src="images/chitwan.jpg" alt="Chitwan National Park">
        <div class="rating">⭐ 4.8</div>
        <div class="card-content">
          <h3>Chitwan National Park</h3>
          <p>Wildlife safari and jungle adventures</p>
          <p class="price">From RS.35,880</p>
          <button class="btn"><a href="chitwan.html" class="btn view-details-btn">View Details</a></button>
        </div>
      </div>

      <!-- Annapurna Base Camp -->
      <div class="card">
        <img src="images/arnapurna.jpg" alt="Annapurna Base Camp">
        <div class="rating">⭐ 4.9</div>
        <div class="card-content">
          <h3>Annapurna Base Camp (ABC)</h3>
          <p>Stunning mountain views and trekking paradise</p>
          <p class="price">From RS.13,850</p>
          <button class="btn"><a href="annapurna.html" class="btn view-details-btn">View Details</a></button>
        </div>
      </div>

       <!-- Pokhara -->
      <div class="card">
        <img src="images/pokhara.jpg" alt="Pokhara">
        <div class="rating">⭐ 4.7</div>
        <div class="card-content">
          <h3>Pokhara</h3>
          <p>Stunning mountain views and trekking paradise</p>
          <p class="price">From RS.23,880</p>
          <button class="btn"><a href="pokhara.html" class="btn view-details-btn">View Details</a></button>
        </div>
      </div>
    </div>
  </section>

  <!-- Choose Section -->
  <section class="choose-section">
    <h2>Why Choose TripNext?</h2>
    <p>Everything you need to plan, book, and enjoy your perfect trip</p>

    <div class="features-container">
      
      <div class="feature-card">
        <i class="fa fa-map-marker"></i>
        <h3>Smart Planning</h3>
        <p>AI-powered recommendations based on your preferences and travel style</p>
      </div>

      <div class="feature-card">
        <i class="fa fa-users"></i>
        <h3>Group Travel</h3>
        <p>Coordinate with friends and family to plan the perfect group adventure</p>
      </div>

      <div class="feature-card">
        <i class="fa fa-camera"></i>
        <h3>Travel Journal</h3>
        <p>Document your journey with photos, notes, and memories that last forever</p>
      </div>

    </div>
  </section>

  <!-- Journey Section -->
  <section class="journey-section">
    <h2>Ready to Start Your Journey?</h2>
    <p>Join thousands of travelers who trust TripNext to make their dream trips a reality</p>
    
    <div class="button-container">
      <a href="signup.php" class="btn-primary">Sign Up Free</a>
      <a href="#" class="btn-outline">Learn More</a>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer-section">
    <div class="footer-top">
      <h2>Ready to Explore ?</h2>
      <p>Book your travel package today and create unforgettable memories in the beautiful landscapes of Nepal.</p>
    </div>

    <div class="footer-content">
      <div class="footer-box">
        <h3 class="footer-logo">TripNest</h3>
        <p>Your trusted partner for exploring the beautiful destinations of Nepal with ease and comfort.</p>
        <div class="social-icons">
          <a href="#"><i class="fa fa-facebook"></i></a>
          <a href="#"><i class="fa fa-instagram"></i></a>
        </div>
      </div>

      <div class="footer-box">
        <h4>Destinations</h4>
        <ul>
          <li>Pokhara</li>
          <li>Chitwan</li>
          <li>Kathmandu</li>
          <li>Lumbini</li>
        </ul>
      </div>

      <div class="footer-box">
        <h4>Quick Links</h4>
        <ul>
          <li>About Us</li>
          <li>Contact Us</li>
          <li>Terms and Conditions</li>
          <li>Privacy Policy</li>
        </ul>
      </div>

      <div class="footer-box">
        <h4>Contact Info</h4>
        <p><i class="fa fa-map-marker"></i> Kathmandu, Nepal</p>
        <p><i class="fa fa-phone"></i> +977 9849086473</p>
        <p><i class="fa fa-envelope"></i> info@tripnest.com</p>
      </div>
    </div>

    <div class="footer-bottom">
      <p><i class="fa fa-copyright"></i> 2025 <strong>TripNest.</strong> All rights reserved.</p>
    </div>
  </footer>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const searchButton = document.getElementById('searchButton');
      const searchResults = document.getElementById('searchResults');
      const destinationCardsResults = document.getElementById('destinationCardsResults');
      const resultsCount = document.getElementById('resultsCount');
      
      // Set minimum date to today
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('startDate').min = today;
      document.getElementById('endDate').min = today;
      
      // Update end date min when start date changes
      document.getElementById('startDate').addEventListener('change', function() {
        document.getElementById('endDate').min = this.value;
      });
      
      // Destination data for Nepal with page links (pointing to HTML files)
      const destinations = {
        "Pokhara": {
          name: "Pokhara",
          price: "RS.23,880",
          page: "pokhara.html" 
        },
        "Chitwan": {
          name: "Chitwan National Park",
          price: "RS.35,880",
          page: "chitwan.html" 
        },
        "Lumbini": {
          name: "Lumbini",
          price: "RS.21,480",
          page: "lumbini.html" 
        },
        "Annapurna": {
          name: "Annapurna Circuit",
          price: "RS.13,850",
          page: "annapurna.html"  
        }
      };
      
      // Handle search button click
      searchButton.addEventListener('click', function(e) {
        e.preventDefault();
        
        const destination = document.getElementById('destination').value;
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        // Validate dates
        if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
          alert('End date must be after start date');
          return;
        }
        
        // Validate destination
        if (!destination) {
          alert('Please select a destination');
          return;
        }
        
        // Show results section
        searchResults.style.display = 'block';
        
        // Format dates for display
        const formattedStartDate = formatDate(startDate);
        const formattedEndDate = formatDate(endDate);
        
        // Clear previous results
        destinationCardsResults.innerHTML = '';
        
        // Display results
        if (destination && destinations[destination]) {
          const dest = destinations[destination];
          
          const card = document.createElement('div');
          card.className = 'destination-card-result';
          card.innerHTML = `
            <h3 class="card-title-result">${dest.name}</h3>
            <p class="card-dates-result">📅 ${formattedStartDate} - ${formattedEndDate}</p>
            <p class="card-price-result">${dest.price}</p>
            <a href="${dest.page}" class="card-button-result">Book Now</a>
          `;
          destinationCardsResults.appendChild(card);
          
          resultsCount.textContent = "1 destination found";
        } else {
          destinationCardsResults.innerHTML = `
            <div class="no-results">
              <i>✈️</i>
              <p>Please select one of the available destinations from the dropdown</p>
            </div>
          `;
          resultsCount.textContent = "0 destinations found";
        }
        
        // Scroll to results
        searchResults.scrollIntoView({ behavior: 'smooth' });
      });
      
      // Helper function to format date as mm/dd/yyyy
      function formatDate(dateString) {
        if (!dateString) return "Not selected";
        const date = new Date(dateString);
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const year = date.getFullYear();
        return `${month}/${day}/${year}`;
      }
    });
  </script>

</body>
</html>