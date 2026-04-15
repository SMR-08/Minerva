import { test, expect } from '@playwright/test';
import { LandingPage } from '../pages/landing.page';
import { RegistroPage } from '../pages/registro.page';

test.describe('Registro de usuario', () => {
  test('registro exitoso con datos válidos', async ({ page }) => {
    const landing = new LandingPage(page);
    const registro = new RegistroPage(page);

    await landing.goto();
    await landing.expectVisible();
    await landing.clickCrearCuenta();

    await registro.expectDisabled();

    const timestamp = Date.now();
    const email = `registro-${timestamp}@prueba.com`;

    await registro.registrar('Usuario Test', email, 'password123');

    await page.waitForURL('**/login', { timeout: 15000 });
  });

  test('registro falla con email duplicado', async ({ page }) => {
    const registro = new RegistroPage(page);
    await registro.goto();

    const timestamp = Date.now();
    const email = `dup-${timestamp}@prueba.com`;

    // First registration
    await registro.registrar('Usuario 1', email, 'password123');
    await page.waitForURL('**/login', { timeout: 15000 });

    // Go back to register and try again
    await page.goto('/registro');
    await registro.registrar('Usuario 2', email, 'password123');

    // Wait for error message to appear (page stays on /registro)
    await page.waitForTimeout(2000);
    await expect(registro.mensaje).toBeVisible({ timeout: 10000 });
    const msg = await registro.mensaje.textContent();
    expect(msg).toContain('correo electrónico');
  });

  test('registro falla con contraseñas no coincidentes', async ({ page }) => {
    const registro = new RegistroPage(page);
    await registro.goto();

    await page.getByLabel('Tu nombre completo').fill('Usuario Test');
    await page.getByLabel('Correo Electrónico').fill('test@prueba.com');
    await registro.inputContrasena.fill('password123');
    await registro.inputContrasenaConfirm.fill('different');

    await registro.btnRegistrarse.click();

    await expect(registro.mensaje).toContainText('Las contraseñas no coinciden');
  });

  test('registro falla con campos vacíos', async ({ page }) => {
    const registro = new RegistroPage(page);
    await registro.goto();

    // Trigger validation by filling and clearing a field
    await page.getByLabel('Tu nombre completo').fill('test');
    await page.getByLabel('Tu nombre completo').fill('');

    // Check that button is disabled
    await expect(registro.btnRegistrarse).toBeDisabled();
  });

  test('registro falla con contraseña corta', async ({ page }) => {
    const registro = new RegistroPage(page);
    await registro.goto();

    await page.getByLabel('Tu nombre completo').fill('Usuario Test');
    await page.getByLabel('Correo Electrónico').fill('short@prueba.com');
    await registro.inputContrasena.fill('123');
    await registro.inputContrasenaConfirm.fill('123');

    await registro.btnRegistrarse.click();

    await expect(registro.mensaje).toContainText('al menos 6 caracteres');
  });
});
