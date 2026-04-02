/**
 * Cosmo Smiles - Global Utility Scripts
 * Handles global interactions like smooth scrolling.
 * Navigation and Mobile Menu logic is handled internally by client-header.php
 */

document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            
            if (targetId === '#' || !targetId) return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                
                // Calculate header height dynamically with fallback
                const header = document.querySelector('header');
                const headerHeight = header ? header.offsetHeight : 80;
                
                // Optimized target position
                const targetPosition = targetElement.offsetTop - headerHeight - 20;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
                
                // Close mobile menu if open (supports .cs-mobile-menu from new header)
                const mobileMenu = document.querySelector('.cs-mobile-menu');
                const overlay = document.querySelector('.cs-overlay');
                
                if (mobileMenu && mobileMenu.classList.contains('active')) {
                    mobileMenu.classList.remove('active');
                    if (overlay) overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }
        });
    });
});