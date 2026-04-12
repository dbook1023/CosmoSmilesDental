<?php
/**
 * Reusable Client Header Component
 * 
 * Required variables before including:
 * - $isLoggedIn (bool): Whether the client is logged in
 * - $userName (string): The client's display name
 * - $baseDir (string): Relative path to project root (e.g., '', '../', '../../')
 * - $profileImage (string|null): Path to user's profile image
 */

$isLoggedIn = $isLoggedIn ?? false;
$userName = $userName ?? 'My Account';
$baseDir = $baseDir ?? '';
$profileImage = $profileImage ?? null;

// Calculate initials for fallback
$initials = 'MA';
if ($userName && $userName !== 'My Account') {
    $parts = explode(' ', $userName);
    if (count($parts) >= 2) {
        $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    } else {
        $initials = strtoupper(substr($parts[0], 0, 2));
    }
}
?>
<!-- Header -->
<header>
    <div class="container">
        <nav class="navbar">
            <div class="logo">
                <a href="<?php echo $baseDir; ?>index.php"><img src="<?php echo clean_url('public/assets/images/logo-main-white-1.png'); ?>" alt="Cosmo Smiles Dental"></a>
            </div>
            
            <div class="nav-center">
                <ul class="nav-links">
                    <li><a href="<?php echo $baseDir; ?>index.php">Home</a></li>
                    <li><a href="<?php echo $baseDir; ?>about.php">About Us</a></li>
                    <li><a href="<?php echo $baseDir; ?>services.php">Services</a></li>
                    <li><a href="<?php echo $baseDir; ?>contact.php">Contact</a></li>
                </ul>
            </div>
            
            <div class="nav-right">
                <div class="user-menu">
                    <button class="user-btn">
                        <?php 
                        $displayImage = null;
                        if ($isLoggedIn && $profileImage) {
                            // Standardize path using global clean_url and avatars/ folder
                            $filename = ltrim(basename($profileImage), '/');
                            $finalPath = 'public/uploads/avatar/' . $filename;
                            $displayImage = clean_url($finalPath);
                        }
                        ?>
                        <?php if ($isLoggedIn && $displayImage): ?>
                            <img src="<?php echo $displayImage; ?>" 
                                 alt="Profile" 
                                 class="user-profile-img"
                                 id="headerAvatarImg"
                                 onerror="this.style.display='none'; document.getElementById('headerAvatarFallback').style.display='flex';">
                            <div class="user-initials" id="headerAvatarFallback" style="display: none;"><?php echo htmlspecialchars($initials); ?></div>
                        <?php elseif ($isLoggedIn): ?>
                            <div class="user-initials"><?php echo htmlspecialchars($initials); ?></div>
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i> 
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($userName); ?></span>
                    </button>
                    <div class="user-dropdown">
                        <?php if($isLoggedIn): ?>
                            <a href="<?php echo $baseDir; ?>client/profile.php"><i class="fas fa-user"></i> Profile</a>
                            <a href="<?php echo $baseDir; ?>client/appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a>
                            <a href="<?php echo $baseDir; ?>client/patient-records.php"><i class="fas fa-file-medical"></i> Patient Records</a>
                            <a href="<?php echo $baseDir; ?>client/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        <?php else: ?>
                            <a href="<?php echo $baseDir; ?>client/login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                            <a href="<?php echo $baseDir; ?>client/signup.php"><i class="fas fa-user-plus"></i> Sign Up</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="cs-hamburger">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </nav>
    </div>
    
    <!-- Mobile Menu -->
    <div class="cs-mobile-menu">
        <div class="cs-close-menu">
            <i class="fas fa-times"></i>
        </div>
        <div class="mobile-nav-section">
            <h3>Navigation</h3>
            <ul class="mobile-links">
                <li><a href="<?php echo $baseDir; ?>index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="<?php echo $baseDir; ?>about.php"><i class="fas fa-info-circle"></i> About Us</a></li>
                <li><a href="<?php echo $baseDir; ?>services.php"><i class="fas fa-teeth"></i> Services</a></li>
                <li><a href="<?php echo $baseDir; ?>contact.php"><i class="fas fa-phone"></i> Contact</a></li>
            </ul>
        </div>
        
        <div class="mobile-nav-section">
            <h3>My Account</h3>
            <ul class="mobile-links">
                <?php if($isLoggedIn): ?>
                    <li><a href="<?php echo $baseDir; ?>client/profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="<?php echo $baseDir; ?>client/appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                    <li><a href="<?php echo $baseDir; ?>client/patient-records.php"><i class="fas fa-file-medical"></i> Patient Records</a></li>
                <?php else: ?>
                    <li><a href="<?php echo $baseDir; ?>client/signup.php"><i class="fas fa-user-plus"></i> Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="mobile-nav-section">
            <h3>Quick Actions</h3>
            <ul class="mobile-links">
                <li><a href="<?php echo $baseDir; ?>client/new-appointments.php" class="new-appointment-btn"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
            </ul>
        </div>
        
        <div class="mobile-actions">
            <?php if($isLoggedIn): ?>
                <a href="<?php echo $baseDir; ?>client/logout.php" class="mobile-btn logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            <?php else: ?>
                <a href="<?php echo $baseDir; ?>client/login.php" class="mobile-btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="cs-overlay"></div>

    <!-- Internalized Header JS -->
    <script>
    (function() {
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector('header .cs-hamburger');
            const mobileMenu = document.querySelector('header .cs-mobile-menu');
            const mobileClose = document.querySelector('header .cs-close-menu');
            const overlay = document.querySelector('header .cs-overlay');
            const userBtn = document.querySelector('header .user-btn');
            const userDropdown = document.querySelector('header .user-dropdown');
            
            if (hamburger && mobileMenu && overlay) {
                hamburger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    openMobileMenu();
                });

                if (mobileClose) {
                    mobileClose.addEventListener('click', function(e) {
                        e.stopPropagation();
                        closeMobileMenu();
                    });
                }

                overlay.addEventListener('click', function() {
                    closeMobileMenu();
                });

                document.querySelectorAll('header .mobile-links a, header .mobile-btn').forEach(link => {
                    link.addEventListener('click', closeMobileMenu);
                });

                function openMobileMenu() {
                    mobileMenu.classList.add('active');
                    overlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }

                function closeMobileMenu() {
                    mobileMenu.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }

            // User dropdown click for mobile/touch
            if (userBtn && userDropdown) {
                userBtn.addEventListener('click', function(e) {
                    if (window.innerWidth <= 992) {
                        e.preventDefault();
                        e.stopPropagation();
                        // Toggle display style directly for mobile to override hover
                        const isVisible = userDropdown.style.display === 'block';
                        userDropdown.style.display = isVisible ? 'none' : 'block';
                    }
                });

                document.addEventListener('click', function(e) {
                    if (!userBtn.contains(e.target) && window.innerWidth <= 992) {
                        userDropdown.style.display = 'none';
                    }
                });
            }

            // Cleanup on resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 992) {
                    if (mobileMenu) mobileMenu.classList.remove('active');
                    if (overlay) overlay.classList.remove('active');
                    document.body.style.overflow = '';
                    if (hamburger) hamburger.innerHTML = '<i class="fas fa-bars"></i>';
                    if (userDropdown) userDropdown.style.display = ''; // Reset to CSS hover control
                }
            });
        });
    })();
    </script>
</header>
