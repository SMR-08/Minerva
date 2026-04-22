import { test, expect } from '@playwright/test';
import { LandingPage } from '../pages/landing.page';
import { RegistroPage } from '../pages/registro.page';

test.describe('Registro de usuario', () => {
  test('registro exitoso con datos válidos', async ({ page }) => {
    const landing = new LandingPage(page);
    await landing.goto();
    await landing.expectVisible();
    await landing.clickCrearCuenta();

    const registro = new RegistroPage(page);
    await registro.expectDisabled();

    const timestamp = Date.now();
    await registro.registrar('Usuario Test', `registro-${timestamp}@prueba.com`, 'password123');
    await page.waitForURL('**/login', { timeout: 15000 });
  });

  test('registro falla con email duplicado', async ({ page }) => {
    const registro = new RegistroPage(page);
    await registro.goto();

    const timestamp = Date.now();
    const email = `dup-${timestamp}@prueba.com`;

    await registro.registrar('Usuario 1', email, 'password123');
    await page.waitForURL('**/login', { timeout: 15000 });

    await page.goto('/registro');
    await registro.registrar('Usuario 2', email, 'password123');

    await expect(registro.mensaje).toBeVisible({ timeout: 10000 });
    await expect(registro.mensaje).toContainText('correo electrónico');
  });

  test('registro falla con contraseñas no coincidentes', async ({ page }) => {
    const registro = new RegistroPage(page);
    await registro.goto();

    await registro.inputNombre.fill('Usuario Test');
    await registro.inputEmail.fill('test@prueba.com');
    await registro.inputContrasena.fill('password123');
    await registro.inputContrasenaConfirm.fill('diferente');
    await registro.btnRegistrarse.click();

    await expect(registro.mensaje).toContainText('Las contraseñas no coinciden', { timeout: 5000 });
  });

  test('botón deshabilitado con campos vacíos', async ({ page }) => {
    const registro = new RegistroPage(page);
    await registro.goto();
    await expect(registro.btnRegistrarse).toBeDisabled();
  });

  test('registro falla con contraseña corta', async ({ page }) => {
    const registro = new RegistroPage(page);
    await registro.goto();

    await registro.inputNombre.fill('Usuario Test');
    await registro.inputEmail.fill('short@prueba.com');
    await registro.inputContrasena.fill('123');
    await registro.inputContrasenaConfirm.fill('123');
    await registro.btnRegistrarse.click();

    await expect(registro.mensaje).toContainText('al menos 6 caracteres', { timeout: 5000 });
  });
});
