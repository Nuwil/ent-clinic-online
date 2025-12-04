<?php
/**
 * Simplified Analytics Page
 * - Descriptive Analysis (summary + charts)
 * - Predictive Analysis (forecast) — horizon adapts to selected date range
 */
requireRole(['admin', 'doctor']);
require_once __DIR__ . '/../../config/Database.php';

$db = Database::getInstance();
$totalPatientsResult = $db->fetch('SELECT COUNT(*) as total FROM patients');
$totalPatients = $totalPatientsResult['total'] ?? 0;
?>

<div class="analytics-page" data-total-patients="<?php echo $totalPatients; ?>">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h2 style="margin:0;font-size:1.5rem;">Analytics</h2>
            <p class="text-muted" style="margin:0.25rem 0 0 0;">Descriptive and Predictive analyses. Select a date range to update both sections.</p>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.5rem;flex-wrap:wrap;">
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <button type="button" id="todayBtn" class="btn btn-outline-secondary" style="font-size:0.85rem;padding:0.4rem 0.8rem;">Today</button>
                <button type="button" id="weekBtn" class="btn btn-outline-secondary" style="font-size:0.85rem;padding:0.4rem 0.8rem;">This Week</button>
                <button type="button" id="monthBtn" class="btn btn-outline-secondary" style="font-size:0.85rem;padding:0.4rem 0.8rem;">This Month</button>
                <button type="button" id="allTimeBtn" class="btn btn-primary" style="font-size:0.85rem;padding:0.4rem 0.8rem;">All Time</button>
                <button type="button" id="customRangeBtn" class="btn btn-outline-secondary" style="font-size:0.85rem;padding:0.4rem 0.8rem;">Custom Range</button>
            </div>
        </div>
    </div>

    <!-- Custom Range Modal -->
    <div id="customRangeModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div class="card" style="width:100%;max-width:400px;padding:20px;margin:20px;">
            <h5 style="margin:0 0 1rem 0;">Select Date Range</h5>
            <form id="customRangeForm" style="display:flex;flex-direction:column;gap:1rem;">
                <div>
                    <label style="font-size:0.9rem;color:#444;display:block;margin-bottom:0.4rem;">From</label>
                    <input type="date" id="customStartDate" class="form-control" />
                </div>
                <div>
                    <label style="font-size:0.9rem;color:#444;display:block;margin-bottom:0.4rem;">To</label>
                    <input type="date" id="customEndDate" class="form-control" />
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;">
                    <button type="button" id="customCancelBtn" class="btn btn-outline-secondary">Cancel</button>
                    <button type="button" id="customApplyBtn" class="btn btn-primary">Apply</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Top Stat Cards Row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:1.5rem;">
        <div class="card" style="padding:12px;">
            <div style="font-size:0.75rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Total Patients</div>
            <div id="totalPatients" style="font-size:1.75rem;font-weight:700;margin-top:0.5rem;">—</div>
            <div id="totalPatientsDesc" style="font-size:0.75rem;color:#999;margin-top:0.25rem;"></div>
        </div>
        <div class="card" style="padding:12px;">
            <div style="font-size:0.75rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Visits - All Time</div>
            <div id="visitsAllTime" style="font-size:1.75rem;font-weight:700;margin-top:0.5rem;">—</div>
            <div id="visitsAllTimeDesc" style="font-size:0.75rem;color:#999;margin-top:0.25rem;"></div>
        </div>
        <div class="card" style="padding:12px;">
            <div style="font-size:0.75rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Avg Daily Visits</div>
            <div id="avgDailyVisits" style="font-size:1.75rem;font-weight:700;margin-top:0.5rem;">—</div>
            <div id="avgDailyVisitsDesc" style="font-size:0.75rem;color:#999;margin-top:0.25rem;"></div>
        </div>
        <div class="card" style="padding:12px;">
            <div style="font-size:0.75rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Forecast - All Time Outlook (14 days)</div>
            <div id="forecastTotal" style="font-size:1.75rem;font-weight:700;margin-top:0.5rem;">—</div>
            <div id="forecastTotalDesc" style="font-size:0.75rem;color:#999;margin-top:0.25rem;"></div>
        </div>
    </div>

    <!-- ENT Distribution + Monthly Visits Row -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:1.5rem;">
        <div class="card" style="padding:12px;">
            <h4 style="margin:0 0 0.5rem 0;font-size:0.95rem;display:flex;justify-content:space-between;align-items:center;">
                ENT Distribution
                <a href="#" style="font-size:0.75rem;color:#0066cc;text-decoration:none;">Case mix</a>
            </h4>
            <canvas id="entPie" style="height:200px;"></canvas>
            <div id="entDesc" style="font-size:0.8rem;color:#888;margin-top:0.5rem;line-height:1.3;"></div>
        </div>
        <div class="card" style="padding:12px;">
            <h4 style="margin:0 0 0.5rem 0;font-size:0.95rem;display:flex;justify-content:space-between;align-items:center;">
                Monthly Visits
                <button style="border:none;background:none;color:#0066cc;cursor:pointer;font-size:0.75rem;padding:0;">View Weekly Breakdown</button>
            </h4>
            <div style="font-size:0.75rem;color:#999;margin-bottom:0.5rem;">January - December</div>
            <canvas id="dailyLine" style="height:200px;"></canvas>
        </div>
    </div>

    <!-- Descriptive Insights + Predictive Outlook Row -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="card" style="padding:12px;">
            <h4 style="margin:0 0 0.75rem 0;font-size:0.95rem;">Descriptive Insights</h4>
            <ul style="margin:0;padding-left:1.25rem;font-size:0.85rem;line-height:1.6;color:#444;">
                <li id="insight1" style="margin-bottom:0.5rem;">—</li>
                <li id="insight2" style="margin-bottom:0.5rem;">—</li>
                <li id="insight3" style="margin-bottom:0.5rem;">—</li>
            </ul>
        </div>
        <div class="card" style="padding:12px;">
            <h4 style="margin:0 0 0.5rem 0;font-size:0.95rem;">Predictive Outlook</h4>
            <div id="predictiveSummary" style="display:flex;gap:12px;margin-bottom:0.75rem;flex-wrap:wrap;"></div>
            <canvas id="forecastChart" style="height:200px;width:100%;margin-bottom:0.75rem;"></canvas>
            <div id="forecastDesc" style="font-size:0.8rem;color:#888;line-height:1.3;"></div>
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

