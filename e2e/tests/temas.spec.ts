import { test, expect } from '@playwright/test';
import { DashboardPage } from '../pages/dashboard.page';

test.describe('Gestión de Temas', () => {
  test('crear tema en una asignatura', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    await dashboard.crearAsignatura('Asignatura Temas');
    await dashboard.clickAsignatura('Asignatura Temas');
    await expect(page.locator('.asignatura-title', { hasText: 'Asignatura Temas' })).toBeVisible({ timeout: 10000 });

    await page.locator('.tema-new-card').click();
    await page.locator('.modal-input:visible').fill('Tema E2E Test');
    await page.locator('.btn-modal-primary:visible').click();

    await expect(page.locator('.tema-name', { hasText: 'Tema E2E Test' })).toBeVisible({ timeout: 10000 });
  });

  test('eliminar tema', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    await dashboard.crearAsignatura('Asignatura Eliminar Tema');
    await dashboard.clickAsignatura('Asignatura Eliminar Tema');
    await expect(page.locator('.asignatura-title', { hasText: 'Asignatura Eliminar Tema' })).toBeVisible({ timeout: 10000 });

    await page.locator('.tema-new-card').click();
    await page.locator('.modal-input:visible').fill('Tema Para Eliminar');
    await page.locator('.btn-modal-primary:visible').click();
    await expect(page.locator('.tema-name', { hasText: 'Tema Para Eliminar' })).toBeVisible({ timeout: 10000 });

    await page.locator('.tema-section', { hasText: 'Tema Para Eliminar' })
      .locator('.btn-menu').click();
    await page.locator('.dropdown-item-danger', { hasText: 'Eliminar' }).click();
    await page.locator('.btn-modal-danger:visible').click();

    await expect(page.locator('.tema-name', { hasText: 'Tema Para Eliminar' }))
      .not.toBeVisible({ timeout: 10000 });
  });
});
