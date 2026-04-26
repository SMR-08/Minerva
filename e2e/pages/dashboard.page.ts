import { type Page, type Locator, expect } from '@playwright/test';

export class DashboardPage {
  readonly page: Page;
  readonly seccionActividad: Locator;
  readonly seccionAsignaturas: Locator;
  readonly userAvatar: Locator;
  readonly btnCrearNueva: Locator;

  constructor(page: Page) {
    this.page = page;
    this.seccionActividad = page.locator('.section-title', { hasText: 'ACTIVIDAD RECIENTE' });
    this.seccionAsignaturas = page.locator('.section-title', { hasText: 'MIS ASIGNATURAS' });
    this.userAvatar = page.locator('.user-avatar');
    this.btnCrearNueva = page.locator('.subject-card.subject-card-new').first();
  }

  async goto() {
    await this.page.goto('/dashboard');
  }

  async expectVisible() {
    await expect(this.userAvatar).toBeVisible();
    await expect(this.seccionActividad).toBeVisible();
    await expect(this.seccionAsignaturas).toBeVisible();
  }

  async crearAsignatura(nombre: string) {
    this.page.once('dialog', async dialog => {
      await dialog.accept(nombre);
    });
    await this.btnCrearNueva.click();
    await expect(this.page.locator('.subject-card', { hasText: nombre })).toBeVisible({ timeout: 10000 });
  }

  async clickAsignatura(nombre: string) {
    await this.page.locator('.subject-card:not(.subject-card-new)', { hasText: nombre }).click();
    await this.page.waitForURL('**/asignatura/**', { timeout: 10000 });
  }

  async logout() {
    await this.userAvatar.click();
    await this.page.locator('.menu-item', { hasText: 'Cerrar sesión' }).click();
  }
}
