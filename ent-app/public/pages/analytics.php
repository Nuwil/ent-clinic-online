<?php
/**
 * Analytics Page - Access restricted to Admin and Doctor roles
 */
requireRole(['admin', 'doctor']);
require_once __DIR__ . '/../../config/Database.php';

$db = Database::getInstance()->getConnection();

// ----- Filters -----
$range = $_GET['range'] ?? 'all';
$customStart = $_GET['start_date'] ?? '';
$customEnd = $_GET['end_date'] ?? '';

$filterStart = null;
$filterEnd = null;
$today = date('Y-m-d');

switch ($range) {
    case 'today':
        $filterStart = $today;
        $filterEnd = $today;
        break;
    case 'week':
        $filterStart = date('Y-m-d', strtotime('monday this week'));
        $filterEnd = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $filterStart = date('Y-m-01');
        $filterEnd = date('Y-m-t');
        break;
    case 'custom':
        if ($customStart && $customEnd) {
            $filterStart = $customStart;
            $filterEnd = $customEnd;
        }
        break;
    case 'all':
    default:
        $range = 'all';
        break;
}

$where = '';
$params = [];
// Only include data from December 1, 2025 onwards
$cutoffDate = '2025-12-01';
if ($filterStart && $filterEnd) {
    // If custom range is selected, ensure it's not before cutoff
    $effectiveStart = max($filterStart, $cutoffDate);
    $where = "WHERE visit_date BETWEEN ? AND ?";
    $params = [$effectiveStart . ' 00:00:00', $filterEnd . ' 23:59:59'];
} else {
    // For 'all' range, only show data from December 1 onwards
    $where = "WHERE DATE(visit_date) >= ?";
    $params = [$cutoffDate];
}

