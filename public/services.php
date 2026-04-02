<?php
// index.php is in: Cosmo Smiles Dental/public/assets/index.php
session_start();

// Include necessary files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Controllers/ContactController.php';
require_once __DIR__ . '/../src/Controllers/SiteContentController.php';

$siteContentController = new SiteContentController();
$servicesContent = $siteContentController->getFlatContent('services');

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
            require_once __DIR__ . '/../config/database.php';
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
            background: linear-gradient(to right, rgba(3, 7, 79, 0.95), rgba(3, 7, 79, 0.65)), 
                        url('https://images.unsplash.com/photo-1598256989800-fe5f95da9787?q=80&w=2070&auto=format&fit=crop') no-repeat center center / cover !important;
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

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
        }

        .service-card {
            padding: 45px 35px;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .service-icon {
            width: 60px;
            height: 60px;
            background: var(--accent-soft);
            color: var(--secondary);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 25px;
        }

        .service-card h3 {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .service-card p {
            font-size: 1rem;
            color: var(--text-muted);
            margin-bottom: 30px;
            flex-grow: 1;
        }

        .tech-flex {
            display: flex;
            align-items: center;
            gap: 80px;
        }

        .tech-content {
            flex: 1.2;
        }

        .tech-image {
            flex: 1;
        }

        .tech-image img {
            width: 100%;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
        }

        .tech-list {
            list-style: none;
            margin-top: 30px;
        }

        .tech-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .tech-list i {
            color: var(--secondary);
            font-size: 1.1rem;
        }

        @media (max-width: 992px) {
            .tech-flex { flex-direction: column; text-align: center; gap: 50px; }
            .tech-list { display: inline-block; text-align: left; }
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
            <span class="section-tag">Clinical Expertise</span>
            <h1 class="section-title" style="font-size: 3.5rem;"><?php echo isset($servicesContent['services_title']) ? nl2br(htmlspecialchars($servicesContent['services_title'])) : 'World-Class <span style="color: var(--secondary);">Dental Solutions</span>'; ?></h1>
            <p class="section-subtitle" style="max-width: 700px; margin: 0 auto;"><?php echo isset($servicesContent['services_subtitle']) ? nl2br(htmlspecialchars($servicesContent['services_subtitle'])) : 'Combining surgical precision with aesthetic mastery to redefine your healthcare experience.'; ?></p>
        </div>
    </section>

    <!-- Services Grid -->
    <section class="section-padding">
        <div class="container">
            <div class="services-grid">
                <?php for($i=1; $i<=6; $i++): ?>
                    <?php if(!empty($servicesContent['service_'.$i.'_title'])): ?>
                    <div class="premium-card service-card">
                        <div class="service-icon"><i class="<?php echo htmlspecialchars($servicesContent['service_'.$i.'_icon'] ?? 'fas fa-star'); ?>"></i></div>
                        <h3 class="premium-font"><?php echo htmlspecialchars($servicesContent['service_'.$i.'_title']); ?></h3>
                        <p><?php echo htmlspecialchars($servicesContent['service_'.$i.'_desc'] ?? ''); ?></p>
                        <a href="client/new-appointments.php" class="btn-premium btn-outline">Consult Now</a>
                    </div>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if(empty($servicesContent['service_1_title'])): ?>
                <div class="premium-card service-card">
                    <div class="service-icon"><i class="fas fa-tooth"></i></div>
                    <h3 class="premium-font">General Dentistry</h3>
                    <p>Foundational care including advanced cleanings, precision fillings, and comprehensive health monitoring.</p>
                    <a href="client/new-appointments.php" class="btn-premium btn-outline">Schedule Exam</a>
                </div>
                <div class="premium-card service-card">
                    <div class="service-icon"><i class="fas fa-magic"></i></div>
                    <h3 class="premium-font">Aesthetic Design</h3>
                    <p>Clinical whitening, porcelain veneers, and complete smile reconstruction for an unmatched outcome.</p>
                    <a href="client/new-appointments.php" class="btn-premium btn-outline">Design My Smile</a>
                </div>
                <div class="premium-card service-card">
                    <div class="service-icon"><i class="fas fa-teeth"></i></div>
                    <h3 class="premium-font">Orthodontics</h3>
                    <p>Specialized alignment therapy using modern bracket systems and high-clarity invisible aligners.</p>
                    <a href="client/new-appointments.php" class="btn-premium btn-outline">Consult Ortho</a>
                </div>
                <div class="premium-card service-card">
                    <div class="service-icon"><i class="fas fa-user-md"></i></div>
                    <h3 class="premium-font">Oral Surgery</h3>
                    <p>Expert-led surgical procedures including wisdom assessments and precision clinical extractions.</p>
                    <a href="client/new-appointments.php" class="btn-premium btn-outline">Call Surgeon</a>
                </div>
                <div class="premium-card service-card">
                    <div class="service-icon"><i class="fas fa-crown"></i></div>
                    <h3 class="premium-font">Implantology</h3>
                    <p>Advanced structural restoration using high-grade titanium implants that mirror natural biocompatibility.</p>
                    <a href="client/new-appointments.php" class="btn-premium btn-outline">Restore Health</a>
                </div>
                <div class="premium-card service-card">
                    <div class="service-icon"><i class="fas fa-baby"></i></div>
                    <h3 class="premium-font">Pediatric Focus</h3>
                    <p>Developing lifelong health foundations for our youngest patients in a safe, stress-free setting.</p>
                    <a href="client/new-appointments.php" class="btn-premium btn-outline">Kids Wellness</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Technology Focus -->
    <section class="section-padding" style="background: var(--bg-light);">
        <div class="container">
            <div class="tech-flex">
                <div class="tech-content">
                    <span class="section-tag">Facility Standard</span>
                    <h2 class="section-title" style="text-align: left;"><?php echo htmlspecialchars($servicesContent['tech_title'] ?? 'Modern Clinical Logistics'); ?></h2>
                    <p class="section-subtitle" style="text-align: left;"><?php echo htmlspecialchars($servicesContent['tech_desc'] ?? 'We invest in the highest tiers of medical technology to ensure every clinical action is backed by digital certainty and patient comfort.'); ?></p>
                    
                    <ul class="tech-list">
                        <?php for($i=1; $i<=4; $i++): ?>
                            <?php if(!empty($servicesContent['tech_list_'.$i])): ?>
                                <li><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($servicesContent['tech_list_'.$i]); ?></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if(empty($servicesContent['tech_list_1'])): ?>
                        <li><i class="fas fa-check-circle"></i> Digital Cephalometric Imaging</li>
                        <li><i class="fas fa-check-circle"></i> Bio-compatible Restoration Materials</li>
                        <li><i class="fas fa-check-circle"></i> Hospital-Grade Sterilization Units</li>
                        <li><i class="fas fa-check-circle"></i> Cloud-integrated Patient Management</li>
                        <?php endif; ?>
                    </ul>
                    
                    <a href="contact.php" class="btn-premium" style="margin-top: 40px;">Explore Our Facility</a>
                </div>
                <div class="tech-image">
                    <img src="<?php echo !empty($servicesContent['tech_img']) ? '/Cosmo_Smiles_Dental_Clinic/public' . htmlspecialchars($servicesContent['tech_img']) : 'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?q=80&w=2070&auto=format&fit=crop'; ?>" alt="Advanced Clinical Tech">
                </div>
            </div>
        </div>
    </section>

    <?php include 'client/includes/client-footer.php'; ?>

    <script src="assets/js/script.js"></script>
</body>
</html>