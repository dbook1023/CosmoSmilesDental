<?php
/**
 * Reusable Admin Sidebar Component
 * 
 * Required variables before including:
 * - $currentPage (string): The active page identifier (e.g., 'dashboard', 'appointments', 'patients', etc.)
 * - $sidebarAdminName (string): The admin display name (e.g., 'Dr. John Doe')
 * - $sidebarAdminRole (string): The admin role label (e.g., 'Administrator', 'Dentist')
 */

// Fallback defaults
$currentPage = $currentPage ?? '';

// Centralized Admin Identity Logic (guarantees consistency across all 8 pages)
$adminFirstName = $_SESSION['admin_first_name'] ?? '';
$adminLastName = $_SESSION['admin_last_name'] ?? '';
$adminRoleCode = strtolower($_SESSION['admin_role'] ?? 'admin');

// Standardize Name
$sidebarAdminName = trim($adminFirstName . ' ' . $adminLastName);
if (empty($sidebarAdminName)) {
    $sidebarAdminName = $_SESSION['admin_username'] ?? ($_SESSION['admin_full_name'] ?? 'Administrator');
}
if (!str_starts_with($sidebarAdminName, 'Dr.') && strtolower($sidebarAdminName) !== 'administrator') {
    $sidebarAdminName = 'Dr. ' . $sidebarAdminName;
}

// Standardize Role
if ($adminRoleCode === 'admin' || strtolower($adminRoleCode) === 'administrator') {
    $sidebarAdminRole = 'Administrator';
} else {
    $sidebarAdminRole = ucfirst($adminRoleCode);
}

// Define nav items
$sidebarNavItems = [
    ['id' => 'dashboard',    'href' => 'admin-dashboard.php',    'icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard'],
    ['id' => 'appointments', 'href' => 'admin-appointments.php', 'icon' => 'fas fa-calendar-alt',   'label' => 'Appointments'],
    ['id' => 'patients',     'href' => 'admin-patients.php',     'icon' => 'fas fa-users',          'label' => 'Patients'],
    ['id' => 'records',      'href' => 'admin-records.php',      'icon' => 'fas fa-file-medical',   'label' => 'Patient Records'],
    ['id' => 'staff',        'href' => 'admin-staff.php',        'icon' => 'fas fa-user-md',        'label' => 'Staff Management'],
    ['id' => 'messages',     'href' => 'admin-messages.php',     'icon' => 'fas fa-message',        'label' => 'Messages'],
    ['id' => 'services',     'href' => 'admin-services.php',     'icon' => 'fas fa-teeth',          'label' => 'Services'],
    ['id' => 'settings',     'href' => 'admin-settings.php',     'icon' => 'fas fa-cogs',           'label' => 'Admin Settings'],
];
?>
<!-- Sidebar -->
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-tooth"></i> Dental Admin</h3>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">
            <?php foreach ($sidebarNavItems as $item): ?>
                <a href="<?php echo $item['href']; ?>" class="sidebar-item<?php echo ($currentPage === $item['id']) ? ' active' : ''; ?>">
                    <i class="<?php echo $item['icon']; ?>"></i>
                    <span><?php echo $item['label']; ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
    
    <div class="sidebar-footer">
        <div class="admin-profile">
            <div class="profile-avatar">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="profile-info">
                <span class="profile-name"><?php echo htmlspecialchars($sidebarAdminName); ?></span>
                <span class="profile-role"><?php echo htmlspecialchars($sidebarAdminRole); ?></span>
            </div>
        </div>
        <a href="admin-logout.php" class="sidebar-item logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>
