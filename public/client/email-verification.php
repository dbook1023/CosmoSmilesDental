<?php
session_start();
require_once '../../config/database.php';

$message = '';
$messageType = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Find user with this token
    $query = "SELECT id, email, first_name FROM clients 
              WHERE verification_token = :token 
              AND token_expiry > NOW() 
              AND is_verified = 0";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":token", $token);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update user as verified
        $updateQuery = "UPDATE clients SET is_verified = 1, 
                        verification_token = NULL, token_expiry = NULL 
                        WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(":id", $user['id']);
        
        if ($updateStmt->execute()) {
            $message = "Email verified successfully! You can now log in.";
            $messageType = "success";
        } else {
            $message = "Verification failed. Please try again.";
            $messageType = "error";
        }
    } else {
        // Check if token exists but expired
        $expiredQuery = "SELECT id FROM clients 
                        WHERE verification_token = :token 
                        AND is_verified = 0";
        $expiredStmt = $db->prepare($expiredQuery);
        $expiredStmt->bindParam(":token", $token);
        $expiredStmt->execute();
        
        if ($expiredStmt->rowCount() > 0) {
            $message = "This verification link has expired. Tokens are only valid for 1 minute and 30 seconds. Please request a new verification email.";
            $messageType = "error";
        } else {
            $message = "Invalid verification link.";
            $messageType = "error";
        }
    }
} else {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Cosmo Smiles Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #03074f 0%, #0d5bb9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .verification-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        
        .verification-icon {
            font-size: 5rem;
            margin-bottom: 20px;
        }
        
        .success-icon {
            color: #28a745;
        }
        
        .error-icon {
            color: #dc3545;
        }
        
        h1 {
            color: #03074f;
            margin-bottom: 15px;
            font-size: 2rem;
        }
        
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            font-size: 1rem;
        }
        
        .message.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .message.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .message.warning {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #0d5bb9;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.3s ease;
            border: none;
            cursor: pointer;
            margin: 5px;
        }
        
        .btn:hover {
            background: #03074f;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .logo {
            margin-bottom: 30px;
        }
        
        .logo img {
            max-height: 80px;
            width: auto;
        }
        
        .token-info {
            background: #e7f3ff;
            border-left: 4px solid #0d5bb9;
            padding: 15px;
            margin-bottom: 25px;
            text-align: left;
            border-radius: 4px;
        }
        
        .token-info i {
            color: #0d5bb9;
            margin-right: 10px;
        }
        
        .token-info p {
            margin: 5px 0;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="logo">
            <img src="../assets/images/logo-main-white-1.png" alt="Cosmo Smiles Dental" style="filter: brightness(0) invert(1);">
        </div>
        
        <div class="verification-icon <?php echo $messageType === 'success' ? 'success-icon' : 'error-icon'; ?>">
            <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        </div>
        
        <h1>Email Verification</h1>
        
        <?php if ($messageType === 'error' && strpos($message, 'expired') !== false): ?>
        <div class="token-info">
            <p><i class="fas fa-clock"></i> <strong>Token Validity:</strong> 1 minute and 30 seconds</p>
            <p><i class="fas fa-info-circle"></i> For security reasons, verification links expire quickly. Please request a new verification email.</p>
        </div>
        <?php endif; ?>
        
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        
        <?php if ($messageType === 'success'): ?>
            <a href="login.php" class="btn">
                <i class="fas fa-sign-in-alt"></i> Go to Login
            </a>
        <?php else: ?>
            <a href="login.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
            <a href="signup.php" class="btn">
                <i class="fas fa-redo"></i> Register Again
            </a>
        <?php endif; ?>
    </div>
</body>
</html>