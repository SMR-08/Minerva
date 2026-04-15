import { test, expect } from '@playwright/test';
import { DashboardPage } from '../pages/dashboard.page';

test.describe('Gestión de Asignaturas', () => {
  test('crear asignatura mediante prompt', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    page.once('dialog', async dialog => {
      await dialog.accept('Asignatura E2E Test');
    });

    await page.locator('.subject-card-new').first().click();

    await expect(page.locator('.subject-card', { hasText: 'Asignatura E2E Test' })).toBeVisible({ timeout: 10000 });
  });

  test('ver temas de una asignatura', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    page.once('dialog', async dialog => {
      await dialog.accept('Asignatura Temas Test');
    });
    await page.locator('.subject-card-new').first().click();
    await expect(page.locator('.subject-card', { hasText: 'Asignatura Temas Test' })).toBeVisible({ timeout: 10000 });

    await dashboard.clickAsignatura('Asignatura Temas Test');

    await expect(page.locator('.asignatura-title', { hasText: 'Asignatura Temas Test' })).toBeVisible({ timeout: 10000 });
  });

  test('eliminar asignatura', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    const nombre = `Para Eliminar ${Date.now()}`;
    page.once('dialog', async dialog => {
      await dialog.accept(nombre);
    });
    await page.locator('.subject-card-new').first().click();
    await expect(page.locator('.subject-card', { hasText: nombre })).toBeVisible({ timeout: 10000 });

    page.on('dialog', async dialog => {
      await dialog.accept();
    });

    await page.locator('.subject-card:not(.subject-card-new)', { hasText: nombre })
      .locator('.btn-menu').dispatchEvent('click');

    await page.waitForTimeout(2000);

    await expect(page.locator('.subject-card', { hasText: nombre })).not.toBeVisible({ timeout: 10000 });
  });
});
