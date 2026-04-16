import { test, expect } from '@playwright/test';
import { LoginPage } from '../pages/login.page';
import { RegistroPage } from '../pages/registro.page';

test.describe('Login de usuario', () => {
  test('login exitoso con credenciales válidas', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();

    // First register a user
    const registro = new RegistroPage(page);
    const timestamp = Date.now();
    const email = `login-${timestamp}@prueba.com`;
    await registro.goto();
    await registro.registrar('Login User', email, 'password123');
    await page.waitForURL('**/login', { timeout: 15000 });

    // Now login
    await login.login(email, 'password123');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
    await expect(page.locator('.user-avatar')).toBeVisible();
  });

  test('login falla con credenciales incorrectas', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();

    await login.login('noexiste@prueba.com', 'wrongpassword');

    await expect(login.mensaje).toBeVisible({ timeout: 10000 });
    const msg = await login.mensaje.textContent();
    expect(msg).toBeTruthy();
  });

  test('login button is disabled with empty fields', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();

    await expect(login.btnIngresar).toBeDisabled();
  });
});
