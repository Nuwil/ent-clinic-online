<?php
// Get patient ID
$patientId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$patientId) {
    redirect('/?page=patients');
}

// Load patient data
$patient = apiCall('GET', '/patients/' . $patientId);

if (!$patient) {
    $_SESSION['message'] = 'Patient not found';
    redirect('/?page=patients');
}

// Load patient visits (handle if table doesn't exist)
$visits = apiCall('GET', '/visits?patient_id=' . $patientId);
$visitsList = isset($visits['visits']) ? $visits['visits'] : [];
if (!$visitsList && $visits === null) {
    $visitsList = []; // Table might not exist yet
}

// Check if editing profile
$editProfile = isset($_GET['edit']) && $_GET['edit'] === 'profile';
$showAddVisit = isset($_GET['add']) && $_GET['add'] === 'visit';
?>

<div class="patient-profile-page">
    <!-- Back Button -->
    <div class="mb-3">
        <a href="<?php echo baseUrl(); ?>/?page=patients" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Back to Patients
        </a>
    </div>

    <!-- Patient Header -->
    <div class="card mb-3">
        <div class="flex flex-between">
            <div>
                <h2 style="margin: 0; font-size: 1.75rem; font-weight: 700;">
                    <?php echo e(isset($patient['first_name']) ? $patient['first_name'] . ' ' . (isset($patient['last_name']) ? $patient['last_name'] : '') : ''); ?>
                </h2>
                <p class="text-muted" style="margin-top: 0.5rem;">
                    Patient ID: <?php echo e(isset($patient['patient_id']) ? $patient['patient_id'] : ''); ?>
                </p>
            </div>
            <div class="flex gap-2">
                <a href="<?php echo baseUrl(); ?>/?page=patient-profile&id=<?php echo $patientId; ?>&edit=profile" 
                   class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Profile
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-2">
        <!-- Patient Information -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user"></i>
                    Personal Information
                </h3>
            </div>

            <?php if ($editProfile): ?>
                <form method="POST" action="<?php echo baseUrl(); ?>/">
                    <input type="hidden" name="action" value="update_patient_profile">
                    <input type="hidden" name="id" value="<?php echo $patientId; ?>">
                    
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" 
                               value="<?php echo e(isset($patient['first_name']) ? $patient['first_name'] : ''); ?>" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" 
                               value="<?php echo e(isset($patient['last_name']) ? $patient['last_name'] : ''); ?>" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control" 
                               value="<?php echo e(isset($patient['date_of_birth']) ? $patient['date_of_birth'] : ''); ?>" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender *</label>
                        <select name="gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo (isset($patient['gender']) && $patient['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo (isset($patient['gender']) && $patient['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo (isset($patient['gender']) && $patient['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo e(isset($patient['email']) ? $patient['email'] : ''); ?>" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control" 
                               value="<?php echo e(isset($patient['phone']) ? $patient['phone'] : ''); ?>" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Occupation</label>
                        <input type="text" name="occupation" class="form-control" 
                               value="<?php echo e(isset($patient['occupation']) ? $patient['occupation'] : ''); ?>" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" 
                               value="<?php echo e(isset($patient['address']) ? $patient['address'] : ''); ?>" />
                    </div>
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" 
                                   value="<?php echo e(isset($patient['city']) ? $patient['city'] : ''); ?>" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" 
                                   value="<?php echo e(isset($patient['state']) ? $patient['state'] : ''); ?>" />
                        </div>
                    </div>
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" class="form-control" 
                                   value="<?php echo e(isset($patient['postal_code']) ? $patient['postal_code'] : ''); ?>" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-control" 
                                   value="<?php echo e(isset($patient['country']) ? $patient['country'] : ''); ?>" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Medical History</label>
                        <textarea name="medical_history" class="form-control" rows="4"><?php echo e(isset($patient['medical_history']) ? $patient['medical_history'] : ''); ?></textarea>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i>
                            Save Changes
                        </button>
                        <a href="<?php echo baseUrl(); ?>/?page=patient-profile&id=<?php echo $patientId; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border-color);">
                        <span class="text-muted">Name:</span>
                        <strong><?php echo e(isset($patient['first_name']) ? $patient['first_name'] . ' ' . (isset($patient['last_name']) ? $patient['last_name'] : '') : ''); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border-color);">
                        <span class="text-muted">Date of Birth:</span>
                        <strong><?php echo e(isset($patient['date_of_birth']) ? formatDate($patient['date_of_birth']) : 'N/A'); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border-color);">
                        <span class="text-muted">Gender:</span>
                        <strong><?php echo e(isset($patient['gender']) ? ucfirst($patient['gender']) : 'N/A'); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border-color);">
                        <span class="text-muted">Email:</span>
                        <strong><?php echo e(isset($patient['email']) ? $patient['email'] : 'N/A'); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border-color);">
                        <span class="text-muted">Phone:</span>
                        <strong><?php echo e(isset($patient['phone']) ? $patient['phone'] : 'N/A'); ?></strong>
                    </div>
                    <?php if (isset($patient['occupation']) && $patient['occupation']): ?>
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border-color);">
                        <span class="text-muted">Occupation:</span>
                        <strong><?php echo e($patient['occupation']); ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($patient['address']) && $patient['address']): ?>
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border-color);">
                        <span class="text-muted">Address:</span>
                        <strong style="text-align: right;">
                            <?php 
                            $addressParts = array_filter([
                                $patient['address'] ?? '',
                                $patient['city'] ?? '',
                                $patient['state'] ?? '',
                                $patient['postal_code'] ?? '',
                                $patient['country'] ?? ''
                            ]);
                            echo e(implode(', ', $addressParts));
                            ?>
                        </strong>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($patient['medical_history']) && $patient['medical_history']): ?>
                    <div style="padding: 0.75rem 0;">
                        <span class="text-muted" style="display: block; margin-bottom: 0.5rem;">Medical History:</span>
                        <p><?php echo nl2br(e($patient['medical_history'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Visit Timeline -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-check"></i>
                    Visit Timeline
                </h3>
                <button type="button" id="addVisitBtn" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i>
                    Add Visit
                </button>
            </div>
            <?php 
                // Always render the add visit container but hide it by default for client-side toggling.
                $addVisibleAttr = $showAddVisit ? '' : 'style="display:none; margin-bottom: 1.5rem;"';
            ?>
            <div id="addVisitContainer" <?php echo $addVisibleAttr; ?> class="add-visit-container" style="padding: 1.5rem; background: var(--bg-primary); border-radius: 12px;">
                    <h4 style="margin-bottom: 1rem;">Add New Visit</h4>
                    <form method="POST" action="<?php echo baseUrl(); ?>/">
                        <input type="hidden" name="action" value="add_visit">
                        <input type="hidden" name="patient_id" value="<?php echo $patientId; ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Visit Date & Time *</label>
                            <input type="datetime-local" name="visit_date" class="form-control" 
                                   value="<?php echo date('Y-m-d\TH:i'); ?>" required />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Visit Type *</label>
                            <select name="visit_type" class="form-control" required>
                                <option value="">Select Visit Type</option>
                                <option value="Consultation">Consultation</option>
                                <option value="Follow-up">Follow-up</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Routine Check">Routine Check</option>
                                <option value="Procedure">Procedure</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ENT Classification *</label>
                            <select name="ent_type" class="form-control" required>
                                <option value="ear">Ear</option>
                                <option value="nose">Nose</option>
                                <option value="throat">Throat</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Chief Complaint</label>
                            <textarea name="chief_complaint" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Diagnosis</label>
                            <textarea name="diagnosis" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Treatment Plan</label>
                            <textarea name="treatment_plan" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Prescription</label>
                            <textarea name="prescription" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i>
                                Save Visit
                            </button>
                            <button type="button" id="cancelAddVisit" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

            <?php if (!empty($visitsList)): ?>
                <div class="timeline">
                    <?php foreach ($visitsList as $visit): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <div>
                                        <h4 style="margin:0;"><?php echo e(isset($visit['visit_type']) ? $visit['visit_type'] : 'Visit'); ?></h4>
                                        <small class="text-muted">
                                            ENT Focus:
                                            <span class="badge-status">
                                                <?php echo ucfirst($visit['ent_type'] ?? 'ear'); ?>
                                            </span>
                                        </small>
                                    </div>
                                    <span class="timeline-date">
                                        <?php echo formatDate(isset($visit['visit_date']) ? $visit['visit_date'] : '', true); ?>
                                    </span>
                                </div>
                                <?php if (isset($visit['chief_complaint']) && $visit['chief_complaint']): ?>
                                    <div class="timeline-section">
                                        <strong>Chief Complaint:</strong>
                                        <p><?php echo nl2br(e($visit['chief_complaint'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($visit['diagnosis']) && $visit['diagnosis']): ?>
                                    <div class="timeline-section">
                                        <strong>Diagnosis:</strong>
                                        <p><?php echo nl2br(e($visit['diagnosis'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($visit['treatment_plan']) && $visit['treatment_plan']): ?>
                                    <div class="timeline-section">
                                        <strong>Treatment Plan:</strong>
                                        <p><?php echo nl2br(e($visit['treatment_plan'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($visit['prescription']) && $visit['prescription']): ?>
                                    <div class="timeline-section">
                                        <strong>Prescription:</strong>
                                        <p><?php echo nl2br(e($visit['prescription'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($visit['notes']) && $visit['notes']): ?>
                                    <div class="timeline-section">
                                        <strong>Notes:</strong>
                                        <p><?php echo nl2br(e($visit['notes'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($visit['doctor_name']) && $visit['doctor_name']): ?>
                                    <div class="timeline-footer">
                                        <small class="text-muted">
                                            <i class="fas fa-user-md"></i>
                                            Doctor: <?php echo e($visit['doctor_name']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center p-3 text-muted">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p style="font-size: 1.125rem;">No visits recorded yet</p>
                    <a href="<?php echo baseUrl(); ?>/?page=patient-profile&id=<?php echo $patientId; ?>&add=visit" class="btn btn-primary mt-2">
                        <i class="fas fa-plus"></i>
                        Add First Visit
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline-item {
    position: relative;
    padding-bottom: 2rem;
    border-left: 2px solid var(--border-color);
    padding-left: 2rem;
    margin-left: -2rem;
}

.timeline-item:last-child {
    border-left: none;
}

.timeline-marker {
    position: absolute;
    left: -12px;
    top: 0;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.75rem;
    box-shadow: var(--shadow);
}

.timeline-content {
    background: var(--bg-primary);
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border-color);
}

.timeline-header h4 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--dark);
}

.timeline-date {
    font-size: 0.875rem;
    color: var(--gray);
    font-weight: 500;
}

.timeline-section {
    margin-bottom: 1rem;
}

.timeline-section strong {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--dark);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.timeline-section p {
    margin: 0;
    color: var(--gray);
    line-height: 1.6;
}

.timeline-footer {
    margin-top: 1rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border-color);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addBtn = document.getElementById('addVisitBtn');
    const addContainer = document.getElementById('addVisitContainer');
    const cancelBtn = document.getElementById('cancelAddVisit');

    if (addBtn && addContainer) {
        addBtn.addEventListener('click', function() {
            addContainer.style.display = '';
            addBtn.style.display = 'none';
            window.scrollTo({ top: addContainer.offsetTop - 20, behavior: 'smooth' });
        });
    }

    if (cancelBtn && addContainer) {
        cancelBtn.addEventListener('click', function() {
            addContainer.style.display = 'none';
            if (addBtn) addBtn.style.display = '';
        });
    }
});
</script>

