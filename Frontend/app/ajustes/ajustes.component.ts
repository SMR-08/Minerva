import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../auth.service';
import { MinervaService } from '../minerva.service';
import { NotificationService } from '../notification.service';
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
  emailOriginal = '';

  // Seguridad
  passwordActual = '';
  passwordNuevo = '';
  passwordConfirm = '';

  // Estados
  cargando = false;
  mensaje: { tipo: 'ok' | 'error'; texto: string } | null = null;

  constructor(
    public authService: AuthService,
    private minervaService: MinervaService,
    private notificationService: NotificationService,
    private router: Router
  ) {}

  ngOnInit(): void {
    const user = this.authService.getUser();
    if (!user) {
      this.router.navigate(['/login']);
      return;
    }
    this.emailOriginal = user.usuario;
    this.email = user.usuario;
    this.nombre = user.usuario.split('@')[0];
  }

  setTab(tab: 'perfil' | 'seguridad' | 'cuenta'): void {
    this.activeTab = tab;
    this.mensaje = null;
    this.limpiarFormularios();
  }

  private limpiarFormularios(): void {
    this.passwordActual = '';
    this.passwordNuevo = '';
    this.passwordConfirm = '';
  }

  guardarPerfil(): void {
    // Validaciones
    if (!this.nombre.trim()) {
      this.notificationService.error('El nombre no puede estar vacío.');
      return;
    }

    if (!this.email.trim()) {
      this.notificationService.error('El correo electrónico no puede estar vacío.');
      return;
    }

    // Validar formato de email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(this.email)) {
      this.notificationService.error('Ingresa un correo electrónico válido.');
      return;
    }

    this.cargando = true;
    this.mensaje = null;

    this.minervaService.actualizarPerfil(this.nombre, this.email).subscribe({
      next: (res: any) => {
        this.cargando = false;
        this.notificationService.success('Perfil actualizado correctamente.');
        this.emailOriginal = this.email;
      },
      error: (error: any) => {
        this.cargando = false;
        const mensajeError = error.error?.message || 'Error al actualizar el perfil.';
        
        // Manejo específico de error de email duplicado
        if (error.error?.message?.includes('email') || error.error?.errors?.email) {
          this.notificationService.error('Este correo electrónico ya está registrado.');
        } else {
          this.notificationService.error(mensajeError);
        }
      }
    });
  }

  cambiarPassword(): void {
    // Validaciones
    if (!this.passwordActual.trim()) {
      this.notificationService.error('La contraseña actual es requerida.');
      return;
    }

    if (!this.passwordNuevo.trim()) {
      this.notificationService.error('La nueva contraseña no puede estar vacía.');
      return;
    }

    if (this.passwordNuevo.length < 8) {
      this.notificationService.error('La contraseña debe tener al menos 8 caracteres.');
      return;
    }

    if (this.passwordNuevo !== this.passwordConfirm) {
      this.notificationService.error('Las contraseñas no coinciden.');
      return;
    }

    if (this.passwordActual === this.passwordNuevo) {
      this.notificationService.warning('La nueva contraseña debe ser diferente a la actual.');
      return;
    }

    this.cargando = true;
    this.mensaje = null;

    this.minervaService.cambiarContrasena(this.passwordActual, this.passwordNuevo).subscribe({
      next: (res: any) => {
        this.cargando = false;
        this.notificationService.success('Contraseña actualizada correctamente.');
        this.limpiarFormularios();
      },
      error: (error: any) => {
        this.cargando = false;
        const mensajeError = error.error?.message || 'Error al cambiar la contraseña.';
        
        // Manejo específico de errores comunes
        if (error.error?.message?.includes('actual') || error.error?.message?.includes('incorrecta')) {
          this.notificationService.error('La contraseña actual es incorrecta.');
        } else {
          this.notificationService.error(mensajeError);
        }
      }
    });
  }

  eliminarCuenta(): void {
    const confirmar = confirm('¿Estás seguro de que quieres eliminar tu cuenta? Esta acción no se puede deshacer.');
    if (!confirmar) return;

    this.cargando = true;
    this.mensaje = null;

    this.minervaService.eliminarCuenta().subscribe({
      next: (res: any) => {
        this.cargando = false;
        this.notificationService.success('Cuenta eliminada correctamente.');
        // Limpiar sesión y redirigir
        localStorage.removeItem('auth_session');
        setTimeout(() => {
          this.router.navigate(['/login']);
        }, 1500);
      },
      error: (error: any) => {
        this.cargando = false;
        const mensajeError = error.error?.message || 'Error al eliminar la cuenta.';
        this.notificationService.error(mensajeError);
      }
    });
  }

  cerrarSesion(): void {
    this.authService.logout();
  }
}
