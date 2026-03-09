import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../auth.service';
import { HeaderComponent } from '../layout/header/header.component';
import { FooterComponent } from '../layout/footer/footer.component';

@Component({
  selector: 'app-formulario-login',
  standalone: true,
  imports: [CommonModule, FormsModule, HeaderComponent, FooterComponent],
  templateUrl: './formulario-login.component.html',
  styleUrls: ['./formulario-login.component.css']
})
export class FormularioLoginComponent {
  formulario = {
    email: '',
    contrasena: ''
  };

  mensaje: string = '';
  error: boolean = false;
  enviado: boolean = false;

  constructor(private auth: AuthService, private router: Router) {}

  onSubmit(): void {
    this.enviado = true;
    if (!this.formulario.email || !this.formulario.contrasena) {
      this.mensaje = 'Por favor, completa correo y contraseña';
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
        this.mensaje = err.error?.message || 'Credenciales inválidas o error de conexión';
        this.error = true;
      }
    });
  }

  onLimpiar(): void {
    this.formulario = { email: '', contrasena: '' };
    this.mensaje = '';
    this.error = false;
    this.enviado = false;
  }
}
