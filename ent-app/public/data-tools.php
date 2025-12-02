<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

$action = $_REQUEST['action'] ?? '';
$db = Database::getInstance()->getConnection();

function redirectWithStatus($status, $message = '')
{
    $url = '/ENT-clinic-online/ent-app/public/?page=patients&status=' . urlencode($status);
    if ($message) {
        $url .= '&message=' . urlencode($message);
    }
    header("Location: $url");
    exit;
}

try {
    if ($action === 'export') {
        $patients = $db->query("SELECT * FROM patients ORDER BY id ASC")->fetchAll();
        $visits = [];
        try {
            $visits = $db->query("SELECT * FROM patient_visits ORDER BY visit_date ASC")->fetchAll();
        } catch (PDOException $e) {
            $visits = [];
        }

        $payload = [
            'exported_at' => date('c'),
            'patients' => $patients,
            'patient_visits' => $visits
        ];

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="ent-clinic-export-' . date('Ymd-His') . '.json"');
        echo json_encode($payload, JSON_PRETTY_PRINT);
        exit;
    }

    if ($action === 'seed') {
        // Insert a single test patient if none exist (non-destructive)
        $existing = $db->query("SELECT COUNT(*) as c FROM patients")->fetch();
        $count = $existing['c'] ?? 0;
        if ($count > 0) {
            redirectWithStatus('info', 'Patients already exist. Seed skipped.');
        }

        $now = date('Y-m-d H:i:s');
        $patientId = 'PAT-' . date('YmdHis') . '-' . rand(100,999);
        $stmt = $db->prepare("INSERT INTO patients (patient_id, first_name, last_name, gender, email, phone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$patientId, 'Test', 'Patient', 'other', 'test.patient@example.com', '0000000000', $now, $now]);

        redirectWithStatus('success', 'Seed patient added.');
    }

    if ($action === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            redirectWithStatus('error', 'Please upload a valid JSON file.');
        }

        $json = file_get_contents($_FILES['import_file']['tmp_name']);
        $data = json_decode($json, true);
        if (!$data || !isset($data['patients'])) {
            redirectWithStatus('error', 'Invalid JSON structure.');
        }

        $db->beginTransaction();

        // Import patients
        $patientStmt = $db->prepare("INSERT INTO patients
            (patient_id, first_name, last_name, date_of_birth, gender, email, phone, occupation, address, city, state, postal_code, country, medical_history, current_medications, allergies, insurance_provider, insurance_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                date_of_birth = VALUES(date_of_birth),
                gender = VALUES(gender),
                email = VALUES(email),
                phone = VALUES(phone),
                occupation = VALUES(occupation),
                address = VALUES(address),
                city = VALUES(city),
                state = VALUES(state),
                postal_code = VALUES(postal_code),
                country = VALUES(country),
                medical_history = VALUES(medical_history),
                current_medications = VALUES(current_medications),
                allergies = VALUES(allergies),
                insurance_provider = VALUES(insurance_provider),
                insurance_id = VALUES(insurance_id),
                updated_at = VALUES(updated_at)");

        foreach ($data['patients'] as $patient) {
            $patientStmt->execute([
                $patient['patient_id'] ?? ('PAT-' . uniqid()),
                $patient['first_name'] ?? '',
                $patient['last_name'] ?? '',
                $patient['date_of_birth'] ?? null,
                $patient['gender'] ?? 'male',
                $patient['email'] ?? null,
                $patient['phone'] ?? null,
                $patient['occupation'] ?? null,
                $patient['address'] ?? null,
                $patient['city'] ?? null,
                $patient['state'] ?? null,
                $patient['postal_code'] ?? null,
                $patient['country'] ?? null,
                $patient['medical_history'] ?? null,
                $patient['current_medications'] ?? null,
                $patient['allergies'] ?? null,
                $patient['insurance_provider'] ?? null,
                $patient['insurance_id'] ?? null,
                $patient['created_at'] ?? date('Y-m-d H:i:s'),
                $patient['updated_at'] ?? date('Y-m-d H:i:s'),
            ]);
        }

        // Import visits if provided
        if (!empty($data['patient_visits'])) {
            $visitStmt = $db->prepare("INSERT INTO patient_visits
                (patient_id, visit_date, visit_type, ent_type, chief_complaint, diagnosis, treatment_plan, prescription, notes, doctor_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($data['patient_visits'] as $visit) {
                $visitStmt->execute([
                    $visit['patient_id'] ?? null,
                    $visit['visit_date'] ?? date('Y-m-d H:i:s'),
                    $visit['visit_type'] ?? 'Consultation',
                    $visit['ent_type'] ?? 'ear',
                    $visit['chief_complaint'] ?? null,
                    $visit['diagnosis'] ?? null,
                    $visit['treatment_plan'] ?? null,
                    $visit['prescription'] ?? null,
                    $visit['notes'] ?? null,
                    $visit['doctor_id'] ?? null,
                    $visit['created_at'] ?? date('Y-m-d H:i:s'),
                    $visit['updated_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }
        }

        $db->commit();
        redirectWithStatus('success', 'Data imported successfully.');
    }

    redirectWithStatus('error', 'Unknown action.');
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    redirectWithStatus('error', 'Operation failed: ' . $e->getMessage());
}

