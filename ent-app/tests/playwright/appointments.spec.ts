import { test, expect } from '@playwright/test';
const baseUrl = process.env.BASE_URL || 'http://localhost/ent-clinic-online/ent-app/public';

test('Booking modal and visit modal should not include Emergency option', async ({ page }) => {
  await page.goto(baseUrl + '/?page=appointments', { waitUntil: 'networkidle' });
  // Open book modal
  await page.locator('#todayBtn').click();
  await page.locator('.day-appointments').first().click();
  await expect(page.locator('#bookModal')).toBeVisible();
  const bookType = await page.locator('#bookType');
  const opt = await bookType.locator('option[value="emergency"]');
  await expect(opt).toHaveCount(0);

  // Open patient profile and edit visit modal
  await page.goto(baseUrl + '/?page=patients', { waitUntil: 'networkidle' });
  const pLink = page.locator('.patients-table a').first();
  await expect(pLink).toBeVisible();
  await pLink.click();
  await page.locator('#openEditProfileBtn').click();
  await expect(page.locator('#editProfileModal')).toBeVisible();
  const visitOpt = page.locator('select[name="visit_type"] option[value="Emergency"]');
  await expect(visitOpt).toHaveCount(0);
});