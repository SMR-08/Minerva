import { type Page, type Locator, expect } from '@playwright/test';

export class LandingPage {
  readonly page: Page;
  readonly btnCrearCuenta: Locator;

  constructor(page: Page) {
    this.page = page;
    this.btnCrearCuenta = page.getByRole('link', { name: 'Comenzar gratis' });
  }

  async goto() {
    await this.page.goto('/');
  }

  async clickCrearCuenta() {
    await this.btnCrearCuenta.click();
  }

  async expectVisible() {
    await expect(this.btnCrearCuenta).toBeVisible();
  }
}
