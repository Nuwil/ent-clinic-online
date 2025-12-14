<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/helpers.php';
requireRole(['admin', 'doctor']);

$start = $_GET['start'] ?? date('Y-m-d', strtotime('-6 days'));
$end = $_GET['end'] ?? date('Y-m-d');
?>
<div class="analytics-page">
    <div class="flex flex-between mb-3">
        <div>
            <h2>Analytics</h2>
            <p class="text-muted">Insights from appointment and visit data</p>
        </div>
        <div style="display:flex; gap:12px; align-items:center;">
            <div style="display:flex; gap:8px; align-items:center;">
                <button class="btn btn-sm btn-outline" id="filterAll">All Time</button>
                <button class="btn btn-sm btn-outline" id="filterToday">Today</button>
                <button class="btn btn-sm btn-outline" id="filterWeek">This Week</button>
                <button class="btn btn-sm btn-outline" id="filterMonth">This Month</button>
            </div>
            <input type="date" id="analyticsStart" value="<?php echo e($start); ?>" class="form-control" style="width:150px;" />
            <input type="date" id="analyticsEnd" value="<?php echo e($end); ?>" class="form-control" style="width:150px;" />
            <button class="btn btn-primary" id="applyFilter">Apply</button>
        </div>
    </div>

    <div class="stats-grid" style="margin-bottom:18px;">
        <div class="card stat-card">
            <div class="stat-header"><div><div class="stat-label">Total Patients</div><div class="stat-value" id="statTotalPatients">—</div></div><div class="stat-icon"><i class="fas fa-users"></i></div></div>
        </div>
        <div class="card stat-card">
            <div class="stat-header"><div><div class="stat-label">Avg Visits (per day)</div><div class="stat-value" id="statAvgVisits">—</div></div><div class="stat-icon"><i class="fas fa-chart-bar"></i></div></div>
        </div>
        <div class="card stat-card">
            <div class="stat-header"><div><div class="stat-label">Completed Appointments</div><div class="stat-value" id="statCompleted">—</div></div><div class="stat-icon"><i class="fas fa-calendar-check"></i></div></div>
        </div>
        <div class="card stat-card">
            <div class="stat-header"><div><div class="stat-label">Cancellations</div><div class="stat-value" id="statCancellations">—</div></div><div class="stat-icon"><i class="fas fa-calendar-times"></i></div></div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;">
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div class="card">
                <h3>Visit Trends <button class="btn btn-sm btn-outline" id="downloadTrend" style="float:right;margin-top:-6px;">Download</button></h3>
                <div class="chart-canvas-wrapper"><canvas id="visitsTrendChart" class="chart-canvas"></canvas></div>
            </div>
            <div class="card">
                <h3 id="forecastHeader">Predictive Analysis — <span id="forecastDaysLabel">7-Day Forecast</span> <button class="btn btn-sm btn-outline" id="downloadForecastSmall" style="float:right;margin-top:-6px;">Download</button></h3>
                <div class="chart-canvas-wrapper"><canvas id="forecastChartSmall" class="chart-canvas"></canvas></div>
            </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div class="card">
                <h3>ENT Distribution <button class="btn btn-sm btn-outline" id="downloadEnt" style="float:right;margin-top:-6px;">Download</button></h3>
                <div class="chart-canvas-wrapper"><canvas id="entDonutChart" class="chart-canvas"></canvas></div>
                <div id="entDistributionSummary" style="margin-top:8px;font-size:0.9rem;color:#333;display:flex;flex-wrap:wrap;gap:8px;"></div>
            </div>
            <div class="card">
                <h3>Cancellation Reasons <button class="btn btn-sm btn-outline" id="downloadPie" style="float:right;margin-top:-6px;">Download</button></h3>
                <div class="chart-canvas-wrapper"><canvas id="cancellationsPie" class="chart-canvas"></canvas></div>
            </div>
            <div class="card">
                <h4>Recommendations</h4>
                <ul id="analyticsSuggestions" style="margin:0;padding-left:18px;color:#333;min-height:30px;"></ul>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const trendCtx = document.getElementById('visitsTrendChart').getContext('2d');
    const pieCtx = document.getElementById('cancellationsPie').getContext('2d');
    const forecastSmallCtx = document.getElementById('forecastChartSmall').getContext('2d');
    let trendChart = null, forecastChart = null, pieChart = null, entChart = null;

    function fetchAnalytics(start, end) {
        const params = new URLSearchParams({ start: start, end: end });
        return fetch('<?php echo baseUrl(); ?>/api.php?route=/api/analytics&' + params.toString(), {
            headers: { 'Content-Type': 'application/json' }
        }).then(r => r.json());
    }

    function renderAnalytics(data) {
        // summary
        document.getElementById('statTotalPatients').innerText = data.summary.total_patients;
        document.getElementById('statAvgVisits').innerText = data.summary.avg_visits_per_day + ' /day';
        document.getElementById('statCompleted').innerText = data.summary.appointments_completed;
        document.getElementById('statCancellations').innerText = data.summary.cancellations;

        // Visit Trends (history as bars only)
        const historyLabels = data.visits_trend.labels || [];
        const historyValues = data.visits_trend.data || [];
        const forecastLabels = (data.forecast && data.forecast.labels) ? data.forecast.labels : [];
        const forecastValues = (data.forecast && data.forecast.data) ? data.forecast.data : [];

        // Trend (bar-only) chart for visits
        const trendLabels = historyLabels;
        const trendData = historyValues;

        if (trendChart) trendChart.destroy();
        // Compute a Y-axis max: at least 10, rounded up to nearest 5, with ticks every 5
        const computeYMaxRounded = (arr, minVal = 10) => {
            const numeric = (arr || []).map(v => Number(v || 0)).filter(v => isFinite(v));
            const max = numeric.length ? Math.max(...numeric) : 0;
            const base = Math.max(max, minVal);
            return Math.ceil(base / 5) * 5;
        };
        const trendYMax = computeYMaxRounded(trendData, 10);

        trendChart = new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [ { type: 'bar', label: 'Visits', data: trendData, backgroundColor: 'rgba(88,103,242,0.85)' } ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true } },
                scales: { x: { display: true }, y: { beginAtZero: true, max: trendYMax, ticks: { stepSize: 5 } } }
            }
        });

        // Hybrid Forecast chart: history bars + forecast line in the same chart (predictive)
        const combinedLabels = historyLabels.concat(forecastLabels);
        const barsDataCombined = historyValues.concat(Array(forecastValues.length).fill(null));
        // Put a connecting point at the last history index to visually connect to the forecast line
        const lastHistoryValue = historyValues.length ? historyValues[historyValues.length - 1] : null;
        const forecastLineData = (historyValues.length > 0)
            ? Array(historyValues.length - 1).fill(null).concat([ lastHistoryValue ]).concat(forecastValues)
            : Array(historyValues.length).fill(null).concat(forecastValues);

        if (forecastChart) forecastChart.destroy();
        // Compute yMax for the hybrid forecast combining history and forecast values
        const forecastYMax = computeYMaxRounded(historyValues.concat(forecastValues), 10);

        forecastChart = new Chart(forecastSmallCtx, {
            type: 'bar',
            data: {
                labels: combinedLabels,
                datasets: [
                    { type: 'bar', label: 'Visits', data: barsDataCombined, backgroundColor: 'rgba(88,103,242,0.85)' },
                    { type: 'line', label: 'Forecast', data: forecastLineData, borderColor: '#50e3c2', borderDash: [6,4], tension: 0.3, fill: false }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true } },
                scales: { x: { display: true }, y: { beginAtZero: true, max: forecastYMax, ticks: { stepSize: 5 } } }
            }
        });

        // Update forecast days label dynamically
        const forecastDaysCount = forecastLabels.length || 0;
        const fdLabel = document.getElementById('forecastDaysLabel');
        if (fdLabel) fdLabel.innerText = forecastDaysCount + '-Day Forecast';

        // No separate small line-only forecast chart — hybrid chart is displayed in Predictive Analysis
        // (forecastChart created above as hybrid: history bars + forecast line)

        // ENT Distribution (Donut)
        if (entChart) entChart.destroy();
        const entLabels = data.ent_distribution.labels || [];
        const entData = data.ent_distribution.data || [];
        const entColors = ['#6c5ce7','#00b894','#fdcb6e','#e17055','#0984e3','#b2bec3'];
        entChart = new Chart(document.getElementById('entDonutChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: entLabels,
                datasets: [{ data: entData, backgroundColor: entColors }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                const v = ctx.parsed;
                                const arr = ctx.dataset.data || [];
                                const sum = arr.reduce((a,b)=>a+(b||0),0);
                                const pct = sum ? ((v / sum) * 100).toFixed(1) : '0';
                                return ctx.label + ': ' + v + ' (' + pct + '%)';
                            }
                        }
                    },
                    legend: { position: 'bottom' }
                }
            }
        });

        // Update ENT summary badges (count + percentage)
        const entSummaryEl = document.getElementById('entDistributionSummary');
        entSummaryEl.innerHTML = '';
        const entTotal = entData.reduce((a,b) => a + b, 0) || 0;
        // If there's no meaningful ENT data, show a friendly placeholder
        if (entTotal === 0) {
            const span = document.createElement('span');
            span.style.display = 'inline-block'; span.style.padding = '6px 10px'; span.style.border = '1px solid #eee'; span.style.borderRadius = '6px'; span.style.background = '#fafafa'; span.style.fontSize = '0.9rem';
            span.innerText = 'No ENT visit data for the selected range';
            entSummaryEl.appendChild(span);
        } else {
            entLabels.forEach((lab, i) => {
                const cnt = entData[i] || 0;
                const pct = entTotal ? Math.round((cnt / entTotal) * 100) : 0;
                const span = document.createElement('span');
                span.style.display = 'inline-block'; span.style.padding = '6px 10px'; span.style.border = '1px solid #eee'; span.style.borderRadius = '6px'; span.style.background = '#fafafa'; span.style.fontSize = '0.9rem';
                span.innerText = lab + ': ' + cnt + ' (' + pct + '%)';
                entSummaryEl.appendChild(span);
            });
        }

        // Cancellation pie (handle missing arrays gracefully)
        if (pieChart) pieChart.destroy();
            const cLabelsRaw = (data.cancellations_by_reason && Array.isArray(data.cancellations_by_reason.labels)) ? data.cancellations_by_reason.labels : [];
            const cDataRaw = (data.cancellations_by_reason && Array.isArray(data.cancellations_by_reason.data)) ? data.cancellations_by_reason.data : [];
            const cLabels = (cLabelsRaw.length === 0) ? ['No cancellations'] : cLabelsRaw;
            const cData = (cDataRaw.length === 0) ? [0] : cDataRaw;
        pieChart = new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: cLabels,
                datasets: [{ data: cData, backgroundColor: ['#f43','rgba(88,103,242,0.85)','rgba(79,195,247,0.6)','rgba(111,111,111,0.5)'] }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Suggestions (prescriptive)
        const suggestionsEl = document.getElementById('analyticsSuggestions');
        suggestionsEl.innerHTML = '';
        if (data.suggestions && data.suggestions.length) {
            data.suggestions.forEach(s => {
                const li = document.createElement('li'); li.innerText = s; suggestionsEl.appendChild(li);
            });
        } else {
            suggestionsEl.innerHTML = '<li>No recommendations at this time</li>';
        }

        // Attach download handlers
        document.getElementById('downloadTrend').onclick = function() { downloadChart(trendChart, 'visits-trend.png'); };
        document.getElementById('downloadPie').onclick = function() { downloadChart(pieChart, 'cancellations.png'); };
        const df = document.getElementById('downloadForecastSmall');
        if (df) df.onclick = function() { downloadChart(forecastChart, 'forecast-hybrid.png'); };
        document.getElementById('downloadEnt').onclick = function() { downloadChart(entChart, 'ent-distribution.png'); };

        // Ensure charts resize when container changes
        window.addEventListener('resize', function() { [trendChart, forecastChart, entChart, pieChart].forEach(c => { if (c) c.resize(); }); });
    }

    function applyFilter(start, end) {
        fetchAnalytics(start, end).then(j => {
            if (j && j.data) {
                renderAnalytics(j.data);
            } else if (j && j.success && j.data) {
                // compatibility with controller success wrapper
                renderAnalytics(j.data);
            } else if (j && j.summary) {
                // fallback when controller returns raw data
                renderAnalytics(j);
            } else {
                console.error('Unexpected analytics response', j);
            }
        }).catch(err => console.error('Error fetching analytics', err));
    }

    // Quick filter helpers
    document.getElementById('filterToday').addEventListener('click', function() {
        const d = new Date();
        const s = d.toISOString().slice(0,10); document.getElementById('analyticsStart').value = s; document.getElementById('analyticsEnd').value = s;
        localStorage.setItem('analyticsFilters', JSON.stringify({start: s, end: s}));
        applyFilter(s, s);
    });
    document.getElementById('filterWeek').addEventListener('click', function() {
        const e = new Date(); const s = new Date(); s.setDate(e.getDate() - 6);
        const sv = s.toISOString().slice(0,10), ev = e.toISOString().slice(0,10);
        document.getElementById('analyticsStart').value = sv; document.getElementById('analyticsEnd').value = ev;
        localStorage.setItem('analyticsFilters', JSON.stringify({start: sv, end: ev}));
        applyFilter(sv, ev);
    });
    document.getElementById('filterMonth').addEventListener('click', function(){ const e = new Date(); const s = new Date(); s.setMonth(e.getMonth() - 1); const sv = s.toISOString().slice(0,10), ev = e.toISOString().slice(0,10); document.getElementById('analyticsStart').value = sv; document.getElementById('analyticsEnd').value = ev; localStorage.setItem('analyticsFilters', JSON.stringify({start: sv, end: ev})); applyFilter(sv, ev); });
    document.getElementById('filterAll').addEventListener('click', function(){ document.getElementById('analyticsStart').value = ''; document.getElementById('analyticsEnd').value = ''; localStorage.setItem('analyticsFilters', JSON.stringify({start: '', end: ''})); applyFilter('', ''); });
    // Debounce helper for filter updates
    function debounce(fn, wait) {
        let t = null;
        return function(...args) { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), wait); };
    }
    const debouncedApply = debounce((s,e) => { localStorage.setItem('analyticsFilters', JSON.stringify({start: s, end: e})); applyFilter(s, e); }, 300);
    document.getElementById('applyFilter').addEventListener('click', function(){ const s = document.getElementById('analyticsStart').value || ''; const e = document.getElementById('analyticsEnd').value || ''; debouncedApply(s, e); });

    // Lazy-load charts when the trend canvas is visible
    let chartsInitialized = false;
    function initWhenVisible() {
        const el = document.getElementById('visitsTrendChart');
        if (!el) return;
        if ('IntersectionObserver' in window) {
            const obs = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !chartsInitialized) {
                        chartsInitialized = true;
                        applyFilter(document.getElementById('analyticsStart').value, document.getElementById('analyticsEnd').value);
                        obs.disconnect();
                    }
                });
            }, {threshold: 0.1});
            obs.observe(el);
        } else {
            // Fallback: immediate init
            chartsInitialized = true;
            applyFilter(document.getElementById('analyticsStart').value, document.getElementById('analyticsEnd').value);
        }
    }

    // Apply persisted filters if present
    const stored = localStorage.getItem('analyticsFilters');
    if (stored) {
        try {
            const obj = JSON.parse(stored);
            if (obj && (obj.start !== undefined || obj.end !== undefined)) {
                if (obj.start) document.getElementById('analyticsStart').value = obj.start;
                if (obj.end) document.getElementById('analyticsEnd').value = obj.end;
                // Defer actual fetching until charts visible
                initWhenVisible();
            } else {
                applyFilter(document.getElementById('analyticsStart').value, document.getElementById('analyticsEnd').value);
            }
        } catch (e) {
            initWhenVisible();
        }
    } else {
        initWhenVisible();
    }

    function downloadChart(chart, filename) {
        if (!chart) return;
        const url = chart.toBase64Image();
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
    }
</script>
