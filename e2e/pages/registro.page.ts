import { type Page, type Locator, expect } from '@playwright/test';

export class RegistroPage {
  readonly page: Page;
  readonly inputNombre: Locator;
  readonly inputEmail: Locator;
  readonly inputContrasena: Locator;
  readonly inputContrasenaConfirm: Locator;
  readonly btnRegistrarse: Locator;
  readonly mensaje: Locator;

  constructor(page: Page) {
    this.page = page;
    this.inputNombre = page.locator('#nombre');
    this.inputEmail = page.locator('#email');
    this.inputContrasena = page.locator('#contrasena');
    this.inputContrasenaConfirm = page.locator('#contrasenaConfirm');
    this.btnRegistrarse = page.getByRole('button', { name: 'REGISTRARSE' });
    this.mensaje = page.locator('.msg-success, .msg-error');
  }

  async goto() {
    await this.page.goto('/registro');
  }

  /**
   * Rellena el formulario y hace click en registrarse.
   * Si waitRedirect=true, espera la redireccion a /login.
   */
  async registrar(nombre: string, email: string, password: string, waitRedirect = false) {
    await this.inputNombre.fill(nombre);
    await this.inputEmail.fill(email);
    await this.inputContrasena.fill(password);
    await this.inputContrasenaConfirm.fill(password);
    await this.btnRegistrarse.click();

    if (waitRedirect) {
      await this.page.waitForURL('**/login', { timeout: 20000 });
    }
  }

  async expectDisabled() {
    await expect(this.btnRegistrarse).toBeDisabled();
  }

  async expectEnabled() {
    await expect(this.btnRegistrarse).toBeEnabled();
  }

  async expectErrorMessage(text: string) {
    await expect(this.mensaje).toContainText(text);
  }
}
