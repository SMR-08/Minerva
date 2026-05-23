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

  // Interceptar la respuesta de la API para confirmar que el registro fue exitoso
  const registroResponse = page.waitForResponse(
    (res) => res.url().includes('/api/register') && res.status() === 201
  );
  await page.getByRole('button', { name: 'REGISTRARSE' }).click();
  await registroResponse;

  // Esperar redireccion a login (el componente usa setTimeout 1500ms)
  await page.waitForURL('**/login', { timeout: 10000 });

  // Login
  await page.locator('#email').fill(email);
  await page.locator('#contrasena').fill(password);

  // Interceptar respuesta de login
  const loginResponse = page.waitForResponse(
    (res) => res.url().includes('/api/login') && res.status() === 200
  );
  await page.getByRole('button', { name: 'INICIAR SESIÓN' }).click();
  await loginResponse;

  // Esperar redireccion al dashboard
  await page.waitForURL('**/dashboard', { timeout: 10000 });

  // Guardar estado de autenticacion para los demas tests
  await page.context().storageState({ path: authFile });
});
