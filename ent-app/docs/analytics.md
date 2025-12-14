# Analytics Dashboard — Developer Guide

This document explains how to extend the Analytics dashboard and backend APIs.

## Overview
- The analytics dashboard can be accessed at `/?page=analytics` and is currently available to users with the `admin` or `doctor` role.
- The page fetches data from `GET /api/analytics` and renders charts using Chart.js.

## Where to add charts
- Frontend: `public/pages/analytics.php` contains the initial scaffold. Add cards and canvas elements here.
- Backend: `api/AnalyticsController.php` contains the `index()` method that returns mock data. Switch to real queries by using `$this->db` to make SQL aggregated queries.
 - Notes:
   - The controller now attempts real DB queries and falls back to mock data if DB is inaccessible.
   - The controller sets `ETag` and `Cache-Control` to enable lightweight client caching.
   - The frontend lazily loads charts only when visible and stores persisted filter states in `localStorage`.

## Suggested API format
Return a JSON object with:
```
{
  "success": true,
  "data": {
    "summary": { "total_patients": 0, "appointments_completed": 0 },
    "visits_trend": { "labels": ["2025-01-01"], "data": [12] },
    "cancellations_by_reason": { "labels": ["No-show"], "data": [5] },
    "forecast": { "labels": ["2025-01-08"], "data": [10] }
  }
}
```

## Best Practices
- Query aggregation should be handled server-side using SQL `GROUP BY` and `COUNT()` to avoid large payloads.
- Cache results for commonly requested filter ranges and use `ETag`/`Last-Modified` for efficiency.
- For predictive analytics, start simple with rolling averages or linear regression before investing in complex ML pipelines.

## How to test
- PHP CLI tests: Run the existing PHP tests using the provided `run_tests.bat` (Windows) or `php` CLI on your system.
  - Example (PowerShell):
    ```powershell
    cd ent-app
    php scripts/run_migrations.php --dry-run
    php scripts/run_migrations.php
    php scripts/run_tests.bat
    ```
  - Note: Ensure PHP CLI is installed and available in PATH. If not, tests will fail to run.

- Playwright visual tests: Install Node and Playwright to run the end-to-end tests. From `ent-app`:
  ```bash
  npm init -y
  npm i -D @playwright/test
  npx playwright install
  # Update your package.json to include a test script for Playwright and run it.
  npx playwright test tests/playwright/analytics.spec.ts
  ```

When running tests in CI, ensure the `ALLOW_HEADER_AUTH` config is set and a test DB is available. Mock fallbacks are provided if DB access cannot be established.
 - Export & lazy load: Charts support download as PNG via the ‘Download’ buttons, and charts only load when the canvas enters the viewport (IntersectionObserver).

## Extending the page
- Add new cards and charts as needed; Chart.js charts can be created via Canvas elements.
- Maintain consistent colors and legend placement for clarity.

***

If you want, I can replace the mock controller with a real DB-backed controller for trends and counts next.