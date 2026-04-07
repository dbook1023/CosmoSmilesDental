<?php
// index.php is in: Cosmo Smiles Dental/public/assets/index.php
ob_start();
session_start();
require_once __DIR__ . '/../src/Services/DdosProtection.php';

// Include necessary files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../src/Controllers/ContactController.php';
require_once __DIR__ . '/../src/Controllers/SiteContentController.php';
require_once __DIR__ . '/../src/Controllers/TestimonialController.php';

$siteContentController = new SiteContentController();
$testimonialController = new TestimonialController();
$homeContent = $siteContentController->getFlatContent('home');
$clinicInfo = $siteContentController->getFlatContent('clinic');
$featuredTestimonials = $testimonialController->getFeaturedTestimonials();

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

// Get user profile image if logged in
$profileImage = null;
if ($isLoggedIn && $client_id) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        $sql = "SELECT profile_image FROM clients WHERE client_id = :client_id OR id = :id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':client_id', $client_id);
        $stmt->bindParam(':id', $client_id);
        $stmt->execute();
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($userData && !empty($userData['profile_image'])) {
            $profileImage = $userData['profile_image'];
        }
    } catch (PDOException $e) {
        // Silently fail or log
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="assets/images/logo1-white.png">
    <title>Cosmo Smiles Dental</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <?php 
    include 'client/includes/client-header-css.php'; 
    include 'client/includes/client-common-styles.php';
    include 'client/includes/client-footer-css.php';
    ?>
    <style>
        /* Homepage High-Fidelity Refinements */
        .hero {
            background: radial-gradient(circle at top right, var(--accent-soft), transparent 40%), white;
            overflow: hidden;
        }

        .hero-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 80px;
        }

        .hero-content {
            flex: 1;
            padding-right: 50px;
        }

        .hero-content h1 {
            font-size: clamp(2.5rem, 6vw, 4.2rem);
            margin-bottom: 30px;
            color: var(--primary);
            line-height: 1.05;
        }

        .hero-content p {
            font-size: 1.2rem;
            color: var(--text-muted);
            margin-bottom: 40px;
            max-width: 580px;
        }

        .hero-image {
            flex: 1;
            position: relative;
            height: 100%;
            display: flex;
            align-items: center;
        }

        .hero-image img {
            width: 100%;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            position: relative;
            z-index: 2;
        }

        .hero-image::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 20px;
            left: 20px;
            border: 2px solid var(--accent-soft);
            border-radius: var(--radius-lg);
            z-index: 1;
        }

        .promo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .promo-card {
            padding: 45px 35px;
            text-align: left;
        }

        .promo-card i {
            font-size: 2.5rem;
            color: var(--secondary);
            margin-bottom: 25px;
            display: block;
        }

        .promo-card h4 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 15px;
            font-weight: 700;
        }

        /* Why Us List */
        .why-us-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
        }

        .why-us-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            border-radius: var(--radius-md);
            transition: var(--transition);
        }

        .why-us-item:hover {
            background: var(--bg-light);
        }

        .why-us-icon {
            width: 60px;
            height: 60px;
            background: var(--primary);
            color: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            box-shadow: var(--shadow-md);
        }

        /* Team Cards */
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .team-card {
            padding: 0;
            text-align: center;
        }

        .team-info {
            padding: 30px;
        }

        .author-avatar {
            width: 50px;
            height: 50px;
            background: var(--accent-soft);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--secondary);
            overflow: hidden;
        }

        .author-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .team-img-premium {
            width: 100%;
            height: 250px;
            background: var(--bg-light);
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: var(--accent);
            border: none;
        }

        /* Testimonials Carousel */
        .testimonial-carousel-wrap { position: relative; padding: 0 50px; }
        .testimonial-carousel-wrap .swiper { overflow: hidden; padding-bottom: 50px; }
        .testimonial-carousel-wrap .swiper-slide { height: auto; }

        .testimonial-card-premium {
            padding: 40px;
            background: white;
            color: var(--text-dark);
            border: none;
            box-shadow: var(--shadow-md);
            border-radius: var(--radius-lg);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .testimonial-text {
            font-style: italic;
            font-size: 1.1rem;
            margin-bottom: 30px;
            color: var(--text-dark);
            flex-grow: 1;
        }

        .author-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--accent-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary);
            font-weight: 700;
            overflow: hidden;
            flex-shrink: 0;
        }
        .author-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .testimonial-carousel-wrap .swiper-button-next,
        .testimonial-carousel-wrap .swiper-button-prev {
            color: rgba(255,255,255,0.8);
            width: 44px; height: 44px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            backdrop-filter: blur(5px);
        }
        .testimonial-carousel-wrap .swiper-button-next::after,
        .testimonial-carousel-wrap .swiper-button-prev::after { font-size: 18px; font-weight: bold; }
        .testimonial-carousel-wrap .swiper-button-next { right: 0; }
        .testimonial-carousel-wrap .swiper-button-prev { left: 0; }
        .testimonial-carousel-wrap .swiper-pagination-bullet { background: rgba(255,255,255,0.5); opacity: 1; }
        .testimonial-carousel-wrap .swiper-pagination-bullet-active { background: white; transform: scale(1.3); }

        @media (max-width: 768px) {
            .testimonial-carousel-wrap { padding: 0 10px; }
            .testimonial-carousel-wrap .swiper-button-next,
            .testimonial-carousel-wrap .swiper-button-prev { display: none; }
        }

        @media (max-width: 992px) {
            .hero-flex {
                flex-direction: column;
                text-align: center;
                gap: 50px;
            }
            .hero-content h1 { font-size: 3rem; }
            .hero-content p { margin: 0 auto 40px; }
            .hero-image { max-width: 500px; }
            #hero-buttons { justify-content: center; }
        }
        /* Tablet & Mobile Responsiveness */
        @media (max-width: 992px) {
            .hero-flex {
                flex-direction: column;
                text-align: center;
                gap: 50px;
                padding-top: 100px;
            }

            .hero-content {
                padding-right: 0;
            }

            .hero-content h1 {
                font-size: 2.8rem;
            }

            .hero-image {
                max-width: 500px;
                margin: 0 auto;
            }

            .promo-grid, .why-us-grid, .team-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            .promo-card { text-align: center; }
            .promo-card i { margin: 0 auto 25px; }
            .why-us-item { flex-direction: column; align-items: center; text-align: center; }
        }

        @media (max-width: 576px) {
            .hero-content h1 {
                font-size: 2.2rem;
            }
            .hero-flex {
                padding-top: 80px;
            }
        }
    </style>
