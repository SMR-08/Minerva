import { type Page, type Locator, expect } from '@playwright/test';

export class DashboardPage {
  readonly page: Page;
  readonly seccionActividad: Locator;
  readonly seccionAsignaturas: Locator;
  readonly userAvatar: Locator;

  constructor(page: Page) {
    this.page = page;
    this.seccionActividad = page.getByRole('heading', { name: 'ACTIVIDAD RECIENTE' });
    this.seccionAsignaturas = page.getByRole('heading', { name: 'MIS ASIGNATURAS' });
    this.userAvatar = page.locator('.user-avatar');
  }

  async goto() {
    await this.page.goto('/dashboard');
  }

  async expectVisible() {
    await expect(this.userAvatar).toBeVisible();
    await expect(this.seccionActividad).toBeVisible();
    await expect(this.seccionAsignaturas).toBeVisible();
  }

  async expectAsignaturaVisible(nombre: string) {
    await expect(this.page.locator('.subject-card', { hasText: nombre })).toBeVisible();
  }

  async clickAsignatura(nombre: string) {
    await this.page.locator('.subject-card:not(.subject-card-new)', { hasText: nombre }).click();
    await this.page.waitForURL('**/asignatura/*', { timeout: 10000 });
  }

  async logout() {
    await this.userAvatar.click();
    await this.page.getByText('Cerrar sesión').click();
  }
}
