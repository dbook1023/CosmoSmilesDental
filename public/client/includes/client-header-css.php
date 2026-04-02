<!-- Shared Client Header CSS -->
<style>
    /* Header & Navigation Styles */
    header {
        background-color: #03074f;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        position: fixed;
        width: 100%;
        top: 0;
        z-index: 1000;
        padding: 10px 0;
    }

    .navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 5px;
        position: relative;
    }

    .logo img {
        height: 60px;
        width: auto;
    }

    .nav-center {
        display: flex;
        justify-content: center;
        flex: 1;
    }

    .nav-links {
        display: flex;
        list-style: none;
    }

    .nav-links li {
        margin: 0 20px;
        position: relative;
    }

    .nav-links li a {
        color: white;
        text-decoration: none;
        font-weight: 400;
        font-size: 1rem;
        transition: color 0.3s;
        text-transform: capitalize;
        padding: 10px 0;
        position: relative;
    }

    .nav-links li a::after {
        content: '';
        position: absolute;
        width: 0;
        height: 2px;
        bottom: 0;
        left: 0;
        background-color: #0d5bb9;
        transition: width 0.3s ease;
    }

    .nav-links li a:hover {
        color: #6ca8f0;
    }

    .nav-links li a:hover::after {
        width: 100%;
    }

    /* User Menu & Profile Styles */
    .nav-right {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .user-menu {
        position: relative;
        padding: 10px 0; /* Add padding to bridge the gap */
    }

    .user-btn {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        padding: 8px 20px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        font-family: inherit;
        font-size: 0.9rem;
        transition: all 0.3s;
        font-weight: 500;
    }

    .user-btn:hover {
        background: var(--accent, #6ca8f0);
    }

    .user-btn i {
        font-size: 1.2rem;
    }

    .user-profile-img {
        width: 25px;
        height: 25px;
        border-radius: 50%;
        object-fit: cover;
    }

    .user-initials {
        width: 25px;
        height: 25px;
        background: var(--secondary, #0d5bb9);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .user-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border-radius: 8px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        min-width: 200px;
        display: none;
        overflow: hidden;
        z-index: 1001;
    }

    /* Keep dropdown open when hovering over the menu area */
    .user-menu:hover .user-dropdown {
        display: block;
    }

    /* Use a pseudo-element bridge to prevent closing on gap */
    .user-dropdown::before {
        content: '';
        position: absolute;
        top: -15px;
        left: 0;
        width: 100%;
        height: 15px;
        background: transparent;
    }

    .user-dropdown a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 20px;
        color: #333;
        text-decoration: none;
        font-size: 0.9rem;
        transition: background 0.3s;
        border-bottom: 1px solid #f0f0f0;
    }

    .user-dropdown a:last-child {
        border-bottom: none;
    }

    .user-dropdown a:hover {
        background: #f8f9fa;
        color: #03074f;
    }

    /* Mobile Styles */
    .cs-hamburger {
        display: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 5px;
        transition: transform 0.3s ease;
    }

    .cs-hamburger:active {
        transform: scale(0.9);
    }

    .cs-mobile-menu {
        position: fixed;
        top: 0;
        right: -100%;
        width: 100%;
        height: 100vh;
        background: white;
        z-index: 3000;
        padding: 60px 30px;
        transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    .cs-mobile-menu.active {
        right: 0;
    }

    .cs-close-menu {
        position: absolute;
        top: 20px;
        right: 20px;
        font-size: 1.8rem;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.3s ease;
    }

    .cs-close-menu:hover {
        transform: rotate(90deg);
        color: #0d5bb9;
    }

    .mobile-nav-section {
        margin-bottom: 25px;
    }

    .mobile-nav-section h3 {
        font-size: 0.8rem;
        text-transform: uppercase;
        color: #999;
        letter-spacing: 1px;
        margin-bottom: 10px;
        padding-bottom: 5px;
        border-bottom: 1px solid #eee;
    }

    .mobile-links {
        list-style: none;
    }

    .mobile-links li {
        margin-bottom: 5px;
    }

    .mobile-links li a {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px 15px;
        color: #333;
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.3s;
        font-weight: 500;
    }

    .mobile-links li a:hover {
        background: #f0f7ff;
        color: #0d5bb9;
        transform: translateX(5px);
    }

    .mobile-links li a i {
        width: 20px;
        text-align: center;
        color: #0d5bb9;
    }

    .mobile-actions {
        margin-top: 20px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .mobile-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 12px;
        border-radius: 6px;
        background: #03074f;
        color: white;
        text-decoration: none;
        font-weight: 600;
    }

    .mobile-btn.logout {
        background: #f8d7da;
        color: #721c24;
    }

    .cs-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        display: none;
        z-index: 2500;
        backdrop-filter: blur(3px);
    }

    .cs-overlay.active {
        display: block;
    }

    @media (max-width: 992px) {
        .nav-center {
            display: none;
        }
        
        .cs-hamburger {
            display: block;
        }
        
        .user-menu {
            display: none;
        }
    }
</style>
