<?php
// public/assets/forgot-password.php
session_start();
?>
<?php 
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../src/Services/DdosProtection.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Cosmo Smiles Dental</title>
    <link rel="icon" type="image/x-icon" href="<?php echo clean_url('public/assets/images/logo1-white.png'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo clean_url('public/assets/css/login.css'); ?>">
    <?php include 'client/includes/recaptcha.php'; ?>
</head>
<body>
    <div class="container">
        <div class="login-container" style="max-width: 800px; min-height: 500px;">
            <!-- Sidebar -->
            <div class="login-sidebar">
                <div class="sidebar-back">
                    <a href="client/login.php">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
                <div class="sidebar-content">
                    <div class="sidebar-image">
                        <img src="<?php echo clean_url('public/assets/images/logo-main-white-1.png'); ?>" alt="Cosmo Smiles Dental">
                    </div>
                    <h1 style="color: #fff; font-weight: 600; font-size: 24px;">Forgot Password</h1>
                    <p style="color: #fff; font-weight: 400; font-size: 14px;">Enter your Registered ID to receive a password reset link in your email.</p>
                </div>
            </div>
            
            <!-- Form -->
            <div class="login-form">
                <div class="form-header">
                    <h2 style="color: #000; font-weight: 700;">Verify Identity</h2>
                    <p>Enter your Client, Staff, or Admin ID</p>
                </div>
                
                <div class="general-error" id="generalError"></div>
                <div class="success-message" id="successMessage"></div>
                
                <form id="forgotPasswordForm" method="POST">
                    <div class="form-group">
                        <input type="text" class="form-input" id="identifier" name="identifier" placeholder=" " required>
                        <label for="identifier" class="form-label">Registered ID</label>
                        <span class="error-message" id="identifierError"></span>
                    </div>
                    
                    <button type="submit" class="login-btn" id="submitBtn">Send Reset Link</button>
                    
                    <div class="auth-footer">
                        <p>Remember your password? <a href="client/login.php">Sign in</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?php echo clean_url('public/assets/js/forgot-password.js'); ?>?v=2"></script>
</body>
</html>
