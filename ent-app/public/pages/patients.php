<?php
// Get search query and pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$pageNum = isset($_GET['p']) ? $_GET['p'] : 1;
$limit = 10;

// Load patients
$queryParams = "page=$pageNum&limit=$limit";
if ($search) {
    $queryParams .= "&search=" . urlencode($search);
}

$patientsData = apiCall('GET', '/patients?' . $queryParams);
$patients = isset($patientsData['patients']) ? $patientsData['patients'] : [];
$totalPages = isset($patientsData['pages']) ? $patientsData['pages'] : 1;

// Get patient for editing if ID provided
$editId = isset($_GET['edit']) ? $_GET['edit'] : null;
$editPatient = null;
if ($editId) {
    $editPatient = apiCall('GET', '/patients/' . $editId);
}

$showForm = $editId ? true : false;
$isEditing = $editId ? true : false;
?>

<div class="patients-page">
    <?php if (isset($_GET['status'])): ?>
        <div class="alert <?php echo $_GET['status'] === 'success' ? 'alert-success' : 'alert-danger'; ?>">
            <i class="fas <?php echo $_GET['status'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
            <?php echo e($_GET['message'] ?? ($_GET['status'] === 'success' ? 'Operation completed successfully.' : 'Operation failed.')); ?>
        </div>
    <?php endif; ?>
    <div class="flex flex-between mb-3">
        <div>
            <h2 style="margin: 0; font-size: 1.75rem; font-weight: 700;">Patients Management</h2>
            <p class="text-muted" style="margin-top: 0.5rem;">Manage patient records and information</p>
        </div>
        <button type="button" id="addPatientBtn" class="btn btn-primary" <?php echo $isEditing ? 'style="display:none;"' : ''; ?>>
            <i class="fas fa-plus"></i>
            Add New Patient
        </button>
    </div>

        <div class="card mb-3" id="patientFormCard" <?php echo $isEditing ? '' : 'style="display:none;"'; ?>>
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-<?php echo $editId ? 'edit' : 'user-plus'; ?>"></i>
                    <?php echo $editId ? 'Edit Patient' : 'Add New Patient'; ?>
                </h3>
            </div>
            <form method="POST" action="<?php echo baseUrl(); ?>/" id="patientForm">
                <input type="hidden" name="action" value="<?php echo $editId ? 'update_patient' : 'add_patient'; ?>">
                <?php if ($editId): ?>
                    <input type="hidden" name="id" value="<?php echo e($editId); ?>">
                <?php endif; ?>
                
                <div class="grid grid-2">
                    <div class="form-group-name">
                        <div class="form-group">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control"
                                   value="<?php echo e(isset($editPatient['first_name']) ? $editPatient['first_name'] : ''); ?>" required />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control"
                                   value="<?php echo e(isset($editPatient['last_name']) ? $editPatient['last_name'] : ''); ?>" required />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo e(isset($editPatient['email']) ? $editPatient['email'] : ''); ?>" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control" 
                               value="<?php echo e(isset($editPatient['phone']) ? $editPatient['phone'] : ''); ?>" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control" 
                               value="<?php echo e(isset($editPatient['date_of_birth']) ? $editPatient['date_of_birth'] : ''); ?>" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender *</label>
                        <select name="gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo (isset($editPatient['gender']) && $editPatient['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo (isset($editPatient['gender']) && $editPatient['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo (isset($editPatient['gender']) && $editPatient['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Medical History</label>
                    <textarea name="medical_history" class="form-control" rows="3"><?php echo e(isset($editPatient['medical_history']) ? $editPatient['medical_history'] : ''); ?></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        Save Patient
                    </button>
                    <?php if ($isEditing): ?>
                        <a href="<?php echo baseUrl(); ?>/?page=patients" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" id="cancelAddPatient">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i>
                Patients List
            </h3>
            <form method="GET" action="<?php echo baseUrl(); ?>/" style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="hidden" name="page" value="patients">
                <input type="text" name="search" class="form-control" style="width: 300px;"
                       value="<?php echo e($search); ?>"
                       placeholder="Search by name, email, or phone...">
                <button type="submit" class="btn btn-secondary btn-icon">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>

        <?php if (!empty($patients)): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Gender</th>
                            <th>Date Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                            <tr style="cursor: pointer;" onclick="window.location.href='<?php echo baseUrl(); ?>/?page=patient-profile&id=<?php echo $patient['id']; ?>'">
                                <td>
                                    <strong><?php echo e(isset($patient['first_name']) ? $patient['first_name'] . ' ' . (isset($patient['last_name']) ? $patient['last_name'] : '') : ''); ?></strong>
                                </td>
                                <td><?php echo e(isset($patient['email']) ? $patient['email'] : 'N/A'); ?></td>
                                <td><?php echo e(isset($patient['phone']) ? $patient['phone'] : 'N/A'); ?></td>
                                <td>
                                    <span class="badge-status">
                                        <?php echo e(isset($patient['gender']) ? ucfirst($patient['gender']) : 'N/A'); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate(isset($patient['created_at']) ? $patient['created_at'] : ''); ?></td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="<?php echo baseUrl(); ?>/?page=patient-profile&id=<?php echo $patient['id']; ?>" 
                                           class="btn btn-sm btn-primary btn-icon" title="View Profile">
                                            <i class="fas fa-user"></i>
                                        </a>
                                        <a href="<?php echo baseUrl(); ?>/?page=patients&edit=<?php echo $patient['id']; ?>" 
                                           class="btn btn-sm btn-secondary btn-icon" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="<?php echo baseUrl(); ?>/" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this patient?');">
                                            <input type="hidden" name="action" value="delete_patient">
                                            <input type="hidden" name="id" value="<?php echo $patient['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger btn-icon" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
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
                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                <p style="font-size: 1.125rem;">No patients found</p>
                <?php if ($search): ?>
                    <p>Try adjusting your search criteria</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="<?php echo baseUrl(); ?>/?page=patients&p=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                   class="page-btn <?php echo $i == $pageNum ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addBtn = document.getElementById('addPatientBtn');
        const formCard = document.getElementById('patientFormCard');
        const form = document.getElementById('patientForm');
        const cancelBtn = document.getElementById('cancelAddPatient');

        if (addBtn && formCard && form) {
            addBtn.addEventListener('click', () => {
                formCard.style.display = '';
                addBtn.style.display = 'none';
                form.reset();
            });
        }

        if (cancelBtn && formCard && form) {
            cancelBtn.addEventListener('click', () => {
                formCard.style.display = 'none';
                if (addBtn) {
                    addBtn.style.display = '';
                }
                form.reset();
            });
        }
    });
    </script>
