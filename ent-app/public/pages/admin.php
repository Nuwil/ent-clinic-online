<?php
/**
 * Admin Dashboard
 */
requireRole('admin');
$currentUser = getCurrentUser();
?>
<div class="admin-dashboard">
    <div class="mb-3">
        <h2 style="margin: 0; font-size: 1.75rem; font-weight: 700;">Admin Dashboard</h2>
        <p class="text-muted" style="margin-top: 0.5rem;">Welcome back, <?php echo e($currentUser['full_name'] ?? $currentUser['username'] ?? 'Administrator'); ?></p>
    </div>

    <div class="grid grid-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">Total Patients</div>
                    <div style="font-size: 2.5rem; font-weight: 700;">
                        <?php
                        $patientsData = apiCall('GET', '/api/patients?limit=1');
                        echo e($patientsData['total'] ?? 0);
                        ?>
                    </div>
                </div>
                <div style="font-size: 3rem; opacity: 0.3;">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>

        <div class="card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">System Users</div>
                    <div style="font-size: 2.5rem; font-weight: 700;">
                        <?php
                        require_once __DIR__ . '/../../config/Database.php';
                        try {
                            $db = Database::getInstance();
                            $result = $db->fetch('SELECT COUNT(*) as count FROM users');
                            $userCount = $result['count'] ?? 0;
                            echo e($userCount);
                        } catch (Exception $e) {
                            echo '0';
                        }
                        ?>
                    </div>
                </div>
                <div style="font-size: 3rem; opacity: 0.3;">
                    <i class="fas fa-user-shield"></i>
                </div>
            </div>
        </div>

        <div class="card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border: none;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">Your Role</div>
                    <div style="font-size: 1.5rem; font-weight: 700;">Administrator</div>
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-top: 0.25rem;">Full System Access</div>
                </div>
                <div style="font-size: 3rem; opacity: 0.3;">
                    <i class="fas fa-crown"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-users-cog"></i>
                    User Management
                </h3>
            </div>
            <div class="card-body">
                <p class="text-muted">Create, update, and manage user accounts for doctors, secretaries, and other administrators.</p>
                <a href="<?php echo baseUrl(); ?>/?page=settings" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    Go to Settings
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-users"></i>
                    Patient Management
                </h3>
            </div>
            <div class="card-body">
                <p class="text-muted">Access and manage all patient records, medical history, and visit information.</p>
                <a href="<?php echo baseUrl(); ?>/?page=patients" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    View Patients
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-database"></i>
                    System Administration
                </h3>
            </div>
            <div class="card-body">
                <p class="text-muted">Export/import data, manage system configuration, and perform administrative tasks.</p>
                <a href="<?php echo baseUrl(); ?>/?page=settings" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    System Settings
                </a>
            </div>
        </div>
    </div>
</div>
