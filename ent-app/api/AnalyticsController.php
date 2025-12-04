<?php
/**
 * Analytics API Controller with Hybrid Forecasting
 * 
 * Implements a hybrid forecasting approach combining:
 * 1. Weekly Seasonality Factor (SF): Average visits per day of week (normalized)
 * 2. Trailing Trend: Simple Moving Average (SMA) or Simple Linear Regression (SLR)
 * 3. Final Forecast: Forecast = (Trailing Trend Base) × (Day-of-Week SF)
 */

require_once __DIR__ . '/Controller.php';

class AnalyticsController extends Controller
{
    /**
     * Calculate Simple Moving Average
     */
    private function calculateSMA($data, $period = 7)
    {
        if (count($data) < $period) {
            return array_sum($data) / max(1, count($data));
        }
        $recentData = array_slice($data, -$period);
        return array_sum($recentData) / count($recentData);
    }

    /**
     * Calculate Simple Linear Regression (SLR)
     * Returns [intercept, slope]
     */
    private function calculateSLR($data)
    {
        $n = count($data);
        if ($n <= 1) {
            return [array_sum($data) / max(1, $n), 0.0];
        }

        $sumX = $sumY = $sumXY = $sumX2 = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $x = (float)$i;
            $y = (float)$data[$i];
            $sumX  += $x;
            $sumY  += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $denominator = $n * $sumX2 - $sumX * $sumX;
        $slope = 0.0;
        $intercept = $sumY / max(1, $n);

        if (abs($denominator) > 0.0001) {
            $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
            $intercept = ($sumY - $slope * $sumX) / $n;
        }

        return [$intercept, $slope];
    }

