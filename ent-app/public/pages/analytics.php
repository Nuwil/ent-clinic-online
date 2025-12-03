<?php
/**
 * Simplified Analytics Page
 * - Descriptive Analysis (summary + charts)
 * - Predictive Analysis (forecast) — horizon adapts to selected date range
 */
requireRole(['admin', 'doctor']);
?>

<div class="analytics-page">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 style="margin:0;font-size:1.5rem;">Analytics</h2>
            <p class="text-muted" style="margin:0.25rem 0 0 0;">Descriptive and Predictive analyses. Select a date range to update both sections.</p>
        </div>
        <form id="filterForm" style="display:flex;gap:8px;align-items:center;">
            <label style="font-size:0.9rem;color:#444;">From</label>
            <input type="date" id="startDate" name="start_date" class="form-control" />
            <label style="font-size:0.9rem;color:#444;">To</label>
            <input type="date" id="endDate" name="end_date" class="form-control" />
            <button type="button" id="applyBtn" class="btn btn-primary">Apply</button>
        </form>
    </div>

    <div id="descriptive" style="margin-bottom:1.25rem;">
        <h3 style="margin:0 0 0.5rem 0;font-size:1.05rem;">Descriptive Analysis</h3>
        <div style="display:grid;grid-template-columns:1fr 2.5fr;gap:12px;margin-bottom:12px;">
            <div class="grid grid-2" style="gap:12px; display:flex; flex-direction: column;">
                <div class="card" style="padding:12px; margin:0;">
                    <div style="font-size:0.85rem;color:#666;">Total Visits (range)</div>
                    <div id="totalVisits" style="font-size:1.5rem;font-weight:700;">—</div>
                </div>
                <div class="card" style="padding:12px;">
                    <div style="font-size:0.85rem;color:#666;">ENT Distribution</div>
                    <canvas id="entPie" style="height:110px;"></canvas>
                </div>
            </div>
            <div class="card" style="padding:12px;">
                <div style="font-size:0.85rem;color:#666;">Daily Visits</div>
                <canvas id="dailyLine" style="height:110px;"></canvas>
            </div>
        </div>
    </div>

    <div id="predictive">
        <h3 style="margin:0 0 0.5rem 0;font-size:1.05rem;">Predictive Analysis</h3>
        <div id="predictiveSummary" style="display:flex;gap:12px;margin-bottom:12px;flex-wrap:wrap;"></div>
        <div class="card" style="padding:12px;">
            <canvas id="forecastChart" style="height:260px;width:100%;"></canvas>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const API_BASE = '<?php echo baseUrl(); ?>';

