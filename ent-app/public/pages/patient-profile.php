<?php
/**
 * Patient Profile Page - Accessible by all authenticated users
 */
// Get patient ID
$patientId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$patientId) {
    redirect('/?page=patients');
}

// Load patient data
$patient = apiCall('GET', '/api/patients/' . $patientId);

if (!$patient) {
    $_SESSION['message'] = 'Patient not found';
    redirect('/?page=patients');
}

// Load patient visits (handle if table doesn't exist)
$visits = apiCall('GET', '/api/visits?patient_id=' . $patientId);
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
                <button type="button" id="openEditProfileBtn" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Profile
                </button>
                <a href="<?php echo baseUrl(); ?>/?page=medical-certificate&patient_id=<?php echo $patientId; ?>" class="btn btn-secondary">
                    <i class="fas fa-file-pdf"></i>
                    Print Certificate
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
                    <!-- Email removed from profile view per request -->
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
        </div>

        <!-- Edit Profile Modal -->
        <div class="modal" id="editProfileModal" hidden aria-hidden="true" role="dialog" aria-modal="true">
            <div class="modal-backdrop"></div>
            <div class="modal-dialog form-modal">
                <div class="modal-header">
                    <h2 class="modal-title">Edit Patient Profile</h2>
                    <button type="button" class="modal-close" id="closeEditProfileModal" aria-label="Close modal">&times;</button>
                </div>
                <form id="editProfileForm" method="POST" action="<?php echo baseUrl(); ?>/">
                    <div class="modal-body">
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="cancelEditProfileBtn">Cancel</button>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Visit Timeline -->
        <?php if (hasRole(['admin', 'doctor'])): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-check"></i>
                    Visit Timeline
                </h3>
            </div>

            <!-- Add Visit Modal -->
            <div class="modal" id="visitModal" hidden aria-hidden="true" role="dialog" aria-modal="true">
                <div class="modal-backdrop" data-modal-dismiss="visitModal"></div>
                <div class="modal-dialog form-modal">
                    <div class="modal-header">
                        <h3 class="modal-title">
                            <i class="fas fa-calendar-plus"></i>
                            Add New Visit
                        </h3>
                        <button type="button" class="modal-close" data-modal-dismiss="visitModal" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                    <form method="POST" action="<?php echo baseUrl(); ?>/">
                        <input type="hidden" name="action" value="add_visit">
                        <input type="hidden" name="patient_id" value="<?php echo $patientId; ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Visit Date & Time *</label>
                            <?php
                                // Default the datetime-local input to current time in Manila
                                $manilaNow = new DateTime('now', new DateTimeZone('Asia/Manila'));
                                $manilaValue = $manilaNow->format('Y-m-d\\TH:i');
                            ?>
                            <input type="datetime-local" name="visit_date" class="form-control" 
                                   value="<?php echo e($manilaValue); ?>" required />
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
                            <label class="form-label">Plan</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save"></i>
                            Save Visit
                        </button>
                        <button type="button" class="btn btn-secondary btn-lg" data-modal-dismiss="visitModal">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                    </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($visitsList)): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Visit Type</th>
                                <th>ENT</th>
                                <th>Chief Complaint</th>
                                <th>Diagnosis</th>
                                <th>Treatment</th>
                                <th>Prescription</th>
                                <th>Plan</th>
                                <th>Doctor</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($visitsList as $visit): ?>
                            <tr>
                                <td><?php echo formatDate(isset($visit['visit_date']) ? $visit['visit_date'] : '', true); ?></td>
                                <td><?php echo e(isset($visit['visit_type']) ? $visit['visit_type'] : ''); ?></td>
                                <td><?php echo e(isset($visit['ent_type']) ? ucfirst($visit['ent_type']) : ''); ?></td>
                                <td><?php echo isset($visit['chief_complaint']) ? nl2br(e($visit['chief_complaint'])) : ''; ?></td>
                                <td><?php echo isset($visit['diagnosis']) ? nl2br(e($visit['diagnosis'])) : ''; ?></td>
                                <td><?php echo isset($visit['treatment_plan']) ? nl2br(e($visit['treatment_plan'])) : ''; ?></td>
                                <td><?php echo isset($visit['prescription']) ? nl2br(e($visit['prescription'])) : ''; ?></td>
                                <td><?php echo isset($visit['notes']) ? nl2br(e($visit['notes'])) : ''; ?></td>
                                <td>
                                    <?php
                                        $doc = isset($visit['doctor_name']) ? trim(strtolower($visit['doctor_name'])) : '';
                                        if ($doc && $doc !== 'admin' && $doc !== 'administrator') {
                                            echo e($visit['doctor_name']);
                                        } else {
                                            echo '-';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="<?php echo baseUrl(); ?>/?page=patient-profile&id=<?php echo $patientId; ?>&edit=visit&visit_id=<?php echo isset($visit['id']) ? $visit['id'] : ''; ?>" class="btn btn-sm btn-secondary btn-icon" title="Edit Visit"><i class="fas fa-edit"></i></a>
                                        <form method="POST" action="<?php echo baseUrl(); ?>/" style="display:inline;" onsubmit="return confirm('Delete this visit?');">
                                            <input type="hidden" name="action" value="delete_visit">
                                            <input type="hidden" name="id" value="<?php echo isset($visit['id']) ? $visit['id'] : ''; ?>">
                                            <input type="hidden" name="patient_id" value="<?php echo $patientId; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center p-3 text-muted">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p style="font-size: 1.125rem;">No visits recorded yet</p>
                    <button type="button" id="addFirstVisitBtn" class="btn btn-primary mt-2">
                        <i class="fas fa-plus"></i>
                        Add First Visit
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-calendar-check"></i> Visit Timeline</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">Visit timeline is not available for Secretary accounts.</p>
                </div>
            </div>
        <?php endif; ?>
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
    const addFirstBtn = document.getElementById('addFirstVisitBtn');
    const modal = document.getElementById('visitModal');
    function setupFocusTrap(el) {
        if (!el) return;
        const focusable = Array.from(el.querySelectorAll('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'));
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const handler = function(e) {
            if (e.key !== 'Tab') return;
            if (focusable.length === 0) { e.preventDefault(); return; }
            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault(); last.focus();
                }
            } else {
                if (document.activeElement === last) {
                    e.preventDefault(); first.focus();
                }
            }
        };
        el._focusTrap = handler;
        document.addEventListener('keydown', handler);
    }

    function removeFocusTrap(el) {
        if (!el || !el._focusTrap) return;
        document.removeEventListener('keydown', el._focusTrap);
        delete el._focusTrap;
    }

    function openVisitModal() {
        if (!modal) return;
        modal.removeAttribute('hidden');
        modal.classList.add('open');
        const main = document.querySelector('.main-content');
        if (main) main.setAttribute('aria-hidden', 'true');
        setupFocusTrap(modal);
        setTimeout(function() {
            const firstField = modal.querySelector('input[name="visit_date"]');
            if (firstField) firstField.focus();
        }, 50);
    }

    function closeVisitModal() {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('hidden', '');
        const main = document.querySelector('.main-content');
        if (main) main.removeAttribute('aria-hidden');
        removeFocusTrap(modal);
    }

    if (addFirstBtn) {
        addFirstBtn.addEventListener('click', function() {
            openVisitModal();
        });
    }

    if (modal) {
        modal.querySelectorAll('[data-modal-dismiss="visitModal"]').forEach(function(el) {
            el.addEventListener('click', closeVisitModal);
        });
        // prevent backdrop click from closing (enforce explicit close)
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                e.stopPropagation();
            }
        });
        // close on Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('open')) {
                closeVisitModal();
            }
        });
    }

    // Auto-open when requested via query param (?add=visit)
    if (<?php echo $showAddVisit ? 'true' : 'false'; ?>) {
        openVisitModal();
    }

    // Edit Profile Modal handlers
    const editProfileModal = document.getElementById('editProfileModal');
    const openEditProfileBtn = document.getElementById('openEditProfileBtn');
    const closeEditProfileModalBtn = document.getElementById('closeEditProfileModal');
    const cancelEditProfileBtn = document.getElementById('cancelEditProfileBtn');
    const editProfileForm = document.getElementById('editProfileForm');

    function openEditProfileModal() {
        if (!editProfileModal) return;
        editProfileModal.removeAttribute('hidden');
        editProfileModal.classList.add('open');
        const main = document.querySelector('.main-content');
        if (main) main.setAttribute('aria-hidden', 'true');
        setupFocusTrap(editProfileModal);
        setTimeout(function() {
            const firstField = editProfileModal.querySelector('input[name="first_name"]');
            if (firstField) firstField.focus();
        }, 50);
    }

    function closeEditProfileModal() {
        if (!editProfileModal) return;
        editProfileModal.classList.remove('open');
        editProfileModal.setAttribute('hidden', '');
        const main = document.querySelector('.main-content');
        if (main) main.removeAttribute('aria-hidden');
        removeFocusTrap(editProfileModal);
    }

    if (openEditProfileBtn) {
        openEditProfileBtn.addEventListener('click', openEditProfileModal);
    }

    if (closeEditProfileModalBtn) {
        closeEditProfileModalBtn.addEventListener('click', closeEditProfileModal);
    }

    if (cancelEditProfileBtn) {
        cancelEditProfileBtn.addEventListener('click', closeEditProfileModal);
    }

    if (editProfileModal) {
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && editProfileModal.classList.contains('open')) {
                closeEditProfileModal();
            }
        });
    }

    // Auto-open when requested via query param (?edit=profile)
    if (<?php echo $editProfile ? 'true' : 'false'; ?>) {
        openEditProfileModal();
    }

});
</script>

