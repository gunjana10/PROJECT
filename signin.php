<?php
session_start();
include("db.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Fetch user by email
    $result = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        // ✅ ADD THIS: Check if user account is active
        if ($user['status'] != 'active') {
            $error = "❌ Your account is disabled. Please contact administrator.";
        }
        // Check if password matches (plain text comparison)
        else if ($password == $user['password']) {
            // Set session variables - FIXED: ADD EMAIL
            $_SESSION['id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['email'] = $user['email'];  // THIS LINE WAS MISSING
            $_SESSION['role'] = $user['role'];
            
            // Redirect based on role
            if ($user['role'] == 'admin') {
                header("Location: admin-dashboard.php");
                exit();
            } else {
                header("Location: user-dashboard.php");
                exit();
            }
        } else {
            $error = "Wrong password!";
        }
    } else {
        $error = "Email not found!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Travel System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            background-image: linear-gradient(to bottom right, #f8f9fa, #e9ecef);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 400px;
        }
        
        .form-card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group label {
            display: block;
            color: #555;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .input-group input {
            width: 100%;
            padding: 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            background: white;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #4dabf7;
            box-shadow: 0 0 0 3px rgba(77, 171, 247, 0.1);
        }
        
        .input-group input.error {
            border-color: #ff6b6b;
            background: #fff5f5;
        }
        
        .input-group input.success {
            border-color: #51cf66;
        }
        
        .error-message {
            color: #ff6b6b;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        
        .server-error {
            background: #ffe3e3;
            color: #fa5252;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #fa5252;
            font-size: 14px;
        }
        
        /* Add disabled account error style */
        .server-error.disabled {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: #4dabf7;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .submit-btn:hover {
            background: #339af0;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(77, 171, 247, 0.2);
        }
        
        .submit-btn:disabled {
            background: #adb5bd;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .links {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 14px;
        }
        
        .links a {
            color: #4dabf7;
            text-decoration: none;
            font-weight: 600;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .form-card {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div class="logo">
                <h1>Welcome Back</h1>
                <p>Sign in to your account</p>
            </div>
            
            <?php if ($error != ""): ?>
                <div class="server-error <?php echo strpos($error, 'disabled') !== false ? 'disabled' : ''; ?>">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <div class="input-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" 
                           placeholder="Enter your email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           required>
                    <div class="error-message" id="emailError">Please enter a valid email address</div>
                </div>
                
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" 
                           placeholder="Enter your password" 
                           required>
                    <div class="error-message" id="passwordError">Password is required</div>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">Sign In</button>
            </form>
            
            <div class="links">
                Don't have an account? <a href="signup.php">Create Account</a>
                <br><br>
                <a href="index.php" style="color: #666;">← Back to Home</a>
            </div>
        </div>
    </div>

    <script>
        // Form elements
        const form = document.getElementById('loginForm');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const submitBtn = document.getElementById('submitBtn');
        
        // Form validation
        function validateEmail() {
            const value = email.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!value) {
                return false;
            }
            
            if (!emailRegex.test(value)) {
                return false;
            }
            
            return true;
        }
        
        function validatePassword() {
            const value = password.value;
            
            if (!value) {
                return false;
            }
            
            return true;
        }
        
        // Form submission
        form.addEventListener('submit', function(e) {
            const isEmailValid = validateEmail();
            const isPasswordValid = validatePassword();
            
            if (!isEmailValid || !isPasswordValid) {
                e.preventDefault();
                alert('Please fill in all fields correctly');
                return false;
            }
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Signing in...';
            return true;
        });
        
        // Initialize
        email.focus();
    </script>
</body>
</html>