import { test, expect } from '@playwright/test';
import { LandingPage } from '../pages/landing.page';
import { RegistroPage } from '../pages/registro.page';
import { LoginPage } from '../pages/login.page';
import { DashboardPage } from '../pages/dashboard.page';

test.describe('Flujo completo de usuario', () => {
  test('registro → login → crear asignatura → crear tema → navegar', async ({ page }) => {
    const timestamp = Date.now();
    const email = `fullflow-${timestamp}@prueba.com`;
    const password = 'password123';

    // 1. Landing page
    const landing = new LandingPage(page);
    await landing.goto();
    await landing.expectVisible();

    // 2. Registration
    await landing.clickCrearCuenta();
    const registro = new RegistroPage(page);
    await registro.registrar('Usuario Completo', email, password);
    await page.waitForURL('**/login', { timeout: 15000 });

    // 3. Login
    const login = new LoginPage(page);
    await login.login(email, password);
    await page.waitForURL('**/dashboard', { timeout: 15000 });

    // 4. Dashboard visible
    const dashboard = new DashboardPage(page);
    await dashboard.expectVisible();

    // 5. Create asignatura
    page.once('dialog', async dialog => {
      await dialog.accept('Programación Web');
    });
    await page.locator('.subject-card-new').first().click();
    await expect(page.locator('.subject-card', { hasText: 'Programación Web' })).toBeVisible({ timeout: 10000 });

    // 6. Navigate to themes (separate page now)
    await dashboard.clickAsignatura('Programación Web');
    await expect(page.locator('.asignatura-title', { hasText: 'Programación Web' })).toBeVisible({ timeout: 10000 });

    // 7. Create theme
    page.once('dialog', async dialog => {
      await dialog.accept('Introducción a APIs REST');
    });
    await page.locator('.tema-new-card').click();
    await expect(page.locator('.tema-name', { hasText: 'Introducción a APIs REST' })).toBeVisible({ timeout: 10000 });

    // 8. Navigate back to dashboard
    await page.locator('.btn-back-inline').first().click();
    await page.waitForURL('**/dashboard', { timeout: 10000 });
    await expect(dashboard.seccionAsignaturas).toBeVisible({ timeout: 10000 });

    // 9. Logout
    await dashboard.logout();
    await page.waitForURL('**/login', { timeout: 10000 });
  });
});
