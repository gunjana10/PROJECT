<?php
include("db.php"); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TripNext</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
      background-color: #99bdc5;
      color: #222;
    }

    a {
      text-decoration: none;
      color: inherit;
    }

    /* ---------- Navbar ---------- */
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 88px;
      background-color:rgb(98, 124, 124);
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      position:sticky;
      top: 0;
      z-index: 90;
    }

    .logo {
      font-size: 1.5rem;
      font-weight: bold;
      color: #006d6d;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .logo::before {
      content: "✈️";
    }

    nav ul {
      display: flex;
      list-style: none;
      gap: 30px;
    }

    nav ul li {
      font-weight: 500;
      color: #333;
      gap: 15px;
      position: relative;
    }

    .nav-right {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .nav-right button {
      border: none;
      cursor: pointer;
      padding: 8px 16px;
      border-radius: 6px;
      font-weight: 500;
    }

    .signin-btn {
      background: #fff;
      border: 1px solid #ccc;
    }

    .admin-btn {
      background: #f7f7f7;
      border: 1px solid #ccc;
    }

    .getstarted-btn {
      background-color: #004d4d;
      color: white;
    }

    /* ---------- Dropdown Styles ---------- */
    .dropdown {
      position: relative;
    }

    .dropbtn {
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .dropdown-content {
      display: none;
      position: absolute;
      background-color: #ffffff;
      min-width: 220px;
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
      border-radius: 8px;
      z-index: 100;
      top: 100%;
      left: 0;
      padding: 10px 0;
      margin-top: 10px;
    }

    .dropdown-content a {
      color: #333;
      padding: 12px 20px;
      text-decoration: none;
      display: block;
      font-weight: 500;
      transition: all 0.3s ease;
      border-left: 3px solid transparent;
    }

    .dropdown-content a:hover {
      background-color: #f0faf7;
      color: #004d4d;
      border-left: 3px solid #004d4d;
    }

    .dropdown:hover .dropdown-content {
      display: block;
    }

    .dropdown:hover .dropbtn {
      color: #004d4d;
    }

    .dropdown-content::before {
      content: '';
      position: absolute;
      top: -10px;
      left: 20px;
      border-width: 0 10px 10px 10px;
      border-style: solid;
      border-color: transparent transparent #ffffff transparent;
    }

    /* ---------- Hero Section ---------- */
    .project {
      text-align: center;
      background-color: #7fa9a9;
      color:black;
      padding: 100px 20px;
      background-image: url(images/background.jpg);
      background-repeat: no-repeat;
      background-size: cover;
    }

    .hero h1 {
      font-size: 3rem;
      font-weight: 800;
      margin-bottom: 15px;
    }

    .hero p {
      font-size: 1.2rem;
      max-width: 650px;
      margin: 0 auto 40px auto;
      color: #f2f2f2;
    }

    .search-box {
      display: flex;
      justify-content: center;
      margin-bottom: 30px;
      position: relative;
    }

    .search-box input {
      width: 350px;
      padding: 12px 15px;
      border-radius: 25px 0 0 25px;
      border: none;
      outline: none;
      font-size: 1rem;
    }

    .search-box button {
      background-color: #004d4d;
      border: none;
      color: white;
      padding: 12px 18px;
      border-radius: 0 25px 25px 0;
      cursor: pointer;
    }

    .start-btn {
      background-color: #ff5a00;
      color: white;
      border: none;
      padding: 14px 30px;
      border-radius: 8px;
      font-size: 1.1rem;
      cursor: pointer;
      transition: background 0.3s;
    }

    .start-btn:hover {
      background-color: #ff7300;
    }

    /* NEW: Hero Search Suggestions Dropdown */
    .search-suggestions {
      position: absolute;
      top: 100%;
      left: 50%;
      transform: translateX(-50%);
      width: 350px;
      background-color: white;
      border-radius: 0 0 10px 10px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      display: none;
      z-index: 100;
      margin-top: -5px;
    }

    .search-suggestion-item {
      padding: 12px 15px;
      cursor: pointer;
      border-bottom: 1px solid #eee;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .search-suggestion-item:last-child {
      border-bottom: none;
    }

    .search-suggestion-item:hover {
      background-color: #f0faf7;
      color: #004d4d;
    }

    .search-suggestion-item i {
      color: #004d4d;
    }

    /* ------- Search Container ------- */
    .search-container {
      background-color:aliceblue;
      padding: 45px;
      border-radius: 12px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
      width: 90%;
      max-width: 900px;
      text-align: center;
      margin: -50px auto 50px auto;
    }

    .search-container h2 {
      color: #004d4d;
      font-size: 1.8rem;
      margin-bottom: 25px;
      font-weight: 700;
    }

    /* ------- Search Form ------- */
    .search-bar {
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
    }

    .search-field {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 8px;
      min-width: 180px;
    }

    .search-field label {
      font-weight: 600;
      color: #004d4d;
      font-size: 0.9rem;
      text-align: left;
    }

    .search-field-input {
      display: flex;
      align-items: center;
      gap: 10px;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      padding: 12px 16px;
      background-color: #fff;
    }

    .search-field-input input, .search-field-input select {
      border: none;
      outline: none;
      width: 100%;
      font-size: 1rem;
      background: transparent;
    }

    .search-field-input i {
      color: #004d4d;
      font-size: 1.1rem;
    }

    /* ------- Search Button ------- */
    .search-button {
      background-color: #004d4d;
      color: white;
      border: none;
      border-radius: 8px;
      padding: 12px 28px;
      cursor: pointer;
      font-size: 1rem;
      font-weight: 500;
      transition: background 0.3s;
      margin-top: 24px;
      height: fit-content;
    }

    .search-button:hover {
      background-color: #007272;
    }

    /* ------- Explore Section ------- */
    .explore-section {
      text-align: center;
      padding: 60px 20px;
      background-color:rgb(176, 176, 168);
      width: 100%;
    }

    .explore-section h2 {
      font-size: 2rem;
      font-weight: 700;
      color: #2f3d4c;
    }

    .explore-section p {
      color: #6b7280;
      font-size: 1.1rem;
      margin-bottom: 40px;
    }

    .card-container {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 25px;
    }

    .card {
      background-color: #f3fdf8;
      border-radius: 10px;
      width: 280px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      position: relative;
      transition: transform 0.3s ease;
    }

    .card:hover {
      transform: translateY(-8px);
    }

    .card img {
      width: 100%;
      height: 200px;
      object-fit: cover;
    }

    /* NO RATING STYLES - REMOVED */
    .card-content {
      padding: 20px;
    }

    .card-content h3 {
      color: #004d40;
      font-size: 1.1rem;
      margin-bottom: 8px;
    }

    .card-content p {
      color: #555;
      font-size: 0.95rem;
      margin: 5px 0;
    }

    .price {
      font-weight: bold;
      color: #003c30;
      margin-top: 10px;
      font-size: 1.1rem;
    }

    .btn {
      margin-top: 15px;
      background-color: #004d40;
      color: white;
      border: none;
      padding: 8px 20px;
      border-radius: 6px;
      cursor: pointer;
      transition: background-color 0.3s ease;
      display: inline-block;
    }

    .btn:hover {
      background-color: #00695c;
    }

    /* ------- Choose Section ------- */
    .choose-section {
      background-color: #f0faf7;
      text-align: center;
      padding: 70px 20px;
      width: 100%;
    }

    .choose-section h2 {
      font-size: 2rem;
      color: #004d40;
      font-weight: 700;
    }

    .choose-section p {
      color: #6b7280;
      margin-bottom: 50px;
      font-size: 1.1rem;
    }

    .features-container {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 30px;
    }

    .feature-card {
      background-color: #f8fffc;
      width: 270px;
      border-radius: 20px;
      padding: 40px 25px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .feature-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
    }

    .feature-card i {
      font-size: 2.5rem;
      color: #005f50;
      margin-bottom: 20px;
    }

    .feature-card h3 {
      font-size: 1.2rem;
      color: #004d40;
      margin-bottom: 12px;
    }

    .feature-card p {
      color: #5f6d66;
      font-size: 0.95rem;
      line-height: 1.6;
    }

    /* ------- Journey Section ------- */
    .journey-section {
      background-color: #005f63;
      color: white;
      text-align: center;
      padding: 60px 20px;
      width: 100%;
    }

    .journey-section h2 {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 15px;
    }

    .journey-section p {
      font-size: 1.1rem;
      color: #e2e8f0;
      margin-bottom: 40px;
    }

    .button-container {
      display: flex;
      justify-content: center;
      gap: 20px;
    }

    .btn-primary {
      background-color: #ff5300;
      color: white;
      padding: 12px 30px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 600;
      transition: 0.3s;
    }

    .btn-primary:hover {
      background-color: #e04a00;
    }

    .btn-outline {
      border: 1.5px solid white;
      color: white;
      padding: 12px 30px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 600;
      transition: 0.3s;
    }

    .btn-outline:hover {
      background-color: white;
      color: #005f63;
    }

    /* ------- Footer Section ------- */
    .footer-section {
      background-color: #5a9b97;
      color: white;
      padding-top: 60px;
      text-align: center;
      width: 100%;
    }

    .footer-top h2 {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 10px;
    }

    .footer-top p {
      font-size: 1rem;
      color: #e2e8f0;
      margin-bottom: 50px;
    }

    .footer-content {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 40px;
      text-align: left;
      padding: 0 30px 30px 30px;
    }

    .footer-box {
      flex: 1;
      min-width: 200px;
      max-width: 250px;
    }

    .footer-logo {
      color: #ff3c00;
      font-weight: 700;
      margin-bottom: 10px;
    }

    .footer-box h4 {
      color: #ff3c00;
      font-size: 15px;
      margin-bottom: 10px;
    }

    .footer-box ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .footer-box ul li {
      color: white;
      font-size: 0.95rem;
      margin-bottom: 6px;
      cursor: pointer;
    }

    .footer-box ul li:hover {
      text-decoration: underline;
    }

    .footer-box p {
      color: #f2f2f2;
      font-size: 0.95rem;
      line-height: 1.6;
    }

    .footer-box i {
      color: #ff3c00;
      margin-right: 8px;
    }

    .social-icons {
      margin-top: 15px;
    }

    .social-icons a {
      display: inline-block;
      margin-right: 10px;
      font-size: 1.3rem;
      color: white;
      background-color: #f2f2f2;
      border-radius: 50%;
      width: 32px;
      height: 32px;
      text-align: center;
      line-height: 32px;
      transition: 0.3s;
    }

    .social-icons a:hover {
      background-color: #ff3c00;
      color: white;
    }

    .footer-bottom {
      background-color: #4a8b87;
      text-align: center;
      padding: 15px 10px;
      border-top: 1px solid rgba(255, 255, 255, 0.2);
    }

    .footer-bottom p {
      color: #f2f2f2;
      font-size: 0.9rem;
    }

    .footer-bottom strong {
      color: #ff3c00;
    }

    /* ------- Responsive ------- */
    @media (max-width: 768px) {
      header {
        flex-direction: column;
        padding: 15px 20px;
        gap: 10px;
      }
      nav ul {
        gap: 15px;
        flex-wrap: wrap;
        justify-content: center;
      }
      .hero h1 {
        font-size: 2.2rem;
      }
      .search-box input {
        width: 80%;
      }
      .search-suggestions {
        width: 80%;
        left: 10%;
        transform: none;
      }
      .search-bar {
        flex-direction: column;
      }
      .search-field,
      .search-button {
        width: 100%;
      }
      .button-container {
        flex-direction: column;
        align-items: center;
      }
      .footer-content {
        flex-direction: column;
        align-items: center;
        text-align: center;
      }
      
      /* Responsive dropdown */
      .dropdown-content {
        position: static;
        box-shadow: none;
        border-radius: 0;
        padding: 0;
        margin-top: 0;
        display: none;
      }
      .dropdown-content::before {
        display: none;
      }
      .dropdown-content a {
        padding: 10px 15px;
        background-color: #f8f8f8;
      }
    }

    /* ------- Search Results Styles ------- */
    .search-results {
      background-color: #f9f9f9;
      padding: 20px;
      border-radius: 12px;
      margin-top: 30px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
      display: none;
    }

    .results-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 2px solid #e0e0e0;
    }

    .results-title {
      font-size: 1.5rem;
      color: #004d4d;
    }

    .results-count {
      color: #666;
      font-weight: 600;
    }

    .destination-cards-results {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 15px;
    }

    .destination-card-result {
      background-color: white;
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
      transition: all 0.3s;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .destination-card-result:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .card-title-result {
      font-size: 1.3rem;
      font-weight: 700;
      color: #004d4d;
      margin: 0;
    }

    .card-dates-result {
      color: #666;
      font-size: 0.9rem;
      margin: 0;
    }

    .card-price-result {
      font-size: 1.2rem;
      font-weight: 700;
      color: #004d4d;
      margin: 0;
    }

    .card-button-result {
      background: linear-gradient(to right, #004d4d, #007272);
      color: white;
      border: none;
      padding: 10px 15px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 600;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
      margin-top: 5px;
      display: block;
    }

    .card-button-result:hover {
      background: linear-gradient(to right, #003c3c, #006060);
      text-decoration: none;
      color: white;
    }

    .no-results {
      text-align: center;
      padding: 30px;
      color: #666;
      font-size: 1rem;
    }

    .no-results i {
      font-size: 2.5rem;
      color: #ccc;
      margin-bottom: 10px;
      display: block;
    }
  </style>
</head>
<body>

  <!-- Navbar -->
  <header>
    <div class="logo">TripNext</div>
    <nav>
      <ul>
        <li><a href="index.php">Home</a></li>
        
        <!-- Destinations Dropdown -->
        <li class="dropdown">
          <a href="#" class="dropbtn">Destinations <i class="fa fa-caret-down"></i></a>
          <div class="dropdown-content">
            <a href="pokhara.html">Pokhara</a>
            <a href="chitwan.html">Chitwan National Park</a>
            <a href="lumbini.html">Lumbini</a>
            <a href="annapurna.html">Annapurna Base Camp(ABC)</a>
          </div>
        </li>
        
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
        <input type="text" id="heroSearch" placeholder="Where do you want to go?">
        <button id="heroSearchBtn">🔍</button>
        <!-- Search Suggestions Dropdown -->
        <div class="search-suggestions" id="searchSuggestions">
          <!-- Dynamic suggestions will be loaded here -->
        </div>
      </div>
      <button class="start-btn" id="heroStartBtn">Start Your Adventure</button>
    </div>
  </section>
  
  <!-- Search Container -->
  <div class="search-container">
    <h2>Plan Your Trip</h2>
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
            <?php
            // FIRST: Show static destinations (always show these)
            $static_destinations = [
                ['name' => 'Pokhara', 'page' => 'pokhara.html'],
                ['name' => 'Chitwan National Park', 'page' => 'chitwan.html'],
                ['name' => 'Lumbini', 'page' => 'lumbini.html'],
                ['name' => 'Annapurna Circuit', 'page' => 'annapurna.html']
            ];
            
            foreach ($static_destinations as $dest) {
                echo '<option value="' . htmlspecialchars($dest['name']) . '">' . htmlspecialchars($dest['name']) . '</option>';
            }
            
            // THEN: Show admin-added destinations from database
            $dest_query = "SELECT name FROM destinations WHERE name NOT IN ('Pokhara', 'Chitwan National Park', 'Lumbini', 'Annapurna Circuit', 'Annapurna Base Camp (ABC)') ORDER BY name";
            $dest_result = mysqli_query($conn, $dest_query);
            
            if ($dest_result && mysqli_num_rows($dest_result) > 0) {
                while ($dest = mysqli_fetch_assoc($dest_result)) {
                    echo '<option value="' . htmlspecialchars($dest['name']) . '">' . htmlspecialchars($dest['name']) . '</option>';
                }
            }
            ?>
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
        <!-- Results will be displayed here -->
      </div>
    </div>
  </div>

  <!-- Explore Section - THIS IS THE KEY PART -->
  <section class="explore-section">
    <h2>Explore Nepal</h2>
    <p>Discover the breathtaking beauty and spiritual richness of Nepal's most iconic destinations</p>

    <div class="card-container">
      <!-- FIRST: Show ALL 4 Static Destinations (Always show these) -->
      <?php
      // Static destinations data - NEVER REMOVE THESE
      $static_destinations = [
          [
              'name' => 'Lumbini',
              'description' => 'Birthplace of Lord Buddha, UNESCO World Heritage Site',
              'price' => 'RS.21,480',
              'image' => 'images/lumbini.jpg',
              'page' => 'lumbini.html'
          ],
          [
              'name' => 'Chitwan National Park',
              'description' => 'Wildlife safari and jungle adventures',
              'price' => 'RS.35,880',
              'image' => 'images/chitwan.jpg',
              'page' => 'chitwan.html'
          ],
          [
              'name' => 'Annapurna Base Camp (ABC)',
              'description' => 'Stunning mountain views and trekking paradise',
              'price' => 'RS.13,850',
              'image' => 'images/arnapurna.jpg',
              'page' => 'annapurna.html'
          ],
          [
              'name' => 'Pokhara',
              'description' => 'Stunning mountain views and trekking paradise',
              'price' => 'RS.23,880',
              'image' => 'images/pokhara.jpg',
              'page' => 'pokhara.html'
          ]
      ];
      
      // Display ALL static destinations first
      foreach ($static_destinations as $dest) {
          ?>
          <div class="card">
            <img src="<?php echo $dest['image']; ?>" alt="<?php echo htmlspecialchars($dest['name']); ?>">
            <div class="card-content">
              <h3><?php echo htmlspecialchars($dest['name']); ?></h3>
              <p><?php echo htmlspecialchars($dest['description']); ?></p>
              <p class="price">From <?php echo $dest['price']; ?></p>
              <button class="btn">
                <a href="<?php echo $dest['page']; ?>" class="view-details-btn">
                  View Details
                </a>
              </button>
            </div>
          </div>
          <?php
      }
      
      // THEN: Show admin-added destinations (as extras)
      $destinations_query = "SELECT * FROM destinations WHERE name NOT IN ('Pokhara', 'Chitwan National Park', 'Lumbini', 'Annapurna Circuit', 'Annapurna Base Camp (ABC)') ORDER BY created_at DESC";
      $destinations_result = mysqli_query($conn, $destinations_query);
      
      if ($destinations_result && mysqli_num_rows($destinations_result) > 0) {
          while ($destination = mysqli_fetch_assoc($destinations_result)) {
              ?>
              <div class="card">
                <img src="<?php echo htmlspecialchars($destination['image_url']); ?>" 
                     alt="<?php echo htmlspecialchars($destination['name']); ?>"
                     onerror="this.src='https://via.placeholder.com/400x300/004d4d/ffffff?text=<?php echo urlencode($destination['name']); ?>'">
                <div class="card-content">
                  <h3><?php echo htmlspecialchars($destination['name']); ?></h3>
                  <p><?php echo htmlspecialchars($destination['description']); ?></p>
                  <p class="price">From <?php echo htmlspecialchars($destination['price']); ?></p>
                  <button class="btn">
                    <a href="dynamic-destination.php?name=<?php echo urlencode($destination['name']); ?>" class="view-details-btn">
                      View Details
                    </a>
                  </button>
                </div>
              </div>
              <?php
          }
      }
      ?>
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
          <?php
          // Show static destinations in footer first
          $footer_static = ['Pokhara', 'Chitwan National Park', 'Lumbini', 'Annapurna Circuit'];
          foreach ($footer_static as $dest_name) {
              echo '<li>' . htmlspecialchars($dest_name) . '</li>';
          }
          
          // Then show admin-added destinations
          $footer_query = "SELECT name FROM destinations WHERE name NOT IN ('Pokhara', 'Chitwan National Park', 'Lumbini', 'Annapurna Circuit') LIMIT 4";
          $footer_result = mysqli_query($conn, $footer_query);
          if ($footer_result) {
              while ($footer_dest = mysqli_fetch_assoc($footer_result)) {
                  echo '<li>' . htmlspecialchars($footer_dest['name']) . '</li>';
              }
          }
          ?>
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
      const heroSearchInput = document.getElementById('heroSearch');
      const heroSearchBtn = document.getElementById('heroSearchBtn');
      const heroStartBtn = document.getElementById('heroStartBtn');
      const searchSuggestions = document.getElementById('searchSuggestions');
      const searchButton = document.getElementById('searchButton');
      const searchResults = document.getElementById('searchResults');
      const destinationCardsResults = document.getElementById('destinationCardsResults');
      const resultsCount = document.getElementById('resultsCount');
      
      // Define BOTH static and dynamic destinations
      const staticDestinations = {
        "Pokhara": {
          name: "Pokhara",
          price: "RS.23,880",
          page: "pokhara.html",
          description: "Stunning mountain views and trekking paradise",
          image: "images/pokhara.jpg"
        },
        "Chitwan National Park": {
          name: "Chitwan National Park",
          price: "RS.35,880",
          page: "chitwan.html",
          description: "Wildlife safari and jungle adventures",
          image: "images/chitwan.jpg"
        },
        "Lumbini": {
          name: "Lumbini",
          price: "RS.21,480",
          page: "lumbini.html",
          description: "Birthplace of Lord Buddha, UNESCO World Heritage Site",
          image: "images/lumbini.jpg"
        },
        
        "Annapurna Base Camp (ABC)": {
          name: "Annapurna Base Camp (ABC)",
          price: "RS.13,850",
          page: "annapurna.html",
          description: "Stunning mountain views and trekking paradise",
          image: "images/arnapurna.jpg"
        }
      };
      
      let allDestinations = { ...staticDestinations }; // Start with static destinations
      
      // Fetch admin-added destinations and combine with static ones
      fetch('get-destinations.php')
        .then(response => response.json())
        .then(adminDestinations => {
          // Add admin destinations to the list (excluding static ones)
          adminDestinations.forEach(dest => {
            if (!staticDestinations[dest.name]) {
              allDestinations[dest.name] = dest;
            }
          });
          
          // Update search suggestions
          updateSearchSuggestions();
          
          // Update destination dropdown
          updateDestinationDropdown();
        })
        .catch(error => {
          console.error('Error loading admin destinations:', error);
          // If fetch fails, just use static destinations
          updateSearchSuggestions();
          updateDestinationDropdown();
        });
      
      // Function to update search suggestions
      function updateSearchSuggestions() {
        searchSuggestions.innerHTML = '';
        
        Object.keys(allDestinations).forEach(destName => {
          const suggestion = document.createElement('div');
          suggestion.className = 'search-suggestion-item';
          suggestion.setAttribute('data-destination', destName);
          suggestion.innerHTML = `
            <i class="fa fa-map-marker"></i>
            <span>${destName}</span>
          `;
          
          suggestion.addEventListener('click', function() {
            const destination = this.getAttribute('data-destination');
            heroSearchInput.value = this.querySelector('span').textContent;
            searchSuggestions.style.display = 'none';
            
            // Scroll to search container
            document.querySelector('.search-container').scrollIntoView({ behavior: 'smooth' });
            
            // Set the dropdown value
            document.getElementById('destination').value = destination;
            
            // Show results after a short delay
            setTimeout(() => {
              searchButton.click();
            }, 300);
          });
          
          searchSuggestions.appendChild(suggestion);
        });
      }
      
      // Function to update destination dropdown
      function updateDestinationDropdown() {
        const destinationSelect = document.getElementById('destination');
        // Clear existing options except first one
        while (destinationSelect.options.length > 1) {
          destinationSelect.remove(1);
        }
        
        // Add all destinations
        Object.keys(allDestinations).forEach(destName => {
          const option = document.createElement('option');
          option.value = destName;
          option.textContent = destName;
          destinationSelect.appendChild(option);
        });
      }
      
      // Set minimum dates
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('startDate').min = today;
      document.getElementById('endDate').min = today;
      
      // Update end date minimum when start date changes
      document.getElementById('startDate').addEventListener('change', function() {
        document.getElementById('endDate').min = this.value;
      });
      
      // Show search suggestions when hero search input is focused
      heroSearchInput.addEventListener('focus', function() {
        searchSuggestions.style.display = 'block';
      });
      
      // Hide search suggestions when clicking outside
      document.addEventListener('click', function(event) {
        if (!heroSearchInput.contains(event.target) && !searchSuggestions.contains(event.target)) {
          searchSuggestions.style.display = 'none';
        }
      });
      
      // Handle Hero Section search button click
      heroSearchBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        const searchTerm = heroSearchInput.value.trim().toLowerCase();
        
        if (!searchTerm) {
          alert('Please enter a destination name');
          return;
        }
        
        // Scroll to search container
        document.querySelector('.search-container').scrollIntoView({ behavior: 'smooth' });
        
        // Find matching destination
        let matchedDestination = '';
        
        // Check for exact matches first
        for (const key in allDestinations) {
          const dest = allDestinations[key];
          const destName = dest.name.toLowerCase();
          
          if (destName.includes(searchTerm) || key.toLowerCase() === searchTerm) {
            matchedDestination = dest.name;
            break;
          }
        }
        
        // If no exact match, try partial matches
        if (!matchedDestination) {
          for (const key in allDestinations) {
            const dest = allDestinations[key];
            const destName = dest.name.toLowerCase();
            
            // Check for partial word matches
            const destWords = destName.split(' ');
            const searchWords = searchTerm.split(' ');
            
            for (const searchWord of searchWords) {
              for (const destWord of destWords) {
                if (destWord.startsWith(searchWord) || destWord.includes(searchWord)) {
                  matchedDestination = dest.name;
                  break;
                }
              }
              if (matchedDestination) break;
            }
            if (matchedDestination) break;
          }
        }
        
        // Set the dropdown to matched destination
        if (matchedDestination) {
          document.getElementById('destination').value = matchedDestination;
          
          // Show results after a short delay
          setTimeout(() => {
            searchButton.click();
          }, 300);
        } else {
          alert('Destination not found. Please try another search.');
          document.getElementById('destination').value = '';
        }
      });
      
      // Handle Enter key in hero search input
      heroSearchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          heroSearchBtn.click();
        }
      });
      
      // Handle Hero Section "Start Your Adventure" button click
      heroStartBtn.addEventListener('click', function() {
        document.querySelector('.search-container').scrollIntoView({ behavior: 'smooth' });
      });
      
      // Handle search button click in the search container
      searchButton.addEventListener('click', function(e) {
        e.preventDefault();
        
        const destinationName = document.getElementById('destination').value;
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        // Validate dates if provided
        if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
          alert('End date must be after start date');
          return;
        }
        
        // Validate destination
        if (!destinationName) {
          alert('Please select a destination from the list');
          return;
        }
        
        // Show results section
        searchResults.style.display = 'block';
        
        // Format dates for display
        const formattedStartDate = formatDate(startDate);
        const formattedEndDate = formatDate(endDate);
        
        // Clear previous results
        destinationCardsResults.innerHTML = '';
        
        let count = 0;
        
        // Find the selected destination
        const selectedDestination = allDestinations[destinationName];
        
        if (selectedDestination) {
          // Create destination card
          createDestinationCard(selectedDestination, formattedStartDate, formattedEndDate);
          count = 1;
        } else {
          // Destination not found in our data
          destinationCardsResults.innerHTML = `
            <div class="no-results">
              <i>✈️</i>
              <p>Destination not found. Please select a valid destination from the list.</p>
            </div>
          `;
        }
        
        // Update results count
        resultsCount.textContent = `${count} destination${count !== 1 ? 's' : ''} found`;
        
        // Scroll to results
        searchResults.scrollIntoView({ behavior: 'smooth' });
      });
      
      // Function to create destination card
      function createDestinationCard(dest, startDate, endDate) {
        const card = document.createElement('div');
        card.className = 'destination-card-result';
        
        // Use dynamic-destination.php for admin-added destinations, keep .html for static ones
        let viewDetailsLink = dest.page;
        if (!dest.page.includes('.html')) {
          viewDetailsLink = `dynamic-destination.php?name=${encodeURIComponent(dest.name)}`;
        }
        
        card.innerHTML = `
          <h3 class="card-title-result">${dest.name}</h3>
          <p class="card-dates-result">📅 ${startDate || "Any date"} - ${endDate || "Any date"}</p>
          <p>${dest.description}</p>
          <p class="card-price-result">Starting from ${dest.price}</p>
          <a href="${viewDetailsLink}" class="card-button-result">View Details</a>
        `;
        destinationCardsResults.appendChild(card);
      }
      
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