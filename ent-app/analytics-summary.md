# Analytics Page — Summary & Predictive Analysis

_Last updated: 2025-12-01_

This document summarizes the functionality of the Analytics page and explains how the predictive analysis (forecast) works, including data inputs, logic, outputs, limitations, and potential improvements.

---

## 1. Purpose
- Provide descriptive and predictive reporting on patient/visit volume and ENT case mix for a selected date range.
- Help clinic operators understand historical load (daily/weekly/monthly), case mix (ear/nose/throat), and short-term demand forecasts.

## 2. Key Features
- Controls
  - Preset filters: **All Time**, **Today**, **This Week**, **This Month**.
  - Custom range: pick start and end dates.
  - Toggle between monthly and weekly breakdowns.
- Visualizations (Chart.js)
  - Donut chart: ENT distribution (Ear / Nose / Throat / Unspecified).
  - Visits chart: Bar chart showing weekly (Mon–Sun) or monthly (Jan–Dec) counts.
  - Forecast chart: Line chart showing historical counts and dashed forecast lines for the forecast horizon.
- Summary and Insights
  - Summary cards: Total Patients, Visits (selected range), Avg Daily Visits, Forecast summary.
  - Descriptive insights: top diagnosis, busiest/quiet days, average daily load.
  - Predictive insights: total forecast, peak/slowest day with estimated counts.
- UX details
  - Auto-refresh for weekly view at next Monday midnight.
  - Graceful handling for insufficient data (shows "Not enough signal...").

## 3. Data Inputs
- The frontend requests analytics from backend via `window.electronAPI.getAnalytics(filterRange)`.
- Backend returns an `analytics` object with any of these fields (prioritized):
  - `allVisits` or `visits`: raw visit records (objects containing at least `date`, and optionally `diagnosisType`, `diagnosis`, etc.)
  - `dailyVisits`: pre-aggregated daily counts
  - `monthlyVisits`: pre-aggregated monthly counts
  - `entCounts`: counts for `ear`, `nose`, `throat` (optional)
  - `totalPatients`, `totalVisits`
- Frontend prefers raw visit arrays and falls back to aggregated series when needed.

## 4. Forecasting Logic (High-level)
The forecasting pipeline is a hybrid, interpretable model that combines a linear trend extrapolation with multiplicative weekday seasonality factors.

Steps:
1. Build daily time series of visit counts (group visits by date) using `buildTimeSeries`.
2. Choose a forecast plan (`getForecastPlan`) based on filter (e.g., 1 day for "today", 7 days for "week", days-in-month for "month", or a clamped horizon for "all").
3. Select a history window for trend estimation — either full history (for `all`/`custom`) or a recent window (bounded by min/max windows, e.g., 7–90 days).
4. Fit a linear trend model (`buildTrendModel`) to the recent window using ordinary least squares on indices vs. visits. The model returns a `predict(daysAhead)` function.
5. Compute weekday seasonality multipliers (`buildSeasonalityFactors`) by averaging visits per weekday and normalizing by the global average. This yields multiplicative weekday factors (e.g., Mon = 1.2, Sun = 0.7).
6. Generate forecast points for each future day in the horizon:
   - baseLevel = trendModel.predict(daysAhead)
   - weekdayFactor = seasonalityFactors[weekday]
   - predicted = round(max(0, baseLevel * weekdayFactor))
7. Package combined labels, `actualDataset` (historical), and `forecastDataset` (historical padding + predicted values). Build a `summary` with total forecast, peak and slowest days.

## 5. Implementation Details (key functions)
- `buildTimeSeries(analytics, filterConfig)` — groups raw visits or maps pre-aggregated daily arrays into labeled time-series entries.
- `getForecastPlan(filterConfig, seriesLength)` — returns forecast `horizon`, `label`, and whether to `useFullHistory`.
- `buildTrendModel(series)` — fits simple linear regression on recent samples (least-squares), returning `.predict(daysAhead)`.
- `buildSeasonalityFactors(analytics, series)` — calculates weekday multipliers based on weekday averages normalized by the global average.
- `generateForecastSeries(analytics, series, filterConfig)` — orchestrates the forecast: selects history window, calls trend & seasonality, iterates to build forecast points and summary.

## 6. Outputs & UI Mapping
- Forecast chart: shows combined historical labels and future labels. Historical values are in `actualDataset`; forecasts in `forecastDataset` (with dashed styling).
- Forecast summary object contains:
  - `total`: sum of forecasted visits over horizon
  - `peakDay` / `peakValue`
  - `minDay` / `minValue`
  - `windowLabel`: human-friendly forecast label (e.g., "Next 7 Days")
- Predictive insights are textual bullets generated from the forecast summary.

## 7. Limitations
- Model = linear trend + weekday multiplier. No advanced smoothing, autoregression, or holiday handling.
- Sensitive to outliers and abrupt shifts (closures, campaigns, holidays).
- No uncertainty estimates (no confidence interval) or error metrics shown.
- Seasonality limited to weekday effects only.

## 8. Suggested Improvements
- Add validation / backtesting utilities (compute MAE / RMSE on holdout windows) and show accuracy metrics.
- Replace or augment trend model with exponential smoothing (Holt-Winters) or a small ARIMA / Prophet implementation for better seasonal modeling and confidence intervals.
- Add robust outlier handling (clipping or winsorizing) or smoothing (moving average) before model fitting.
- Optionally move forecasting server-side (Node or Python) to use established forecasting libraries and return predictions and error bands to the frontend.

## 9. Where to find the code
- Frontend: `src/renderer/pages/AnalyticsPage.jsx` — UI + forecasting helper functions: `buildTimeSeries`, `buildTrendModel`, `buildSeasonalityFactors`, `generateForecastSeries`, etc.
- Backend: `src/main/database.cjs` — `getAnalytics()` and `getAnalyticsWithFilter(startDate, endDate)` returning the analytics object used by the frontend.

## 10. Next steps I can take (pick one)
- Add a small holdout/backtest function to compute MAE/RMSE and write results to the console or UI.
- Implement Holt-Winters smoothing and compare forecasts vs current linear+seasonality method.
- Move forecasting to a server-side script using Python/statsmodels/Prophet and deliver richer forecasts with uncertainty.

---

If you want, I can convert this to plain `.txt` instead, or add a small example showing how to compute MAE/RMSE and run a quick backtest in the repo. Which do you prefer?