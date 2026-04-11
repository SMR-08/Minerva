import { test, expect } from '@playwright/test';
import { LoginPage } from '../pages/login.page';

test.describe('Login de usuario', () => {
  test('login exitoso con credenciales válidas', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();

    // First register a user
    await page.goto('/registro');
    const timestamp = Date.now();
    const email = `login-${timestamp}@prueba.com`;
    await page.getByLabel('Nombre Completo:').fill('Login User');
    await page.getByLabel('Email:').fill(email);
    await page.getByPlaceholder('Mínimo 6 caracteres').fill('password123');
    await page.getByPlaceholder('Confirme su contraseña').fill('password123');
    await page.getByRole('button', { name: 'Registrarse' }).click();
    await page.waitForURL('**/login', { timeout: 15000 });

    // Now login
    await login.login(email, 'password123');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
    await expect(page.getByRole('button', { name: 'Salir' })).toBeVisible();
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

    // Button should be disabled when fields are empty
    await expect(login.btnIngresar).toBeDisabled();
  });
});
