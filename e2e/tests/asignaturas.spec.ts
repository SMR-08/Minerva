import { test, expect } from '@playwright/test';
import { DashboardPage } from '../pages/dashboard.page';

test.describe('Gestión de Asignaturas', () => {
  test('crear asignatura mediante prompt', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    page.once('dialog', async dialog => {
      await dialog.accept('Asignatura E2E Test');
    });

    await page.locator('.tarjeta-nueva').first().click();

    await expect(page.getByText('Asignatura E2E Test')).toBeVisible({ timeout: 10000 });
  });

  test('ver temas de una asignatura', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    page.once('dialog', async dialog => {
      await dialog.accept('Asignatura Temas Test');
    });
    await page.locator('.tarjeta-nueva').first().click();
    await expect(page.getByText('Asignatura Temas Test')).toBeVisible({ timeout: 10000 });

    await dashboard.clickAsignatura('Asignatura Temas Test');

    await expect(page.getByText('Temas de: Asignatura Temas Test')).toBeVisible({ timeout: 10000 });
  });

  test('eliminar asignatura', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    // Create a fresh asignatura specifically for deletion
    // Don't navigate to its themes to avoid FK issues
    const nombre = `Para Eliminar ${Date.now()}`;
    page.once('dialog', async dialog => {
      await dialog.accept(nombre);
    });
    await page.locator('.tarjeta-nueva').first().click();
    await expect(page.getByText(nombre)).toBeVisible({ timeout: 10000 });

    // Set up persistent dialog handler for confirm
    page.on('dialog', async dialog => {
      await dialog.accept();
    });

    // Find and click the delete button for this specific asignatura
    await page.locator('.tarjeta-asignatura:not(.tarjeta-nueva)', { hasText: nombre })
      .locator('.btn-eliminar').dispatchEvent('click');

    // Wait for the UI to update
    await page.waitForTimeout(2000);

    // Verify it's gone
    await expect(page.getByText(nombre)).not.toBeVisible({ timeout: 10000 });
  });
});
