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
            // Only doctors and admins should access analytics
            $this->requireRole(['admin', 'doctor']);

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
                // 2) Weekly visits - last 7 days grouped by local Manila date
                $manilaNow = new DateTime('now', new DateTimeZone('Asia/Manila'));
                $manilaToday = $manilaNow->format('Y-m-d');
                $startWeekly = date('Y-m-d', strtotime($manilaToday . ' -6 days'));

                // Use CONVERT_TZ to convert stored UTC datetimes to Manila before grouping by date
                $stmt2 = $this->db->prepare("SELECT DATE(CONVERT_TZ(visit_date, '+00:00', '+08:00')) as d, COUNT(*) as count FROM patient_visits WHERE DATE(CONVERT_TZ(visit_date, '+00:00', '+08:00')) BETWEEN ? AND ? GROUP BY DATE(CONVERT_TZ(visit_date, '+00:00', '+08:00')) ORDER BY d");
                $stmt2->execute([$startWeekly, $manilaToday]);
                $rows2 = $stmt2->fetchAll();

                $weekly = [];
                for ($i = 6; $i >= 0; $i--) {
                    $day = date('Y-m-d', strtotime($manilaToday . " -{$i} days"));
                    $weekly[$day] = 0;
                }
                foreach ($rows2 as $r) {
                    $d = $r['d'];
                    if (isset($weekly[$d])) $weekly[$d] = (int)$r['count'];
            }

            // 3) Seasonality factors (SF) using all historical data: avg visits per weekday
            $weeklyAll = array_fill(1, 7, 0);
            $totalWeeklyAll = 0;
            try {
                    // Compute weekday counts using Manila local weekday mapping
                    $stmt3 = $this->db->query("SELECT DAYOFWEEK(CONVERT_TZ(visit_date, '+00:00', '+08:00')) as dow, COUNT(*) as count FROM patient_visits GROUP BY DAYOFWEEK(CONVERT_TZ(visit_date, '+00:00', '+08:00'))");
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
                // 4) Trailing trend: get daily counts for last $trendDays days using Manila local dates
                $dailySeries = []; // array of ['date'=>'YYYY-MM-DD','count'=>int]
                $dailyCounts = [];
                $trendStartManila = date('Y-m-d', strtotime($manilaToday . ' -' . max(1, $trendDays - 1) . ' days'));
                $effectiveStart = $trendStartManila;
                try {
                    $stmt4 = $this->db->prepare("SELECT DATE(CONVERT_TZ(visit_date, '+00:00', '+08:00')) as d, COUNT(*) as count FROM patient_visits WHERE DATE(CONVERT_TZ(visit_date, '+00:00', '+08:00')) >= ? GROUP BY DATE(CONVERT_TZ(visit_date, '+00:00', '+08:00')) ORDER BY d ASC");
                    $stmt4->execute([$effectiveStart]);
                    foreach ($stmt4->fetchAll() as $row) {
                        $dailySeries[] = ['date' => $row['d'], 'count' => (int)$row['count']];
                        $dailyCounts[] = (int)$row['count'];
                    }
                } catch (PDOException $e) {
                    $dailySeries = [];
                    $dailyCounts = [];
            }

            $forecastStats = ['baseLevel' => 0.0, 'trendPerDay' => 0.0, 'n' => count($dailyCounts), 'smoothing' => $smoothing, 'mae' => null, 'rmse' => null, 'backtest_h' => 0, 'method' => 'holt_winters'];
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

                // Use Holt-Winters additive seasonal model (weekly seasonality) for forecasts when we have enough data
                $seasonLen = 7;
                $hwForecast = null;
                if ($n >= max(14, $seasonLen * 2)) {
                    // perform Holt-Winters additive with improved trend parameters
                    $alpha = 0.3; $beta = 0.1; $gamma = 0.3;
                    $hw = $this->holtWintersAdditive($processedCounts, $seasonLen, $alpha, $beta, $gamma, 0);
                    // baseLevel approximate = last level + last trend
                    $forecastStats['baseLevel'] = max(0.0, $hw['level'] + $hw['trend']);
                    $forecastStats['trendPerDay'] = $hw['trend'];
                    $forecastStats['method'] = 'holt_winters_additive';
                    $hwForecast = $hw;
                } else {
                    $sma = array_sum($processedCounts) / $n;
                    $forecastStats['baseLevel'] = max(0.0, $sma);
                    $forecastStats['method'] = 'sma_fallback';
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
                    if (isset($hwForecast) && is_array($hwForecast)) {
                        // If we used Holt-Winters on full series, backtest using Holt-Winters trained on train only
                        $hwTrain = $this->holtWintersAdditive($train, $seasonLen, 0.3, 0.1, 0.3, $H);
                        $preds = $hwTrain['forecasts'];
                    } else {
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
                        for ($t = 1; $t <= $H; $t++) {
                            $d = date('Y-m-d', strtotime("+" . $t . " days", strtotime($lastDate)));
                            $dow = (int)date('N', strtotime($d));
                            $mysqlDow = $dow === 7 ? 1 : $dow + 1;
                            $sf = $seasonality[$mysqlDow] ?? 1.0;
                            $preds[] = $base_train * $sf;
                        }
                    }

                    // actuals from the withheld tail
                    $actuals = [];
                    for ($t = 0; $t < $H; $t++) {
                        $actuals[] = isset($dailySeries[$m + $t]['count']) ? (int)$dailySeries[$m + $t]['count'] : 0;
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
            // If Holt-Winters forecast available, use it; otherwise use baseLevel * seasonality
            for ($i = 0; $i < $horizon; $i++) {
                $date = date('Y-m-d', strtotime("+" . ($i+1) . " days"));
                $dow = (int)date('N', strtotime($date)); // 1 (Mon) .. 7 (Sun)
                $mysqlDow = $dow === 7 ? 1 : $dow + 1;
                $sf = $seasonality[$mysqlDow] ?? 1.0;

                if (isset($hwForecast) && is_array($hwForecast)) {
                    // use Holt-Winters generated seasonals and levels for out-of-sample forecasts
                    $hwVals = $this->holtWintersAdditive($processedCounts, 7, 0.3, 0.1, 0.3, $i+1);
                    $value = $hwVals['forecasts'][$i];
                    $method = 'holt_winters';
                    $sfUsed = null;
                } else {
                    $value = $forecastStats['baseLevel'] * $sf;
                    $method = 'base_sf';
                    $sfUsed = $sf;
                }

                $forecastRows[] = [
                    'label' => $weekdayLabels[$mysqlDow] ?? date('D', strtotime($date)),
                    'date' => $date,
                    'sf' => $sfUsed !== null ? round($sfUsed, 3) : null,
                    'value' => round($value, 2),
                    'method' => $method
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

    // Simple Holt-Winters additive implementation for weekly seasonality
    private function holtWintersAdditive(array $series, int $seasonLen = 7, float $alpha = 0.3, float $beta = 0.1, float $gamma = 0.3, int $h = 7)
    {
        $n = count($series);
        if ($n === 0) return ['level' => 0.0, 'trend' => 0.0, 'seasonals' => [], 'forecasts' => array_fill(0, $h, 0.0)];

        // Initialize seasonals using average of seasons
        $seasonals = array_fill(0, $seasonLen, 0.0);
        $seasonAverages = [];
        $nSeasons = (int)floor($n / $seasonLen);
        if ($nSeasons < 1) $nSeasons = 1;

        for ($i = 0; $i < $nSeasons; $i++) {
            $start = $i * $seasonLen;
            $seasonAverages[$i] = array_sum(array_slice($series, $start, $seasonLen)) / $seasonLen;
        }
        for ($i = 0; $i < $seasonLen; $i++) {
            $sum = 0; $cnt = 0;
            for ($j = 0; $j < $nSeasons; $j++) {
                $idx = $j * $seasonLen + $i;
                if ($idx < $n) { $sum += $series[$idx] / max(1e-9, $seasonAverages[$j]); $cnt++; }
            }
            $seasonals[$i] = $cnt ? ($sum / $cnt) : 1.0;
        }

        // Initialize level and trend more robustly
        $level = $series[0];
        $trend = 0.0;
        if ($n > $seasonLen) {
            // Use the last half of series to compute trend (more recent data)
            $halfStart = (int)floor($n / 2);
            $recentSlice = array_slice($series, $halfStart);
            $oldAvg = array_sum(array_slice($series, 0, $halfStart)) / max(1, $halfStart);
            $newAvg = array_sum($recentSlice) / max(1, count($recentSlice));
            $trend = ($newAvg - $oldAvg) / max(1, count($recentSlice));
            $level = $newAvg;
        }

        $smooth = $level;
        $b = $trend;
        $s = $seasonals;

        // Fit
        for ($t = 0; $t < $n; $t++) {
            $x = $series[$t];
            $m = $t - $seasonLen >= 0 ? $s[$t % $seasonLen] : $s[$t % $seasonLen];
            $last_level = $smooth;
            $smooth = $alpha * ($x - $m) + (1 - $alpha) * ($smooth + $b);
            $b = $beta * ($smooth - $last_level) + (1 - $beta) * $b;
            $s[$t % $seasonLen] = $gamma * ($x - $smooth) + (1 - $gamma) * $s[$t % $seasonLen];
        }

        // Forecast h steps
        $forecasts = [];
        for ($i = 1; $i <= $h; $i++) {
            $m = ($n + $i - 1) % $seasonLen;
            $forecasts[] = ($smooth + $b * $i) + $s[$m];
        }

        return ['level' => $smooth, 'trend' => $b, 'seasonals' => $s, 'forecasts' => $forecasts];
    }
}