$visits = [];
try {
    $stmt = $db->prepare("SELECT *, DATE(visit_date) AS visit_day, DAYOFWEEK(visit_date) AS dow
                          FROM patient_visits
                          $where
                          ORDER BY visit_date DESC");
    $stmt->execute($params);
    $visits = $stmt->fetchAll();
} catch (PDOException $e) {
    $visits = [];
}

$totalVisitsFilter = count($visits);
$entDistribution = ['ear' => 0, 'nose' => 0, 'throat' => 0];
$weeklyCounts = array_fill(1, 7, 0);

foreach ($visits as $visit) {
    $type = $visit['ent_type'] ?? 'ear';
    if (isset($entDistribution[$type])) {
        $entDistribution[$type]++;
    }
    $dow = isset($visit['dow']) ? (int)$visit['dow'] : (int)date('N', strtotime($visit['visit_day'] ?? 'now'));
    if ($dow >= 1 && $dow <= 7) {
        $weeklyCounts[$dow]++;
    }
}

$weeklyLabels = [
    2 => 'Monday',
    3 => 'Tuesday',
    4 => 'Wednesday',
    5 => 'Thursday',
    6 => 'Friday',
    7 => 'Saturday',
    1 => 'Sunday'
];
$maxWeekly = max($weeklyCounts) ?: 1;

// --- Additional aggregates for different views ---
// Monthly counts for current year (Jan..Dec)
$currentYear = date('Y');
$monthlyCounts = array_fill(1, 12, 0);
foreach ($visits as $v) {
    $d = isset($v['visit_day']) ? $v['visit_day'] : date('Y-m-d', strtotime($v['visit_date'] ?? 'now'));
    $y = (int)date('Y', strtotime($d));
    $m = (int)date('n', strtotime($d));
    if ($y === (int)$currentYear) {
        $monthlyCounts[$m]++;
    }
}
$monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// Weekly-series across the selected data (ISO year-week grouping)
$weekBuckets = [];
foreach ($visits as $v) {
    $d = isset($v['visit_day']) ? $v['visit_day'] : date('Y-m-d', strtotime($v['visit_date'] ?? 'now'));
    $isoYear = date('o', strtotime($d));
    $isoWeek = date('W', strtotime($d));
    $key = $isoYear . '-W' . $isoWeek;
    if (!isset($weekBuckets[$key])) $weekBuckets[$key] = 0;
    $weekBuckets[$key]++;
}
ksort($weekBuckets);
$allWeeksLabels = array_keys($weekBuckets);
$allWeeksCounts = array_values($weekBuckets);

// If the selected range is a month, build day-level series and week-level grouping for that month
$monthDayLabels = [];
$monthDayCounts = [];
if ($filterStart && $filterEnd) {
    $startDT = new DateTime($filterStart);
    $endDT = new DateTime($filterEnd);
    $period = new DatePeriod($startDT, new DateInterval('P1D'), $endDT->modify('+1 day'));
    foreach ($period as $dt) {
        $dstr = $dt->format('Y-m-d');
        $monthDayLabels[] = $dt->format('j'); // day number
        $monthDayCounts[$dstr] = 0;
    }
    // count visits into days
    foreach ($visits as $v) {
        $d = isset($v['visit_day']) ? $v['visit_day'] : date('Y-m-d', strtotime($v['visit_date'] ?? 'now'));
        if (isset($monthDayCounts[$d])) $monthDayCounts[$d]++;
    }
    // convert day counts into ordered array
    $monthDayCounts = array_values($monthDayCounts);
    // week grouping within month: week index starting from 1
    $monthWeekBuckets = [];
    $idx = 0; $weekIdxMap = [];
    $dtCursor = new DateTime($filterStart);
    while ($dtCursor->format('Y-m-d') <= $endDT->format('Y-m-d')) {
        $weekKey = 'W' . $dtCursor->format('W');
        if (!isset($weekIdxMap[$weekKey])) { $weekIdxMap[$weekKey] = count($weekIdxMap); $monthWeekBuckets[$weekIdxMap[$weekKey]] = 0; }
        $dtCursor->modify('+1 day');
    }
    // populate
    $dtCursor = new DateTime($filterStart);
    while ($dtCursor->format('Y-m-d') <= $endDT->format('Y-m-d')) {
        $wk = 'W' . $dtCursor->format('W');
        $i = $weekIdxMap[$wk];
        $dstr = $dtCursor->format('Y-m-d');
        // find visits on this date
        foreach ($visits as $v) {
            $vd = isset($v['visit_day']) ? $v['visit_day'] : date('Y-m-d', strtotime($v['visit_date'] ?? 'now'));
            if ($vd === $dstr) $monthWeekBuckets[$i]++;
        }
        $dtCursor->modify('+1 day');
    }
    $monthWeekCounts = array_values($monthWeekBuckets);
    $monthWeekLabels = [];
    for ($i = 0; $i < count($monthWeekCounts); $i++) { $monthWeekLabels[] = 'Week ' . ($i + 1); }
} else {
    $monthDayLabels = [];
    $monthDayCounts = [];
    $monthWeekLabels = [];
    $monthWeekCounts = [];
}
$patientsData = apiCall('GET', '/patients?limit=1');
$summaryStats = [
    'patients' => isset($patientsData['total']) ? (int)$patientsData['total'] : 0,
    'visits' => $totalVisitsFilter,
];

// Request forecast and seasonality from API (centralized hybrid method)
// Decide forecast horizon based on selected range
$showForecast = true;
$horizon = 7;
// smoothing option removed (UI simplified)
switch ($range) {
    case 'all':
        $horizon = 14;
        break;
    case 'today':
        $showForecast = false;
        break;
    case 'week':
        $horizon = 7;
        break;
    case 'month':
        $horizon = (int)date('t');
        break;
    case 'custom':
        if ($filterStart && $filterEnd) {
            $ds = strtotime($filterStart);
            $de = strtotime($filterEnd);
            if ($de >= $ds) {
                $days = (int)(($de - $ds) / 86400) + 1;
                if ($days <= 1) {
                    $showForecast = false;
                } else {
                    $horizon = min(max(1, $days), 14);
                }
            } else {
                $showForecast = false;
            }
        } else {
            $showForecast = false;
        }
        break;
    default:
        $horizon = 7;
}

if ($showForecast) {
    $apiForecast = apiCall('GET', '/analytics?trend_days=90&min_regression_days=14&horizon=' . intval($horizon));
    if ($apiForecast && is_array($apiForecast)) {
    $entDistribution = $apiForecast['ent_distribution'] ?? $entDistribution;
    // daily_counts is used for sparklines
        $dailyCounts = $apiForecast['daily_counts'] ?? [];
        $forecastStats = $apiForecast['forecast_stats'] ?? ['baseLevel' => 0, 'trendPerDay' => 0];
        $forecastRows = [];
        if (!empty($apiForecast['forecast_rows']) && is_array($apiForecast['forecast_rows'])) {
            foreach ($apiForecast['forecast_rows'] as $fr) {
                $forecastRows[] = [
                    'label' => $fr['label'] ?? ($fr['date'] ?? ''),
                    'date' => $fr['date'] ?? null,
                    'sf' => isset($fr['sf']) ? number_format((float)$fr['sf'], 2) : '1.00',
                    'value' => isset($fr['value']) ? number_format((float)$fr['value'], 1) : '0.0'
                ];
            }
        }
    } else {
        // API call failed; fall back
        $dailyCounts = [];
        $forecastStats = ['baseLevel' => 0, 'trendPerDay' => 0];
        $forecastRows = [];
    }
} else {
    $dailyCounts = [];
    $forecastStats = ['baseLevel' => 0, 'trendPerDay' => 0];
    $forecastRows = [];
}

// derive small summary from forecastRows
$forecastTotal = 0;
$forecastPeakLabel = '';
$forecastPeakValue = 0;
$forecastMinLabel = '';
$forecastMinValue = PHP_INT_MAX;
foreach ($forecastRows as $fr) {
    $v = isset($fr['value']) ? (float)str_replace(',', '', $fr['value']) : 0;
    $forecastTotal += $v;
    if ($v > $forecastPeakValue) { $forecastPeakValue = $v; $forecastPeakLabel = $fr['label'] . (isset($fr['date']) ? ' (' . $fr['date'] . ')' : ''); }
    if ($v < $forecastMinValue) { $forecastMinValue = $v; $forecastMinLabel = $fr['label'] . (isset($fr['date']) ? ' (' . $fr['date'] . ')' : ''); }
}
if ($forecastMinValue === PHP_INT_MAX) { $forecastMinValue = 0; $forecastMinLabel = ''; }
?>

<div class="analytics-page">
    <div class="mb-3">
        <h2 style="margin:0;font-size:1.75rem;font-weight:700;">Analytics & Forecast</h2>
        <p class="text-muted" style="margin-top:0.5rem;">Monitor visit loads, filter by time, and plan ahead.</p>
    </div>

    <div class="card mb-3">
        <form method="get" class="grid grid-3" style="align-items:end;">
            <input type="hidden" name="page" value="analytics">
            <input type="hidden" id="rangeInput" name="range" value="<?php echo e($range); ?>">

            <div>
                <label class="form-label">Date Range</label>
                <div class="filter-group" role="tablist" aria-label="Date range filters">
                    <button type="button" class="filter-btn btn-ghost <?php echo $range === 'all' ? 'active' : ''; ?>" data-range="all">All Time</button>
                    <button type="button" class="filter-btn btn-ghost <?php echo $range === 'today' ? 'active' : ''; ?>" data-range="today">Today</button>
                    <button type="button" class="filter-btn btn-ghost <?php echo $range === 'week' ? 'active' : ''; ?>" data-range="week">This Week</button>
                    <button type="button" class="filter-btn btn-ghost <?php echo $range === 'month' ? 'active' : ''; ?>" data-range="month">This Month</button>
                    <button type="button" id="customBtn" class="filter-btn btn-ghost <?php echo $range === 'custom' ? 'active' : ''; ?>" data-range="custom">Custom Range</button>
                </div>
            </div>

            <?php $showCustom = $range === 'custom'; ?>
            <div id="customRangeFields" <?php if (!$showCustom) echo 'style="display:none;"'; ?>>
                <div>
                    <label class="form-label">Start Date</label>
                    <input type="date" id="startDate" name="start_date" class="form-control" value="<?php echo e($customStart); ?>" <?php echo $showCustom ? '' : 'disabled'; ?> />
                </div>
                <div>
                    <label class="form-label">End Date</label>
                    <input type="date" id="endDate" name="end_date" class="form-control" value="<?php echo e($customEnd); ?>" <?php echo $showCustom ? '' : 'disabled'; ?> />
                </div>
            </div>

            <div style="grid-column:1/-1;text-align:right;margin-top:1rem;">
                <button type="submit" id="applyFilterBtn" class="btn btn-primary" style="display:<?php echo $showCustom ? 'inline-block' : 'none'; ?>;">
                    <i class="fas fa-filter"></i>
                    Apply Filter
                </button>
            </div>
        </form>
    </div>

    <div class="stats-grid">
        <div class="stat-card card">
            <div class="stat-header">
                <div>
                    <div class="stat-label">Patients</div>
                    <div class="stat-value"><?php echo e($summaryStats['patients']); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="stat-body">
                <div class="stat-spark">
                    <canvas id="sparkPatients" height="36"></canvas>
                </div>
                <div class="stat-change positive"><i class="fas fa-arrow-up"></i><span>Active</span></div>
            </div>
        </div>

        <div class="stat-card card">
            <div class="stat-header">
                <div>
                    <div class="stat-label">Visits (Filtered)</div>
                    <div class="stat-value"><?php echo e($summaryStats['visits']); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            </div>
            <div class="stat-body">
                <div class="stat-spark">
                    <canvas id="sparkVisits" height="36"></canvas>
                </div>
                <div class="stat-change"><i class="fas fa-clock"></i><span><?php echo ucfirst($range); ?> range</span></div>
            </div>
        </div>
    </div>

    <div class="grid grid-2" style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">                         
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-stethoscope"></i> ENT Case Distribution</h3>
            </div>
            <div style="padding: 12px;">
                <canvas id="entChart" aria-label="ENT distribution chart" role="img" style="max-height:320px;"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-bar"></i> <span id="visitsHeader">Weekly Visits</span></h3>
                </div>
            <div style="padding: 12px;">
                    <canvas id="weeklyChart" aria-label="Weekly visits chart" role="img" style="max-height:320px;"></canvas>
                    <div style="margin-top:8px;display:flex;gap:8px;align-items:center;">
                        <button id="toggleMonthWeekBtn" type="button" class="btn btn-ghost" style="display:none;"></button>
                    </div>
            </div>
        </div>
    </div>

    <?php if ($showForecast): ?>
    <!-- Predictive Outlook Header and Summary Boxes -->
    <div style="margin-bottom:2rem;">
        <h2 style="margin:0 0 1rem 0;font-size:1.5rem;font-weight:600;">Predictive Outlook</h2>
        
        <div class="grid grid-3" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1rem;">
            <!-- All-Time Outlook Box -->
            <div style="background:linear-gradient(135deg, #f0f4ff 0%, #e3f2ff 100%);border:1px solid #d4e1ff;border-radius:8px;padding:1.5rem;">
                <div style="font-size:0.85rem;color:#4f46e5;font-weight:600;margin-bottom:0.5rem;">ALL-TIME OUTLOOK (<?php echo intval($horizon); ?> DAYS)</div>
                <div style="font-size:2rem;font-weight:700;color:#2d3748;"><?php echo number_format($forecastTotal,0); ?></div>
                <div style="font-size:0.8rem;color:#666;margin-top:0.5rem;">visits</div>
            </div>

            <!-- Peak Day Box -->
            <div style="background:linear-gradient(135deg, #fef3f2 0%, #fff5f3 100%);border:1px solid #ffd1cc;border-radius:8px;padding:1.5rem;">
                <div style="font-size:0.85rem;color:#d97706;font-weight:600;margin-bottom:0.5rem;">PEAK</div>
                <div style="font-size:1.4rem;font-weight:700;color:#2d3748;"><?php echo e(preg_replace('/\s*\(.*?\)/', '', $forecastPeakLabel)); ?> · <?php echo intval($forecastPeakValue); ?> visits</div>
                <div style="font-size:0.8rem;color:#666;margin-top:0.5rem;">highest expected demand</div>
            </div>

            <!-- Slowest Day Box -->
            <div style="background:linear-gradient(135deg, #f0fdf4 0%, #f0fdf4 100%);border:1px solid #dcfce7;border-radius:8px;padding:1.5rem;">
                <div style="font-size:0.85rem;color:#16a34a;font-weight:600;margin-bottom:0.5rem;">SLOWEST</div>
                <div style="font-size:1.4rem;font-weight:700;color:#2d3748;"><?php echo e(preg_replace('/\s*\(.*?\)/', '', $forecastMinLabel)); ?> · <?php echo intval($forecastMinValue); ?> visits</div>
                <div style="font-size:0.8rem;color:#666;margin-top:0.5rem;">lowest expected demand</div>
            </div>
        </div>
    </div>

    <!-- Main Chart Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-line"></i> Forecast Trend</h3>
        </div>
        <div style="padding:1.5rem;">
            <canvas id="forecastChart" aria-label="visit forecast" role="img" style="max-height:360px;width:100%;"></canvas>
        </div>
    </div>

    <!-- Insights Section -->
    <div style="margin-top:1.5rem;background:#f8f9fa;border-left:4px solid #4f46e5;padding:1rem;border-radius:4px;">
        <ul style="list-style:none;padding:0;margin:0;font-size:0.95rem;color:#555;">
            <li style="margin-bottom:0.5rem;"><i class="fas fa-check-circle" style="color:#10b981;margin-right:0.5rem;"></i>
                Projected demand is <strong><?php echo number_format($forecastTotal,0); ?> visits</strong> over <?php echo intval($horizon); ?>-day outlook.
            </li>
            <li style="margin-bottom:0.5rem;"><i class="fas fa-check-circle" style="color:#10b981;margin-right:0.5rem;"></i>
                Peak demand likely on <strong><?php echo e(preg_replace('/\s*\(.*?\)/', '', $forecastPeakLabel)); ?> (~<?php echo intval($forecastPeakValue); ?> visits)</strong>.
            </li>
            <li style="margin-bottom:0;"><i class="fas fa-check-circle" style="color:#10b981;margin-right:0.5rem;"></i>
                Slowest day expected on <strong><?php echo e(preg_replace('/\s*\(.*?\)/', '', $forecastMinLabel)); ?> (~<?php echo intval($forecastMinValue); ?> visits)</strong>.
            </li>
        </ul>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-calendar-week"></i> Forecast Not Shown</h3>
        </div>
        <div style="padding:12px;" class="text-muted">
            <?php if ($range === 'today'): ?>
                <p>No forecast is shown for the <strong>Today</strong> range. Choose a longer date range to generate a forecast.</p>
            <?php else: ?>
                <p>No forecast available for the selected range. Choose a wider date range to generate a forecast.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    
</div>

<!-- Chart.js and interactive logic -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Handle filter button clicks and custom range visibility
function selectRange(range, btn) {
    const form = document.querySelector('form');
    const hidden = document.getElementById('rangeInput');
    const container = document.getElementById('customRangeFields');
    const start = document.getElementById('startDate');
    const end = document.getElementById('endDate');
    const applyBtn = document.getElementById('applyFilterBtn');

    // update hidden input
    if (hidden) hidden.value = range;

    // update active classes
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    if (range === 'custom') {
        if (container) container.style.display = 'block';
        if (start) start.disabled = false;
        if (end) end.disabled = false;
        if (applyBtn) applyBtn.style.display = 'inline-block';
        // do not auto-submit: user must pick dates and click Apply
    } else {
        // hide custom fields and submit form for presets
        if (container) container.style.display = 'none';
        if (start) start.disabled = true;
        if (end) end.disabled = true;
        if (applyBtn) applyBtn.style.display = 'none';
        if (form) form.submit();
    }
}

// Initialize buttons on page load
document.addEventListener('DOMContentLoaded', function(){
    const current = document.getElementById('rangeInput') ? document.getElementById('rangeInput').value : '';
    const btn = document.querySelector('.filter-btn[data-range="' + current + '"]');
    if (btn) btn.classList.add('active');
    // ensure custom fields visibility matches current value
    if (current === 'custom') {
        const container = document.getElementById('customRangeFields');
        const start = document.getElementById('startDate');
        const end = document.getElementById('endDate');
        if (container) container.style.display = 'block';
        if (start) start.disabled = false;
        if (end) end.disabled = false;
            const applyBtn = document.getElementById('applyFilterBtn');
            if (applyBtn) applyBtn.style.display = 'inline-block';
    }
    // attach click handlers
    document.querySelectorAll('.filter-btn').forEach(b => {
        b.addEventListener('click', function(e){ selectRange(this.getAttribute('data-range'), this); });
    });
});

// Embedded data from PHP
const entDistribution = <?php echo json_encode($entDistribution); ?>;
const weeklyCounts = <?php echo json_encode(array_values(array_map(function($d){return (int)$d;}, [$weeklyCounts[2],$weeklyCounts[3],$weeklyCounts[4],$weeklyCounts[5],$weeklyCounts[6],$weeklyCounts[7],$weeklyCounts[1]]))); ?>;
const weeklyLabelsOrdered = <?php echo json_encode(array_values(array_map(function($d){return $d;}, [$weeklyLabels[2],$weeklyLabels[3],$weeklyLabels[4],$weeklyLabels[5],$weeklyLabels[6],$weeklyLabels[7],$weeklyLabels[1]]))); ?>;
const forecastRows = <?php echo json_encode($forecastRows); ?>;
const dailyTrend = <?php echo json_encode($dailyCounts); ?>;
const forecastStats = <?php echo json_encode($forecastStats); ?>;
// Additional aggregates
const monthlyLabels = <?php echo json_encode($monthNames); ?>;
const monthlyCounts = <?php echo json_encode(array_values($monthlyCounts)); ?>;
const allWeeksLabels = <?php echo json_encode($allWeeksLabels); ?>;
const allWeeksCounts = <?php echo json_encode(array_values($allWeeksCounts)); ?>;
const monthDayLabels = <?php echo json_encode($monthDayLabels); ?>; // day numbers
const monthDayCounts = <?php echo json_encode($monthDayCounts); ?>;
const monthWeekLabels = <?php echo json_encode($monthWeekLabels); ?>;
const monthWeekCounts = <?php echo json_encode($monthWeekCounts); ?>;
const currentRange = '<?php echo e($range); ?>';

function createEntChart() {
    const ctx = document.getElementById('entChart').getContext('2d');
    const labels = Object.keys(entDistribution).map(k => k.toUpperCase());
    const values = Object.values(entDistribution);
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: ['#2f6bed','#06b6d4','#10b981'],
                hoverOffset: 8,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { mode: 'index', intersect: false }
            }
        }
    });
}

