<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records - Cosmo Smiles Dental</title>
    <link rel="icon" type="image/png" href="<?php echo clean_url('public/assets/images/logo1-white.png'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            z-index: 1001;
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

        .nav-section:last-child {
            margin-bottom: 0;
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
            margin-bottom: 30px;
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

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .date-display {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--dark);
            font-weight: 500;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            font-family: "Open Sans", sans-serif;
            font-size: 0.9rem;
        }

        .btn:hover {
            background: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-primary {
            background: var(--secondary);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: var(--dark);
        }

        /* Patient ID Search Section */
        .patient-search-section {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            text-align: center;
        }

        .patient-search-section h3 {
            color: var(--primary);
            margin-bottom: 20px;
            font-size: 1.5rem;
        }

        .search-container {
            display: flex;
            gap: 15px;
            align-items: center;
            justify-content: center;
            max-width: 600px;
            margin: 0 auto;
        }

        .form-group {
            flex: 1;
            max-width: 400px;
        }

        .search-input {
            width: 100%;
            padding: 14px 20px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(108, 168, 240, 0.2);
        }

        .search-btn {
            padding: 14px 30px;
            font-size: 1rem;
        }

        .search-example {
            margin-top: 15px;
            color: var(--dark);
            opacity: 0.7;
            font-size: 0.9rem;
        }

        /* Records Container */
        .records-container {
            display: none; /* Hidden until patient is selected */
        }

        /* Patient Info Bar */
        .patient-info-bar {
            background: white;
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .patient-details {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .patient-avatar-large {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .patient-info-text h4 {
            margin: 0 0 5px 0;
            font-size: 1.2rem;
            color: var(--primary);
        }

        .patient-info-text p {
            margin: 0;
            color: var(--dark);
            opacity: 0.8;
        }

        .patient-stats {
            display: flex;
            gap: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            display: block;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--dark);
            opacity: 0.7;
        }

        /* Records Header */
        .records-header {
            background: white;
            border-radius: 12px 12px 0 0;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
        }

        .records-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--primary);
        }

        /* Records Content */
        .records-content {
            background: white;
            border-radius: 0 0 12px 12px;
            padding: 25px;
            min-height: 400px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        /* Records Actions Bar */
        .records-actions-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        /* Records List */
        .records-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        /* Record Item (List Type) */
        .record-item {
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .record-item:hover {
            border-color: var(--accent);
            background: var(--light-accent);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .record-type-indicator {
            width: 8px;
            height: 60px;
            border-radius: 4px;
            flex-shrink: 0;
        }

        .record-type-indicator.treatment {
            background: #28a745;
        }

        .record-type-indicator.consultation {
            background: #ffc107;
        }

        .record-type-indicator.xray {
            background: #17a2b8;
        }

        .record-type-indicator.prescription {
            background: #dc3545;
        }

        .record-type-indicator.followup {
            background: #6c757d;
        }

        .record-type-indicator.emergency {
            background: #fd7e14;
        }

        .record-main-info {
            flex: 1;
            min-width: 0;
        }

        .record-header-line {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 8px;
        }

        .record-type-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .record-type-badge.treatment {
            background: #d4edda;
            color: #155724;
        }

        .record-type-badge.consultation {
            background: #fff3cd;
            color: #856404;
        }

        .record-type-badge.xray {
            background: #d1ecf1;
            color: #0c5460;
        }

        .record-type-badge.prescription {
            background: #f8d7da;
            color: #721c24;
        }

        .record-title {
            font-weight: 700;
            color: var(--dark);
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .record-details-line {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: var(--dark);
        }

        .record-detail-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .record-detail-item i {
            width: 16px;
            color: var(--secondary);
        }

        .record-description {
            font-size: 0.9rem;
            color: var(--dark);
            opacity: 0.8;
            line-height: 1.5;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .record-date {
            font-size: 0.85rem;
            color: var(--dark);
            opacity: 0.7;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .record-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .record-action-btn {
            padding: 8px;
            border-radius: 4px;
            background: white;
            border: 1px solid var(--border);
            color: var(--dark);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
        }

        .record-action-btn:hover {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--dark);
            opacity: 0.7;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: var(--border);
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--dark);
        }

        /* Success Message */
        .success-message {
            display: none;
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            align-items: center;
            gap: 10px;
        }

        .success-message.active {
            display: flex;
        }

        /* Back to Search Button */
        .back-to-search {
            margin-top: 20px;
            text-align: center;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--primary);
            font-size: 1.3rem;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--dark);
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Record Details Modal */
        .record-details-container {
            padding: 20px 0;
        }

        .record-detail-section {
            margin-bottom: 30px;
        }

        .record-detail-section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            color: var(--primary);
            font-size: 1.1rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-accent);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .detail-item {
            margin-bottom: 12px;
        }

        .detail-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 4px;
            font-size: 0.9rem;
        }

        .detail-value {
            color: var(--dark);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .detail-description {
            background: var(--light-accent);
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .additional-info {
            background: #f8f9fa;
            border-left: 4px solid var(--secondary);
            padding: 15px;
            border-radius: 0 8px 8px 0;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-top: 20px;
        }

        /* Form Styles for Modal */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .required::after {
            content: " *";
            color: var(--error);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            font-family: 'Open Sans', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(108, 168, 240, 0.2);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232c3e50' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 15px;
            padding-right: 40px;
        }

        .form-help {
            font-size: 0.85rem;
            color: var(--dark);
            opacity: 0.7;
            margin-top: 5px;
        }

        /* File Upload in Modal */
        .modal-upload-area {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .modal-upload-area.active {
            border-color: var(--accent);
            background: var(--light-accent);
        }

        .modal-upload-icon {
            font-size: 2.5rem;
            color: var(--secondary);
            margin-bottom: 15px;
        }

        /* File Preview in Modal */
        .modal-file-preview {
            margin-top: 20px;
            display: none;
        }

        .modal-file-preview.active {
            display: block;
        }

        .modal-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
        }

        .modal-preview-item {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
        }

        .modal-preview-icon {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .modal-preview-icon.pdf {
            background: #fff2f0;
            color: #ff4d4f;
        }

        .modal-preview-icon.image {
            background: #f0f7ff;
            color: #1890ff;
        }

        .modal-preview-icon.document {
            background: #f6ffed;
            color: #52c41a;
        }

        .modal-preview-info {
            flex: 1;
            min-width: 0;
        }

        .modal-preview-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 3px;
            word-break: break-word;
            font-size: 0.9rem;
        }

        .modal-preview-size {
            font-size: 0.8rem;
            color: var(--dark);
            opacity: 0.7;
        }

        .modal-preview-remove {
            background: none;
            border: none;
            color: var(--error);
            cursor: pointer;
            font-size: 1rem;
            padding: 5px;
        }

        /* Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .admin-sidebar {
                width: 250px;
            }
            
            .admin-main {
                margin-left: 250px;
            }
        }

        @media (max-width: 992px) {
            .hamburger {
                display: block;
            }
            
            .admin-sidebar {
                transform: translateX(-100%);
                z-index: 999;
            }
            
            .admin-sidebar.active {
                transform: translateX(0);
            }
            
            .admin-main {
                margin-left: 0;
                width: 100%;
            }
            
            .search-container {
                flex-direction: column;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .detail-grid {
                grid-template-columns: 1fr;
            }
            
            .record-details-line {
                flex-wrap: wrap;
                gap: 10px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }
            
            .patient-info-bar {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .patient-stats {
                width: 100%;
                justify-content: space-between;
            }
            
            .admin-main {
                padding: 20px;
            }
            
            .modal-content {
                width: 95%;
            }
            
            .record-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .record-type-indicator {
                width: 100%;
                height: 4px;
            }
            
            .record-actions {
                align-self: flex-end;
            }
        }

        @media (max-width: 576px) {
            .admin-main {
                padding: 15px;
            }
            
            .records-header, .patient-info-bar {
                padding: 15px;
            }
            
            .records-content {
                padding: 15px;
            }
            
            .header-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .modal-body {
                padding: 15px;
            }
            
            .record-actions {
                align-self: stretch;
                justify-content: flex-end;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .patient-search-section, .records-container {
            animation: fadeIn 0.6s ease;
        }
    </style>
</head>
<body>
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">
                    <a href="../index.php"><img src="../assets/images/logo-main-white-1.png" alt="Cosmo Smiles Dental"></a>
                </div>
                
                <div class="header-right">
                    <div class="hamburger">
                        <i class="fas fa-bars"></i>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <!-- Overlay for mobile sidebar -->
    <div class="overlay"></div>

    <!-- Admin Dashboard Layout -->
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-tooth"></i> Staff Dashboard</h3>
            </div>
            
            <nav class="sidebar-nav">
                  <!-- Main Navigation Links -->
                <div class="nav-section">
                    <a href="staff-dashboard.php" class="sidebar-item active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    
                    <a href="staff-appointments.php" class="sidebar-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Appointments</span>
                    </a>
                    
                    <a href="staff-patients.php" class="sidebar-item">
                        <i class="fas fa-users"></i>
                        <span>Patients</span>
                    </a>
                    
                    <a href="staff-records.php" class="sidebar-item">
                        <i class="fas fa-file-medical"></i>
                        <span>Patient Records</span>
                    </a>
                    
                </div>
                
                <!-- Additional Links -->
                <div class="nav-section">
                    <a href="staff-messages.php" class="sidebar-item">
                        <i class="fas fa-comments"></i>
                        <span>Messages</span>
                    </a>
                    
                    <a href="staff-reminders.php" class="sidebar-item">
                        <i class="fas fa-bell"></i>
                        <span>Send Reminders</span>
                    </a>
                    
                    <a href="staff-settings.php" class="sidebar-item">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="admin-profile">
                    <div class="profile-avatar">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="profile-info">
                        <span class="profile-name">Dr. Roberto Garcia</span>
                        <span class="profile-role">Orthodontist</span>
                    </div>
                </div>
                <a href="../staff-login.php" class="sidebar-item logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="header-content">
                    <h1>Patient Records</h1>
                    <p>Access and manage patient medical records and documents</p>
                </div>
                <div class="header-actions">
                    <div class="date-display">
                        <i class="fas fa-calendar"></i>
                        <span id="current-date">Loading...</span>
                    </div>
                </div>
            </div>

            <!-- Patient ID Search Section -->
            <div class="patient-search-section" id="search-section">
                <h3>Enter Patient ID to Access Records</h3>
                <div class="search-container">
                    <div class="form-group">
                        <input type="text" id="patient-id-input" class="search-input" placeholder="Enter Patient ID (e.g., PT-2101)">
                    </div>
                    <button class="btn btn-primary search-btn" id="search-patient-btn">
                        <i class="fas fa-search"></i> Access Records
                    </button>
                </div>
                <div class="search-example">
                    Example: PT-2101, PT-2102, PT-2103
                </div>
            </div>

            <!-- Records Container (Initially Hidden) -->
            <div class="records-container" id="records-container">
                <!-- Patient Info Bar -->
                <div class="patient-info-bar">
                    <div class="patient-details">
                        <div class="patient-avatar-large" id="patient-avatar">JD</div>
                        <div class="patient-info-text">
                            <h4 id="patient-name">Juan Dela Cruz</h4>
                            <p id="patient-id-display">Patient ID: PT-2101</p>
                        </div>
                    </div>
                    <div class="patient-stats">
                        <div class="stat-item">
                            <span class="stat-value" id="total-records">4</span>
                            <span class="stat-label">Total Records</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value" id="last-updated">Today</span>
                            <span class="stat-label">Last Updated</span>
                        </div>
                    </div>
                </div>

                <!-- Records Header -->
                <div class="records-header">
                    <h3>Patient Records</h3>
                    <button class="btn btn-success" id="create-record-btn">
                        <i class="fas fa-file-medical-alt"></i> Create New Record
                    </button>
                </div>

                <!-- Records Content -->
                <div class="records-content">
                    <!-- Records Actions Bar -->
                    <div class="records-actions-bar">
                        <div style="display: flex; gap: 10px;">
                            <button class="btn" id="filter-all-btn" data-filter="all">
                                All Records
                            </button>
                            <button class="btn" id="filter-treatment-btn" data-filter="treatment">
                                Treatments
                            </button>
                            <button class="btn" id="filter-consultation-btn" data-filter="consultation">
                                Consultations
                            </button>
                            <button class="btn" id="filter-xray-btn" data-filter="xray">
                                X-Rays
                            </button>
                        </div>
                        <div style="margin-left: auto;">
                            <button class="btn" id="refresh-btn">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Success Message -->
                    <div class="success-message" id="success-message">
                        <i class="fas fa-check-circle"></i>
                        <span>Patient record created successfully!</span>
                    </div>

                    <!-- Records List -->
                    <div class="records-list" id="records-list">
                        <!-- Record items will be populated by JavaScript -->
                    </div>

                    <!-- Empty State (Initially Hidden) -->
                    <div class="empty-state" id="empty-state" style="display: none;">
                        <i class="fas fa-folder-open"></i>
                        <h3>No records found</h3>
                        <p>Create new records or upload files to get started</p>
                        <button class="btn btn-primary" style="margin-top: 20px;" id="empty-create-btn">
                            <i class="fas fa-file-medical-alt"></i> Create First Record
                        </button>
                    </div>

                    <!-- Back to Search Button -->
                    <div class="back-to-search">
                        <button class="btn" id="back-to-search-btn">
                            <i class="fas fa-search"></i> Search Another Patient
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Record Modal -->
    <div class="modal" id="create-record-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Patient Record</h3>
                <button class="close-modal" id="close-create-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="create-record-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="record-patient-id" class="required">Patient ID</label>
                            <input type="text" id="record-patient-id" class="form-control" required placeholder="Enter Patient ID">
                            <div class="form-help">Enter existing Patient ID (e.g., PT-2101)</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="record-type" class="required">Record Type</label>
                            <select id="record-type" class="form-control" required>
                                <option value="">Select Record Type</option>
                                <option value="treatment">Treatment</option>
                                <option value="consultation">Consultation</option>
                                <option value="xray">X-Ray</option>
                                <option value="prescription">Prescription</option>
                                <option value="followup">Follow-up</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="record-title" class="required">Record Title</label>
                            <input type="text" id="record-title" class="form-control" required placeholder="e.g., Tooth Filling, Root Canal, Dental Check-up">
                        </div>
                        
                        <div class="form-group">
                            <label for="record-date" class="required">Date</label>
                            <input type="date" id="record-date" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="record-time" class="required">Time</label>
                            <input type="time" id="record-time" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="record-dentist" class="required">Dentist</label>
                            <select id="record-dentist" class="form-control" required>
                                <option value="">Select Dentist</option>
                                <option value="Dr. Michael Chen">Dr. Michael Chen</option>
                                <option value="Dr. Roberto Garcia">Dr. Roberto Garcia</option>
                                <option value="Dr. Maria Santos">Dr. Maria Santos</option>
                                <option value="Dr. Emily Rodriguez">Dr. Emily Rodriguez</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="record-duration">Duration</label>
                            <select id="record-duration" class="form-control">
                                <option value="">Select Duration</option>
                                <option value="30 minutes">30 minutes</option>
                                <option value="45 minutes">45 minutes</option>
                                <option value="1 hour">1 hour</option>
                                <option value="1 hour 15 minutes">1 hour 15 minutes</option>
                                <option value="1 hour 30 minutes">1 hour 30 minutes</option>
                                <option value="2 hours">2 hours</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="record-procedure" class="required">Procedure</label>
                            <input type="text" id="record-procedure" class="form-control" required placeholder="e.g., Composite filling, Root canal, Tooth extraction">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="record-description" class="required">Description</label>
                            <textarea id="record-description" class="form-control" rows="4" required placeholder="Detailed description of the procedure..."></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="record-notes">Additional Notes</label>
                            <textarea id="record-notes" class="form-control" rows="3" placeholder="Any additional observations or notes..."></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="record-followup">Follow-up Instructions</label>
                            <textarea id="record-followup" class="form-control" rows="3" placeholder="Instructions for patient follow-up..."></textarea>
                        </div>
                    </div>
                    
                    <!-- File Upload Section in Modal -->
                    <div class="form-group full-width">
                        <label>Attach Files (Optional)</label>
                        <div class="modal-upload-area" id="modal-upload-area">
                            <div class="modal-upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">
                                <h4>Drag & Drop files here</h4>
                                <p>or click to browse files</p>
                                <p style="font-size: 0.8rem;">Supported: PDF, JPG, PNG, DOC, DOCX (Max: 10MB)</p>
                            </div>
                            <input type="file" class="file-input" id="modal-file-input" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        </div>
                        
                        <!-- File Preview in Modal -->
                        <div class="modal-file-preview" id="modal-file-preview">
                            <div style="margin-top: 20px; margin-bottom: 10px; font-weight: 600; color: var(--dark);">
                                Selected Files
                            </div>
                            <div class="modal-preview-grid" id="modal-preview-grid">
                                <!-- File previews will be added here -->
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn" id="cancel-create-record">Cancel</button>
                <button class="btn btn-success" id="save-record-btn">
                    <i class="fas fa-save"></i> Save Patient Record
                </button>
            </div>
        </div>
    </div>

    <!-- View Record Details Modal -->
    <div class="modal" id="view-record-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Record Details</h3>
                <button class="close-modal" id="close-view-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="record-details-container" id="record-details-content">
                    <!-- Record details will be populated here -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" id="close-view-record">Close</button>
                <button class="btn btn-primary" id="edit-record-btn">
                    <i class="fas fa-edit"></i> Edit Record
                </button>
                <button class="btn btn-success" id="download-record-btn">
                    <i class="fas fa-download"></i> Download as PDF
                </button>
            </div>
        </div>
    </div>

    <script>
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.custom-notification');
            existingNotifications.forEach(notification => notification.remove());
            
            const notification = document.createElement('div');
            notification.className = `custom-notification ${type}`;
            
            // Inline styles for independence
            const bgColor = type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : type === 'warning' ? '#ff9800' : '#2196f3';
            notification.style.cssText = `position: fixed; top: 20px; right: 20px; background: ${bgColor}; color: white; padding: 15px 20px; border-radius: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 10px; z-index: 9999; font-family: 'Inter', sans-serif; transition: all 0.3s ease;`;
            
            let icon = 'fa-info-circle';
            if (type === 'success') icon = 'fa-check-circle';
            if (type === 'error') icon = 'fa-exclamation-circle';
            if (type === 'warning') icon = 'fa-exclamation-triangle';
            
            notification.innerHTML = `
                <div class="notification-content" style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas ${icon}"></i>
                    <span class="notification-message" style="white-space: pre-line;">${message}</span>
                </div>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; cursor: pointer; padding-left: 15px;">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }

        // Set current date
        const currentDate = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('current-date').textContent = currentDate.toLocaleDateString('en-PH', options);

        // Mobile sidebar toggle
        const hamburger = document.querySelector('.hamburger');
        const sidebar = document.querySelector('.admin-sidebar');
        const overlay = document.querySelector('.overlay');
        const mainContent = document.querySelector('.admin-main');

        hamburger.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });

        // Close sidebar when clicking on a link (for mobile)
        const sidebarLinks = document.querySelectorAll('.sidebar-item');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                }
            });
        });

        // Sample patient data
        const patients = {
            'PT-2101': {
                name: 'Juan Dela Cruz',
                avatar: 'JD',
                age: 32,
                gender: 'Male',
                lastVisit: '2023-10-15'
            },
            'PT-2102': {
                name: 'Maria Santos',
                avatar: 'MS',
                age: 28,
                gender: 'Female',
                lastVisit: '2023-10-18'
            },
            'PT-2103': {
                name: 'Pedro Reyes',
                avatar: 'PR',
                age: 45,
                gender: 'Male',
                lastVisit: '2023-10-20'
            },
            'PT-2104': {
                name: 'Ana Villanueva',
                avatar: 'AV',
                age: 35,
                gender: 'Female',
                lastVisit: '2023-10-22'
            }
        };

        // Sample patient records matching the format you provided
        const patientRecords = {
            'PT-2101': [
                {
                    id: 'REC-001',
                    type: 'treatment',
                    title: 'Tooth Filling',
                    date: '2023-04-22',
                    time: '14:15',
                    dentist: 'Dr. Michael Chen',
                    duration: '1 hour 15 minutes',
                    procedure: 'Composite filling applied to cavity on lower left molar (#19)',
                    description: 'Composite filling applied to cavity on lower left molar (#19). The cavity was cleaned and prepared before applying the composite material. The filling was then shaped and polished to match the natural tooth contour.',
                    notes: 'Patient reported mild sensitivity to cold drinks. Recommended using sensitivity toothpaste for 2 weeks.',
                    followup: 'Schedule follow-up in 6 months for check-up. Avoid chewing hard foods on the treated side for 24 hours.',
                    files: [],
                    created: '2023-04-22 14:30'
                },
                {
                    id: 'REC-002',
                    type: 'consultation',
                    title: 'Initial Dental Check-up',
                    date: '2023-04-15',
                    time: '10:00',
                    dentist: 'Dr. Roberto Garcia',
                    duration: '45 minutes',
                    procedure: 'Comprehensive dental examination and cleaning',
                    description: 'Complete oral examination including X-rays, periodontal assessment, and oral cancer screening. Professional cleaning performed to remove plaque and tartar.',
                    notes: 'Patient has moderate gingivitis. Recommended improved oral hygiene routine and scheduled deep cleaning.',
                    followup: 'Schedule deep cleaning session in 2 weeks. Maintain proper brushing and flossing techniques.',
                    files: ['xray-front.jpg', 'xray-side.jpg'],
                    created: '2023-04-15 10:45'
                },
                {
                    id: 'REC-003',
                    type: 'xray',
                    title: 'Panoramic X-Ray',
                    date: '2023-04-10',
                    time: '09:30',
                    dentist: 'Dr. Maria Santos',
                    duration: '30 minutes',
                    procedure: 'Full mouth panoramic X-ray imaging',
                    description: 'Panoramic X-ray taken to assess overall dental health, check for impacted teeth, and evaluate jawbone structure.',
                    notes: 'Wisdom teeth are fully erupted and properly aligned. No signs of cysts or tumors detected.',
                    followup: 'No immediate follow-up required. Next routine X-ray in 2 years.',
                    files: ['panoramic-xray-2023-04.jpg'],
                    created: '2023-04-10 10:00'
                },
                {
                    id: 'REC-004',
                    type: 'treatment',
                    title: 'Root Canal Treatment',
                    date: '2023-03-28',
                    time: '11:00',
                    dentist: 'Dr. Michael Chen',
                    duration: '2 hours',
                    procedure: 'Root canal therapy on upper right first molar (#3)',
                    description: 'Complete root canal treatment performed on tooth #3. All canals were cleaned, shaped, and filled with gutta-percha. Temporary filling placed.',
                    notes: 'Tooth was non-vital with periapical radiolucency. Patient reported severe pain prior to treatment.',
                    followup: 'Schedule crown preparation in 2 weeks. Avoid chewing on treated side until permanent restoration.',
                    files: ['root-canal-xray-before.jpg', 'root-canal-xray-after.jpg'],
                    created: '2023-03-28 13:15'
                }
            ],
            'PT-2102': [
                {
                    id: 'REC-005',
                    type: 'consultation',
                    title: 'Orthodontic Consultation',
                    date: '2023-04-20',
                    time: '15:30',
                    dentist: 'Dr. Emily Rodriguez',
                    duration: '1 hour',
                    procedure: 'Orthodontic assessment and treatment planning',
                    description: 'Comprehensive orthodontic evaluation including bite analysis, space assessment, and treatment options discussion.',
                    notes: 'Patient has Class II malocclusion with overjet. Good candidate for clear aligner therapy.',
                    followup: 'Schedule impressions for clear aligners in 1 week.',
                    files: ['ortho-photos-front.jpg', 'ortho-photos-side.jpg'],
                    created: '2023-04-20 16:30'
                }
            ]
        };

        // State management
        let currentPatientId = null;
        let currentRecords = [];
        let currentFilter = 'all';

        // DOM Elements
        const searchSection = document.getElementById('search-section');
        const recordsContainer = document.getElementById('records-container');
        const searchBtn = document.getElementById('search-patient-btn');
        const patientIdInput = document.getElementById('patient-id-input');
        const patientName = document.getElementById('patient-name');
        const patientIdDisplay = document.getElementById('patient-id-display');
        const patientAvatar = document.getElementById('patient-avatar');
        const totalRecords = document.getElementById('total-records');
        const lastUpdated = document.getElementById('last-updated');
        const recordsList = document.getElementById('records-list');
        const emptyState = document.getElementById('empty-state');
        const backToSearchBtn = document.getElementById('back-to-search-btn');
        const createRecordBtn = document.getElementById('create-record-btn');
        const successMessage = document.getElementById('success-message');
        const emptyCreateBtn = document.getElementById('empty-create-btn');

        // Patient search functionality
        searchBtn.addEventListener('click', () => {
            const patientId = patientIdInput.value.trim().toUpperCase();
            
            if (!patientId) {
                showNotification('Please enter a Patient ID', 'error');
                return;
            }
            
            if (patients[patientId]) {
                // Show records container
                searchSection.style.display = 'none';
                recordsContainer.style.display = 'block';
                
                // Set current patient
                currentPatientId = patientId;
                
                // Update patient info
                const patient = patients[patientId];
                patientName.textContent = patient.name;
                patientIdDisplay.textContent = `Patient ID: ${patientId}`;
                patientAvatar.textContent = patient.avatar;
                
                // Hide success message
                successMessage.classList.remove('active');
                
                // Load records
                loadPatientRecords();
            } else {
                showNotification(`Patient with ID ${patientId} not found. Please check the ID and try again.`, 'error');
            }
        });

        // Also allow Enter key to search
        patientIdInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                searchBtn.click();
            }
        });

        // Back to search functionality
        backToSearchBtn.addEventListener('click', () => {
            recordsContainer.style.display = 'none';
            searchSection.style.display = 'block';
            patientIdInput.value = '';
            patientIdInput.focus();
        });

        // Load patient records
        function loadPatientRecords() {
            recordsList.innerHTML = '';
            const allRecords = patientRecords[currentPatientId] || [];
            
            // Apply filter
            currentRecords = currentFilter === 'all' 
                ? allRecords 
                : allRecords.filter(record => record.type === currentFilter);
            
            if (currentRecords.length === 0) {
                emptyState.style.display = 'block';
                recordsList.style.display = 'none';
                totalRecords.textContent = '0';
                lastUpdated.textContent = 'No records';
                return;
            }
            
            emptyState.style.display = 'none';
            recordsList.style.display = 'flex';
            
            // Sort records by date (newest first)
            currentRecords.sort((a, b) => new Date(b.date + ' ' + b.time) - new Date(a.date + ' ' + a.time));
            
            // Create record items
            currentRecords.forEach((record, index) => {
                const recordItem = createRecordListItem(record, index);
                recordsList.appendChild(recordItem);
            });
            
            // Update stats
            totalRecords.textContent = allRecords.length;
            const latestRecord = allRecords[0];
            if (latestRecord) {
                lastUpdated.textContent = formatDate(latestRecord.date);
            }
        }

        // Create record list item
        function createRecordListItem(record, index) {
            const item = document.createElement('div');
            item.className = 'record-item';
            item.setAttribute('data-record-id', record.id);
            
            // Get type class and label
            const typeClass = record.type;
            const typeLabel = getTypeLabel(record.type);
            
            // Format date and time
            const formattedDate = formatDate(record.date);
            const formattedTime = formatTime(record.time);
            const formattedDateTime = `${formattedDate} at ${formattedTime}`;
            
            item.innerHTML = `
                <div class="record-type-indicator ${typeClass}"></div>
                <div class="record-main-info">
                    <div class="record-header-line">
                        <span class="record-type-badge ${typeClass}">${typeLabel}</span>
                        <span class="record-date">
                            <i class="far fa-calendar"></i> ${formattedDate}
                        </span>
                    </div>
                    <div class="record-title">${record.title}</div>
                    <div class="record-details-line">
                        <div class="record-detail-item">
                            <i class="fas fa-user-md"></i>
                            <span>${record.dentist}</span>
                        </div>
                        <div class="record-detail-item">
                            <i class="fas fa-clock"></i>
                            <span>${record.duration}</span>
                        </div>
                        <div class="record-detail-item">
                            <i class="fas fa-stethoscope"></i>
                            <span>${record.procedure.substring(0, 40)}${record.procedure.length > 40 ? '...' : ''}</span>
                        </div>
                    </div>
                    <div class="record-description">${record.description}</div>
                </div>
                <div class="record-actions">
                    <button class="record-action-btn view-btn" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="record-action-btn edit-btn" title="Edit Record">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="record-action-btn delete-btn" title="Delete Record">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            
            // Add event listeners
            const viewBtn = item.querySelector('.view-btn');
            const editBtn = item.querySelector('.edit-btn');
            const deleteBtn = item.querySelector('.delete-btn');
            
            viewBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                viewRecordDetails(record);
            });
            
            editBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                editRecord(record, index);
            });
            
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                deleteRecord(record.id, index);
            });
            
            item.addEventListener('click', (e) => {
                if (!e.target.closest('.record-action-btn')) {
                    viewRecordDetails(record);
                }
            });
            
            return item;
        }

        // Get type label
        function getTypeLabel(type) {
            const labels = {
                'treatment': 'Treatment',
                'consultation': 'Consultation',
                'xray': 'X-Ray',
                'prescription': 'Prescription',
                'followup': 'Follow-up',
                'emergency': 'Emergency'
            };
            return labels[type] || type;
        }

        // Format date
        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = { month: 'long', day: 'numeric', year: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }

        // Format time
        function formatTime(timeString) {
            const [hours, minutes] = timeString.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            return `${hour12}:${minutes} ${ampm}`;
        }

        // Filter buttons
        document.querySelectorAll('[data-filter]').forEach(button => {
            button.addEventListener('click', (e) => {
                const filter = e.target.getAttribute('data-filter');
                currentFilter = filter;
                
                // Update active button
                document.querySelectorAll('[data-filter]').forEach(btn => {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn');
                });
                e.target.classList.remove('btn');
                e.target.classList.add('btn-primary');
                
                // Reload records with filter
                loadPatientRecords();
            });
        });

        // View Record Details Modal functionality
        const viewRecordModal = document.getElementById('view-record-modal');
        const closeViewModal = document.getElementById('close-view-modal');
        const closeViewRecord = document.getElementById('close-view-record');
        const viewRecordContent = document.getElementById('record-details-content');

        function viewRecordDetails(record) {
            // Format date and time
            const formattedDate = formatDate(record.date);
            const formattedDateTime = `${formattedDate} at ${formatTime(record.time)}`;
            
            // Create record details HTML matching the sample format
            const detailsHTML = `
                <div class="record-detail-section">
                    <h3 class="section-title">Treatment Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Procedure:</div>
                            <div class="detail-value">${record.procedure}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Date:</div>
                            <div class="detail-value">${formattedDateTime}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Dentist:</div>
                            <div class="detail-value">${record.dentist}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Duration:</div>
                            <div class="detail-value">${record.duration}</div>
                        </div>
                    </div>
                </div>
                
                <div class="record-detail-section">
                    <h3 class="section-title">Description</h3>
                    <div class="detail-description">
                        ${record.description}
                    </div>
                </div>
                
                ${record.notes ? `
                <div class="record-detail-section">
                    <h3 class="section-title">Notes</h3>
                    <div class="detail-description">
                        ${record.notes}
                    </div>
                </div>
                ` : ''}
                
                ${record.followup ? `
                <div class="record-detail-section">
                    <h3 class="section-title">Follow-up Instructions</h3>
                    <div class="detail-description">
                        ${record.followup}
                    </div>
                </div>
                ` : ''}
                
                ${record.files && record.files.length > 0 ? `
                <div class="record-detail-section">
                    <h3 class="section-title">Attached Files</h3>
                    <div class="detail-grid">
                        ${record.files.map(file => `
                            <div class="detail-item">
                                <div class="detail-value">
                                    <i class="fas fa-file-pdf"></i> ${file}
                                    <button class="btn" style="margin-left: 10px; padding: 5px 10px; font-size: 0.8rem;">
                                        <i class="fas fa-download"></i> Download
                                    </button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
                
                <div class="additional-info">
                    <strong>Additional Information</strong><br>
                    This record contains the complete treatment details as documented by your dental care provider. For any questions about this procedure, please contact our office.
                </div>
            `;
            
            viewRecordContent.innerHTML = detailsHTML;
            viewRecordModal.classList.add('active');
        }

        // Close view modal
        closeViewModal.addEventListener('click', () => {
            viewRecordModal.classList.remove('active');
        });

        closeViewRecord.addEventListener('click', () => {
            viewRecordModal.classList.remove('active');
        });

        // Create Record Modal functionality
        const createRecordModal = document.getElementById('create-record-modal');
        const closeCreateModal = document.getElementById('close-create-modal');
        const cancelCreateRecord = document.getElementById('cancel-create-record');
        const saveRecordBtn = document.getElementById('save-record-btn');
        const createRecordForm = document.getElementById('create-record-form');
        const modalUploadArea = document.getElementById('modal-upload-area');
        const modalFileInput = document.getElementById('modal-file-input');
        const modalFilePreview = document.getElementById('modal-file-preview');
        const modalPreviewGrid = document.getElementById('modal-preview-grid');
        const recordDateInput = document.getElementById('record-date');
        const recordTimeInput = document.getElementById('record-time');

        // Set default date to today and time to current hour
        const now = new Date();
        recordDateInput.value = now.toISOString().split('T')[0];
        recordTimeInput.value = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;

        // Open create record modal
        createRecordBtn.addEventListener('click', () => {
            createRecordModal.classList.add('active');
            
            // If a patient is currently viewed, pre-fill their ID
            if (currentPatientId) {
                document.getElementById('record-patient-id').value = currentPatientId;
            }
        });

        // Open create record modal from empty state button
        emptyCreateBtn.addEventListener('click', () => {
            createRecordModal.classList.add('active');
            
            // Pre-fill with current patient ID
            if (currentPatientId) {
                document.getElementById('record-patient-id').value = currentPatientId;
            }
        });

        // Close create record modal
        closeCreateModal.addEventListener('click', () => {
            createRecordModal.classList.remove('active');
            resetCreateRecordForm();
        });

        cancelCreateRecord.addEventListener('click', () => {
            createRecordModal.classList.remove('active');
            resetCreateRecordForm();
        });

        // Modal drag and drop functionality
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            modalUploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            modalUploadArea.addEventListener(eventName, highlightModal, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            modalUploadArea.addEventListener(eventName, unhighlightModal, false);
        });

        function highlightModal() {
            modalUploadArea.classList.add('active');
        }

        function unhighlightModal() {
            modalUploadArea.classList.remove('active');
        }

        modalUploadArea.addEventListener('drop', handleModalDrop, false);

        function handleModalDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleModalFiles(files);
        }

        modalFileInput.addEventListener('change', (e) => {
            handleModalFiles(e.target.files);
        });

        let modalUploadedFiles = [];

        function handleModalFiles(files) {
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                // Check file size (max 10MB)
                if (file.size > 10 * 1024 * 1024) {
                    showNotification(`File "${file.name}" exceeds 10MB size limit.`, 'warning');
                    continue;
                }
                
                // Check file type
                const validTypes = ['application/pdf', 'application/msword', 
                                   'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                   'image/jpeg', 'image/jpg', 'image/png'];
                
                if (!validTypes.includes(file.type)) {
                    showNotification(`File "${file.name}" is not a supported format. Please upload PDF, DOC, DOCX, JPG, or PNG files.`, 'warning');
                    continue;
                }
                
                modalUploadedFiles.push(file);
            }
            
            updateModalFilePreview();
        }

        function updateModalFilePreview() {
            modalPreviewGrid.innerHTML = '';
            
            if (modalUploadedFiles.length > 0) {
                modalFilePreview.classList.add('active');
                
                modalUploadedFiles.forEach((file, index) => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'modal-preview-item';
                    
                    let iconClass = 'document';
                    let icon = 'fa-file-alt';
                    
                    if (file.type === 'application/pdf') {
                        iconClass = 'pdf';
                        icon = 'fa-file-pdf';
                    } else if (file.type.includes('image/')) {
                        iconClass = 'image';
                        icon = 'fa-file-image';
                    }
                    
                    fileItem.innerHTML = `
                        <div class="modal-preview-icon ${iconClass}">
                            <i class="fas ${icon}"></i>
                        </div>
                        <div class="modal-preview-info">
                            <div class="modal-preview-name">${file.name}</div>
                            <div class="modal-preview-size">${formatFileSize(file.size)}</div>
                        </div>
                        <button type="button" class="modal-preview-remove" data-index="${index}">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    
                    modalPreviewGrid.appendChild(fileItem);
                });
            } else {
                modalFilePreview.classList.remove('active');
            }
        }

        // Remove individual file from modal
        modalPreviewGrid.addEventListener('click', (e) => {
            if (e.target.closest('.modal-preview-remove')) {
                const button = e.target.closest('.modal-preview-remove');
                const index = parseInt(button.getAttribute('data-index'));
                modalUploadedFiles.splice(index, 1);
                updateModalFilePreview();
            }
        });

        // Save record functionality
        saveRecordBtn.addEventListener('click', () => {
            // Get form values
            const patientId = document.getElementById('record-patient-id').value.trim().toUpperCase();
            const recordType = document.getElementById('record-type').value;
            const recordTitle = document.getElementById('record-title').value.trim();
            const recordDate = document.getElementById('record-date').value;
            const recordTime = document.getElementById('record-time').value;
            const recordDentist = document.getElementById('record-dentist').value;
            const recordDuration = document.getElementById('record-duration').value;
            const recordProcedure = document.getElementById('record-procedure').value.trim();
            const recordDescription = document.getElementById('record-description').value.trim();
            const recordNotes = document.getElementById('record-notes').value.trim();
            const recordFollowup = document.getElementById('record-followup').value.trim();
            
            // Validate required fields
            if (!patientId) {
                showNotification('Please enter a Patient ID', 'error');
                document.getElementById('record-patient-id').focus();
                return;
            }
            
            if (!recordType) {
                showNotification('Please select a record type', 'error');
                document.getElementById('record-type').focus();
                return;
            }
            
            if (!recordTitle) {
                showNotification('Please enter a record title', 'error');
                document.getElementById('record-title').focus();
                return;
            }
            
            if (!recordDate) {
                showNotification('Please select a date', 'error');
                document.getElementById('record-date').focus();
                return;
            }
            
            if (!recordTime) {
                showNotification('Please select a time', 'error');
                document.getElementById('record-time').focus();
                return;
            }
            
            if (!recordDentist) {
                showNotification('Please select a dentist', 'error');
                document.getElementById('record-dentist').focus();
                return;
            }
            
            if (!recordProcedure) {
                showNotification('Please enter the procedure', 'error');
                document.getElementById('record-procedure').focus();
                return;
            }
            
            if (!recordDescription) {
                showNotification('Please enter a description', 'error');
                document.getElementById('record-description').focus();
                return;
            }
            
            // Check if patient exists
            if (!patients[patientId]) {
                if (!confirm(`Patient ID ${patientId} is not found in the system. Do you want to create a record anyway?`)) {
                    return;
                }
            }
            
            // Create new record object matching the format
            const newRecord = {
                id: 'REC-' + String(Date.now()).slice(-6),
                type: recordType,
                title: recordTitle,
                date: recordDate,
                time: recordTime,
                dentist: recordDentist,
                duration: recordDuration || 'Not specified',
                procedure: recordProcedure,
                description: recordDescription,
                notes: recordNotes,
                followup: recordFollowup,
                files: modalUploadedFiles.map(file => file.name),
                created: new Date().toISOString()
            };
            
            // Add to patient records
            if (!patientRecords[patientId]) {
                patientRecords[patientId] = [];
            }
            patientRecords[patientId].push(newRecord);
            
            // Show success message
            showSuccessMessage(`Record created successfully for Patient ${patientId}!`);
            
            // Close modal and reset form
            createRecordModal.classList.remove('active');
            resetCreateRecordForm();
            
            // If the created record is for the currently viewed patient, refresh the view
            if (currentPatientId === patientId) {
                loadPatientRecords();
            }
        });

        function showSuccessMessage(message) {
            successMessage.querySelector('span').textContent = message;
            successMessage.classList.add('active');
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                successMessage.classList.remove('active');
            }, 5000);
        }

        function resetCreateRecordForm() {
            createRecordForm.reset();
            modalUploadedFiles = [];
            updateModalFilePreview();
            
            // Reset date and time to current
            const now = new Date();
            recordDateInput.value = now.toISOString().split('T')[0];
            recordTimeInput.value = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
            
            // If a patient is currently viewed, pre-fill their ID
            if (currentPatientId) {
                document.getElementById('record-patient-id').value = currentPatientId;
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Edit record functionality
        function editRecord(record, index) {
            // For now, just show a notification
            showNotification(`Editing record: ${record.title}\nIn a real application, this would open an edit form with the record data pre-filled.`, 'info');
        }

        // Delete record functionality
        function deleteRecord(recordId, index) {
            if (confirm(`Are you sure you want to delete this record?`)) {
                // Remove from patient records
                if (patientRecords[currentPatientId]) {
                    patientRecords[currentPatientId].splice(index, 1);
                }
                
                // Show success message
                showSuccessMessage('Record deleted successfully!');
                
                // Refresh the view
                loadPatientRecords();
            }
        }

        // Refresh button
        document.getElementById('refresh-btn').addEventListener('click', () => {
            loadPatientRecords();
        });

        // Initialize with focus on search input
        document.addEventListener('DOMContentLoaded', () => {
            patientIdInput.focus();
        });
    </script>
</body>
</html>