</head>

<body class="bg-white">
    <?php 
    $baseDir = ''; 
    include 'client/includes/client-header.php'; 
    ?>

    <!-- Hero Section -->
    <section class="section-full hero">
        <div class="container hero-flex">
            <div class="hero-content">
                <div class="section-tag"><i class="fas fa-certificate"></i> Trusted Medical Excellence</div>
                <h1><?php echo isset($homeContent['hero_title']) ? nl2br(htmlspecialchars($homeContent['hero_title'])) : 'Excellence in <br><span style="color: var(--secondary);">Every Smile.</span>'; ?></h1>
                <p><?php echo isset($homeContent['hero_subtitle']) ? nl2br(htmlspecialchars($homeContent['hero_subtitle'])) : 'Welcome to Cosmo Smiles, where cutting-edge dental technology meets a compassionate, personalized approach to your clinical journey.'; ?></p>
                <div id="hero-buttons" style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <a href="client/new-appointments.php" class="btn-premium">Book Appointment</a>
                    <a href="services.php" class="btn-premium btn-outline">Our Services</a>
                </div>
            </div>
            <div class="hero-image">
                <?php 
                $heroImgExists = false;
                if (!empty($homeContent['hero_bg_image'])) {
                    $heroPath = __DIR__ . '/..' . $homeContent['hero_bg_image'];
                    if (file_exists($heroPath)) {
                        $heroImgExists = true;
                    }
                }
                ?>
                <img src="<?php echo $heroImgExists ? URL_ROOT . ltrim($homeContent['hero_bg_image'], '/') : 'assets/images/csdc.jpg'; ?>" alt="Cosmo Smiles Dental Clinic">
            </div>
        </div>
    </section>

    <!-- Promo Section -->
    <section class="section-full" style="background: var(--bg-light);">
        <div class="container">
            <div class="promo-grid">
                <?php for($i=1; $i<=6; $i++): ?>
                    <?php if(!empty($homeContent['promo_'.$i.'_title'])): ?>
                    <div class="premium-card promo-card">
                        <i class="<?php echo htmlspecialchars($homeContent['promo_'.$i.'_icon'] ?? 'fas fa-star'); ?>"></i>
                        <h4><?php echo htmlspecialchars($homeContent['promo_'.$i.'_title']); ?></h4>
                        <p><?php echo htmlspecialchars($homeContent['promo_'.$i.'_desc'] ?? ''); ?></p>
                    </div>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if(empty($homeContent['promo_1_title'])): ?>
                <div class="premium-card promo-card">
                    <i class="fas fa-hand-holding-medical"></i>
                    <h4>Accessible Care</h4>
                    <p>Premium clinic services made affordable with flexible payment options and membership rewards.</p>
                </div>
                <div class="premium-card promo-card">
                    <i class="fas fa-microscope"></i>
                    <h4>Digital Diagnostics</h4>
                    <p>Complimentary high-definition panoramic imaging for every new patient's first assessment.</p>
                </div>
                <div class="premium-card promo-card">
                    <i class="fas fa-sparkles"></i>
                    <h4>Complete Revival</h4>
                    <p>Receive a complimentary clinical-grade whitening session when starting any aesthetic plan.</p>
                </div>
                <div class="premium-card promo-card">
                    <i class="fas fa-shield-heart"></i>
                    <h4>Patient Comfort</h4>
                    <p>Experience stress-free dentistry with our proprietary sedation and comfort protocols.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Why Us Section -->
    <section class="section-full">
        <div class="container">
            <div class="section-header">
                <span class="section-tag">The Cosmo Advantage</span>
                <h2 class="section-title">Why Patients Choose Us</h2>
                <p class="section-subtitle">Combining years of clinical mastery with a dedicated human-first philosophy.</p>
            </div>
            <div class="why-us-grid">
                <?php for($i=1; $i<=6; $i++): ?>
                    <?php if(!empty($homeContent['why_'.$i.'_title'])): ?>
                    <div class="why-us-item">
                        <div class="why-us-icon"><i class="<?php echo htmlspecialchars($homeContent['why_'.$i.'_icon'] ?? 'fas fa-check'); ?>"></i></div>
                        <div>
                            <h3 style="margin-bottom: 10px;"><?php echo htmlspecialchars($homeContent['why_'.$i.'_title']); ?></h3>
                            <p style="color: var(--text-muted);"><?php echo htmlspecialchars($homeContent['why_'.$i.'_desc'] ?? ''); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if(empty($homeContent['why_1_title'])): ?>
                <div class="why-us-item">
                    <div class="why-us-icon"><i class="fas fa-user-md"></i></div>
                    <div>
                        <h3 style="margin-bottom: 10px;">Board Certified</h3>
                        <p style="color: var(--text-muted);">Our specialists are internationally trained and constantly innovate through global seminars.</p>
                    </div>
                </div>
                <div class="why-us-item">
                    <div class="why-us-icon"><i class="fas fa-clock"></i></div>
                    <div>
                        <h3 style="margin-bottom: 10px;">Emergency Response</h3>
                        <p style="color: var(--text-muted);">Priority scheduling for urgent cases ensures you get relief and care within the fastest timeframe.</p>
                    </div>
                </div>
                <div class="why-us-item">
                    <div class="why-us-icon"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <h3 style="margin-bottom: 10px;">Modern Standards</h3>
                        <p style="color: var(--text-muted);">We adhere to the strictest sterilization and clinical protocols for your absolute safety.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Specialists Section -->
    <section class="section-full" style="background: var(--bg-light);">
        <div class="container">
            <div class="section-header">
                <span class="section-tag">Our Experts</span>
                <h2 class="section-title">Meet Your Care Team</h2>
                <p class="section-subtitle">Dedicated professionals committed to transforming your smile and health.</p>
            </div>
            <div class="team-grid">
                <?php for($i=1; $i<=6; $i++): ?>
                    <?php if(!empty($homeContent['team_'.$i.'_name'])): ?>
                    <div class="premium-card team-card">
                        <?php 
                        $teamImgExists = false;
                        if (!empty($homeContent['team_'.$i.'_img'])) {
                            $teamPath = __DIR__ . $homeContent['team_'.$i.'_img'];
                            if (file_exists($teamPath)) {
                                $teamImgExists = true;
                            }
                        }
                        ?>
                        <?php if($teamImgExists): ?>
                            <div class="team-img-premium" style="background-image: url('<?php echo htmlspecialchars(ltrim($homeContent['team_'.$i.'_img'], '/')); ?>');"></div>
                        <?php else: ?>
                            <div class="team-img-premium"><i class="fas fa-user-doctor"></i></div>
                        <?php endif; ?>
                        <div class="team-info">
                            <h3 style="margin-bottom: 5px;"><?php echo htmlspecialchars($homeContent['team_'.$i.'_name']); ?></h3>
                            <p style="color: var(--secondary); font-weight: 600; font-size: 0.9rem; margin-bottom: 15px;"><?php echo htmlspecialchars($homeContent['team_'.$i.'_role'] ?? 'Specialist'); ?></p>
                            <p style="color: var(--text-muted); font-size: 0.95rem;"><?php echo htmlspecialchars($homeContent['team_'.$i.'_desc'] ?? ''); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if(empty($homeContent['team_1_name'])): ?>
                <div class="premium-card team-card">
                    <div class="team-img-premium"><i class="fas fa-user-doctor"></i></div>
                    <div class="team-info">
                        <h3 style="margin-bottom: 5px;">Dr. Rhea Ann Salcedo</h3>
                        <p style="color: var(--secondary); font-weight: 600; font-size: 0.9rem; margin-bottom: 15px;">General Dentistry</p>
                        <p style="color: var(--text-muted); font-size: 0.95rem;">Master of complex alignments and digital smile design solutions.</p>
                    </div>
                </div>
                <div class="premium-card team-card">
                    <div class="team-img-premium" style="color: var(--secondary);"><i class="fas fa-user-doctor"></i></div>
                    <div class="team-info">
                        <h3 style="margin-bottom: 5px;">Dr. Vincent Robert Ompoc</h3>
                        <p style="color: var(--secondary); font-weight: 600; font-size: 0.9rem; margin-bottom: 15px;">General Dentistry</p>
                        <p style="color: var(--text-muted); font-size: 0.95rem;">Specialist in precision oral reconstruction and dental implantology.</p>
                    </div>
                </div>
                <div class="premium-card team-card">
                    <div class="team-img-premium" style="color: var(--accent);"><i class="fas fa-user-doctor"></i></div>
                    <div class="team-info">
                        <h3 style="margin-bottom: 5px;">Dr. Sofia Blanco</h3>
                        <p style="color: var(--secondary); font-weight: 600; font-size: 0.9rem; margin-bottom: 15px;">General Dentistry</p>
                        <p style="color: var(--text-muted); font-size: 0.95rem;">Creating delightful and anxiety-free dental visits for our young smiles.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="section-full" style="background: linear-gradient(rgba(10, 25, 47, 0.8), rgba(10, 25, 47, 0.8)), url('<?php echo URL_ROOT . ltrim($homeContent['hero_bg_image'], '/'); ?>') center/cover no-repeat fixed;">
        <div class="container">
            <div class="section-header">
                <span class="section-tag" style="background: rgba(255,255,255,0.1); color: white;">Success Stories</span>
                <h2 class="section-title" style="color: white;">Patient Experiences</h2>
                <p class="section-subtitle" style="color: rgba(255,255,255,0.7);">Hear directly from our community about their clinical journeys with us.</p>
            </div>
            <div class="testimonial-carousel-wrap">
                <div class="swiper testimonialSwiper">
                    <div class="swiper-wrapper">
                        <?php if (!empty($featuredTestimonials)): ?>
                            <?php foreach($featuredTestimonials as $t): ?>
                            <div class="swiper-slide">
                                <div class="premium-card testimonial-card-premium">
                                    <p class="testimonial-text">"<?php echo htmlspecialchars($t['feedback']); ?>"</p>
                                    <div class="author-info">
                                        <div class="author-avatar">
                                            <?php if(!empty($t['profile_image'])): ?>
                                                <img src="<?php echo URL_ROOT . htmlspecialchars($t['profile_image']); ?>" 
                                                     alt="Profile" 
                                                     onerror="this.onerror=null; this.parentElement.innerHTML='<i class=\'fas fa-user\'></i>';">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h4 style="font-size: 1rem;"><?php echo htmlspecialchars($t['client_name']); ?></h4>
                                            <div style="color: #f59e0b; font-size: 0.8rem; margin-top: 5px;">
                                                <?php 
                                                for($i = 1; $i <= 5; $i++) {
                                                    echo $i <= $t['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="swiper-slide">
                                <div class="premium-card testimonial-card-premium">
                                    <p class="testimonial-text">"The level of care here is unmatched. I used to be terrified of dentists, but the team made me feel completely at ease."</p>
                                    <div class="author-info">
                                        <div class="author-avatar"><i class="fas fa-user"></i></div>
                                        <div>
                                            <h4 style="font-size: 1rem;">Sarah Jenkins</h4>
                                            <p style="font-size: 0.8rem; color: var(--text-muted);">Patient since 2022</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="swiper-slide">
                                <div class="premium-card testimonial-card-premium">
                                    <p class="testimonial-text">"Finally found a clinic that treats you like family. Professional, clean, and transparent."</p>
                                    <div class="author-info">
                                        <div class="author-avatar"><i class="fas fa-user"></i></div>
                                        <div>
                                            <h4 style="font-size: 1rem;">Michael Tan</h4>
                                            <p style="font-size: 0.8rem; color: var(--text-muted);">Orthodontic Patient</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="swiper-pagination"></div>
                </div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>
        </div>
    </section>

    <!-- Hours & Final CTA -->
    <section class="section-full">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 80px; align-items: center;">
                <div class="premium-card" style="padding: 50px; background: var(--primary); color: white; border: none;">
                    <h3 style="font-size: 2rem; margin-bottom: 30px;">Facility Hours</h3>
                    <div style="display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <span>Mon - Fri</span><span><?php echo htmlspecialchars(str_replace(['Mon - Fri: ', 'Mon-Fri: '], '', $homeContent['hours_week'] ?? '8:00 AM - 6:00 PM')); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <span>Saturday</span><span><?php echo htmlspecialchars(str_replace(['Sat: ', 'Saturday: '], '', $homeContent['hours_sat'] ?? '9:00 AM - 3:00 PM')); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 15px 0;">
                        <span>Sunday</span><span><?php echo htmlspecialchars(str_replace(['Sun: ', 'Sunday: '], '', $homeContent['hours_sun'] ?? 'No Clinic Operations')); ?></span>
                    </div>
                </div>
                <div>
                    <h2 class="section-title" style="text-align: left; font-size: 3.5rem;">Take the first step to a <span style="color: var(--secondary);">better you.</span></h2>
                    <p class="section-subtitle" style="margin-bottom: 40px; text-align: left;">Our clinicians are ready to design your personalized health roadmap. Join the thousands who trust us with their clinical care.</p>
                    <a href="client/new-appointments.php" class="btn-premium" style="padding: 20px 50px; font-size: 1.2rem;">Book Free Consultation</a>
                </div>
            </div>
        </div>
    </section>

    <?php include 'client/includes/client-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        const testimonialCount = <?php echo count($featuredTestimonials ?? []); ?>;
        new Swiper('.testimonialSwiper', {
            slidesPerView: 1,
            spaceBetween: 30,
            loop: testimonialCount > 3,
            autoplay: testimonialCount > 3 ? { delay: 5000, disableOnInteraction: false } : false,
            pagination: { el: '.swiper-pagination', clickable: true },
            navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
            breakpoints: {
                768: { slidesPerView: 2, loop: testimonialCount > 2 },
                1024: { slidesPerView: 3, loop: testimonialCount > 3 }
            }
        });
    </script>
</body>
</html>