    // Main endpoint
    public function index()
    {
        try {
            if (session_status() === PHP_SESSION_NONE) @session_start();

            // Only admin/doctor
            $this->requireRole(['admin', 'doctor']);

            // Input params: optional start_date, end_date (YYYY-MM-DD)
            $start = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : null;
            $end = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : null;

            // Normalize range: if missing, use last 30 days
            if (!$end && $start) $end = date('Y-m-d');
            if (!$start && $end) $start = date('Y-m-d', strtotime($end . ' -29 days'));
            if (!$start && !$end) {
                $end = date('Y-m-d');
                $start = date('Y-m-d', strtotime($end . ' -29 days'));
            }

            // Validate dates
            $startDT = DateTime::createFromFormat('Y-m-d', $start);
            $endDT = DateTime::createFromFormat('Y-m-d', $end);
            if (!$startDT || !$endDT) {
                $this->error('Invalid date format. Use YYYY-MM-DD', 400);
            }
            if ($startDT > $endDT) {
                $this->error('start_date must be before or equal to end_date', 400);
            }

            // Compute number of days in range (inclusive)
            $days = (int)$startDT->diff($endDT)->days + 1;

            // Determine forecast horizon adaptively: between 0..14
            // If the range is 1 day -> no forecast (horizon 0). Otherwise horizon = min(14, max(1, round(days/2)))
            $horizon = 0;
            if ($days > 1) {
                $horizon = min(14, max(1, (int)round($days / 2)));
            }

            // ----- Descriptive: aggregates within the date range -----
            $descriptive = [
                'start_date' => $start,
                'end_date' => $end,
                'days' => $days,
                'total_visits' => 0,
                'ent_distribution' => ['ear' => 0, 'nose' => 0, 'throat' => 0, 'head_neck_tumor' => 0, 'lifestyle_medicine' => 0, 'misc' => 0],
                'daily_series' => [], // [{date, count}, ...]
                'weekly_counts' => [], // dow=>count
            ];

            // Build daily series between dates with zero-fill
            $period = new DatePeriod($startDT, new DateInterval('P1D'), (clone $endDT)->modify('+1 day'));
            $dates = [];
            foreach ($period as $d) {
                $dates[$d->format('Y-m-d')] = 0;
            }

            // Query visits in range
            $stmt = $this->db->prepare(
                "SELECT DATE(visit_date) AS d, ent_type, COUNT(*) AS c, DAYOFWEEK(visit_date) AS dow
                 FROM patient_visits
                 WHERE DATE(visit_date) BETWEEN ? AND ?
                 GROUP BY DATE(visit_date), ent_type, DAYOFWEEK(visit_date)");
            $stmt->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
            $rows = $stmt->fetchAll();

            $weeklyCounts = array_fill(1, 7, 0);
            foreach ($rows as $r) {
                $d = $r['d'];
                $cnt = (int)$r['c'];
                if (isset($dates[$d])) $dates[$d] += $cnt;
                $type = $r['ent_type'] ?? 'ear';
                if (isset($descriptive['ent_distribution'][$type])) {
                    $descriptive['ent_distribution'][$type] += $cnt;
                }
                $dow = (int)$r['dow'];
                if ($dow >= 1 && $dow <= 7) $weeklyCounts[$dow] += $cnt;
            }

            $descriptive['daily_series'] = array_map(function($k, $v){ return ['date'=>$k,'count'=> (int)$v]; }, array_keys($dates), array_values($dates));
            $descriptive['total_visits'] = array_sum(array_values($dates));
            $descriptive['weekly_counts'] = $weeklyCounts;

            // ----- Predictive: generate forecast based on the daily counts in the selected range -----
            $predictive = [
                'horizon' => $horizon,
                'forecast_rows' => [],
                'forecast_stats' => ['baseLevel'=>0,'trendPerDay'=>0]
            ];

            if ($horizon > 0) {
                // Prepare dailyCounts array (ordered) for trend calculations
                $dailyCounts = array_values(array_map(function($r){ return (int)$r['count']; }, $descriptive['daily_series']));

                // If no meaningful data, fall back to global averages
                if (array_sum($dailyCounts) === 0) {
                    // Use last 90 days global average per day for base
                    $gstmt = $this->db->query("SELECT DATE(visit_date) AS d, COUNT(*) AS c FROM patient_visits GROUP BY DATE(visit_date) ORDER BY d DESC LIMIT 90");
                    $grows = $gstmt->fetchAll();
                    $gcounts = array_map(function($r){ return (int)$r['c']; }, $grows);
                    $dailyCounts = $gcounts ?: [1];
                }

                // Calculate stats: use SLR if enough points, else SMA
                $n = count($dailyCounts);
                if ($n >= 7) {
                    list($intercept, $slope) = $this->calculateSLR($dailyCounts);
                    $baseLevel = max(1.0, $intercept + $slope * ($n - 1));
                    $trendPerDay = $slope;
                } else {
                    $baseLevel = max(1.0, $this->calculateSMA($dailyCounts, min(7,$n)));
                    $trendPerDay = 0.0;
                }
                $smoothed = max(1.0, $this->calculateSMA($dailyCounts, min(7, $n)));
                $forecastStats = ['baseLevel' => max(1.0, ($baseLevel + $smoothed)/2.0), 'trendPerDay' => $trendPerDay, 'n' => $n];

                // Seasonality: compute from global history for stability
                $sfStmt = $this->db->query("SELECT DAYOFWEEK(visit_date) AS dow, COUNT(*) AS c FROM patient_visits GROUP BY DAYOFWEEK(visit_date)");
                $sfRows = $sfStmt->fetchAll();
                $weeklyAll = array_fill(1,7,0); $total=0;
                foreach ($sfRows as $r) { $d=(int)$r['dow']; $c=(int)$r['c']; if ($d>=1 && $d<=7) { $weeklyAll[$d]=$c; $total += $c; } }
                $overallDaily = $total>0 ? $total/7.0 : 1.0; $seasonality = array_fill(1,7,1.0);
                for ($dow=1;$dow<=7;$dow++){ $seasonality[$dow] = $overallDaily>0 ? ($weeklyAll[$dow]/$overallDaily) : 1.0; }
                $avgSf = array_sum($seasonality)/7.0; if ($avgSf>0) for ($dow=1;$dow<=7;$dow++) $seasonality[$dow] /= $avgSf;

                // Generate forecast rows using hybrid approach
                $weekdayLabels = [1=>'Sunday',2=>'Monday',3=>'Tuesday',4=>'Wednesday',5=>'Thursday',6=>'Friday',7=>'Saturday'];
                for ($i=0;$i<$horizon;$i++) {
                    $date = date('Y-m-d', strtotime("+" . ($i+1) . " days"));
                    $mysqlDow = (int)date('w', strtotime($date)) + 1; if ($mysqlDow==8) $mysqlDow=1;
                    $sf = isset($seasonality[$mysqlDow]) ? $seasonality[$mysqlDow] : 1.0;
                    $t = $i+1; $baseT = max(1.0, $forecastStats['baseLevel'] + $forecastStats['trendPerDay'] * $t);
                    $val = max(1.0, round($baseT * $sf, 2));
                    $predictive['forecast_rows'][] = ['date'=>$date,'label'=>$weekdayLabels[$mysqlDow]??'Day','dow'=>$mysqlDow,'sf'=>round($sf,3),'base'=>round($baseT,2),'value'=>$val];
                }
                $predictive['forecast_stats'] = $forecastStats;
            }

            // Return consolidated payload
            $this->success([
                'descriptive' => $descriptive,
                'predictive' => $predictive
            ]);
        } catch (Exception $e) {
            $this->error('Analytics error: ' . $e->getMessage(), 500);
        }
    }
}
