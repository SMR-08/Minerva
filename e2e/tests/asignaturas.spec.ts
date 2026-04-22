import { test, expect } from '@playwright/test';
import { DashboardPage } from '../pages/dashboard.page';

test.describe('Gestión de Asignaturas', () => {
  test('crear asignatura mediante prompt', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();
    await dashboard.crearAsignatura('Asignatura E2E Test');
  });

  test('navegar a una asignatura', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();
    await dashboard.crearAsignatura('Asignatura Navegación');
    await dashboard.clickAsignatura('Asignatura Navegación');
    await expect(page.locator('.asignatura-title', { hasText: 'Asignatura Navegación' })).toBeVisible({ timeout: 10000 });
  });

  test('eliminar asignatura', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    const nombre = `Para Eliminar ${Date.now()}`;
    await dashboard.crearAsignatura(nombre);

    // Abrir menú de opciones y confirmar eliminación
    page.on('dialog', async dialog => dialog.accept());
    await page.locator('.subject-card:not(.subject-card-new)', { hasText: nombre })
      .locator('.btn-menu').dispatchEvent('click');

    await expect(page.locator('.subject-card', { hasText: nombre }))
      .not.toBeVisible({ timeout: 10000 });
  });
});
