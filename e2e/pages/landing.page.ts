import { type Page, type Locator, expect } from '@playwright/test';

export class LandingPage {
  readonly page: Page;
  readonly btnIniciarSesion: Locator;
  readonly btnCrearCuenta: Locator;

  constructor(page: Page) {
    this.page = page;
    this.btnIniciarSesion = page.getByRole('button', { name: 'Iniciar Sesión' });
    this.btnCrearCuenta = page.getByRole('button', { name: 'Crear Cuenta' });
  }

  async goto() {
    await this.page.goto('/');
  }

  async clickCrearCuenta() {
    await this.btnCrearCuenta.click();
  }

  async clickIniciarSesion() {
    await this.btnIniciarSesion.click();
  }

  async expectVisible() {
    await expect(this.btnIniciarSesion).toBeVisible();
    await expect(this.btnCrearCuenta).toBeVisible();
  }
}
