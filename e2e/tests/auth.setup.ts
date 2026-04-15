import { test as setup, expect } from '@playwright/test';
import path from 'path';

const authFile = path.join(__dirname, '../playwright/.auth/user.json');

setup('authenticate as user', async ({ page }) => {
  // Generate unique email to avoid conflicts
  const timestamp = Date.now();
  const email = `e2e-${timestamp}@prueba.com`;
  const password = 'password123';

  // Go to registration
  await page.goto('/registro');

  // Fill registration form
  await page.getByLabel('Tu nombre completo').fill('Usuario E2E');
  await page.getByLabel('Correo Electrónico').fill(email);
  await page.getByLabel('Contraseña').fill(password);
  await page.getByLabel('Confirmar Contraseña').fill(password);

  // Submit
  await page.getByRole('button', { name: 'REGISTRARSE' }).click();

  // Wait for redirect to login
  await page.waitForURL('**/login', { timeout: 15000 });
  await page.waitForTimeout(500);

  // Login
  await page.getByLabel('Correo Electrónico').fill(email);
  await page.getByLabel('Contraseña').fill(password);
  await page.getByRole('button', { name: 'INICIAR SESIÓN' }).click();

  // Wait for dashboard
  await page.waitForURL('**/dashboard', { timeout: 15000 });
  await expect(page.locator('.user-avatar')).toBeVisible();

  // Save storage state (cookies + localStorage with auth token)
  await page.context().storageState({ path: authFile });
});
