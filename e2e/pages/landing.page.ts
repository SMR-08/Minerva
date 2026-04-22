import { type Page, type Locator, expect } from '@playwright/test';

export class LandingPage {
  readonly page: Page;
  readonly btnComenzar: Locator;

  constructor(page: Page) {
    this.page = page;
    // El hero tiene "Comenzar gratis" como link principal
    this.btnComenzar = page.locator('a.btn-hero', { hasText: 'Comenzar gratis' });
  }

  async goto() {
    await this.page.goto('/');
  }

  async clickCrearCuenta() {
    await this.btnComenzar.click();
  }

  async expectVisible() {
    await expect(this.btnComenzar).toBeVisible();
  }
}
