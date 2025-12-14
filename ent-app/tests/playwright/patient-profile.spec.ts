import { test, expect } from '@playwright/test';

const baseUrl = process.env.BASE_URL || 'http://localhost/ent-clinic-online/ent-app/public';

test('Edit patient profile with empty date_of_birth should succeed', async ({ page }) => {
  await page.goto(baseUrl + '/?page=patients', { waitUntil: 'networkidle' });
  // Navigate to first patient (assumes list exists)
  const firstPatientLink = page.locator('.patients-table a').first();
  await expect(firstPatientLink).toBeVisible();
  await firstPatientLink.click();

  // Click Edit Profile
  await page.locator('#openEditProfileBtn').click();
  await expect(page.locator('#editProfileModal')).toBeVisible();

  // Clear Date of Birth
  const dateInput = page.locator('input[name="date_of_birth"]');
  await dateInput.fill('');

  // Submit form
  await page.locator('#editProfileForm').evaluate(form => (form as HTMLFormElement).submit());

  // Wait for success or error message
  await expect(page.locator('.alert')).toBeVisible();
  const msg = await page.locator('.alert').innerText();
  if (msg.toLowerCase().includes('failed')) {
    throw new Error('Patient update failed: ' + msg);
  }
});