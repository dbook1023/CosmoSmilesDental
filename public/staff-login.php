<?php 
require_once __DIR__ . '/../src/Services/DdosProtection.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - Cosmo Smiles Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/staff-login.css">
    <?php include __DIR__ . '/client/includes/recaptcha.php'; ?>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <!-- Login Sidebar -->
            <div class="login-sidebar">
                <!-- Back to Main Website Link in Sidebar -->
                <div class="sidebar-back">
                    <a href="index.php">
                        <i class="fas fa-arrow-left"></i> Back to Main Website
                    </a>
                </div>

                <div class="sidebar-content">
                    <div class="sidebar-image">
                        <img src="assets/images/logo-main-white-1.png" alt="Cosmo Smiles Dental">
                    </div>
                    
                    <h1>Staff Portal</h1>
                    <p>Access your dental practice tools and patient management system</p>
                    
                    <ul class="features">
                        <li><i class="fas fa-calendar-check"></i> Appointment Scheduling</li>
                        <li><i class="fas fa-file-medical"></i> Patient Record Management</li>
                        <li><i class="fas fa-comments"></i> Patient Communication</li>
                    </ul>
                </div>
            </div>
            
            <!-- Login Form -->
            <div class="login-form">
                <div class="form-header">
                    <h2>Staff Sign In</h2>
                    <p>Enter your staff credentials to access the system</p>
                </div>
                
                <!-- General Error Message -->
                <div class="general-error" id="generalError"></div>
                
                <form id="loginForm">
                    <div class="form-group" id="staffIdGroup">
                        <input type="text" class="form-input" id="staffId" name="staff_id" placeholder=" " required>
                        <label for="staffId" class="form-label">Staff ID</label>
                        <span class="error-message" id="staffIdError"></span>
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
                                    
                    <!-- Back to Main Website Link in Form (shown when sidebar is hidden) -->
                    <div class="form-back-to-site">
                        <a href="index.php">
                            <i class="fas fa-arrow-left"></i> Back to Main Website
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/staff-login.js?v=2"></script>
</body>
</html>