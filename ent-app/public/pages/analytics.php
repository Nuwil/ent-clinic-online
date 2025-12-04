<?php
/**
 * Simplified Analytics Page
 * - Descriptive Analysis (summary + charts)
 * - Predictive Analysis (forecast) — horizon adapts to selected date range
 */
requireRole(['admin', 'doctor']);
?>

<div class="analytics-page">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h2 style="margin:0;font-size:1.5rem;">Analytics</h2>
            <p class="text-muted" style="margin:0.25rem 0 0 0;">Descriptive and Predictive analyses. Select a date range to update both sections.</p>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.5rem;flex-wrap:wrap;">
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                <button type="button" id="todayBtn" class="btn btn-outline-secondary" style="font-size:0.85rem;padding:0.4rem 0.8rem;">Today</button>
                <button type="button" id="weekBtn" class="btn btn-outline-secondary" style="font-size:0.85rem;padding:0.4rem 0.8rem;">This Week</button>
                <button type="button" id="monthBtn" class="btn btn-outline-secondary" style="font-size:0.85rem;padding:0.4rem 0.8rem;">This Month</button>
                <button type="button" id="allTimeBtn" class="btn btn-primary" style="font-size:0.85rem;padding:0.4rem 0.8rem;">All Time</button>
                <button type="button" id="customRangeBtn" class="btn btn-outline-secondary" style="font-size:0.85rem;padding:0.4rem 0.8rem;">Custom Range</button>
                <button type="button" id="refreshBtn" class="btn btn-success" style="font-size:0.85rem;padding:0.4rem 0.8rem;margin-left:auto;" title="Reload charts with current date range">🔄 Refresh</button>
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

    <div id="descriptive" style="margin-bottom:1.25rem;">
        <h3 style="margin:0 0 0.5rem 0;font-size:1.05rem;">Descriptive Analysis</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:12px;">
            <div class="card" style="padding:12px;">
                <div style="font-size:0.85rem;color:#666;">Total Visits (range)</div>
                <div id="totalVisits" style="font-size:1.5rem;font-weight:700;">—</div>
                <div id="totalVisitsDesc" style="font-size:0.8rem;color:#888;margin-top:0.5rem;line-height:1.3;"></div>
            </div>
            <div class="card" style="padding:12px;">
                <div style="font-size:0.85rem;color:#666;">ENT Distribution</div>
                <canvas id="entPie" style="height:80px;"></canvas>
                <div id="entDesc" style="font-size:0.8rem;color:#888;margin-top:0.5rem;line-height:1.3;"></div>
                <div id="entLegend" style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;font-size:0.85rem;color:#444;"></div>
            </div>
            <div class="card" style="padding:12px;">
                <div style="font-size:0.85rem;color:#666;">Daily Visits</div>
                <canvas id="dailyLine" style="height:80px;"></canvas>
                <div id="dailyDesc" style="font-size:0.8rem;color:#888;margin-top:0.5rem;line-height:1.3;"></div>
            </div>
        </div>
    </div>

    <div id="predictive">
        <h3 style="margin:0 0 0.5rem 0;font-size:1.05rem;">Predictive Analysis</h3>
        <div id="predictiveSummary" style="display:flex;gap:12px;margin-bottom:12px;flex-wrap:wrap;"></div>
        <div class="card" style="padding:12px;">
            <canvas id="forecastChart" style="height:200px;width:100%;"></canvas>
            <div id="forecastDesc" style="font-size:0.8rem;color:#888;margin-top:0.75rem;line-height:1.3;"></div>
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
    const url = `${API_BASE}/api.php?route=/api/analytics&start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}&t=${Date.now()}`;
    // Use no-store cache and pragma to prevent caching; include timestamp for cache busting
    const res = await fetch(url, { 
        credentials: 'same-origin', 
        cache: 'no-store',
        headers: { 'Pragma': 'no-cache', 'Cache-Control': 'no-cache' }
    });
    if (!res.ok) throw new Error('API error ' + res.status);
    const payload = await res.json();
    try { console.debug('fetchAnalytics', start, end, 'ent_distribution:', payload.data?.descriptive?.ent_distribution); } catch(e) {}
    return payload.data || payload;
}

let entChart = null, dailyChart = null, forecastChart = null;

function formatENTLabel(key) {
    const map = {
        'ear': 'Ear',
        'nose': 'Nose',
        'throat': 'Throat',
        'head_neck_tumor': 'Head & Neck Tumors',
        'lifestyle_medicine': 'Lifestyle Medicine',
        'misc': 'Misc/Others'
    };
    return map[key] || key.toUpperCase();
}