function createWeeklyChart() {
    const ctx = document.getElementById('weeklyChart').getContext('2d');
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: weeklyLabelsOrdered,
            datasets: [{
                label: 'Visits',
                data: weeklyCounts,
                backgroundColor: weeklyCounts.map(v => v > 0 ? 'rgba(47,107,237,0.9)' : 'rgba(99,102,241,0.2)'),
                borderRadius: 6,
                barThickness: 28
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false }, tooltip: { mode: 'index' } },
            scales: {
                y: { beginAtZero: true, ticks: { precision:0 } }
            }
        }
    });
}

// Generic renderer for visits chart (re-usable)
function renderVisitsPlot(labels, data) {
    const canvas = document.getElementById('weeklyChart');
    if (!canvas) return null;
    // destroy previous
    if (canvas._visitsChart) { try { canvas._visitsChart.destroy(); } catch(e){} }
    const ctx = canvas.getContext('2d');
    const chart = new Chart(ctx, {
        type: 'bar',
        data: { labels: labels, datasets: [{ label: 'Visits', data: data, backgroundColor: data.map(v => v > 0 ? 'rgba(47,107,237,0.9)' : 'rgba(99,102,241,0.2)'), borderRadius: 6, barThickness: 28 }] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
    canvas._visitsChart = chart;
    return chart;
}

function createSparkline(id, data, color) {
    const ctx = document.getElementById(id);
    if (!ctx) return null;
    const labels = data.map((v,i) => i+1);
    return new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                borderColor: color,
                backgroundColor: color.replace('1)', '0.08)') || color,
                tension: 0.35,
                fill: true,
                pointRadius: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            scales: { x: { display: false }, y: { display: false } }
        }
    });
}

