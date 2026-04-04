<?php 
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Controllers/SiteContentController.php';
require_once __DIR__ . '/../../src/Controllers/TestimonialController.php';
require_once __DIR__ . '/../../config/env.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

$siteContentController = new SiteContentController();
$testimonialController = new TestimonialController();

// Fetch admin info
$adminId = $_SESSION['admin_id'];
$adminData = null;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = :id");
    $stmt->execute([':id' => $adminId]);
    $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adminData) {
        $adminName = 'Dr. ' . htmlspecialchars($adminData['first_name'] . ' ' . $adminData['last_name']);
        $adminRole = ($adminData['role'] === 'admin') ? 'Administrator' : ucfirst(htmlspecialchars($adminData['role']));
        $adminStaffId = $adminData['dentist_id'];
    }
} catch (PDOException $e) {
    error_log("Error fetching admin: " . $e->getMessage());
}

$currentPage = 'settings';
$sidebarAdminName = $adminName ?? 'Administrator';
$sidebarAdminRole = $adminRole ?? 'Administrator';

// Handle AJAX Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Invalid action'];

    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($firstName) || empty($lastName) || empty($username) || empty($email)) {
             echo json_encode(['success' => false, 'message' => 'Required fields missing']);
             exit;
        }

        // Check if sensitive info changed and verify OTP if so
        $isEmailChanged = ($email !== $adminData['email']);
        $isPhoneChanged = ($phone !== ($adminData['phone'] ?? ''));

        if ($isEmailChanged || $isPhoneChanged) {
            $otp = $_POST['otp'] ?? '';
            if (empty($otp)) {
                echo json_encode(['success' => false, 'needs_otp' => true, 'message' => 'Verification code required for email/phone changes']);
                exit;
            }
            
            require_once __DIR__ . '/../../src/Controllers/OTPController.php';
            $otpCtrl = new OTPController();
            
            if ($isEmailChanged) {
                $verify = $otpCtrl->verifyEmailOTP($email, $otp);
                if (!$verify['success']) {
                    echo json_encode(['success' => false, 'message' => 'Invalid email verification code']);
                    exit;
                }
            } else if ($isPhoneChanged) {
                $verify = $otpCtrl->verifyPhoneOTP($phone, $otp);
                if (!$verify['success']) {
                    echo json_encode(['success' => false, 'message' => 'Invalid phone verification code']);
                    exit;
                }
            }
        }

        try {
            // Check if username is already taken by another admin
            $checkStmt = $conn->prepare("SELECT id FROM admin_users WHERE username = :un AND id != :id");
            $checkStmt->execute([':un' => $username, ':id' => $adminId]);
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Username is already taken']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE admin_users SET first_name = :fn, last_name = :ln, username = :un, email = :em, phone = :ph, updated_at = NOW() WHERE id = :id");
            if ($stmt->execute([':fn' => $firstName, ':ln' => $lastName, ':un' => $username, ':em' => $email, ':ph' => $phone, ':id' => $adminId])) {
                $_SESSION['admin_first_name'] = $firstName;
                $_SESSION['admin_last_name'] = $lastName;
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_email'] = $email;
                $response = ['success' => true, 'message' => 'Profile updated successfully'];
            }
        } catch (Exception $e) {
            $response['message'] = 'Update failed: ' . $e->getMessage();
        }
        echo json_encode($response);
        exit;
    } else if ($action === 'change_password') {
        $currentPass = $_POST['current_password'];
        $newPass = $_POST['new_password'];
        $confirmPass = $_POST['confirm_password'] ?? '';

        if ($newPass !== $confirmPass) {
            $response['message'] = 'New passwords do not match';
            echo json_encode($response);
            exit();
        }

        $hasUppercase = preg_match('/[A-Z]/', $newPass);
        $hasLowercase = preg_match('/[a-z]/', $newPass);
        $hasNumber = preg_match('/[0-9]/', $newPass);
        $hasSpecial = preg_match('/[^A-Za-z0-9]/', $newPass);

        if (strlen($newPass) < 8 || !$hasUppercase || !$hasLowercase || !$hasNumber || !$hasSpecial) {
            $response['message'] = 'Password does not meet all security requirements';
            echo json_encode($response);
            exit();
        }
        
        $stmt = $conn->prepare("SELECT password FROM admin_users WHERE id = :id");
        $stmt->execute([':id' => $adminId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($currentPass, $user['password'])) {
            if (password_verify($newPass, $user['password'])) {
                $response['message'] = 'New password cannot be the same as current password';
                echo json_encode($response);
                exit();
            }
            $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE admin_users SET password = :pass WHERE id = :id");
            if ($updateStmt->execute([':pass' => $hashedPass, ':id' => $adminId])) {
                $response = ['success' => true, 'message' => 'Password updated successfully'];
            }
        } else {
            $response['message'] = 'Incorrect current password';
        }
    } else if ($action === 'update_clinic') {
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($name) || empty($address) || empty($email) || empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'All clinic fields are required']);
            exit;
        }

        $siteContentController->updateContent('clinic', 'name', 'text', $name);
        $siteContentController->updateContent('clinic', 'address', 'text', $address);
        $siteContentController->updateContent('clinic', 'email', 'text', $email);
        $siteContentController->updateContent('clinic', 'phone', 'text', $phone);
        
        $response = ['success' => true, 'message' => 'Clinic information updated successfully'];
    } else if ($action === 'update_content') {
        $response = $siteContentController->processAdminContentUpdate();
    } else if ($action === 'set_featured_testimonial') {
        $feedbackId = $_POST['feedback_id'] ?? 0;
        $clientId = $_POST['client_id'] ?? '';
        $response = $testimonialController->setFeaturedTestimonial($feedbackId, $clientId);
    } else {
        $response = ['success' => true, 'message' => 'Settings updated successfully (Demo Mode)'];
    }
    echo json_encode($response);
    exit();
}

$homeContent = $siteContentController->getFlatContent('home');
$aboutContent = $siteContentController->getFlatContent('about');
$servicesContent = $siteContentController->getFlatContent('services');
$contactContent = $siteContentController->getFlatContent('contact');
$clinicInfo = array_merge([
    'name' => 'Cosmo Smiles Dental Clinic',
    'address' => '123 Dental St, Medical Plaza',
    'email' => 'info@cosmosmiles.com',
    'phone' => '09123456789'
], $siteContentController->getFlatContent('clinic'));
$groupedTestimonials = $testimonialController->getAllFeedbacksForAdmin();

