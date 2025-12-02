<?php
/**
 * Recordings API Controller
 */

require_once __DIR__ . '/Controller.php';

class RecordingsController extends Controller
{
    // Get all recordings
    public function index()
    {
        try {
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 10;
            $offset = ($page - 1) * $limit;
            $patientId = $_GET['patient_id'] ?? null;
            $status = $_GET['status'] ?? null;

            $sql = "SELECT r.*, p.first_name, p.last_name FROM recordings r JOIN patients p ON r.patient_id = p.id WHERE 1=1";
            $params = [];

            if ($patientId) {
                $sql .= " AND r.patient_id = ?";
                $params[] = $patientId;
            }

            if ($status) {
                $sql .= " AND r.status = ?";
                $params[] = $status;
            }

            $countStmt = $this->db->prepare(str_replace('SELECT r.*, p.first_name, p.last_name', 'SELECT COUNT(*) as count', $sql));
            $countStmt->execute($params);
            $total = $countStmt->fetch()['count'];

            $sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $recordings = $stmt->fetchAll();

            $this->success([
                'recordings' => $recordings,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]);
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    // Get single recording
    public function show($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT r.*, p.first_name, p.last_name FROM recordings r JOIN patients p ON r.patient_id = p.id WHERE r.id = ?");
            $stmt->execute([$id]);
            $recording = $stmt->fetch();

            if (!$recording) {
                $this->error('Recording not found', 404);
            }

            $this->success($recording);
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    // Create new recording
    public function store()
    {
        try {
            $input = $this->getInput();

            $rules = [
                'patient_id' => 'required|numeric',
                'recording_type' => 'required',
                'recording_title' => 'required|min:3',
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

            $data = [
                'patient_id' => $input['patient_id'],
                'recording_type' => $input['recording_type'],
                'recording_title' => $input['recording_title'],
                'recording_description' => $input['recording_description'] ?? null,
                'file_path' => $input['file_path'] ?? null,
                'file_size' => $input['file_size'] ?? null,
                'duration' => $input['duration'] ?? null,
                'recorded_by' => $input['recorded_by'] ?? 1,
                'recorded_at' => $input['recorded_at'] ?? date('Y-m-d H:i:s'),
                'diagnosis' => $input['diagnosis'] ?? null,
                'notes' => $input['notes'] ?? null,
                'status' => $input['status'] ?? 'pending',
            ];

            $columns = implode(',', array_keys($data));
            $placeholders = implode(',', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO recordings ($columns) VALUES ($placeholders)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_values($data));
            $recordingId = $this->db->lastInsertId();

            $this->success(['id' => $recordingId], 'Recording created successfully', 201);
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    // Update recording
    public function update($id)
    {
        try {
            $input = $this->getInput();

            // Check if recording exists
            $stmt = $this->db->prepare("SELECT id FROM recordings WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                $this->error('Recording not found', 404);
            }

            $allowedFields = [
                'recording_title', 'recording_description', 'recording_type',
                'file_path', 'file_size', 'duration', 'diagnosis', 'notes', 'status'
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
            $sql = "UPDATE recordings SET $set WHERE id = ?";

            $params = array_values($data);
            $params[] = $id;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->success([], 'Recording updated successfully');
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    // Delete recording
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT file_path FROM recordings WHERE id = ?");
            $stmt->execute([$id]);
            $recording = $stmt->fetch();

            if (!$recording) {
                $this->error('Recording not found', 404);
            }

            // Delete file if exists
            if ($recording['file_path'] && file_exists($recording['file_path'])) {
                unlink($recording['file_path']);
            }

            $stmt = $this->db->prepare("DELETE FROM recordings WHERE id = ?");
            $stmt->execute([$id]);

            $this->success([], 'Recording deleted successfully');
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
}
