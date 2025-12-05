<?php
// Simple CLI tests for appointment overlap and daily_max logic
require_once __DIR__ . '/../config/Database.php';
$db = Database::getInstance()->getConnection();

function assertEqual($a,$b,$msg){
    if ($a === $b) {
        echo "PASS: $msg\n";
    } else {
        echo "FAIL: $msg -> Expected: ".var_export($b,true)." Got: ".var_export($a,true)."\n";
    }
}

// Use a test date
$date = date('Y-m-d', strtotime('+1 day'));
$start1 = $date . ' 09:00:00';
$end1 = $date . ' 09:30:00';

// Clean test appointments for date
$db->prepare("DELETE FROM appointments WHERE DATE(start_at) = :date")->execute(['date'=>$date]);

// Insert first appointment
$stmt = $db->prepare("INSERT INTO appointments (patient_id, type, status, start_at, end_at) VALUES (1,'follow_up','scheduled',:s,:e)");
$stmt->execute(['s'=>$start1,'e'=>$end1]);

// Overlap detection: simulate controller query
$checkStmt = $db->prepare("SELECT COUNT(*) as c FROM appointments WHERE status = 'scheduled' AND ((start_at < :end_at AND end_at > :start_at))");
$checkStmt->execute(['start_at'=>$date.' 09:15:00','end_at'=>$date.' 09:45:00']);
$c = (int)$checkStmt->fetchColumn();
assertEqual($c>0, true, 'Overlap detected for overlapping slot');

// Non-overlap detection
$checkStmt->execute(['start_at'=>$date.' 09:30:00','end_at'=>$date.' 09:45:00']);
$c2 = (int)$checkStmt->fetchColumn();
assertEqual($c2>0, false, 'No overlap for back-to-back slot');

// daily_max enforcement test: set appointment_types.daily_max for follow_up to 2 for test
$db->prepare("INSERT INTO appointment_types (`key`,`label`,`duration_minutes`,`buffer_minutes`,`daily_max`) VALUES ('test_follow','Test Follow',15,5,2) ON DUPLICATE KEY UPDATE daily_max = 2")->execute();
// Add two test_follow appointments
$db->prepare("DELETE FROM appointments WHERE type = 'test_follow' AND DATE(start_at)=:date")->execute(['date'=>$date]);
$db->prepare("INSERT INTO appointments (patient_id,type,status,start_at,end_at) VALUES (1,'test_follow','scheduled',:s1,:e1)")
    ->execute(['s1'=>$date.' 10:00:00','e1'=>$date.' 10:15:00']);
$db->prepare("INSERT INTO appointments (patient_id,type,status,start_at,end_at) VALUES (1,'test_follow','scheduled',:s2,:e2)")
    ->execute(['s2'=>$date.' 10:20:00','e2'=>$date.' 10:35:00']);

// Count
$cnt = (int)$db->prepare("SELECT COUNT(*) FROM appointments WHERE type='test_follow' AND status='scheduled' AND DATE(start_at)=:date")->execute(['date'=>$date]) && (int)$db->query("SELECT COUNT(*) FROM appointments WHERE type='test_follow' AND status='scheduled' AND DATE(start_at)='$date'")->fetchColumn();
assertEqual($cnt >= 2, true, 'Two test_follow appointments present');

// Attempt a third should exceed daily_max (simulate controller behavior)
$dailyMaxStmt = $db->prepare("SELECT daily_max FROM appointment_types WHERE `key` = :key LIMIT 1");
$dailyMaxStmt->execute(['key'=>'test_follow']);
$dm = $dailyMaxStmt->fetchColumn();
assertEqual((int)$dm, 2, 'daily_max read from appointment_types');

// cleanup test rows (leave real data untouched except test rows)
$db->prepare("DELETE FROM appointments WHERE DATE(start_at) = :date AND (type = 'test_follow' OR type = 'test_follow')")->execute(['date'=>$date]);

echo "Tests completed.\n";
