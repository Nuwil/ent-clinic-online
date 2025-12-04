-- ENT Clinic Online Database Schema
-- MySQL/MariaDB

CREATE DATABASE IF NOT EXISTS ent_clinic;
USE ent_clinic;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150),
    role ENUM('admin', 'doctor', 'staff') DEFAULT 'staff',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
);

-- Patients Table
CREATE TABLE IF NOT EXISTS patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other') NOT NULL,
    email VARCHAR(100),
    full_name VARCHAR(255),
    phone VARCHAR(20),
    occupation VARCHAR(100),
    address VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    medical_history TEXT,
    current_medications TEXT,
    allergies TEXT,
    vaccine_history TEXT,
    insurance_provider VARCHAR(100),
    insurance_id VARCHAR(100),
    height DECIMAL(5,2) COMMENT 'Height in cm',
    weight DECIMAL(5,2) COMMENT 'Weight in kg',
    bmi DECIMAL(5,2) COMMENT 'Body Mass Index (kg/m2)',
    blood_pressure VARCHAR(20) COMMENT 'e.g., 120/80',
    temperature DECIMAL(4,1) COMMENT 'Temperature in Celsius',
    vitals_updated_at TIMESTAMP NULL COMMENT 'Last update of vitals',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_patient_id (patient_id),
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_created_at (created_at)
);

-- Recordings Table
CREATE TABLE IF NOT EXISTS recordings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    recording_type ENUM('audio', 'video', 'endoscopy', 'imaging') NOT NULL,
    recording_title VARCHAR(255) NOT NULL,
    recording_description TEXT,
    file_path VARCHAR(255),
    file_size INT,
    duration INT,
    recorded_by INT,
    recorded_at DATETIME,
    diagnosis TEXT,
    notes TEXT,
    status ENUM('pending', 'processed', 'archived') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_patient_id (patient_id),
    INDEX idx_recorded_at (recorded_at),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Appointments Table
CREATE TABLE IF NOT EXISTS appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT,
    appointment_date DATETIME NOT NULL,
    appointment_type VARCHAR(100),
    duration INT,
    status ENUM('scheduled', 'completed', 'cancelled', 'no-show') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id),
    INDEX idx_patient_id (patient_id),
    INDEX idx_doctor_id (doctor_id),
    INDEX idx_appointment_date (appointment_date),
    INDEX idx_status (status)
);

-- Analytics Table
CREATE TABLE IF NOT EXISTS analytics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    metric_type VARCHAR(100) NOT NULL,
    metric_name VARCHAR(255) NOT NULL,
    metric_value INT DEFAULT 0,
    measurement_date DATE NOT NULL,
    additional_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_metric_type (metric_type),
    INDEX idx_measurement_date (measurement_date)
);

-- Session/Activity Log Table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Insert default admin user (password: admin123)
-- Note: This password hash is for 'admin123'. If you need to regenerate, use: password_hash('admin123', PASSWORD_DEFAULT)
INSERT IGNORE INTO users (username, email, password_hash, full_name, role, is_active)
VALUES ('admin', 'admin@entclinic.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', TRUE);

-- Medicines Table
CREATE TABLE IF NOT EXISTS medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    dosage VARCHAR(100),
    unit VARCHAR(50),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_is_active (is_active)
);

-- Patient Visits Table
CREATE TABLE IF NOT EXISTS patient_visits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    visit_date DATETIME NOT NULL,
    visit_type VARCHAR(100),
    ent_type ENUM('ear', 'nose', 'throat', 'head_neck_tumor', 'lifestyle_medicine', 'misc') DEFAULT 'ear',
    chief_complaint TEXT,
    diagnosis TEXT,
    treatment_plan TEXT,
    prescription TEXT,
    notes TEXT,
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    blood_pressure VARCHAR(20),
    temperature DECIMAL(4,1),
    vitals_notes TEXT,
    doctor_id INT,
    doctor_name VARCHAR(150),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_patient_id (patient_id),
    INDEX idx_visit_date (visit_date)
);

INSERT IGNORE INTO medicines (name, dosage, unit) VALUES
('Amoxicillin', '500', 'mg'),
('Ibuprofen', '200', 'mg'),
('Paracetamol', '500', 'mg'),
('Cetirizine', '10', 'mg'),
('Omeprazole', '20', 'mg'),
('Metronidazole', '400', 'mg'),
('Cephalexin', '500', 'mg'),
('Aspirin', '81', 'mg'),
('Loratadine', '10', 'mg'),
('Dexamethasone', '0.5', 'mg'),
('Ambroxol', '30', 'mg'),
('Diphenhydramine', '25', 'mg'),
('Fluconazole', '150', 'mg'),
('Hydrocodone', '5', 'mg'),
('Itraconazole', '100', 'mg'),
('Ketoconazole', '200', 'mg'),
('Levofloxacin', '500', 'mg'),
('Mometasone', '50', 'mcg'),
('Nifedipine', '30', 'mg'),
('Oxymetazoline', '0.05', '%');

-- Prescription items table: stores prescribed medicines and instructions linked to visits
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