<?php
// index.php is in: Cosmo Smiles Dental/public/assets/index.php
session_start();

// Include necessary files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../src/Controllers/ContactController.php';
require_once __DIR__ . '/../src/Controllers/SiteContentController.php';

$siteContentController = new SiteContentController();
$contactContent = $siteContentController->getFlatContent('contact');
$clinicInfo = $siteContentController->getFlatContent('clinic');
$homeContent = $siteContentController->getFlatContent('home');

// Enhanced session handling with security
$isLoggedIn = isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true;

// Initialize variables
$userName = 'My Account';
$clientFullName = '';
$clientEmail = '';
$clientPhone = '';
$client_id = null;

// If logged in, get client details for pre-filling
if ($isLoggedIn) {
    // Get user name for display
    $firstName = isset($_SESSION['client_first_name']) ? $_SESSION['client_first_name'] : '';
    $lastName = isset($_SESSION['client_last_name']) ? $_SESSION['client_last_name'] : '';
    $userName = trim($firstName . ' ' . $lastName);
    
    // Get client ID - check all possible session variables
    if (isset($_SESSION['client_id']) && !empty($_SESSION['client_id'])) {
        $client_id = $_SESSION['client_id'];
    } elseif (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $client_id = $_SESSION['user_id'];
    } elseif (isset($_SESSION['id']) && !empty($_SESSION['id'])) {
        $client_id = $_SESSION['id'];
    }
    
    // If both names are empty, show generic name
    if (empty($userName)) {
        $userName = 'My Account';
    }
    
    // Get contact details for pre-filling
    $contactController = new ContactController();
    
    if (isset($_SESSION['client_email']) && isset($_SESSION['client_phone'])) {
        $clientFullName = $firstName . ' ' . $lastName;
        $clientEmail = $_SESSION['client_email'];
        $clientPhone = $_SESSION['client_phone'];
    } elseif ($client_id) {
        // Try to get from database
        $clientData = $contactController->getClientData($client_id);
        if ($clientData) {
            $clientFullName = $clientData['full_name'];
            $clientEmail = $clientData['email'];
            $clientPhone = $clientData['phone'];
            
            // Store in session for future use
            $_SESSION['client_email'] = $clientEmail;
            $_SESSION['client_phone'] = $clientPhone;
            // Also store the correct client_client_id from database
            if (isset($clientData['client_id'])) {
                $_SESSION['client_client_id'] = $clientData['client_id'];
                $client_id_number = $clientData['client_id'];
            }
        }
    }

    // Get user profile image if logged in
    $profileImage = null;
    if ($isLoggedIn && $client_id) {
        $sql = "SELECT profile_image FROM clients WHERE client_id = :client_id OR id = :id LIMIT 1";
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':client_id', $client_id);
            $stmt->bindParam(':id', $client_id);
            $stmt->execute();
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($userData && !empty($userData['profile_image'])) {
                $profileImage = $userData['profile_image'];
            }
        } catch (Exception $e) {
            error_log("Error fetching profile image: " . $e->getMessage());
        }
    }
}

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $contactController = new ContactController();
    
    // Verify and get the correct client_id
    $form_client_id = null;
    if ($isLoggedIn && $client_id) {
        // Get client data to verify and get correct client_id from database
        $clientData = $contactController->getClientData($client_id);
        if ($clientData && isset($clientData['client_id'])) {
            $form_client_id = $clientData['client_id'];
        }
    }
    
    // Prepare form data
    $formData = [
        'client_id' => $form_client_id,
        'name' => trim($_POST['name']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone'] ?? ''),
        'message' => trim($_POST['message'])
    ];
    
    // Process the form
    $result = $contactController->processContactForm($formData);
    
    // Store result in session
    $_SESSION['contact_message'] = $result['message'];
    $_SESSION['contact_message_type'] = $result['type'];
    
    // Redirect to prevent form resubmission
    header('Location: index.php#contact');
    exit();
}

