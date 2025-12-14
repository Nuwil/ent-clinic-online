<?php
require_once __DIR__ . '/Controller.php';

class AnalyticsController extends Controller {
    // GET /api/analytics?start=YYYY-MM-DD&end=YYYY-MM-DD&type=summary
    public function index() {
        $this->requireRole(['admin','doctor']);
        $start = isset($_GET['start']) ? trim($_GET['start']) : null;
        $end = isset($_GET['end']) ? trim($_GET['end']) : null;
        $type = $_GET['type'] ?? 'summary';

        // If client requested All Time (empty start), derive earliest visit date from DB
        try {
            $minDateStmt = $this->db->query("SELECT MIN(visit_date) FROM patient_visits");
            $minDate = $minDateStmt ? $minDateStmt->fetchColumn() : null;
        } catch (Exception $e) {
            $minDate = null;
        }

        if (empty($start)) {
            if ($minDate) {
                $sdt = new DateTime($minDate);
            } else {
                // Fallback to clinic first recorded date (e.g., 2025-11-25) so all-time returns something
                $sdt = new DateTime('2025-11-25');
            }
        } else {
            try { $sdt = new DateTime($start); } catch (Exception $e) { $sdt = new DateTime(date('Y-m-d', strtotime('-6 days'))); }
        }

        if (empty($end)) {
            $edt = new DateTime();
        } else {
            try { $edt = new DateTime($end); } catch (Exception $e) { $edt = new DateTime(); }
        }

        // If DB connectivity works, build real aggregates; otherwise fall back to mock
        try {
            $pdo = $this->db;

            // Summary
            $totalPatients = (int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'Completed' AND DATE(appointment_date) BETWEEN :start AND :end");
            $stmt->execute(['start' => $sdt->format('Y-m-d'), 'end' => $edt->format('Y-m-d')]);
            $appointmentsCompleted = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'Cancelled' AND DATE(appointment_date) BETWEEN :start AND :end");
            $stmt->execute(['start' => $sdt->format('Y-m-d'), 'end' => $edt->format('Y-m-d')]);
            $cancellations = (int)$stmt->fetchColumn();

            // Avg wait minutes: diff between appointment_date and visit_date where appointment_id exists
            $stmt = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(MINUTE, a.appointment_date, v.visit_date)) as avg_wait FROM patient_visits v JOIN appointments a ON v.appointment_id = a.id WHERE DATE(a.appointment_date) BETWEEN :start AND :end");
            $stmt->execute(['start' => $sdt->format('Y-m-d'), 'end' => $edt->format('Y-m-d')]);
            $avgWait = (float)$stmt->fetchColumn();
            if ($avgWait === null) $avgWait = 0;

            // Trend: visits per day (use patient_visits)
            $stmt = $pdo->prepare("SELECT DATE(visit_date) as day, COUNT(*) as c FROM patient_visits WHERE DATE(visit_date) BETWEEN :start AND :end GROUP BY DATE(visit_date) ORDER BY DATE(visit_date)");
            $stmt->execute(['start' => $sdt->format('Y-m-d'), 'end' => $edt->format('Y-m-d')]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $labels = [];
            $values = [];
            // Build full date range
            $period = new DatePeriod($sdt, new DateInterval('P1D'), (clone $edt)->modify('+1 day'));
            $map = [];
            foreach ($rows as $r) $map[$r['day']] = (int)$r['c'];
            foreach ($period as $d) {
                $k = $d->format('Y-m-d');
                $labels[] = $k;
                $values[] = $map[$k] ?? 0;
            }

            // Cancellation breakdown by appointment_type
            $stmt = $pdo->prepare("SELECT appointment_type, COUNT(*) as c FROM appointments WHERE status = 'Cancelled' AND DATE(appointment_date) BETWEEN :start AND :end GROUP BY appointment_type");
            $stmt->execute(['start' => $sdt->format('Y-m-d'), 'end' => $edt->format('Y-m-d')]);
            $cancellationsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $cLabels = [];
            $cData = [];
            foreach ($cancellationsRows as $r) { $cLabels[] = $r['appointment_type'] ?? 'Unspecified'; $cData[] = (int)$r['c']; }

            // Determine the forecast horizon based on selected range
            $rangeDays = max(1, (int)$edt->diff($sdt)->days + 1);
            if (empty($start)) {
                $forecastDays = 30; // All Time
            } elseif ($rangeDays <= 7) {
                $forecastDays = 7; // Today/This Week
            } elseif ($rangeDays <= 30) {
                $forecastDays = 30; // This Month / longer ranges
            } else {
                $forecastDays = 30; // Cap at 30 days
            }
            // Compute average visits per day in selected range
            $totalVisits = array_sum($values);
            $avgVisits = ($rangeDays > 0) ? round($totalVisits / $rangeDays, 2) : 0;

            // ENT distribution by ent_type (ensure consistent categories) - normalize incoming values and handle synonyms
            $entCategories = [
                'ear' => 'Ears',
                'nose' => 'Nose',
                'throat' => 'Throat',
                'head_neck_tumor' => 'Head & Neck',
                'lifestyle_medicine' => 'Lifestyle',
                'misc' => 'Misc / Others'
            ];

            // Synonyms map (map common label variations to canonical key)
            $synonyms = [
                'head & neck' => 'head_neck_tumor',
                'head/neck' => 'head_neck_tumor',
                'head_neck' => 'head_neck_tumor',
                'headneck' => 'head_neck_tumor',
                'head_neck_tumor' => 'head_neck_tumor',
                'lifestyle medicine' => 'lifestyle_medicine',
                'lifestyle_medicine' => 'lifestyle_medicine',
                'lifestyle' => 'lifestyle_medicine',
                'misc' => 'misc',
                'other' => 'misc',
                'misc/others' => 'misc'
            ];

            $stmt = $pdo->prepare("SELECT ent_type, COUNT(*) as c FROM patient_visits WHERE DATE(visit_date) BETWEEN :start AND :end GROUP BY ent_type");
            $stmt->execute(['start' => $sdt->format('Y-m-d'), 'end' => $edt->format('Y-m-d')]);
            $entRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $entMap = [];
            foreach ($entRows as $r) {
                $raw = strtolower(trim((string)$r['ent_type']));
                if (isset($synonyms[$raw])) {
                    $key = $synonyms[$raw];
                } else {
                    // fallback: if exact key exists, keep it; otherwise skip
                    $key = array_key_exists($raw, $entCategories) ? $raw : null;
                }
                if ($key) {
                    $entMap[$key] = (isset($entMap[$key]) ? $entMap[$key] : 0) + (int)$r['c'];
                }
            }
            $entLabels = [];
            $entData = [];
            foreach ($entCategories as $key => $label) {
                $entLabels[] = $label;
                $entData[] = $entMap[$key] ?? 0;
            }
            // Forecast: simple moving average baseline computed from the last min(14, range) days, but extend to forecastDays
            $lookback = max(1, min(14, count($values)));
            $lastValues = array_slice($values, max(0, count($values) - $lookback));
            $avgLast = $lastValues ? array_sum($lastValues) / count($lastValues) : 0;
            $forecastLabels = [];
            $forecastData = [];
            for ($i=1; $i<=$forecastDays; $i++) {
                $future = (new DateTime($edt->format('Y-m-d')))->modify("+$i days");
                $forecastLabels[] = $future->format('Y-m-d');
                // Add a small random noise for demo but keep smoothing
                $forecastData[] = max(0, (int)round($avgLast + rand(-2, 3)));
            }

            $summary = [
                'total_patients' => $totalPatients,
                'appointments_completed' => $appointmentsCompleted,
                'cancellations' => $cancellations,
                'avg_visits_per_day' => $avgVisits
            ];

            $payload = [
                'summary' => $summary,
                'visits_trend' => ['labels' => $labels, 'data' => $values],
                'cancellations_by_reason' => ['labels' => $cLabels, 'data' => $cData],
                'ent_distribution' => ['labels' => $entLabels, 'data' => $entData],
                'forecast' => ['labels' => $forecastLabels, 'data' => $forecastData]
            ];

            // Add a few prescriptive suggestions based on forecast
            $maxPred = max($forecastData);
            $suggestions = [];
            if ($maxPred > 40) {
                $suggestions[] = 'Increase available clinicians on peak days (forecast > 40 visits)';
            }
            if ($avgLast > 25) {
                $suggestions[] = 'Consider adding additional follow-up slots for the coming week';
            }
            if ($cancellations > 0) {
                $suggestions[] = 'Review cancellation reasons and confirm appointment reminders';
            }
            $payload['suggestions'] = $suggestions;

            // Simple caching via ETag - clients can revalidate
            $etag = sha1(json_encode($payload));
            header('Cache-Control: public, max-age=30');
            header('ETag: ' . $etag);
            if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
                http_response_code(304);
                exit;
            }

            $this->success($payload);

        } catch (Exception $e) {
            // When DB not available, fall back to mock
            $labels = [];
            $values = [];
            $period = new DatePeriod($sdt, new DateInterval('P1D'), $edt->modify('+1 day'));
            foreach ($period as $d) {
                $labels[] = $d->format('Y-m-d');
                $values[] = rand(8, 32);
            }
            $summary = [
                'total_patients' => rand(200, 500),
                'appointments_completed' => rand(180, 470),
                'cancellations' => rand(10, 60),
                'avg_visits_per_day' => round((array_sum($values) / max(1, count($values))), 2)
            ];
            $cancellationReasons = [
                'No-show' => rand(5, 20),
                'Scheduling conflict' => rand(1, 10),
                'Clinician unavailable' => rand(0, 5),
                'Other' => rand(0, 5)
            ];
            $forecastLabels = [];
            $forecastValues = [];
            for ($i = 1; $i <= 7; $i++) {
                $future = (new DateTime($end))->modify("+$i days");
                $forecastLabels[] = $future->format('Y-m-d');
                $forecastValues[] = max(0, (int)($values[count($values)-1] ?? 10) + rand(-2, 4));
            }
            $entCategories = ['Ears','Nose','Throat','Head & Neck','Lifestyle','Misc / Others'];
            $entValues = [];
            $entTotal = 0;
            foreach ($entCategories as $i => $k) { $v = rand(5, 40); $entValues[] = $v; $entTotal += $v; }

            $payload = [
                'summary' => $summary,
                'visits_trend' => ['labels' => $labels, 'data' => $values],
                'cancellations_by_reason' => ['labels' => array_keys($cancellationReasons), 'data' => array_values($cancellationReasons)],
                'ent_distribution' => ['labels' => $entCategories, 'data' => $entValues],
                'forecast' => ['labels' => $forecastLabels, 'data' => $forecastValues],
                'suggestions' => ['No DB connection - showing sample suggestions']
            ];
            header('Cache-Control: public, max-age=5');
            $this->success($payload);
        }
    }
}
