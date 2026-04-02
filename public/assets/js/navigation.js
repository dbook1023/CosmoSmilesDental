// Navigation Functionality
document.addEventListener('DOMContentLoaded', function() {
  // Mobile Navigation Toggle
  const hamburger = document.querySelector('.hamburger');
  const mobileMenu = document.querySelector('.mobile-menu');
  const overlay = document.querySelector('.overlay');
  
  if (hamburger) {
    hamburger.addEventListener('click', () => {
      mobileMenu.classList.toggle('active');
      overlay.classList.toggle('active');
      document.body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
      
      // Change hamburger icon
      if (mobileMenu.classList.contains('active')) {
        hamburger.innerHTML = '<i class="fas fa-times"></i>';
      } else {
        hamburger.innerHTML = '<i class="fas fa-bars"></i>';
      }
    });
  }
  
  // Close mobile menu when clicking on overlay
  if (overlay) {
    overlay.addEventListener('click', () => {
      mobileMenu.classList.remove('active');
      overlay.classList.remove('active');
      document.body.style.overflow = '';
      if (hamburger) hamburger.innerHTML = '<i class="fas fa-bars"></i>';
    });
  }
  
  // Close mobile menu when clicking on a link
  document.querySelectorAll('.mobile-links a, .mobile-btn').forEach(link => {
    link.addEventListener('click', () => {
      mobileMenu.classList.remove('active');
      overlay.classList.remove('active');
      document.body.style.overflow = '';
      if (hamburger) hamburger.innerHTML = '<i class="fas fa-bars"></i>';
    });
  });
  
  // User Dropdown Functionality
  const userMenu = document.querySelector('.user-menu');
  const userBtn = document.querySelector('.user-btn');
  const userDropdown = document.querySelector('.user-dropdown');
  
  if (userBtn && userDropdown) {
    // Toggle dropdown on button click
    userBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      userDropdown.classList.toggle('show');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      if (!userMenu.contains(e.target)) {
        userDropdown.classList.remove('show');
      }
    });
    
    // Close dropdown when clicking on dropdown items
    userDropdown.addEventListener('click', (e) => {
      if (e.target.tagName === 'A') {
        userDropdown.classList.remove('show');
      }
    });
    
    // Close dropdown on escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        userDropdown.classList.remove('show');
      }
    });
  }
  
  // Add scroll effect to navbar
  window.addEventListener('scroll', () => {
    const header = document.querySelector('header');
    if (header) {
      if (window.scrollY > 100) {
        header.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.1)';
        header.style.background = 'var(--primary)';
      } else {
        header.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
        header.style.background = 'var(--primary)';
      }
    }
  });
});