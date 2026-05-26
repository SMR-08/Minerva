import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../auth.service';
import { Router } from '@angular/router';
import { HeaderComponent } from '../layout/header/header.component';
import { FooterComponent } from '../layout/footer/footer.component';

@Component({
  selector: 'app-ajustes',
  standalone: true,
  imports: [CommonModule, FormsModule, HeaderComponent, FooterComponent],
  templateUrl: './ajustes.component.html',
  styleUrl: './ajustes.component.css'
})
export class AjustesComponent implements OnInit {
  activeTab: 'perfil' | 'seguridad' | 'cuenta' = 'perfil';

  // Perfil
  nombre = '';
  email = '';

  // Seguridad
  passwordActual = '';
  passwordNuevo = '';
  passwordConfirm = '';

  // Feedback
  mensaje: { tipo: 'ok' | 'error'; texto: string } | null = null;

  constructor(public authService: AuthService, private router: Router) {}

  ngOnInit(): void {
    const user = this.authService.getUser();
    if (!user) {
      this.router.navigate(['/login']);
      return;
    }
    this.email = user.usuario;
    this.nombre = user.usuario.split('@')[0];
  }

  setTab(tab: 'perfil' | 'seguridad' | 'cuenta'): void {
    this.activeTab = tab;
    this.mensaje = null;
  }

  guardarPerfil(): void {
    // Aquí conectarías con la API
    this.mensaje = { tipo: 'ok', texto: 'Perfil actualizado correctamente.' };
  }

  cambiarPassword(): void {
    if (this.passwordNuevo !== this.passwordConfirm) {
      this.mensaje = { tipo: 'error', texto: 'Las contraseñas no coinciden.' };
      return;
    }
    if (this.passwordNuevo.length < 8) {
      this.mensaje = { tipo: 'error', texto: 'La contraseña debe tener al menos 8 caracteres.' };
      return;
    }
    // Aquí conectarías con la API
    this.mensaje = { tipo: 'ok', texto: 'Contraseña actualizada.' };
    this.passwordActual = '';
    this.passwordNuevo = '';
    this.passwordConfirm = '';
  }

  eliminarCuenta(): void {
    const confirmar = confirm('¿Estás seguro de que quieres eliminar tu cuenta? Esta acción no se puede deshacer.');
    if (confirmar) {
      this.authService.logout();
    }
  }

  cerrarSesion(): void {
    this.authService.logout();
  }
}
