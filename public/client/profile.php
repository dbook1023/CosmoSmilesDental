<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Controllers/LoginController.php';

// Start session and check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in - if not, redirect to login
if (!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Get user ID from session
$client_id = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;

if (!$client_id) {
    header("Location: login.php");
    exit();
}

// Database connection
$database = new Database();
$conn = $database->getConnection();

// Handle profile information update
$updateSuccess = false;
$updateMessage = '';
$updateMessageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        // Determine which section is being updated
        $updateSection = isset($_POST['update_section']) ? $_POST['update_section'] : 'personal';
        
        // First, get current user data to compare
        $current_sql = "SELECT first_name, last_name, phone, gender, 
                               address_line1, address_line2, city, state, postal_code, country
                        FROM clients 
                        WHERE id = :id";
        $current_stmt = $conn->prepare($current_sql);
        $current_stmt->bindParam(':id', $client_id);
        $current_stmt->execute();
        $current_data = $current_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Build dynamic update query based on changed fields
        $updateFields = [];
        $params = [':id' => $client_id];
        
        // Personal info fields - all are required, cannot be empty
        if (isset($_POST['first_name'])) {
            $new_value = trim($_POST['first_name']);
            if (empty($new_value)) {
                throw new Exception("First name cannot be empty.");
            }
            if ($new_value !== $current_data['first_name']) {
                $updateFields[] = "first_name = :first_name";
                $params[':first_name'] = $new_value;
            }
        }
        
        if (isset($_POST['last_name'])) {
            $new_value = trim($_POST['last_name']);
            if (empty($new_value)) {
                throw new Exception("Last name cannot be empty.");
            }
            if ($new_value !== $current_data['last_name']) {
                $updateFields[] = "last_name = :last_name";
                $params[':last_name'] = $new_value;
            }
        }
        
        if (isset($_POST['phone'])) {
            $new_value = trim($_POST['phone']);
            if (empty($new_value)) {
                throw new Exception("Phone number cannot be empty.");
            }
            // Validate phone format
            if (!preg_match('/^[0-9+\-\s()]+$/', $new_value)) {
                throw new Exception("Please enter a valid phone number.");
            }
            if ($new_value !== $current_data['phone']) {
                $updateFields[] = "phone = :phone";
                $params[':phone'] = $new_value;
            }
        }
        
        if (isset($_POST['gender'])) {
            $new_value = $_POST['gender'];
            if (empty($new_value)) {
                throw new Exception("Gender cannot be empty.");
            }
            $validGenders = ['male', 'female', 'other'];
            if (!in_array($new_value, $validGenders)) {
                throw new Exception("Invalid gender selection.");
            }
            if ($new_value !== $current_data['gender']) {
                $updateFields[] = "gender = :gender";
                $params[':gender'] = $new_value;
            }
        }
        
        // Address fields - all are optional, can be empty or null
        if (isset($_POST['address_line1'])) {
            $new_value = trim($_POST['address_line1']);
            if ($new_value !== ($current_data['address_line1'] ?? '')) {
                $updateFields[] = "address_line1 = :address_line1";
                $params[':address_line1'] = $new_value;
            }
        }
        
        if (isset($_POST['address_line2'])) {
            $new_value = trim($_POST['address_line2']);
            if ($new_value !== ($current_data['address_line2'] ?? '')) {
                $updateFields[] = "address_line2 = :address_line2";
                $params[':address_line2'] = $new_value;
            }
        }
        
        if (isset($_POST['city'])) {
            $new_value = trim($_POST['city']);
            if ($new_value !== ($current_data['city'] ?? '')) {
                $updateFields[] = "city = :city";
                $params[':city'] = $new_value;
            }
        }
        
        if (isset($_POST['state'])) {
            $new_value = trim($_POST['state']);
            if ($new_value !== ($current_data['state'] ?? '')) {
                $updateFields[] = "state = :state";
                $params[':state'] = $new_value;
            }
        }
        
        if (isset($_POST['postal_code'])) {
            $new_value = trim($_POST['postal_code']);
            // Validate postal code format if provided
            if ($new_value !== '' && !preg_match('/^[A-Za-z0-9\-\s]+$/', $new_value)) {
                throw new Exception("Please enter a valid postal code.");
            }
            if ($new_value !== ($current_data['postal_code'] ?? '')) {
                $updateFields[] = "postal_code = :postal_code";
                $params[':postal_code'] = $new_value;
            }
        }
        
        if (isset($_POST['country'])) {
            $new_value = trim($_POST['country']) !== '' ? trim($_POST['country']) : 'Philippines';
            if ($new_value !== $current_data['country']) {
                $updateFields[] = "country = :country";
                $params[':country'] = $new_value;
            }
        }
        
        // Always update the updated_at timestamp if there are changes
        if (!empty($updateFields)) {
            $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
        }
        
        // Only proceed if there are fields to update
        if (empty($updateFields)) {
            throw new Exception("No changes detected.");
        }
        
        // Build and execute update query
        $sql = "UPDATE clients SET " . implode(', ', $updateFields) . " WHERE id = :id";
        $stmt = $conn->prepare($sql);
        
        // Bind all parameters
        foreach ($params as $key => &$value) {
            $stmt->bindParam($key, $value);
        }
        
        if ($stmt->execute()) {
            // Update session data for changed fields
            if (isset($params[':first_name'])) $_SESSION['client_first_name'] = $params[':first_name'];
            if (isset($params[':last_name'])) $_SESSION['client_last_name'] = $params[':last_name'];
            if (isset($params[':phone'])) $_SESSION['client_phone'] = $params[':phone'];
            if (isset($params[':gender'])) $_SESSION['client_gender'] = $params[':gender'];
            
            $updateSuccess = true;
            $updateMessage = "Profile information updated successfully!";
            $updateMessageType = 'success';
            
            // Redirect to refresh page and show success message
            header("Location: profile.php?update=success&section=" . $updateSection);
            exit();
        } else {
            throw new Exception("Failed to update profile information.");
        }
    } catch (Exception $e) {
        $updateSuccess = false;
        $updateMessage = $e->getMessage();
        $updateMessageType = 'error';
    }
}

