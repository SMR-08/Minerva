import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../auth.service';

@Component({
  selector: 'app-formulario-login',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './formulario-login.component.html',
  styleUrls: ['./formulario-login.component.css']
})
export class FormularioLoginComponent {
  formulario = {
    usuario: '',
    contrasena: ''
  };

  mensaje: string = '';
  error: boolean = false;
  enviado: boolean = false;
  mostrarContrasena: boolean = false;

  constructor(private auth: AuthService, private router: Router) {}

  toggleMostrarContrasena(): void {
    this.mostrarContrasena = !this.mostrarContrasena;
  }

  onSubmit(): void {
    this.enviado = true;
    if (!this.formulario.usuario || !this.formulario.contrasena) {
      this.mensaje = 'Por favor, completa usuario y contraseña';
      this.error = true;
      return;
    }

    this.auth.login(this.formulario.usuario, this.formulario.contrasena).subscribe({
      next: (res) => {
        this.mensaje = 'Ingreso exitoso';
        this.error = false;
        this.router.navigate(['/dashboard']);
      },
      error: (err) => {
        console.error('Error login:', err);
        this.mensaje = err.error?.message || 'Credenciales inválidas o error de conexión';
        this.error = true;
      }
    });
  }

  onLimpiar(): void {
    this.formulario = { usuario: '', contrasena: '' };
    this.mensaje = '';
    this.error = false;
    this.enviado = false;
  }
}
