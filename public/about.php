<?php

// index.php is in: Cosmo Smiles Dental/public/assets/index.php
session_start();

// Include necessary files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Controllers/ContactController.php';
require_once __DIR__ . '/../src/Controllers/SiteContentController.php';

$siteContentController = new SiteContentController();
$aboutContent = $siteContentController->getFlatContent('about');

// Enhanced session handling with security
$isLoggedIn = isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true;

// Initialize variables
$userName = 'My Account';
$clientFullName = '';
$clientEmail = '';
$clientPhone = '';
// If logged in, get client details for pre-filling
if ($isLoggedIn) {
    // Get user name for display
    $firstName = $_SESSION['client_first_name'] ?? '';
    $lastName = $_SESSION['client_last_name'] ?? '';
    $userName = trim($firstName . ' ' . $lastName);
    
    // Standardized: client_id is numeric, client_client_id is varchar
    $client_id = $_SESSION['client_id'] ?? null;
    $client_client_id = $_SESSION['client_client_id'] ?? null;
    
    // If both names are empty, show generic name
    if (empty($userName)) {
        $userName = 'My Account';
    }

    // Get user profile image if logged in
    $profileImage = null;
    if ($isLoggedIn && $client_id) {
        $sql = "SELECT profile_image FROM clients WHERE client_id = :client_id OR id = :id LIMIT 1";
        try {
            $pdo = (new Database())->getConnection();
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="assets/images/logo1-white.png">
    <title>Cosmo Smiles Dental</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <?php 
    include 'client/includes/client-header-css.php'; 
    include 'client/includes/client-common-styles.php';
    include 'client/includes/client-footer-css.php';
    ?>
    <style>
        .page-hero {
            position: relative;
            padding: 160px 0 100px;
            background: linear-gradient(to right, rgba(3, 7, 79, 0.9), rgba(3, 7, 79, 0.6)), url('https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?q=80&w=2070&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
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

        .about-flex {
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            gap: 100px;
            width: 100%;
        }

        .about-image {
            flex: 1;
            position: relative;
        }

        .about-image img {
            width: 100%;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            display: block;
        }

        .about-image::after {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 100px;
            height: 100px;
            background: var(--accent-soft);
            z-index: -1;
            border-radius: 20px;
        }

        .about-content {
            flex: 1.2;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stats-flex {
            display: flex;
            gap: 20px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .stat-item {
            padding: 20px 25px;
            background: white;
            border-radius: 15px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: var(--shadow-sm);
            flex: 1 1 200px;
            min-width: 200px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--secondary);
        }

        .stat-item i {
            font-size: 1.8rem;
            color: var(--secondary);
            position: absolute;
            top: 15px;
            right: 15px;
            opacity: 0.15;
        }

        .stat-item h3 {
            font-size: 2.2rem;
            color: var(--primary);
            font-family: 'Montserrat', sans-serif;
            line-height: 1;
            margin-bottom: 8px;
            font-weight: 800;
        }

        .stat-item p {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        .vm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .vm-card {
            padding: 50px 40px;
            position: relative;
            overflow: hidden;
        }

        .vm-icon {
            width: 60px;
            height: 60px;
            background: var(--accent-soft);
            color: var(--secondary);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            position: absolute;
            top: 20px;
            right: 20px;
            opacity: 0.6;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .value-card {
            padding: 40px 30px;
            text-align: center;
        }

        .value-card i {
            font-size: 2.2rem;
            color: var(--secondary);
            margin-bottom: 20px;
            display: block;
        }

        @media (max-width: 992px) {
            .about-flex { flex-direction: column !important; text-align: center; gap: 50px; }
            .about-content { padding-right: 0; }
            .about-content .section-title, .about-content .section-subtitle { text-align: center !important; }
            .stats-flex { justify-content: center; }
        }

        @media (max-width: 768px) {
            .page-hero { padding: 120px 0 80px; }
            .page-hero h1 { font-size: 2.2rem !important; }
            .stat-item { flex: 1 1 100%; }
            .vm-grid, .values-grid { grid-template-columns: 1fr; }
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
            <span class="section-tag"><?php echo htmlspecialchars($aboutContent['about_tag'] ?? 'Established 2018'); ?></span>
            <h1 class="section-title" style="font-size: 2.8rem;">Our Story & <span style="color: var(--secondary);">Clinical Excellence</span></h1>
            <p class="section-subtitle" style="max-width: 700px; margin: 0 auto;">Pioneering advanced dental solutions with a heart for patient-centered medical care.</p>
        </div>
    </section>
 
    <!-- About Section -->
    <section class="section-padding">
        <div class="container">
            <div class="about-flex">
                <div class="about-image">
                    <img src="<?php echo !empty($aboutContent['about_img']) ? htmlspecialchars(ltrim($aboutContent['about_img'], '/')) : 'assets/images/team1.jpg'; ?>" alt="The Cosmo Team">
                </div>
                <div class="about-content">
                    <span class="section-tag">Who We Are</span>
                    <h2 class="section-title" style="text-align: left; margin-bottom: 25px;"><?php echo isset($aboutContent['about_title']) ? nl2br(htmlspecialchars($aboutContent['about_title'])) : 'Pioneering Modern <br>Family Dentistry'; ?></h2>
                    <p class="section-subtitle" style="text-align: left; margin-bottom: 25px; font-size: 1.1rem; line-height: 1.8;"><?php echo isset($aboutContent['about_description']) ? nl2br(htmlspecialchars($aboutContent['about_description'])) : 'With nearly a decade of dedicated service, our team of board-certified clinicians is committed to delivering state-of-the-art results in a compassionate, hospital-grade environment.\n\nWe integrate the latest global innovations in implantology and aesthetic design to ensure every procedure is precise, safe, and tailored to your unique clinical roadmap.'; ?></p>

                    
                    <div class="stats-flex">
                        <?php for($i=1; $i<=2; $i++): ?>
                            <?php if(!empty($aboutContent['stat_'.$i.'_num'])): ?>
                            <div class="stat-item">
                                <i class="<?php echo htmlspecialchars($aboutContent['stat_'.$i.'_icon'] ?? 'fas fa-star'); ?>"></i>
                                <h3><?php echo htmlspecialchars($aboutContent['stat_'.$i.'_num']); ?></h3>
                                <p><?php echo htmlspecialchars($aboutContent['stat_'.$i.'_label'] ?? ''); ?></p>
                            </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if(empty($aboutContent['stat_1_num'])): ?>
                        <div class="stat-item">
                            <i class="fas fa-stethoscope"></i>
                            <h3>6+</h3>
                            <p>Years Clinical Mastery</p>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-certificate"></i>
                            <h3>12k+</h3>
                            <p>Transformations</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Vision & Mission -->
    <section class="section-padding" style="background: var(--bg-light);">
        <div class="container">
            <div class="vm-grid">
                <div class="premium-card vm-card">
                    <div class="vm-icon"><i class="<?php echo htmlspecialchars($aboutContent['vision_icon'] ?? 'fas fa-eye'); ?>"></i></div>
                    <h3 class="section-title" style="font-size: 1.8rem; text-align: left;"><?php echo htmlspecialchars($aboutContent['vision_title'] ?? 'The Vision'); ?></h3>
                    <p class="section-subtitle" style="text-align: left;"><?php echo htmlspecialchars($aboutContent['vision_desc'] ?? 'To lead as the gold standard in community clinical care, recognized for elevating oral health through innovation and clinical integrity.'); ?></p>
                </div>
                <div class="premium-card vm-card">
                    <div class="vm-icon"><i class="<?php echo htmlspecialchars($aboutContent['mission_icon'] ?? 'fas fa-bullseye'); ?>"></i></div>
                    <h3 class="section-title" style="font-size: 1.8rem; text-align: left;"><?php echo htmlspecialchars($aboutContent['mission_title'] ?? 'The Mission'); ?></h3>
                    <p class="section-subtitle" style="text-align: left;"><?php echo htmlspecialchars($aboutContent['mission_desc'] ?? 'To empower our community with the confidence of a healthy smile, provided through safe, transparent, and empathetic medical procedures.'); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Values -->
    <section class="section-padding">
        <div class="container">
            <div class="section-header">
                <span class="section-tag">Our Foundations</span>
                <h2 class="section-title">The Principles We Uphold</h2>
                <p class="section-subtitle">Our clinical philosophy is built on four non-negotiable pillars of excellence.</p>
            </div>
            <div class="values-grid">
                <?php for($i=1; $i<=6; $i++): ?>
                    <?php if(!empty($aboutContent['value_'.$i.'_title'])): ?>
                    <div class="premium-card value-card">
                        <i class="<?php echo htmlspecialchars($aboutContent['value_'.$i.'_icon'] ?? 'fas fa-star'); ?>"></i>
                        <h4><?php echo htmlspecialchars($aboutContent['value_'.$i.'_title']); ?></h4>
                        <p class="section-subtitle" style="font-size: 0.95rem;"><?php echo htmlspecialchars($aboutContent['value_'.$i.'_desc'] ?? ''); ?></p>
                    </div>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if(empty($aboutContent['value_1_title'])): ?>
                <div class="premium-card value-card">
                    <i class="fas fa-heart"></i>
                    <h4>Human First</h4>
                    <p class="section-subtitle" style="font-size: 0.95rem;">Treating every patient with dignity, empathy, and personalized clinical attention.</p>
                </div>
                <div class="premium-card value-card">
                    <i class="fas fa-microscope"></i>
                    <h4>Tech Precision</h4>
                    <p class="section-subtitle" style="font-size: 0.95rem;">Leveraging digital diagnostics for unmatched surgical and aesthetic accuracy.</p>
                </div>
                <div class="premium-card value-card">
                    <i class="fas fa-shield-halved"></i>
                    <h4>Clinical Safety</h4>
                    <p class="section-subtitle" style="font-size: 0.95rem;">Absolute adherence to global sterilization and bio-safety protocols.</p>
                </div>
                <div class="premium-card value-card">
                    <i class="fas fa-handshake"></i>
                    <h4>Integrity</h4>
                    <p class="section-subtitle" style="font-size: 0.95rem;">Unwavering transparency in pricing, treatment planning, and medical outcomes.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'client/includes/client-footer.php'; ?>

    <script src="assets/js/script.js"></script>
</body>
</html>
