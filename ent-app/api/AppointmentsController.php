<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/SlotGenerator.php';

class AppointmentsController extends Controller {

    // Return list of doctors
    public function doctors() {
        $stmt = $this->db->prepare("SELECT id, full_name, email FROM users WHERE role = 'doctor' AND is_active = 1 ORDER BY full_name");
        $stmt->execute();
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->success(['doctors' => $doctors], 'Doctors retrieved');
    }

    // Return available appointments within a date range
    public function index() {
        $start = $_GET['start'] ?? date('Y-m-d');
        $end = $_GET['end'] ?? $start;
        $patient_id = $_GET['patient_id'] ?? null;

        $pdo = $this->db;

        // appointments table stores `appointment_date` and `duration`.
        // Alias to `start_at`/`end_at` for frontend compatibility.
        $sql = "SELECT *, appointment_date AS start_at, DATE_ADD(appointment_date, INTERVAL COALESCE(duration,0) MINUTE) AS end_at, appointment_type AS type FROM appointments WHERE DATE(appointment_date) BETWEEN :start AND :end";
        
        // Filter by patient_id if provided
        if ($patient_id) {
            $sql .= " AND patient_id = :patient_id";
        }
        
        $sql .= " ORDER BY appointment_date";
        $stmt = $pdo->prepare($sql);
        $params = ['start' => $start, 'end' => $end];
        if ($patient_id) {
            $params['patient_id'] = $patient_id;
        }
        $stmt->execute($params);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->json(['appointments' => $appointments]);
    }

    // Create a new appointment (with validation, overlap and daily_max enforcement)
    public function create() {
        $input = $this->getInput();
        // Log incoming input for debugging
        @file_put_contents(__DIR__ . '/../logs/appointment_create.log', "\n--- Incoming Payload ---\nTime: " . date('c') . "\nInput: " . var_export($input, true) . "\n", FILE_APPEND);

        $patient_id = $input['patient_id'] ?? null;
        $doctor_id = $input['doctor_id'] ?? null;
        $type = $input['type'] ?? 'follow_up';
        $start_at = $input['start_at'] ?? null;
        $end_at = $input['end_at'] ?? null;
        $notes = $input['notes'] ?? null;
        $chief_complaint = $input['chief_complaint'] ?? null;
        
        // Vitals
        $blood_pressure = $input['blood_pressure'] ?? null;
        $temperature = $input['temperature'] ?? null;
        $pulse_rate = $input['pulse_rate'] ?? null;
        $respiratory_rate = $input['respiratory_rate'] ?? null;
        $oxygen_saturation = $input['oxygen_saturation'] ?? null;

        // Basic validation (doctor_id is optional at creation)
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

        // Validate doctor if provided (doctor assignment is optional)
        if ($doctor_id !== null && $doctor_id !== '') {
            if (!is_numeric($doctor_id)) {
                return $this->error('doctor_id must be numeric', 422);
            }
            $stmt = $this->db->prepare("SELECT id FROM users WHERE id = :id AND role = 'doctor' LIMIT 1");
            $stmt->execute(['id' => $doctor_id]);
            $d = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$d) return $this->error('Doctor not found', 404);
        } else {
            // Ensure we store null for unassigned
            $doctor_id = null;
        }

        // Parse times
        try {
            // Log raw incoming datetimes for debugging timezone/offset issues
            // write to ent-app logs folder (ensure logs directory exists)
            @file_put_contents(__DIR__ . '/../logs/appointment_create.log', "\n--- Appointment Create ---\nTime: " . date('c') . "\nRaw start_at: " . var_export($start_at, true) . "\nRaw end_at: " . var_export($end_at, true) . "\n", FILE_APPEND);

            $s = new DateTime($start_at);
            $e = new DateTime($end_at);
            // Log parsed DateTime values (server timezone)
            @file_put_contents(__DIR__ . '/../logs/appointment_create.log', "Parsed start: " . $s->format('Y-m-d H:i:s T') . "\nParsed end: " . $e->format('Y-m-d H:i:s T') . "\n", FILE_APPEND);
        } catch (Exception $ex) {
            return $this->error('Invalid start/end datetime', 400);
        }

        if ($e <= $s) return $this->error('End must be after start', 400);