function formatShort(iso) {
    const d = new Date(iso + 'T00:00:00');
    if (isNaN(d)) return iso;
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

async function fetchAnalytics(start, end) {
    const url = `${API_BASE}/api.php?route=/api/analytics&start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}`;
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('API error ' + res.status);
    const payload = await res.json();
    return payload.data || payload;
}

let entChart = null, dailyChart = null, forecastChart = null;

function renderDescriptive(des) {
    document.getElementById('totalVisits').innerText = des.total_visits;

    const entCanvas = document.getElementById('entPie').getContext('2d');
    const entLabels = Object.keys(des.ent_distribution).map(k => k.toUpperCase());
    const entVals = Object.values(des.ent_distribution);
    if (entChart) try { entChart.destroy(); } catch(e){}
    entChart = new Chart(entCanvas, { type: 'doughnut', data: { labels: entLabels, datasets: [{ data: entVals, backgroundColor: ['#2f6bed','#06b6d4','#10b981'] }] }, options: { plugins:{legend:{position:'bottom'}} } });

    const dailyCtx = document.getElementById('dailyLine').getContext('2d');
    const labels = des.daily_series.map(r => r.date).map(d=>formatShort(d));
    const data = des.daily_series.map(r => r.count);
    if (dailyChart) try { dailyChart.destroy(); } catch(e){}
    dailyChart = new Chart(dailyCtx, { type: 'line', data: { labels, datasets:[{ label:'Daily Visits', data, borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,0.08)', fill:true, tension:0.3 }] }, options:{ plugins:{legend:{display:false}}, scales:{ y:{ beginAtZero:true } } } });
}

function renderPredictive(pred, des) {
    const container = document.getElementById('predictiveSummary');
    container.innerHTML = '';
    if (!pred || pred.horizon === 0 || !pred.forecast_rows.length) {
        container.innerHTML = '<div class="card" style="padding:12px;">No forecast for selected range (need >1 day)</div>';
        if (forecastChart) try { forecastChart.destroy(); } catch(e){}
        return;
    }

    // quick summary cards: horizon total, peak, min
    const total = pred.forecast_rows.reduce((s,r)=>s+r.value,0);
    const peak = pred.forecast_rows.reduce((p,r)=> r.value>p.value?r:p, pred.forecast_rows[0]);
    const min = pred.forecast_rows.reduce((p,r)=> r.value<p.value?r:p, pred.forecast_rows[0]);

    const cards = [
        { title: `Forecast (${pred.horizon} days)`, value: Math.round(total)},
        { title: `Peak`, value: `${peak.label} · ${Math.round(peak.value)}` },
        { title: `Slowest`, value: `${min.label} · ${Math.round(min.value)}` }
    ];
    for (const c of cards) {
        const el = document.createElement('div'); el.className='card'; el.style.padding='12px'; el.innerHTML = `<div style="font-size:0.85rem;color:#666">${c.title}</div><div style="font-size:1.25rem;font-weight:700">${c.value}</div>`; container.appendChild(el);
    }

    // Forecast chart: combine actual recent days with forecast
    const actualDates = des.daily_series.map(r=>formatShort(r.date));
    const actualVals = des.daily_series.map(r=>r.count);
    const fDates = pred.forecast_rows.map(r=>formatShort(r.date));
    const fVals = pred.forecast_rows.map(r=>r.value);

    const labels = [...actualDates, ...fDates];
    const actualData = [...actualVals, ...Array(fVals.length).fill(null)];
    const forecastData = [...Array(actualVals.length).fill(null), ...fVals];

    const ctx = document.getElementById('forecastChart').getContext('2d');
    if (forecastChart) try { forecastChart.destroy(); } catch(e){}
    forecastChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label:'Actual', data: actualData, borderColor:'#2563eb', backgroundColor:'rgba(37,99,235,0.08)', fill:true, tension:0.35, pointRadius:3 },
                { label:'Forecast', data: forecastData, borderColor:'#f97316', backgroundColor:'rgba(249,115,22,0.06)', fill:true, tension:0.35, borderDash:[6,4], pointRadius:3 }
            ]
        },
        options: { plugins:{ tooltip:{mode:'index',intersect:false} }, scales:{ y:{ beginAtZero:true } } }
    });
}

async function loadAndRender(start, end) {
    try {
        const payload = await fetchAnalytics(start, end);
        renderDescriptive(payload.descriptive);
        renderPredictive(payload.predictive, payload.descriptive);
    } catch (e) {
        console.error('Analytics load failed', e);
        alert('Failed to load analytics: ' + e.message);
    }
}

document.addEventListener('DOMContentLoaded', function(){
    const startInput = document.getElementById('startDate');
    const endInput = document.getElementById('endDate');
    const applyBtn = document.getElementById('applyBtn');

    // default last 30 days
    const today = new Date();
    const endDefault = today.toISOString().slice(0,10);
    const d30 = new Date(); d30.setDate(d30.getDate() - 29);
    const startDefault = d30.toISOString().slice(0,10);
    startInput.value = startDefault; endInput.value = endDefault;

    loadAndRender(startInput.value, endInput.value);

    applyBtn.addEventListener('click', function(){
        const s = startInput.value; const e = endInput.value;
        if (!s || !e) return alert('Please pick both start and end dates');
        if (new Date(s) > new Date(e)) return alert('Start date must be before end date');
        loadAndRender(s,e);
    });
});
</script>

