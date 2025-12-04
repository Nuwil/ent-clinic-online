<?php
require_once __DIR__ . '/../config/Database.php';

echo "Create prescription_items table if missing\n";
try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->query("SHOW TABLES LIKE 'prescription_items'");
    $exists = $stmt->fetch();
    if ($exists) {
        echo "prescription_items already exists.\n";
        exit(0);
    }

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    $db->exec($sql);
    echo "prescription_items table created.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
