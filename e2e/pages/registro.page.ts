import { type Page, type Locator, expect } from '@playwright/test';

export class RegistroPage {
  readonly page: Page;
  readonly inputNombre: Locator;
  readonly inputEmail: Locator;
  readonly inputContrasena: Locator;
  readonly inputContrasenaConfirm: Locator;
  readonly btnRegistrarse: Locator;
  readonly btnLimpiar: Locator;
  readonly mensaje: Locator;

  constructor(page: Page) {
    this.page = page;
    this.inputNombre = page.getByLabel('Nombre Completo:');
    this.inputEmail = page.getByLabel('Email:');
    this.inputContrasena = page.getByPlaceholder('Mínimo 6 caracteres');
    this.inputContrasenaConfirm = page.getByPlaceholder('Confirme su contraseña');
    this.btnRegistrarse = page.getByRole('button', { name: 'Registrarse' });
    this.btnLimpiar = page.getByRole('button', { name: 'Limpiar' });
    this.mensaje = page.locator('.mensaje-exito, .mensaje-error');
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