// Fetch latest user data from database
try {
    $sql = "SELECT first_name, last_name, email, phone, birthdate, gender, 
                   profile_image, address_line1, address_line2, city, state, postal_code, country
            FROM clients 
            WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $client_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $firstName = $user['first_name'];
        $lastName = $user['last_name'];
        $email = $user['email'];
        $phone = $user['phone'];
        $birthdate = $user['birthdate'];
        $gender = $user['gender'];
        $profile_image = isset($user['profile_image']) ? $user['profile_image'] : '';
        
        // Address fields
        $address_line1 = isset($user['address_line1']) ? $user['address_line1'] : '';
        $address_line2 = isset($user['address_line2']) ? $user['address_line2'] : '';
        $city = isset($user['city']) ? $user['city'] : '';
        $state = isset($user['state']) ? $user['state'] : '';
        $postal_code = isset($user['postal_code']) ? $user['postal_code'] : '';
        $country = isset($user['country']) ? $user['country'] : 'Philippines';
        
        // Update session
        $_SESSION['client_first_name'] = $firstName;
        $_SESSION['client_last_name'] = $lastName;
        $_SESSION['client_email'] = $email;
        $_SESSION['client_phone'] = $phone;
        $_SESSION['client_birthdate'] = $birthdate;
        $_SESSION['client_gender'] = $gender;
        $_SESSION['profile_image'] = $profile_image;
        
        $userName = trim($firstName . ' ' . $lastName);
    }
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}

// Initialize variables from session
$firstName = isset($_SESSION['client_first_name']) ? $_SESSION['client_first_name'] : '';
$lastName = isset($_SESSION['client_last_name']) ? $_SESSION['client_last_name'] : '';
$email = isset($_SESSION['client_email']) ? $_SESSION['client_email'] : '';
$phone = isset($_SESSION['client_phone']) ? $_SESSION['client_phone'] : '';
$birthdate = isset($_SESSION['client_birthdate']) ? $_SESSION['client_birthdate'] : '';
$gender = isset($_SESSION['client_gender']) ? $_SESSION['client_gender'] : '';
$profile_image = isset($_SESSION['profile_image']) ? $_SESSION['profile_image'] : '';

