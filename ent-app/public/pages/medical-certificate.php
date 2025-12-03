<?php
/**
 * Medical Certificate Page - Accessible by all authenticated roles
 * Provides a clean, printable template for medical certificates.
 * Can auto-populate patient data if patient_id is passed via URL.
 */

$patientData = null;
$patientName = '';
$patientAge = '';
$visitDate = '';
$visitLocation = '';

// Check if patient_id is passed in URL
if (isset($_GET['patient_id'])) {
    $patientId = (int)$_GET['patient_id'];
    
    // Fetch patient data via API
    $response = apiCall('GET', '/api/patients/' . $patientId);
    
    if ($response && isset($response['id'])) {
        $patientData = $response;
        $patientName = $patientData['first_name'] . ' ' . $patientData['last_name'];
        
        // Calculate age from DOB
        if (isset($patientData['date_of_birth']) && $patientData['date_of_birth']) {
            $dob = new DateTime($patientData['date_of_birth']);
            $today = new DateTime();
            $patientAge = $today->diff($dob)->y;
        }
        
        // Fetch latest visit for date/location info
        $visitsResponse = apiCall('GET', '/api/visits?patient_id=' . $patientId . '&limit=1');
        if ($visitsResponse && is_array($visitsResponse) && count($visitsResponse) > 0) {
            $latestVisit = $visitsResponse[0];
            $visitDate = isset($latestVisit['visit_date']) ? formatDate($latestVisit['visit_date']) : '';
            $visitLocation = isset($latestVisit['location']) ? $latestVisit['location'] : 'ENT Clinic';
        } else {
            $visitLocation = 'ENT Clinic';
        }
    }
}
?>

<div class="medical-certificate-page">
    <div class="card no-print mb-3" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;">
        <div>
            <h2 style="margin:0;font-size:1.5rem;font-weight:700;">Print Medical Certificate</h2>
            <p class="text-muted" style="margin-top:0.35rem;">Fill in the details below, then use the print button for a clean certificate.</p>
        </div>
        <div class="flex gap-1">
            <button type="button" class="btn btn-secondary" onclick="window.history.back();">
                <i class="fas fa-arrow-left"></i>
                Back
            </button>
            <button type="button" class="btn btn-primary" onclick="window.print();">
                <i class="fas fa-print"></i>
                Print Certificate
            </button>
        </div>
    </div>

    <div class="certificate-wrapper">
        <div class="certificate-sheet">
            <header class="certificate-header">
                <div class="clinic-identity">
                    <div class="clinic-logo">
                        <i class="fas fa-hospital-user"></i>
                    </div>
                    <div class="clinic-text">
                        <h1>ENT Clinic</h1>
                        <p>Ear · Nose · Throat Specialty Clinic</p>
                        <p class="clinic-meta">Address / Contact details can be printed here</p>
                    </div>
                </div>
                <div class="certificate-meta">
                    <h2>MEDICAL CERTIFICATE</h2>
                    <p>Date: <span contenteditable="true" class="editable-field"><?php echo date('F j, Y'); ?></span></p>
                    <p>Place: <span contenteditable="true" class="editable-field">__________________________</span></p>
                </div>
            </header>

            <main class="certificate-body">
                <p class="certificate-paragraph">
                    To whom it may concern:
                </p>

                <p class="certificate-paragraph">
                    This is to certify that <span contenteditable="true" class="editable-field"><?php echo !empty($patientName) ? htmlspecialchars($patientName) : '__________________________________________'; ?></span>,
                    <span contenteditable="true" class="editable-field"><?php echo !empty($patientAge) ? $patientAge : '____'; ?></span> years old, was examined at
                    <span contenteditable="true" class="editable-field"><?php echo !empty($visitLocation) ? htmlspecialchars($visitLocation) : '__________________________________________'; ?></span>
                    on <span contenteditable="true" class="editable-field"><?php echo !empty($visitDate) ? htmlspecialchars($visitDate) : '__________________________________________'; ?></span>.
                </p>

                <p class="certificate-paragraph">
                    Clinical Impression / Diagnosis:
                </p>
                <p class="certificate-paragraph bordered editable-multiline" contenteditable="true">
                    _______________________________________________________________________________________<br>
                    _______________________________________________________________________________________<br>
                    _______________________________________________________________________________________
                </p>

                <p class="certificate-paragraph">
                    Recommendations / Activity & Work Clearance:
                </p>
                <p class="certificate-paragraph bordered editable-multiline" contenteditable="true">
                    _______________________________________________________________________________________<br>
                    _______________________________________________________________________________________<br>
                    _______________________________________________________________________________________
                </p>

                <p class="certificate-paragraph">
                    This certificate is being issued upon the request of the above-named patient for
                    <span contenteditable="true" class="editable-field">__________________________________________</span>.
                </p>

                <p class="certificate-paragraph" style="margin-top:3rem;">
                    Very truly yours,
                </p>

                <div class="certificate-signature-block">
                    <div class="signature-line"></div>
                    <p class="certificate-signatory" contenteditable="true">Dr. ________________________________</p>
                    <p class="certificate-signatory-meta" contenteditable="true">ENT Specialist</p>
                    <p class="certificate-signatory-meta" contenteditable="true">License No.: ________________</p>
                </div>
            </main>
        </div>
    </div>
</div>


