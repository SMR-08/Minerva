import { type Page, type Locator, expect } from '@playwright/test';

export class LoginPage {
  readonly page: Page;
  readonly inputEmail: Locator;
  readonly inputContrasena: Locator;
  readonly btnIngresar: Locator;
  readonly mensaje: Locator;

  constructor(page: Page) {
    this.page = page;
    this.inputEmail = page.locator('#email');
    this.inputContrasena = page.locator('#contrasena');
    this.btnIngresar = page.getByRole('button', { name: 'INICIAR SESIÓN' });
    this.mensaje = page.locator('.msg-success, .msg-error');
  }

  async goto() {
    await this.page.goto('/login');
  }

  async login(email: string, password: string) {
    await this.inputEmail.fill(email);
    await this.inputContrasena.fill(password);
    await this.btnIngresar.click();
  }

  async expectDisabled() {
    await expect(this.btnIngresar).toBeDisabled();
  }

  async expectEnabled() {
    await expect(this.btnIngresar).toBeEnabled();
  }

  async expectErrorMessage(text: string) {
    await expect(this.mensaje).toContainText(text);
  }
}
