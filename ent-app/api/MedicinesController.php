<?php
/**
 * Medicines Controller
 */

require_once __DIR__ . '/Controller.php';

class MedicinesController extends Controller
{
    /**
     * GET /api/medicines - Get all active medicines
     */
    public function index()
    {
        try {
            $query = "SELECT id, name, dosage, unit FROM medicines WHERE is_active = TRUE ORDER BY name ASC";
            $stmt = $this->db->prepare($query);
            
            if (!$stmt->execute()) {
                throw new Exception('Database error: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $medicines = [];
            
            while ($row = $result->fetch_assoc()) {
                $medicines[] = $row;
            }
            
            $this->json($medicines);
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/medicines - Create a new medicine (admin only)
     */
    public function store()
    {
        $this->requireRole('admin');
        
        $input = $this->getInput();
        
        $name = trim($input['name'] ?? '');
        $dosage = trim($input['dosage'] ?? '');
        $unit = trim($input['unit'] ?? '');
        
        if (empty($name)) {
            $this->error('Medicine name is required', 400);
        }
        
        try {
            $query = "INSERT INTO medicines (name, dosage, unit) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($query);
            
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $this->db->error);
            }
            
            $stmt->bind_param('sss', $name, $dosage, $unit);
            
            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            
            $this->success(['id' => $this->db->insert_id], 'Medicine created successfully', 201);
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
}
