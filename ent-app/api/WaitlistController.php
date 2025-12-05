<?php
require_once __DIR__ . '/Controller.php';

class WaitlistController extends Controller {

    public function index() {
        $stmt = $this->db->prepare("SELECT * FROM waitlist ORDER BY created_at ASC LIMIT 10");
        $stmt->execute();
        $waitlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->json(['waitlist' => $waitlist]);
    }

    public function add() {
        $input = $this->getInput();
        $patient_id = $input['patient_id'] ?? null;
        $reason = $input['reason'] ?? null;

        if (!$patient_id) return $this->error('patient_id required', 400);

        // Check patient exists
        $stmt = $this->db->prepare("SELECT id FROM patients WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $patient_id]);
        if (!$stmt->fetch()) return $this->error('Patient not found', 404);

        // Add to waitlist
        $stmt = $this->db->prepare("INSERT INTO waitlist (patient_id, reason) VALUES (:patient_id, :reason)");
        $stmt->execute(['patient_id' => $patient_id, 'reason' => $reason]);

        $this->success(['id' => $this->db->lastInsertId()], 'Added to waitlist', 201);
    }

    public function remove($id) {
        $stmt = $this->db->prepare("DELETE FROM waitlist WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $this->success(true);
    }

    // Send notification to a patient (stub - integrate with email/SMS service)
    public function notify($patientId) {
        // In a real app, send email/SMS here
        // For now, just log it
        error_log('Notifying patient ' . $patientId . ' of available slot');

        // Optional: mark in a notifications table
        // $stmt = $this->db->prepare("INSERT INTO notifications (patient_id, type) VALUES (:patient_id, 'slot_available')");
        // $stmt->execute(['patient_id' => $patientId]);

        $this->success(true, 'Notification sent');
    }

}
