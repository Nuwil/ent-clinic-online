<?php
/**
 * Analytics API Controller
 */

require_once __DIR__ . '/Controller.php';

class AnalyticsController extends Controller
{
    // Returns ENT distribution and weekly visits
    public function index()
    {
        try {
            // Parameters (optional)
            $trendDays = isset($_GET['trend_days']) ? (int)$_GET['trend_days'] : 90; // trailing window for SMA / regression
            $minRegression = isset($_GET['min_regression_days']) ? (int)$_GET['min_regression_days'] : 14;
            $horizon = isset($_GET['horizon']) ? (int)$_GET['horizon'] : 7;
            $smoothing = isset($_GET['smoothing']) ? strtolower(trim($_GET['smoothing'])) : 'none'; // none | ma | exp

            // helper: simple winsorize (clip extremes) to reduce outlier impact
            $winsorize = function(array $arr, $p = 0.05) {
                $n = count($arr);
                if ($n === 0) return $arr;
                $sorted = $arr;
                sort($sorted);
                $loIdx = (int)floor($p * $n);
                $hiIdx = (int)ceil((1 - $p) * $n) - 1;
                $lo = $sorted[max(0, min($loIdx, $n-1))];
                $hi = $sorted[max(0, min($hiIdx, $n-1))];
                return array_map(function($v) use ($lo, $hi) {
                    if ($v < $lo) return $lo;
                    if ($v > $hi) return $hi;
                    return $v;
                }, $arr);
            };

            // helper: simple EWMA smoothing
            $ewma = function(array $arr, $alpha = 0.25) {
                $out = [];
                $s = null;
                foreach ($arr as $v) {
                    if ($s === null) $s = $v;
                    else $s = $alpha * $v + (1 - $alpha) * $s;
                    $out[] = $s;
                }
                return $out;
            };

            // helper: moving average (trailing window)
            $movingAverage = function(array $arr, $w = 3) {
                $n = count($arr);
                if ($n === 0) return $arr;
                $out = [];
                for ($i = 0; $i < $n; $i++) {
                    $start = max(0, $i - $w + 1);
                    $slice = array_slice($arr, $start, $i - $start + 1);
                    $out[] = array_sum($slice) / max(1, count($slice));
                }
                return $out;
            };

            // 1) ENT distribution (ear/nose/throat)
            $stmt = $this->db->query("SELECT ent_type, COUNT(*) as count FROM patient_visits GROUP BY ent_type");
            $rows = $stmt->fetchAll();
            $distribution = ['ear' => 0, 'nose' => 0, 'throat' => 0];
            foreach ($rows as $r) {
                $type = $r['ent_type'] ?? 'ear';
                if (!isset($distribution[$type])) $distribution[$type] = 0;
                $distribution[$type] = (int)$r['count'];
            }

            // 2) Weekly visits - last 7 days grouped by date
            $cutoffDate = '2025-12-01'; // Only include data from December 1 onwards
            $stmt2 = $this->db->prepare("SELECT DATE(visit_date) as d, COUNT(*) as count FROM patient_visits WHERE visit_date >= ? AND DATE(visit_date) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(visit_date) ORDER BY DATE(visit_date)");
            $stmt2->execute([$cutoffDate]);
            $rows2 = $stmt2->fetchAll();

            $weekly = [];
            for ($i = 6; $i >= 0; $i--) {
                $day = date('Y-m-d', strtotime("-{$i} days"));
                $weekly[$day] = 0;
            }
            foreach ($rows2 as $r) {
                $d = $r['d'];
                $weekly[$d] = (int)$r['count'];
            }

            // 3) Seasonality factors (SF) using all historical data: avg visits per weekday
            $weeklyAll = array_fill(1, 7, 0);
            $totalWeeklyAll = 0;
            try {
                $stmt3 = $this->db->query("SELECT DAYOFWEEK(visit_date) as dow, COUNT(*) as count FROM patient_visits GROUP BY DAYOFWEEK(visit_date)");
                foreach ($stmt3->fetchAll() as $row) {
                    $dow = (int)$row['dow'];
                    $cnt = (int)$row['count'];
                    if ($dow >= 1 && $dow <= 7) {
                        $weeklyAll[$dow] = $cnt;
                        $totalWeeklyAll += $cnt;
                    }
                }
            } catch (PDOException $e) {
                // ignore
            }
            $overallDaily = $totalWeeklyAll > 0 ? $totalWeeklyAll / 7.0 : 0;
            $seasonality = array_fill(1, 7, 1.0);
            for ($dow = 1; $dow <= 7; $dow++) {
                $seasonality[$dow] = $overallDaily > 0 ? ($weeklyAll[$dow] / $overallDaily) : 1.0;
            }
            // normalize so mean(sf) == 1.0
            $avgSf = array_sum($seasonality) / 7.0 ?: 1.0;
            for ($dow = 1; $dow <= 7; $dow++) $seasonality[$dow] = $seasonality[$dow] / $avgSf;

            // 4) Trailing trend: get daily counts for last $trendDays days (preserve dates)
            // Filter to only include data from December 1, 2025 onwards
            $dailySeries = []; // array of ['date'=>'YYYY-MM-DD','count'=>int]
            $dailyCounts = [];
            $trendStart = date('Y-m-d', strtotime("-" . max(1, $trendDays - 1) . " days"));
            $cutoffDate = '2025-12-01'; // Only include data from December 1 onwards
            $effectiveStart = max($trendStart, $cutoffDate);
            try {
                $stmt4 = $this->db->prepare("SELECT DATE(visit_date) as d, COUNT(*) as count FROM patient_visits WHERE visit_date >= ? GROUP BY DATE(visit_date) ORDER BY d ASC");
                $stmt4->execute([$effectiveStart]);
                foreach ($stmt4->fetchAll() as $row) {
                    $dailySeries[] = ['date' => $row['d'], 'count' => (int)$row['count']];
                    $dailyCounts[] = (int)$row['count'];
                }
            } catch (PDOException $e) {
                $dailySeries = [];
                $dailyCounts = [];
            }

            $forecastStats = ['baseLevel' => 0.0, 'trendPerDay' => 0.0, 'n' => count($dailyCounts), 'smoothing' => $smoothing, 'mae' => null, 'rmse' => null, 'backtest_h' => 0, 'method' => 'linear_sma'];
            $n = count($dailyCounts);
            if ($n > 0) {
                // optionally apply winsorization + smoothing before fitting (but keep original dailySeries for reporting)
                $processedCounts = $dailyCounts;
                // winsorize to reduce extreme outliers
                $processedCounts = $winsorize($processedCounts, 0.03);
                if ($smoothing === 'exp') {
                    $processedCounts = $ewma($processedCounts, 0.25);
                } elseif ($smoothing === 'ma') {
                    $processedCounts = $movingAverage($processedCounts, 3);
                }

                $sma = array_sum($processedCounts) / $n;
                if ($n >= $minRegression) {
                    // fit linear regression on processedCounts
                    $sumX = $sumY = $sumXY = $sumX2 = 0.0;
                    for ($i = 0; $i < $n; $i++) {
                        $x = (float)$i;
                        $y = (float)$processedCounts[$i];
                        $sumX += $x;
                        $sumY += $y;
                        $sumXY += $x * $y;
                        $sumX2 += $x * $x;
                    }
                    $den = $n * $sumX2 - $sumX * $sumX;
                    if ($den != 0.0) {
                        $slope = ($n * $sumXY - $sumX * $sumY) / $den;
                        $intercept = ($sumY - $slope * $sumX) / $n;
                        $currentPred = $intercept + $slope * ($n - 1);
                        $forecastStats['baseLevel'] = max(0.0, ($sma + $currentPred) / 2.0);
                        $forecastStats['trendPerDay'] = $slope;
                        $forecastStats['method'] = 'linear_regression_on_processed';
                    } else {
                        $forecastStats['baseLevel'] = max(0.0, $sma);
                    }
                } else {
                    $forecastStats['baseLevel'] = max(0.0, $sma);
                }

                // Backtesting: holdout last H days where H = min(14, floor(n*0.15)) but ensure training size >= minRegression
                $H = min(14, max(1, (int)floor($n * 0.15)));
                while ($n - $H < $minRegression && $H > 0) { $H--; }
                if ($H > 0 && $n - $H >= 1) {
                    $m = $n - $H; // training size
                    // training series from processedCounts
                    $train = array_slice($processedCounts, 0, $m);
                    $trainN = count($train);
                    $ma_train = array_sum($train) / max(1, $trainN);
                    // fit regression on train
                    $sumX = $sumY = $sumXY = $sumX2 = 0.0;
                    for ($i = 0; $i < $trainN; $i++) {
                        $x = (float)$i;
                        $y = (float)$train[$i];
                        $sumX += $x; $sumY += $y; $sumXY += $x * $y; $sumX2 += $x * $x;
                    }
                    $den = $trainN * $sumX2 - $sumX * $sumX;
                    if ($den != 0.0) {
                        $slope_t = ($trainN * $sumXY - $sumX * $sumY) / $den;
                        $intercept_t = ($sumY - $slope_t * $sumX) / $trainN;
                        $currentPred_t = $intercept_t + $slope_t * ($trainN - 1);
                        $base_train = max(0.0, ($ma_train + $currentPred_t) / 2.0);
                    } else {
                        $base_train = max(0.0, $ma_train);
                        $slope_t = 0.0;
                        $intercept_t = $ma_train;
                    }

                    // compute predictions for H holdout days using weekday seasonality computed earlier
                    $lastDate = $dailySeries[$n - 1]['date'];
                    $preds = [];
                    $actuals = [];
                    for ($t = 1; $t <= $H; $t++) {
                        $d = date('Y-m-d', strtotime("+" . $t . " days", strtotime($lastDate)));
                        $dow = (int)date('N', strtotime($d));
                        $mysqlDow = $dow === 7 ? 1 : $dow + 1;
                        $sf = $seasonality[$mysqlDow] ?? 1.0;
                        $pred = $base_train * $sf;
                        $preds[] = $pred;
                        // actual from original dailySeries (the withheld tail)
                        $actuals[] = isset($dailySeries[$m + ($t - 1)]['count']) ? (int)$dailySeries[$m + ($t - 1)]['count'] : 0;
                    }
                    // compute MAE and RMSE
                    $sumAbs = 0.0; $sumSq = 0.0; $cnt = count($preds);
                    for ($i = 0; $i < $cnt; $i++) {
                        $err = $preds[$i] - $actuals[$i];
                        $sumAbs += abs($err);
                        $sumSq += $err * $err;
                    }
                    $mae = $cnt ? ($sumAbs / $cnt) : null;
                    $rmse = $cnt ? sqrt($sumSq / $cnt) : null;
                    $forecastStats['mae'] = $mae !== null ? round($mae, 3) : null;
                    $forecastStats['rmse'] = $rmse !== null ? round($rmse, 3) : null;
                    $forecastStats['backtest_h'] = $cnt;
                }
            }

            // 5) Build forecast rows for next $horizon days using hybrid method:
            // Forecast = (Trailing Trend Base) * (Day of Week SF)
            $weekdayLabels = [2 => 'Monday',3=>'Tuesday',4=>'Wednesday',5=>'Thursday',6=>'Friday',7=>'Saturday',1=>'Sunday'];
            $forecastRows = [];
            for ($i = 0; $i < $horizon; $i++) {
                $date = date('Y-m-d', strtotime("+" . ($i+1) . " days"));
                $dow = (int)date('N', strtotime($date)); // 1 (Mon) .. 7 (Sun)
                // convert to MySQL DAYOFWEEK mapping where 1 = Sunday
                $mysqlDow = $dow === 7 ? 1 : $dow + 1;
                $sf = $seasonality[$mysqlDow] ?? 1.0;
                $value = $forecastStats['baseLevel'] * $sf;
                $forecastRows[] = [
                    'label' => $weekdayLabels[$mysqlDow] ?? date('D', strtotime($date)),
                    'date' => $date,
                    'sf' => round($sf, 3),
                    'value' => round($value, 2)
                ];
            }

            $this->success([
                'ent_distribution' => $distribution,
                'weekly_visits' => $weekly,
                'seasonality' => $seasonality,
                'daily_counts' => $dailyCounts,
                'forecast_rows' => $forecastRows,
                'forecast_stats' => $forecastStats
            ]);
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
}
