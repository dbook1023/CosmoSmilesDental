<!-- Shared Admin Sidebar & Header CSS -->
<style>
    /* Import Google Fonts */
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap');
/* CSS Variables */
    :root {
        --primary: #03074f;
        --secondary: #0d5bb9;
        --accent: #6ca8f0;
        --light-accent: #e6f0ff;
        --dark: #2c3e50;
        --light: #f8f9fa;
        --text: #333333;
        --white: #ffffff;
        --success: #28a745;
        --error: #dc3545;
        --warning: #ffc107;
        --border: #e1e5e9;
        --sidebar-bg: #f8fafc;
        --sidebar-width: 280px;
        --header-height: 70px;
    }

    /* Reset and Base Styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html {
        scroll-behavior: smooth;
    }

    body {
        color: var(--text);
        background-color: var(--white);
        line-height: 1.6;
        overflow-x: hidden;
        font-family: 'Open Sans', sans-serif;
    }

    .container {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 15px;
    }

    /* Admin Header */
    .admin-header {
        background-color: var(--primary);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        position: fixed;
        width: 100%;
        top: 0;
        z-index: 1000;
        height: var(--header-height);
    }

    .navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 5px;
        position: relative;
        height: 100%;
    }

    .logo {
        display: flex;
        align-items: center;
        z-index: 1001;
    }

    .logo img {
        height: 60px;
        width: auto;
        padding: 5px 0;
        filter: brightness(0) invert(1);
    }

    .header-right {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .hamburger {
        display: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 5px;
        z-index: 1100; /* Higher than header/sidebar */
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
    }

    /* Admin Container Layout */
    .admin-container {
        display: flex;
        min-height: 100vh;
        padding-top: var(--header-height);
    }

    /* Sidebar Styles */
    .admin-sidebar {
        width: var(--sidebar-width);
        background: var(--sidebar-bg);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        position: fixed;
        height: calc(100vh - var(--header-height));
        overflow-y: auto;
        transition: transform 0.3s ease;
    }

    .sidebar-header {
        padding: 25px 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .sidebar-header h3 {
        color: var(--primary);
        font-family: "Inter", sans-serif;
        font-size: 1.3rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .sidebar-nav {
        flex: 1;
        padding: 20px 0;
    }

    .nav-section {
        margin-bottom: 20px;
    }

    .sidebar-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        color: var(--dark);
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
        border-left: 3px solid transparent;
    }

    .sidebar-item:hover {
        background: var(--light-accent);
        color: var(--secondary);
        border-left-color: var(--accent);
    }

    .sidebar-item.active {
        background: var(--primary);
        color: white;
        border-left-color: var(--secondary);
    }

    .sidebar-item i {
        width: 20px;
        text-align: center;
        font-size: 1.1rem;
    }

    .sidebar-item span {
        flex: 1;
        font-weight: 500;
    }

    .sidebar-footer {
        padding: 20px;
        border-top: 1px solid var(--border);
    }

    .admin-profile {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .profile-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
    }

    .profile-info {
        display: flex;
        flex-direction: column;
    }

    .profile-name {
        font-weight: 600;
        color: var(--dark);
        font-size: 0.9rem;
    }

    .profile-role {
        font-size: 0.8rem;
        color: var(--dark);
        opacity: 0.7;
    }

    .logout-btn {
        margin-top: 15px;
        justify-content: center;
        background: var(--light-accent);
    }

    /* Overlay */
    .overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        z-index: 1050; /* Higher than sidebar, lower than modal/hamburger */
    }

    .overlay.active {
        display: block;
    }

    /* Main Content */
    .admin-main {
        flex: 1;
        margin-left: var(--sidebar-width);
        padding: 30px;
        background: #f8fafc;
        min-height: calc(100vh - var(--header-height));
        transition: margin-left 0.3s ease;
    }

    /* Dashboard Header */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 30px;
    }

    .header-content {
        flex: 1;
    }

    .header-content h1 {
        font-family: "Inter", sans-serif;
        color: var(--primary);
        font-size: 2.2rem;
        margin-bottom: 5px;
    }

    .header-content p {
        color: var(--dark);
        opacity: 0.8;
        margin: 0;
    }

    /* Header Actions */
    .header-actions {
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }

    /* Clock card styling */
    .date-display {
        display: flex;
        align-items: center;
        gap: 12px;
        background: #fff;
        padding: 8px 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border: 1px solid rgba(0,0,0,0.05);
        transition: all 0.2s ease;
    }

    .date-display i {
        font-size: 1.3rem;
        color: var(--secondary);
    }

    .clock-content {
        display: flex;
        flex-direction: column;
        line-height: 1.3;
        text-align: left;
    }

    #admin-date {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--dark);
        opacity: 0.7;
        letter-spacing: 0.3px;
    }

    #admin-time {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--primary);
        font-family: 'Monaco', 'Consolas', monospace;
        letter-spacing: 0.5px;
    }

    /* Responsive Sidebar */
    @media (max-width: 992px) {
        .admin-sidebar {
            transform: translateX(-100%);
            z-index: 1080; /* Higher than overlay, lower than hamburger */
            height: 100vh;
            top: 0;
        }

        .admin-sidebar.active {
            transform: translateX(0);
        }

        .admin-main {
            margin-left: 0;
            padding: 15px;
        }

        .hamburger {
            display: block !important;
        }

        .admin-header {
            padding: 0 15px;
        }

        .header-actions {
            gap: 10px;
        }
    }

    @media (max-width: 768px) {
        .dashboard-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }

        .header-content h1 {
            font-size: 1.8rem;
        }

        .header-actions {
            width: 100%;
            justify-content: space-between;
        }

        .date-display {
            padding: 5px 12px;
        }
    }

    @media (max-width: 576px) {
        .admin-header {
            padding: 0 15px;
        }
        
        .navbar {
            flex-wrap: wrap;
            gap: 10px;
        }

        .header-right {
            width: 100%;
            justify-content: flex-end;
            gap: 10px;
        }

        .logo img {
            height: 60px;
        }

        .admin-container {
            padding-top: 80px; /* Adjusted for smaller header */
        }
    }
</style>
