<?php
ob_start();
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect to index.php if user is already logged in
if (isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true) {
    header("Location: appointments.php");
    exit();
}

// Include database configuration and security service
require_once '../../config/database.php';
require_once '../../src/Services/DdosProtection.php';
require_once '../../src/Services/SecurityService.php';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $loginValue = trim($_POST['login_value'] ?? '');
    $loginType = $_POST['login_type'] ?? 'email'; // Will be 'email' or 'client_id'
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    $ip = SecurityService::getIpAddress();
    
    // Input validation
    $isValid = true;
    $errors = [];
    
    // Validate login value
    if(empty($loginValue)) {
        $errors[$loginType] = "Email or Client ID is required";
        $isValid = false;
    } elseif ($loginType === 'email' && !filter_var($loginValue, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address";
        $isValid = false;
    } elseif ($loginType === 'client_id' && !preg_match('/^PAT\d+$/i', $loginValue)) {
        $errors['client_id'] = "Please enter a valid Client ID (e.g., PAT0001)";
        $isValid = false;
    }
    
    // Password validation
    if(empty($password)) {
        $errors['password'] = "Password is required";
        $isValid = false;
    }
    
    // If no validation errors, check database
    if($isValid) {
        // Create database connection
        try {
            $database = new Database();
            $db = $database->getConnection();
            $security = new SecurityService($db);
            $recaptchaToken = $_POST['recaptcha_token'] ?? '';
            
            // Verify reCAPTCHA
            if (!$security->verifyReCaptcha($recaptchaToken)) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'errors' => ['general' => "Bot detection failed. Please try again or contact support."]
                ]);
                exit();
            }
            
            // Check Rate Limit
            $rateLimit = $security->checkRateLimit($loginValue, $ip);
            if ($rateLimit['is_blocked']) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'errors' => ['general' => "Too many failed attempts. Please try again in {$rateLimit['wait_message']}."]
                ]);
                exit();
            }
        } catch(PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'errors' => ['general' => 'Database connection failed. Please try again later.']
            ]);
            exit();
        }
        
        // Prepare query based on login type
        if ($loginType === 'email') {
            $query = "SELECT id, client_id, first_name, last_name, email, password FROM clients WHERE email = :login_value";
        } else { // client_id
            $query = "SELECT id, client_id, first_name, last_name, email, password FROM clients WHERE client_id = :login_value";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":login_value", $loginValue);
        
        try {
            $stmt->execute();
            
            if($stmt->rowCount() > 0){
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password
                if(password_verify($password, $row['password'])){
                    // Password is correct, start session
                    $_SESSION['client_id'] = (int)$row['id']; // Numeric Primary Key
                    $_SESSION['client_client_id'] = $row['client_id']; // Varchar ID (PAT...)
                    $_SESSION['client_first_name'] = $row['first_name'];
                    $_SESSION['client_last_name'] = $row['last_name'];
                    $_SESSION['client_email'] = $row['email'];
                    $_SESSION['client_logged_in'] = true;
                    
                    // Remember me functionality
                    if($remember){
                        // Set cookie for 30 days
                        setcookie('client_login', $loginValue, time() + (30 * 24 * 60 * 60), "/");
                        setcookie('login_type', $loginType, time() + (30 * 24 * 60 * 60), "/");
                        setcookie('user_token', $row['password'], time() + (30 * 24 * 60 * 60), "/");
                    }
                    
                    // Record success
                    $security->recordAttempt($loginValue, $ip, true);

                    // Return success response for AJAX
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Login successful! Welcome back, ' . $row['first_name'] . '!',
                        'redirect' => '../index.php'
                    ]);
                    exit();
                } else {
                    $security->recordAttempt($loginValue, $ip, false);
                    $errors['password'] = "Invalid password. Please try again.";
                }
            } else {
                $security->recordAttempt($loginValue, $ip, false);
                $errors[$loginType] = $loginType === 'email' 
                    ? "No account found with this email address." 
                    : "No account found with this Client ID.";
            }
        } catch(PDOException $e) {
            error_log("Login query error: " . $e->getMessage());
            $errors['general'] = "System error. Please try again later.";
        }
    }
    
    // If there are errors, return JSON response for AJAX
    if (!empty($errors)) {
        error_log("Login errors: " . print_r($errors, true));
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'errors' => $errors
        ]);
        exit();
    }
}

// Auto-fill remember me data
$defaultLoginValue = '';
$rememberChecked = '';

$rememberChecked = '';

if(isset($_COOKIE['client_login']) && isset($_COOKIE['user_token'])){
    $defaultLoginValue = $_COOKIE['client_login'];
    $rememberChecked = "checked";
}

// Header variables
$isLoggedIn = false;
$userName = 'My Account';
$profileImage = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cosmo Smiles Dental - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/login.css">
    <?php include 'includes/client-header-css.php'; ?>
    <?php include 'includes/recaptcha.php'; ?>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <!-- Login Sidebar -->
            <div class="login-sidebar">
                <!-- Back to Main Website Link in Sidebar -->
                <div class="sidebar-back">
                    <a href="../index.php">
                        <i class="fas fa-arrow-left"></i> Back to Main Website
                    </a>
                </div>

                <div class="sidebar-content">
                    <div class="sidebar-image">
                        <img src="../assets/images/logo-main-white-1.png" alt="Cosmo Smiles Dental">
                    </div>
                    
                    <ul class="features">
                        <li><i class="fas fa-calendar-alt"></i> Easy Appointment Booking</li>
                        <li><i class="fas fa-users"></i> Patient Records Management</li>
                        <li><i class="fas fa-star"></i> Premium Dental Care</li>
                        <li><i class="fas fa-cog"></i> Personalized Experience</li>
                    </ul>
                </div>
            </div>
            
            <!-- Login Form -->
            <div class="login-form">
                <div class="form-header">
                    <h2>Welcome Back</h2>
                    <p>Sign in to your account to continue</p>
                </div>
                
                <div class="general-error" id="generalError"></div>
                <div class="success-message" id="successMessage"></div>
                
                <form id="loginForm" method="POST">
                    <div class="form-group" id="loginInputGroup">
                        <input type="text" class="form-input" id="loginInput" name="login_value" placeholder=" " 
                               value="<?php echo htmlspecialchars($defaultLoginValue); ?>" 
                               autocomplete="username" required>
                        <label for="loginInput" class="form-label">Email or Client ID</label>
                        <span class="error-message" id="loginError"></span>
                    </div>
                    
                    <div class="form-group" id="passwordGroup">
                        <input type="password" class="form-input" id="loginPassword" name="password" 
                               placeholder=" " autocomplete="current-password" required>
                        <label for="loginPassword" class="form-label">Password</label>
                        <button type="button" class="password-toggle" id="toggleLoginPassword">
                            <i class="fas fa-eye"></i>
                        </button>
                        <span class="error-message" id="passwordError"></span>
                    </div>
                    
                    <div class="form-options">
                        <div class="remember-me">
                            <input type="checkbox" id="remember" name="remember" <?php echo $rememberChecked; ?>>
                            <label for="remember">Remember me</label>
                        </div>
                        <a href="../forgot-password.php" class="forgot-password">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="login-btn" id="submitBtn">Sign In</button>
                    
                    <div class="auth-footer">
                        <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
                    </div>
                    
                    <!-- Back to Main Website Link in Form (shown when sidebar is hidden) -->
                    <div class="form-back-to-site">
                        <a href="../index.php">
                            <i class="fas fa-arrow-left"></i> Back to Main Website
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/login.js"></script>
</body>
</html>