// Initialize address variables if not set
$address_line1 = isset($address_line1) ? $address_line1 : '';
$address_line2 = isset($address_line2) ? $address_line2 : '';
$city = isset($city) ? $city : '';
$state = isset($state) ? $state : '';
$postal_code = isset($postal_code) ? $postal_code : '';
$country = isset($country) ? $country : 'Philippines';

if (empty($userName)) {
    $userName = trim($firstName . ' ' . $lastName);
    if (empty($userName)) {
        $userName = 'My Account';
    }
}

// Handle profile image upload
$uploadSuccess = false;
$uploadMessage = '';
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
$max_file_size = 5 * 1024 * 1024; // 5MB

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    try {
        $file = $_FILES['profile_image'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Upload error: " . $file['error']);
        }
        
        if ($file['size'] > $max_file_size) {
            throw new Exception("File size too large. Maximum size is 5MB.");
        }
        
        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension']);
        
        if (!in_array($extension, $allowed_extensions)) {
            throw new Exception("Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.");
        }
        
        // Get the project root path in htdocs
        $project_root = $_SERVER['DOCUMENT_ROOT'] . '/Cosmo_Smiles_Dental_Clinic';
        $upload_dir = $project_root . '/uploads/avatar/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $unique_filename = 'avatar_' . $client_id . '_' . time() . '.' . $extension;
        $upload_path = $upload_dir . $unique_filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Save relative path
            $image_path_db = 'uploads/avatar/' . $unique_filename;
            
            // Delete old image if exists
            $check_sql = "SELECT profile_image FROM clients WHERE id = :id";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':id', $client_id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
                $old_image = isset($row['profile_image']) ? $row['profile_image'] : null;
                if ($old_image && file_exists($project_root . '/' . $old_image)) {
                    unlink($project_root . '/' . $old_image);
                }
            }
            
            // Update database
            $sql = "UPDATE clients SET profile_image = :profile_image WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':profile_image', $image_path_db);
            $stmt->bindParam(':id', $client_id);
            
            if ($stmt->execute()) {
                $_SESSION['profile_image'] = $image_path_db;
                $profile_image = $image_path_db;
                $uploadSuccess = true;
                $uploadMessage = "Profile picture updated successfully!";
                
                // Redirect to refresh page
                header("Location: profile.php?upload=success");
                exit();
            } else {
                unlink($upload_path);
                throw new Exception("Failed to update database");
            }
        } else {
            throw new Exception("Failed to move uploaded file");
        }
    } catch (Exception $e) {
        $uploadMessage = $e->getMessage();
    }
}

// Handle password change
$passwordSuccess = false;
$passwordMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        $currentPassword = $_POST['currentPassword'];
        $newPassword = $_POST['newPassword'];
        $confirmPassword = $_POST['confirmPassword'];
        
        if ($newPassword !== $confirmPassword) {
            throw new Exception("New passwords do not match");
        }
        
        if (strlen($newPassword) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }
        
        if (!preg_match('/[A-Z]/', $newPassword)) {
            throw new Exception("Password must contain at least one uppercase letter");
        }
        
        if (!preg_match('/[0-9]/', $newPassword)) {
            throw new Exception("Password must contain at least one number");
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            throw new Exception("Password must contain at least one special character");
        }
        
        $sql = "SELECT password FROM clients WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $client_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $hashedPassword = $row['password'];
            
            if (!password_verify($currentPassword, $hashedPassword)) {
                throw new Exception("Current password is incorrect");
            }
            
            if (password_verify($newPassword, $hashedPassword)) {
                throw new Exception("New password cannot be the same as current password");
            }
            
            $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $sql = "UPDATE clients SET password = :password WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':password', $newHashedPassword);
            $stmt->bindParam(':id', $client_id);
            
            if ($stmt->execute()) {
                $passwordSuccess = true;
                $passwordMessage = "Password updated successfully!";
            } else {
                throw new Exception("Failed to update password");
            }
        } else {
            throw new Exception("User not found");
        }
    } catch (Exception $e) {
        $passwordMessage = $e->getMessage();
    }
}

