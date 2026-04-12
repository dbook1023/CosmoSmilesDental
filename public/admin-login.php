<?php 
session_start();
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin/admin-dashboard.php");
    exit();
}
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../src/Services/DdosProtection.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Cosmo Smiles Dental</title>
    <link rel="icon" type="image/png" href="<?php echo clean_url('public/assets/images/logo1-white.png'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo clean_url('public/assets/css/admin-login.css'); ?>">
    <?php include __DIR__ . '/client/includes/recaptcha.php'; ?>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <!-- Login Sidebar -->
            <div class="login-sidebar">
                <div class="sidebar-back">
                    <a href="index.php">
                        <i class="fas fa-arrow-left"></i> Back to Main Website
                    </a>
                </div>

                <div class="sidebar-content">
                    <div class="sidebar-image">
                        <img src="<?php echo clean_url('public/assets/images/logo-main-white-1.png'); ?>" alt="Cosmo Smiles Dental">
                    </div>
                    
                    <h1>Admin Portal</h1>
                    <p>Secure access to your dental practice management system</p>
                    
                    <ul class="features">
                        <li><i class="fas fa-calendar-alt"></i> Manage Appointments</li>
                        <li><i class="fas fa-users"></i> Patient Records Management</li>
                        <li><i class="fas fa-chart-line"></i> Analytics & Reporting</li>
                        <li><i class="fas fa-cog"></i> System Configuration</li>
                    </ul>
                </div>
            </div>
            
            <!-- Login Form -->
            <div class="login-form">
                <div class="form-header">
                    <h2>Welcome Back, Admin!</h2>
                    <p>Enter your credentials to access the admin panel</p>
                </div>
                
                <!-- General Error Message -->
                <div class="general-error" id="generalError"></div>
                
                <form id="loginForm">
                    <div class="form-group" id="usernameGroup">
                        <input type="text" class="form-input" id="username" name="username" placeholder=" " required>
                        <label for="username" class="form-label">Username</label>
                        <span class="error-message" id="usernameError"></span>
                    </div>
                    
                    <div class="form-group" id="passwordGroup">
                        <input type="password" class="form-input" id="password" name="password" placeholder=" " required>
                        <label for="password" class="form-label">Password</label>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                        <span class="error-message" id="passwordError"></span>
                    </div>
                    
                    <div class="form-options">
                        <div class="remember-me">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Remember me</label>
                        </div>
                        <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="login-btn" id="submitBtn">Sign In</button>
                    
                    <div class="form-back-to-site">
                        <a href="index.php">
                            <i class="fas fa-arrow-left"></i> Back to Main Website
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?php echo clean_url('public/assets/js/admin-login.js'); ?>?v=2"></script>
</body>
</html>