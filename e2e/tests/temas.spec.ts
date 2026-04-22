import { test, expect } from '@playwright/test';
import { DashboardPage } from '../pages/dashboard.page';

test.describe('Gestión de Temas', () => {
  test('crear tema en una asignatura', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    await dashboard.crearAsignatura('Asignatura Temas');
    await dashboard.clickAsignatura('Asignatura Temas');
    await expect(page.locator('.asignatura-title', { hasText: 'Asignatura Temas' })).toBeVisible({ timeout: 10000 });

    page.once('dialog', async dialog => dialog.accept('Tema E2E Test'));
    await page.locator('.tema-new-card').click();

    await expect(page.locator('.tema-name', { hasText: 'Tema E2E Test' })).toBeVisible({ timeout: 10000 });
  });

  test('eliminar tema', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    await dashboard.crearAsignatura('Asignatura Eliminar Tema');
    await dashboard.clickAsignatura('Asignatura Eliminar Tema');
    await expect(page.locator('.asignatura-title', { hasText: 'Asignatura Eliminar Tema' })).toBeVisible({ timeout: 10000 });

    page.once('dialog', async dialog => dialog.accept('Tema Para Eliminar'));
    await page.locator('.tema-new-card').click();
    await expect(page.locator('.tema-name', { hasText: 'Tema Para Eliminar' })).toBeVisible({ timeout: 10000 });

    page.on('dialog', async dialog => dialog.accept());
    await page.locator('.tema-section', { hasText: 'Tema Para Eliminar' })
      .locator('.btn-menu').dispatchEvent('click');

    await expect(page.locator('.tema-name', { hasText: 'Tema Para Eliminar' }))
      .not.toBeVisible({ timeout: 10000 });
  });
});
