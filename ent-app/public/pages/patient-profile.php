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

// Check if editing profile or visit
$editProfile = isset($_GET['edit']) && $_GET['edit'] === 'profile';
$showAddVisit = isset($_GET['add']) && $_GET['add'] === 'visit';
$editVisit = isset($_GET['edit']) && $_GET['edit'] === 'visit';
$editVisitId = isset($_GET['visit_id']) ? $_GET['visit_id'] : null;
$editVisitData = null;

if ($editVisit && $editVisitId) {
    // Load the visit to edit
    $allVisits = apiCall('GET', '/api/visits?patient_id=' . $patientId);
    if (isset($allVisits['visits'])) {
        foreach ($allVisits['visits'] as $v) {
            if ($v['id'] == $editVisitId) {
                $editVisitData = $v;
                $showAddVisit = true; // Show the modal
                break;
            }
        }
    }
}
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
                <a href="<?php echo baseUrl(); ?>/?page=medical-certificate&patient_id=<?php echo $patientId; ?>" class="btn btn-secondary" style="display: none;">
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

            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <?php
                // Fields to display in Patient Information (label => key)
                $fields = [
                    'Patient ID' => 'patient_id',
                    'Name' => ['first_name','last_name'],
                    'Date of Birth' => 'date_of_birth',
                    'Gender' => 'gender',
                    'Email' => 'email',
                    'Phone' => 'phone',
                    'Height' => 'height',
                    'Weight' => 'weight',
                    'Occupation' => 'occupation',
                    'Blood Type' => 'blood_type',
                    'Marital Status' => 'marital_status',
                    'Allergies' => 'allergies',
                    'Postal Code' => 'postal_code',
                    'Country' => 'country',
                    'State/Province' => 'state',
                    'City' => 'city',
                    'Address' => 'address',
                    'Emergency Contact' => ['emergency_contact_name','emergency_contact_phone'],
                    'Medical History' => 'medical_history',
                    'Notes' => 'notes'
                ];

                foreach ($fields as $label => $key) {
                    $value = '';
                    if (is_array($key)) {
                        // Combine multiple keys
                        $parts = [];
                        foreach ($key as $k) {
                            if (!empty($patient[$k])) $parts[] = $patient[$k];
                        }
                        $value = implode(' ', $parts);
                    } else {
                        if (isset($patient[$key])) $value = $patient[$key];
                    }

                    if ($label === 'Date of Birth') {
                        $value = $value ? formatDate($value) : '';
                    }
                    if ($label === 'Gender') {
                        $value = $value ? ucfirst($value) : '';
                    }
                    if ($label === 'Address') {
                        $addressParts = array_filter([
                            $patient['address'] ?? '',
                            $patient['city'] ?? '',
                            $patient['state'] ?? '',
                            $patient['postal_code'] ?? '',
                            $patient['country'] ?? ''
                        ]);
                        $value = implode(', ', $addressParts);
                    }

                    // Skip empty values except for Medical History (show N/A)
                    if ($label === 'Medical History') {
                        echo '<div style="padding:0.5rem 0; border-bottom:1px solid var(--border-color);">';
                        echo '<span class="text-muted" style="display:block;margin-bottom:0.25rem;">' . e($label) . ':</span>';
                        echo '<div>' . ($value ? nl2br(e($value)) : '<em>N/A</em>') . '</div>';
                        echo '</div>'; 
                        continue;
                    }

                    if ($value === '' || $value === null) continue;

                    echo '<div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--border-color);">';
                    echo '<span class="text-muted">' . e($label) . ':</span>';
                    echo '<strong style="text-align:right;">' . e($value) . '</strong>';
                    echo '</div>';
                }
                ?>
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
                                   value="<?php echo e(isset($patient['date_of_birth']) ? $patient['date_of_birth'] : ''); ?>" required/>
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
                                <select name="city" id="citySelect" class="form-control" data-selected="<?php echo e(isset($patient['city']) ? $patient['city'] : ''); ?>">
                                    <option value=""><?php echo e(isset($patient['city']) ? $patient['city'] : '-- Select City --'); ?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">State/Province</label>
                                <select name="state" id="stateSelect" class="form-control">
                                    <option value=""><?php echo isset($patient['state']) ? $patient['state'] : 'Select Province'; ?></option>
                                        <option value="Bataan" <?php echo (isset($patient['state']) && $patient['state'] === 'Bataan') ? 'selected' : ''; ?>>Bataan</option>
                                        <option value="Bulacan" <?php echo (isset($patient['state']) && $patient['state'] === 'Bulacan') ? 'selected' : ''; ?>>Bulacan</option>
                                        <option value="Nueva Ecija" <?php echo (isset($patient['state']) && $patient['state'] === 'Nueva Ecija') ? 'selected' : ''; ?>>Nueva Ecija</option>
                                        <option value="Pampanga" <?php echo (isset($patient['state']) && $patient['state'] === 'Pampanga') ? 'selected' : ''; ?>>Pampanga</option>
                                        <option value="Tarlac" <?php echo (isset($patient['state']) && $patient['state'] === 'Tarlac') ? 'selected' : ''; ?>>Tarlac</option>
                                        <option value="Zambales" <?php echo (isset($patient['state']) && $patient['state'] === 'Zambales') ? 'selected' : ''; ?>>Zambales</option>
                                    </optgroup>
                                    <optgroup label="Calabarzon (Region IV-A)">
                                        <option value="Batangas" <?php echo (isset($patient['state']) && $patient['state'] === 'Batangas') ? 'selected' : ''; ?>>Batangas</option>
                                        <option value="Cavite" <?php echo (isset($patient['state']) && $patient['state'] === 'Cavite') ? 'selected' : ''; ?>>Cavite</option>
                                        <option value="Laguna" <?php echo (isset($patient['state']) && $patient['state'] === 'Laguna') ? 'selected' : ''; ?>>Laguna</option>
                                        <option value="Quezon" <?php echo (isset($patient['state']) && $patient['state'] === 'Quezon') ? 'selected' : ''; ?>>Quezon</option>
                                        <option value="Rizal" <?php echo (isset($patient['state']) && $patient['state'] === 'Rizal') ? 'selected' : ''; ?>>Rizal</option>
                                    </optgroup>
                                    <optgroup label="Mimaropa (Region IV-B)">
                                        <option value="Antique" <?php echo (isset($patient['state']) && $patient['state'] === 'Antique') ? 'selected' : ''; ?>>Antique</option>
                                        <option value="Capiz" <?php echo (isset($patient['state']) && $patient['state'] === 'Capiz') ? 'selected' : ''; ?>>Capiz</option>
                                        <option value="Marinduque" <?php echo (isset($patient['state']) && $patient['state'] === 'Marinduque') ? 'selected' : ''; ?>>Marinduque</option>
                                        <option value="Occidental Mindoro" <?php echo (isset($patient['state']) && $patient['state'] === 'Occidental Mindoro') ? 'selected' : ''; ?>>Occidental Mindoro</option>
                                        <option value="Oriental Mindoro" <?php echo (isset($patient['state']) && $patient['state'] === 'Oriental Mindoro') ? 'selected' : ''; ?>>Oriental Mindoro</option>
                                        <option value="Palawan" <?php echo (isset($patient['state']) && $patient['state'] === 'Palawan') ? 'selected' : ''; ?>>Palawan</option>
                                        <option value="Romblon" <?php echo (isset($patient['state']) && $patient['state'] === 'Romblon') ? 'selected' : ''; ?>>Romblon</option>
                                    </optgroup>
                                    <optgroup label="Bicol (Region V)">
                                        <option value="Albay" <?php echo (isset($patient['state']) && $patient['state'] === 'Albay') ? 'selected' : ''; ?>>Albay</option>
                                        <option value="Camarines Norte" <?php echo (isset($patient['state']) && $patient['state'] === 'Camarines Norte') ? 'selected' : ''; ?>>Camarines Norte</option>
                                        <option value="Camarines Sur" <?php echo (isset($patient['state']) && $patient['state'] === 'Camarines Sur') ? 'selected' : ''; ?>>Camarines Sur</option>
                                        <option value="Catanduanes" <?php echo (isset($patient['state']) && $patient['state'] === 'Catanduanes') ? 'selected' : ''; ?>>Catanduanes</option>
                                        <option value="Masbate" <?php echo (isset($patient['state']) && $patient['state'] === 'Masbate') ? 'selected' : ''; ?>>Masbate</option>
                                    </optgroup>
                                    <optgroup label="Western Visayas (Region VI)">
                                        <option value="Aklan" <?php echo (isset($patient['state']) && $patient['state'] === 'Aklan') ? 'selected' : ''; ?>>Aklan</option>
                                        <option value="Capiz" <?php echo (isset($patient['state']) && $patient['state'] === 'Capiz') ? 'selected' : ''; ?>>Capiz</option>
                                        <option value="Guimaras" <?php echo (isset($patient['state']) && $patient['state'] === 'Guimaras') ? 'selected' : ''; ?>>Guimaras</option>
                                        <option value="Iloilo" <?php echo (isset($patient['state']) && $patient['state'] === 'Iloilo') ? 'selected' : ''; ?>>Iloilo</option>
                                        <option value="Negros Occidental" <?php echo (isset($patient['state']) && $patient['state'] === 'Negros Occidental') ? 'selected' : ''; ?>>Negros Occidental</option>
                                    </optgroup>
                                    <optgroup label="Central Visayas (Region VII)">
                                        <option value="Bohol" <?php echo (isset($patient['state']) && $patient['state'] === 'Bohol') ? 'selected' : ''; ?>>Bohol</option>
                                        <option value="Cebu" <?php echo (isset($patient['state']) && $patient['state'] === 'Cebu') ? 'selected' : ''; ?>>Cebu</option>
                                        <option value="Negros Oriental" <?php echo (isset($patient['state']) && $patient['state'] === 'Negros Oriental') ? 'selected' : ''; ?>>Negros Oriental</option>
                                        <option value="Siquijor" <?php echo (isset($patient['state']) && $patient['state'] === 'Siquijor') ? 'selected' : ''; ?>>Siquijor</option>
                                    </optgroup>
                                    <optgroup label="Eastern Visayas (Region VIII)">
                                        <option value="Biliran" <?php echo (isset($patient['state']) && $patient['state'] === 'Biliran') ? 'selected' : ''; ?>>Biliran</option>
                                        <option value="Eastern Samar" <?php echo (isset($patient['state']) && $patient['state'] === 'Eastern Samar') ? 'selected' : ''; ?>>Eastern Samar</option>
                                        <option value="Leyte" <?php echo (isset($patient['state']) && $patient['state'] === 'Leyte') ? 'selected' : ''; ?>>Leyte</option>
                                        <option value="Northern Samar" <?php echo (isset($patient['state']) && $patient['state'] === 'Northern Samar') ? 'selected' : ''; ?>>Northern Samar</option>
                                        <option value="Samar" <?php echo (isset($patient['state']) && $patient['state'] === 'Samar') ? 'selected' : ''; ?>>Samar</option>
                                        <option value="Southern Leyte" <?php echo (isset($patient['state']) && $patient['state'] === 'Southern Leyte') ? 'selected' : ''; ?>>Southern Leyte</option>
                                    </optgroup>
                                    <optgroup label="Zamboanga Peninsula (Region IX)">
                                        <option value="Zamboanga del Norte" <?php echo (isset($patient['state']) && $patient['state'] === 'Zamboanga del Norte') ? 'selected' : ''; ?>>Zamboanga del Norte</option>
                                        <option value="Zamboanga del Sur" <?php echo (isset($patient['state']) && $patient['state'] === 'Zamboanga del Sur') ? 'selected' : ''; ?>>Zamboanga del Sur</option>
                                        <option value="Zamboanga Sibugay" <?php echo (isset($patient['state']) && $patient['state'] === 'Zamboanga Sibugay') ? 'selected' : ''; ?>>Zamboanga Sibugay</option>
                                    </optgroup>
                                    <optgroup label="Northern Mindanao (Region X)">
                                        <option value="Bukidnon" <?php echo (isset($patient['state']) && $patient['state'] === 'Bukidnon') ? 'selected' : ''; ?>>Bukidnon</option>
                                        <option value="Camiguin" <?php echo (isset($patient['state']) && $patient['state'] === 'Camiguin') ? 'selected' : ''; ?>>Camiguin</option>
                                        <option value="Lanao del Norte" <?php echo (isset($patient['state']) && $patient['state'] === 'Lanao del Norte') ? 'selected' : ''; ?>>Lanao del Norte</option>
                                        <option value="Misamis Oriental" <?php echo (isset($patient['state']) && $patient['state'] === 'Misamis Oriental') ? 'selected' : ''; ?>>Misamis Oriental</option>
                                    </optgroup>
                                    <optgroup label="Davao (Region XI)">
                                        <option value="Davao del Norte" <?php echo (isset($patient['state']) && $patient['state'] === 'Davao del Norte') ? 'selected' : ''; ?>>Davao del Norte</option>
                                        <option value="Davao del Sur" <?php echo (isset($patient['state']) && $patient['state'] === 'Davao del Sur') ? 'selected' : ''; ?>>Davao del Sur</option>
                                        <option value="Davao Oriental" <?php echo (isset($patient['state']) && $patient['state'] === 'Davao Oriental') ? 'selected' : ''; ?>>Davao Oriental</option>
                                    </optgroup>
                                    <optgroup label="Soccsksargen (Region XII)">
                                        <option value="Cotabato" <?php echo (isset($patient['state']) && $patient['state'] === 'Cotabato') ? 'selected' : ''; ?>>Cotabato</option>
                                        <option value="Sarangani" <?php echo (isset($patient['state']) && $patient['state'] === 'Sarangani') ? 'selected' : ''; ?>>Sarangani</option>
                                        <option value="South Cotabato" <?php echo (isset($patient['state']) && $patient['state'] === 'South Cotabato') ? 'selected' : ''; ?>>South Cotabato</option>
                                        <option value="Sultan Kudarat" <?php echo (isset($patient['state']) && $patient['state'] === 'Sultan Kudarat') ? 'selected' : ''; ?>>Sultan Kudarat</option>
                                    </optgroup>
                                    <optgroup label="BARMM (Bangsamoro Autonomous Region in Muslim Mindanao)">
                                        <option value="Basilan" <?php echo (isset($patient['state']) && $patient['state'] === 'Basilan') ? 'selected' : ''; ?>>Basilan</option>
                                        <option value="Lanao del Sur" <?php echo (isset($patient['state']) && $patient['state'] === 'Lanao del Sur') ? 'selected' : ''; ?>>Lanao del Sur</option>
                                        <option value="Maguindanao" <?php echo (isset($patient['state']) && $patient['state'] === 'Maguindanao') ? 'selected' : ''; ?>>Maguindanao</option>
                                        <option value="Sulu" <?php echo (isset($patient['state']) && $patient['state'] === 'Sulu') ? 'selected' : ''; ?>>Sulu</option>
                                        <option value="Tawi-Tawi" <?php echo (isset($patient['state']) && $patient['state'] === 'Tawi-Tawi') ? 'selected' : ''; ?>>Tawi-Tawi</option>
                                    </optgroup>
                                    <optgroup label="Caraga (Region XIII)">
                                        <option value="Agusan del Norte" <?php echo (isset($patient['state']) && $patient['state'] === 'Agusan del Norte') ? 'selected' : ''; ?>>Agusan del Norte</option>
                                        <option value="Agusan del Sur" <?php echo (isset($patient['state']) && $patient['state'] === 'Agusan del Sur') ? 'selected' : ''; ?>>Agusan del Sur</option>
                                        <option value="Dinagat Islands" <?php echo (isset($patient['state']) && $patient['state'] === 'Dinagat Islands') ? 'selected' : ''; ?>>Dinagat Islands</option>
                                        <option value="Surigao del Norte" <?php echo (isset($patient['state']) && $patient['state'] === 'Surigao del Norte') ? 'selected' : ''; ?>>Surigao del Norte</option>
                                        <option value="Surigao del Sur" <?php echo (isset($patient['state']) && $patient['state'] === 'Surigao del Sur') ? 'selected' : ''; ?>>Surigao del Sur</option>
                                    </optgroup>
                                </select>
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
                                <select name="country" id="countrySelect" class="form-control" data-selected="<?php echo e(isset($patient['country']) ? $patient['country'] : ''); ?>">
                                    <option value=""><?php echo e(isset($patient['country']) ? $patient['country'] : '-- Select Country --'); ?></option>
                                </select>
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
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                <h3 class="card-title" style="margin:0;">
                    <i class="fas fa-calendar-check"></i>
                    Visit Timeline
                </h3>
                <button type="button" id="addVisitBtn" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i>
                    Add Visit
                </button>
            </div>

            <!-- Add Visit Modal -->
            <div class="modal" id="visitModal" hidden aria-hidden="true" role="dialog" aria-modal="true">
                <div class="modal-backdrop" data-modal-dismiss="visitModal"></div>
                <div class="modal-dialog form-modal">
                    <div class="modal-header">
                        <h3 class="modal-title">
                            <i class="fas fa-<?php echo $editVisitData ? 'edit' : 'calendar-plus'; ?>"></i>
                            <?php echo $editVisitData ? 'Edit Visit' : 'Add New Visit'; ?>
                        </h3>
                        <button type="button" class="modal-close" data-modal-dismiss="visitModal" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                    <form method="POST" action="<?php echo baseUrl(); ?>/">
                        <input type="hidden" name="action" value="<?php echo $editVisitData ? 'update_visit' : 'add_visit'; ?>">
                        <input type="hidden" name="patient_id" value="<?php echo $patientId; ?>">
                        <?php if ($editVisitData): ?>
                        <input type="hidden" name="id" value="<?php echo $editVisitData['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label class="form-label">Visit Date & Time *</label>
                            <?php
                                // Default the datetime-local input to current time in Manila
                                $manilaNow = new DateTime('now', new DateTimeZone('Asia/Manila'));
                                $manilaValue = $manilaNow->format('Y-m-d\\TH:i');
                            ?>
                            <input type="datetime-local" name="visit_date" class="form-control" 
                                   value="<?php echo e($editVisitData ? substr($editVisitData['visit_date'], 0, 16) : $manilaValue); ?>" required />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Visit Type *</label>
                            <select name="visit_type" class="form-control" required>
                                <option value="">Select Visit Type</option>
                                <option value="Consultation" <?php echo ($editVisitData && $editVisitData['visit_type'] === 'Consultation') ? 'selected' : ''; ?>>Consultation</option>
                                <option value="Follow-up" <?php echo ($editVisitData && $editVisitData['visit_type'] === 'Follow-up') ? 'selected' : ''; ?>>Follow-up</option>
                                <option value="Emergency" <?php echo ($editVisitData && $editVisitData['visit_type'] === 'Emergency') ? 'selected' : ''; ?>>Emergency</option>
                                <option value="Routine Check" <?php echo ($editVisitData && $editVisitData['visit_type'] === 'Routine Check') ? 'selected' : ''; ?>>Routine Check</option>
                                <option value="Procedure" <?php echo ($editVisitData && $editVisitData['visit_type'] === 'Procedure') ? 'selected' : ''; ?>>Procedure</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ENT Classification *</label>
                            <select name="ent_type" class="form-control" required>
                                <option value="ear" <?php echo ($editVisitData && $editVisitData['ent_type'] === 'ear') ? 'selected' : ''; ?>>Ear</option>
                                <option value="nose" <?php echo ($editVisitData && $editVisitData['ent_type'] === 'nose') ? 'selected' : ''; ?>>Nose</option>
                                <option value="throat" <?php echo ($editVisitData && $editVisitData['ent_type'] === 'throat') ? 'selected' : ''; ?>>Throat</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Chief Complaint</label>
                            <textarea name="chief_complaint" class="form-control" rows="2"><?php echo $editVisitData ? e($editVisitData['chief_complaint']) : ''; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Diagnosis</label>
                            <textarea name="diagnosis" class="form-control" rows="2"><?php echo $editVisitData ? e($editVisitData['diagnosis']) : ''; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Treatment Plan</label>
                            <textarea name="treatment_plan" class="form-control" rows="2"><?php echo $editVisitData ? e($editVisitData['treatment_plan']) : ''; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Prescription</label>
                            <textarea name="prescription" class="form-control" rows="2"><?php echo $editVisitData ? e($editVisitData['prescription']) : ''; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Plan</label>
                            <textarea name="notes" class="form-control" rows="3"><?php echo $editVisitData ? e($editVisitData['notes']) : ''; ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save"></i>
                            <?php echo $editVisitData ? 'Update Visit' : 'Save Visit'; ?>
                        </button>
                        <button type="button" class="btn btn-secondary btn-lg" data-modal-dismiss="visitModal">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                    </div>
                    </form>
                </div>
            </div>

            <!-- Prescription Panel (Sidebar) - Shows beside visit modal -->
            <div id="prescriptionPanel" class="prescription-panel" style="display: none;">
                <div class="prescription-panel-content">
                    <div class="prescription-panel-header">
                        <h3 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-prescription-bottle"></i>
                            Prescribe Meds
                        </h3>
                        <button type="button" id="closePrescriptionPanel" class="modal-close" aria-label="Close prescription panel">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="prescription-panel-body">
                        <!-- Medicines Selection -->
                        <div class="form-group">
                            <label class="form-label">Select Medicines</label>
                            <div id="medicinesContainer" style="border: 1px solid var(--border-color); padding: 1rem; border-radius: 0.25rem; overflow-y: auto; background-color: var(--bg-secondary);">
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="1" data-medicine="Amoxicillin 500mg" id="med_1" />
                                    <label for="med_1" style="margin: 0; cursor: pointer; flex: 1;">Amoxicillin <strong>500mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="2" data-medicine="Aspirin 100mg" id="med_2" />
                                    <label for="med_2" style="margin: 0; cursor: pointer; flex: 1;">Aspirin <strong>100mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="3" data-medicine="Atorvastatin 10mg" id="med_3" />
                                    <label for="med_3" style="margin: 0; cursor: pointer; flex: 1;">Atorvastatin <strong>10mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="4" data-medicine="Cetirizine 10mg" id="med_4" />
                                    <label for="med_4" style="margin: 0; cursor: pointer; flex: 1;">Cetirizine <strong>10mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="5" data-medicine="Metformin 500mg" id="med_5" />
                                    <label for="med_5" style="margin: 0; cursor: pointer; flex: 1;">Metformin <strong>500mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="6" data-medicine="Ibuprofen 400mg" id="med_6" />
                                    <label for="med_6" style="margin: 0; cursor: pointer; flex: 1;">Ibuprofen <strong>400mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="7" data-medicine="Paracetamol 500mg" id="med_7" />
                                    <label for="med_7" style="margin: 0; cursor: pointer; flex: 1;">Paracetamol <strong>500mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="8" data-medicine="Ciprofloxacin 500mg" id="med_8" />
                                    <label for="med_8" style="margin: 0; cursor: pointer; flex: 1;">Ciprofloxacin <strong>500mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="9" data-medicine="Omeprazole 20mg" id="med_9" />
                                    <label for="med_9" style="margin: 0; cursor: pointer; flex: 1;">Omeprazole <strong>20mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="10" data-medicine="Loratadine 10mg" id="med_10" />
                                    <label for="med_10" style="margin: 0; cursor: pointer; flex: 1;">Loratadine <strong>10mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="11" data-medicine="Lisinopril 10mg" id="med_11" />
                                    <label for="med_11" style="margin: 0; cursor: pointer; flex: 1;">Lisinopril <strong>10mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="12" data-medicine="Amlodipine 5mg" id="med_12" />
                                    <label for="med_12" style="margin: 0; cursor: pointer; flex: 1;">Amlodipine <strong>5mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="13" data-medicine="Azithromycin 500mg" id="med_13" />
                                    <label for="med_13" style="margin: 0; cursor: pointer; flex: 1;">Azithromycin <strong>500mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="14" data-medicine="Fluoxetine 20mg" id="med_14" />
                                    <label for="med_14" style="margin: 0; cursor: pointer; flex: 1;">Fluoxetine <strong>20mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="15" data-medicine="Sertraline 50mg" id="med_15" />
                                    <label for="med_15" style="margin: 0; cursor: pointer; flex: 1;">Sertraline <strong>50mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="16" data-medicine="Gabapentin 300mg" id="med_16" />
                                    <label for="med_16" style="margin: 0; cursor: pointer; flex: 1;">Gabapentin <strong>300mg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="17" data-medicine="Albuterol Inhaler 90mcg" id="med_17" />
                                    <label for="med_17" style="margin: 0; cursor: pointer; flex: 1;">Albuterol Inhaler <strong>90mcg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="18" data-medicine="Fluticasone Nasal Spray 50mcg" id="med_18" />
                                    <label for="med_18" style="margin: 0; cursor: pointer; flex: 1;">Fluticasone Nasal Spray <strong>50mcg</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="19" data-medicine="Vitamin D3 1000IU" id="med_19" />
                                    <label for="med_19" style="margin: 0; cursor: pointer; flex: 1;">Vitamin D3 <strong>1000IU</strong></label>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="checkbox" name="medicine" value="20" data-medicine="Oxymetazoline Nasal Spray" id="med_20" />
                                    <label for="med_20" style="margin: 0; cursor: pointer; flex: 1;">Oxymetazoline Nasal Spray</label>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Prescription Notes
                        <div class="form-group">
                            <label class="form-label">Additional Notes</label>
                            <textarea id="prescriptionNotes" class="form-control" rows="2" placeholder="Special instructions, dosage modifications, etc."></textarea>
                        </div>

                         Prescription Fields -->
                        <!-- <div class="grid grid-2">
                            <div class="form-group">
                                <label class="form-label">Refill</label>
                                <input type="text" id="refillField" class="form-control" placeholder="e.g., 012345" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <input type="checkbox" id="labelCheckbox" /> Label
                                </label>
                            </div>
                        </div> -->

                        <!-- Date and Signature Fields -->
                        <!-- <div class="grid grid-2">
                            <div class="form-group">
                                <label class="form-label">Prescription Date</label>
                                <input type="date" id="prescriptionDateField" class="form-control" value="<?php echo date('Y-m-d'); ?>" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">Doctor's Signature</label>
                                <input type="text" id="signatureField" class="form-control" placeholder="e.g., Dr. Smith / DS" />
                            </div>
                        </div> -->

                        <!-- Hidden field for collected medicines -->
                        <input type="hidden" id="medicinesSelected" value="">
                    </div>
                    <div class="prescription-panel-footer">
                        <button type="button" class="btn btn-info btn-lg" id="printPrescription" style="width: 100%;">
                            <i class="fas fa-print"></i>
                            Print Prescription
                        </button>
                    </div>
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

