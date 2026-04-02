<?php
// client-footer.php
// $baseDir is used for path resolution
if (!isset($baseDir)) $baseDir = '';
?>
<footer>
    <div class="container">
        <div class="footer-grid">
            <!-- Clinic Info -->
            <div class="footer-col footer-about">
                <h3><?php echo htmlspecialchars($clinicInfo['name'] ?? 'Cosmo Smiles Dental'); ?></h3>
                <p><?php echo htmlspecialchars($clinicInfo['footer_desc'] ?? 'Establishing healthy smiles through exceptional care and advanced dental practices. With 6+ years of experience, we deliver personalized dental solutions for the whole family.'); ?></p>
                <div class="footer-socials">
                    <a href="https://www.facebook.com/profile.php?id=100063660475340" target="_blank" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.waze.com/live-map/directions/ph/calabarzon/binangonan/cosmo-smiles-dental-clinic?to=place.ChIJ3Z21dojHlzMRvazDzgFbayk" target="_blank" title="Waze"><i class="fa-brands fa-waze"></i></a>
                    <a href="https://www.google.com/search?q=cosmo+smiles+dental+clinic" target="_blank" title="Google"><i class="fab fa-google"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-col">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="<?php echo $baseDir; ?>index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                    <li><a href="<?php echo $baseDir; ?>about.php"><i class="fas fa-chevron-right"></i> About Us</a></li>
                    <li><a href="<?php echo $baseDir; ?>services.php"><i class="fas fa-chevron-right"></i> Our Services</a></li>
                    <li><a href="<?php echo $baseDir; ?>contact.php"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                    <li><a href="<?php echo $baseDir; ?>client/new-appointments.php"><i class="fas fa-chevron-right"></i> Book Appointment</a></li>
                </ul>
            </div>

            <!-- Services -->
            <div class="footer-col">
                <h3>Our Services</h3>
                <ul class="footer-links">
                    <li><a href="<?php echo $baseDir; ?>services.php"><i class="fas fa-plus"></i> General Dentistry</a></li>
                    <li><a href="<?php echo $baseDir; ?>services.php"><i class="fas fa-plus"></i> Cosmetic Dentistry</a></li>
                    <li><a href="<?php echo $baseDir; ?>services.php"><i class="fas fa-plus"></i> Orthodontic Treatment</a></li>
                    <li><a href="<?php echo $baseDir; ?>services.php"><i class="fas fa-plus"></i> Oral Surgery</a></li>
                    <li><a href="<?php echo $baseDir; ?>services.php"><i class="fas fa-plus"></i> Dental Implants</a></li>
                </ul>
            </div>

            <!-- Contact Details -->
            <div class="footer-col">
                <h3>Contact Us</h3>
                <div class="footer-contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div class="footer-contact-info">
                        <h4>Location</h4>
                        <p><?php echo nl2br(htmlspecialchars($clinicInfo['address'] ?? '703-F National Road Tayuman Binangonan, Rizal')); ?></p>
                    </div>
                </div>
                <div class="footer-contact-item">
                    <i class="fas fa-phone-alt"></i>
                    <div class="footer-contact-info">
                        <h4>Phone</h4>
                        <p><?php echo htmlspecialchars($clinicInfo['phone'] ?? '0926 649 2903'); ?></p>
                    </div>
                </div>
                <div class="footer-contact-item">
                    <i class="fas fa-envelope"></i>
                    <div class="footer-contact-info">
                        <h4>Email</h4>
                        <p><?php echo htmlspecialchars($clinicInfo['email'] ?? 'cosmosmilesdental@gmail.com'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Cosmo Smiles Dental Clinic. All Rights Reserved.</p>
            <div class="footer-bottom-links">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>
