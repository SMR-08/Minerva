import { type Page, type Locator, expect } from '@playwright/test';

export class DashboardPage {
  readonly page: Page;
  readonly btnSalir: Locator;
  readonly linkSubir: Locator;
  readonly seccionActividad: Locator;
  readonly seccionAsignaturas: Locator;
  readonly btnCrearAsignatura: Locator;
  readonly btnEliminarAsignatura: Locator;
  readonly btnVolver: Locator;
  readonly btnCrearTema: Locator;

  constructor(page: Page) {
    this.page = page;
    this.btnSalir = page.getByRole('button', { name: 'Salir' });
    this.linkSubir = page.getByRole('link', { name: 'Subir' });
    this.seccionActividad = page.getByRole('heading', { name: 'Actividad Reciente' });
    this.seccionAsignaturas = page.getByRole('heading', { name: 'Mis Asignaturas' });
    this.btnCrearAsignatura = page.getByText('Crear Asignatura');
    this.btnEliminarAsignatura = page.getByRole('button', { name: 'Eliminar Asignatura' });
    this.btnVolver = page.getByRole('button', { name: /Volver/ });
    this.btnCrearTema = page.getByText('Crear Tema');
  }

  async goto() {
    await this.page.goto('/dashboard');
  }

  async expectVisible() {
    await expect(this.btnSalir).toBeVisible();
    await expect(this.seccionActividad).toBeVisible();
    await expect(this.seccionAsignaturas).toBeVisible();
  }

  async expectAsignaturaVisible(nombre: string) {
    await expect(this.page.getByText(nombre)).toBeVisible();
  }

  async expectTemaVisible(nombre: string) {
    await expect(this.page.getByText(nombre)).toBeVisible();
  }

  async clickAsignatura(nombre: string) {
    await this.page.locator('.tarjeta-asignatura', { hasText: nombre }).click();
  }

  async clickTema(nombre: string) {
    await this.page.locator('.tarjeta-asignatura', { hasText: nombre }).click();
  }

  async clickCrearAsignatura() {
    // Handle the window.prompt by listening for dialog
    this.page.on('dialog', async dialog => {
      await dialog.accept('Asignatura de Prueba');
    });
    await this.page.locator('.tarjeta-nueva').first().click();
  }

  async clickCrearTema() {
    this.page.on('dialog', async dialog => {
      await dialog.accept('Tema de Prueba');
    });
    await this.page.locator('.tarjeta-nueva').click();
  }

  async clickEliminarAsignatura() {
    // Confirm the window.confirm dialog
    this.page.on('dialog', async dialog => {
      await dialog.accept();
    });
    await this.btnEliminarAsignatura.click();
  }

  async clickEliminarTema() {
    this.page.on('dialog', async dialog => {
      await dialog.accept();
    });
    await this.page.locator('.btn-eliminar').click();
  }
}
