<?php
include("db.php");

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Check if email already exists
    $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    
    if (mysqli_num_rows($check) > 0) {
        $error = "Email already registered!";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Insert new user with plain text password
        $sql = "INSERT INTO users (fullname, email, phone, password, role) 
                VALUES ('$fullname', '$email', '$phone', '$password', 'user')";
        
        if (mysqli_query($conn, $sql)) {
            $success = "Registration successful! Redirecting to login...";
            header("refresh:2;url=signin.php");
        } else {
            $error = "Registration failed!";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sign Up - Travel System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
             font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            /* OPTION 3: Warm Light Grey */
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
            max-width: 450px;
        }
        
        .form-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 5px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .logo p {
            color: #666;
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
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .input-group input.error {
            border-color: #ff4757;
            background: #fff5f5;
        }
        
        .input-group input.success {
            border-color: #2ed573;
        }
        
        .error-message {
            color: #ff4757;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        
        .server-error {
            background: #ffebee;
            color: #c62828;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #c62828;
            font-size: 14px;
        }
        
        .server-success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #2e7d32;
            font-size: 14px;
        }
        
        .phone-wrapper {
            display: flex;
            gap: 10px;
        }
        
        .country-code {
            flex: 0 0 80px;
        }
        
        .country-code input {
            background: #e9ecef;
            font-weight: bold;
        }
        
        .phone-number {
            flex: 1;
        }
        
        .strength-meter {
            height: 5px;
            background: #e0e0e0;
            border-radius: 3px;
            margin-top: 10px;
            overflow: hidden;
        }
        
        .strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s;
        }
        
        .weak { background: #ff4757; width: 33%; }
        .medium { background: #ffa502; width: 66%; }
        .strong { background: #2ed573; width: 100%; }
        
        .password-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .match-message {
            font-size: 12px;
            margin-top: 5px;
        }
        
        .match-success { color: #2e7d32; }
        .match-error { color: #ff4757; }
        
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .submit-btn:disabled {
            background: #ccc;
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
            color: #667eea;
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
            
            .phone-wrapper {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div class="logo">
                <h1>Create Account</h1>
                <p>Join our travel community</p>
            </div>
            
            <?php if ($error != ""): ?>
                <div class="server-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success != ""): ?>
                <div class="server-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" id="signupForm">
                <div class="input-group">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" 
                           placeholder="Enter your full name" 
                           value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>"
                           required>
                    
                </div>
                
                <div class="input-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" 
                           placeholder="Enter your email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           required>
                    <div class="error-message" id="emailError">Please enter a valid email address</div>
                </div>
                
                <div class="input-group">
                    <label for="phone">Phone Number</label>
                    <div class="phone-wrapper">
                        <div class="country-code">
                            <input type="text" value="+98" readonly>
                        </div>
                        <div class="phone-number">
                            <input type="tel" id="phone" name="phone" 
                                   placeholder="9876543210" 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                   pattern="[0-9]{10}"
                                   required>
                        </div>
                    </div>
                    <div class="error-message" id="phoneError">Please enter a valid 10-digit phone number</div>
                </div>
                
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" 
                           placeholder="Create a password (min 6 characters)" 
                           minlength="6"
                           required>
                    
                    <div class="strength-meter">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="error-message" id="passwordError">Password must be at least 6 characters</div>
                </div>
                
                <div class="input-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           placeholder="Confirm your password" 
                           required>
                    <div class="match-message" id="matchMessage"></div>
                    <div class="error-message" id="confirmPasswordError">Passwords do not match</div>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    Create Account
                </button>
            </form>
            
            <div class="links">
                Already have an account? <a href="signin.php">Sign In</a>
            </div>
        </div>
    </div>

    <script>
        // Form elements
        const form = document.getElementById('signupForm');
        const fullname = document.getElementById('fullname');
        const email = document.getElementById('email');
        const phone = document.getElementById('phone');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submitBtn');
        const strengthBar = document.getElementById('strengthBar');
        
        // Error elements
        const fullnameError = document.getElementById('fullnameError');
        const emailError = document.getElementById('emailError');
        const phoneError = document.getElementById('phoneError');
        const passwordError = document.getElementById('passwordError');
        const confirmPasswordError = document.getElementById('confirmPasswordError');
        const matchMessage = document.getElementById('matchMessage');
        
        // Validation functions
        function validateFullname() {
            const value = fullname.value.trim();
            if (value.length < 3) {
                showError(fullname, fullnameError, 'Name must be at least 3 characters');
                return false;
            }
            hideError(fullname, fullnameError);
            return true;
        }
        
        function validateEmail() {
            const value = email.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                showError(email, emailError, 'Please enter a valid email address');
                return false;
            }
            hideError(email, emailError);
            return true;
        }
        
        function validatePhone() {
            const value = phone.value.trim();
            const phoneRegex = /^[0-9]{10}$/;
            if (!phoneRegex.test(value)) {
                showError(phone, phoneError, 'Please enter a valid 10-digit phone number');
                return false;
            }
            hideError(phone, phoneError);
            return true;
        }
        
        function validatePassword() {
            const value = password.value;
            if (value.length < 6) {
                showError(password, passwordError, 'Password must be at least 6 characters');
                return false;
            }
            hideError(password, passwordError);
            return true;
        }
        
        function validateConfirmPassword() {
            const passValue = password.value;
            const confirmValue = confirmPassword.value;
            
            if (confirmValue === '') {
                hideError(confirmPassword, confirmPasswordError);
                matchMessage.textContent = '';
                return false;
            }
            
            if (passValue !== confirmValue) {
                showError(confirmPassword, confirmPasswordError, 'Passwords do not match');
                matchMessage.textContent = '❌ Passwords do not match';
                matchMessage.className = 'match-message match-error';
                return false;
            } else {
                hideError(confirmPassword, confirmPasswordError);
                matchMessage.textContent = '✅ Passwords match';
                matchMessage.className = 'match-message match-success';
                return true;
            }
        }
        
        // Password strength checker
        function checkPasswordStrength() {
            const value = password.value;
            let strength = 0;
            
            if (value.length >= 6) strength++;
            if (value.length >= 8) strength++;
            if (/[A-Z]/.test(value)) strength++;
            if (/[a-z]/.test(value)) strength++;
            if (/[0-9]/.test(value)) strength++;
            if (/[^A-Za-z0-9]/.test(value)) strength++;
            
            strengthBar.className = 'strength-bar';
            if (value.length === 0) {
                strengthBar.style.width = '0%';
            } else if (strength <= 2) {
                strengthBar.classList.add('weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        }
        
        // Helper functions
        function showError(input, errorElement, message) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            input.classList.add('error');
            input.classList.remove('success');
        }
        
        function hideError(input, errorElement) {
            errorElement.style.display = 'none';
            input.classList.remove('error');
            input.classList.add('success');
        }
        
        // Real-time validation
        fullname.addEventListener('input', validateFullname);
        fullname.addEventListener('blur', validateFullname);
        
        email.addEventListener('input', validateEmail);
        email.addEventListener('blur', validateEmail);
        
        phone.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }
            validatePhone();
        });
        phone.addEventListener('blur', validatePhone);
        
        password.addEventListener('input', function() {
            validatePassword();
            checkPasswordStrength();
            validateConfirmPassword();
        });
        
        confirmPassword.addEventListener('input', validateConfirmPassword);
        
        // Form submission
        form.addEventListener('submit', function(e) {
            const isFullnameValid = validateFullname();
            const isEmailValid = validateEmail();
            const isPhoneValid = validatePhone();
            const isPasswordValid = validatePassword();
            const isConfirmPasswordValid = validateConfirmPassword();
            
            if (!isFullnameValid || !isEmailValid || !isPhoneValid || !isPasswordValid || !isConfirmPasswordValid) {
                e.preventDefault();
                return false;
            }
            
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                showError(confirmPassword, confirmPasswordError, 'Passwords do not match');
                confirmPassword.focus();
                return false;
            }
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Account...';
            return true;
        });
        
        // Enable/disable submit button
        function checkFormValidity() {
            const isFullnameValid = fullname.value.trim().length >= 3;
            const isEmailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim());
            const isPhoneValid = /^[0-9]{10}$/.test(phone.value.trim());
            const isPasswordValid = password.value.length >= 6;
            const isConfirmPasswordValid = confirmPassword.value === password.value && password.value !== '';
            
            submitBtn.disabled = !(isFullnameValid && isEmailValid && isPhoneValid && isPasswordValid && isConfirmPasswordValid);
        }
        
        // Check form validity on input
        form.addEventListener('input', checkFormValidity);
        
        // Initialize
        checkFormValidity();
        checkPasswordStrength();
        fullname.focus();
    </script>
</body>
</html>