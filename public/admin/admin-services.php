<?php 
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Controllers/AdminServiceController.php';

// Ensure user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin-login.php");
    exit();
}

$controller = new AdminServiceController();

// Handle AJAX Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Unknown action'];

    switch ($action) {
        case 'add':
            $data = [
                'name' => $_POST['name'],
                'description' => $_POST['description'],
                'duration' => (int)$_POST['duration'],
                'price' => (float)$_POST['price'],
                'is_active' => (int)$_POST['is_active']
            ];
            if ($controller->addService($data)) {
                $response = ['success' => true, 'message' => 'Service added successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to add service'];
            }
            break;
        case 'update':
            $id = (int)$_POST['id'];
            $data = [
                'name' => $_POST['name'],
                'description' => $_POST['description'],
                'duration' => (int)$_POST['duration'],
                'price' => (float)$_POST['price'],
                'is_active' => (int)$_POST['is_active']
            ];
            if ($controller->updateService($id, $data)) {
                $response = ['success' => true, 'message' => 'Service updated successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to update service'];
            }
            break;
        case 'deactivate':
            $id = (int)$_POST['id'];
            // Soft delete - just mark as inactive
            if ($controller->toggleServiceStatus($id, 0)) {
                $response = ['success' => true, 'message' => 'Service has been deactivated and will no longer appear in appointments'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to deactivate service'];
            }
            break;
        case 'activate':
            $id = (int)$_POST['id'];
            // Reactivate service
            if ($controller->toggleServiceStatus($id, 1)) {
                $response = ['success' => true, 'message' => 'Service has been reactivated and is now available for appointments'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to activate service'];
            }
            break;
    }
    echo json_encode($response);
    exit;
}

// Fetch admin info
$adminId = $_SESSION['admin_id'];
$adminName = "Administrator";
$adminRole = "Administrator";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT first_name, last_name, role FROM admin_users WHERE id = :id");
    $stmt->execute([':id' => $adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        $adminName = "Dr. " . $admin['first_name'] . ' ' . $admin['last_name'];
        $adminRole = ($admin['role'] === 'admin') ? 'Administrator' : ucfirst(htmlspecialchars($admin['role']));
    }
} catch (PDOException $e) {
    error_log("Error fetching admin: " . $e->getMessage());
}

// Filtering setup
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

$services = $controller->getAllServices([
    'search' => $search,
    'status' => $status
]);

$stats = $controller->getServiceStats();

// Fetch admin info for sidebar
$admin_name = $_SESSION['admin_full_name'] ?? 'Administrator';
$admin_role = $_SESSION['admin_role'] ?? 'admin';
$currentPage = 'services';
$sidebarAdminName = (strpos($admin_name, 'Dr.') === false && $admin_name !== 'Administrator') ? 'Dr. ' . $admin_name : $admin_name;
$sidebarAdminRole = (strtolower($admin_role) === 'admin') ? 'Administrator' : ucfirst($admin_role);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Services Management - Cosmo Smiles Dental</title>
    <link rel="icon" type="image/png" href="<?php echo clean_url('public/assets/images/logo1-white.png'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php  include 'includes/admin-sidebar-css.php'; ?>
    <style>        
        /* Buttons */
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 24px; background: var(--secondary); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-family: "Open Sans", sans-serif; font-size: 0.9rem; white-space: nowrap; text-decoration: none; }
        .btn:hover { background: var(--primary); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
        .btn-primary { background: var(--secondary); }
        .btn-success { background: var(--success); }
        .btn-danger { background: var(--error); }
        .btn-warning { background: var(--warning); color: var(--dark); }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }

        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); display: flex; align-items: center; gap: 20px; transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { width: 60px; height: 60px; border-radius: 12px; background: var(--light-accent); display: flex; align-items: center; justify-content: center; color: var(--secondary); font-size: 1.5rem; }
        .stat-content h3 { font-size: 0.9rem; color: var(--dark); opacity: 0.8; margin-bottom: 8px; font-weight: 500; }
        .stat-number { font-size: 2rem; font-weight: 700; color: var(--primary); }

        /* Filter Box */
        .staff-filter { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); margin-bottom: 30px; }
        .filter-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--dark); font-size: 0.9rem; }
        .filter-control { width: 100%; padding: 10px 15px; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; font-family: inherit; background: white; }
        .filter-control:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(108, 168, 240, 0.2); }
        .filter-actions { display: flex; gap: 10px; }

        /* Table CSS */
        .staff-table-container { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); overflow: hidden; }
        .table-header { padding: 20px 25px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .table-header h3 { margin: 0; font-size: 1.1rem; color: var(--primary); }
        .staff-table { width: 100%; border-collapse: collapse; }
        .staff-table th { background: var(--light-accent); padding: 15px; text-align: left; font-weight: 600; color: var(--primary); font-size: 0.9rem; border-bottom: 1px solid var(--border); }
        .staff-table td { padding: 15px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        .staff-table tr:hover { background: var(--light-accent); }
        .staff-table tr:last-child td { border-bottom: none; }

        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-block; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); animation: fadeIn 0.4s ease; overflow: hidden; }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-family: "Inter", sans-serif; color: var(--primary); font-size: 1.2rem; margin: 0; }
        .close-modal { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666; transition: color 0.3s; }
        .close-modal:hover { color: var(--error); }
        .modal-body { padding: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--dark); font-size: 0.9rem; }
        .form-control { width: 100%; padding: 10px 15px; border: 1px solid var(--border); border-radius: 6px; font-family: inherit; font-size: 0.9rem; transition: all 0.3s; }
        .form-control:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(108, 168, 240, 0.2); }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        .modal-footer { padding: 20px 25px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 15px; }

        /* Dialog System */
        .dialog-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: none; align-items: center; justify-content: center; z-index: 3000; backdrop-filter: blur(4px); }
        .dialog-overlay.active { display: flex; }
        .dialog-box { background: white; border-radius: 12px; padding: 30px; width: 90%; max-width: 400px; text-align: center; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); transform: scale(0.9); transition: transform 0.3s ease; }
        .dialog-overlay.active .dialog-box { transform: scale(1); }
        .dialog-icon { width: 60px; height: 60px; background: var(--light-accent); color: var(--secondary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 20px; }
        .dialog-box h3 { margin-bottom: 15px; color: var(--primary); font-family: 'Inter', sans-serif; font-size: 1.5rem; }
        .dialog-box p { color: var(--dark); opacity: 0.8; margin-bottom: 25px; }
        .dialog-actions { display: flex; gap: 15px; justify-content: center; }

        @keyframes fadeIn { from { opacity:0; transform: translateY(20px); } to { opacity:1; transform: translateY(0); } }


        @media (max-width: 768px) {
            .dashboard-header { flex-direction: column; align-items: stretch; gap: 16px; }
            .header-actions { justify-content: space-between; width: 100%; gap: 15px; }
            .date-display { flex: 2; justify-content: center; }
            .btn { flex: 1; justify-content: center; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-actions { align-self: flex-start; }
            .admin-main { padding: 20px; }
            .staff-table thead { display: none; }
            .staff-table, .staff-table tbody, .staff-table tr, .staff-table td { display: block; width: 100%; }
            .staff-table tr { margin-bottom: 15px; border: 1px solid var(--border); border-radius: 8px; position: relative; }
            .staff-table td { padding: 10px 15px; text-align: right; border-bottom: 1px solid #f1f1f1; }
            .staff-table td::before { content: attr(data-label); float: left; font-weight: 700; color: var(--primary); }
            .staff-table td:last-child { border-bottom: none; text-align: center; }
            .form-row { flex-direction: column; gap: 0; }
        }

        @media (max-width: 576px) {
            .admin-main { padding: 15px; }
            .stats-grid { grid-template-columns: 1fr; }
            .header-actions { flex-direction: column; align-items: stretch; gap: 12px; }
            .date-display { width: 100%; justify-content: center; }
            .btn { width: 100%; justify-content: center; }
            .staff-table th, .staff-table td { padding: 10px; }
        }
    </style>
</head>
<body>
    <!-- Overlay for mobile sidebar -->
    <div class="overlay"></div>

    <?php  include 'includes/admin-header.php'; ?>

    <div class="admin-container">
        <!-- Sidebar -->
        <?php  include 'includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="dashboard-header">
                <div class="header-content">
                    <h1>Services Management</h1>
                    <p>Manage dental treatments, pricing (&#8369;), and availability</p>
                </div>
                <div class="header-actions">
                    <div class="date-display">
                        <i class="fas fa-calendar-alt"></i>
                        <div class="clock-content">
                            <span id="admin-date">Loading...</span>
                            <span id="admin-time">00:00:00 AM</span>
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('add')"><i class="fas fa-plus"></i> Add Service</button>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-teeth"></i></div>
                    <div class="stat-content">
                        <h3>Total Services</h3>
                        <div class="stat-number"><?php  echo isset($stats['total']) ? $stats['total'] : 0; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color:var(--success);"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-content">
                        <h3>Active Services</h3>
                        <div class="stat-number"><?php  echo isset($stats['active']) ? $stats['active'] : 0; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color:var(--error);"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-content">
                        <h3>Inactive</h3>
                        <div class="stat-number"><?php  echo isset($stats['inactive']) ? $stats['inactive'] : 0; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color:var(--warning);"><i class="fas fa-coins"></i></div>
                    <div class="stat-content">
                        <h3>Avg. Rate</h3>
                        <div class="stat-number">&#8369;<?php  echo isset($stats['avg_price']) ? number_format($stats['avg_price'], 0) : 0; ?></div>
                    </div>
                </div>
            </div>

            <div class="staff-filter">
                <form method="GET" class="filter-row">
                    <div class="filter-group">
                        <label>Search Service</label>
                        <input type="text" name="search" class="filter-control" placeholder="Search by name..." value="<?php  echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" class="filter-control" onchange="this.form.submit()">
                            <option value="all" <?php  echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="1" <?php  echo $status === '1' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="0" <?php  echo $status === '0' ? 'selected' : ''; ?>>Inactive Only</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                        <a href="admin-services.php" class="btn" style="background:var(--light-accent); color:var(--secondary);"><i class="fas fa-redo"></i> Reset</a>
                    </div>
                </form>
            </div>

            <div class="staff-table-container">
                <div class="table-header">
                    <h3>Treatment Catalog</h3>
                </div>
                <div class="table-content">
                    <table class="staff-table">
                        <thead>
                            <tr>
                                <th>Treatment Name</th>
                                <th>Description</th>
                                <th>Duration</th>
                                <th>Rate (&#8369;)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php  if (empty($services)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:40px; color:#666;">No services found.</td></tr>
                            <?php  else: ?>
                            <?php  foreach ($services as $s): ?>
                            <tr>
                                <td data-label="Treatment Name"><strong><?php  echo htmlspecialchars($s['name']); ?></strong></td>
                                <td data-label="Description">
                                    <?php  echo htmlspecialchars(substr($s['description'], 0, 100)) . (strlen($s['description']) > 100 ? '...' : ''); ?>
                                </td>
                                <td data-label="Duration"><i class="far fa-clock"></i> <?php  echo $s['duration_minutes']; ?> mins</td>
                                <td data-label="Rate (&#8369;)"><strong>&#8369;<?php  echo number_format($s['price'], 2); ?></strong></td>
                                <td data-label="Status">
                                    <span class="status-badge <?php  echo $s['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php  echo $s['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div style="display:flex; gap:10px; justify-content: flex-end;">
                                        <button class="btn btn-sm" style="background:var(--light-accent); color:var(--secondary);" onclick='openModal("edit", <?php  echo json_encode($s); ?>)'><i class="fas fa-edit"></i> Edit</button>
                                        <?php  if ($s['is_active']): ?>
                                        <button class="btn btn-sm btn-warning" onclick="deactivateService(<?php  echo $s['id']; ?>, '<?php  echo addslashes($s['name']); ?>')"><i class="fas fa-ban"></i> Deactivate</button>
                                        <?php  else: ?>
                                        <button class="btn btn-sm btn-success" onclick="activateService(<?php  echo $s['id']; ?>, '<?php  echo addslashes($s['name']); ?>')"><i class="fas fa-check-circle"></i> Activate</button>
                                        <?php  endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php  endforeach; ?>
                            <?php  endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Service Modal -->
    <div class="modal" id="service-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Add Service</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="service-form">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="id" id="service-id">
                    
                    <div class="form-group">
                        <label>Service Name</label>
                        <input type="text" name="name" id="service-name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="service-description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Duration (mins)</label>
                            <input type="number" name="duration" id="service-duration" class="form-control" required min="5" step="5">
                        </div>
                        <div class="form-group">
                            <label>Price (&#8369;)</label>
                            <input type="number" name="price" id="service-price" class="form-control" required min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="is_active" id="service-active" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn" onclick="closeModal()" style="background:#ddd; color:#333;">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Dialog System -->
    <div class="dialog-overlay" id="custom-dialog">
        <div class="dialog-box">
            <div class="dialog-icon" id="dialog-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h3 id="dialog-title">Confirmation</h3>
            <p id="dialog-message">Are you sure you want to proceed?</p>
            <div class="dialog-actions">
                <button class="btn" id="dialog-cancel" style="background:#ddd; color:#333;">Cancel</button>
                <button class="btn btn-primary" id="dialog-confirm">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        // Clock functionality
        function updateClock() {
            const now = new Date();
            const dateOptions = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
            const timeOptions = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
            
            const dateEl = document.getElementById('admin-date');
            const timeEl = document.getElementById('admin-time');
            
            if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US', dateOptions);
            if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', timeOptions);
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Mobile Sidebar
        const hamburger = document.querySelector('.hamburger');
        const sidebar = document.querySelector('.admin-sidebar');
        const overlay = document.querySelector('.overlay');

        if (hamburger) {
            hamburger.addEventListener('click', () => {
                sidebar.classList.add('active');
                overlay.classList.add('active');
            });
        }

        if (overlay) {
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
        function showDialog({ title, message, iconType = 'warning', showCancel = true, onConfirm = null }) {
            document.getElementById('dialog-title').textContent = title;
            document.getElementById('dialog-message').textContent = message;
            
            const icon = document.querySelector('#dialog-icon i');
            icon.className = 'fas';
            const iconBox = document.getElementById('dialog-icon');
            
            if (iconType === 'success') {
                icon.classList.add('fa-check-circle');
                iconBox.style.color = 'var(--success)';
                iconBox.style.background = '#d4edda';
            } else if (iconType === 'error') {
                icon.classList.add('fa-times-circle');
                iconBox.style.color = 'var(--error)';
                iconBox.style.background = '#f8d7da';
            } else {
                icon.classList.add('fa-exclamation-triangle');
                iconBox.style.color = 'var(--warning)';
                iconBox.style.background = 'var(--light-accent)';
            }

            const dialog = document.getElementById('custom-dialog');
            const cancelBtn = document.getElementById('dialog-cancel');
            const confirmBtn = document.getElementById('dialog-confirm');

            cancelBtn.style.display = showCancel ? 'block' : 'none';
            dialog.classList.add('active');

            const handleConfirm = () => {
                dialog.classList.remove('active');
                if (onConfirm) onConfirm();
                confirmBtn.removeEventListener('click', handleConfirm);
                cancelBtn.removeEventListener('click', handleCancel);
            };
            
            const handleCancel = () => {
                dialog.classList.remove('active');
                confirmBtn.removeEventListener('click', handleConfirm);
                cancelBtn.removeEventListener('click', handleCancel);
            };

            confirmBtn.onclick = handleConfirm;
            cancelBtn.onclick = handleCancel;
        }

        // Modal Logic
        function openModal(mode, data = null) {
            const modal = document.getElementById('service-modal');
            const title = document.getElementById('modal-title');
            const actionInput = document.getElementById('form-action');
            const form = document.getElementById('service-form');

            form.reset();
            if (mode === 'edit' && data) {
                title.textContent = 'Edit Service Details';
                actionInput.value = 'update';
                document.getElementById('service-id').value = data.id;
                document.getElementById('service-name').value = data.name;
                document.getElementById('service-description').value = data.description;
                document.getElementById('service-duration').value = data.duration_minutes;
                document.getElementById('service-price').value = data.price;
                document.getElementById('service-active').value = data.is_active;
            } else {
                title.textContent = 'Register New Service';
                actionInput.value = 'add';
            }
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('service-modal').classList.remove('active');
        }

        // Deactivate Service (Soft Delete)
        function deactivateService(id, name) {
            showDialog({
                title: 'Confirm Deactivation',
                message: `Are you sure you want to deactivate "${name}"? Deactivated services will not appear in new appointments but will remain in records.`,
                onConfirm: () => {
                    const fd = new FormData();
                    fd.append('action', 'deactivate');
                    fd.append('id', id);

                    fetch(window.location.href, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showDialog({ 
                                title: 'Service Deactivated', 
                                message: data.message, 
                                iconType: 'success', 
                                showCancel: false, 
                                onConfirm: () => location.reload() 
                            });
                        } else {
                            showDialog({ title: 'Error', message: data.message, iconType: 'error', showCancel: false });
                        }
                    })
                    .catch(() => showDialog({ title: 'Error', message: 'Network connection issue.', iconType: 'error', showCancel: false }));
                }
            });
        }

        // Activate Service
        function activateService(id, name) {
            showDialog({
                title: 'Confirm Activation',
                message: `Are you sure you want to activate "${name}"? This service will become available for new appointments.`,
                onConfirm: () => {
                    const fd = new FormData();
                    fd.append('action', 'activate');
                    fd.append('id', id);

                    fetch(window.location.href, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showDialog({ 
                                title: 'Service Activated', 
                                message: data.message, 
                                iconType: 'success', 
                                showCancel: false, 
                                onConfirm: () => location.reload() 
                            });
                        } else {
                            showDialog({ title: 'Error', message: data.message, iconType: 'error', showCancel: false });
                        }
                    })
                    .catch(() => showDialog({ title: 'Error', message: 'Network connection issue.', iconType: 'error', showCancel: false }));
                }
            });
        }

        // AJAX Form Submission
        document.getElementById('service-form').onsubmit = function(e) {
            e.preventDefault();
            const form = this;
            const formData = new FormData(form);
            const isEdit = formData.get('action') === 'update';
            
            showDialog({
                title: 'Confirm Change',
                message: isEdit ? 'Are you sure you want to save these changes?' : 'Are you sure you want to register this new service?',
                onConfirm: () => {
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showDialog({
                                title: 'Success',
                                message: data.message,
                                iconType: 'success',
                                showCancel: false,
                                onConfirm: () => location.reload()
                            });
                        } else {
                            showDialog({ title: 'Error', message: data.message, iconType: 'error', showCancel: false });
                        }
                    })
                    .catch(() => showDialog({ title: 'Error', message: 'Network connection issue.', iconType: 'error', showCancel: false }));
                }
            });
        };

        // Window click for modal
        window.onclick = (e) => {
            if (e.target.id === 'service-modal') closeModal();
        };
    </script>
</body>
</html>