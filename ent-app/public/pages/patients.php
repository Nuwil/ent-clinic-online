<?php
/**
 * Patients Page - Accessible by all authenticated users
 */
// Get search query and pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$pageNum = isset($_GET['p']) ? $_GET['p'] : 1;
$limit = 10;

// Load patients
$queryParams = "page=$pageNum&limit=$limit";
if ($search) {
    $queryParams .= "&search=" . urlencode($search);
}

$patientsData = apiCall('GET', '/api/patients?' . $queryParams);
$patients = isset($patientsData['patients']) ? $patientsData['patients'] : [];
$totalPages = isset($patientsData['pages']) ? $patientsData['pages'] : 1;

// Get patient for editing if ID provided
$editId = isset($_GET['edit']) ? $_GET['edit'] : null;
$editPatient = null;
if ($editId) {
    $editPatient = apiCall('GET', '/api/patients/' . $editId);
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
        <div>
            <button id="showAddPatientBtn" class="btn btn-primary">
                <i class="fas fa-user-plus"></i>
                Add New Patient
            </button>
        </div>
    </div>

    <!-- Add/Edit Patient Modal -->
    <div class="modal" id="patientModal" <?php echo $showForm ? '' : 'hidden aria-hidden="true"'; ?> role="dialog"
        aria-modal="true">
        <div class="modal-backdrop" data-modal-dismiss="patientModal"></div>
        <div class="modal-dialog form-modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-<?php echo $editId ? 'edit' : 'user-plus'; ?>"></i>
                    <?php echo $editId ? 'Edit Patient' : 'Add New Patient'; ?>
                </h3>
                <button type="button" class="modal-close" data-modal-dismiss="patientModal" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" action="<?php echo baseUrl(); ?>/" id="patientForm">
                    <input type="hidden" name="action"
                        value="<?php echo $editId ? 'update_patient' : 'add_patient'; ?>">
                    <?php if ($editId): ?>
                        <input type="hidden" name="id" value="<?php echo e($editId); ?>">
                    <?php endif; ?>

                    <div class="grid grid-2">
                        <div class="form-group-name">
                            <div class="form-group">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" class="form-control"
                                    value="<?php echo e(isset($editPatient['first_name']) ? $editPatient['first_name'] : ''); ?>"
                                    required />
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" class="form-control"
                                    value="<?php echo e(isset($editPatient['last_name']) ? $editPatient['last_name'] : ''); ?>"
                                    required />
                            </div>
                        </div>
                        <div class="form-group-name">
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
                        <div class="form-group-name">
                            <div class="form-group">
                                <label class="form-label">Height (cm)</label>
                                <input type="text" name="height" class="form-control"
                                    value="<?php echo e(isset($editPatient['height']) ? $editPatient['height'] : ''); ?>" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">Weight (kg)</label>
                                <input type="text" name="weight" class="form-control"
                                    value="<?php echo e(isset($editPatient['weight']) ? $editPatient['weight'] : ''); ?>" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control"
                                value="<?php echo e(intval(isset($editPatient['phone']) ? $editPatient['phone'] : '')); ?>" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Occupation</label>
                            <input type="text" name="occupation" class="form-control"
                                value="<?php echo e(isset($editPatient['occupation']) ? $editPatient['occupation'] : ''); ?>" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control"
                                value="<?php echo e(isset($editPatient['address']) ? $editPatient['address'] : ''); ?>" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <select name="city" id="citySelect" class="form-control" data-selected="<?php echo e(isset($editPatient['city']) ? $editPatient['city'] : ''); ?>">
                                <option value=""><?php echo e(isset($editPatient['city']) ? $editPatient['city'] : '-- Select City --'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">State/Province</label>
                            <select name="state" id="stateSelect" class="form-control" data-selected="<?php echo e(isset($editPatient['state']) ? $editPatient['state'] : ''); ?>">
                                <option value=""><?php echo e(isset($editPatient['state']) ? $editPatient['state'] : '-- Select State/Province --'); ?></option>
                                    <option value="Occidental Mindoro">Occidental Mindoro (Mamburao)</option>
                                    <option value="Oriental Mindoro">Oriental Mindoro (Calapan City)</option>
                                    <option value="Palawan">Palawan (Puerto Princesa City)</option>
                                    <option value="Romblon">Romblon (Romblon)</option>
                                </optgroup>
                                <optgroup label="Bicol Region (Region V)">
                                    <option value="Albay">Albay (Legazpi City)</option>
                                    <option value="Camarines Norte">Camarines Norte (Daet)</option>
                                    <option value="Camarines Sur">Camarines Sur (Pili)</option>
                                    <option value="Catanduanes">Catanduanes (Virac)</option>
                                    <option value="Masbate">Masbate (Masbate City)</option>
                                    <option value="Sorsogon">Sorsogon (Sorsogon City)</option>
                                </optgroup>
                                <optgroup label="Western Visayas (Region VI)">
                                    <option value="Aklan">Aklan (Kalibo)</option>
                                    <option value="Antique">Antique (San Jose de Buenavista)</option>
                                    <option value="Capiz">Capiz (Roxas City)</option>
                                    <option value="Guimaras">Guimaras (Jordan)</option>
                                    <option value="Iloilo">Iloilo (Iloilo City)</option>
                                    <option value="Negros Occidental">Negros Occidental (Bacolod City)</option>
                                </optgroup>
                                <optgroup label="Central Visayas (Region VII)">
                                    <option value="Bohol">Bohol (Tagbilaran City)</option>
                                    <option value="Cebu">Cebu (Cebu City)</option>
                                    <option value="Negros Oriental">Negros Oriental (Dumaguete City)</option>
                                    <option value="Siquijor">Siquijor (Siquijor)</option>
                                </optgroup>
                                <optgroup label="Eastern Visayas (Region VIII)">
                                    <option value="Biliran">Biliran (Naval)</option>
                                    <option value="Eastern Samar">Eastern Samar (Borongan City)</option>
                                    <option value="Leyte">Leyte (Tacloban City)</option>
                                    <option value="Northern Samar">Northern Samar (Catarman)</option>
                                    <option value="Samar">Samar (Western Samar) (Catbalogan City)</option>
                                    <option value="Southern Leyte">Southern Leyte (Maasin City)</option>
                                </optgroup>
                                <optgroup label="Zamboanga Peninsula (Region IX)">
                                    <option value="Zamboanga del Norte">Zamboanga del Norte (Dipolog City)</option>
                                    <option value="Zamboanga del Sur">Zamboanga del Sur (Pagadian City)</option>
                                    <option value="Zamboanga Sibugay">Zamboanga Sibugay (Ipil)</option>
                                </optgroup>
                                <optgroup label="Northern Mindanao (Region X)">
                                    <option value="Bukidnon">Bukidnon (Malaybalay City)</option>
                                    <option value="Camiguin">Camiguin (Mambajao)</option>
                                    <option value="Lanao del Norte">Lanao del Norte (Tubod)</option>
                                    <option value="Misamis Occidental">Misamis Occidental (Oroquieta City)</option>
                                    <option value="Misamis Oriental">Misamis Oriental (Cagayan de Oro City)</option>
                                </optgroup>
                                <optgroup label="Davao Region (Region XI)">
                                    <option value="Davao de Oro">Davao de Oro (Nabunturan)</option>
                                    <option value="Davao del Norte">Davao del Norte (Tagum City)</option>
                                    <option value="Davao del Sur">Davao del Sur (Digos City)</option>
                                    <option value="Davao Occidental">Davao Occidental (Malita)</option>
                                    <option value="Davao Oriental">Davao Oriental (Mati City)</option>
                                </optgroup>
                                <optgroup label="Soccsksargen (Region XII)">
                                    <option value="Cotabato">Cotabato (North Cotabato) (Kidapawan City)</option>
                                    <option value="Sarangani">Sarangani (Alabel)</option>
                                    <option value="South Cotabato">South Cotabato (Koronadal City)</option>
                                    <option value="Sultan Kudarat">Sultan Kudarat (Isulan)</option>
                                </optgroup>
                                <optgroup label="Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)">
                                    <option value="Basilan">Basilan (Isabela City)</option>
                                    <option value="Lanao del Sur">Lanao del Sur (Marawi City)</option>
                                    <option value="Maguindanao del Norte">Maguindanao del Norte (Datu Odin Sinsuat)
                                    </option>
                                    <option value="Maguindanao del Sur">Maguindanao del Sur (Buluan)</option>
                                    <option value="Sulu">Sulu (Jolo)</option>
                                    <option value="Tawi-Tawi">Tawi-Tawi (Bongao)</option>
                                </optgroup>
                                <optgroup label="Caraga Region (Region XIII)">
                                    <option value="Agusan del Norte">Agusan del Norte (Cabadbaran City)</option>
                                    <option value="Agusan del Sur">Agusan del Sur (Prosperidad)</option>
                                    <option value="Dinagat Islands">Dinagat Islands (San Jose)</option>
                                    <option value="Surigao del Norte">Surigao del Norte (Surigao City)</option>
                                    <option value="Surigao del Sur">Surigao del Sur (Tandag City)</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" class="form-control"
                                value="<?php echo e(isset($editPatient['postal_code']) ? $editPatient['postal_code'] : ''); ?>" />
                        </div>

                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <select name="country" id="countrySelect" class="form-control" data-selected="<?php echo e(isset($editPatient['country']) ? $editPatient['country'] : ''); ?>">
                                <option value=""><?php echo e(isset($editPatient['country']) ? $editPatient['country'] : '-- Select Country --'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Allergies</label>
                            <input type="text" name="allergies" class="form-control"
                                value="<?php echo e(isset($editPatient['allergies']) ? $editPatient['allergies'] : ''); ?>" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="form-control"
                                value="<?php echo e(isset($editPatient['emergency_contact_name']) ? $editPatient['emergency_contact_name'] : ''); ?>" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Emergency Contact Phone</label>
                            <input type="tel" name="emergency_contact_phone" class="form-control"
                                value="<?php echo e(isset($editPatient['emergency_contact_phone']) ? $editPatient['emergency_contact_phone'] : ''); ?>" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Medical History</label>
                        <textarea name="medical_history" class="form-control"
                            rows="3"><?php echo e(isset($editPatient['medical_history']) ? $editPatient['medical_history'] : ''); ?></textarea>
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-dismiss="patientModal">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i>
                    <?php echo $editId ? 'Save Changes' : 'Save Patient'; ?>
                </button>
            </div>
            </form>
        </div>
    </div>


    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i>
                Patients List
            </h3>
            <form method="GET" action="<?php echo baseUrl(); ?>/"
                style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="hidden" name="page" value="patients">
                <input type="text" name="search" class="form-control" style="width: 300px;"
                    value="<?php echo e($search); ?>" placeholder="Search by name or phone...">
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
                            <th>Phone</th>
                            <th>Gender</th>
                            <th>Date Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td onclick="window.location.href='<?php echo baseUrl(); ?>/?page=patient-profile&id=<?php echo $patient['id']; ?>'"
                                    style="cursor: pointer;">
                                    <strong><?php echo e(isset($patient['first_name']) ? $patient['first_name'] . ' ' . (isset($patient['last_name']) ? $patient['last_name'] : '') : ''); ?></strong>
                                </td>
                                <td onclick="window.location.href='<?php echo baseUrl(); ?>/?page=patient-profile&id=<?php echo $patient['id']; ?>'"
                                    style="cursor: pointer;">
                                    <?php echo e(isset($patient['phone']) ? $patient['phone'] : 'N/A'); ?>
                                </td>
                                <td onclick="window.location.href='<?php echo baseUrl(); ?>/?page=patient-profile&id=<?php echo $patient['id']; ?>'"
                                    style="cursor: pointer;">
                                    <span class="badge-status">
                                        <?php echo e(isset($patient['gender']) ? ucfirst($patient['gender']) : 'N/A'); ?>
                                    </span>
                                </td>
                                <td onclick="window.location.href='<?php echo baseUrl(); ?>/?page=patient-profile&id=<?php echo $patient['id']; ?>'"
                                    style="cursor: pointer;">
                                    <?php echo formatDate(isset($patient['created_at']) ? $patient['created_at'] : ''); ?>
                                </td>
                                <td>
                                    <div class="flex gap-1" onclick="event.stopPropagation();">
                                        <a href="<?php echo baseUrl(); ?>/?page=patient-profile&id=<?php echo $patient['id']; ?>"
                                            class="btn btn-sm btn-primary btn-icon" title="View Profile">
                                            <i class="fas fa-user"></i>
                                        </a>
                                        <a href="<?php echo baseUrl(); ?>/?page=patients&edit=<?php echo $patient['id']; ?>"
                                            class="btn btn-sm btn-secondary btn-icon" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if (hasRole('admin')): ?>
                                            <form method="POST" action="<?php echo baseUrl(); ?>/" style="display: inline;"
                                                onsubmit="return confirm('Are you sure you want to delete this patient?');">
                                                <input type="hidden" name="action" value="delete_patient">
                                                <input type="hidden" name="id" value="<?php echo $patient['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger btn-icon" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
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
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('showAddPatientBtn');
        var modal = document.getElementById('patientModal');
        if (!btn || !modal) return;

        function openModal() {
            modal.removeAttribute('hidden');
            modal.classList.add('open');
            var main = document.querySelector('.main-content');
            if (main) main.setAttribute('aria-hidden', 'true');
            setTimeout(function () {
                var first = modal.querySelector('input[name="first_name"]');
                if (first) first.focus();
            }, 50);
        }

        function closeModal() {
            modal.classList.remove('open');
            modal.setAttribute('hidden', '');
            var main = document.querySelector('.main-content');
            if (main) main.removeAttribute('aria-hidden');
        }

        btn.addEventListener('click', function () {
            openModal();
        });

        // wire dismiss elements (backdrop, close button)
        var dismissors = modal.querySelectorAll('[data-modal-dismiss="patientModal"]');
        dismissors.forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                closeModal();
            });
        });

        // prevent backdrop click from closing (enforce explicit close)
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                e.stopPropagation();
            }
        });

        // close on Escape key when open
        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && modal.classList.contains('open')) {
                closeModal();
            }
        });

        // If modal already visible on load (e.g., editing), ensure it's open and focused
        if (!modal.hasAttribute('hidden')) {
            modal.classList.add('open');
            setTimeout(function () {
                var first = modal.querySelector('input[name="first_name"]');
                if (first) first.focus();
            }, 50);
        }
    });
</script>
<!-- Location selectors loader -->
<script src="<?php echo baseUrl(); ?>/js/location-loader.js"></script>
<script>
    window.addEventListener('DOMContentLoaded', function () {
        if (window.initLocationSelectors) {
            window.initLocationSelectors('<?php echo baseUrl(); ?>/api-locations.php');
        }
    });
</script>