// Get any messages from session
$contactMessage = '';
$contactMessageType = '';
if (isset($_SESSION['contact_message'])) {
    $contactMessage = $_SESSION['contact_message'];
    $contactMessageType = $_SESSION['contact_message_type'];
    unset($_SESSION['contact_message']);
    unset($_SESSION['contact_message_type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="<?php echo clean_url('public/assets/images/logo1-white.png'); ?>">
    <title>Cosmo Smiles Dental</title>
    <link rel="stylesheet" href="<?php echo clean_url('public/assets/css/styles.css'); ?>">
    <?php 
    include 'client/includes/client-header-css.php'; 
    include 'client/includes/client-common-styles.php';
    include 'client/includes/client-footer-css.php';
    ?>
    <style>
        .page-hero {
            position: relative;
            padding: 160px 0 100px;
            background: linear-gradient(to right, rgba(3, 7, 79, 0.95), rgba(3, 7, 79, 0.7)), 
                        url('https://images.unsplash.com/photo-1445527815219-ecbfec67492e?q=80&w=2070&auto=format&fit=crop') no-repeat center center / cover !important;
            text-align: center;
            color: white;
        }

        .page-hero .section-title, 
        .page-hero .section-subtitle {
            color: white !important;
        }

        .page-hero .section-tag {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 60px;
            align-items: start;
        }

        .info-card {
            padding: 50px 40px;
        }

        .contact-method {
            display: flex;
            gap: 20px;
            margin-bottom: 35px;
        }

        .method-icon {
            width: 55px;
            height: 55px;
            background: var(--accent-soft);
            color: var(--secondary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .form-card {
            padding: 60px;
        }

        .premium-input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #F1F5F9;
            border-radius: 12px;
            font-family: inherit;
            font-size: 1rem;
            transition: var(--transition);
            background: #F8FAFC;
            margin-bottom: 25px;
        }

        .premium-input:focus {
            outline: none;
            border-color: var(--secondary);
            background: white;
            box-shadow: var(--shadow-md);
        }

        .map-frame {
            width: 100%;
            height: 450px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            margin-top: 80px;
        }

        @media (max-width: 992px) {
            .contact-grid { grid-template-columns: 1fr; gap: 40px; }
            .form-card { padding: 40px; }
            .info-card { padding: 40px; }
        }

        @media (max-width: 576px) {
            .form-card { padding: 40px 25px; }
            .info-card { padding: 40px 25px; }
            .contact-method { gap: 15px; margin-bottom: 25px; }
            .method-icon { width: 45px; height: 45px; font-size: 1rem; }
            .hours-container { width: 100% !important; max-width: 350px; }
            .social-buttons { flex-direction: column; }
        }
    </style>
</head>

<body>
    <?php 
    $baseDir = ''; 
    include 'client/includes/client-header.php'; 
    ?>

    <!-- Page Hero -->
    <section class="page-hero">
        <div class="container">
            <span class="section-tag">Clinical Support</span>
            <h1 class="section-title" style="font-size: 3.5rem;"><?php echo isset($contactContent['contact_title']) ? nl2br(htmlspecialchars($contactContent['contact_title'])) : 'Connect with <span style="color: var(--secondary);">Our Team</span>'; ?></h1>
            <p class="section-subtitle" style="max-width: 700px; margin: 0 auto;"><?php echo isset($contactContent['contact_subtitle']) ? nl2br(htmlspecialchars($contactContent['contact_subtitle'])) : 'Our dedicated patient support coordinators are ready to assist you with scheduling and clinical inquiries.'; ?></p>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="section-padding">
        <div class="container">
            <!-- Alert Area -->
            <?php if ($contactMessage): ?>
                <div class="premium-card" style="margin-bottom: 40px; padding: 25px; border-left: 6px solid var(--secondary); background: #f0f7ff;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <i class="fas fa-check-circle" style="color: var(--secondary); font-size: 1.5rem;"></i>
                        <span style="font-weight: 600; color: var(--primary);"><?php echo htmlspecialchars($contactMessage); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="contact-grid">
                <!-- Info Column -->
                <div class="premium-card info-card">
                    <h3 class="section-title" style="text-align: left; font-size: 2rem;">Quick Reach</h3>
                    <p class="section-subtitle" style="text-align: left; margin-bottom: 40px;">Find us at our Binangonan facility or reach out via our digital channels.</p>
                    
                    <div class="contact-method">
                        <div class="method-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div>
                            <h4 style="margin-bottom: 5px;">Clinic Location</h4>
                            <p class="section-subtitle" style="font-size: 0.95rem; text-align: left;"><?php echo nl2br(htmlspecialchars($clinicInfo['address'] ?? '703-F National Road Tayuman<br>Binangonan, Rizal')); ?></p>
                        </div>
                    </div>

                    <div class="contact-method">
                        <div class="method-icon"><i class="fas fa-phone-alt"></i></div>
                        <div>
                            <h4 style="margin-bottom: 5px;">Direct Line</h4>
                            <p class="section-subtitle" style="font-size: 0.95rem; text-align: left;"><?php echo htmlspecialchars($clinicInfo['phone'] ?? '0926 649 2903'); ?></p>
                        </div>
                    </div>

                    <div class="contact-method">
                        <div class="method-icon"><i class="fas fa-clock"></i></div>
                        <div>
                            <h4 style="margin-bottom: 15px;">Facility Hours</h4>
                            <div class="hours-container" style="width: 250px;">
                                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #F1F5F9;">
                                    <span style="font-weight: 600; font-size: 0.9rem; color: var(--primary);">Mon - Fri</span>
                                    <span style="font-size: 0.9rem; color: var(--text);"><?php echo htmlspecialchars(str_replace(['Mon - Fri: ', 'Mon-Fri: '], '', $homeContent['hours_week'] ?? '8:00 AM - 6:00 PM')); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #F1F5F9;">
                                    <span style="font-weight: 600; font-size: 0.9rem; color: var(--primary);">Saturday</span>
                                    <span style="font-size: 0.9rem; color: var(--text);"><?php echo htmlspecialchars(str_replace(['Sat: ', 'Saturday: '], '', $homeContent['hours_sat'] ?? '9:00 AM - 3:00 PM')); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                                    <span style="font-weight: 600; font-size: 0.9rem; color: var(--primary);">Sunday</span>
                                    <span style="font-size: 0.9rem; color: var(--text);"><?php echo htmlspecialchars(str_replace(['Sun: ', 'Sunday: '], '', $homeContent['hours_sun'] ?? 'No Clinic Operations')); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="social-buttons" style="margin-top: 50px; display: flex; gap: 15px;">
                        <a href="<?php echo htmlspecialchars($contactContent['contact_fb'] ?? 'https://www.facebook.com/profile.php?id=100063660475340'); ?>" class="btn-premium" style="flex: 1; justify-content: center; background-color: #1877F2;"><i class="fab fa-facebook-f"></i></a>
                        <a href="<?php echo htmlspecialchars($contactContent['contact_waze'] ?? 'https://www.waze.com/live-map/directions/ph/calabarzon/binangonan/cosmo-smiles-dental-clinic?to=place.ChIJ3Z21dojHlzMRvazDzgFbayk'); ?>" class="btn-premium btn-outline" style="flex: 1; justify-content: center;"><i class="fa-brands fa-waze"></i></a>
                    </div>
                </div>

                <!-- Form Column -->
                <div class="premium-card form-card">
                    <h3 class="section-title" style="text-align: left; font-size: 2rem;">Contact Us</h3>
                    <p class="section-subtitle" style="text-align: left; margin-bottom: 40px;">Expect a clinical coordinator to respond within 2-4 business hours.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="contact_submit" value="1">
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 10px; font-weight: 600; font-size: 0.9rem;">Full Name</label>
                                <input type="text" name="name" class="premium-input" placeholder="Clinical Full Name" required value="<?php echo htmlspecialchars($clientFullName); ?>">
                            </div>
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 10px; font-weight: 600; font-size: 0.9rem;">Email Address</label>
                                <input type="email" name="email" class="premium-input" placeholder="Digital Address" required value="<?php echo htmlspecialchars($clientEmail); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="display: block; margin-bottom: 10px; font-weight: 600; font-size: 0.9rem;">Phone Contact</label>
                            <input type="tel" name="phone" class="premium-input" placeholder="Mobile or Work Line" value="<?php echo htmlspecialchars($clientPhone); ?>">
                        </div>

                        <div class="form-group">
                            <label style="display: block; margin-bottom: 10px; font-weight: 600; font-size: 0.9rem;">Description of Needs</label>
                            <textarea name="message" class="premium-input" rows="5" placeholder="Please describe your clinical inquiry or service requests..." required></textarea>
                        </div>

                        <button type="submit" class="btn-premium" style="width: 100%; justify-content: center; padding: 20px;">
                            <i class="fas fa-paper-plane"></i> Send Inquiry
                        </button>
                    </form>
                </div>
            </div>

            <!-- Map -->
            <div class="map-frame">
                <iframe 
                    src="<?php echo htmlspecialchars($contactContent['contact_map_url'] ?? 'https://www.google.com/maps?q=14.5115122,121.1646472&z=17&output=embed'); ?>" 
                    width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>
    </section>

    <?php include 'client/includes/client-footer.php'; ?>

    <script src="<?php echo clean_url('public/assets/js/script.js'); ?>"></script>

</body>
</html>