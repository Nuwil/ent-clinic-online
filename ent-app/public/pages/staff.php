<?php
/**
 * Secretary / Staff Dashboard
 */
requireRole('staff');
$currentUser = getCurrentUser();
?>
<div class="staff-dashboard">
    <div class="mb-3">
        <h2 style="margin: 0; font-size: 1.75rem; font-weight: 700;">Secretary Dashboard</h2>
        <p class="text-muted" style="margin-top: 0.5rem;">Welcome, <?php echo e($currentUser['full_name'] ?? $currentUser['username'] ?? 'Secretary'); ?></p>
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
                    <div style="font-size: 1.5rem; font-weight: 700;">Secretary</div>
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-top: 0.25rem;">Patient Management</div>
                </div>
                <div style="font-size: 3rem; opacity: 0.3;">
                    <i class="fas fa-clipboard-list"></i>
                </div>
            </div>
        </div>

        <div class="card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border: none;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">System Status</div>
                    <div style="font-size: 1.5rem; font-weight: 700;">Active</div>
                    <div style="font-size: 0.875rem; opacity: 0.9; margin-top: 0.25rem;">All systems ready</div>
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
                <p class="text-muted">View and manage patient records, contact information, and basic patient data.</p>
                <a href="<?php echo baseUrl(); ?>/?page=patients" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    View Patients
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-circle"></i>
                    Patient Profiles
                </h3>
            </div>
            <div class="card-body">
                <p class="text-muted">Access patient profiles to view detailed information and contact details.</p>
                <a href="<?php echo baseUrl(); ?>/?page=patients" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    Browse Patients
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-plus"></i>
                    Add New Patient
                </h3>
            </div>
            <div class="card-body">
                <p class="text-muted">Register new patients and enter their basic information into the system.</p>
                <a href="<?php echo baseUrl(); ?>/?page=patients" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    Add Patient
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-search"></i>
                    Search Patients
                </h3>
            </div>
            <div class="card-body">
                <p class="text-muted">Quickly find patients by name, phone number, or other identifying information.</p>
                <a href="<?php echo baseUrl(); ?>/?page=patients" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    Search Patients
                </a>
            </div>
        </div>
    </div>
</div>
