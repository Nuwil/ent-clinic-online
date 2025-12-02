import { holtWintersAdditive, holtLinear } from './holtWinters.js';
import { rollingMean, olsSlope, extrapolateFromSlope } from './movingAverage.js';
import { hybridForecast, winsorize } from './hybridForecast.js';

function median(arr) {
  const a = arr.slice().sort((x,y)=>x-y);
  const n = a.length; if (n===0) return 0; const mid = Math.floor(n/2);
  return (n%2===1)?a[mid]:(a[mid-1]+a[mid])/2;
}

function ensureNumber(v) { const n = Number(v); return isFinite(n)?n:0; }

// Create a new forecast chart replacing existing one
export function drawForecastChart(containerId, actualDates, actualData, forecastDates, forecastVals) {
  const canvas = document.getElementById(containerId);
  if (!canvas) return null;
  // destroy existing Chart instance if present
  if (canvas._chartInstance) { try { canvas._chartInstance.destroy(); } catch(e){} }

  const allLabels = [...actualDates, ...forecastDates];
  const actualChartData = [...actualData, ...Array(forecastVals.length).fill(null)];
  const forecastChartData = [...Array(actualData.length).fill(null), ...forecastVals];

  const cfg = {
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
          pointRadius: 4
        },
        {
          label: 'Forecast',
          data: forecastChartData,
          borderColor: 'rgba(249, 115, 22, 0.95)',
          backgroundColor: 'rgba(249, 115, 22, 0.08)',
          fill: true,
          tension: 0.4,
          borderWidth: 3,
          pointRadius: 4,
          borderDash: [5,5]
        }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'top' } },
      scales: { y: { beginAtZero: true } }
    }
  };

  const ctx = canvas.getContext('2d');
  const chart = new Chart(ctx, cfg);
  canvas._chartInstance = chart;
  return chart;
}

// Hook: call this when user clicks Run Forecast
export function runClientForecast(opts = {}) {
  // extract existing embedded data
  const rawDaily = window.dailyTrend || [];
  // dailyTrend expected to be array of {date, count}
  const counts = rawDaily.map(d => ensureNumber(d.count));
  const dates = rawDaily.map(d => d.date);
  const method = opts.method || 'holt';
  const horizon = opts.horizon || 14;

  // pre-clean (winsorize) for robustness
  const clean = winsorize(counts, 3);
  let forecastVals = [];
  let season = [];
  let components = {};

  if (method === 'holt') {
    const hw = holtWintersAdditive(clean, 7, horizon, opts.alpha ?? 0.3, opts.beta ?? 0.05, opts.gamma ?? 0.2);
    forecastVals = hw.forecast.map(v => Math.max(0, v));
    season = hw.season;
    components = { level: hw.level.slice(-1)[0], trend: hw.trend.slice(-1)[0] };
  } else if (method === 'holt-linear') {
    const hw = holtLinear(clean, horizon, opts.alpha ?? 0.3, opts.beta ?? 0.05);
    forecastVals = hw.forecast.map(v => Math.max(0, v));
    components = { level: hw.level.slice(-1)[0], trend: hw.trend.slice(-1)[0] };
  } else if (method === 'ma') {
    const window = opts.window || 7;
    const sm = rollingMean(clean, window);
    const slope = olsSlope(sm, Math.min(30, Math.floor(counts.length/2) || 14));
    const last = sm[sm.length-1] || counts[counts.length-1] || 0;
    forecastVals = extrapolateFromSlope(last, slope, horizon).map(v => Math.max(0, v));
    components = { lastSmoothed: last, slope };
  } else if (method === 'hybrid') {
    const res = hybridForecast(clean, { seasonLength:7, nPred:horizon, alpha:opts.alpha, beta:opts.beta, gamma:opts.gamma });
    forecastVals = res.blended.map(v => Math.max(0, v));
    components = { hwTrend: res.hwTrend, maSlope: res.maSlope, blendMA: res.blendMA };
  }

  // build forecastDates (use last known date + 1..horizon)
  const lastDate = dates[dates.length - 1] ? new Date(dates[dates.length - 1] + 'T00:00:00') : new Date();
  const forecastDates = [];
  for (let i = 1; i <= horizon; i++) {
    const d = new Date(lastDate);
    d.setDate(d.getDate() + i);
    forecastDates.push(d.toISOString().split('T')[0]);
  }

  // draw chart (short labels)
  const shortActual = dates.map(d => (d ? new Date(d+'T00:00:00').toLocaleDateString() : ''));
  const shortForecast = forecastDates.map(d => new Date(d+'T00:00:00').toLocaleDateString());
  drawForecastChart('forecastChart', shortActual, counts, shortForecast, forecastVals);

  // populate interpretability area
  const info = document.getElementById('clientForecastInfo');
  if (info) {
    info.innerText = JSON.stringify(components, null, 2);
  }
}

// Expose default action when module loaded in browser
document.addEventListener('DOMContentLoaded', () => {
  const runBtn = document.getElementById('runClientForecastBtn');
  if (runBtn) {
    runBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const method = document.getElementById('clientMethodSelect').value;
      const horizon = parseInt(document.getElementById('clientHorizon').value || '14', 10);
      runClientForecast({ method, horizon });
    });
  }
});
