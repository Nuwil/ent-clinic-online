<?php
/**
 * Doctor Dashboard
 */
requireRole('doctor');
$currentUser = getCurrentUser();
?>
<div class="doctor-dashboard">
    <div class="mb-3">
        <h2 style="margin: 0; font-size: 1.75rem; font-weight: 700;">Doctor Dashboard</h2>
        <p class="text-muted" style="margin-top: 0.5rem;">Welcome, Dr. <?php echo e($currentUser['full_name'] ?? $currentUser['username'] ?? 'Doctor'); ?></p>
    </div>

    <div class="grid grid-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">Total Patients</div>
                    <div style="font-size: 2.5rem; font-weight: 700;">
                        <?php
                        $patientsData = apiCall('GET', '/patients?limit=1');
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
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">Your Role</div>
                    <div style="font-size: 1.5rem; font-weight: 700;">Doctor</div>
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-top: 0.25rem;">Patient Care Access</div>
                </div>
                <div style="font-size: 3rem; opacity: 0.3;">
                    <i class="fas fa-user-md"></i>
                </div>
            </div>
        </div>

        <div class="card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border: none;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">Quick Access</div>
                    <div style="font-size: 1.5rem; font-weight: 700;">Ready</div>
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-top: 0.25rem;">All systems operational</div>
                </div>
                <div style="font-size: 3rem; opacity: 0.3;">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-users"></i>
                    Patient Management
                </h3>
            </div>
            <div class="card-body">
                <p class="text-muted">View and manage patient records, medical history, and treatment information.</p>
                <a href="<?php echo baseUrl(); ?>/?page=patients" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    View Patients
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-stethoscope"></i>
                    Patient Visits
                </h3>
            </div>
            <div class="card-body">
                <p class="text-muted">Record and manage patient visits, diagnoses, treatments, and prescriptions.</p>
                <a href="<?php echo baseUrl(); ?>/?page=patients" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    Manage Visits
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line"></i>
                    Analytics & Trends
                </h3>
            </div>
            <div class="card-body">
                <p class="text-muted">View clinic analytics, visit patterns, and forecasting to help with patient care planning.</p>
                <a href="<?php echo baseUrl(); ?>/?page=analytics" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    View Analytics
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-injured"></i>
                    Patient Profiles
                </h3>
            </div>
            <div class="card-body">
                <p class="text-muted">Access detailed patient profiles with complete medical history and visit records.</p>
                <a href="<?php echo baseUrl(); ?>/?page=patients" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    Browse Patients
                </a>
            </div>
        </div>
    </div>
</div>
