<style>
    /* Premium Footer Styles */
    footer {
        background: #03074F;
        color: white;
        padding: 80px 0 30px;
        position: relative;
        overflow: hidden;
    }

    footer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(90deg, #0d5bb9, #6ca8f0, #0d5bb9);
    }

    .footer-grid {
        display: grid;
        grid-template-columns: 1.5fr 1fr 1fr 1.2fr;
        gap: 40px;
        margin-bottom: 50px;
    }

    .footer-col h3 {
        font-family: 'Inter', sans-serif;
        font-size: 1.5rem;
        margin-bottom: 25px;
        color: #FFF;
        position: relative;
    }

    .footer-about p {
        opacity: 0.8;
        line-height: 1.8;
        margin-bottom: 25px;
        font-size: 0.95rem;
    }

    .footer-socials {
        display: flex;
        gap: 15px;
    }

    .footer-socials a {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        color: white;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .footer-socials a:hover {
        background: #6ca8f0;
        color: #03074F;
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(108, 168, 240, 0.4);
    }

    .footer-links {
        list-style: none;
    }

    .footer-links li {
        margin-bottom: 12px;
    }

    .footer-links a {
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        font-size: 0.95rem;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .footer-links a i {
        font-size: 0.7rem;
        color: #6ca8f0;
        transition: transform 0.3s;
    }

    .footer-links a:hover {
        color: white;
        padding-left: 5px;
    }

    .footer-links a:hover i {
        transform: translateX(3px);
    }

    .footer-contact-item {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
    }

    .footer-contact-item i {
        font-size: 1.2rem;
        color: #0d5bb9;
        margin-top: 3px;
    }

    .footer-contact-info h4 {
        font-size: 1rem;
        margin-bottom: 5px;
        font-weight: 600;
    }

    .footer-contact-info p {
        font-size: 0.9rem;
        opacity: 0.7;
        line-height: 1.5;
    }

    .footer-bottom {
        padding-top: 30px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .footer-bottom p {
        font-size: 0.9rem;
        opacity: 0.6;
    }

    .footer-bottom-links {
        display: flex;
        gap: 20px;
    }

    .footer-bottom-links a {
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        font-size: 0.85rem;
        transition: color 0.3s;
    }

    .footer-bottom-links a:hover {
        color: #6ca8f0;
    }

    @media (max-width: 992px) {
        .footer-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 576px) {
        footer {
            padding: 60px 0 30px;
        }
        .footer-grid {
            grid-template-columns: 1fr;
            gap: 30px;
        }
        .footer-bottom {
            flex-direction: column;
            text-align: center;
        }
    }
</style>
