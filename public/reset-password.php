<?php
// public/assets/reset-password.php
require_once __DIR__ . '/../src/Controllers/PasswordResetController.php';
require_once __DIR__ . '/../config/env.php';

$token = $_GET['token'] ?? '';
$controller = new PasswordResetController();
$verification = $controller->verifyToken($token);

$isValid = $verification['success'];
$error = !$isValid ? $verification['message'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Cosmo Smiles Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo clean_url('public/assets/css/login.css'); ?>">
</head>
<body>
    <div class="container">
        <div class="login-container" style="max-width: 800px; min-height: 550px;">
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
                    <h1>New Password</h1>
                    <p>Create a strong, unique password to secure your account.</p>
                </div>
            </div>
            
            <!-- Form -->
            <div class="login-form">
                <div class="form-header">
                    <h2>Reset Password</h2>
                    <p>Set your new account password below</p>
                </div>
                
                <div class="general-error" id="generalError" <?php if ($error) echo 'style="display:block"'; ?>>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <div class="success-message" id="successMessage"></div>
                
                <?php if ($isValid): ?>
                <form id="resetPasswordForm" method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <input type="password" class="form-input" id="password" name="password" placeholder=" " required>
                        <label for="password" class="form-label">New Password</label>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                        <span class="error-message" id="passwordError"></span>
                    </div>

                    <div class="form-group">
                        <input type="password" class="form-input" id="confirm_password" name="confirm_password" placeholder=" " required>
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <button type="button" class="password-toggle" id="toggleConfirmPassword">
                            <i class="fas fa-eye"></i>
                        </button>
                        <span class="error-message" id="confirmPasswordError"></span>
                    </div>
                    
                    <button type="submit" class="login-btn" id="submitBtn">Update Password</button>
                </form>
                <?php else: ?>
                <div class="auth-footer">
                    <p>This link may have expired. <a href="forgot-password.php">Request a new one</a></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="<?php echo clean_url('public/assets/js/reset-password.js'); ?>?v=2"></script>
</body>
</html>
