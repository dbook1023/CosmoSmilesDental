<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --primary: #03074F;
        --secondary: #0D5BB9;
        --accent: #6CA8F0;
        --accent-soft: rgba(108, 168, 240, 0.1);
        --bg-light: #F8FAFC;
        --bg-white: #FFFFFF;
        --text-dark: #1E293B;
        --text-muted: #64748B;
        --white: #FFFFFF;
        
        /* Layered Shadows for Premium Depth */
        --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        
        --transition: all 0.3s ease;
        --radius-lg: 24px;
        --radius-md: 12px;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--bg-white);
        color: var(--text-dark);
        line-height: 1.5;
        overflow-x: hidden;
    }

    h1, h2, h3, h4, .premium-font {
        font-family: 'Montserrat', sans-serif;
        font-weight: 700;
        letter-spacing: -0.01em;
    }

    .container {
        max-width: 1240px;
        margin: 0 auto;
        padding: 0 32px;
    }

    /* Standardized Section Padding */
    .section-padding { padding: 100px 0; }
    
    .section-full {
        min-height: 100vh;
        height: auto;
        width: 100%;
        display: flex;
        align-items: center;
        padding: 100px 0;
        position: relative;
    }
    
    .section-header {
        text-align: center;
        margin-bottom: 50px;
        max-width: 750px;
        margin-left: auto;
        margin-right: auto;
    }

    .section-tag {
        display: inline-block;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--secondary);
        background: var(--accent-soft);
        padding: 6px 14px;
        border-radius: 40px;
        margin-bottom: 16px;
    }

    .section-title {
        font-family: 'Montserrat', sans-serif;
        font-size: 3rem;
        color: var(--primary);
        margin-bottom: 25px;
        line-height: 1.1;
    }

    .section-subtitle {
        font-size: 1.05rem;
        color: var(--text-muted);
        line-height: 1.6;
    }

    /* Standardized Card (Glass/Premium Hybrid) */
    .premium-card {
        background: var(--white);
        border-radius: var(--radius-lg);
        border: 1px solid rgba(0,0,0,0.05);
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        overflow: hidden;
    }

    .premium-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-xl);
        border-color: var(--accent-soft);
    }

    /* Premium Button Stylings */
    .btn-premium {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: var(--primary);
        color: white !important;
        padding: 12px 28px;
        border-radius: var(--radius-md);
        text-decoration: none;
        font-weight: 600;
        transition: var(--transition);
        border: none;
        cursor: pointer;
        font-size: 0.95rem;
    }
    

    .btn-premium:hover {
        background: var(--secondary);
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .btn-premium.btn-outline {
        background: transparent;
        border: 2px solid var(--primary);
        color: var(--primary) !important;
        box-shadow: none;
    }

    .btn-premium.btn-outline:hover {
        background: var(--accent-soft);
        color: var(--secondary) !important;
        border-color: var(--secondary);
    }

    /* Glass Effect */
    .glass-card {
        background: var(--glass);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: var(--shadow-md);
        border-radius: 20px;
        padding: 40px;
        transition: var(--transition);
    }

    .glass-card:hover {
        transform: translateY(-10px);
        box-shadow: var(--shadow-lg);
    }
    /* Global Responsive Utilities */
    @media (max-width: 992px) {
        .section-padding { padding: 60px 0; }
        .section-full { padding: 80px 0; }
        .section-title { font-size: 2.2rem; }
    }

    @media (max-width: 768px) {
        .section-title { font-size: 1.8rem; }
        .section-padding { padding: 40px 0; }
    }
    /* Global Responsive Utilities */
    @media (max-width: 992px) {
        .section-padding { padding: 60px 0; }
        .section-full { padding: 80px 0; }
        .section-title { font-size: 2.2rem; }
    }

    @media (max-width: 768px) {
        .section-title { font-size: 1.8rem; }
        .section-padding { padding: 40px 0; }
    }
    /* Subpage Responsive Adjustments */
    @media (max-width: 992px) {
        .page-hero .section-title { font-size: 2.5rem; }
    }
    @media (max-width: 768px) {
        .page-hero .section-title { font-size: 2rem; }
        .page-hero { padding: 120px 0 60px; }
    }
</style>
