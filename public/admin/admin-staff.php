<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Controllers/AdminStaffController.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

$database = new Database();
$pdo = $database->getConnection();
$controller = new AdminStaffController($pdo);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    echo json_encode($controller->handleAjaxRequest($_POST['action'], $_POST));
    exit();
}

// Pagination and Filtering setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role = isset($_GET['role']) ? $_GET['role'] : 'all';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

$data = $controller->getAllStaff([
    'page' => $page, 
    'limit' => $limit,
    'search' => $search,
    'role' => $role,
    'status' => $status
]);

$staffList = isset($data['staff']) ? $data['staff'] : [];
$totalPages = isset($data['total_pages']) ? $data['total_pages'] : 1;
$totalRecords = isset($data['total_records']) ? $data['total_records'] : 0;
$stats = $controller->getStaffStats();

$admin_name = isset($_SESSION['admin_full_name']) ? $_SESSION['admin_full_name'] : 'Administrator';
$admin_role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'Admin';

// Sidebar variables
$currentPage = 'staff';
$sidebarAdminName = 'Dr. ' . $admin_name;
$sidebarAdminRole = (strtolower($admin_role) === 'admin') ? 'Administrator' : ucfirst($admin_role);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Cosmo Smiles Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin-sidebar-css.php'; ?>
    <style>



        /* Add Staff Button - consistent styling */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
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
            white-space: nowrap;
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

        /* Staff Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: var(--light-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary);
            font-size: 1.5rem;
        }

        .stat-content h3 {
            font-size: 0.9rem;
            color: var(--dark);
            opacity: 0.8;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }

        /* Staff Filter */
        .staff-filter {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }

        .filter-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: "Open Sans", sans-serif;
            font-size: 0.9rem;
            color: var(--dark);
            background: white;
        }

        .filter-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(108, 168, 240, 0.2);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        /* Staff Table */
        .staff-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
        }

        .table-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--primary);
        }

        .table-actions {
            display: flex;
            gap: 10px;
        }

        .table-content {
            padding: 0;
            overflow-x: auto;
        }

        .staff-table {
            width: 100%;
            border-collapse: collapse;
        }

        .staff-table th {
            background: var(--light-accent);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--primary);
            font-size: 0.9rem;
            border-bottom: 1px solid var(--border);
        }

        .staff-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
        }

        .staff-table tr:last-child td {
            border-bottom: none;
        }

        .staff-table tr:hover {
            background: var(--light-accent);
        }

        .staff-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .staff-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary);
        }

        .staff-details h4 {
            margin: 0 0 4px 0;
            font-size: 0.95rem;
            color: var(--dark);
        }

        .staff-details p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--dark);
            opacity: 0.8;
        }

        .staff-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .staff-role {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .role-dentist {
            background: #e7f3ff;
            color: #0066cc;
        }

        .role-receptionist {
            background: #fef7cd;
            color: #92400e;
        }

        .staff-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .action-btn.view {
            color: var(--secondary);
            background: var(--light-accent);
        }

        .action-btn.edit {
            color: var(--warning);
            background: #fff3cd;
        }

        .action-btn.delete {
            color: var(--error);
            background: #f8d7da;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: white;
            border-top: 1px solid var(--border);
        }

        .pagination-info {
            font-size: 0.9rem;
            color: var(--dark);
            opacity: 0.8;
        }

        .pagination-info span {
            font-weight: 600;
            color: var(--primary);
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pagination-btn, .page-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--dark);
        }

        .pagination-btn:hover, .page-btn:hover {
            border-color: var(--secondary);
            color: var(--secondary);
            background: var(--light-accent);
        }

        .page-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
            z-index: 1002;
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
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--primary);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: var(--error);
        }

        .modal-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: "Open Sans", sans-serif;
            font-size: 0.9rem;
            color: var(--dark);
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(108, 168, 240, 0.2);
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 20px 25px;
            border-top: 1px solid var(--border);
        }

        /* Dialog Styles */
        .dialog-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            backdrop-filter: blur(4px);
        }

        .dialog-overlay.active {
            display: flex;
        }

        .dialog-box {
            background: white;
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .dialog-overlay.active .dialog-box {
            transform: scale(1);
        }

        .dialog-icon {
            width: 60px;
            height: 60px;
            background: var(--light-accent);
            color: var(--secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 20px;
        }

        .dialog-box h3 {
            margin-bottom: 15px;
            color: var(--primary);
            font-family: 'Inter', sans-serif;
            font-size: 1.5rem;
        }

        .dialog-box p {
            color: var(--dark);
            opacity: 0.8;
            margin-bottom: 25px;
        }

        .dialog-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .dialog-actions .btn {
            min-width: 120px;
        }

        .loading-spinner {
            text-align: center;
            padding: 40px;
            color: var(--secondary);
            font-size: 1.2rem;
        }

        .view-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            padding: 10px;
        }

        .detail-item label {
            display: block;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .detail-item span {
            font-weight: 600;
            color: var(--primary);
        }
        
        .credential-box {
            background: var(--light-accent);
            border: 1px dashed var(--secondary);
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
        }

        .credential-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .credential-item:last-child {
            margin-bottom: 0;
        }

        .credential-label {
            font-weight: 600;
            color: var(--primary);
        }

        .credential-value {
            font-family: 'Monaco', 'Consolas', monospace;
            color: var(--secondary);
            background: white;
            padding: 2px 6px;
            border-radius: 4px;
        }

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
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: stretch;
                gap: 16px;
            }
            .header-actions {
                justify-content: space-between;
                width: 100%;
                gap: 15px;
            }
            .date-display {
                flex: 2;
                justify-content: center;
            }
            .btn {
                flex: 1;
                justify-content: center;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .filter-row {
                flex-direction: column;
            }
            .filter-actions {
                align-self: flex-start;
            }
            .admin-main {
                padding: 20px;
            }
            .staff-actions {
                flex-direction: column;
            }
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }

        @media (max-width: 576px) {
            .admin-main {
                padding: 15px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .header-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            .date-display {
                width: 100%;
                justify-content: center;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
            .staff-table {
                font-size: 0.85rem;
            }
            .staff-table th,
            .staff-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/admin-header.php'; ?>

    <!-- Admin Dashboard Layout -->
    <div class="admin-container">
        <?php include 'includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Dashboard Header with Flex alignment -->
            <div class="dashboard-header">
                <div class="header-content">
                    <h1>Staff Management</h1>
                    <p>Manage staff accounts, roles, and permissions</p>
                </div>
                <div class="header-actions">
                    <div class="date-display">
                        <i class="fas fa-calendar-alt"></i>
                        <div class="clock-content">
                            <span id="admin-date">Loading...</span>
                            <span id="admin-time">00:00:00 AM</span>
                        </div>
                    </div>
                    <button class="btn btn-primary" id="add-staff-btn">
                        <i class="fas fa-plus"></i> Add Staff
                    </button>
                </div>
            </div>

            <!-- Staff Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-md"></i></div>
                    <div class="stat-content">
                        <h3>Total Staff</h3>
                        <div class="stat-number"><?php echo isset($stats['total']) ? $stats['total'] : 0; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                    <div class="stat-content">
                        <h3>Active Staff</h3>
                        <div class="stat-number"><?php echo isset($stats['active']) ? $stats['active'] : 0; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
                    <div class="stat-content">
                        <h3>Pending Approval</h3>
                        <div class="stat-number"><?php echo isset($stats['pending']) ? $stats['pending'] : 0; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-stethoscope"></i></div>
                    <div class="stat-content">
                        <h3>Medical Staff</h3>
                        <div class="stat-number"><?php echo isset($stats['medical']) ? $stats['medical'] : 0; ?></div>
                    </div>
                </div>
            </div>

            <!-- Staff Filter -->
            <div class="staff-filter">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="role-filter">Role</label>
                        <select id="role-filter" class="filter-control">
                            <option value="all" <?php echo $role == 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="dentist" <?php echo $role == 'dentist' ? 'selected' : ''; ?>>Dentist</option>
                            <option value="receptionist" <?php echo $role == 'receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="status-filter">Status</label>
                        <select id="status-filter" class="filter-control">
                            <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="search-filter">Search</label>
                        <input type="text" id="search-filter" class="filter-control" placeholder="Search by name..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-actions">
                        <button class="btn btn-primary" id="apply-filters-btn">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <button class="btn" id="reset-filters-btn">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Staff Table -->
            <div class="staff-table-container">
                <div class="table-header">
                    <h3>All Staff Members</h3>
                    <div class="table-actions">
                        <button class="btn btn-success" id="export-btn">
                            <i class="fas fa-file-export"></i> Export
                        </button>
                    </div>
                </div>
                
                <div class="table-content">
                    <table class="staff-table">
                        <thead>
                            <tr>
                                <th>Staff Member</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Join Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="staff-table-body">
                            <?php if (empty($staffList)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px;">No staff members found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($staffList as $staff): ?>
                                <tr>
                                    <td data-label="Staff Member">
                                        <div class="staff-info">
                                            <div class="staff-avatar">
                                                <i class="fas fa-user-<?php echo ($staff['user_type'] === 'admin_type') ? 'md' : 'user'; ?>"></i>
                                            </div>
                                            <div class="staff-details">
                                                <h4><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></h4>
                                                <p>ID: <?php echo htmlspecialchars($staff['staff_id']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Role">
                                        <span class="staff-role role-<?php echo strtolower(str_replace(' ', '_', $staff['role'])); ?>"><?php 
                                            $displayRole = str_replace('_', ' ', $staff['role']);
                                            echo ($displayRole === 'admin') ? 'Dentist' : htmlspecialchars(ucfirst($displayRole)); 
                                        ?></span>
                                    </td>
                                    <td data-label="Email"><?php echo htmlspecialchars($staff['email']); ?></td>
                                    <td data-label="Phone"><?php echo !empty($staff['phone']) ? htmlspecialchars($staff['phone']) : 'N/A'; ?></td>
                                    <td data-label="Status">
                                        <span class="staff-status status-<?php echo strtolower($staff['status']); ?>"><?php echo htmlspecialchars(ucfirst($staff['status'])); ?></span>
                                    </td>
                                    <td data-label="Join Date"><?php echo date('M d, Y', strtotime($staff['created_at'])); ?></td>
                                    <td data-label="Actions">
                                        <div class="staff-actions">
                                            <button class="action-btn view" data-id="<?php echo $staff['id']; ?>" data-type="<?php echo $staff['user_type']; ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="action-btn edit" data-id="<?php echo $staff['id']; ?>" data-type="<?php echo $staff['user_type']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if ($staff['user_type'] !== 'admin_type' && strtolower($staff['status']) === 'inactive'): ?>
                                            <button class="action-btn delete" data-id="<?php echo $staff['id']; ?>" data-type="<?php echo $staff['user_type']; ?>">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <span><?php echo count($staffList); ?></span> of <span><?php echo $totalRecords; ?></span> staff members
                    </div>
                    <div class="pagination-controls" id="pagination-controls">
                        <button class="pagination-btn" id="prev-page" <?php echo $page <= 1 ? 'disabled' : ''; ?> data-page="<?php echo $page - 1; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="page-numbers">
                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $startPage + 4);
                            if ($endPage - $startPage < 4) $startPage = max(1, $endPage - 4);
                            
                            for($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <button class="page-btn <?php echo $i === $page ? 'active' : ''; ?>" data-page="<?php echo $i; ?>"><?php echo $i; ?></button>
                            <?php endfor; ?>
                        </div>
                        <button class="pagination-btn" id="next-page" <?php echo $page >= $totalPages ? 'disabled' : ''; ?> data-page="<?php echo $page + 1; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Staff Modal -->
    <div class="modal" id="add-staff-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Staff Member</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="add-staff-form" autocomplete="off">
                    <input type="hidden" id="staff-id" name="id">
                    <input type="hidden" id="staff-type" name="type">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first-name">First Name</label>
                            <input type="text" id="first-name" name="first-name" class="form-control" autocomplete="given-name" required>
                        </div>
                        <div class="form-group">
                            <label for="last-name">Last Name</label>
                            <input type="text" id="last-name" name="last-name" class="form-control" autocomplete="family-name" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" autocomplete="off" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" autocomplete="off" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" class="form-control" required>
                                <option value="">Select Role</option>
                                <option value="dentist">Dentist</option>
                                <option value="receptionist">Receptionist</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="pending">Pending</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes/Department</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="Additional information"></textarea>
                    </div>

                    <!-- OTP Verification Section (Hidden by default) -->
                    <div id="otp-section" style="display: none; background: #fff5f5; border: 1px solid #feb2b2; padding: 15px; border-radius: 8px; margin-top: 15px;">
                        <p style="color: #c53030; font-size: 0.85rem; margin-bottom: 10px; font-weight: 600;">
                            <i class="fas fa-shield-alt"></i> Sensitive information changed. Verification required.
                        </p>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="otp_code" name="otp" class="form-control" placeholder="Enter 6-digit OTP" maxlength="6">
                            <button type="button" id="send-otp-btn" class="btn" style="background: #4a5568; padding: 8px 15px;">Send OTP</button>
                        </div>
                        <p id="otp-timer" style="font-size: 0.8rem; color: #718096; margin-top: 5px;"></p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn" id="cancel-add-staff">Cancel</button>
                <button class="btn btn-primary" id="save-staff">Save Staff Member</button>
            </div>
        </div>
    </div>

    <!-- View Staff Modal -->
    <div class="modal" id="view-staff-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Staff Member Details</h3>
                <button class="close-modal" onclick="document.getElementById('view-staff-modal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body" id="view-staff-details" style="padding: 20px;">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i> Loading details...
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="document.getElementById('view-staff-modal').classList.remove('active')">Close</button>
            </div>
        </div>
    </div>

    <!-- Custom Dialog -->
    <div id="custom-dialog" class="dialog-overlay">
        <div class="dialog-box">
            <div class="dialog-icon" id="dialog-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 id="dialog-title">Confirmation</h3>
            <p id="dialog-message">Are you sure you want to proceed with this action?</p>
            <div class="dialog-actions">
                <button class="btn" id="dialog-cancel">Cancel</button>
                <button class="btn btn-primary" id="dialog-confirm">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        // Set current date
        const currentDate = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };

        // Mobile sidebar toggle
        const hamburger = document.querySelector('.hamburger');
        const sidebar = document.querySelector('.admin-sidebar');
        const overlay = document.querySelector('.overlay');

        if (hamburger && sidebar && overlay) {
            hamburger.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            });

            overlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
        }

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

        // Responsive sidebar behavior
        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
        });

        // Dialog Helper
        const customDialog = document.getElementById('custom-dialog');
        const dialogTitle = document.getElementById('dialog-title');
        const dialogMessage = document.getElementById('dialog-message');
        const dialogIcon = document.getElementById('dialog-icon');
        const dialogCancel = document.getElementById('dialog-cancel');
        const dialogConfirm = document.getElementById('dialog-confirm');

        function showDialog({ title, message, iconType = 'warning', showCancel = true, isHTML = false, onConfirm = null, confirmText = 'Confirm', autoCloseMs = null }) {
            dialogTitle.textContent = title;
            if (isHTML) {
                dialogMessage.innerHTML = message;
            } else {
                dialogMessage.textContent = message;
            }
            dialogConfirm.textContent = confirmText;
            
            // Set icon
            const icon = dialogIcon.querySelector('i');
            icon.className = 'fas';
            if (iconType === 'warning') {
                icon.classList.add('fa-exclamation-triangle');
                dialogIcon.style.color = 'var(--warning)';
            } else if (iconType === 'success') {
                icon.classList.add('fa-check-circle');
                dialogIcon.style.color = 'var(--success)';
            } else if (iconType === 'error') {
                icon.classList.add('fa-times-circle');
                dialogIcon.style.color = 'var(--error)';
            } else {
                icon.classList.add('fa-info-circle');
                dialogIcon.style.color = 'var(--secondary)';
            }

            dialogCancel.style.display = showCancel ? 'block' : 'none';
            customDialog.classList.add('active');

            const handleConfirm = () => {
                cleanup();
                if (onConfirm) onConfirm();
            };

            const handleCancel = () => {
                cleanup();
            };

            const cleanup = () => {
                customDialog.classList.remove('active');
                dialogConfirm.removeEventListener('click', handleConfirm);
                dialogCancel.removeEventListener('click', handleCancel);
                if (window.currentDialogTimeout) clearTimeout(window.currentDialogTimeout);
            };

            dialogConfirm.addEventListener('click', handleConfirm);
            dialogCancel.addEventListener('click', handleCancel);

            if (autoCloseMs) {
                if (window.currentDialogTimeout) clearTimeout(window.currentDialogTimeout);
                window.currentDialogTimeout = setTimeout(() => {
                    if (customDialog.classList.contains('active')) {
                        handleConfirm();
                    }
                }, autoCloseMs);
            }
        }

        // Modal functionality
        const addStaffBtn = document.getElementById('add-staff-btn');
        const addStaffModal = document.getElementById('add-staff-modal');
        const closeModalBtns = document.querySelectorAll('.close-modal');
        const cancelAddStaffBtn = document.getElementById('cancel-add-staff');
        const saveStaffBtn = document.getElementById('save-staff');
        const addStaffForm = document.getElementById('add-staff-form');

        if (addStaffBtn) {
            addStaffBtn.addEventListener('click', () => {
                document.getElementById('staff-id').value = '';
                document.getElementById('staff-type').value = '';
                addStaffForm.reset();
                document.querySelector('#add-staff-modal .modal-header h3').textContent = 'Add New Staff Member';
                addStaffModal.classList.add('active');
            });
        }

        const closeModal = () => {
            addStaffModal?.classList.remove('active');
            document.getElementById('view-staff-modal')?.classList.remove('active');
            addStaffForm?.reset();
        };

        closeModalBtns.forEach(btn => btn.addEventListener('click', closeModal));
        if (cancelAddStaffBtn) cancelAddStaffBtn.addEventListener('click', closeModal);

        if (saveStaffBtn) {
            saveStaffBtn.addEventListener('click', () => {
                if (addStaffForm.checkValidity()) {
                    const formData = new FormData(addStaffForm);
                    const isEdit = !!document.getElementById('staff-id').value;
                    formData.append('action', isEdit ? 'update' : 'add');
                    
                    // Explicitly set all fields for consistency
                    const fields = ['first-name', 'last-name', 'email', 'phone', 'role', 'status', 'notes', 'otp_code'];
                    fields.forEach(f => {
                        const element = document.getElementById(f);
                        if (element) {
                            const key = f === 'otp_code' ? 'otp' : f.replace('-', '_');
                            formData.set(key, element.value);
                        }
                    });
                    
                    if (isEdit) {
                        formData.set('id', document.getElementById('staff-id').value);
                        formData.set('type', document.getElementById('staff-type').value);
                    }

                    fetch(window.location.href, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.needs_otp) {
                            document.getElementById('otp-section').style.display = 'block';
                            showDialog({ title: 'Verification Required', message: data.message, iconType: 'info', showCancel: false });
                            return;
                        }

                        if (data.success) {
                            let successMsg = data.message;
                            if (!isEdit && data.id) {
                                const pwd = successMsg.split('Default password is: ')[1] || 'Check system rules';
                                successMsg = `
                                    <p>${successMsg.split('.')[0]}. Please share these credentials with the new staff member:</p>
                                    <div class="credential-box">
                                        <div class="credential-item">
                                            <span class="credential-label">Registered ID:</span>
                                            <span class="credential-value">${data.id}</span>
                                        </div>
                                        <div class="credential-item">
                                            <span class="credential-label">Default Password:</span>
                                            <span class="credential-value">${pwd}</span>
                                        </div>
                                    </div>
                                    <p style="font-size: 0.85rem; color: #666;">They will be prompted to change this upon first login.</p>
                                `;
                            }
                            
                            showDialog({
                                title: 'Staff Member Registered',
                                message: successMsg,
                                isHTML: true,
                                iconType: 'success',
                                showCancel: false,
                                confirmText: 'Close (X)',
                                autoCloseMs: 60000,
                                onConfirm: () => location.reload()
                            });
                        } else {
                            showDialog({ title: 'Registration Failed', message: data.message, iconType: 'error', showCancel: false });
                        }
                    })
                    .catch(err => showDialog({ title: 'Error', message: 'Connection error. Please try again.', iconType: 'error', showCancel: false }));
                } else {
                    addStaffForm.reportValidity();
                }
            });
        }

        // View Staff
        document.addEventListener('click', e => {
            const viewBtn = e.target.closest('.action-btn.view');
            if (viewBtn) {
                const id = viewBtn.dataset.id;
                const type = viewBtn.dataset.type;
                
                const detailsContainer = document.getElementById('view-staff-details');
                if (!detailsContainer) return;
                
                detailsContainer.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
                const modal = document.getElementById('view-staff-modal');
                if (modal) modal.classList.add('active');

                const formData = new FormData();
                formData.append('action', 'get_details');
                formData.append('id', id);
                formData.append('type', type);

                fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const s = data.staff;
                        const formatDate = (dateStr) => {
                            if (!dateStr) return 'N/A';
                            const d = new Date(dateStr);
                            return isNaN(d) ? 'N/A' : d.toLocaleDateString();
                        };

                        detailsContainer.innerHTML = `
                            <div class="view-details-grid">
                                <div class="detail-item"><label>Staff ID</label><span>${s.staff_id || 'N/A'}</span></div>
                                <div class="detail-item"><label>Full Name</label><span>${s.first_name || ''} ${s.last_name || ''}</span></div>
                                <div class="detail-item"><label>Role</label><span>${(s.role || '').toLowerCase() === 'admin' ? 'DENTIST' : (s.role || '').toUpperCase().replace('_', ' ')}</span></div>
                                <div class="detail-item"><label>Email</label><span>${s.email || 'N/A'}</span></div>
                                <div class="detail-item"><label>Phone</label><span>${s.phone || 'N/A'}</span></div>
                                <div class="detail-item"><label>Department</label><span>${s.department || 'N/A'}</span></div>
                                <div class="detail-item"><label>Status</label><span>${(s.status || '').toUpperCase()}</span></div>
                                <div class="detail-item"><label>Join Date</label><span>${formatDate(s.created_at)}</span></div>
                            </div>
                        `;
                    } else {
                        detailsContainer.innerHTML = `<div class="error-msg"><i class="fas fa-exclamation-circle"></i> ${data.message}</div>`;
                    }
                })
                .catch(err => {
                    detailsContainer.innerHTML = `<div class="error-msg"><i class="fas fa-exclamation-circle"></i> Failed to load staff details.</div>`;
                });
            }
        });

        // Edit Staff
        document.addEventListener('click', e => {
            const editBtn = e.target.closest('.action-btn.edit');
            if (editBtn) {
                const id = editBtn.dataset.id;
                const type = editBtn.dataset.type;
                const row = editBtn.closest('tr');
                
                document.getElementById('staff-id').value = id;
                document.getElementById('staff-type').value = type;
                
                const fullName = row.querySelector('h4').textContent.split(' ');
                document.getElementById('first-name').value = fullName[0];
                document.getElementById('last-name').value = fullName.slice(1).join(' ');
                document.getElementById('email').value = row.cells[2].textContent;
                const phoneCell = row.cells[3].textContent;
                document.getElementById('phone').value = phoneCell !== 'N/A' ? phoneCell : '';
                let roleValue = row.querySelector('.staff-role').textContent.trim().toLowerCase().replace(' ', '_');
                if (roleValue === 'dentist') roleValue = 'dentist';
                document.getElementById('role').value = roleValue;
                document.getElementById('status').value = row.querySelector('.staff-status').textContent.trim().toLowerCase();
                document.getElementById('otp-section').style.display = 'none';
                document.getElementById('notes').value = ''; // Reset notes if needed or fetch via get_details below if you prefer

                document.querySelector('#add-staff-modal .modal-header h3').textContent = 'Edit Staff Member';
                addStaffModal.classList.add('active');

                // Pre-check for changes on input to show OTP section early? No, let server decide for simplicity
                
                // Fetch full details including specialization
                const detailsFormData = new FormData();
                detailsFormData.append('action', 'get_details');
                detailsFormData.append('id', id);
                detailsFormData.append('type', type);

                fetch(window.location.href, { method: 'POST', body: detailsFormData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const s = data.staff;
                        document.getElementById('notes').value = s.specialization || s.department || '';
                    }
                });
            }
        });

        // Send OTP logic
        document.getElementById('send-otp-btn')?.addEventListener('click', function() {
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            const btn = this;
            
            btn.disabled = true;
            btn.textContent = 'Sending...';

            // We need a way to send OTP. Usually we'd call OTPController via AJAX.
            // Assuming there's a route for this or we add an action.
            const otpFormData = new FormData();
            otpFormData.append('action', 'send_otp');
            otpFormData.append('email', email);
            otpFormData.append('phone', phone);

            fetch('../controllers/OTPController.php', { method: 'POST', body: otpFormData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showDialog({ title: 'OTP Sent', message: 'Verification code has been sent to your email/phone.', iconType: 'success', showCancel: false });
                    let timeLeft = 60;
                    const timerEl = document.getElementById('otp-timer');
                    const interval = setInterval(() => {
                        timeLeft--;
                        timerEl.textContent = `Resend in ${timeLeft}s`;
                        if (timeLeft <= 0) {
                            clearInterval(interval);
                            btn.disabled = false;
                            btn.textContent = 'Resend OTP';
                            timerEl.textContent = '';
                        }
                    }, 1000);
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Send OTP';
                    showDialog({ title: 'Error', message: data.message, iconType: 'error', showCancel: false });
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.textContent = 'Send OTP';
                showDialog({ title: 'Error', message: 'Failed to connect to OTP service.', iconType: 'error', showCancel: false });
            });
        });

        // Delete Staff
        document.addEventListener('click', e => {
            const deleteBtn = e.target.closest('.action-btn.delete');
            if (deleteBtn) {
                const id = deleteBtn.dataset.id;
                const type = deleteBtn.dataset.type;
                const name = deleteBtn.closest('tr').querySelector('h4').textContent;

                showDialog({
                    title: 'Confirm Deletion',
                    message: `Are you sure you want to delete ${name}? This action cannot be undone.`,
                    onConfirm: () => {
                        const formData = new FormData();
                        formData.append('action', 'delete');
                        formData.append('id', id);
                        formData.append('type', type);

                        fetch(window.location.href, { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                showDialog({ title: 'Deleted', message: data.message, iconType: 'success', showCancel: false, onConfirm: () => location.reload() });
                            } else {
                                showDialog({ title: 'Error', message: data.message, iconType: 'error', showCancel: false });
                            }
                        });
                    }
                });
            }
        });

        // Pagination
        document.addEventListener('click', e => {
            const btn = e.target.closest('.page-btn, .pagination-btn');
            if (btn && !btn.disabled) {
                const page = btn.dataset.page;
                const url = new URL(window.location);
                url.searchParams.set('page', page);
                const currentSearch = document.getElementById('search-filter').value;
                const currentRole = document.getElementById('role-filter').value;
                const currentStatus = document.getElementById('status-filter').value;
                
                if (currentSearch) url.searchParams.set('search', currentSearch);
                if (currentRole !== 'all') url.searchParams.set('role', currentRole);
                if (currentStatus !== 'all') url.searchParams.set('status', currentStatus);
                
                window.location.href = url.href;
            }
        });

        // Export
        const exportBtn = document.getElementById('export-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                const formData = new FormData();
                formData.append('action', 'export');

                fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const blob = new Blob([data.csv], { type: 'text/csv' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.setAttribute('hidden', '');
                        a.setAttribute('href', url);
                        a.setAttribute('download', 'staff_list.csv');
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                    }
                });
            });
        }

        // Filter functionality
        const applyFiltersBtn = document.getElementById('apply-filters-btn');
        const resetFiltersBtn = document.getElementById('reset-filters-btn');
        
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', () => {
                const search = document.getElementById('search-filter').value;
                const role = document.getElementById('role-filter').value;
                const status = document.getElementById('status-filter').value;
                
                const url = new URL(window.location);
                if (search) url.searchParams.set('search', search); else url.searchParams.delete('search');
                if (role !== 'all') url.searchParams.set('role', role); else url.searchParams.delete('role');
                if (status !== 'all') url.searchParams.set('status', status); else url.searchParams.delete('status');
                
                url.searchParams.set('page', 1);
                window.location.href = url.href;
            });
        }
        
        if (resetFiltersBtn) {
            resetFiltersBtn.addEventListener('click', () => {
                window.location.href = 'admin-staff.php';
            });
        }

        // Admin Clock
        function updateAdminClock() {
            const now = new Date();
            const dateOptions = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
            const timeOptions = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
            
            const dateEl = document.getElementById('admin-date');
            const timeEl = document.getElementById('admin-time');
            
            if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US', dateOptions);
            if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', timeOptions);
        }
        setInterval(updateAdminClock, 1000);
        updateAdminClock();
    </script>
</body>
</html>