function getENTColors() {
    // 6 distinct colors for 6 ENT categories
    return ['#2f6bed', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
}

function renderDescriptive(des, start, end) {
    document.getElementById('totalVisits').innerText = des.total_visits;
    
    // Total visits description
    const rangeStr = formatRange(start, end);
    const totalDesc = des.total_visits === 1 
        ? `There was 1 visit in ${rangeStr}.`
        : `There were ${des.total_visits} visits in ${rangeStr}.`;
    document.getElementById('totalVisitsDesc').innerText = totalDesc;

    // Build canvas + data for ENT distribution, filtering out zero-value categories for clarity
    const entCanvas = document.getElementById('entPie').getContext('2d');
    // Use a canonical list of ENT keys so the chart always displays the full set of classifications
    const canonicalEntKeys = ['ear','nose','throat','head_neck_tumor','lifestyle_medicine','misc'];
    const entKeys = canonicalEntKeys;
    const entLabels = entKeys.map(k => formatENTLabel(k));
    const entColors = getENTColors();

    // Raw numeric values (ensure numeric coercion and fallback to 0)
    const entValsRaw = entKeys.map((k) => Number((des.ent_distribution && des.ent_distribution[k]) ? des.ent_distribution[k] : 0));

    // Show all categories by default so classifications are always visible.
    // For categories with zero count, use a muted gray color so they appear but are visually de-emphasized.
    let finalLabels = entLabels.slice();
    let finalVals = entValsRaw.slice();
    let finalColors = finalLabels.map((_, i) => (finalVals[i] > 0 ? entColors[i % entColors.length] : '#e6e6e6'));

    // Debug: what data the chart will render
    try { console.debug('ENT distribution (canonical):', entKeys, entValsRaw, 'finalLabels:', finalLabels, 'finalVals:', finalVals); } catch(e) {}

    if (entChart) try { entChart.destroy(); } catch(e){}

    // Register datalabels plugin if available
    try {
        if (typeof Chart !== 'undefined' && typeof Chart.register === 'function') {
            if (typeof ChartDataLabels !== 'undefined') {
                Chart.register(ChartDataLabels);
            } else if (typeof chartjs_plugin_datalabels !== 'undefined') {
                Chart.register(chartjs_plugin_datalabels);
            }
        }
    } catch(e) { /* ignore registration errors */ }

    entChart = new Chart(entCanvas, {
        type: 'doughnut',
        data: { labels: finalLabels, datasets: [{ data: finalVals, backgroundColor: finalColors }] },
        options: {
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 12 },
                    // Make legend entries clickable to toggle slices
                    onClick: function(evt, legendItem, legend) {
                        const ci = legend.chart;
                        const index = legendItem.index;
                        const meta = ci.getDatasetMeta(0);
                        if (meta && meta.data && meta.data[index]) {
                            meta.data[index].hidden = !meta.data[index].hidden;
                            ci.update();
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed !== undefined ? context.parsed : (context.raw || 0);
                            const total = context.chart.data.datasets[0].data.reduce((s, v) => s + (Number(v) || 0), 0);
                            const pct = total ? Math.round((Number(value) / total) * 100) : 0;
                            return label + ': ' + value + ' (' + pct + '%)';
                        }
                    }
                },
                datalabels: {
                    // Show percent labels; place outside for very small slices
                    formatter: function(value, ctx) {
                        const total = ctx.dataset.data.reduce((s, v) => s + (Number(v) || 0), 0);
                        if (!total) return '';
                        const pct = Math.round((Number(value) / total) * 100);
                        return pct > 0 ? pct + '%' : '';
                    },
                    color: '#fff',
                    font: { weight: '600', size: 11 },
                    anchor: function(ctx) {
                        const value = Number(ctx.dataset.data[ctx.dataIndex] || 0);
                        const total = ctx.dataset.data.reduce((s, v) => s + (Number(v) || 0), 0);
                        const pct = total ? (value / total) : 0;
                        return pct < 0.08 ? 'end' : 'center';
                    },
                    align: function(ctx) {
                        const value = Number(ctx.dataset.data[ctx.dataIndex] || 0);
                        const total = ctx.dataset.data.reduce((s, v) => s + (Number(v) || 0), 0);
                        const pct = total ? (value / total) : 0;
                        return pct < 0.08 ? 'end' : 'center';
                    },
                    offset: function(ctx) {
                        const value = Number(ctx.dataset.data[ctx.dataIndex] || 0);
                        const total = ctx.dataset.data.reduce((s, v) => s + (Number(v) || 0), 0);
                        const pct = total ? (value / total) : 0;
                        return pct < 0.08 ? 12 : 0;
                    },
                    clamp: false
                }
            }
        }
    });

    // ENT Distribution description and textual legend
    const total_ent = entValsRaw.reduce((a,b) => a + (Number(b)||0), 0);
    if (total_ent > 0) {
        // Build entries for the legend showing all classifications; sort by count desc so most important appear first
        const entries = finalLabels.map((l, i) => ({ label: l, count: finalVals[i], pct: Math.round((finalVals[i] / total_ent) * 100), color: finalColors[i] })).sort((a,b)=>b.count-a.count);
        const entDesc = entries.map(e => `${e.label}: ${e.count}`).join(', ');
        document.getElementById('entDesc').innerText = entDesc;

        // Populate textual legend
        const legendEl = document.getElementById('entLegend');
        legendEl.innerHTML = '';
        for (const e of entries) {
            const span = document.createElement('div');
            span.style.display = 'inline-flex';
            span.style.alignItems = 'center';
            span.style.gap = '8px';
            span.style.padding = '6px 8px';
            span.style.border = '1px solid #eee';
            span.style.borderRadius = '6px';
            const color = e.color || '#ddd';
            const sw = `<span style="width:12px;height:12px;background:${color};display:inline-block;border-radius:3px;opacity:${e.count?1:0.45};"></span>`;
            span.innerHTML = `${sw} <strong>${e.label}</strong> &nbsp; ${e.count} (${e.pct}%)`;
            legendEl.appendChild(span);
        }
    } else {
        document.getElementById('entDesc').innerText = '';
        document.getElementById('entLegend').innerHTML = '';
    }

    const dailyCtx = document.getElementById('dailyLine').getContext('2d');
    const labels = des.daily_series.map(r => r.date).map(d=>formatShort(d));
    const data = des.daily_series.map(r => r.count);
    if (dailyChart) try { dailyChart.destroy(); } catch(e){}
    dailyChart = new Chart(dailyCtx, { type: 'line', data: { labels, datasets:[{ label:'Daily Visits', data, borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,0.08)', fill:true, tension:0.3 }] }, options:{ plugins:{legend:{display:false}}, scales:{ y:{ beginAtZero:true } } } });
    
    // Daily Visits description
    if (data.length > 0) {
        const avg = (data.reduce((a,b) => a+b, 0) / data.length).toFixed(1);
        const max = Math.max(...data);
        const maxIdx = data.indexOf(max);
        const peakDate = formatShort(des.daily_series[maxIdx].date);
        const dailyDesc = `Average: ${avg} visits/day. Peak: ${peakDate} (${max} visits).`;
        document.getElementById('dailyDesc').innerText = dailyDesc;
    }
}

function renderPredictive(pred, des, start, end) {
    const container = document.getElementById('predictiveSummary');
    container.innerHTML = '';
    if (!pred || pred.horizon === 0 || !pred.forecast_rows.length) {
        container.innerHTML = '<div class="card" style="padding:12px;">No forecast for selected range (need >1 day)</div>';
        if (forecastChart) try { forecastChart.destroy(); } catch(e){}
        document.getElementById('forecastDesc').innerText = '';
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
    
    // Forecast description
    const forecastAvg = (total / pred.horizon).toFixed(1);
    const forecastDesc = `Forecast shows ~${forecastAvg} visits/day over the next ${pred.horizon} days. Peak expected on ${peak.label} with ~${Math.round(peak.value)} visits.`;
    document.getElementById('forecastDesc').innerText = forecastDesc;
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
    const refreshBtn = document.getElementById('refreshBtn');
    const customRangeModal = document.getElementById('customRangeModal');
    const customStartDate = document.getElementById('customStartDate');
    const customEndDate = document.getElementById('customEndDate');
    const customApplyBtn = document.getElementById('customApplyBtn');
    const customCancelBtn = document.getElementById('customCancelBtn');

    let activePreset = 'all-time'; // Track active preset
    let currentStart = null, currentEnd = null; // Track current date range for refresh

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
        currentStart = start;
        currentEnd = end;
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

    // Refresh button: reload charts with current date range
    refreshBtn.addEventListener('click', function(){
        if (currentStart && currentEnd) {
            loadAndRender(currentStart, currentEnd, activePreset);
            console.debug('Charts refreshed for range', currentStart, 'to', currentEnd);
        }
    });

    // Auto-refresh when page regains focus (user switches back to the tab)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && currentStart && currentEnd) {
            console.debug('Page visible again, auto-refreshing analytics...');
            loadAndRender(currentStart, currentEnd, activePreset);
        }
    });

    // Initialize with All Time as default
    const [startDefault, endDefault] = getPresetDates('all-time');
    setDates(startDefault, endDefault, 'all-time');
    setActiveButton(allTimeBtn);
});
</script>

