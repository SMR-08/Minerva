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
  styleUrl: './formulario-login.component.css'
})
export class FormularioLoginComponent {
  formulario = {
    email: '',
    contrasena: ''
  };

  mensaje: string = '';
  error: boolean = false;
  enviado: boolean = false;
  mostrarContrasena: boolean = false;

  constructor(private auth: AuthService, private router: Router) {}

  onSubmit(): void {
    this.enviado = true;
    if (!this.formulario.email || !this.formulario.contrasena) {
      this.mensaje = 'Por favor, completa email y contraseña';
      this.error = true;
      return;
    }

    this.auth.login(this.formulario.email, this.formulario.contrasena).subscribe({
      next: (res) => {
        this.mensaje = 'Ingreso exitoso';
        this.error = false;
        this.router.navigate(['/dashboard']);
      },
      error: (err) => {
        console.error('Error login:', err);
        const primerError = err.error?.errors ? Object.values(err.error.errors)[0] : err.error?.message;
        this.mensaje = primerError || 'Credenciales inválidas o error de conexión';
        this.error = true;
      }
    });
  }
}
