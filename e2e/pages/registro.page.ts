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
    this.inputNombre = page.getByLabel('Tu nombre completo', { exact: true });
    this.inputEmail = page.getByLabel('Correo Electrónico', { exact: true });
    this.inputContrasena = page.getByLabel('Contraseña', { exact: true });
    this.inputContrasenaConfirm = page.getByLabel('Confirmar Contraseña', { exact: true });
    this.btnRegistrarse = page.getByRole('button', { name: 'REGISTRARSE' });
    this.mensaje = page.locator('.msg-success, .msg-error');
  }

  async goto() {
    await this.page.goto('/registro');
  }

  async registrar(nombre: string, email: string, password: string) {
    await this.inputNombre.fill(nombre);
    await this.inputEmail.fill(email);
    await this.inputContrasena.fill(password);
    await this.inputContrasenaConfirm.fill(password);
    await this.btnRegistrarse.click();
  }

  async expectDisabled() {
    await expect(this.btnRegistrarse).toBeDisabled();
  }

  async expectEnabled() {
    await expect(this.btnRegistrarse).toBeEnabled();
  }

  async expectSuccessMessage(text: string) {
    await expect(this.mensaje).toContainText(text);
  }

  async expectErrorMessage(text: string) {
    await expect(this.mensaje).toContainText(text);
  }
}