$isLoggedIn = isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true;

// Set profile image for header
$profileImage = $profile_image;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile Settings - Cosmo Smiles Dental</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/profile.css">
  <?php include 'includes/client-header-css.php'; ?>
</head>

<body>
    <?php 
    $baseDir = '../'; 
    include 'includes/client-header.php'; 
    ?>

  <!-- Profile Settings -->
  <section class="profile-settings">
    <div class="container">
      <div id="messageBoxContainer"></div>
      
      <div class="profile-header">
        <div class="header-content">
          <h1 class="profile-title">Profile Settings</h1>
          <p class="profile-subtitle">Manage your account information and settings</p>
        </div>
        <div class="profile-avatar">
          <div class="avatar">
            <?php 
            if(!empty($profile_image) && trim($profile_image) !== '' && $profile_image !== 'NULL'): 
                $image_path = $baseDir . '../' . $profile_image;
                $timestamp = time();
            ?>
                <img src="<?php echo htmlspecialchars($image_path); ?>?t=<?php echo $timestamp; ?>" 
                     id="avatarImage" 
                     alt="Profile Picture"
                     style="width: 100%; height: 100%; object-fit: cover;"
                     onerror="this.style.display='none'; document.getElementById('avatarIcon').style.display='flex';">
                <i class="fas fa-user" id="avatarIcon" style="display: none;"></i>
            <?php else: ?>
                <img src="" alt="" id="avatarImage" style="display: none;">
                <i class="fas fa-user" id="avatarIcon"></i>
            <?php endif; ?>
          </div>
          <button class="avatar-edit" id="avatarEditBtn">
            <i class="fas fa-camera"></i>
          </button>
        </div>
      </div>

      <div class="profile-content">
        <div class="profile-sidebar">
          <div class="sidebar-nav">
            <button class="sidebar-item active" data-tab="personal">
              <i class="fas fa-user"></i>
              <span>Personal Info</span>
            </button>
            <button class="sidebar-item" data-tab="address">
              <i class="fas fa-home"></i>
              <span>Address</span>
            </button>
            <button class="sidebar-item" data-tab="account">
              <i class="fas fa-lock"></i>
              <span>Account Security</span>
            </button>
          </div>
        </div>

        <div class="profile-main">
          <!-- Personal Info Tab -->
          <div class="tab-content active" id="personal-tab">
            <div class="tab-header">
              <h2>Personal Information</h2>
              <p>Update your personal details (all fields are required)</p>
            </div>

            <form method="POST" class="profile-form" id="personalInfoForm">
              <input type="hidden" name="update_profile" value="1">
              <input type="hidden" name="update_section" value="personal">
              
              <div class="form-section">
                <h3 class="section-title">Basic Information</h3>
                
                <div class="form-row">
                  <div class="form-group">
                    <input type="text" id="first_name" name="first_name" class="form-control" 
                           placeholder="First Name" value="<?php echo htmlspecialchars($firstName); ?>"
                           data-original="<?php echo htmlspecialchars($firstName); ?>" 
                           autocomplete="given-name" required>
                    <label for="first_name">First Name *</label>
                    <span class="field-hint">Current: <?php echo htmlspecialchars($firstName); ?></span>
                    <span class="error" id="firstNameError"></span>
                  </div>
                  
                  <div class="form-group">
                    <input type="text" id="last_name" name="last_name" class="form-control" 
                           placeholder="Last Name" value="<?php echo htmlspecialchars($lastName); ?>"
                           data-original="<?php echo htmlspecialchars($lastName); ?>" 
                           autocomplete="family-name" required>
                    <label for="last_name">Last Name *</label>
                    <span class="field-hint">Current: <?php echo htmlspecialchars($lastName); ?></span>
                    <span class="error" id="lastNameError"></span>
                  </div>
                </div>
                
                <div class="form-group">
                  <input type="email" id="email" class="form-control" 
                         placeholder="Email" value="<?php echo htmlspecialchars($email); ?>" 
                         autocomplete="email" readonly disabled>
                  <label for="email">Email (cannot be changed)</label>
                </div>
                
                <div class="form-group">
                  <input type="tel" id="phone" name="phone" class="form-control" 
                         placeholder="Phone Number" value="<?php echo htmlspecialchars($phone); ?>"
                         data-original="<?php echo htmlspecialchars($phone); ?>" 
                         autocomplete="tel" required>
                  <label for="phone">Phone Number *</label>
                  <span class="field-hint">Current: <?php echo htmlspecialchars($phone); ?></span>
                  <span class="error" id="phoneError"></span>
                </div>
              </div>
              
              <div class="form-section">
                <h3 class="section-title">Additional Information</h3>
                
                <div class="form-row">
                  <div class="form-group">
                    <input type="date" id="birthdate" class="form-control" 
                           placeholder="Birthdate" value="<?php echo htmlspecialchars($birthdate); ?>" readonly disabled>
                    <label for="birthdate">Birthdate</label>
                  </div>
                  
                  <div class="form-group">
                    <select id="gender" name="gender" class="form-control" data-original="<?php echo htmlspecialchars($gender); ?>" required>
                        <option value="" disabled <?php echo empty($gender) ? 'selected' : ''; ?>>Select Gender</option>
                        <option value="male" <?php echo ($gender == 'male') ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($gender == 'female') ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo ($gender == 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                    <label for="gender">Gender *</label>
                    <span class="field-hint">Current: <?php echo ucfirst($gender) ?: 'Not set'; ?></span>
                  </div>
                </div>
              </div>

              <div class="form-actions">
                <button type="button" class="form-btn form-btn-outline" id="cancelPersonalBtn">Cancel</button>
                <button type="button" class="form-btn" id="updatePersonalBtn">Update Selected Fields</button>
              </div>
            </form>
          </div>

          <!-- Address Tab -->
          <div class="tab-content" id="address-tab">
            <div class="tab-header">
              <h2>Address Information</h2>
              <p>Manage your home address (all fields are optional)</p>
            </div>

            <form method="POST" class="profile-form" id="addressForm">
              <input type="hidden" name="update_profile" value="1">
              <input type="hidden" name="update_section" value="address">
              
              <div class="form-section">
                <h3 class="section-title">Home Address</h3>
                
                <div class="form-group">
                  <input type="text" id="address_line1" name="address_line1" class="form-control" 
                         placeholder="Address Line 1" value="<?php echo htmlspecialchars($address_line1); ?>"
                         data-original="<?php echo htmlspecialchars($address_line1); ?>"
                         autocomplete="address-line1">
                  <label for="address_line1">Address Line 1</label>
                  <?php if(!empty($address_line1)): ?>
                    <span class="field-hint">Current: <?php echo htmlspecialchars($address_line1); ?></span>
                  <?php endif; ?>
                </div>
                
                <div class="form-group">
                  <input type="text" id="address_line2" name="address_line2" class="form-control" 
                         placeholder="Address Line 2" value="<?php echo htmlspecialchars($address_line2); ?>"
                         data-original="<?php echo htmlspecialchars($address_line2); ?>"
                         autocomplete="address-line2">
                  <label for="address_line2">Address Line 2 (Optional)</label>
                  <?php if(!empty($address_line2)): ?>
                    <span class="field-hint">Current: <?php echo htmlspecialchars($address_line2); ?></span>
                  <?php endif; ?>
                </div>
                
                <div class="form-row">
                  <div class="form-group">
                    <input type="text" id="city" name="city" class="form-control" 
                           placeholder="City" value="<?php echo htmlspecialchars($city); ?>"
                           data-original="<?php echo htmlspecialchars($city); ?>"
                           autocomplete="address-level2">
                    <label for="city">City</label>
                    <?php if(!empty($city)): ?>
                      <span class="field-hint">Current: <?php echo htmlspecialchars($city); ?></span>
                    <?php endif; ?>
                  </div>
                  
                  <div class="form-group">
                    <input type="text" id="state" name="state" class="form-control" 
                           placeholder="State/Province" value="<?php echo htmlspecialchars($state); ?>"
                           data-original="<?php echo htmlspecialchars($state); ?>"
                           autocomplete="address-level1">
                    <label for="state">State/Province</label>
                    <?php if(!empty($state)): ?>
                      <span class="field-hint">Current: <?php echo htmlspecialchars($state); ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                
                <div class="form-row">
                  <div class="form-group">
                    <input type="text" id="postal_code" name="postal_code" class="form-control" 
                           placeholder="Postal Code" value="<?php echo htmlspecialchars($postal_code); ?>"
                           data-original="<?php echo htmlspecialchars($postal_code); ?>"
                           autocomplete="postal-code">
                    <label for="postal_code">Postal Code</label>
                    <?php if(!empty($postal_code)): ?>
                      <span class="field-hint">Current: <?php echo htmlspecialchars($postal_code); ?></span>
                    <?php endif; ?>
                    <span class="error" id="postalCodeError"></span>
                  </div>
                  
                  <div class="form-group">
                    <input type="text" id="country" name="country" class="form-control" 
                           placeholder="Country" value="<?php echo htmlspecialchars($country); ?>"
                           data-original="<?php echo htmlspecialchars($country); ?>"
                           autocomplete="country-name">
                    <label for="country">Country</label>
                    <?php if(!empty($country)): ?>
                      <span class="field-hint">Current: <?php echo htmlspecialchars($country); ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="form-actions">
                <button type="button" class="form-btn form-btn-outline" id="cancelAddressBtn">Cancel</button>
                <button type="button" class="form-btn" id="updateAddressBtn">Update Address Fields</button>
              </div>
            </form>
          </div>

          <!-- Account Security Tab -->
          <div class="tab-content" id="account-tab">
            <div class="tab-header">
              <h2>Account Security</h2>
              <p>Manage your password and account security settings</p>
            </div>

            <div class="password-requirements">
              <strong>Password Requirements:</strong>
              <ul>
                <li id="req-length">At least 8 characters long</li>
                <li id="req-uppercase">At least one uppercase letter</li>
                <li id="req-number">At least one number</li>
                <li id="req-special">At least one special character</li>
                <li id="req-different">Different from current password</li>
              </ul>
            </div>

            <form method="POST" class="profile-form" id="passwordForm">
              <input type="hidden" name="change_password" value="1">
              <div class="form-section">
                <h3 class="section-title">Change Password</h3>
                
                <div class="form-group">
                  <div class="password-container">
                    <input type="password" id="currentPassword" name="currentPassword" class="form-control" placeholder="Enter your current password" autocomplete="current-password" required>
                    <label for="currentPassword">Current Password</label>
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                      <i class="fas fa-eye"></i>
                    </button>
                  </div>
                  <span class="error" id="currentPasswordError"></span>
                </div>
                
                <div class="form-group">
                  <div class="password-container">
                    <input type="password" id="newPassword" name="newPassword" class="form-control" placeholder="Enter your new password" autocomplete="new-password" required>
                    <label for="newPassword">New Password</label>
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                      <i class="fas fa-eye"></i>
                    </button>
                  </div>
                  <div class="password-strength" id="passwordStrength"></div>
                  <span class="error" id="newPasswordError"></span>
                </div>
                
                <div class="form-group">
                  <div class="password-container">
                    <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" placeholder="Confirm your new password" autocomplete="new-password" required>
                    <label for="confirmPassword">Confirm New Password</label>
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                      <i class="fas fa-eye"></i>
                    </button>
                  </div>
                  <span class="error" id="confirmPasswordError"></span>
                </div>
              </div>

              <div class="form-actions">
                <button type="button" class="form-btn form-btn-outline" id="cancelBtn">Cancel</button>
                <button type="button" class="form-btn" id="updatePasswordBtn">Update Password</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Image Upload Modal -->
  <div class="modal-overlay" id="imageUploadModal">
    <div class="modal">
      <div class="modal-header">
        <h3>Update Profile Picture</h3>
      </div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data" id="imageUploadForm">
          <input type="file" id="profileImageInput" name="profile_image" accept="image/*" required>
          <div class="preview-container" id="imagePreview"></div>
          <div class="modal-actions">
            <button type="button" class="modal-btn modal-btn-cancel" id="cancelUploadBtn">Cancel</button>
            <button type="submit" class="modal-btn modal-btn-confirm">Upload</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Personal Info Confirmation Dialog -->
  <div class="confirmation-dialog" id="personalInfoConfirmationDialog">
    <div class="dialog-box">
      <div class="dialog-icon">
        <i class="fas fa-user-edit"></i>
      </div>
      <div class="dialog-header">
        <h3>Confirm Changes</h3>
      </div>
      <div class="dialog-body" id="personalInfoDialogBody">
        Are you sure you want to update your personal information?
      </div>
      <div class="dialog-actions">
        <button type="button" class="dialog-btn cancel" id="personalInfoDialogCancelBtn">Cancel</button>
        <button type="button" class="dialog-btn confirm" id="personalInfoDialogConfirmBtn">Update Fields</button>
      </div>
    </div>
  </div>

  <!-- Address Confirmation Dialog -->
  <div class="confirmation-dialog" id="addressConfirmationDialog">
    <div class="dialog-box">
      <div class="dialog-icon">
        <i class="fas fa-home"></i>
      </div>
      <div class="dialog-header">
        <h3>Confirm Changes</h3>
      </div>
      <div class="dialog-body" id="addressDialogBody">
        Are you sure you want to update your address information?
      </div>
      <div class="dialog-actions">
        <button type="button" class="dialog-btn cancel" id="addressDialogCancelBtn">Cancel</button>
        <button type="button" class="dialog-btn confirm" id="addressDialogConfirmBtn">Update Fields</button>
      </div>
    </div>
  </div>

  <!-- Password Change Confirmation Dialog -->
  <div class="confirmation-dialog" id="passwordConfirmationDialog">
    <div class="dialog-box">
      <div class="dialog-icon">
        <i class="fas fa-key"></i>
      </div>
      <div class="dialog-header">
        <h3>Confirm Password Change</h3>
      </div>
      <div class="dialog-body">
        Are you sure you want to change your password? You will need to use your new password for future logins.
      </div>
      <div class="dialog-actions">
        <button type="button" class="dialog-btn cancel" id="dialogCancelBtn">Cancel</button>
        <button type="button" class="dialog-btn confirm" id="dialogConfirmBtn">Change Password</button>
      </div>
    </div>
  </div>

  <script src="../assets/js/profile.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($updateSuccess || isset($_GET['update']) && $_GET['update'] == 'success'): ?>
        setTimeout(function() {
            let section = '<?php echo isset($_GET['section']) ? $_GET['section'] : 'personal'; ?>';
            let message = section === 'address' ? 'Address information updated successfully!' : 'Profile information updated successfully!';
            showMessageBox('success', message);
        }, 300);
        <?php endif; ?>
        
        <?php if (!empty($updateMessage) && !$updateSuccess): ?>
        setTimeout(function() {
            showMessageBox('error', '<?php echo addslashes($updateMessage); ?>');
        }, 300);
        <?php endif; ?>
        
        <?php if ($uploadSuccess || isset($_GET['upload']) && $_GET['upload'] == 'success'): ?>
        setTimeout(function() {
            showMessageBox('success', 'Profile picture updated successfully!');
            const avatarImg = document.getElementById('avatarImage');
            if (avatarImg && avatarImg.src) {
                avatarImg.src = avatarImg.src.split('?')[0] + '?t=' + new Date().getTime();
                avatarImg.style.display = 'block';
                const avatarIcon = document.getElementById('avatarIcon');
                if (avatarIcon) {
                    avatarIcon.style.display = 'none';
                }
            }
        }, 300);
        <?php endif; ?>
        
        <?php if (!empty($uploadMessage) && !$uploadSuccess): ?>
        setTimeout(function() {
            showMessageBox('error', '<?php echo addslashes($uploadMessage); ?>');
        }, 300);
        <?php endif; ?>
        
        <?php if ($passwordSuccess): ?>
        setTimeout(function() {
            showMessageBox('success', 'Password updated successfully!');
        }, 300);
        <?php endif; ?>
        
        <?php if (!empty($passwordMessage) && !$passwordSuccess): ?>
        setTimeout(function() {
            showMessageBox('error', '<?php echo addslashes($passwordMessage); ?>');
        }, 300);
        <?php endif; ?>
    });
  </script>
</body>
</html>