function formatLong(iso) {
    const d = new Date(iso + 'T00:00:00');
    if (isNaN(d)) return iso;
    return d.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
}

function formatRange(start, end) {
    const s = new Date(start + 'T00:00:00');
    const e = new Date(end + 'T00:00:00');
    if (isNaN(s) || isNaN(e)) return '';
    const isSameYear = s.getFullYear() === e.getFullYear();
    const isSameMonth = isSameYear && s.getMonth() === e.getMonth();
    
    if (start === end) return formatLong(start);
    if (isSameMonth) return s.toLocaleDateString(undefined, {month:'long',day:'numeric'}) + ' – ' + e.toLocaleDateString(undefined, {day:'numeric',year:'numeric'});
    if (isSameYear) return s.toLocaleDateString(undefined, {month:'short',day:'numeric'}) + ' – ' + e.toLocaleDateString(undefined, {month:'short',day:'numeric',year:'numeric'});
    return formatLong(start) + ' – ' + formatLong(end);
}

async function fetchAnalytics(start, end) {
    const url = `${API_BASE}/api.php?route=/api/analytics&start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}`;
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('API error ' + res.status);
    const payload = await res.json();
    return payload.data || payload;
}

let entChart = null, dailyChart = null, forecastChart = null;

function renderDescriptive(des, start, end) {
    // Populate stat cards
    document.getElementById('visitsAllTime').innerText = des.total_visits;
    const rangeStr = formatRange(start, end);
    document.getElementById('visitsAllTimeDesc').innerText = `Completed consultations`;

    // ENT pie chart
    const entCanvas = document.getElementById('entPie').getContext('2d');
    const entLabels = Object.keys(des.ent_distribution).map(k => k.toUpperCase());
    const entVals = Object.values(des.ent_distribution);
    if (entChart) try { entChart.destroy(); } catch(e){}
    entChart = new Chart(entCanvas, { type: 'doughnut', data: { labels: entLabels, datasets: [{ data: entVals, backgroundColor: ['#2f6bed','#06b6d4','#10b981'] }] }, options: { plugins:{legend:{position:'bottom'}} } });
    
    // ENT Distribution description
    const total_ent = entVals.reduce((a,b) => a+b, 0);
    if (total_ent > 0) {
        const entries = entLabels.map((l, i) => ({ label: l, count: entVals[i] })).sort((a, b) => b.count - a.count);
        const entDesc = entries.map(e => `${e.label}: ${e.count}`).join(', ');
        document.getElementById('entDesc').innerText = entDesc;
    }

    // Daily/Monthly visits chart
    const dailyCtx = document.getElementById('dailyLine').getContext('2d');
    const labels = des.daily_series.map(r => r.date).map(d=>formatShort(d));
    const data = des.daily_series.map(r => r.count);
    if (dailyChart) try { dailyChart.destroy(); } catch(e){}
    dailyChart = new Chart(dailyCtx, { type: 'line', data: { labels, datasets:[{ label:'Daily Visits', data, borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,0.08)', fill:true, tension:0.3 }] }, options:{ plugins:{legend:{display:false}}, scales:{ y:{ beginAtZero:true } } } });
    
    // Populate avg daily visits stat
    if (data.length > 0) {
        const avg = (data.reduce((a,b) => a+b, 0) / data.length).toFixed(1);
        document.getElementById('avgDailyVisits').innerText = avg;
        document.getElementById('avgDailyVisitsDesc').innerText = 'visits/day for this time';
    }
    
    // Populate descriptive insights
    if (data.length > 0) {
        const avg = (data.reduce((a,b) => a+b, 0) / data.length).toFixed(1);
        const max = Math.max(...data);
        const maxIdx = data.indexOf(max);
        const peakDate = formatShort(des.daily_series[maxIdx].date);
        document.getElementById('insight1').innerText = `Ear visits create lead with ${entVals[0]} visits (${((entVals[0]/total_ent)*100).toFixed(0)}% of volume)`;
        document.getElementById('insight2').innerText = `Thu is the busiest day (${max} visits) while Mon is the lightest.`;
        document.getElementById('insight3').innerText = `Average lead is ${avg} visits/day for the all time.`;
    }
}

function renderPredictive(pred, des, start, end) {
    const container = document.getElementById('predictiveSummary');
    container.innerHTML = '';
    if (!pred || pred.horizon === 0 || !pred.forecast_rows.length) {
        document.getElementById('forecastTotal').innerText = '—';
        document.getElementById('forecastTotalDesc').innerText = '';
        if (forecastChart) try { forecastChart.destroy(); } catch(e){}
        document.getElementById('forecastDesc').innerText = '';
        return;
    }

    // quick summary cards: horizon total, peak, min
    const total = pred.forecast_rows.reduce((s,r)=>s+r.value,0);
    const peak = pred.forecast_rows.reduce((p,r)=> r.value>p.value?r:p, pred.forecast_rows[0]);
    const min = pred.forecast_rows.reduce((p,r)=> r.value<p.value?r:p, pred.forecast_rows[0]);

    // Populate forecast stat card
    document.getElementById('forecastTotal').innerText = Math.round(total);
    document.getElementById('forecastTotalDesc').innerText = `Peak: ${peak.label} - ${Math.round(peak.value)}`;

    // Render summary cards inside predictive section
    const cards = [
        { title: `Forecast (${pred.horizon} days)`, value: Math.round(total)},
        { title: `Peak`, value: `${peak.label} · ${Math.round(peak.value)}` },
        { title: `Slowest`, value: `${min.label} · ${Math.round(min.value)}` }
    ];
    for (const c of cards) {
        const el = document.createElement('div'); el.style.padding='8px 12px'; el.style.backgroundColor='#f8f9fa'; el.style.borderRadius='4px'; el.innerHTML = `<div style="font-size:0.75rem;color:#666;margin-bottom:0.25rem;">${c.title}</div><div style="font-size:1rem;font-weight:700;color:#333;">${c.value}</div>`; container.appendChild(el);
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
    
    // Forecast description
    const forecastAvg = (total / pred.horizon).toFixed(1);
    const forecastDesc = `Projected demand is ${Math.round(total)} visits over all-time outlook (14 days).`;
    document.getElementById('forecastDesc').innerText = forecastDesc;
    
    // Additional forecast insights
    const bulletPoints = [
        `Peak demand likely on ${peak.label} (~${Math.round(peak.value)} visits).`,
        `Slowest day expected on ${min.label} (~${Math.round(min.value)} visits).`
    ];
    // Could populate additional insight elements if needed
}

function computeDescriptiveRange(preset, origStart, origEnd) {
    // Returns [start, end] for descriptive graphs according to preset rules
    const toISO = d => d.toISOString().slice(0,10);
    const endDate = new Date(origEnd + 'T00:00:00');
    endDate.setHours(0,0,0,0);

    if (preset === 'today') {
        // last 7 days ending today
        const s = new Date(endDate);
        s.setDate(s.getDate() - 6);
        return [toISO(s), toISO(endDate)];
    }

    if (preset === 'week') {
        // current week Monday - Sunday that contains endDate
        const dow = endDate.getDay(); // 0 (Sun) - 6 (Sat)
        const diffToMonday = (dow + 6) % 7; // 0 for Monday
        const monday = new Date(endDate);
        monday.setDate(endDate.getDate() - diffToMonday);
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        return [toISO(monday), toISO(sunday)];
    }

    if (preset === 'month' || preset === 'custom') {
        // Show full month of the end date (29-31 days depending on month)
        const year = endDate.getFullYear();
        const month = endDate.getMonth();
        const first = new Date(year, month, 1);
        const last = new Date(year, month + 1, 0); // last day of month
        return [toISO(first), toISO(last)];
    }

    if (preset === 'all-time') {
        // wide range - let backend handle (use a very early date)
        return ['2000-01-01', toISO(endDate)];
    }

    // fallback - use provided range
    return [origStart, origEnd];
}

async function loadAndRender(origStart, origEnd, preset) {
    try {
        // descriptive graphs may use adjusted ranges while predictive uses the original selection
        const [descStart, descEnd] = computeDescriptiveRange(preset || 'all-time', origStart, origEnd);

        // fetch descriptive (adjusted range) and predictive (original range) in parallel
        const [descPayload, predPayload] = await Promise.all([
            fetchAnalytics(descStart, descEnd),
            fetchAnalytics(origStart, origEnd)
        ]);

        if (descPayload) renderDescriptive(descPayload.descriptive, descStart, descEnd);
        if (predPayload) renderPredictive(predPayload.predictive, predPayload.descriptive, origStart, origEnd);
    } catch (e) {
        console.error('Analytics load failed', e);
        alert('Failed to load analytics: ' + e.message);
    }
}

document.addEventListener('DOMContentLoaded', function(){
    const todayBtn = document.getElementById('todayBtn');
    const weekBtn = document.getElementById('weekBtn');
    const monthBtn = document.getElementById('monthBtn');
    const allTimeBtn = document.getElementById('allTimeBtn');
    const customRangeBtn = document.getElementById('customRangeBtn');
    const customRangeModal = document.getElementById('customRangeModal');
    const customStartDate = document.getElementById('customStartDate');
    const customEndDate = document.getElementById('customEndDate');
    const customApplyBtn = document.getElementById('customApplyBtn');
    const customCancelBtn = document.getElementById('customCancelBtn');

    let activePreset = 'all-time'; // Track active preset

    function setActiveButton(btnElement) {
        [todayBtn, weekBtn, monthBtn, allTimeBtn, customRangeBtn].forEach(btn => {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline-secondary');
        });
        if (btnElement) {
            btnElement.classList.remove('btn-outline-secondary');
            btnElement.classList.add('btn-primary');
        }
    }

    function setDates(start, end, preset) {
        activePreset = preset;
        loadAndRender(start, end, preset);
    }

    function getPresetDates(preset) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const end = today.toISOString().slice(0, 10);

        if (preset === 'today') {
            // For predictive: use last 7 days ending today
            const s = new Date(today);
            s.setDate(s.getDate() - 6);
            return [s.toISOString().slice(0,10), end];
        } else if (preset === 'week') {
            // Current calendar week Monday - Sunday
            const dow = today.getDay(); // 0 (Sun) - 6 (Sat)
            const diffToMonday = (dow + 6) % 7; // 0 for Monday
            const monday = new Date(today);
            monday.setDate(today.getDate() - diffToMonday);
            const sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6);
            return [monday.toISOString().slice(0,10), sunday.toISOString().slice(0,10)];
        } else if (preset === 'month') {
            // Full current month
            const start = new Date(today.getFullYear(), today.getMonth(), 1);
            const last = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            return [start.toISOString().slice(0,10), last.toISOString().slice(0,10)];
        } else if (preset === 'all-time') {
            return ['2000-01-01', end];
        }
        return [end, end];
    }

    function openCustomRangeModal() {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const endDefault = today.toISOString().slice(0, 10);
        const d30 = new Date(); d30.setDate(d30.getDate() - 29);
        const startDefault = d30.toISOString().slice(0, 10);
        
        customStartDate.value = startDefault;
        customEndDate.value = endDefault;
        customRangeModal.style.display = 'flex';
    }

    function closeCustomRangeModal() {
        customRangeModal.style.display = 'none';
    }

    // Modal close on background click
    customRangeModal.addEventListener('click', function(e) {
        if (e.target === customRangeModal) {
            closeCustomRangeModal();
        }
    });

    customCancelBtn.addEventListener('click', closeCustomRangeModal);

    customApplyBtn.addEventListener('click', function() {
        const s = customStartDate.value;
        const e = customEndDate.value;
        if (!s || !e) return alert('Please pick both start and end dates');
        if (new Date(s) > new Date(e)) return alert('Start date must be before end date');
        setDates(s, e, 'custom');
        setActiveButton(customRangeBtn);
        closeCustomRangeModal();
    });

    customRangeBtn.addEventListener('click', openCustomRangeModal);

    // Preset buttons
    todayBtn.addEventListener('click', function(){
        const [s, e] = getPresetDates('today');
        setDates(s, e, 'today');
        setActiveButton(todayBtn);
    });

    weekBtn.addEventListener('click', function(){
        const [s, e] = getPresetDates('week');
        setDates(s, e, 'week');
        setActiveButton(weekBtn);
    });

    monthBtn.addEventListener('click', function(){
        const [s, e] = getPresetDates('month');
        setDates(s, e, 'month');
        setActiveButton(monthBtn);
    });

    allTimeBtn.addEventListener('click', function(){
        const [s, e] = getPresetDates('all-time');
        setDates(s, e, 'all-time');
        setActiveButton(allTimeBtn);
    });

    // Initialize with All Time as default
    const analyticsPage = document.querySelector('.analytics-page');
    const totalPatientsVal = analyticsPage.getAttribute('data-total-patients');
    document.getElementById('totalPatients').innerText = totalPatientsVal || '0';
    document.getElementById('totalPatientsDesc').innerText = 'Registered in the system';
    
    const [startDefault, endDefault] = getPresetDates('all-time');
    loadAndRender(startDefault, endDefault, 'all-time');
    setActiveButton(allTimeBtn);
});
</script>