        // Overlap check against stored appointment_date + duration
        if ($doctor_id) {
            $sql = "SELECT COUNT(*) as c FROM appointments WHERE status IN ('Pending', 'Accepted') AND doctor_id = :doctor_id AND ((appointment_date < :end_at AND DATE_ADD(appointment_date, INTERVAL COALESCE(duration,0) MINUTE) > :start_at))";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['start_at' => $start_at, 'end_at' => $end_at, 'doctor_id' => $doctor_id]);
        } else {
            $sql = "SELECT COUNT(*) as c FROM appointments WHERE status IN ('Pending', 'Accepted') AND ((appointment_date < :end_at AND DATE_ADD(appointment_date, INTERVAL COALESCE(duration,0) MINUTE) > :start_at))";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['start_at' => $start_at, 'end_at' => $end_at]);
        }
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
            $stmt = $this->db->prepare("SELECT COUNT(*) as c FROM appointments WHERE status IN ('Pending', 'Accepted') AND appointment_type = :type AND DATE(appointment_date) = :date");
            $stmt->execute(['type' => $type, 'date' => $date]);
            $c = (int)$stmt->fetchColumn();
            if ($c >= $dailyMax) {
                return $this->error('Daily limit reached for this appointment type', 409);
            }
        }

        // Insert appointment
        // Compute duration in minutes and insert into appointment_date/duration columns
        $duration = (int)(($e->getTimestamp() - $s->getTimestamp()) / 60);
        $sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_type, status, appointment_date, duration, notes, chief_complaint, blood_pressure, temperature, pulse_rate, respiratory_rate, oxygen_saturation) VALUES (:patient_id, :doctor_id, :appointment_type, 'Pending', :appointment_date, :duration, :notes, :chief_complaint, :blood_pressure, :temperature, :pulse_rate, :respiratory_rate, :oxygen_saturation)";
        $stmt = $this->db->prepare($sql);
        try {
            $stmt->execute([
            'patient_id' => $patient_id,
            'doctor_id' => $doctor_id,
            'appointment_type' => $type,
            'appointment_date' => $start_at,
            'duration' => $duration,
            'notes' => $notes,
            'chief_complaint' => $chief_complaint,
            'blood_pressure' => $blood_pressure,
            'temperature' => $temperature,
            'pulse_rate' => $pulse_rate,
            'respiratory_rate' => $respiratory_rate,
            'oxygen_saturation' => $oxygen_saturation
            ]);
        } catch (PDOException $ex) {
            @file_put_contents(__DIR__ . '/../logs/appointment_create.log', "SQL Error: " . $ex->getMessage() . "\n", FILE_APPEND);
            return $this->error('Database error while creating appointment', 500);
        }

        $id = $this->db->lastInsertId();
        $this->success(['id' => $id], 'Appointment booked', 201);
    }

    public function cancel($id) {
        // Allow optional cancellation reason in request body
        $input = $this->getInput();
        $reason = isset($input['reason']) ? trim($input['reason']) : null;
        $stmt = $this->db->prepare("UPDATE appointments SET status = 'Cancelled', cancellation_reason = :reason WHERE id = :id");
        $stmt->execute(['id' => $id, 'reason' => $reason]);
        $this->success(true);
    }

    // Accept an appointment (doctor confirms they will see patient)
    public function accept($id) {
        // Only doctors and admins may accept appointments
        $this->requireRole(['doctor', 'admin']);
        $stmt = $this->db->prepare("SELECT * FROM appointments WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $apt = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$apt) return $this->error('Appointment not found', 404);

        $stmt = $this->db->prepare("UPDATE appointments SET status = 'Accepted' WHERE id = :id");
        $stmt->execute(['id' => $id]);
        // Return updated appointment row
        $stmt = $this->db->prepare("SELECT * FROM appointments WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->success(['id' => $id, 'appointment' => $updated], 'Appointment accepted');
    }

    // Mark appointment as completed (when a visit is created from this appointment)
    public function complete($id) {
        // Only doctors and admins may complete appointments (create visits from accepted appointments)
        $this->requireRole(['doctor', 'admin']);
        try {
            $stmt = $this->db->prepare("SELECT * FROM appointments WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $apt = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$apt) return $this->error('Appointment not found', 404);

            $input = $this->getInput();
            // Log raw complete input & mapping for debugging
            @file_put_contents(__DIR__ . '/../logs/appointment_complete.log', "\n--- Appointment Complete ---\nTime: " . date('c') . "\nInput: " . var_export($input, true) . "\n", FILE_APPEND);
            
            // Create a visit record from the appointment completion data
            // Only include columns that exist in the `patient_visits` table
            $visitDate = null;
            if (!empty($apt['appointment_date'])) {
                // appointment_date may be stored as DATETIME or ISO string
                $visitDate = $apt['appointment_date'];
            } else {
                $visitDate = date('Y-m-d H:i:s');
            }

            $currentUser = $_SESSION['user'] ?? null;
            $doctorId = $currentUser['id'] ?? null;
            $doctorName = isset($currentUser['full_name']) ? $currentUser['full_name'] : ($currentUser['username'] ?? null);

            // If a visit already exists for this appointment, avoid creating a duplicate.
            $existingStmt = $this->db->prepare("SELECT id FROM patient_visits WHERE appointment_id = :id LIMIT 1");
            $existingStmt->execute(['id' => $id]);
            $existingVisit = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if ($existingVisit) {
                // There is already a visit created for this appointment - mark appointment completed and return existing visit id
                @file_put_contents(__DIR__ . '/../logs/appointment_complete.log', "Visit already exists for appointment $id, visit_id: " . ($existingVisit['id'] ?? '(unknown)') . "\n", FILE_APPEND);
                $visitId = $existingVisit['id'];

                $stmt = $this->db->prepare("UPDATE appointments SET status = 'Completed' WHERE id = :id");
                $stmt->execute(['id' => $id]);

                $this->success(['id' => $id, 'visit_id' => $visitId], 'Appointment already had visit; marked Completed');
                return;
            }

            // Normalize ent_type provided by client; default to 'misc'
            $entType = isset($input['ent_type']) ? ($this->normalizeEntType($input['ent_type']) ?? 'misc') : 'misc';

            $visitData = [
                'patient_id' => $apt['patient_id'],
                'appointment_id' => $id,
                'visit_date' => $visitDate,
                'visit_type' => $input['visit_type'] ?? 'Consultation',
                'ent_type' => $entType,
                'chief_complaint' => $input['chief_complaint'] ?? '',
                'diagnosis' => $input['diagnosis'] ?? '',
                'treatment_plan' => $input['treatment'] ?? '',
                'prescription' => $input['prescription'] ?? null,
                'notes' => $input['notes'] ?? $input['plan'] ?? null,
                // vitals (optional) - only include if provided
                'blood_pressure' => isset($input['blood_pressure']) && $input['blood_pressure'] !== '' ? $input['blood_pressure'] : null,
                'temperature' => isset($input['temperature']) && $input['temperature'] !== '' ? $input['temperature'] : null,
                'pulse_rate' => isset($input['pulse_rate']) && $input['pulse_rate'] !== '' ? (int)$input['pulse_rate'] : null,
                'respiratory_rate' => isset($input['respiratory_rate']) && $input['respiratory_rate'] !== '' ? (int)$input['respiratory_rate'] : null,
                'oxygen_saturation' => isset($input['oxygen_saturation']) && $input['oxygen_saturation'] !== '' ? (int)$input['oxygen_saturation'] : null,
                'vitals_notes' => isset($input['vitals_notes']) ? $input['vitals_notes'] : null,
                'doctor_id' => $doctorId,
                'doctor_name' => $doctorName,
            ];
            
            $columns = implode(',', array_keys($visitData));
            $placeholders = implode(',', array_fill(0, count($visitData), '?'));
            $sql = "INSERT INTO patient_visits ($columns) VALUES ($placeholders)";
            
            $stmt = $this->db->prepare($sql);
            try {
                @file_put_contents(__DIR__ . '/../logs/appointment_complete.log', "Visit Data: " . var_export($visitData, true) . "\n", FILE_APPEND);
                $stmt->execute(array_values($visitData));
            } catch (PDOException $ex) {
                @file_put_contents(__DIR__ . '/../logs/appointment_complete.log', "SQL Error: " . $ex->getMessage() . "\n", FILE_APPEND);
                return $this->error('Database error while completing appointment', 500);
            }
            $visitId = $this->db->lastInsertId();

            // Mark appointment as completed
            $stmt = $this->db->prepare("UPDATE appointments SET status = 'Completed' WHERE id = :id");
            $stmt->execute(['id' => $id]);
            
            $this->success(['id' => $id, 'visit_id' => $visitId], 'Appointment completed and visit record created');
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
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

        // Check for conflicts in new time slot (respect doctor assignment if present)
        if (!empty($apt['doctor_id'])) {
            $sql = "SELECT COUNT(*) as c FROM appointments WHERE status IN ('Pending', 'Accepted') AND id != :id AND doctor_id = :doctor_id AND ((appointment_date < :end_at AND DATE_ADD(appointment_date, INTERVAL COALESCE(duration,0) MINUTE) > :start_at))";
            $params = ['id' => $id, 'start_at' => $start_at, 'end_at' => $end_at, 'doctor_id' => $apt['doctor_id']];
        } else {
            $sql = "SELECT COUNT(*) as c FROM appointments WHERE status IN ('Pending', 'Accepted') AND id != :id AND ((appointment_date < :end_at AND DATE_ADD(appointment_date, INTERVAL COALESCE(duration,0) MINUTE) > :start_at))";
            $params = ['id' => $id, 'start_at' => $start_at, 'end_at' => $end_at];
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['c'] > 0) {
            return $this->error('New slot is occupied', 409);
        }

        // Update appointment
        $notes = $input['notes'] ?? $apt['notes'];
        // Update appointment_date and duration and record reschedule metadata
        $newDuration = (int)( (strtotime($start_at) === false || strtotime($end_at) === false) ? 0 : ( (strtotime($end_at) - strtotime($start_at)) / 60 ) );

        // Only attempt to set rescheduled_from/rescheduled_to if those columns exist (DB may not have migrations applied)
        $hasResFrom = false; $hasResTo = false;
        try {
            $c = $this->db->prepare("SHOW COLUMNS FROM appointments LIKE 'rescheduled_from'"); $c->execute(); if ($c->fetch()) $hasResFrom = true;
            $c = $this->db->prepare("SHOW COLUMNS FROM appointments LIKE 'rescheduled_to'"); $c->execute(); if ($c->fetch()) $hasResTo = true;
        } catch (Exception $e) {
            // ignore - keep flags false
        }

        if ($hasResFrom && $hasResTo) {
            $stmt = $this->db->prepare("UPDATE appointments SET appointment_date = :start, duration = :duration, notes = :notes, rescheduled_from = :res_from, rescheduled_to = :res_to WHERE id = :id");
            $params = ['start' => $start_at, 'duration' => $newDuration, 'notes' => $notes, 'res_from' => $apt['appointment_date'], 'res_to' => $start_at, 'id' => $id];
        } else {
            // Fallback: don't attempt to write rescheduled columns to avoid SQL errors on older DBs
            $stmt = $this->db->prepare("UPDATE appointments SET appointment_date = :start, duration = :duration, notes = :notes WHERE id = :id");
            $params = ['start' => $start_at, 'duration' => $newDuration, 'notes' => $notes, 'id' => $id];
            @file_put_contents(__DIR__ . '/../logs/appointment_reschedule.log', "Reschedule columns missing; skipping rescheduled_* update for appointment {$id}\n", FILE_APPEND);
        }

        try {
            $stmt->execute($params);
        } catch (PDOException $ex) {
            @file_put_contents(__DIR__ . '/../logs/appointment_reschedule.log', "SQL Error: " . $ex->getMessage() . "\nParams: " . var_export($params, true) . "\n", FILE_APPEND);
            return $this->error('Database error while rescheduling appointment', 500);
        }

        $this->success(['id' => $id], 'Appointment rescheduled');
    }

    // Generate slots for a given date based on clinic policy
    public function slots() {
        $date = $_GET['date'] ?? date('Y-m-d');

        $config = $this->getClinicConfig();
        $slots = SlotGenerator::generateSlotsForDate($date, $config);

        // Mark booked slots from DB (appointment_date + duration)
        $stmt = $this->db->prepare("SELECT appointment_date AS start_at, DATE_ADD(appointment_date, INTERVAL COALESCE(duration,0) MINUTE) AS end_at, appointment_type AS type FROM appointments WHERE DATE(appointment_date) = :date AND status IN ('Pending', 'Accepted')");
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
