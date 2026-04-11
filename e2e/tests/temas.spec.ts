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
    await page.locator('.tarjeta-nueva').first().click();
    await expect(page.getByText('Asignatura Temas')).toBeVisible({ timeout: 10000 });

    // Navigate to themes view
    await dashboard.clickAsignatura('Asignatura Temas');
    await expect(page.getByText('Temas de: Asignatura Temas')).toBeVisible({ timeout: 10000 });

    // Create a theme
    page.once('dialog', async dialog => {
      await dialog.accept('Tema E2E Test');
    });
    await dashboard.btnCrearTema.click();

    await expect(page.getByText('Tema E2E Test')).toBeVisible({ timeout: 10000 });
  });

  test('eliminar tema', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    // Create asignatura
    page.once('dialog', async dialog => {
      await dialog.accept('Asignatura Eliminar Tema');
    });
    await page.locator('.tarjeta-nueva').first().click();
    await expect(page.getByText('Asignatura Eliminar Tema')).toBeVisible({ timeout: 10000 });

    // Navigate to themes
    await dashboard.clickAsignatura('Asignatura Eliminar Tema');
    await expect(page.getByText('Temas de: Asignatura Eliminar Tema')).toBeVisible({ timeout: 10000 });

    // Create a theme
    page.once('dialog', async dialog => {
      await dialog.accept('Tema Para Eliminar');
    });
    await dashboard.btnCrearTema.click();
    await expect(page.getByText('Tema Para Eliminar')).toBeVisible({ timeout: 10000 });

    // Set up persistent dialog handler
    page.on('dialog', async dialog => {
      await dialog.accept();
    });

    // Use dispatchEvent to avoid Playwright waiting for dialog
    await page.locator('.tarjeta-asignatura:not(.tarjeta-nueva) .btn-eliminar').dispatchEvent('click');

    await page.waitForTimeout(2000);
    await expect(page.getByText('Tema Para Eliminar')).not.toBeVisible({ timeout: 10000 });
  });
});
