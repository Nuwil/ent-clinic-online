<?php
/**
 * Patients API Controller
 */

require_once __DIR__ . '/Controller.php';

class PatientsController extends Controller
{
    // Get all patients
    public function index()
    {
        try {
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 10;
            $offset = ($page - 1) * $limit;
            $search = $_GET['search'] ?? '';

            $sql = "SELECT * FROM patients";
            $params = [];

            if (!empty($search)) {
                $sql .= " WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?";
                $searchTerm = "%$search%";
                $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
            }

            $countStmt = $this->db->prepare(str_replace('SELECT *', 'SELECT COUNT(*) as count', $sql));
            $countStmt->execute($params);
            $total = $countStmt->fetch()['count'];

            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $patients = $stmt->fetchAll();

            $this->success([
                'patients' => $patients,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]);
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    // Get single patient
    public function show($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM patients WHERE id = ?");
            $stmt->execute([$id]);
            $patient = $stmt->fetch();

            if (!$patient) {
                $this->error('Patient not found', 404);
            }

            // Get patient's recordings
            $stmt = $this->db->prepare("SELECT * FROM recordings WHERE patient_id = ? ORDER BY created_at DESC");
            $stmt->execute([$id]);
            $patient['recordings'] = $stmt->fetchAll();

            // Get patient's appointments
            $stmt = $this->db->prepare("SELECT * FROM appointments WHERE patient_id = ? ORDER BY appointment_date DESC");
            $stmt->execute([$id]);
            $patient['appointments'] = $stmt->fetchAll();

            $this->success($patient);
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    // Create new patient
    public function store()
    {
        try {
            // allow admin, doctor, and staff (secretary) to create patients
            $this->requireRole(['admin', 'doctor', 'staff']);
            $input = $this->getInput();

            // Validation: require names and gender; make date_of_birth optional and validate email only if provided
            $rules = [
                'first_name' => 'required|min:2',
                'last_name' => 'required|min:2',
                'gender' => 'required'
            ];

            $errors = $this->validate($input, $rules);
            if (!empty($errors)) {
                $this->error($errors, 422);
            }

            if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                $this->error(['email' => 'email must be a valid email'], 422);
            }

            // Generate unique patient ID
            $patientId = 'PAT-' . date('YmdHis') . '-' . rand(1000, 9999);

            $data = [
                'patient_id' => $patientId,
                'first_name' => $input['first_name'] ?? '',
                'last_name' => $input['last_name'] ?? '',
                'date_of_birth' => $input['date_of_birth'] ?? null,
                'gender' => $input['gender'] ?? '',
                'email' => $input['email'] ?? null,
                'phone' => $input['phone'] ?? null,
                'occupation' => $input['occupation'] ?? null,
                'address' => $input['address'] ?? null,
                'city' => $input['city'] ?? null,
                'state' => $input['state'] ?? null,
                'postal_code' => $input['postal_code'] ?? null,
                'country' => $input['country'] ?? null,
                'medical_history' => $input['medical_history'] ?? null,
                'current_medications' => $input['current_medications'] ?? null,
                'allergies' => $input['allergies'] ?? null,
                'insurance_provider' => $input['insurance_provider'] ?? null,
                'insurance_id' => $input['insurance_id'] ?? null,
                'created_by' => $input['created_by'] ?? 1,
            ];

            $columns = implode(',', array_keys($data));
            $placeholders = implode(',', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO patients ($columns) VALUES ($placeholders)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_values($data));
            $patientId = $this->db->lastInsertId();

            $this->success(['id' => $patientId], 'Patient created successfully', 201);
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    // Update patient
    public function update($id)
    {
        try {
            // allow admin, doctor, and staff to update patients
            $this->requireRole(['admin', 'doctor', 'staff']);
            $input = $this->getInput();

            // Check if patient exists
            $stmt = $this->db->prepare("SELECT id FROM patients WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                $this->error('Patient not found', 404);
            }

            $allowedFields = [
                'first_name', 'last_name', 'gender', 'date_of_birth', 'email',
                'phone', 'occupation', 'address', 'city', 'state', 'postal_code', 'country',
                'medical_history', 'current_medications', 'allergies',
                'insurance_provider', 'insurance_id'
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

            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
            $sql = "UPDATE patients SET $set WHERE id = ?";

            $params = array_values($data);
            $params[] = $id;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->success([], 'Patient updated successfully');
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    // Delete patient
    public function delete($id)
    {
        try {
            // only admin can delete patients
            $this->requireRole(['admin']);
            $stmt = $this->db->prepare("SELECT id FROM patients WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                $this->error('Patient not found', 404);
            }

            $stmt = $this->db->prepare("DELETE FROM patients WHERE id = ?");
            $stmt->execute([$id]);

            $this->success([], 'Patient deleted successfully');
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
}
