import { test as setup, expect } from '@playwright/test';
import path from 'path';

const authFile = path.join(__dirname, '../playwright/.auth/user.json');

setup('authenticate as user', async ({ page }) => {
  const timestamp = Date.now();
  const email = `e2e-${timestamp}@prueba.com`;
  const password = 'password123';

  // Registro
  await page.goto('/registro');
  await page.locator('#nombre').fill('Usuario E2E');
  await page.locator('#email').fill(email);
  await page.locator('#contrasena').fill(password);
  await page.locator('#contrasenaConfirm').fill(password);
  await page.getByRole('button', { name: 'REGISTRARSE' }).click();

  // Esperar redireccion a login
  await page.waitForURL('**/login', { timeout: 20000 });

  // Login
  await page.locator('#email').fill(email);
  await page.locator('#contrasena').fill(password);
  await page.getByRole('button', { name: 'INICIAR SESIÓN' }).click();

  // Esperar redireccion al dashboard
  await page.waitForURL('**/dashboard', { timeout: 20000 });

  // Guardar estado de autenticacion para los demas tests
  await page.context().storageState({ path: authFile });
});