// Icon options helper
function iconOptions($selected = '') {
    $icons = [
        'fas fa-tooth' => 'Tooth',
        'fas fa-teeth' => 'Teeth',
        'fas fa-teeth-open' => 'Teeth Open',
        'fas fa-user-md' => 'Doctor',
        'fas fa-user-doctor' => 'Doctor Alt',
        'fas fa-stethoscope' => 'Stethoscope',
        'fas fa-heartbeat' => 'Heartbeat',
        'fas fa-heart' => 'Heart',
        'fas fa-star' => 'Star',
        'fas fa-magic' => 'Magic Wand',
        'fas fa-crown' => 'Crown',
        'fas fa-baby' => 'Baby',
        'fas fa-shield-halved' => 'Shield',
        'fas fa-shield-alt' => 'Shield Alt',
        'fas fa-microscope' => 'Microscope',
        'fas fa-handshake' => 'Handshake',
        'fas fa-certificate' => 'Certificate',
        'fas fa-award' => 'Award',
        'fas fa-trophy' => 'Trophy',
        'fas fa-medal' => 'Medal',
        'fas fa-eye' => 'Eye / Vision',
        'fas fa-bullseye' => 'Bullseye / Mission',
        'fas fa-check-circle' => 'Check Circle',
        'fas fa-clock' => 'Clock',
        'fas fa-calendar-check' => 'Calendar Check',
        'fas fa-syringe' => 'Syringe',
        'fas fa-pills' => 'Pills',
        'fas fa-x-ray' => 'X-Ray',
        'fas fa-hospital' => 'Hospital',
        'fas fa-clinic-medical' => 'Clinic',
        'fas fa-notes-medical' => 'Medical Notes',
        'fas fa-briefcase-medical' => 'Medical Briefcase',
        'fas fa-hand-holding-medical' => 'Medical Hand',
        'fas fa-smile' => 'Smiley',
        'fas fa-laugh-beam' => 'Laugh',
        'fas fa-gem' => 'Gem',
        'fas fa-bolt' => 'Bolt',
        'fas fa-fire' => 'Fire',
        'fas fa-leaf' => 'Leaf',
        'fas fa-sun' => 'Sun',
        'fas fa-palette' => 'Palette',
    ];
    $html = '<option value="">-- Select Icon --</option>';
    foreach ($icons as $class => $label) {
        $sel = ($selected === $class) ? ' selected' : '';
        $html .= '<option value="' . $class . '"' . $sel . '>' . $label . '</option>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin-Settings - Cosmo Smiles Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <?php  include 'includes/admin-sidebar-css.php'; ?>
    <style>
        /* Essential Fonts and Variables */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap');
/* Typography Match */
        h1, h2, h3, h4 { font-family: "Inter", sans-serif; color: var(--primary); font-weight: 600; }

        /* Content Specific Styles - ENHANCED READABILITY */
        .admin-main { overflow-x: hidden; }
        .settings-grid { display: grid; grid-template-columns: 310px 1fr; gap: 30px; align-items: start; width: 100%; max-width: 100%; }
        .settings-nav-card { background: white; border-radius: 12px; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08); overflow: hidden; position: sticky; top: 100px; }
        .settings-nav-btn { width: 100%; padding: 20px; display: flex; align-items: center; gap: 15px; background: none; border: none; border-bottom: 1px solid var(--border); text-align: left; cursor: pointer; color: var(--dark); font-family: inherit; font-size: 1rem; font-weight: 600; transition: 0.3s; border-left: 5px solid transparent; }
        .settings-nav-btn:last-child { border-bottom: none; }
        .settings-nav-btn.active { background: var(--primary); color: white; border-left-color: var(--secondary); }
        .settings-nav-btn:not(.active):hover { background: var(--light-accent); color: var(--secondary); }

        .settings-panels { flex: 1; min-width: 0; } /* Allow panels to shrink */
        .settings-panel { background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06); min-height: 550px; display: none; }
        .settings-panel.active { display: block; animation: fadeIn 0.5s ease; }

        .profile-gradient-card { 
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); 
            border-radius: 15px; padding: 45px; color: white; margin-bottom: 35px; display: flex; align-items: center; gap: 30px; 
            box-shadow: 0 10px 30px rgba(3, 7, 79, 0.15);
        }
        .profile-icon-large { width: 110px; height: 110px; border-radius: 50%; background: rgba(255,255,255,0.25); display: flex; align-items: center; justify-content: center; font-size: 3rem; border: 4px solid rgba(255,255,255,0.4); }
        .profile-info-large h2 { color: white; font-size: 2.4rem; margin-bottom: 5px; text-shadow: 0 2px 4px rgba(0,0,0,0.2); font-weight: 700; }
        .profile-info-large p { font-size: 1.2rem; opacity: 1; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .profile-info-large span { display: block; margin-top: 12px; font-size: 1rem; font-weight: 500; color: rgba(255,255,255,0.9); background: rgba(0,0,0,0.1); padding: 5px 12px; border-radius: 20px; width: fit-content; }

        .detail-table { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 35px; }
        .detail-table-header { padding: 22px 25px; background: var(--light-accent); border-bottom: 1px solid var(--border); }
        .detail-table-row { display: flex; border-bottom: 1px solid var(--border); transition: background 0.2s; align-items: stretch; flex-wrap: wrap; }
        .detail-table-row:hover { background: #fafbfc; }
        .detail-table-row:last-child { border-bottom: none; }
        .detail-table-label { width: 240px; padding: 20px 25px; background: var(--light); color: var(--dark); font-weight: 700; border-right: 1px solid var(--border); display: flex; align-items: center; gap: 12px; font-size: 0.95rem; flex-shrink: 0; min-width: 0; }
        .detail-table-value { flex: 1; min-width: 0; padding: 20px 25px; font-size: 1rem; color: var(--text); }

        .form-control { width: 100%; padding: 14px 18px; border: 1.5px solid var(--border); border-radius: 8px; font-family: 'Open Sans', sans-serif; transition: 0.3s; font-size: 1rem; }
        .form-control:focus { outline: none; border-color: var(--secondary); box-shadow: 0 0 0 4px rgba(13,91,185,0.1); }
        textarea.form-control { min-height: 120px; resize: vertical; }

        .image-preview { margin-top: 15px; border-radius: 8px; max-width: 100%; max-height: 200px; border: 1px dashed var(--border); padding: 5px; }

        .btn-update { background: var(--success); color: white; border: none; padding: 15px 30px; border-radius: 8px; cursor: pointer; font-weight: 700; transition: 0.3s; font-size: 1rem; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2); display: inline-flex; align-items: center; gap: 10px; }
        .btn-update:hover { background: #218838; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(40, 167, 69, 0.3); }

        .password-reqs { background: #f1f6ff; padding: 25px; border-radius: 12px; margin-bottom: 30px; border: 1px solid var(--light-accent); }
        .req-item { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; font-size: 1rem; font-weight: 500; }
        .req-item.met { color: var(--success); }
        .req-item.unmet { color: #556677; opacity: 0.7; }
        
        .pass-group { position: relative; margin-bottom: 25px; }
        .pass-group label { display: block; margin-bottom: 8px; font-weight: 700; color: var(--dark); }
        .pass-toggle { position: absolute; right: 15px; top: 48px; background: none; border: none; color: var(--dark); cursor: pointer; font-size: 1.2rem; padding: 5px; display: flex; align-items: center; justify-content: center; opacity: 0.7; }
        .pass-toggle:hover { opacity: 1; }

        .alert { padding: 18px 25px; border-radius: 8px; margin-bottom: 30px; display: none; font-weight: 600; }
        .alert.active { display: flex; align-items: center; gap: 12px; }
        .alert-success { background: #e6ffed; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #fff5f5; color: #721c24; border: 1px solid #f5c6cb; }

        /* Testimonial specific styles */
        .testimonial-client-group { background: #f8f9fc; border: 1px solid var(--border); border-radius: 12px; margin-bottom: 25px; overflow: hidden; }
        .testimonial-client-header { background: var(--secondary); color: white; padding: 15px 20px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; }
        .testimonial-item { padding: 20px; border-bottom: 1px solid var(--border); display: flex; gap: 20px; align-items: center; background: white; transition: 0.2s; }
        .testimonial-item:last-child { border-bottom: none; }
        .testimonial-item:hover { background: #f1f5f9; }
        .testimonial-content { flex: 1; }
        .testimonial-rating { color: #f59e0b; margin-bottom: 8px; }
        .testimonial-text { font-style: italic; color: #4a5568; margin-bottom: 8px; quotes: '"' '"'; }
        .testimonial-date { font-size: 0.85rem; color: #a0aec0; }
        .testimonial-toggle { 
            position: relative; width: 60px; height: 32px; border-radius: 32px; background: #e2e8f0; 
            cursor: pointer; transition: 0.3s; flex-shrink: 0; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
        .testimonial-toggle::after {
            content: ''; position: absolute; top: 4px; left: 4px; width: 24px; height: 24px; 
            background: white; border-radius: 50%; transition: 0.3s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .testimonial-toggle.active { background: var(--success); }
        .testimonial-toggle.active::after { left: 32px; }
        
        .st-btn { 
            padding: 12px 24px; border-radius: 8px; background: #f1f5f9; color: #64748b; 
            border: 1px solid var(--border); font-weight: 600; cursor: pointer; transition: 0.3s;
            display: flex; align-items: center; gap: 10px; font-size: 0.95rem; white-space: nowrap;
        }
        .st-btn:hover { background: #e2e8f0; color: var(--primary); }
        .st-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

        .section-tabs { 
            display: flex; gap: 15px; margin-bottom: 30px; padding-bottom: 5px; 
            overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none;
        }
        .section-tabs::-webkit-scrollbar { display: none; }

        .st-panel { display: none; }
        .st-panel.active { display: block; animation: fadeIn 0.3s; }

        /* Custom Administrative Modals - UNIFIED STYLES */
        .overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(3, 7, 79, 0.6); backdrop-filter: blur(4px); 
            z-index: 1000; display: none; opacity: 0; transition: 0.3s; 
        }
        .overlay.active { display: block; opacity: 1; }

        .admin-modal { 
            position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.9); 
            background: white; padding: 40px; border-radius: 16px; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.2); z-index: 1001; 
            width: 90%; max-width: 500px; display: none; opacity: 0; 
            transition: 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); text-align: center;
        }
        .admin-modal.active { display: block; opacity: 1; transform: translate(-50%, -50%) scale(1); }
        .admin-modal-icon { 
            width: 80px; height: 80px; border-radius: 50%; 
            margin: 0 auto 25px; display: flex; align-items: center; justify-content: center; 
            font-size: 2.2rem; 
        }
        .admin-modal-success .admin-modal-icon { background: #e6ffed; color: var(--success); }
        .admin-modal-error .admin-modal-icon { background: #fff5f5; color: #e53e3e; }
        .admin-modal-confirm .admin-modal-icon { background: #fff8e6; color: #f59e0b; }
        
        .admin-modal h3 { font-size: 1.8rem; margin-bottom: 15px; font-family: "Inter", sans-serif; }
        .admin-modal p { color: #64748b; font-size: 1.1rem; line-height: 1.6; margin-bottom: 30px; }
        
        .admin-modal-actions { display: flex; gap: 15px; justify-content: center; }
        .admin-modal-btn { 
            padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; 
            border: none; font-size: 1rem; flex: 1; 
        }
        .btn-modal-confirm { background: var(--primary); color: white; }
        .btn-modal-confirm:hover { background: var(--secondary); transform: translateY(-2px); }
        .btn-modal-cancel { background: #f1f5f9; color: #64748b; }
        .btn-modal-cancel:hover { background: #e2e8f0; }

        .confirm-btns { display: flex; gap: 15px; margin-top: 20px; }
        .confirm-btns .btn { flex: 1; padding: 12px; font-weight: 700; border-radius: 8px; cursor: pointer; border: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-secondary { background: #edf2f7; color: #4a5568; }

        .modal-close { position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 1.2rem; color: #64748b; cursor: pointer; }

        /* Icon Select Dropdown */
        .icon-select-wrap { position: relative; }
        .icon-select-wrap select { padding-left: 40px; appearance: auto; }
        .icon-select-preview { 
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%); 
            width: 22px; height: 22px; display: flex; align-items: center; justify-content: center;
            color: var(--secondary); font-size: 1rem; pointer-events: none;
        }

        /* Dynamic Card Add/Remove */
        .dynamic-card-group { border: 1px solid var(--border); border-radius: 10px; margin-bottom: 15px; overflow: hidden; position: relative; }
        .dynamic-card-group .detail-table-row:first-child { border-top: none; }
        .btn-remove-card { position: absolute; top: 10px; right: 10px; background: #fee2e2; color: #dc3545; border: none; border-radius: 6px; padding: 5px 10px; cursor: pointer; font-size: 0.8rem; font-weight: 600; z-index: 2; transition: 0.2s; }
        .btn-remove-card:hover { background: #dc3545; color: white; }
        .btn-add-card { background: var(--light-accent); color: var(--secondary); border: 2px dashed var(--secondary); padding: 15px; border-radius: 10px; cursor: pointer; font-weight: 700; width: 100%; margin-top: 10px; transition: 0.2s; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-add-card:hover { background: var(--secondary); color: white; border-color: var(--secondary); }

        @media (max-width: 1024px) { 
            .settings-grid { grid-template-columns: 1fr; } 
            .settings-nav-card { position: static; margin-bottom: 20px; } 
        }
        @media (max-width: 768px) {
            .settings-panel { padding: 20px; }
            .detail-table-label { width: 100%; border-right: none; border-bottom: 1px solid var(--border); padding: 15px 20px; }
            .detail-table-value { width: 100%; padding: 15px 20px; min-width: 0; }
            .profile-gradient-card { flex-direction: column; text-align: center; padding: 25px; }
            .section-tabs { gap: 10px; justify-content: flex-start; }
            .st-btn { flex: 0 0 auto; padding: 10px 18px; }
        }
    </style>
</head>
<body>
    <?php  include 'includes/admin-header.php'; ?>
    <div class="overlay"></div>

    <div class="admin-container">
        <?php  include 'includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="dashboard-header">
                <div class="header-content">
                    <h1>System Settings</h1>
                    <p>Manage clinic information, dynamic site content, and your security credentials</p>
                </div>
                <div class="header-actions">
                    <div class="date-display">
                        <i class="fas fa-calendar-alt" style="font-size: 1.2rem; color: var(--secondary);"></i>
                        <div class="clock-content">
                            <span id="admin-date">Loading...</span>
                            <span id="admin-time">00:00:00 AM</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="settings-grid">
                    <button class="settings-nav-btn active" data-target="profile">
                        <i class="fas fa-user-circle"></i>
                        Profile Information
                    </button>
                    <button class="settings-nav-btn" data-target="info">
                        <i class="fas fa-hospital"></i>
                        Clinic Details
                    </button>
                    <button class="settings-nav-btn" data-target="content">
                        <i class="fas fa-window-maximize"></i>
                        Public Page Content
                    </button>
                    <button class="settings-nav-btn" data-target="testimonials">
                        <i class="fas fa-star"></i>
                        <span>Client Testimonials</span>
                    </button>
                    <button class="settings-nav-btn" data-target="security">
                        <i class="fas fa-shield-alt"></i>
                        <span>Security Settings</span>
                    </button>
                </div>

                <div class="settings-panels">
                    <!-- Clinic Details Panel (Unchanged logically, just UI match) -->
                    <!-- Admin Profile Information Panel (NEW) -->
                    <div class="settings-panel active" id="profile-panel">
                        <div class="profile-gradient-card">
                            <div class="profile-icon-large"><i class="fas fa-user-shield"></i></div>
                            <div class="profile-info-large">
                                <h2><?php  echo str_replace('Dr. ', '', $adminName); ?></h2>
                                <p><?php  echo $adminRole; ?></p>
                                <span>Administrator ID: #<?php  echo htmlspecialchars($adminStaffId ?: str_pad($adminId, 4, '0', STR_PAD_LEFT)); ?></span>
                            </div>
                        </div>

                        <div class="detail-table">
                            <div class="detail-table-header"><h4>Personal Details</h4></div>
                            <form id="admin-profile-form">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="detail-table-row">
                                    <div class="detail-table-label"><i class="fas fa-user-tag"></i> Login Username</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="username" class="form-control" value="<?php  echo htmlspecialchars($adminData['username'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label"><i class="fas fa-id-card"></i> First Name</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="first_name" class="form-control" value="<?php  echo htmlspecialchars($adminData['first_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label"><i class="fas fa-id-card"></i> Last Name</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="last_name" class="form-control" value="<?php  echo htmlspecialchars($adminData['last_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label"><i class="fas fa-envelope"></i> Email Address</div>
                                    <div class="detail-table-value">
                                        <input type="email" name="email" class="form-control" value="<?php  echo htmlspecialchars($adminData['email'] ?? ''); ?>" required>
                                        <small style="color: var(--secondary); margin-top: 5px; display: block;">Verification required for changes</small>
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label"><i class="fas fa-phone"></i> Phone Number</div>
                                    <div class="detail-table-value">
                                        <input type="tel" name="phone" class="form-control" value="<?php  echo htmlspecialchars($adminData['phone'] ?? ''); ?>" required>
                                        <small style="color: var(--secondary); margin-top: 5px; display: block;">Verification required for changes</small>
                                    </div>
                                </div>
                                <div style="padding: 20px; text-align: right;">
                                    <button type="submit" class="btn-update">Save Profile Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Clinic Details Panel -->
                    <div class="settings-panel" id="info-panel">
                        <div class="section-title" style="margin-bottom: 25px;">
                            <h2>Clinic Information</h2>
                            <p>Update the core identity and contact details of the clinic.</p>
                        </div>
                        <div class="detail-table">
                            <div class="detail-table-header"><h4>Clinic Identity</h4></div>
                            <form id="clinic-form">
                                <input type="hidden" name="action" value="update_clinic">
                                <div class="detail-table-row">
                                    <div class="detail-table-label"><i class="fas fa-hospital"></i> Clinic Name</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="name" class="form-control" value="<?php  echo htmlspecialchars($clinicInfo['name']); ?>" required>
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label"><i class="fas fa-map-marker-alt"></i> Address</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="address" class="form-control" value="<?php  echo htmlspecialchars($clinicInfo['address']); ?>" required>
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label"><i class="fas fa-envelope"></i> Public Email</div>
                                    <div class="detail-table-value">
                                        <input type="email" name="email" class="form-control" value="<?php  echo htmlspecialchars($clinicInfo['email']); ?>" required>
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label"><i class="fas fa-phone"></i> Public Phone</div>
                                    <div class="detail-table-value">
                                        <input type="tel" name="phone" class="form-control" value="<?php  echo htmlspecialchars($clinicInfo['phone']); ?>" required pattern="^(\+?\d{1,3}[- ]?)?\d{10,11}$">
                                    </div>
                                </div>
                                <div style="padding: 20px; text-align: right;">
                                    <button type="submit" class="btn-update">Update Clinic Identity</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Public Page Content Panel -->
                    <div class="settings-panel" id="content-panel">
                        <div class="section-title">
                            <h2>Dynamic Site Content</h2>
                            <p>Edit the copy, images, and visual elements of the public-facing pages.</p>
                        </div>
                        
                        <div class="section-tabs">
                            <button type="button" class="st-btn active" data-st="home">Home Page</button>
                            <button type="button" class="st-btn" data-st="about">About Page</button>
                            <button type="button" class="st-btn" data-st="services">Services Page</button>
                            <button type="button" class="st-btn" data-st="contact">Contact Page</button>
                        </div>

                        <!-- Home Form -->
                        <form id="content-form-home" class="st-panel active" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_content">
                            <input type="hidden" name="page" value="home">
                            <div class="detail-table">
                                <div class="detail-table-header"><h4>Hero Section</h4></div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Hero Title</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="content[hero_title]" class="form-control" value="<?php  echo htmlspecialchars($homeContent['hero_title'] ?? 'Get a Beautiful Smile'); ?>">
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Hero Subtitle</div>
                                    <div class="detail-table-value">
                                        <textarea name="content[hero_subtitle]" class="form-control"><?php  echo htmlspecialchars($homeContent['hero_subtitle'] ?? 'Professional dental care you can trust.'); ?></textarea>
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Hero Background Image</div>
                                    <div class="detail-table-value">
                                        <input type="file" name="images[hero_bg_image]" accept="image/*" class="form-control">
                                        <?php  if(!empty($homeContent['hero_bg_image'])): ?>
                                            <img src="<?php echo URL_ROOT . ltrim($homeContent['hero_bg_image'], '/'); ?>" class="image-preview" style="object-fit: cover;">
                                        <?php  endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-table" style="margin-top: 30px;">
                                <div class="detail-table-header" style="display:flex;justify-content:space-between;align-items:center;"><h4>Premium Services Highlights (Home)</h4><small style="color:#718096;">Max 6 cards</small></div>
                                <div id="promo-cards-container">
                                <?php  for($i=1; $i<=6; $i++): ?>
                                    <?php  $hasData = !empty($homeContent['promo_'.$i.'_title']); ?>
                                    <div class="dynamic-card-group" data-card-index="<?php  echo $i; ?>" style="<?php  echo (!$hasData && $i > 1) ? 'display:none;' : ''; ?>">
                                        <button type="button" class="btn-remove-card" onclick="removeCard(this)"><i class="fas fa-times"></i> Remove</button>
                                        <div class="detail-table-row">
                                            <div class="detail-table-label">Highlight Card <?php  echo $i; ?></div>
                                            <div class="detail-table-value" style="display: grid; gap: 10px;">
                                                <input type="text" name="content[promo_<?php  echo $i; ?>_title]" class="form-control" placeholder="Title" value="<?php  echo htmlspecialchars($homeContent['promo_'.$i.'_title'] ?? ''); ?>">
                                                <textarea name="content[promo_<?php  echo $i; ?>_desc]" class="form-control" placeholder="Description"><?php  echo htmlspecialchars($homeContent['promo_'.$i.'_desc'] ?? ''); ?></textarea>
                                                <div class="icon-select-wrap">
                                                    <span class="icon-select-preview"><i class="<?php  echo htmlspecialchars($homeContent['promo_'.$i.'_icon'] ?? 'fas fa-star'); ?>"></i></span>
                                                    <select name="content[promo_<?php  echo $i; ?>_icon]" class="form-control icon-dropdown" onchange="updateIconPreview(this)">
                                                        <?php  echo iconOptions($homeContent['promo_'.$i.'_icon'] ?? ''); ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php  endfor; ?>
                                </div>
                                <button type="button" class="btn-add-card" onclick="addCard('promo-cards-container', 6)"><i class="fas fa-plus"></i> Add Promo Card</button>
                            </div>

                            <div class="detail-table" style="margin-top: 30px;">
                                <div class="detail-table-header" style="display:flex;justify-content:space-between;align-items:center;"><h4>The Cosmo Advantage (Home)</h4><small style="color:#718096;">Max 6 items</small></div>
                                <div id="why-cards-container">
                                <?php  for($i=1; $i<=6; $i++): ?>
                                    <?php  $hasData = !empty($homeContent['why_'.$i.'_title']); ?>
                                    <div class="dynamic-card-group" data-card-index="<?php  echo $i; ?>" style="<?php  echo (!$hasData && $i > 1) ? 'display:none;' : ''; ?>">
                                        <button type="button" class="btn-remove-card" onclick="removeCard(this)"><i class="fas fa-times"></i> Remove</button>
                                        <div class="detail-table-row">
                                            <div class="detail-table-label">Advantage Item <?php  echo $i; ?></div>
                                            <div class="detail-table-value" style="display: grid; gap: 10px;">
                                                <input type="text" name="content[why_<?php  echo $i; ?>_title]" class="form-control" placeholder="Title" value="<?php  echo htmlspecialchars($homeContent['why_'.$i.'_title'] ?? ''); ?>">
                                                <textarea name="content[why_<?php  echo $i; ?>_desc]" class="form-control" placeholder="Description"><?php  echo htmlspecialchars($homeContent['why_'.$i.'_desc'] ?? ''); ?></textarea>
                                                <div class="icon-select-wrap">
                                                    <span class="icon-select-preview"><i class="<?php  echo htmlspecialchars($homeContent['why_'.$i.'_icon'] ?? 'fas fa-star'); ?>"></i></span>
                                                    <select name="content[why_<?php  echo $i; ?>_icon]" class="form-control icon-dropdown" onchange="updateIconPreview(this)">
                                                        <?php  echo iconOptions($homeContent['why_'.$i.'_icon'] ?? ''); ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php  endfor; ?>
                                </div>
                                <button type="button" class="btn-add-card" onclick="addCard('why-cards-container', 6)"><i class="fas fa-plus"></i> Add Reason Card</button>
                            </div>

                            <div class="detail-table" style="margin-top: 30px;">
                                <div class="detail-table-header" style="display:flex;justify-content:space-between;align-items:center;"><h4>Our Care Team (Home)</h4><small style="color:#718096;">Max 6 members</small></div>
                                <div id="team-cards-container">
                                <?php  for($i=1; $i<=6; $i++): ?>
                                    <?php  $hasData = !empty($homeContent['team_'.$i.'_name']); ?>
                                    <div class="dynamic-card-group" data-card-index="<?php  echo $i; ?>" style="<?php  echo (!$hasData && $i > 1) ? 'display:none;' : ''; ?>">
                                        <button type="button" class="btn-remove-card" onclick="removeCard(this)"><i class="fas fa-times"></i> Remove</button>
                                        <div class="detail-table-row">
                                            <div class="detail-table-label">Team Specialist <?php  echo $i; ?></div>
                                            <div class="detail-table-value" style="display: grid; gap: 10px;">
                                                <input type="text" name="content[team_<?php  echo $i; ?>_name]" class="form-control" placeholder="Full Name" value="<?php  echo htmlspecialchars($homeContent['team_'.$i.'_name'] ?? ''); ?>">
                                                <input type="text" name="content[team_<?php  echo $i; ?>_role]" class="form-control" placeholder="Role / Specialization" value="<?php  echo htmlspecialchars($homeContent['team_'.$i.'_role'] ?? ''); ?>">
                                                <textarea name="content[team_<?php  echo $i; ?>_desc]" class="form-control" placeholder="Short Bio"><?php  echo htmlspecialchars($homeContent['team_'.$i.'_desc'] ?? ''); ?></textarea>
                                                <div style="margin-top: 5px; background: #f8fafc; padding: 10px; border-radius: 8px;">
                                                    <label style="display:block; margin-bottom:5px; font-weight:600; font-size:0.85em;">Profile Image</label>
                                                    <input type="file" name="images[team_<?php  echo $i; ?>_img]" accept="image/*" class="form-control">
                                                    <?php  if (!empty($homeContent['team_'.$i.'_img'])): ?>
                                                        <img src="<?php echo URL_ROOT . ltrim($homeContent['team_'.$i.'_img'], '/'); ?>" class="image-preview" style="object-fit: cover;">
                                                    <?php  endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php  endfor; ?>
                                </div>
                                <button type="button" class="btn-add-card" onclick="addCard('team-cards-container', 6)"><i class="fas fa-plus"></i> Add Specialist Card</button>
                            </div>

                            <div class="detail-table" style="margin-top: 30px;">
                                <div class="detail-table-header"><h4>Clinic Operating Hours</h4></div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Weekday Hours</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="content[hours_week]" class="form-control" placeholder="e.g. Mon - Fri: 8:00 AM - 6:00 PM" value="<?php  echo htmlspecialchars($homeContent['hours_week'] ?? '8:00 AM - 6:00 PM'); ?>">
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Saturday Hours</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="content[hours_sat]" class="form-control" placeholder="e.g. 9:00 AM - 3:00 PM" value="<?php  echo htmlspecialchars($homeContent['hours_sat'] ?? '9:00 AM - 3:00 PM'); ?>">
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Sunday Hours</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="content[hours_sun]" class="form-control" placeholder="e.g. Closed" value="<?php  echo htmlspecialchars($homeContent['hours_sun'] ?? 'No Clinic Operations'); ?>">
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: right; margin-bottom: 20px; margin-top: 20px;">
                                <button type="submit" class="btn-update"><i class="fas fa-save"></i> Save Home Content</button>
                            </div>
                        </form>

                        <!-- About Form -->
                        <form id="content-form-about" class="st-panel" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_content">
                            <input type="hidden" name="page" value="about">
                            <div class="detail-table">
                                <div class="detail-table-header"><h4>Pioneering Modern Family Dentistry (About)</h4></div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Established Tag</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="content[about_tag]" class="form-control" placeholder="e.g. Established 2018" value="<?php  echo htmlspecialchars($aboutContent['about_tag'] ?? 'Established 2018'); ?>">
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Headline</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="content[about_title]" class="form-control" value="<?php  echo htmlspecialchars($aboutContent['about_title'] ?? 'Pioneering Modern Family Dentistry'); ?>">
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Description</div>
                                    <div class="detail-table-value">
                                        <textarea name="content[about_description]" class="form-control" style="min-height:200px;"><?php  echo htmlspecialchars($aboutContent['about_description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Who We Are Photo</div>
                                    <div class="detail-table-value">
                                        <input type="file" name="images[about_img]" accept="image/*" class="form-control">
                                        <?php  if(!empty($aboutContent['about_img'])): ?>
                                            <img src="<?php echo URL_ROOT . ltrim($aboutContent['about_img'], '/'); ?>" class="image-preview" style="object-fit: cover;">
                                        <?php  endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-table" style="margin-top: 30px;">
                                <div class="detail-table-header"><h4>Clinic Performance Stats (About)</h4></div>
                                <?php  for($i=1; $i<=2; $i++): ?>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Stat <?php  echo $i; ?></div>
                                    <div class="detail-table-value" style="display: grid; gap: 10px;">
                                        <input type="text" name="content[stat_<?php  echo $i; ?>_num]" class="form-control" placeholder="Number (e.g. 12k+)" value="<?php  echo htmlspecialchars($aboutContent['stat_'.$i.'_num'] ?? ''); ?>">
                                        <input type="text" name="content[stat_<?php  echo $i; ?>_label]" class="form-control" placeholder="Label (e.g. Transformations)" value="<?php  echo htmlspecialchars($aboutContent['stat_'.$i.'_label'] ?? ''); ?>">
                                        <div class="icon-select-wrap">
                                            <span class="icon-select-preview"><i class="<?php  echo htmlspecialchars($aboutContent['stat_'.$i.'_icon'] ?? 'fas fa-star'); ?>"></i></span>
                                            <select name="content[stat_<?php  echo $i; ?>_icon]" class="form-control icon-dropdown" onchange="updateIconPreview(this)">
                                                <?php  echo iconOptions($aboutContent['stat_'.$i.'_icon'] ?? ''); ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <?php  endfor; ?>
                            </div>

                            <div class="detail-table" style="margin-top: 30px;">
                                <div class="detail-table-header"><h4>Vision & Mission</h4></div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">The Vision</div>
                                    <div class="detail-table-value" style="display: grid; gap: 10px;">
                                        <input type="text" name="content[vision_title]" class="form-control" placeholder="Title" value="<?php  echo htmlspecialchars($aboutContent['vision_title'] ?? 'The Vision'); ?>">
                                        <textarea name="content[vision_desc]" class="form-control" placeholder="Vision Statement"><?php  echo htmlspecialchars($aboutContent['vision_desc'] ?? ''); ?></textarea>
                                        <div class="icon-select-wrap">
                                            <span class="icon-select-preview"><i class="<?php  echo htmlspecialchars($aboutContent['vision_icon'] ?? 'fas fa-eye'); ?>"></i></span>
                                            <select name="content[vision_icon]" class="form-control icon-dropdown" onchange="updateIconPreview(this)">
                                                <?php  echo iconOptions($aboutContent['vision_icon'] ?? 'fas fa-eye'); ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">The Mission</div>
                                    <div class="detail-table-value" style="display: grid; gap: 10px;">
                                        <input type="text" name="content[mission_title]" class="form-control" placeholder="Title" value="<?php  echo htmlspecialchars($aboutContent['mission_title'] ?? 'The Mission'); ?>">
                                        <textarea name="content[mission_desc]" class="form-control" placeholder="Mission Statement"><?php  echo htmlspecialchars($aboutContent['mission_desc'] ?? ''); ?></textarea>
                                        <div class="icon-select-wrap">
                                            <span class="icon-select-preview"><i class="<?php  echo htmlspecialchars($aboutContent['mission_icon'] ?? 'fas fa-bullseye'); ?>"></i></span>
                                            <select name="content[mission_icon]" class="form-control icon-dropdown" onchange="updateIconPreview(this)">
                                                <?php  echo iconOptions($aboutContent['mission_icon'] ?? 'fas fa-bullseye'); ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-table" style="margin-top: 30px;">
                                <div class="detail-table-header" style="display:flex;justify-content:space-between;align-items:center;"><h4>The Principles We Uphold (About)</h4><small style="color:#718096;">Max 6 pillars</small></div>
                                <div id="value-cards-container">
                                <?php  for($i=1; $i<=6; $i++): ?>
                                    <?php  $hasData = !empty($aboutContent['value_'.$i.'_title']); ?>
                                    <div class="dynamic-card-group" data-card-index="<?php  echo $i; ?>" style="<?php  echo (!$hasData && $i > 4) ? 'display:none;' : ''; ?>">
                                        <button type="button" class="btn-remove-card" onclick="removeCard(this)"><i class="fas fa-times"></i> Remove</button>
                                        <div class="detail-table-row">
                                            <div class="detail-table-label">Philosophy Pillar <?php  echo $i; ?></div>
                                            <div class="detail-table-value" style="display: grid; gap: 10px;">
                                                <input type="text" name="content[value_<?php  echo $i; ?>_title]" class="form-control" placeholder="Title" value="<?php  echo htmlspecialchars($aboutContent['value_'.$i.'_title'] ?? ''); ?>">
                                                <textarea name="content[value_<?php  echo $i; ?>_desc]" class="form-control" placeholder="Description"><?php  echo htmlspecialchars($aboutContent['value_'.$i.'_desc'] ?? ''); ?></textarea>
                                                <div class="icon-select-wrap">
                                                    <span class="icon-select-preview"><i class="<?php  echo htmlspecialchars($aboutContent['value_'.$i.'_icon'] ?? 'fas fa-star'); ?>"></i></span>
                                                    <select name="content[value_<?php  echo $i; ?>_icon]" class="form-control icon-dropdown" onchange="updateIconPreview(this)">
                                                        <?php  echo iconOptions($aboutContent['value_'.$i.'_icon'] ?? ''); ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php  endfor; ?>
                                </div>
                                <button type="button" class="btn-add-card" onclick="addCard('value-cards-container', 6)"><i class="fas fa-plus"></i> Add Value Pillar</button>
                            </div>

                            <div style="text-align: right; margin-bottom: 20px; margin-top: 20px;">
                                <button type="submit" class="btn-update"><i class="fas fa-save"></i> Save About Content</button>
                            </div>
                        </form>

                        <!-- Services Form -->
                        <form id="content-form-services" class="st-panel" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_content">
                            <input type="hidden" name="page" value="services">
                            <div class="detail-table">
                                <div class="detail-table-header"><h4>Services Page Headers</h4></div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Header Title</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="content[services_title]" class="form-control" value="<?php  echo htmlspecialchars($servicesContent['services_title'] ?? 'Our Premium Services'); ?>">
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Header Subtitle</div>
                                    <div class="detail-table-value">
                                        <textarea name="content[services_subtitle]" class="form-control"><?php  echo htmlspecialchars($servicesContent['services_subtitle'] ?? 'Comprehensive care for your family.'); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-table" style="margin-top: 30px;">
                                <div class="detail-table-header" style="display:flex;justify-content:space-between;align-items:center;"><h4>World-Class Dental Solutions (Services)</h4><small style="color:#718096;">Max 6 items</small></div>
                                <div id="service-cards-container">
                                <?php  for($i=1; $i<=6; $i++): ?>
                                    <?php  $hasData = !empty($servicesContent['service_'.$i.'_title']); ?>
                                    <div class="dynamic-card-group" data-card-index="<?php  echo $i; ?>" style="<?php  echo (!$hasData && $i > 3) ? 'display:none;' : ''; ?>">
                                        <button type="button" class="btn-remove-card" onclick="removeCard(this)"><i class="fas fa-times"></i> Remove</button>
                                        <div class="detail-table-row">
                                            <div class="detail-table-label">Service Link <?php  echo $i; ?></div>
                                            <div class="detail-table-value" style="display: grid; gap: 10px;">
                                                <input type="text" name="content[service_<?php  echo $i; ?>_title]" class="form-control" placeholder="Title" value="<?php  echo htmlspecialchars($servicesContent['service_'.$i.'_title'] ?? ''); ?>">
                                                <textarea name="content[service_<?php  echo $i; ?>_desc]" class="form-control" placeholder="Description"><?php  echo htmlspecialchars($servicesContent['service_'.$i.'_desc'] ?? ''); ?></textarea>
                                                <div class="icon-select-wrap">
                                                    <span class="icon-select-preview"><i class="<?php  echo htmlspecialchars($servicesContent['service_'.$i.'_icon'] ?? 'fas fa-star'); ?>"></i></span>
                                                    <select name="content[service_<?php  echo $i; ?>_icon]" class="form-control icon-dropdown" onchange="updateIconPreview(this)">
                                                        <?php  echo iconOptions($servicesContent['service_'.$i.'_icon'] ?? ''); ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php  endfor; ?>
                                </div>
                                <button type="button" class="btn-add-card" onclick="addCard('service-cards-container', 6)"><i class="fas fa-plus"></i> Add Service Card</button>
                            </div>

                            <div class="detail-table" style="margin-top: 30px;">
                                <div class="detail-table-header"><h4>Clinical Technology & Facility (Services)</h4></div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Header Title</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="content[tech_title]" class="form-control" value="<?php  echo htmlspecialchars($servicesContent['tech_title'] ?? 'Modern Clinical Logistics'); ?>">
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Header Description</div>
                                    <div class="detail-table-value">
                                        <textarea name="content[tech_desc]" class="form-control"><?php  echo htmlspecialchars($servicesContent['tech_desc'] ?? 'We invest in the highest tiers of medical technology.'); ?></textarea>
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Technology Featured Image</div>
                                    <div class="detail-table-value">
                                        <input type="file" name="images[tech_img]" class="form-control" accept="image/*">
                                        <?php  if (!empty($servicesContent['tech_img'])): ?>
                                            <img src="<?php echo URL_ROOT . ltrim($servicesContent['tech_img'], '/'); ?>" class="image-preview" style="object-fit: cover;">
                                        <?php  endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-table" style="margin-top: 30px;">
                                <div class="detail-table-header"><h4>Technology List (4 Items)</h4></div>
                                <?php  for($i=1; $i<=4; $i++): ?>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Tech Item <?php  echo $i; ?></div>
                                    <div class="detail-table-value">
                                        <input type="text" name="content[tech_list_<?php  echo $i; ?>]" class="form-control" placeholder="List item" value="<?php  echo htmlspecialchars($servicesContent['tech_list_'.$i] ?? ''); ?>">
                                    </div>
                                </div>
                                <?php  endfor; ?>
                            </div>

                            <div style="text-align: right; margin-bottom: 20px; margin-top: 20px;">
                                <button type="submit" class="btn-update"><i class="fas fa-save"></i> Save Services Content</button>
                            </div>
                        </form>
                        
                        <!-- Contact Form -->
                        <form id="content-form-contact" class="st-panel" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_content">
                            <input type="hidden" name="page" value="contact">
                            <div class="detail-table">
                                <div class="detail-table-header"><h4>Connect with Our Team (Contact)</h4></div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Contact Header</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="content[contact_title]" class="form-control" value="<?php  echo htmlspecialchars($contactContent['contact_title'] ?? 'Contact Us'); ?>">
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Contact Message</div>
                                    <div class="detail-table-value">
                                        <textarea name="content[contact_subtitle]" class="form-control"><?php  echo htmlspecialchars($contactContent['contact_subtitle'] ?? 'Reach out to schedule an appointment today.'); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-table" style="margin-top: 30px;">
                                <div class="detail-table-header"><h4>Clinic Support & Facility Details</h4></div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Facility Hours Summary</div>
                                    <div class="detail-table-value">
                                        <textarea name="content[contact_hours]" class="form-control" placeholder="Mon - Fri: 8 AM - 6 PM&#10;Sat: 9 AM - 3 PM"><?php  echo htmlspecialchars($contactContent['contact_hours'] ?? "Mon - Fri: 8 AM - 6 PM\nSat: 9 AM - 3 PM\nSun: No Clinic Operations"); ?></textarea>
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Google Maps Embed URL</div>
                                    <div class="detail-table-value">
                                        <textarea name="content[contact_map_url]" class="form-control" placeholder="https://www.google.com/maps/embed?pb=..."><?php  echo htmlspecialchars($contactContent['contact_map_url'] ?? "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3862.3364952044813!2d121.1913162758455!3d14.494056086012678!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c78876b59dd9%3A0x296b5b01ce83acbd!2sCosmo%20Smiles%20Dental%20Clinic!5e0!3m2!1sen!2sph!4v1711900000000!5m2!1sen!2sph"); ?></textarea>
                                        <small style="color: #718096; margin-top: 5px; display: block;">Paste the <strong>src</strong> attribute from the Google Maps "Embed a map" HTML.</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="detail-table" style="margin-top: 30px;">
                                <div class="detail-table-header"><h4>Social Links</h4></div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Facebook Profile URL</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="content[contact_fb]" class="form-control" value="<?php  echo htmlspecialchars($contactContent['contact_fb'] ?? 'https://www.facebook.com/profile.php?id=100063660475340'); ?>">
                                    </div>
                                </div>
                                <div class="detail-table-row">
                                    <div class="detail-table-label">Waze Location URL</div>
                                    <div class="detail-table-value">
                                        <input type="text" name="content[contact_waze]" class="form-control" value="<?php  echo htmlspecialchars($contactContent['contact_waze'] ?? 'https://www.waze.com/live-map/directions/ph/calabarzon/binangonan/cosmo-smiles-dental-clinic?to=place.ChIJ3Z21dojHlzMRvazDzgFbayk'); ?>">
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: right; margin-bottom: 20px; margin-top: 20px;">
                                <button type="submit" class="btn-update"><i class="fas fa-save"></i> Save Contact Content</button>
                            </div>
                        </form>
                    </div>

                    <!-- Client Testimonials Panel -->
                    <div class="settings-panel" id="testimonials-panel">
                        <div class="section-title" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
                            <div>
                                <h2>Featured Testimonials</h2>
                                <p>Select which client feedback to feature on the public site's testimonials section. You may only select one valid testimonial per client.</p>
                            </div>
                            <div style="display: flex; gap: 15px;">
                                <input type="text" id="test-filter-search" class="form-control" placeholder="Search client name..." style="width: 250px;">
                                <select id="test-filter-stars" class="form-control" style="width: 150px;">
                                    <option value="all">All Ratings</option>
                                    <option value="5">5 Stars</option>
                                    <option value="4">4 Stars</option>
                                    <option value="3">3 Stars</option>
                                    <option value="2">2 Stars</option>
                                    <option value="1">1 Star</option>
                                </select>
                            </div>
                        </div>

                        <div class="alert alert-success" id="test-success">
                            <i class="fas fa-check-circle"></i> <span id="test-success-msg">Testimonial updated successfully.</span>
                        </div>
                        <div class="alert alert-error" id="test-error">
                            <i class="fas fa-times-circle"></i> <span id="test-error-msg"></span>
                        </div>

                        <?php  if (empty($groupedTestimonials)): ?>
                            <div style="padding: 40px; text-align: center; color: #718096; background: #f8f9fa; border-radius: 12px; border: 1px dashed var(--border);">
                                <i class="fas fa-comment-slash" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                                <p>No client feedback has been submitted yet.</p>
                            </div>
                        <?php  else: ?>
                            <?php  foreach ($groupedTestimonials as $clientGroup): ?>
                                <div class="testimonial-client-group">
                                    <div class="testimonial-client-header">
                                        <span><i class="fas fa-user-circle"></i> <?php  echo htmlspecialchars($clientGroup['client_name']); ?></span>
                                        <span style="font-weight: 500; font-size: 0.9em; opacity: 0.8;">Client ID: <?php  echo htmlspecialchars($clientGroup['client_id']); ?></span>
                                    </div>
                                    
                                    <?php  foreach ($clientGroup['feedbacks'] as $feedback): ?>
                                        <div class="testimonial-item" data-rating="<?php  echo $feedback['rating']; ?>">
                                            <div class="testimonial-content">
                                                <div class="testimonial-rating">
                                                    <?php  
                                                    for($i = 1; $i <= 5; $i++) {
                                                        echo $i <= $feedback['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                                    }
                                                    ?>
                                                </div>
                                                <div class="testimonial-text">"<?php  echo htmlspecialchars($feedback['feedback']); ?>"</div>
                                                <div class="testimonial-date">Submitted on: <?php  echo date('M j, Y', strtotime($feedback['created_at'])); ?></div>
                                            </div>
                                            <div class="testimonial-action">
                                                <div class="testimonial-toggle <?php  echo $feedback['is_featured'] ? 'active' : ''; ?>" 
                                                     data-feedback-id="<?php  echo $feedback['id']; ?>"
                                                     data-client-id="<?php  echo htmlspecialchars($clientGroup['client_id']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    <?php  endforeach; ?>
                                    
                                    <!-- Add a "none" option logically if they want to un-feature all from this client -->
                                    <div class="testimonial-item testimonial-none" style="background:#fdfdfd;">
                                        <div class="testimonial-content" style="color:#718096; font-style: italic;">
                                            Do not feature any testimonial from this client.
                                        </div>
                                        <div class="testimonial-action">
                                            <?php  
                                            // Check if none are featured
                                            $hasFeature = false;
                                            foreach ($clientGroup['feedbacks'] as $f) {
                                                if ($f['is_featured']) $hasFeature = true;
                                            }
                                            ?>
                                            <div class="testimonial-toggle testimonial-none-toggle <?php  echo !$hasFeature ? 'active' : ''; ?>" 
                                                 data-feedback-id="0" 
                                                 data-client-id="<?php  echo htmlspecialchars($clientGroup['client_id']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php  endforeach; ?>
                        <?php  endif; ?>
                    </div>

                    <!-- Security Panel -->
                    <div class="settings-panel" id="security-panel">
                        <div class="section-title" style="margin-bottom: 30px;">
                            <h2>Update Password</h2>
                            <p>Ensure your account remains secure with a strong password</p>
                        </div>

                        <div class="alert alert-success" id="pass-success">
                            <i class="fas fa-check-circle"></i> Password updated successfully!
                        </div>
                        <div class="alert alert-error" id="pass-error">
                            <i class="fas fa-times-circle"></i> <span id="error-txt"></span>
                        </div>

                        <div class="password-reqs">
                            <h4>Password Requirements:</h4>
                            <div class="req-item unmet" id="req-len"><i class="fas fa-circle"></i> <span>At least 8 characters long</span></div>
                            <div class="req-item unmet" id="req-up"><i class="fas fa-circle"></i> <span>Contains uppercase letter</span></div>
                            <div class="req-item unmet" id="req-low"><i class="fas fa-circle"></i> <span>Contains lowercase letter</span></div>
                            <div class="req-item unmet" id="req-num"><i class="fas fa-circle"></i> <span>Contains number</span></div>
                            <div class="req-item unmet" id="req-spec"><i class="fas fa-circle"></i> <span>Contains special character</span></div>
                        </div>

                        <form id="password-form">
                            <input type="hidden" name="action" value="change_password">
                            <div class="pass-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" id="curr-pass" class="form-control" required>
                                <button type="button" class="pass-toggle" data-for="curr-pass"><i class="fas fa-eye"></i></button>
                            </div>
                            <div class="pass-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" id="new-pass" class="form-control" required>
                                <button type="button" class="pass-toggle" data-for="new-pass"><i class="fas fa-eye"></i></button>
                            </div>
                            <div class="pass-group" style="margin-bottom: 35px;">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" id="conf-pass" class="form-control" required>
                                <button type="button" class="pass-toggle" data-for="conf-pass"><i class="fas fa-eye"></i></button>
                            </div>
                            <button type="submit" class="btn-update">Save New Password</button>
                        </form>
                    </div>
                </div>
            </div>
            </div>
        </main>
    </div>

    <!-- UI Modals HTML - MOVED ABOVE SCRIPT -->
    <div class="admin-modal admin-modal-confirm" id="modal-confirm">
        <div class="admin-modal-icon"><i class="fas fa-question"></i></div>
        <h3 id="confirm-title">Confirmation</h3>
        <p id="confirm-message">Are you sure you want to proceed with this action?</p>
        <div class="admin-modal-actions">
            <button class="admin-modal-btn btn-modal-cancel" id="btn-confirm-no">Cancel</button>
            <button class="admin-modal-btn btn-modal-confirm" id="btn-confirm-yes">Yes, Proceed</button>
        </div>
    </div>

    <div class="admin-modal" id="modal-alert">
        <div class="admin-modal-icon"><i class="fas fa-info"></i></div>
        <h3 id="alert-title">Notification</h3>
        <p id="alert-message">Action completed.</p>
        <div class="admin-modal-actions">
            <button class="admin-modal-btn btn-modal-confirm" id="btn-alert-ok" style="width: 100%;">Understood</button>
        </div>
    </div>

    <!-- OTP Verification Modal -->
    <div class="admin-modal" id="modal-otp">
        <div class="admin-modal-icon" style="background: var(--secondary); color: white;"><i class="fas fa-shield-alt"></i></div>
        <h2 id="otp-title" style="font-family: 'Inter', sans-serif; color: var(--primary); margin-top: 15px;">Verify Your Identity</h2>
        <p id="otp-message">Please enter the 6-digit verification code sent to your email.</p>
        <div style="margin: 20px 0;">
            <input type="text" id="otp-input" class="form-control" placeholder="000000" maxlength="6" style="text-align: center; font-size: 1.5rem; letter-spacing: 5px; font-weight: 700;">
        </div>
        <div class="confirm-btns">
            <button class="btn btn-secondary" id="btn-otp-resend">Resend Code</button>
            <button class="btn btn-primary" id="btn-otp-verify">Verify & Save</button>
        </div>
        <button class="modal-close" onclick="closeModals()"><i class="fas fa-times"></i></button>
    </div>

    <script>
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.custom-notification');
            existingNotifications.forEach(notification => notification.remove());
            
            const notification = document.createElement('div');
            notification.className = `custom-notification ${type}`;
            
            // Inline styles for independence
            const bgColor = type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : type === 'warning' ? '#ff9800' : '#2196f3';
            notification.style.cssText = `position: fixed; top: 20px; right: 20px; background: ${bgColor}; color: white; padding: 15px 20px; border-radius: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 10px; z-index: 9999; font-family: 'Inter', sans-serif; transition: all 0.3s ease;`;
            
            let icon = 'fa-info-circle';
            if (type === 'success') icon = 'fa-check-circle';
            if (type === 'error') icon = 'fa-exclamation-circle';
            if (type === 'warning') icon = 'fa-exclamation-triangle';
            
            notification.innerHTML = `
                <div class="notification-content" style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas ${icon}"></i>
                    <span class="notification-message" style="white-space: pre-line;">${message}</span>
                </div>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; cursor: pointer; padding-left: 15px;">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }

        // Tab Navigation
        const navBtns = document.querySelectorAll('.settings-nav-btn');
        const panels = document.querySelectorAll('.settings-panel');
        navBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.target;
                if (!target) return;
                navBtns.forEach(b => b.classList.remove('active'));
                panels.forEach(p => p.classList.remove('active'));
                btn.classList.add('active');
                const targetPanel = document.getElementById(`${target}-panel`);
                if (targetPanel) targetPanel.classList.add('active');
            });
        });

        // Inner Sub-tabs for Site Content
        const stBtns = document.querySelectorAll('.st-btn');
        const stPanels = document.querySelectorAll('.st-panel');
        stBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.st;
                if (!target) return;
                stBtns.forEach(b => b.classList.remove('active'));
                stPanels.forEach(p => p.classList.remove('active'));
                btn.classList.add('active');
                const targetST = document.getElementById(`content-form-${target}`);
                if (targetST) targetST.classList.add('active');
            });
        });

        // Custom Modal Logic
        let overlayElement, confirmModal, alertModal;
        let confirmActionCallback = null;

        document.addEventListener('DOMContentLoaded', () => {
            overlayElement = document.querySelector('.overlay');
            confirmModal = document.getElementById('modal-confirm');
            alertModal = document.getElementById('modal-alert');

            const btnYes = document.getElementById('btn-confirm-yes');
            const btnNo = document.getElementById('btn-confirm-no');
            const btnOk = document.getElementById('btn-alert-ok');
            
            if (btnYes) btnYes.addEventListener('click', () => { closeModals(); if (confirmActionCallback) confirmActionCallback(); });
            if (btnNo) btnNo.addEventListener('click', closeModals);
            if (btnOk) btnOk.addEventListener('click', closeModals);
            if (overlayElement) overlayElement.addEventListener('click', closeModals);
        });

        function closeModals() {
            document.querySelectorAll('.admin-modal').forEach(m => m.classList.remove('active'));
            if (overlayElement) overlayElement.classList.remove('active');
            const sidebar = document.querySelector('.admin-sidebar');
            if (sidebar) sidebar.classList.remove('active');
        }

        function showConfirm(title, message, callback) {
            const t = document.getElementById('confirm-title');
            const m = document.getElementById('confirm-message');
            if (t) t.textContent = title;
            if (m) m.textContent = message;
            confirmActionCallback = callback;
            if (overlayElement) overlayElement.classList.add('active');
            if (confirmModal) confirmModal.classList.add('active');
        }

        function showAlert(type, title, message) {
            if (!alertModal) return;
            alertModal.className = `admin-modal admin-modal-${type} active`;
            const icon = alertModal.querySelector('.admin-modal-icon');
            if (icon) icon.innerHTML = type === 'success' ? '<i class="fas fa-check"></i>' : '<i class="fas fa-exclamation"></i>';
            const t = document.getElementById('alert-title');
            const m = document.getElementById('alert-message');
            if (t) t.textContent = title;
            if (m) m.textContent = message;
            if (overlayElement) overlayElement.classList.add('active');
        }

        // Form Handling
        const forms = ['clinic-form', 'password-form', 'admin-profile-form', 'content-form-home', 'content-form-about', 'content-form-services', 'content-form-contact'];
        forms.forEach(id => {
            const f = document.getElementById(id);
            if (!f) return;
            f.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const processUpdate = async (otpCode = '') => {
                    const btn = f.querySelector('button[type="submit"]');
                    const orig = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...'; 
                    btn.disabled = true;

                    try {
                        const formData = new FormData(f);
                        if (otpCode) {
                            formData.append('otp', otpCode);
                            // Also ensure we keep the intended action if it was a profile update
                        }

                        const res = await fetch('admin-settings.php', { method: 'POST', body: formData });
                        const data = await res.json();
                        
                        if (data.needs_otp) {
                            // First, ask where to send OTP if multiple changed, or just send to the new one
                            const email = f.querySelector('[name="email"]')?.value;
                            const phone = f.querySelector('[name="phone"]')?.value;
                            const currentEmail = "<?php  echo $adminData['email']; ?>";
                            const currentPhone = "<?php  echo $adminData['phone'] ?? ''; ?>";
                            
                            let targetType = 'email';
                            let targetVal = email;

                            if (email !== currentEmail) {
                                targetType = 'email';
                                targetVal = email;
                            } else if (phone !== currentPhone) {
                                targetType = 'phone';
                                targetVal = phone;
                            }

                            showOTPModal(targetType, targetVal, async (code) => {
                                await processUpdate(code);
                            });
                        } else if (data.success) {
                            showAlert('success', 'Information Updated', data.message);
                            if (id === 'password-form') f.reset();
                            if (id === 'admin-profile-form') setTimeout(() => location.reload(), 1500);
                        } else {
                            showAlert('error', 'Update Failed', data.message);
                        }
                    } catch (err) { 
                        showAlert('error', 'System Error', 'An unexpected error occurred.');
                    } finally { 
                        btn.innerHTML = orig; btn.disabled = false; 
                    }
                };

                showConfirm('Confirm Update', 'Are you sure you want to save these changes?', async () => {
                    await processUpdate();
                });
            });
        });

        // OTP Modal Logic
        function showOTPModal(type, value, callback) {
            const modal = document.getElementById('modal-otp');
            const title = document.getElementById('otp-title');
            const msg = document.getElementById('otp-message');
            const input = document.getElementById('otp-input');
            const btn = document.getElementById('btn-otp-verify');
            const resend = document.getElementById('btn-otp-resend');

            title.textContent = `Verify ${type === 'email' ? 'Email' : 'Phone'}`;
            msg.textContent = `A verification code has been sent to ${value}`;
            input.value = '';
            
            if (modal && overlayElement) {
                modal.classList.add('active');
                overlayElement.classList.add('active');
            }

            // Send initial OTP
            sendOTP(type, value);

            btn.onclick = () => {
                const code = input.value.trim();
                if (code.length === 6) {
                    closeModals();
                    callback(code);
                } else {
                    showNotification('Please enter a 6-digit code', 'error');
                }
            };

            resend.onclick = () => sendOTP(type, value);
        }

        async function sendOTP(type, value) {
            const fd = new FormData();
            fd.append('action', type === 'email' ? 'send_email_otp' : 'send_phone_otp');
            fd.append(type, value);
            const firstNameInput = document.querySelector('[name="first_name"]');
            if (firstNameInput) fd.append('first_name', firstNameInput.value.trim());
            try {
                const res = await fetch('../controllers/OTPController.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    // Show a small toast or inline message
                    console.log('OTP Sent');
                }
            } catch(e) {}
        }

        // Testimonial Toggle
        document.querySelectorAll('.testimonial-toggle').forEach(toggle => {
            toggle.addEventListener('click', () => {
                if (toggle.classList.contains('active')) return;
                showConfirm('Update Testimonial', 'Set this as featured?', async () => {
                    const fd = new FormData();
                    fd.append('action', 'set_featured_testimonial');
                    fd.append('feedback_id', toggle.dataset.feedbackId);
                    fd.append('client_id', toggle.dataset.clientId);
                    try {
                        const res = await fetch('admin-settings.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.success) {
                            document.querySelectorAll(`.testimonial-toggle[data-client-id="${toggle.dataset.clientId}"]`).forEach(t => t.classList.remove('active'));
                            toggle.classList.add('active');
                            showAlert('success', 'Updated', data.message);
                        }
                    } catch(e) {}
                });
            });
        });

        // Filtering
        const filterStars = document.getElementById('test-filter-stars');
        const filterSearch = document.getElementById('test-filter-search');
        function filterTestimonials() {
            if (!filterStars || !filterSearch) return;
            const starVal = filterStars.value;
            const searchVal = filterSearch.value.toLowerCase();
            document.querySelectorAll('.testimonial-client-group').forEach(group => {
                let hasVisible = false;
                const clientName = (group.querySelector('.testimonial-client-header span')?.textContent || '').toLowerCase();
                group.querySelectorAll('.testimonial-item:not(.testimonial-none)').forEach(item => {
                    const r = parseInt(item.dataset.rating || 0);
                    const match = ((starVal === 'all') || (r === parseInt(starVal))) && clientName.includes(searchVal);
                    item.style.display = match ? 'flex' : 'none';
                    if (match) hasVisible = true;
                });
                group.style.display = (hasVisible || (starVal === 'all' && clientName.includes(searchVal))) ? 'block' : 'none';
            });
        }
        if (filterStars) filterStars.addEventListener('change', filterTestimonials);
        if (filterSearch) filterSearch.addEventListener('input', filterTestimonials);

        // Sidebar Toggle
        const sideToggle = document.querySelector('.admin-sidebar-toggle');
        const sidebar = document.querySelector('.admin-sidebar');
        if (sideToggle && sidebar) {
            sideToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                if (overlayElement) overlayElement.classList.toggle('active');
            });
        }

        // Clock
        function tick() {
            const now = new Date();
            const d = document.getElementById('admin-date');
            const t = document.getElementById('admin-time');
            if (d) d.textContent = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
            if (t) t.textContent = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
        }
        setInterval(tick, 1000); tick();

        // Helpers
        function addCard(cId, max) {
            const container = document.getElementById(cId);
            if (!container) return;
            const cards = container.querySelectorAll('.dynamic-card-group');
            let shown = 0; let next = null;
            cards.forEach(c => { if (c.style.display !== 'none') shown++; else if (!next) next = c; });
            if (shown >= max) { showAlert('error', 'Limit Reached', `Max ${max} items.`); return; }
            if (next) { next.style.display = ''; next.style.animation = 'fadeIn 0.3s ease'; }
        }
        function removeCard(btn) {
            const card = btn.closest('.dynamic-card-group');
            if (!card) return;
            const vis = card.parentElement.querySelectorAll('.dynamic-card-group:not([style*="display: none"])');
            if (vis.length <= 1) { showAlert('error', 'Required', 'At least one item is required.'); return; }
            
            card.querySelectorAll('input, select, textarea').forEach(el => el.value = '');
            card.querySelectorAll('.image-preview').forEach(img => img.remove());
            
            // Add a hidden input to force the server to clear the image field in the database!
            const fileInputs = card.querySelectorAll('input[type="file"]');
            fileInputs.forEach(fileInput => {
                const nameAttr = fileInput.getAttribute('name');
                if (nameAttr) {
                    const match = nameAttr.match(/\[(.*?)\]/);
                    if (match && match[1]) {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = `content[${match[1]}]`;
                        hiddenInput.value = '';
                        card.appendChild(hiddenInput);
                    }
                }
            });
            
            card.style.display = 'none';
        }
        function updateIconPreview(sel) {
            const preview = sel.closest('.icon-select-wrap')?.querySelector('.icon-select-preview i');
            if (preview) preview.className = sel.value || 'fas fa-star';
        }

        // Cropper logic
        let currentCropper = null;
        let currentFileInput = null;
        const cropperModalHtml = `
        <div id="cropperModal" class="modal-backdrop" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:9999; background:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
            <div class="modal-content" style="background:white; padding:20px; border-radius:10px; width:90%; max-width:800px; max-height:90vh; display:flex; flex-direction:column;">
                <h3 style="margin-bottom:15px; font-family: 'Inter', sans-serif; color: var(--primary);">Crop Image</h3>
                <div style="flex-grow:1; overflow:hidden; min-height:400px; background:#f0f0f0; border-radius: 8px;">
                    <img id="cropperImage" style="max-width:100%; display:block;">
                </div>
                <div style="margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" class="btn-modal-cancel" onclick="closeCropperModal()" style="border: none; padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer;">Cancel</button>
                    <button type="button" class="btn-modal-confirm" onclick="applyCrop()" style="border: none; padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer;">Apply Crop</button>
                </div>
            </div>
        </div>
        `;
        document.body.insertAdjacentHTML('beforeend', cropperModalHtml);

        document.addEventListener('change', function(e) {
            if (e.target && e.target.type === 'file' && e.target.accept.includes('image')) {
                const file = e.target.files[0];
                if (file) {
                    e.target.dataset.fileName = file.name;
                    e.target.dataset.fileType = file.type;
                    const reader = new FileReader();
                    reader.onload = function(event) { openCropperModal(event.target.result, e.target); };
                    reader.readAsDataURL(file);
                }
            }
        });

        function openCropperModal(dataUrl, inputEl) {
            currentFileInput = inputEl;
            const modal = document.getElementById('cropperModal');
            const image = document.getElementById('cropperImage');
            image.src = dataUrl;
            modal.style.display = 'flex';
            if (currentCropper) currentCropper.destroy();
            currentCropper = new Cropper(image, { viewMode: 2, autoCropArea: 1, responsive: true });
        }

        function closeCropperModal(resetInput = true) {
            document.getElementById('cropperModal').style.display = 'none';
            if (currentCropper) { currentCropper.destroy(); currentCropper = null; }
            if (resetInput && currentFileInput) currentFileInput.value = ''; // Only reset if canceled
        }

        function applyCrop() {
            if (!currentCropper || !currentFileInput) return;
            const canvas = currentCropper.getCroppedCanvas();
            if (!canvas) { closeCropperModal(true); return; }
            
            const dataUrl = canvas.toDataURL(currentFileInput.dataset.fileType || 'image/jpeg', 0.9);
            canvas.toBlob(function(blob) {
                const file = new File([blob], currentFileInput.dataset.fileName || 'cropped.jpg', {
                    type: currentFileInput.dataset.fileType || 'image/jpeg', lastModified: new Date().getTime()
                });
                const container = new DataTransfer();
                container.items.add(file);
                currentFileInput.files = container.files;
                
                let preview = currentFileInput.parentElement.querySelector('.image-preview');
                if (!preview) {
                    preview = document.createElement('img');
                    preview.className = 'image-preview';
                    preview.style.objectFit = 'cover';
                    preview.style.maxWidth = '100%';
                    preview.style.maxHeight = '200px';
                    preview.style.borderRadius = '8px';
                    preview.style.border = '1px dashed var(--border)';
                    preview.style.padding = '5px';
                    preview.style.marginTop = '15px';
                    currentFileInput.parentElement.appendChild(preview);
                }
                preview.src = dataUrl;
                closeCropperModal(false); // Don't reset since we applied the crop
            }, currentFileInput.dataset.fileType || 'image/jpeg', 0.9);
        }

        // Password helpers
        document.querySelectorAll('.pass-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.for);
                if (input) {
                    const isPass = input.type === 'password';
                    input.type = isPass ? 'text' : 'password';
                    btn.querySelector('i').className = isPass ? 'fas fa-eye-slash' : 'fas fa-eye';
                }
            });
        });
        const np = document.getElementById('new-pass');
        if (np) np.addEventListener('input', () => {
            const v = np.value;
            const checks = { 'req-len': v.length >= 8, 'req-up': /[A-Z]/.test(v), 'req-low': /[a-z]/.test(v), 'req-num': /[0-9]/.test(v), 'req-spec': /[^A-Za-z0-9]/.test(v) };
            for (const [id, met] of Object.entries(checks)) {
                const el = document.getElementById(id);
                if (el) { el.className = `req-item ${met ? 'met' : 'unmet'}`; el.querySelector('i').className = met ? 'fas fa-check-circle' : 'fas fa-circle'; }
            }
        });
    </script>
    <div class="admin-sidebar-toggle"><i class="fas fa-bars"></i></div>
</body>
</html>
