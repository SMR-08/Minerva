import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../auth.service';

@Component({
  selector: 'app-formulario-registro',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './formulario-registro.component.html',
  styleUrl: './formulario-registro.component.css'
})
export class FormularioRegistroComponent {
  formulario = {
    nombre: '',
    usuario: '',
    contrasena: '',
    contrasenaConfirmar: ''
  };

  mensaje: string = '';
  error: boolean = false;
  enviado: boolean = false;

  constructor(private authService: AuthService, private router: Router) {}

  onSubmit(): void {
    this.enviado = true;

    if (!this.formulario.nombre || !this.formulario.usuario || !this.formulario.contrasena || !this.formulario.contrasenaConfirmar) {
      this.mensaje = 'Por favor, completa todos los campos requeridos';
      this.error = true;
      return;
    }

    if (this.formulario.contrasena !== this.formulario.contrasenaConfirmar) {
      this.mensaje = 'Las contraseñas no coinciden';
      this.error = true;
      return;
    }

    if (this.formulario.contrasena.length < 6) {
      this.mensaje = 'La contraseña debe tener al menos 6 caracteres';
      this.error = true;
      return;
    }

    const datosRegistro = {
      nombre: this.formulario.nombre,
      usuario: this.formulario.usuario,
      contrasena: this.formulario.contrasena
    };

    this.authService.registerUser(datosRegistro).subscribe({
      next: (res) => {
        this.mensaje = 'Registro completado exitosamente. Ya puedes iniciar sesión.';
        this.error = false;
        this.onLimpiar();
        setTimeout(() => this.router.navigate(['/login']), 1500);
      },
      error: (err) => {
        console.error('Error registro:', err);
        this.mensaje = err.error?.message || 'Error al registrarse. Inténtalo de nuevo.';
        this.error = true;
      }
    });
  }

  onLimpiar(): void {
    this.formulario = {
      nombre: '',
      usuario: '',
      contrasena: '',
      contrasenaConfirmar: ''
    };
    this.mensaje = '';
    this.error = false;
    this.enviado = false;
  }
}
