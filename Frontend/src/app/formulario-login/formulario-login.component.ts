import { Component, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../auth.service';

@Component({
  selector: 'app-formulario-login',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './formulario-login.component.html',
  styleUrl: './formulario-login.component.css'
})
export class FormularioLoginComponent implements OnDestroy {
  formulario = {
    email: '',
    contrasena: ''
  };

  mensaje: string = '';
  error: boolean = false;
  enviado: boolean = false;
  mostrarContrasena: boolean = false;

  private destroy$ = new Subject<void>();

  constructor(private auth: AuthService, private router: Router) {}

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  onSubmit(): void {
    this.enviado = true;
    if (!this.formulario.email || !this.formulario.contrasena) {
      this.mensaje = 'Por favor, completa email y contraseña';
      this.error = true;
      return;
    }

    this.auth.login(this.formulario.email, this.formulario.contrasena).pipe(takeUntil(this.destroy$)).subscribe({
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
