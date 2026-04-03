<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../src/Services/DdosProtection.php';
require_once __DIR__ . '/../../src/Services/SecurityService.php';

// Redirect to index.php if user is already logged in
if (isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true) {
    header("Location: ../index.php");
    exit();
}

// Include database configuration
require_once '../../config/database.php';

// Display any errors from social login
if (isset($_SESSION['error'])) {
    $socialError = $_SESSION['error'];
    unset($_SESSION['error']);
}

function generateUniqueClientId($db, $maxAttempts = 10) {
    // Get the highest current client_id starting with PAT
    $query = "SELECT client_id FROM clients WHERE client_id LIKE 'PAT%' ORDER BY LENGTH(client_id) DESC, client_id DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $nextNumber = 1; // Default starting number
    
    if($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastClientId = $row['client_id'];
        
        // Extract the number part from PAT0001 format
        if(preg_match('/PAT(\d+)/', $lastClientId, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }
    }
    
    // Try to find an available client_id
    for($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $proposedId = 'PAT' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        
        // Check if this ID already exists
        $checkQuery = "SELECT id FROM clients WHERE client_id = :client_id LIMIT 1";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(":client_id", $proposedId);
        $checkStmt->execute();
        
        if($checkStmt->rowCount() === 0) {
            // ID is available, use it
            return $proposedId;
        }
        
        // ID is taken, try next number
        $nextNumber++;
    }
    
    // If all attempts fail, use timestamp-based ID as fallback
    return 'PAT' . time() . rand(100, 999);
}

// Check if it's an AJAX request (POST with specific data)
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $birthdate = $_POST['birthdate'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $terms = isset($_POST['terms']) && $_POST['terms'] === '1' ? true : false;
    $parentalConsent = isset($_POST['parental_consent']) && $_POST['parental_consent'] === '1' ? true : false;
    $recaptchaToken = $_POST['recaptcha_token'] ?? '';
    
    // Create SecurityService
    $database = new Database();
    $db = $database->getConnection();
    $security = new SecurityService($db);

    // Verify reCAPTCHA
    if (!$security->verifyReCaptcha($recaptchaToken)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'errors' => ['general' => "Bot detection failed. Please try again."]
        ]);
        exit();
    }
    $signatureFilename = null;
    
    // Input validation
    $isValid = true;
    $errors = [];
    
    // First Name validation
    if(empty($firstName)) {
        $errors['firstName'] = "First name is required";
        $isValid = false;
    } elseif (!preg_match("/^[a-zA-Z ]*$/", $firstName)) {
        $errors['firstName'] = "Only letters and spaces allowed";
        $isValid = false;
    } elseif (strlen($firstName) < 2) {
        $errors['firstName'] = "First name must be at least 2 characters";
        $isValid = false;
    }
    
    // Last Name validation
    if(empty($lastName)) {
        $errors['lastName'] = "Last name is required";
        $isValid = false;
    } elseif (!preg_match("/^[a-zA-Z ]*$/", $lastName)) {
        $errors['lastName'] = "Only letters and spaces allowed";
        $isValid = false;
    } elseif (strlen($lastName) < 2) {
        $errors['lastName'] = "Last name must be at least 2 characters";
        $isValid = false;
    }
    
    // Birthdate validation
    if(empty($birthdate)) {
        $errors['birthdate'] = "Date of birth is required";
        $isValid = false;
    } else {
        // Calculate age
        $birthDate = new DateTime($birthdate);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        
        // Check if minor (under 18)
        $isMinor = $age < 18;
        
        // If minor, require parental consent signature
        if($isMinor) {
            if(!isset($_FILES['parental_signature']) || $_FILES['parental_signature']['error'] !== UPLOAD_ERR_OK) {
                $errors['parental_consent'] = "Parental consent signature is required for users under 18";
                $isValid = false;
            } else {
                $file = $_FILES['parental_signature'];
                $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                
                if(!in_array($file['type'], $allowedTypes)) {
                    $errors['parental_consent'] = "Only PNG, JPG, or JPEG files are allowed";
                    $isValid = false;
                } elseif($file['size'] > $maxSize) {
                    $errors['parental_consent'] = "File size must be less than 5MB";
                    $isValid = false;
                } else {
                    // File is valid, will be moved after account creation
                    $parentalConsent = true;
                }
            }
        }
    }
    
    // Phone validation
    if(empty($phone)) {
        $errors['phone'] = "Phone number is required";
        $isValid = false;
    } elseif (!preg_match("/^09[0-9]{9}$/", $phone)) {
        $errors['phone'] = "Please enter a valid Philippine mobile number (09XXXXXXXXX)";
        $isValid = false;
    }
    
    // Email validation
    if(empty($email)) {
        $errors['email'] = "Email is required";
        $isValid = false;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address";
        $isValid = false;
    }
    
    // Password validation
    if(empty($password)) {
        $errors['password'] = "Password is required";
        $isValid = false;
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters long";
        $isValid = false;
    } elseif (!preg_match("/[A-Z]/", $password)) {
        $errors['password'] = "Password must contain at least one uppercase letter";
        $isValid = false;
    } elseif (!preg_match("/[a-z]/", $password)) {
        $errors['password'] = "Password must contain at least one lowercase letter";
        $isValid = false;
    } elseif (!preg_match("/[0-9]/", $password)) {
        $errors['password'] = "Password must contain at least one number";
        $isValid = false;
    }
    
    // Confirm Password validation
    if(empty($confirmPassword)) {
        $errors['confirmPassword'] = "Please confirm your password";
        $isValid = false;
    } elseif ($password !== $confirmPassword) {
        $errors['confirmPassword'] = "Passwords do not match";
        $isValid = false;
    }
    
    // Terms validation
    if(!$terms) {
        $errors['terms'] = "You must agree to the Terms of Service and Privacy Policy";
        $isValid = false;
    }
    
    // If no validation errors, check database and create account
    if($isValid) {
        // Create database connection
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            // Check if email already exists
            $checkEmailQuery = "SELECT id FROM clients WHERE email = :email";
            $checkStmt = $db->prepare($checkEmailQuery);
            $checkStmt->bindParam(":email", $email);
            $checkStmt->execute();
            
            if($checkStmt->rowCount() > 0){
                $errors['email'] = "Email is already registered. Please use a different email.";
                $isValid = false;
            } else {
                // Verify that email and phone OTPs were verified in this session
                if (!isset($_SESSION['email_verified']) || $_SESSION['email_verified'] !== $email) {
                    $errors['general'] = "Please verify your email address first.";
                    $isValid = false;
                } elseif (!isset($_SESSION['phone_verified']) || $_SESSION['phone_verified'] !== $phone) {
                    $errors['general'] = "Please verify your phone number first.";
                    $isValid = false;
                } else {
                    // Calculate age again for database
                    $birthDate = new DateTime($birthdate);
                    $today = new DateTime();
                    $age = $today->diff($birthDate)->y;
                    $isMinor = $age < 18;
                    
                    // Generate unique client_id
                    $client_id = generateUniqueClientId($db);
                    
                    // Insert new client
                    $query = "INSERT INTO clients 
                             SET client_id=:client_id, 
                                 first_name=:first_name, last_name=:last_name, 
                                 birthdate=:birthdate, gender=:gender, phone=:phone, email=:email, 
                                 password=:password, is_minor=:is_minor, parental_consent=:parental_consent,
                                 parental_signature=:parental_signature";
                    
                    $stmt = $db->prepare($query);
                    
                    // Hash password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Handle signature upload if minor
                    if($isMinor && isset($_FILES['parental_signature']) && $_FILES['parental_signature']['error'] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($_FILES['parental_signature']['name'], PATHINFO_EXTENSION);
                        $signatureFilename = 'sig_' . time() . '_' . uniqid() . '.' . $ext;
                        $uploadDir = __DIR__ . '/../../../uploads/signatures/';
                        if(!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        move_uploaded_file($_FILES['parental_signature']['tmp_name'], $uploadDir . $signatureFilename);
                    }
                    
                    // Bind parameters
                    $stmt->bindParam(":client_id", $client_id);
                    $stmt->bindParam(":first_name", $firstName);
                    $stmt->bindParam(":last_name", $lastName);
                    $stmt->bindParam(":birthdate", $birthdate);
                    $stmt->bindParam(":gender", $gender);
                    $stmt->bindParam(":phone", $phone);
                    $stmt->bindParam(":email", $email);
                    $stmt->bindParam(":password", $hashedPassword);
                    $stmt->bindParam(":is_minor", $isMinor, PDO::PARAM_BOOL);
                    $stmt->bindParam(":parental_consent", $parentalConsent, PDO::PARAM_BOOL);
                    $stmt->bindParam(":parental_signature", $signatureFilename);
                    
                    if($stmt->execute()){
                        // Clear verification session variables
                        unset($_SESSION['email_verified']);
                        unset($_SESSION['phone_verified']);
                        
                        // Always send JSON response for AJAX
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'message' => 'Account created successfully! Your Client ID is ' . $client_id . '. You can now login.',
                            'client_id' => $client_id
                        ]);
                        exit();
                    } else {
                        $errors['general'] = "Unable to create account. Please try again.";
                        $isValid = false;
                    }
                }
            }
        } catch(PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $errors['general'] = "System error. Please try again later. Error: " . $e->getMessage();
            $isValid = false;
        }
    }
    
    // If there are errors, return JSON response
    if (!$isValid || !empty($errors)) {
        // Always send JSON response for AJAX
        header('Content-Type: application/json');
        http_response_code(400); // Bad request
        echo json_encode([
            'success' => false,
            'errors' => $errors
        ]);
        exit();
    }
}

