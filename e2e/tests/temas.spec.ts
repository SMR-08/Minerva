import { test, expect } from '@playwright/test';
import { DashboardPage } from '../pages/dashboard.page';

test.describe('Gestión de Temas', () => {
  test('crear tema en una asignatura', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    // Create an asignatura first
    page.once('dialog', async dialog => {
      await dialog.accept('Asignatura Temas');
    });
    await page.locator('.subject-card-new').first().click();
    await expect(page.locator('.subject-card', { hasText: 'Asignatura Temas' })).toBeVisible({ timeout: 10000 });

    // Navigate to themes view (now a separate page)
    await dashboard.clickAsignatura('Asignatura Temas');
    await expect(page.locator('.asignatura-title', { hasText: 'Asignatura Temas' })).toBeVisible({ timeout: 10000 });

    // Create a theme
    page.once('dialog', async dialog => {
      await dialog.accept('Tema E2E Test');
    });
    await page.locator('.tema-new-card').click();

    await expect(page.locator('.tema-name', { hasText: 'Tema E2E Test' })).toBeVisible({ timeout: 10000 });
  });

  test('eliminar tema', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    // Create asignatura
    page.once('dialog', async dialog => {
      await dialog.accept('Asignatura Eliminar Tema');
    });
    await page.locator('.subject-card-new').first().click();
    await expect(page.locator('.subject-card', { hasText: 'Asignatura Eliminar Tema' })).toBeVisible({ timeout: 10000 });

    // Navigate to themes
    await dashboard.clickAsignatura('Asignatura Eliminar Tema');
    await expect(page.locator('.asignatura-title', { hasText: 'Asignatura Eliminar Tema' })).toBeVisible({ timeout: 10000 });

    // Create a theme
    page.once('dialog', async dialog => {
      await dialog.accept('Tema Para Eliminar');
    });
    await page.locator('.tema-new-card').click();
    await expect(page.locator('.tema-name', { hasText: 'Tema Para Eliminar' })).toBeVisible({ timeout: 10000 });

    // Set up persistent dialog handler
    page.on('dialog', async dialog => {
      await dialog.accept();
    });

    // Use dispatchEvent to avoid Playwright waiting for dialog
    await page.locator('.tema-section', { hasText: 'Tema Para Eliminar' })
      .locator('.btn-menu').dispatchEvent('click');

    await page.waitForTimeout(2000);
    await expect(page.locator('.tema-name', { hasText: 'Tema Para Eliminar' })).not.toBeVisible({ timeout: 10000 });
  });
});
