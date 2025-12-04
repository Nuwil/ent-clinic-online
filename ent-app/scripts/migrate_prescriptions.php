<?php
/**
 * Simple migration script to create prescription_items table if it doesn't exist.
 * Usage (PowerShell):
 * php scripts\migrate_prescriptions.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

try {
    $db = Database::getInstance()->getConnection();

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS prescription_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    visit_id INT DEFAULT NULL,
    patient_id INT NOT NULL,
    medicine_id INT DEFAULT NULL,
    medicine_name VARCHAR(255) NOT NULL,
    instruction TEXT,
    doctor_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES patient_visits(id) ON DELETE SET NULL,
    INDEX idx_patient_id_presc (patient_id),
    INDEX idx_visit_id_presc (visit_id)
);
SQL;

    if ($db->query($sql) === TRUE) {
        echo "Migration applied: prescription_items table ensured.\n";
        exit(0);
    } else {
        echo "Migration failed: " . $db->error . "\n";
        exit(2);
    }
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(3);
}
