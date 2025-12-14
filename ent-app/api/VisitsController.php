<?php
/**
 * Patient Visits API Controller
 */

require_once __DIR__ . '/Controller.php';

class VisitsController extends Controller
{
    // Get all visits for a patient
    public function index()
    {
        try {
            $patientId = $_GET['patient_id'] ?? null;
            
            if (!$patientId) {
                $this->error('Patient ID is required', 400);
            }

            $sql = "SELECT v.*, u.full_name as doctor_name 
                    FROM patient_visits v 
                    LEFT JOIN users u ON v.doctor_id = u.id 
                    WHERE v.patient_id = ? 
                    ORDER BY v.visit_date DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$patientId]);
            $visits = $stmt->fetchAll();

            $this->success([
                'visits' => $visits,
                'total' => count($visits)
            ]);
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    // Get single visit
    public function show($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT v.*, u.full_name as doctor_name 
                                        FROM patient_visits v 
                                        LEFT JOIN users u ON v.doctor_id = u.id 
                                        WHERE v.id = ?");
            $stmt->execute([$id]);
            $visit = $stmt->fetch();

            if (!$visit) {
                $this->error('Visit not found', 404);
            }

            $this->success($visit);
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    // Create new visit
    public function store()
    {
        try {
            // Allow admins and doctors full access; allow staff (secretary) to create visits
            // but restrict them to only adding patient_id, visit_date, chief_complaint and vitals.
            $this->requireRole(['admin', 'doctor', 'staff']);
            $input = $this->getInput();

            // Determine allowed fields and validation rules based on caller role
            $apiUser = $this->getApiUser();
            $isStaff = isset($apiUser['role']) && $apiUser['role'] === 'staff';

            if ($isStaff) {
                $rules = [
                    'patient_id' => 'required|numeric',
                    'visit_date' => 'required'
                ];
                // Allow staff to add visit details including ENT, diagnosis, treatment, and prescription
                $allowedFields = ['patient_id', 'appointment_id', 'visit_date', 'visit_type', 'ent_type', 'chief_complaint', 'diagnosis', 'treatment_plan', 'prescription', 'notes', 'height', 'weight', 'blood_pressure', 'temperature', 'vitals_notes'];
            } else {
                $rules = [
                    'patient_id' => 'required|numeric',
                    'visit_date' => 'required',
                    'visit_type' => 'required',
                ];
                $allowedFields = ['patient_id','appointment_id','visit_date','visit_type','ent_type','chief_complaint','diagnosis','treatment_plan','prescription','notes','height','weight','blood_pressure','temperature','vitals_notes','doctor_id'];
            }

            // Log incoming visit payload for debugging
            @file_put_contents(__DIR__ . '/../logs/visit_store.log', "\n--- Visit Store ---\nTime: " . date('c') . "\nInput: " . var_export($input, true) . "\n", FILE_APPEND);

            $errors = $this->validate($input, $rules);
            if (!empty($errors)) {
                $this->error($errors, 422);
            }

            // Check if patient exists
            $stmt = $this->db->prepare("SELECT id FROM patients WHERE id = ?");
            $stmt->execute([$input['patient_id']]);
            if (!$stmt->fetch()) {
                $this->error('Patient not found', 404);
            }

            // Ensure patient_visits table exists (create from migration if missing)
            $tblCheck = $this->db->prepare("SHOW TABLES LIKE 'patient_visits'");
            $tblCheck->execute();
            $tblExists = $tblCheck->fetch();
            if (!$tblExists) {
                $migrationPaths = [
                    __DIR__ . '/../database/patient_visits_table.sql',
                    __DIR__ . '/../database/schema.sql'
                ];
                $applied = false;
                foreach ($migrationPaths as $migrationPath) {
                    if (file_exists($migrationPath)) {
                        $sql = file_get_contents($migrationPath);
                        if ($sql && trim($sql) !== '') {
                            // Execute all statements in the SQL file
                            try {
                                $this->db->exec($sql);
                                $applied = true;
                                @file_put_contents(__DIR__ . '/../logs/visit_store.log', "Applied migration file: $migrationPath\n", FILE_APPEND);
                                break; // if schema.sql applied it will create all tables
                            } catch (PDOException $ex) {
                                @file_put_contents(__DIR__ . '/../logs/visit_store.log', "Error applying migration $migrationPath: " . $ex->getMessage() . "\n", FILE_APPEND);
                            }
                        }
                    }
                }
                if (!$applied) {
                    // Fallback: create a basic table structure
                    $createSql = "CREATE TABLE IF NOT EXISTS patient_visits (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        patient_id INT NOT NULL,
                        visit_date DATETIME NOT NULL,
                        visit_type VARCHAR(100),
                        ent_type ENUM('ear','nose','throat') DEFAULT 'ear',
                        chief_complaint TEXT,
                        diagnosis TEXT,
                        treatment_plan TEXT,
                        prescription TEXT,
                        notes TEXT,
                        doctor_id INT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )";
                    $this->db->exec($createSql);
                }
            }

            // Normalize some input keys: allow 'plan' as fallback for notes, and accept 'treatment' as 'treatment_plan'
            if (isset($input['plan']) && !isset($input['notes'])) {
                $input['notes'] = $input['plan'];
            }
            if (isset($input['treatment']) && !isset($input['treatment_plan'])) {
                $input['treatment_plan'] = $input['treatment'];
            }

            // Build data only from allowed fields (prevents staff from setting doctor-only fields)
            $data = [];
            foreach ($allowedFields as $f) {
                if (isset($input[$f])) {
                    $data[$f] = $input[$f];
                } else {
                    // For ent_type default to 'ear' if not provided and allowed
                    if ($f === 'ent_type') $data['ent_type'] = 'ear';
                    if ($f === 'doctor_id' && !$isStaff) $data['doctor_id'] = $input['doctor_id'] ?? 1;
                }
            }

            $columns = implode(',', array_keys($data));
            $placeholders = implode(',', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO patient_visits ($columns) VALUES ($placeholders)";

            $stmt = $this->db->prepare($sql);
            try {
                @file_put_contents(__DIR__ . '/../logs/visit_store.log', "Visit Data: " . var_export($data, true) . "\n", FILE_APPEND);
                $stmt->execute(array_values($data));
            } catch (PDOException $ex) {
                @file_put_contents(__DIR__ . '/../logs/visit_store.log', "SQL Error: " . $ex->getMessage() . "\n", FILE_APPEND);
                $this->error('Database error while creating visit', 500);
            }
            $visitId = $this->db->lastInsertId();

            $this->success(['id' => $visitId], 'Visit created successfully', 201);
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    // Update visit
    public function update($id)
    {
        try {
            // only doctors or admins can update visits
            $this->requireRole(['admin', 'doctor']);
            $input = $this->getInput();

            // Normalize synonyms in update payload
            if (isset($input['plan']) && !isset($input['notes'])) {
                $input['notes'] = $input['plan'];
            }
            if (isset($input['treatment']) && !isset($input['treatment_plan'])) {
                $input['treatment_plan'] = $input['treatment'];
            }

            // Check if visit exists
            $stmt = $this->db->prepare("SELECT id FROM patient_visits WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                $this->error('Visit not found', 404);
            }

            $allowedFields = [
                'visit_date', 'visit_type', 'ent_type', 'chief_complaint', 'diagnosis',
                'treatment_plan', 'prescription', 'notes', 'height', 'weight', 'blood_pressure',
                'temperature', 'vitals_notes'
            ];

            $data = [];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $data[$field] = $input[$field];
                }
            }

            if (empty($data)) {
                $this->error('No data to update', 400);
            }

            $set = implode(', ', array_map(function($k) { return $k . ' = ?'; }, array_keys($data)));
            $sql = "UPDATE patient_visits SET $set WHERE id = ?";

            $params = array_values($data);
            $params[] = $id;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->success([], 'Visit updated successfully');
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    // Delete visit
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM patient_visits WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                $this->error('Visit not found', 404);
            }

            $stmt = $this->db->prepare("DELETE FROM patient_visits WHERE id = ?");
            $stmt->execute([$id]);

            $this->success([], 'Visit deleted successfully');
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
}

