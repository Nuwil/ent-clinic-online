export function rollingMean(data, window = 7) {
  if (window <= 1) return data.slice();
  const n = data.length;
  const out = new Array(n).fill(NaN);
  const half = Math.floor(window / 2);
  for (let i = 0; i < n; i++) {
    const start = Math.max(0, i - half);
    const end = Math.min(n, i + half + 1);
    let sum = 0, count = 0;
    for (let j = start; j < end; j++) { sum += data[j]; count++; }
    out[i] = sum / count;
  }
  return out;
}

export function olsSlope(series, len = 14) {
  const n = series.length;
  const start = Math.max(0, n - len);
  let sx = 0, sy = 0, sxx = 0, sxy = 0;
  let count = 0;
  for (let i = start; i < n; i++) {
    const x = count;
    const y = series[i];
    if (!isFinite(y)) { count++; continue; }
    sx += x; sy += y; sxx += x * x; sxy += x * y; count++;
  }
  if (count < 2) return 0;
  const denom = (count * sxx - sx * sx);
  if (Math.abs(denom) < 1e-9) return 0;
  const slope = (count * sxy - sx * sy) / denom;
  return slope;
}

export function extrapolateFromSlope(lastValue, slope, nPred = 14) {
  const out = [];
  for (let m = 1; m <= nPred; m++) out.push(lastValue + slope * m);
  return out;
}
