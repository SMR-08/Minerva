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

  // Fill registration form using specific selectors
  await page.getByLabel('Nombre Completo:').fill('Usuario E2E');
  await page.getByLabel('Email:').fill(email);
  await page.getByPlaceholder('Mínimo 6 caracteres').fill(password);
  await page.getByPlaceholder('Confirme su contraseña').fill(password);

  // Submit
  await page.getByRole('button', { name: 'Registrarse' }).click();

  // Wait for redirect to login (the success message may disappear quickly)
  await page.waitForURL('**/login', { timeout: 15000 });

  // Small wait to ensure the redirect completed
  await page.waitForTimeout(500);

  // Login
  await page.getByLabel('Email:').fill(email);
  await page.getByLabel('Contraseña:').fill(password);
  await page.getByRole('button', { name: 'Ingresar' }).click();

  // Wait for dashboard
  await page.waitForURL('**/dashboard', { timeout: 15000 });
  await expect(page.getByRole('button', { name: 'Salir' })).toBeVisible();

  // Save storage state (cookies + localStorage with auth token)
  await page.context().storageState({ path: authFile });
});
