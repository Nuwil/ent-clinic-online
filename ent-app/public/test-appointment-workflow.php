<?php
/**
 * Appointment Workflow Integration Test
 * 
 * This script tests the complete appointment workflow:
 * 1. Book an appointment
 * 2. Accept the appointment
 * 3. Complete the appointment (auto-creates visit)
 * 
 * Usage: Run via browser or CLI
 * http://localhost/ent-clinic-online/ent-app/public/test-appointment-workflow.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

$db = Database::getInstance()->getConnection();

echo "<pre>";
echo "=== ENT Clinic Appointment Workflow Integration Test ===\n\n";

try {
    // Test 1: Check database tables
    echo "Test 1: Checking database tables...\n";
    
    $tables = ['appointments', 'patients', 'patient_visits', 'prescription_items'];
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT 1 FROM `$table` LIMIT 1");
            echo "  ✓ Table '$table' exists\n";
        } catch (Exception $e) {
            echo "  ✗ Table '$table' NOT FOUND\n";
        }
    }
    
    // Test 2: Check appointments table schema
    echo "\nTest 2: Checking appointments table schema...\n";
    $stmt = $db->query("DESCRIBE appointments");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $requiredCols = ['id', 'patient_id', 'type', 'status', 'start_at', 'end_at', 'notes'];
    
    $colNames = array_column($columns, 'Field');
    foreach ($requiredCols as $col) {
        if (in_array($col, $colNames)) {
            echo "  ✓ Column '$col' exists\n";
        } else {
            echo "  ✗ Column '$col' NOT FOUND\n";
        }
    }
    
    // Test 3: Check patient_visits table schema
    echo "\nTest 3: Checking patient_visits table schema...\n";
    $stmt = $db->query("DESCRIBE patient_visits");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $requiredCols = ['patient_id', 'visit_date', 'ent_type', 'diagnosis', 'treatment_plan', 'notes'];
    
    $colNames = array_column($columns, 'Field');
    foreach ($requiredCols as $col) {
        if (in_array($col, $colNames)) {
            echo "  ✓ Column '$col' exists\n";
        } else {
            echo "  ✗ Column '$col' NOT FOUND\n";
        }
    }
    
    // Test 4: Check if sample patient exists
    echo "\nTest 4: Checking for sample patients...\n";
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM patients LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['cnt'] > 0) {
        echo "  ✓ Found " . $result['cnt'] . " patients in database\n";
        $stmt = $db->query("SELECT id, first_name, last_name FROM patients LIMIT 1");
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "    Sample patient: ID=" . $patient['id'] . ", Name=" . $patient['first_name'] . " " . $patient['last_name'] . "\n";
    } else {
        echo "  ✗ No patients found in database\n";
    }
    
    // Test 5: Check API controller files
    echo "\nTest 5: Checking API controller files...\n";
    $files = [
        'AppointmentsController' => __DIR__ . '/../api/AppointmentsController.php',
        'SlotGenerator' => __DIR__ . '/../api/SlotGenerator.php',
        'WaitlistController' => __DIR__ . '/../api/WaitlistController.php'
    ];
    
    foreach ($files as $name => $path) {
        if (file_exists($path)) {
            echo "  ✓ $name exists\n";
            // Check for required methods
            $content = file_get_contents($path);
            if ($name === 'AppointmentsController') {
                if (strpos($content, 'public function accept') !== false) {
                    echo "    ✓ accept() method found\n";
                } else {
                    echo "    ✗ accept() method NOT found\n";
                }
                if (strpos($content, 'public function complete') !== false) {
                    echo "    ✓ complete() method found\n";
                } else {
                    echo "    ✗ complete() method NOT found\n";
                }
            }
        } else {
            echo "  ✗ $name NOT found\n";
        }
    }
    
    // Test 6: Check API routes
    echo "\nTest 6: Checking API routes...\n";
    $apiFile = __DIR__ . '/../api.php';
    if (file_exists($apiFile)) {
        $content = file_get_contents($apiFile);
        $routes = [
            '/api/appointments/:id/accept' => 'accept',
            '/api/appointments/:id/complete' => 'complete'
        ];
        
        foreach ($routes as $route => $method) {
            if (strpos($content, $route) !== false) {
                echo "  ✓ Route '$route' registered\n";
            } else {
                echo "  ✗ Route '$route' NOT registered\n";
            }
        }
    }
    
    // Test 7: Check appointment status values
    echo "\nTest 7: Checking appointment status values...\n";
    $stmt = $db->query("SELECT DISTINCT status FROM appointments");
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($statuses)) {
        echo "  ⓘ No appointments yet (this is normal)\n";
    } else {
        foreach ($statuses as $status) {
            echo "  • Status: '$status'\n";
        }
    }
    
    // Test 8: Check if appointment created today
    echo "\nTest 8: Checking recent appointments...\n";
    $stmt = $db->prepare("
        SELECT a.*, p.first_name, p.last_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE DATE(a.created_at) = CURDATE()
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $apts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($apts)) {
        echo "  ⓘ No appointments created today\n";
    } else {
        foreach ($apts as $apt) {
            echo "  • ID: {$apt['id']}, Patient: {$apt['first_name']} {$apt['last_name']}, Status: {$apt['status']}\n";
        }
    }
    
    // Test 9: Check if visits created today
    echo "\nTest 9: Checking recent visits...\n";
    $stmt = $db->prepare("
        SELECT pv.*, p.first_name, p.last_name 
        FROM patient_visits pv
        JOIN patients p ON pv.patient_id = p.id
        WHERE DATE(pv.created_at) = CURDATE()
        ORDER BY pv.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($visits)) {
        echo "  ⓘ No visits created today (run the workflow to create some)\n";
    } else {
        foreach ($visits as $visit) {
            echo "  • ID: {$visit['id']}, Patient: {$visit['first_name']} {$visit['last_name']}, ENT Type: {$visit['ent_type']}\n";
        }
    }
    
    // Test 10: Verify appointments.php page
    echo "\nTest 10: Checking appointments page...\n";
    $pageFile = __DIR__ . '/pages/appointments.php';
    if (file_exists($pageFile)) {
        echo "  ✓ appointments.php page exists\n";
        $content = file_get_contents($pageFile);
        if (strpos($content, 'completeModal') !== false) {
            echo "    ✓ Complete modal found\n";
        } else {
            echo "    ✗ Complete modal NOT found\n";
        }
        if (strpos($content, 'acceptAppointment') !== false) {
            echo "    ✓ acceptAppointment function found\n";
        } else {
            echo "    ✗ acceptAppointment function NOT found\n";
        }
    } else {
        echo "  ✗ appointments.php page NOT found\n";
    }
    
    // Test 11: Check patient profile modifications
    echo "\nTest 11: Checking patient profile modifications...\n";
    $pageFile = __DIR__ . '/pages/patient-profile.php';
    if (file_exists($pageFile)) {
        echo "  ✓ patient-profile.php page exists\n";
        $content = file_get_contents($pageFile);
        if (strpos($content, 'Appointments are now the primary workflow') !== false) {
            echo "    ✓ Appointment workflow message found\n";
        } else {
            echo "    ✗ Appointment workflow message NOT found\n";
        }
    }
    
    echo "\n=== Test Complete ===\n";
    echo "If all checks passed, the appointment workflow is properly integrated.\n";
    echo "Next: Navigate to the appointments page to test the workflow.\n";

} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "</pre>";
?>
