import { holtWintersAdditive } from './holtWinters.js';
import { rollingMean, olsSlope, extrapolateFromSlope } from './movingAverage.js';

function median(arr) {
  const a = arr.slice().sort((x,y)=>x-y);
  const n = a.length;
  if (n === 0) return 0;
  const mid = Math.floor(n/2);
  return (n%2===1) ? a[mid] : (a[mid-1]+a[mid])/2;
}

export function winsorize(data, k = 3) {
  const med = median(data);
  const devs = data.map(v => Math.abs(v - med));
  const mad = median(devs) || 1;
  const lower = med - k * mad;
  const upper = med + k * mad;
  return data.map(v => Math.min(upper, Math.max(lower, v)));
}

export function hybridForecast(data, opts = {}) {
  const seasonLength = opts.seasonLength ?? 7;
  const nPred = opts.nPred ?? 14;
  const clean = winsorize(data, 3);
  const hw = holtWintersAdditive(clean, seasonLength, nPred, opts.alpha ?? 0.3, opts.beta ?? 0.05, opts.gamma ?? 0.2);
  const smoothed = rollingMean(clean, 7);
  const maSlope = olsSlope(smoothed, Math.min(30, Math.floor(data.length/2) || 14));
  const hwTrend = (hw.trend && hw.trend.length) ? hw.trend[hw.trend.length - 1] : 0;

  const magnitudeThreshold = Math.max(0.03, 0.02 * (median(data) || 10));
  let blendMA = 0;
  if (Math.abs(maSlope) > Math.abs(hwTrend) && Math.abs(maSlope) > magnitudeThreshold) {
    const ratio = Math.min(5, Math.abs(maSlope) / (Math.abs(hwTrend) + 1e-9));
    blendMA = Math.min(0.9, 0.2 + 0.2 * Math.sqrt(ratio));
  }

  const hwForecast = hw.forecast;
  const lastValue = smoothed[smoothed.length - 1] || data[data.length - 1];
  const maForecast = extrapolateFromSlope(lastValue, maSlope, nPred);

  const blended = [];
  for (let i = 0; i < nPred; i++) {
    blended.push((1 - blendMA) * hwForecast[i] + blendMA * maForecast[i]);
  }

  return { hw, smoothed, maSlope, hwTrend, blendMA, blended };
}