// Render charts on DOM ready
document.addEventListener('DOMContentLoaded', function(){
    try { createEntChart(); } catch(e) { console.warn('Ent chart failed', e); }
    try {
        const toggleBtn = document.getElementById('toggleMonthWeekBtn');
        const headerSpan = document.getElementById('visitsHeader');
        const showMonthlyDefault = (currentRange === 'all' || currentRange === 'month' || currentRange === 'custom');

        if (showMonthlyDefault) {
            // show months by default
            renderVisitsPlot(monthlyLabels, monthlyCounts);
            if (headerSpan) headerSpan.innerText = 'Monthly Visits';
            if (toggleBtn) { toggleBtn.style.display = 'inline-block'; toggleBtn.innerText = 'View Weekly Visits'; toggleBtn.dataset.state = 'monthly'; }
        } else {
            // for 'today' and 'week' show Monday-Sunday (weeklyLabelsOrdered)
            renderVisitsPlot(weeklyLabelsOrdered, weeklyCounts);
            if (headerSpan) headerSpan.innerText = 'Weekly Visits';
            if (toggleBtn) toggleBtn.style.display = 'none';
        }
        
    } catch(e) { console.warn('Visits chart failed', e); }
    try { createSparkline('sparkPatients', dailyTrend.slice(-14), 'rgba(47,107,237,0.9)'); } catch(e){/*ignore*/}
    try { createSparkline('sparkVisits', dailyTrend.slice(-14), 'rgba(16,185,129,0.9)'); } catch(e){/*ignore*/}

    // Combined Actual + Forecast Chart (14 days actual + forecast line)
    try {
        // Format dates
        function formatShortDate(iso) {
            if (!iso) return '';
            const d = new Date(iso + 'T00:00:00');
            if (isNaN(d)) return iso;
            return d.toLocaleString(undefined, { month: 'short', day: 'numeric' });
        }

        // Get actual data from December 1 onwards (only show data we have)
        const cutoffDate = new Date('2025-12-01');
        const todayDate = new Date();
        const actualDates = [];
        const actualData = [];

        // Determine format of `dailyTrend` returned by server:
        // - older API returned an array of counts (numbers) corresponding to dates from cutoffDate onward
        // - newer API may provide array of {date, count}
        const dailyIsNumbers = Array.isArray(dailyTrend) && dailyTrend.length > 0 && (typeof dailyTrend[0] === 'number');
        const dailyIsObjects = Array.isArray(dailyTrend) && dailyTrend.length > 0 && dailyTrend[0] && typeof dailyTrend[0] === 'object' && ('date' in dailyTrend[0] || 'count' in dailyTrend[0]);

        // Build dates from Dec 1 to today and map counts accordingly
        for (let d = new Date(cutoffDate); d <= todayDate; d.setDate(d.getDate() + 1)) {
            const dateStr = d.toISOString().split('T')[0];
            actualDates.push(formatShortDate(dateStr));

            if (dailyIsNumbers) {
                // dailyTrend[0] corresponds to cutoffDate; compute index
                const idx = Math.floor((new Date(dateStr) - new Date('2025-12-01')) / 86400000);
                const val = (idx >= 0 && idx < dailyTrend.length) ? Number(dailyTrend[idx]) : 0;
                actualData.push(isFinite(val) ? val : 0);
            } else if (dailyIsObjects) {
                let found = false;
                for (let item of dailyTrend) {
                    if (!item) continue;
                    // item may be {date,count} or {d,count} or similar
                    if ((item.date && item.date === dateStr) || (item.d && item.d === dateStr)) {
                        actualData.push(Number(item.count || item.c || 0));
                        found = true; break;
                    }
                }
                if (!found) actualData.push(0);
            } else {
                // unknown shape: push 0
                actualData.push(0);
            }
        }

        // Forecast data
        const forecastDates = forecastRows.map(r => r.date ? formatShortDate(r.date) : (r.label || ''));
        const forecastVals = forecastRows.map(r => parseFloat(String(r.value).replace(/,/g, '')) || 0);

        // Combine all labels and create datasets
        const allLabels = [...actualDates, ...forecastDates];
        const actualChartData = [...actualData, ...Array(forecastVals.length).fill(null)];
        const forecastChartData = [...Array(actualData.length).fill(null), ...forecastVals];

        const ctxF = document.getElementById('forecastChart').getContext('2d');

        new Chart(ctxF, {
            type: 'line',
            data: {
                labels: allLabels,
                datasets: [
                    {
                        label: 'Actual',
                        data: actualChartData,
                        borderColor: 'rgba(96, 165, 250, 0.95)',
                        backgroundColor: 'rgba(96, 165, 250, 0.15)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 5,
                        pointBackgroundColor: 'rgba(59, 130, 246, 0.9)',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointHoverRadius: 7,
                        segment: {
                            borderDash: ctx => ctx.p0DataIndex !== undefined && ctx.p1DataIndex !== undefined && 
                                                actualChartData[ctx.p1DataIndex] === null ? [0] : []
                        }
                    },
                    {
                        label: 'Forecast',
                        data: forecastChartData,
                        borderColor: 'rgba(249, 115, 22, 0.95)',
                        backgroundColor: 'rgba(249, 115, 22, 0.08)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 5,
                        pointBackgroundColor: 'rgba(249, 115, 22, 0.9)',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointHoverRadius: 7,
                        borderDash: [5, 5],
                        segment: {
                            borderDash: ctx => ctx.p0DataIndex !== undefined && ctx.p1DataIndex !== undefined && 
                                                forecastChartData[ctx.p0DataIndex] === null ? [5, 5] : []
                        }
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: { 
                    mode: 'index', 
                    intersect: false 
                },
                plugins: {
                    legend: { 
                        display: true,
                        position: 'top',
                        labels: {
                            font: { size: 12, weight: '500' },
                            color: 'rgba(55, 65, 81, 0.9)',
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(0, 0, 0, 0.85)',
                        padding: 12,
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 },
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        cornerRadius: 6,
                        displayColors: true,
                        callbacks: {
                            title: function(context) {
                                return '📅 ' + context[0].label;
                            },
                            label: function(context) {
                                const datasetLabel = context.dataset.label || '';
                                const value = context.parsed.y;
                                if (value === null) return null;
                                return datasetLabel + ': ' + Math.round(value) + ' visits';
                            },
                            afterLabel: function(context) {
                                if (context.dataset.label === 'Forecast' && context.parsed.y !== null) {
                                    const idx = context.dataIndex;
                                    const fIdx = idx - actualData.length;
                                    if (fIdx >= 0 && fIdx < forecastRows.length) {
                                        const sf = parseFloat(String(forecastRows[fIdx].sf)) || 1.0;
                                        return '⚖️ Seasonality: ' + (sf * 100).toFixed(0) + '%';
                                    }
                                }
                                return '';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: { size: 11 },
                            color: 'rgba(107, 114, 128, 0.8)'
                        },
                        grid: {
                            color: 'rgba(107, 114, 128, 0.08)',
                            drawBorder: false
                        }
                    },
                    x: {
                        ticks: {
                            font: { size: 10 },
                            color: 'rgba(107, 114, 128, 0.8)',
                            maxRotation: 45,
                            minRotation: 0
                        },
                        grid: {
                            display: false,
                            drawBorder: false
                        }
                    }
                }
            }
        });
    } catch (e) {
        console.warn('Forecast chart failed', e);
    }
    // attach single toggle handler for month/week views
    const toggleBtn = document.getElementById('toggleMonthWeekBtn');
    const headerSpan = document.getElementById('visitsHeader');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            const state = this.dataset.state || 'monthly';
            if (state === 'monthly') {
                // switch to weekly view (always Monday->Sunday for the selected range)
                renderVisitsPlot(weeklyLabelsOrdered, weeklyCounts);
                this.dataset.state = 'weekly';
                this.innerText = 'Back to Monthly Visits';
                if (headerSpan) headerSpan.innerText = 'Weekly Visits';
            } else {
                // back to monthly
                renderVisitsPlot(monthlyLabels, monthlyCounts);
                this.dataset.state = 'monthly';
                this.innerText = 'View Weekly Visits';
                if (headerSpan) headerSpan.innerText = 'Monthly Visits';
            }
        });
    }
});
</script>
<!-- forecasting demo module removed from page (server-side API forecast retained) -->

