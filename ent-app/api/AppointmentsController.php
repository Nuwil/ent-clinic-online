<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/SlotGenerator.php';

class AppointmentsController extends Controller {

    // Return available appointments within a date range
    public function index() {
        $start = $_GET['start'] ?? date('Y-m-d');
        $end = $_GET['end'] ?? $start;

        $pdo = $this->db;

        // appointments table stores `appointment_date` and `duration`.
        // Alias to `start_at`/`end_at` for frontend compatibility.
        $sql = "SELECT *, appointment_date AS start_at, DATE_ADD(appointment_date, INTERVAL COALESCE(duration,0) MINUTE) AS end_at, appointment_type AS type FROM appointments WHERE DATE(appointment_date) BETWEEN :start AND :end ORDER BY appointment_date";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['start' => $start, 'end' => $end]);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->json(['appointments' => $appointments]);
    }

    // Create a new appointment (with validation, overlap and daily_max enforcement)
    public function create() {
        $input = $this->getInput();

        $patient_id = $input['patient_id'] ?? null;
        $type = $input['type'] ?? 'follow_up';
        $start_at = $input['start_at'] ?? null;
        $end_at = $input['end_at'] ?? null;
        $notes = $input['notes'] ?? null;

        // Basic validation
        $errors = $this->validate(['patient_id'=>$patient_id,'start_at'=>$start_at,'end_at'=>$end_at], [
            'patient_id' => 'required|numeric',
            'start_at' => 'required',
            'end_at' => 'required'
        ]);

        if (!empty($errors)) {
            return $this->error($errors, 422);
        }

        // Validate patient exists
        $stmt = $this->db->prepare("SELECT id FROM patients WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $patient_id]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) return $this->error('Patient not found', 404);

        // Parse times
        try {
            $s = new DateTime($start_at);
            $e = new DateTime($end_at);
        } catch (Exception $ex) {
            return $this->error('Invalid start/end datetime', 400);
        }

        if ($e <= $s) return $this->error('End must be after start', 400);

        // Overlap check against stored appointment_date + duration
        $sql = "SELECT COUNT(*) as c FROM appointments WHERE status = 'scheduled' AND ((appointment_date < :end_at AND DATE_ADD(appointment_date, INTERVAL COALESCE(duration,0) MINUTE) > :start_at))";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['start_at' => $start_at, 'end_at' => $end_at]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['c'] > 0) {
            return $this->error('Slot is occupied', 409);
        }

        // Enforce daily_max for type (try DB appointment_types first)
        $date = $s->format('Y-m-d');
        $dailyMax = null;
        $stmt = $this->db->prepare("SELECT daily_max FROM appointment_types WHERE `key` = :key LIMIT 1");
        $stmt->execute(['key' => $type]);
        $atype = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($atype && $atype['daily_max'] !== null) {
            $dailyMax = (int)$atype['daily_max'];
        } else {
            $config = $this->getClinicConfig();
            $dailyMax = $config['types'][$type]['daily_max'] ?? null;
        }

        if ($dailyMax !== null) {
            $stmt = $this->db->prepare("SELECT COUNT(*) as c FROM appointments WHERE status = 'scheduled' AND appointment_type = :type AND DATE(appointment_date) = :date");
            $stmt->execute(['type' => $type, 'date' => $date]);
            $c = (int)$stmt->fetchColumn();
            if ($c >= $dailyMax) {
                return $this->error('Daily limit reached for this appointment type', 409);
            }
        }

        // Insert appointment
        // Compute duration in minutes and insert into appointment_date/duration columns
        $duration = (int)(($e->getTimestamp() - $s->getTimestamp()) / 60);
        $sql = "INSERT INTO appointments (patient_id, appointment_type, status, appointment_date, duration, notes) VALUES (:patient_id, :appointment_type, 'scheduled', :appointment_date, :duration, :notes)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'patient_id' => $patient_id,
            'appointment_type' => $type,
            'appointment_date' => $start_at,
            'duration' => $duration,
            'notes' => $notes
        ]);

        $id = $this->db->lastInsertId();
        $this->success(['id' => $id], 'Appointment booked', 201);
    }

    public function cancel($id) {
        $stmt = $this->db->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $this->success(true);
    }

    // Accept an appointment (doctor confirms they will see patient)
    public function accept($id) {
        $stmt = $this->db->prepare("SELECT * FROM appointments WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $apt = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$apt) return $this->error('Appointment not found', 404);

        $stmt = $this->db->prepare("UPDATE appointments SET status = 'accepted' WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $this->success(['id' => $id], 'Appointment accepted');
    }

    // Mark appointment as completed and auto-create a visit record
    public function complete($id) {
        $stmt = $this->db->prepare("SELECT * FROM appointments WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $apt = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$apt) return $this->error('Appointment not found', 404);

        $input = $this->getInput();
        $ent_type = $input['ent_type'] ?? 'misc';
        $diagnosis = $input['diagnosis'] ?? '';
        $treatment = $input['treatment'] ?? '';
        $prescription_items = $input['prescription_items'] ?? [];

        // Mark appointment as completed
        $stmt = $this->db->prepare("UPDATE appointments SET status = 'completed' WHERE id = :id");
        $stmt->execute(['id' => $id]);

        // Auto-create visit record from appointment
        $stmt = $this->db->prepare("
            INSERT INTO patient_visits (patient_id, visit_date, ent_type, diagnosis, treatment_plan, notes, created_at)
            VALUES (:patient_id, :visit_date, :ent_type, :diagnosis, :treatment_plan, :notes, NOW())
        ");
        $stmt->execute([
            'patient_id' => $apt['patient_id'],
            'visit_date' => date('Y-m-d', strtotime($apt['appointment_date'])),
            'ent_type' => $ent_type,
            'diagnosis' => $diagnosis,
            'treatment_plan' => $treatment,
            'notes' => $apt['notes'] ?? ''
        ]);

        $visit_id = $this->db->lastInsertId();

        // If prescription items provided, link them to visit
        if (!empty($prescription_items) && is_array($prescription_items)) {
            $stmt = $this->db->prepare("
                INSERT INTO prescription_items (visit_id, medicine_id, dosage, frequency, duration, instructions)
                VALUES (:visit_id, :medicine_id, :dosage, :frequency, :duration, :instructions)
            ");
            foreach ($prescription_items as $item) {
                $stmt->execute([
                    'visit_id' => $visit_id,
                    'medicine_id' => $item['medicine_id'] ?? null,
                    'dosage' => $item['dosage'] ?? '',
                    'frequency' => $item['frequency'] ?? '',
                    'duration' => $item['duration'] ?? '',
                    'instructions' => $item['instructions'] ?? ''
                ]);
            }
        }

        $this->success([
            'appointment_id' => $id,
            'visit_id' => $visit_id
        ], 'Appointment completed and visit record created');
    }

    // Reschedule an appointment to a new time slot
    public function reschedule($id) {
        $input = $this->getInput();
        $start_at = $input['start_at'] ?? null;
        $end_at = $input['end_at'] ?? null;

        if (!$start_at || !$end_at) {
            return $this->error('Missing start_at or end_at', 400);
        }

        // Verify appointment exists
        $stmt = $this->db->prepare("SELECT * FROM appointments WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $apt = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$apt) return $this->error('Appointment not found', 404);

        // Check for conflicts in new time slot
        $sql = "SELECT COUNT(*) as c FROM appointments WHERE status = 'scheduled' AND id != :id AND ((appointment_date < :end_at AND DATE_ADD(appointment_date, INTERVAL COALESCE(duration,0) MINUTE) > :start_at))";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'start_at' => $start_at, 'end_at' => $end_at]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['c'] > 0) {
            return $this->error('New slot is occupied', 409);
        }

        // Update appointment
        $notes = $input['notes'] ?? $apt['notes'];
        // Update appointment_date and duration
        $newDuration = (int)( (strtotime($start_at) === false || strtotime($end_at) === false) ? 0 : ( (strtotime($end_at) - strtotime($start_at)) / 60 ) );
        $stmt = $this->db->prepare("UPDATE appointments SET appointment_date = :start, duration = :duration, notes = :notes WHERE id = :id");
        $stmt->execute(['start' => $start_at, 'duration' => $newDuration, 'notes' => $notes, 'id' => $id]);

        $this->success(['id' => $id], 'Appointment rescheduled');
    }

    // Generate slots for a given date based on clinic policy
    public function slots() {
        $date = $_GET['date'] ?? date('Y-m-d');

        $config = $this->getClinicConfig();
        $slots = SlotGenerator::generateSlotsForDate($date, $config);

        // Mark booked slots from DB (appointment_date + duration)
        $stmt = $this->db->prepare("SELECT appointment_date AS start_at, DATE_ADD(appointment_date, INTERVAL COALESCE(duration,0) MINUTE) AS end_at, appointment_type AS type FROM appointments WHERE DATE(appointment_date) = :date AND status = 'scheduled'");
        $stmt->execute(['date' => $date]);
        $booked = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // annotate
        foreach ($slots as &$s) {
            $s['booked'] = false;
            foreach ($booked as $b) {
                if ($s['start'] == $b['start_at'] && $s['end'] == $b['end_at']) {
                    $s['booked'] = true;
                    $s['type'] = $b['type'];
                }
            }
        }

        $this->json(['date' => $date, 'slots' => $slots]);
    }

    private function getClinicConfig() {
        // Clinic schedule and appointment types (hard-coded for now)
        return [
            'working_hours' => ['start' => '09:00', 'end' => '17:00'],
            'lunch' => ['start' => '12:00', 'end' => '13:00'],
            'types' => [
                'new_patient' => ['duration' => 30, 'buffer' => 10, 'daily_max' => 4],
                'follow_up' => ['duration' => 15, 'buffer' => 5, 'daily_max' => 10],
                'procedure' => ['duration' => 45, 'buffer' => 15, 'daily_max' => 2],
                'emergency' => ['duration' => 0, 'buffer' => 0, 'daily_max' => null]
            ],
            'exam_rooms' => 1
        ];
    }

}
