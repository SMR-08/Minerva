import { test, expect } from '@playwright/test';
import { DashboardPage } from '../pages/dashboard.page';

test.describe('Dashboard', () => {
  test('dashboard carga correctamente para usuario autenticado', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();
    await dashboard.expectVisible();
  });

  test('dashboard muestra sección de actividad reciente', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();
    await expect(dashboard.seccionActividad).toBeVisible();
  });

  test('dashboard muestra sección de asignaturas', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();
    await expect(dashboard.seccionAsignaturas).toBeVisible();
  });

  test('dashboard muestra botón crear nueva asignatura', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();
    await expect(dashboard.btnCrearNueva).toBeVisible();
  });
});