// If it's not a POST request or not AJAX, show the HTML form

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
    <title>Cosmo Smiles Dental - Sign Up</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/signup.css">
    <?php include 'includes/recaptcha.php'; ?>
    <?php include 'includes/client-header-css.php'; ?>
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
            
            <!-- Signup Form -->
            <div class="login-form">
                <div class="form-header">
                    <h2>Create Account</h2>
                    <p>Join our dental family today</p>
                </div>
                
                <div class="general-error" id="generalError"></div>
                
                <!-- Display social login errors -->
                <?php if(isset($socialError)): ?>
                <div class="general-error" style="display: block;">
                    <?php echo htmlspecialchars($socialError); ?>
                </div>
                <?php endif; ?>
                
                <div class="success-message" id="successMessage"></div>
                
                <!-- Form Steps Indicator -->
                <div class="form-steps">
                    <div class="step active" data-step="1">
                        <div class="step-number">1</div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="step" data-step="2">
                        <div class="step-number">2</div>
                        <div class="step-label">Account</div>
                    </div>
                    <div class="step" data-step="3">
                        <div class="step-number">3</div>
                        <div class="step-label">Email OTP</div>
                    </div>
                    <div class="step" data-step="4">
                        <div class="step-number">4</div>
                        <div class="step-label">Phone OTP</div>
                    </div>
                    <div class="step" data-step="5">
                        <div class="step-number">5</div>
                        <div class="step-label">Complete</div>
                    </div>
                </div>
                
                <form id="signupForm" method="POST" enctype="multipart/form-data">
                    <!-- Step 1: Personal Information -->
                    <div class="form-step active" id="step-1">
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-user"></i>
                                Personal Information
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group" id="firstNameGroup">
                                    <input type="text" id="firstName" name="firstName" class="form-input" placeholder=" " required>
                                    <label for="firstName" class="form-label">First Name</label>
                                    <span class="error-message" id="firstNameError"></span>
                                </div>
                                
                                <div class="form-group" id="lastNameGroup">
                                    <input type="text" id="lastName" name="lastName" class="form-input" placeholder=" " required>
                                    <label for="lastName" class="form-label">Last Name</label>
                                    <span class="error-message" id="lastNameError"></span>
                                </div>
                            </div>
                            
                            <div class="form-group" id="birthdateGroup">
                                <input type="date" id="birthdate" name="birthdate" class="form-input" placeholder=" " required>
                                <label for="birthdate" class="form-label">Date of Birth</label>
                                <span class="error-message" id="birthdateError"></span>
                            </div>

                            <div class="form-group" id="genderGroup">
                                <select id="gender" name="gender" class="form-input" required>
                                    <option value="" disabled selected></option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                                <label for="gender" class="form-label">Gender</label>
                                <span class="error-message" id="genderError"></span>
                            </div>
                            
                            <div class="form-group" id="phoneGroup">
                                <input type="tel" id="phone" name="phone" class="form-input" placeholder=" " maxlength="11" required>
                                <label for="phone" class="form-label">Phone Number</label>
                                <span class="error-message" id="phoneError"></span>
                            </div>

                            <!-- Parental Consent Signature Upload (initially hidden) -->
                            <div class="parental-consent-section" id="parentalConsentGroup" style="display: none;">
                                <div class="consent-header">
                                    <i class="fas fa-child"></i>
                                    <span>Parental / Guardian Consent Required</span>
                                </div>
                                <p class="consent-description">If you are under 18 years old, parental or legal guardian consent is required to create an account. <br>By providing their signature, the parent or guardian confirms that they give permission for their child to register and use this platform.</p>
                                
                                <div class="signature-pad-container">
                                    <div class="signature-pad-wrapper">
                                        <canvas id="signatureCanvas" class="signature-canvas" width="300" height="150"></canvas>
                                    </div>
                                    <div class="signature-pad-footer">
                                        <button type="button" class="btn-clear-sig" id="clearSignatureBtn">
                                            <i class="fas fa-eraser"></i> Clear Signature
                                        </button>
                                        <span class="signature-hint">Draw your signature using your mouse or finger</span>
                                    </div>
                                </div>
                                <input type="hidden" id="parental_consent" name="parental_consent" value="0">
                                <span class="error-message" id="parentalConsentError"></span>
                            </div>
                        </div>
                        
                        <div class="form-navigation">
                            <div></div>
                            <button type="button" class="btn-next" data-next="2">Next</button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Account Details -->
                    <div class="form-step" id="step-2">
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-lock"></i>
                                Account Details
                            </div>
                            
                            <div class="form-group" id="emailGroup">
                                <input type="email" id="email" name="email" class="form-input" placeholder=" " required>
                                <label for="email" class="form-label">Email Address</label>
                                <span class="error-message" id="emailError"></span>
                            </div>
                            
                            <div class="form-group" id="passwordGroup">
                                <input type="password" id="password" name="password" class="form-input" placeholder=" " required>
                                <label for="password" class="form-label">Password</label>
                                <button type="button" class="password-toggle" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <span class="error-message" id="passwordError"></span>
                            </div>
                            
                            <div class="form-group" id="confirmPasswordGroup">
                                <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" placeholder=" " required>
                                <label for="confirmPassword" class="form-label">Confirm Password</label>
                                <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <span class="error-message" id="confirmPasswordError"></span>
                            </div>
                        
                            <div id="termsGroup">
                                <!-- Scrollable Terms & Privacy Policy -->
                                <div class="terms-scroll-container">
                                    <div class="terms-scroll-content" id="termsScrollContent">
                                        <h4>Terms of Service</h4>
                                        <p>Welcome to Cosmo Smiles Dental Clinic. By creating an account, you agree to the following terms:</p>
                                        <p><strong>1. Account Registration.</strong> You agree to provide accurate and complete information during registration. You are responsible for maintaining the confidentiality of your account credentials.</p>
                                        <p><strong>2. Appointment Booking.</strong> Appointments booked through this platform are subject to availability. Cancellations must be made at least 24 hours in advance.</p>
                                        <p><strong>3. Medical Information.</strong> Any medical information you provide will be used solely for the purpose of dental treatment and care. You consent to the collection and use of your health data by our dental professionals.</p>
                                        <p><strong>4. Payment.</strong> You agree to pay for all services rendered. Payment terms will be discussed prior to any procedure.</p>
                                        <p><strong>5. Patient Responsibilities.</strong> You agree to follow all post-treatment instructions provided by our dental professionals. Failure to do so may affect treatment outcomes.</p>
                                        <p><strong>6. Limitation of Liability.</strong> Cosmo Smiles Dental shall not be liable for any indirect, incidental, or consequential damages arising from the use of our services.</p>
                                        
                                        <h4 style="margin-top:16px;">Privacy Policy</h4>
                                        <p>Cosmo Smiles Dental Clinic is committed to protecting your personal information:</p>
                                        <p><strong>1. Data Collection.</strong> We collect personal information including your name, contact details, date of birth, and dental health records to provide quality dental care.</p>
                                        <p><strong>2. Data Usage.</strong> Your information is used to manage appointments, provide dental services, send appointment reminders, and communicate important health information.</p>
                                        <p><strong>3. Data Protection.</strong> We implement industry-standard security measures to protect your personal data. Your information is stored securely and access is restricted to authorized personnel only.</p>
                                        <p><strong>4. Data Sharing.</strong> We do not sell or share your personal information with third parties except as required by law or with your explicit consent.</p>
                                        <p><strong>5. Your Rights.</strong> You have the right to access, correct, or request deletion of your personal data. Contact our clinic to exercise these rights.</p>
                                        <p><strong>6. Data Retention.</strong> We retain your information for as long as necessary to provide our services and comply with legal requirements.</p>
                                        <p><strong>7. Cookies.</strong> Our website uses cookies to improve your experience. By using our website, you consent to the use of cookies as described in this policy.</p>
                                        <p><strong>8. Updates.</strong> We may update this privacy policy from time to time. You will be notified of any significant changes.</p>
                                    </div>
                                    <div class="terms-scroll-indicator" id="termsScrollIndicator">
                                        <i class="fas fa-arrow-down"></i> Scroll down to read all terms
                                    </div>
                                </div>
                                
                                <div class="checkbox-container" id="termsCheckboxContainer">
                                    <input type="checkbox" id="terms" name="terms" disabled>
                                    <label for="terms">I have read and agree to the <strong>Terms of Service</strong> and <strong>Privacy Policy</strong></label>
                                    <span class="error-message" id="termsError"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-navigation">
                            <button type="button" class="btn-prev" data-prev="1">Previous</button>
                            <button type="button" class="btn-next" data-next="3">Next</button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Email Verification -->
                    <div class="form-step" id="step-3">
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-envelope"></i>
                                Email Verification
                            </div>
                            
                            <div class="otp-verification-card">
                                <div class="otp-icon">
                                    <i class="fas fa-envelope-open-text"></i>
                                </div>
                                <p class="otp-instruction">We've sent a 6-digit verification code to your email address:</p>
                                <p class="otp-target" id="emailOtpTarget"></p>
                                
                                <div class="otp-input-container" id="emailOtpContainer">
                                    <input type="text" maxlength="1" class="otp-digit" data-index="0" inputmode="numeric" autocomplete="one-time-code">
                                    <input type="text" maxlength="1" class="otp-digit" data-index="1" inputmode="numeric">
                                    <input type="text" maxlength="1" class="otp-digit" data-index="2" inputmode="numeric">
                                    <input type="text" maxlength="1" class="otp-digit" data-index="3" inputmode="numeric">
                                    <input type="text" maxlength="1" class="otp-digit" data-index="4" inputmode="numeric">
                                    <input type="text" maxlength="1" class="otp-digit" data-index="5" inputmode="numeric">
                                </div>
                                
                                <span class="error-message otp-error" id="emailOtpError"></span>
                                <div class="otp-success" id="emailOtpSuccess"><i class="fas fa-check-circle"></i> Email verified successfully!</div>
                                
                                <button type="button" class="btn-verify" id="verifyEmailOtpBtn">Verify Email</button>
                                
                                <div class="otp-resend">
                                    <span id="emailResendTimer">Resend code in <strong>60s</strong></span>
                                    <button type="button" class="btn-resend" id="resendEmailOtpBtn" disabled>Resend Code</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-navigation">
                            <button type="button" class="btn-prev" data-prev="2">Previous</button>
                            <button type="button" class="btn-next" data-next="4" id="emailOtpNextBtn" disabled>Next</button>
                        </div>
                    </div>
                    
                    <!-- Step 4: Phone Verification -->
                    <div class="form-step" id="step-4">
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-mobile-alt"></i>
                                Phone Verification
                            </div>
                            
                            <div class="otp-verification-card">
                                <div class="otp-icon">
                                    <i class="fas fa-sms"></i>
                                </div>
                                <p class="otp-instruction">We've sent a 6-digit verification code via SMS to:</p>
                                <p class="otp-target" id="phoneOtpTarget"></p>
                                
                                <div class="otp-input-container" id="phoneOtpContainer">
                                    <input type="text" maxlength="1" class="otp-digit" data-index="0" inputmode="numeric" autocomplete="one-time-code">
                                    <input type="text" maxlength="1" class="otp-digit" data-index="1" inputmode="numeric">
                                    <input type="text" maxlength="1" class="otp-digit" data-index="2" inputmode="numeric">
                                    <input type="text" maxlength="1" class="otp-digit" data-index="3" inputmode="numeric">
                                    <input type="text" maxlength="1" class="otp-digit" data-index="4" inputmode="numeric">
                                    <input type="text" maxlength="1" class="otp-digit" data-index="5" inputmode="numeric">
                                </div>
                                
                                <span class="error-message otp-error" id="phoneOtpError"></span>
                                <div class="otp-success" id="phoneOtpSuccess"><i class="fas fa-check-circle"></i> Phone number verified successfully!</div>
                                
                                <button type="button" class="btn-verify" id="verifyPhoneOtpBtn">Verify Phone</button>
                                
                                <div class="otp-resend">
                                    <span id="phoneResendTimer">Resend code in <strong>60s</strong></span>
                                    <button type="button" class="btn-resend" id="resendPhoneOtpBtn" disabled>Resend Code</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-navigation">
                            <button type="button" class="btn-prev" data-prev="3">Previous</button>
                            <button type="button" class="btn-next" data-next="5" id="phoneOtpNextBtn" disabled>Next</button>
                        </div>
                    </div>
                    
                    <!-- Step 5: Confirmation -->
                    <div class="form-step" id="step-5">
                        <div class="confirmation-section">
                            <i class="fas fa-check-circle confirmation-icon"></i>
                            <h3 class="confirmation-title">Ready to Create Your Account!</h3>
                            <p class="confirmation-text">Your email and phone number have been verified. Click below to complete your registration.</p>
                            
                            <div class="verification-badges">
                                <div class="v-badge"><i class="fas fa-envelope-circle-check"></i> Email Verified</div>
                                <div class="v-badge"><i class="fas fa-phone-flip"></i> Phone Verified</div>
                            </div>
                        </div>
                        
                        <div class="form-navigation">
                            <button type="button" class="btn-prev" data-prev="4">Previous</button>
                            <button type="submit" class="signup-btn" id="submitBtn">Create Account</button>
                        </div>
                    </div>
                </form>
                
                <div class="auth-footer">
                    <p>Already have an account? <a href="login.php">Sign in here</a></p>
                </div>
                
                <!-- Back to Main Website Link in Form (shown when sidebar is hidden) -->
                <div class="form-back-to-site">
                    <a href="../index.php">
                        <i class="fas fa-arrow-left"></i> Back to Main Website
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/signup.js"></script>
</body>
</html>