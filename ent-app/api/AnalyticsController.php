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

    /**
     * Main analytics endpoint with hybrid forecasting
     */
    public function index()
    {
        try {
            // Ensure session is started for auth check
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }

            // Only doctors and admins should access analytics
            $user = $this->getApiUser();
            if (!$user || empty($user['role'])) {
                $this->error('Unauthorized: Admin or Doctor role required', 403);
            }
            if (!in_array($user['role'], ['admin', 'doctor'])) {
                $this->error('Forbidden: Only admins and doctors can access analytics', 403);
            }

            // Parameters
            $trendDays = isset($_GET['trend_days']) ? max(7, (int)$_GET['trend_days']) : 90;
            $minRegressionDays = isset($_GET['min_regression_days']) ? max(7, (int)$_GET['min_regression_days']) : 14;
            $horizon = isset($_GET['horizon']) ? max(1, (int)$_GET['horizon']) : 7;

            // ===== STEP 1: ENT Distribution (all-time) =====
            $distribution = $this->getENTDistribution();

            // ===== STEP 2: Weekly visits (last 7 days) =====
            $weekly = $this->getWeeklyVisits();

            // ===== STEP 3: Weekly Seasonality Factors (all history) =====
            $seasonality = $this->calculateWeeklySeasonality();

            // ===== STEP 4: Trailing Trend Data =====
            $dailySeries = $this->getDailyTrendData($trendDays);
            $dailyCounts = array_map(function ($item) {
                return (int)$item['count'];
            }, $dailySeries);

            // ===== STEP 5: Calculate Base Level and Trend =====
            $forecastStats = $this->calculateForecastStats($dailyCounts, $minRegressionDays);

            // ===== STEP 6: Generate Forecast (Hybrid Approach) =====
            $forecastRows = $this->generateHybridForecast(
                $seasonality,
                $forecastStats,
                $horizon
            );

            // ===== STEP 7: Get Total Visits =====
            $totalVisitsAll = $this->getTotalVisitsCount();

            // Return success response
            $this->success([
                'ent_distribution' => $distribution,
                'weekly_visits'    => $weekly,
                'seasonality'      => $seasonality,
                'daily_counts'     => $dailyCounts,
                'daily_series'     => $dailySeries,
                'forecast_rows'    => $forecastRows,
                'forecast_stats'   => $forecastStats,
                'total_visits_all' => $totalVisitsAll
            ]);
        } catch (Exception $e) {
            $this->error('Analytics error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get ENT case distribution (all time)
     */
    private function getENTDistribution()
    {
        try {
            $stmt = $this->db->query("SELECT ent_type, COUNT(*) AS count FROM patient_visits GROUP BY ent_type");
            $rows = $stmt->fetchAll();
            $distribution = ['ear' => 0, 'nose' => 0, 'throat' => 0];
            foreach ($rows as $r) {
                $type = $r['ent_type'] ?? 'ear';
                if (isset($distribution[$type])) {
                    $distribution[$type] = (int)$r['count'];
                }
            }
            return $distribution;
        } catch (Exception $e) {
            return ['ear' => 0, 'nose' => 0, 'throat' => 0];
        }
    }

    /**
     * Get weekly visits (last 7 days)
     */
    private function getWeeklyVisits()
    {
        try {
            $today = date('Y-m-d');
            $startWeekly = date('Y-m-d', strtotime($today . ' -6 days'));
            
            $stmt = $this->db->prepare(
                "SELECT DATE(visit_date) AS d, COUNT(*) AS count
                 FROM patient_visits
                 WHERE DATE(visit_date) BETWEEN ? AND ?
                 GROUP BY DATE(visit_date)
                 ORDER BY d"
            );
            $stmt->execute([$startWeekly, $today]);
            
            $weekly = [];
            for ($i = 6; $i >= 0; $i--) {
                $day = date('Y-m-d', strtotime($today . " -{$i} days"));
                $weekly[$day] = 0;
            }
            
            foreach ($stmt->fetchAll() as $r) {
                $d = $r['d'];
                if (isset($weekly[$d])) {
                    $weekly[$d] = (int)$r['count'];
                }
            }
            return $weekly;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Calculate weekly seasonality factors from all history
     * Returns normalized day-of-week factors (1=Monday, 7=Sunday per MySQL DAYOFWEEK)
     */
    private function calculateWeeklySeasonality()
    {
        try {
            // Get visit counts by day of week (MySQL: 1=Sunday, 2=Monday, ..., 7=Saturday)
            $stmt = $this->db->query(
                "SELECT DAYOFWEEK(visit_date) AS dow, COUNT(*) AS count
                 FROM patient_visits
                 GROUP BY DAYOFWEEK(visit_date)"
            );
            
            $weeklyAll = array_fill(1, 7, 0);
            $totalWeeklyAll = 0;
            
            foreach ($stmt->fetchAll() as $row) {
                $dow = (int)$row['dow'];
                $cnt = (int)$row['count'];
                if ($dow >= 1 && $dow <= 7) {
                    $weeklyAll[$dow] = $cnt;
                    $totalWeeklyAll += $cnt;
                }
            }

            // Calculate average visits per day
            $overallDaily = $totalWeeklyAll > 0 ? $totalWeeklyAll / 7.0 : 1.0;

            // Calculate seasonality factors
            $seasonality = array_fill(1, 7, 1.0);
            for ($dow = 1; $dow <= 7; $dow++) {
                $seasonality[$dow] = $overallDaily > 0 ? ($weeklyAll[$dow] / $overallDaily) : 1.0;
            }

            // Normalize to mean 1.0 for pure relative weights
            $avgSf = array_sum($seasonality) / 7.0;
            if ($avgSf > 0.0001) {
                for ($dow = 1; $dow <= 7; $dow++) {
                    $seasonality[$dow] = $seasonality[$dow] / $avgSf;
                }
            }

            return $seasonality;
        } catch (Exception $e) {
            // Return neutral seasonality if query fails
            return array_fill(1, 7, 1.0);
        }
    }

    /**
     * Get daily trend data for last N days
     */
    private function getDailyTrendData($trendDays)
    {
        try {
            $today = date('Y-m-d');
            $trendStart = date('Y-m-d', strtotime($today . ' -' . max(1, $trendDays - 1) . ' days'));

            $stmt = $this->db->prepare(
                "SELECT DATE(visit_date) AS d, COUNT(*) AS count
                 FROM patient_visits
                 WHERE DATE(visit_date) >= ?
                 GROUP BY DATE(visit_date)
                 ORDER BY d ASC"
            );
            $stmt->execute([$trendStart]);

            $dailySeries = [];
            foreach ($stmt->fetchAll() as $row) {
                $dailySeries[] = ['date' => $row['d'], 'count' => (int)$row['count']];
            }

            return $dailySeries;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Calculate forecast statistics (base level and trend)
     */
    private function calculateForecastStats($dailyCounts, $minRegressionDays)
    {
        $stats = [
            'baseLevel'   => 1.0,  // Default to 1 visit minimum
            'trendPerDay' => 0.0,
            'n'           => count($dailyCounts),
            'method'      => 'hybrid_sma_slr',
            'description' => 'Base: SMA(7) + Trend: SLR, normalized by weekly seasonality'
        ];

        $n = count($dailyCounts);
        if ($n === 0) {
            // No data - return default minimum forecast
            $stats['baseLevel'] = 1.0;
            return $stats;
        }

        // If we have enough data, use SLR; otherwise use simple average
        if ($n >= $minRegressionDays) {
            list($intercept, $slope) = $this->calculateSLR($dailyCounts);
            $baseLevel = max(1.0, $intercept + $slope * ($n - 1));
            $trendPerDay = $slope;
        } else {
            // Fall back to SMA if not enough data
            $baseLevel = max(1.0, $this->calculateSMA($dailyCounts, min(7, $n)));
            $trendPerDay = 0.0;
        }

        // Smooth the base level using 7-day SMA
        $smoothedBase = max(1.0, $this->calculateSMA($dailyCounts, min(7, $n)));
        $stats['baseLevel'] = max(1.0, ($baseLevel + $smoothedBase) / 2.0);
        $stats['trendPerDay'] = $trendPerDay;

        return $stats;
    }

    /**
     * Generate hybrid forecast for the horizon
     * Formula: Forecast = (Trailing Trend Base) × (Day-of-Week SF)
     */
    private function generateHybridForecast($seasonality, $forecastStats, $horizon)
    {
        $weekdayLabels = [
            1 => 'Sunday',
            2 => 'Monday',
            3 => 'Tuesday',
            4 => 'Wednesday',
            5 => 'Thursday',
            6 => 'Friday',
            7 => 'Saturday'
        ];

        $forecastRows = [];
        
        for ($i = 0; $i < $horizon; $i++) {
            $date = date('Y-m-d', strtotime("+" . ($i + 1) . " days"));
            // MySQL DAYOFWEEK: 1=Sunday, 2=Monday, ..., 7=Saturday
            $mysqlDow = (int)date('w', strtotime($date)) + 1;
            if ($mysqlDow === 8) $mysqlDow = 1; // Handle Sunday edge case

            // Get seasonality factor for this day
            $sf = isset($seasonality[$mysqlDow]) ? $seasonality[$mysqlDow] : 1.0;

            // Calculate base forecast with trend
            $t = $i + 1;
            $baseT = max(1.0, $forecastStats['baseLevel'] + $forecastStats['trendPerDay'] * $t);

            // Apply seasonality factor
            $forecastValue = round($baseT * $sf, 2);

            $forecastRows[] = [
                'label' => $weekdayLabels[$mysqlDow] ?? 'Day',
                'date' => $date,
                'dow' => $mysqlDow,
                'sf' => round($sf, 3),
                'base' => round($baseT, 2),
                'value' => max(1.0, $forecastValue),
                'method' => 'hybrid'
            ];
        }

        return $forecastRows;
    }

    /**
     * Get total visits count
     */
    private function getTotalVisitsCount()
    {
        try {
            $row = $this->db->query("SELECT COUNT(*) AS c FROM patient_visits")->fetch();
            return isset($row['c']) ? (int)$row['c'] : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}
