import { test, expect } from '@playwright/test';

const baseUrl = process.env.BASE_URL || 'http://localhost/ent-clinic-online/ent-app/public';

// This test requires the environment to support header-based auth via request context,
// or log in via the UI. Here we set a header as an example (Playwright supports route-level headers).

test('Analytics page renders charts and suggestions', async ({ page }) => {
  // Set admin authorization header via navigator for fetch (not a straightforward approach);
  await page.goto(baseUrl + '/?page=analytics', { waitUntil: 'networkidle' });
  await expect(page.locator('h2')).toContainText('Analytics');
  await expect(page.locator('#visitsTrendChart')).toBeVisible();
  await expect(page.locator('#forecastChartSmall')).toBeVisible();
  await expect(page.locator('#entDonutChart')).toBeVisible();
  await expect(page.locator('#hnlmoPie')).toBeVisible();
  await expect(page.locator('#visitsTrendSummary')).toBeVisible();
  await expect(page.locator('#forecastSummary')).toBeVisible();
  await expect(page.locator('#downloadTrend')).toBeVisible();
  await expect(page.locator('#analyticsSuggestions')).toBeVisible();

  // Ensure the forecast small chart appears visually below the visit trends chart
  const visitsBox = await page.locator('#visitsTrendChart').boundingBox();
  const forecastSmallBox = await page.locator('#forecastChartSmall').boundingBox();
  if (!visitsBox || !forecastSmallBox) throw new Error('Could not read chart positions');
  if (forecastSmallBox.y <= visitsBox.y) throw new Error('Forecast chart should be placed below Visit Trends');
});
