// Additive Holt-Winters (triple exponential smoothing)
export function holtWintersAdditive(data, seasonLength = 7, nPred = 14, alpha = 0.3, beta = 0.05, gamma = 0.2) {
  const n = data.length;
  if (n === 0) throw new Error('Empty data');
  if (n < seasonLength * 2) {
    return holtLinear(data, nPred, alpha, beta);
  }

  // initialize seasonality by subtracting local means
  const season = new Array(seasonLength).fill(0);
  const globalMean = data.reduce((a, b) => a + b, 0) / n;
  for (let i = 0; i < seasonLength; i++) {
    let sum = 0, count = 0;
    for (let j = i; j < n; j += seasonLength) { sum += data[j]; count++; }
    season[i] = (sum / Math.max(1, count)) - globalMean;
  }

  const level = [];
  const trend = [];
  const fitted = [];

  const firstSeasonAvg = data.slice(0, seasonLength).reduce((a,b)=>a+b,0)/seasonLength;
  const secondSeasonAvg = data.slice(seasonLength, seasonLength*2).reduce((a,b)=>a+b,0)/seasonLength;
  level[0] = firstSeasonAvg;
  trend[0] = (secondSeasonAvg - firstSeasonAvg) / seasonLength;

  for (let t = 0; t < n; t++) {
    const y = data[t];
    if (t === 0) {
      fitted.push(level[0] + trend[0] + season[0]);
      continue;
    }
    const lastLevel = level[t - 1];
    const lastTrend = trend[t - 1];
    const seasonIdx = t % seasonLength;

    const newLevel = alpha * (y - season[seasonIdx]) + (1 - alpha) * (lastLevel + lastTrend);
    level.push(newLevel);
    const newTrend = beta * (newLevel - lastLevel) + (1 - beta) * lastTrend;
    trend.push(newTrend);

    season[seasonIdx] = gamma * (y - newLevel) + (1 - gamma) * season[seasonIdx];

    const fit = lastLevel + lastTrend + season[seasonIdx];
    fitted.push(fit);
  }

  const forecast = [];
  const lastLevel = level[level.length - 1];
  const lastTrend = trend[trend.length - 1];
  for (let m = 1; m <= nPred; m++) {
    const s = season[(n + m - 1) % seasonLength];
    forecast.push(lastLevel + m * lastTrend + s);
  }

  return { level, trend, season, fitted, forecast };
}

export function holtLinear(data, nPred = 14, alpha = 0.3, beta = 0.05) {
  const n = data.length;
  if (n === 0) throw new Error('Empty data');
  const level = [];
  const trend = [];
  const fitted = [];
  level[0] = data[0];
  trend[0] = n > 1 ? data[1] - data[0] : 0;
  for (let t = 1; t < n; t++) {
    const y = data[t];
    const newLevel = alpha * y + (1 - alpha) * (level[t - 1] + trend[t - 1]);
    level.push(newLevel);
    const newTrend = beta * (newLevel - level[t - 1]) + (1 - beta) * trend[t - 1];
    trend.push(newTrend);
    fitted.push(level[t - 1] + trend[t - 1]);
  }
  const forecast = [];
  const lastLevel = level[level.length - 1];
  const lastTrend = trend[trend.length - 1];
  for (let m = 1; m <= nPred; m++) forecast.push(lastLevel + m * lastTrend);
  return { level, trend, season: [], fitted, forecast };
}
