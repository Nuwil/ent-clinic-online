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
            $input = $this->getInput();

            $rules = [
                'patient_id' => 'required|numeric',
                'visit_date' => 'required',
                'visit_type' => 'required',
            ];

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
                $migrationPath = __DIR__ . '/../database/patient_visits_table.sql';
                if (file_exists($migrationPath)) {
                    $sql = file_get_contents($migrationPath);
                    // Execute all statements in the SQL file
                    $this->db->exec($sql);
                } else {
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

            $data = [
                'patient_id' => $input['patient_id'],
                'visit_date' => $input['visit_date'],
                'visit_type' => $input['visit_type'] ?? null,
                'ent_type' => $input['ent_type'] ?? 'ear',
                'chief_complaint' => $input['chief_complaint'] ?? null,
                'diagnosis' => $input['diagnosis'] ?? null,
                'treatment_plan' => $input['treatment_plan'] ?? null,
                'prescription' => $input['prescription'] ?? null,
                'notes' => $input['notes'] ?? null,
                'doctor_id' => $input['doctor_id'] ?? 1,
            ];

            $columns = implode(',', array_keys($data));
            $placeholders = implode(',', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO patient_visits ($columns) VALUES ($placeholders)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_values($data));
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
            $input = $this->getInput();

            // Check if visit exists
            $stmt = $this->db->prepare("SELECT id FROM patient_visits WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                $this->error('Visit not found', 404);
            }

            $allowedFields = [
                'visit_date', 'visit_type', 'ent_type', 'chief_complaint', 'diagnosis',
                'treatment_plan', 'prescription', 'notes'
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