/* Prescription Panel Styling */
.prescription-panel {
    position: fixed;
    top: 0;
    right: 0;
    width: 400px;
    height: 100vh;
    background: var(--bg-primary);
    border-left: 1px solid var(--border-color);
    box-shadow: -2px 0 8px rgba(0, 0, 0, 0.1);
    z-index: 1040;
    display: flex;
    flex-direction: column;
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.prescription-panel-content {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
}

.prescription-panel-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-secondary);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.prescription-panel-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.prescription-panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
}

.prescription-panel-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    background: var(--bg-secondary);
}

/* Adjust modal overlay when prescription panel is open */
.modal.open ~ .prescription-panel {
    display: flex;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .prescription-panel {
        width: 100%;
        right: 0;
        left: auto;
        border-left: none;
        border-top: 1px solid var(--border-color);
    }
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

    if (addBtn) {
        addBtn.addEventListener('click', function() {
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

    // ==================== Prescription Panel Handlers (Side Panel) ====================
    const prescriptionPanel = document.getElementById('prescriptionPanel');
    const medicinesContainer = document.getElementById('medicinesContainer');
    const medicinesSelectedInput = document.getElementById('medicinesSelected');
    const prescriptionNotes = document.getElementById('prescriptionNotes');
    const refillField = document.getElementById('refillField');
    const labelCheckbox = document.getElementById('labelCheckbox');
    const prescriptionDateField = document.getElementById('prescriptionDateField');
    const signatureField = document.getElementById('signatureField');
    const exportPrescriptionPDF = document.getElementById('exportPrescriptionPDF');
    const exportPrescriptionWord = document.getElementById('exportPrescriptionWord');
    const closePrescriptionPanel = document.getElementById('closePrescriptionPanel');
    
    // toBePrint object to track medicines for printing
    let toBePrint = {
        medicines: [],
        patientId: <?php echo $patientId; ?>,
        patientName: '<?php echo e($patient['first_name'] . ' ' . $patient['last_name']); ?>',
        patientAddress: '<?php echo e(isset($patient['address']) ? addslashes($patient['address']) : ''); ?>'
    };

    // Initialize medicines checkboxes
    function loadMedicines() {
        if (!medicinesContainer) return;
        
        // Attach change listener to all medicine checkboxes
        medicinesContainer.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedMedicines);
        });
    }

    function updateSelectedMedicines() {
        const selected = Array.from(medicinesContainer.querySelectorAll('input[type="checkbox"]:checked'))
            .map(cb => cb.getAttribute('data-medicine'))
            .join('; ');
        medicinesSelectedInput.value = selected;
        
        // Update toBePrint dataset
        toBePrint.medicines = Array.from(medicinesContainer.querySelectorAll('input[type="checkbox"]:checked'))
            .map(cb => ({ name: cb.getAttribute('data-medicine'), id: cb.value }));
    }

    function openPrescriptionPanel() {
        if (!prescriptionPanel) return;
        prescriptionPanel.style.display = 'flex';
        loadMedicines(); // Load medicines when panel opens
    }

    function closePrescriptionPanelFn() {
        if (!prescriptionPanel) return;
        prescriptionPanel.style.display = 'none';
    }

    // Update openVisitModal to also show prescription panel
    const originalOpenVisitModal = openVisitModal;
    openVisitModal = function() {
        originalOpenVisitModal();
        openPrescriptionPanel(); // Show prescription panel when visit modal opens
    };

    // Update closeVisitModal to also hide prescription panel
    const originalCloseVisitModal = closeVisitModal;
    closeVisitModal = function() {
        originalCloseVisitModal();
        closePrescriptionPanelFn(); // Hide prescription panel when visit modal closes
    };

    // Close prescription panel button
    if (closePrescriptionPanel) {
        closePrescriptionPanel.addEventListener('click', closePrescriptionPanelFn);
    }

    // Export handlers for prescription panel
    if (exportPrescriptionPDF) {
        exportPrescriptionPDF.addEventListener('click', function() {
            updateSelectedMedicines();
            
            if (toBePrint.medicines.length === 0) {
                alert('Please select at least one medicine.');
                return;
            }

            const formData = new FormData();
            formData.set('patient_id', toBePrint.patientId);
            formData.set('medicines_selected', medicinesSelectedInput.value);
            formData.set('prescription_notes', (typeof prescriptionNotes !== 'undefined' && prescriptionNotes ? prescriptionNotes.value : ''));
            formData.set('refill', (typeof refillField !== 'undefined' && refillField ? refillField.value : ''));
            formData.set('label_checkbox', (typeof labelCheckbox !== 'undefined' && labelCheckbox ? (labelCheckbox.checked ? '1' : '0') : '0'));
            formData.set('prescription_date', (typeof prescriptionDateField !== 'undefined' && prescriptionDateField ? prescriptionDateField.value : ''));
            formData.set('signature', (typeof signatureField !== 'undefined' && signatureField ? signatureField.value : ''));
            formData.set('export_format', 'pdf');
            formData.set('action', 'export_prescription');
            
            fetch('<?php echo baseUrl(); ?>/api.php?route=/api/prescription/export', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    return response.blob();
                }
                throw new Error('Failed to export PDF');
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'prescription_<?php echo $patientId; ?>_' + new Date().getTime() + '.pdf';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
                closePrescriptionPanelFn();
            })
            .catch(error => {
                console.error('Error exporting PDF:', error);
                alert('Error exporting PDF. Please try again.');
            });
        });
    }

    if (exportPrescriptionWord) {
        exportPrescriptionWord.addEventListener('click', function() {
            updateSelectedMedicines();
            
            if (toBePrint.medicines.length === 0) {
                alert('Please select at least one medicine.');
                return;
            }

            const formData = new FormData();
            formData.set('patient_id', toBePrint.patientId);
            formData.set('medicines_selected', medicinesSelectedInput.value);
            formData.set('prescription_notes', (typeof prescriptionNotes !== 'undefined' && prescriptionNotes ? prescriptionNotes.value : ''));
            formData.set('refill', (typeof refillField !== 'undefined' && refillField ? refillField.value : ''));
            formData.set('label_checkbox', (typeof labelCheckbox !== 'undefined' && labelCheckbox ? (labelCheckbox.checked ? '1' : '0') : '0'));
            formData.set('prescription_date', (typeof prescriptionDateField !== 'undefined' && prescriptionDateField ? prescriptionDateField.value : ''));
            formData.set('signature', (typeof signatureField !== 'undefined' && signatureField ? signatureField.value : ''));
            formData.set('export_format', 'word');
            formData.set('action', 'export_prescription');
            
            fetch('<?php echo baseUrl(); ?>/api.php?route=/api/prescription/export', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    return response.blob();
                }
                throw new Error('Failed to export Word');
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'prescription_<?php echo $patientId; ?>_' + new Date().getTime() + '.docx';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
                closePrescriptionPanelFn();
            })
            .catch(error => {
                console.error('Error exporting Word:', error);
                alert('Error exporting Word document. Please try again.');
            });
        });
    }

    // Print handler - open a printable window containing selected medicines and clinic header
    const printPrescriptionBtn = document.getElementById('printPrescription');
    function printPrescriptionFn() {
        updateSelectedMedicines();

        const meds = (toBePrint.medicines && toBePrint.medicines.length)
            ? toBePrint.medicines.map(m => `<li>${m.name}</li>`).join('')
            : '<li><em>No medicines selected</em></li>';

        const clinicHeader = `
            <div style="text-align:center; margin-bottom:12px;">
                <h2 style="margin:0;">Clinic</h2>
                <div style="font-size:0.9rem;">Address / Contact</div>
                <hr style="margin-top:12px;" />
            </div>`;

        const printable = `
            <html>
            <head>
                <title>Prescription - ${toBePrint.patientName}</title>
                <style>body{font-family:Arial,Helvetica,sans-serif;padding:20px;color:#000}ul{padding-left:0}li{list-style:none;margin-bottom:8px}</style>
            </head>
            <body>
                ${clinicHeader}
                <h3>Prescription for: ${toBePrint.patientName}</h3>
                <p>Patient ID: ${toBePrint.patientId}</p>
                <ul>
                    ${meds}
                </ul>
                <div style="margin-top:24px;">Printed: ${new Date().toLocaleString()}</div>
            </body>
            </html>`;

        const w = window.open('', '_blank');
        if (!w) {
            alert('Pop-up blocked. Please allow pop-ups to print.');
            return;
        }
        w.document.open();
        w.document.write(printable);
        w.document.close();
        w.focus();
        w.print();
    }

    if (printPrescriptionBtn) {
        printPrescriptionBtn.addEventListener('click', printPrescriptionFn);
    }

});
</script>
<!-- Location data loader (using lightweight location-loader) -->
<script src="<?php echo baseUrl(); ?>/js/location-loader.js"></script>
<script>
    window.addEventListener('DOMContentLoaded', function () {
        if (window.initLocationSelectors) {
            window.initLocationSelectors('<?php echo baseUrl(); ?>/api-locations.php');
        }
    });
</script>

