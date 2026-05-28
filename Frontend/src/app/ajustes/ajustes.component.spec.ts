import { ComponentFixture, TestBed } from '@angular/core/testing';
import { HttpClientTestingModule } from '@angular/common/http/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { FormsModule } from '@angular/forms';
import { of, throwError } from 'rxjs';

import { AjustesComponent } from './ajustes.component';
import { AuthService } from '../auth.service';
import { MinervaService } from '../minerva.service';
import { NotificationService } from '../notification.service';
import { Router } from '@angular/router';

describe('AjustesComponent', () => {
  let component: AjustesComponent;
  let fixture: ComponentFixture<AjustesComponent>;
  let authService: jasmine.SpyObj<AuthService>;
  let minervaService: jasmine.SpyObj<MinervaService>;
  let notificationService: jasmine.SpyObj<NotificationService>;
  let router: Router;

  beforeEach(async () => {
    const authServiceSpy = jasmine.createSpyObj('AuthService', ['getUser', 'logout']);
    const minervaServiceSpy = jasmine.createSpyObj('MinervaService', [
      'actualizarPerfil',
      'cambiarContrasena',
      'eliminarCuenta'
    ]);
    const notificationServiceSpy = jasmine.createSpyObj('NotificationService', [
      'success',
      'error',
      'warning'
    ]);

    await TestBed.configureTestingModule({
      imports: [
        AjustesComponent,
        HttpClientTestingModule,
        RouterTestingModule,
        FormsModule
      ],
      providers: [
        { provide: AuthService, useValue: authServiceSpy },
        { provide: MinervaService, useValue: minervaServiceSpy },
        { provide: NotificationService, useValue: notificationServiceSpy }
      ]
    }).compileComponents();

    authService = TestBed.inject(AuthService) as jasmine.SpyObj<AuthService>;
    minervaService = TestBed.inject(MinervaService) as jasmine.SpyObj<MinervaService>;
    notificationService = TestBed.inject(NotificationService) as jasmine.SpyObj<NotificationService>;
    router = TestBed.inject(Router);

    authService.getUser.and.returnValue({ usuario: 'test@example.com', token: 'fake-token' });

    fixture = TestBed.createComponent(AjustesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should initialize user data on ngOnInit', () => {
    expect(component.email).toBe('test@example.com');
    expect(component.nombre).toBe('test');
    expect(component.emailNuevo).toBe('test@example.com');
    expect(component.nombreNuevo).toBe('test');
  });

  it('should redirect to login if user is not authenticated', () => {
    authService.getUser.and.returnValue(null);
    spyOn(router, 'navigate');
    component.ngOnInit();
    expect(router.navigate).toHaveBeenCalledWith(['/login']);
  });

  describe('guardarPerfil', () => {
    it('should show error if nombre is empty', () => {
      component.nombreNuevo = '';
      component.guardarPerfil();
      expect(notificationService.error).toHaveBeenCalledWith('El nombre no puede estar vacío.');
    });

    it('should show error if email is empty', () => {
      component.nombreNuevo = 'Test User';
      component.emailNuevo = '';
      component.guardarPerfil();
      expect(notificationService.error).toHaveBeenCalledWith('El correo electrónico no puede estar vacío.');
    });

    it('should show error if email format is invalid', () => {
      component.nombreNuevo = 'Test User';
      component.emailNuevo = 'invalid-email';
      component.guardarPerfil();
      expect(notificationService.error).toHaveBeenCalledWith('Ingresa un correo electrónico válido.');
    });

    it('should call actualizarPerfil with correct parameters and update local state on success', () => {
      component.nombreNuevo = 'New Name';
      component.emailNuevo = 'newemail@example.com';
      minervaService.actualizarPerfil.and.returnValue(of({ success: true }));

      component.guardarPerfil();

      expect(minervaService.actualizarPerfil).toHaveBeenCalledWith('New Name', 'newemail@example.com');
      expect(notificationService.success).toHaveBeenCalledWith('Perfil actualizado correctamente.');
      expect(component.nombre).toBe('New Name');
      expect(component.email).toBe('newemail@example.com');
    });

    it('should show specific error if email is already registered', () => {
      component.nombreNuevo = 'Test User';
      component.emailNuevo = 'duplicate@example.com';
      minervaService.actualizarPerfil.and.returnValue(
        throwError(() => ({ error: { message: 'El email ya está registrado' } }))
      );

      component.guardarPerfil();

      expect(notificationService.error).toHaveBeenCalledWith('Este correo electrónico ya está registrado.');
    });
  });

  describe('cambiarPassword', () => {
    it('should show error if passwordActual is empty', () => {
      component.passwordActual = '';
      component.cambiarPassword();
      expect(notificationService.error).toHaveBeenCalledWith('La contraseña actual es requerida.');
    });

    it('should show error if passwordNuevo is empty', () => {
      component.passwordActual = 'oldpass123';
      component.passwordNuevo = '';
      component.cambiarPassword();
      expect(notificationService.error).toHaveBeenCalledWith('La nueva contraseña no puede estar vacía.');
    });

    it('should show error if passwordNuevo is less than 8 characters', () => {
      component.passwordActual = 'oldpass123';
      component.passwordNuevo = 'short';
      component.cambiarPassword();
      expect(notificationService.error).toHaveBeenCalledWith('La contraseña debe tener al menos 8 caracteres.');
    });

    it('should show error if passwords do not match', () => {
      component.passwordActual = 'oldpass123';
      component.passwordNuevo = 'newpass123';
      component.passwordConfirm = 'different123';
      component.cambiarPassword();
      expect(notificationService.error).toHaveBeenCalledWith('Las contraseñas no coinciden.');
    });

    it('should show warning if new password is same as current', () => {
      component.passwordActual = 'samepass123';
      component.passwordNuevo = 'samepass123';
      component.passwordConfirm = 'samepass123';
      component.cambiarPassword();
      expect(notificationService.warning).toHaveBeenCalledWith('La nueva contraseña debe ser diferente a la actual.');
    });

    it('should call cambiarContrasena and clear form on success', () => {
      component.passwordActual = 'oldpass123';
      component.passwordNuevo = 'newpass123';
      component.passwordConfirm = 'newpass123';
      minervaService.cambiarContrasena.and.returnValue(of({ success: true }));

      component.cambiarPassword();

      expect(minervaService.cambiarContrasena).toHaveBeenCalledWith('oldpass123', 'newpass123');
      expect(notificationService.success).toHaveBeenCalledWith('Contraseña actualizada correctamente.');
      expect(component.passwordActual).toBe('');
      expect(component.passwordNuevo).toBe('');
      expect(component.passwordConfirm).toBe('');
    });

    it('should show specific error if current password is incorrect', () => {
      component.passwordActual = 'wrongpass';
      component.passwordNuevo = 'newpass123';
      component.passwordConfirm = 'newpass123';
      minervaService.cambiarContrasena.and.returnValue(
        throwError(() => ({ error: { message: 'La contraseña actual es incorrecta' } }))
      );

      component.cambiarPassword();

      expect(notificationService.error).toHaveBeenCalledWith('La contraseña actual es incorrecta.');
    });
  });

  describe('eliminarCuenta', () => {
    it('should not proceed if user cancels confirmation', () => {
      spyOn(window, 'confirm').and.returnValue(false);
      component.eliminarCuenta();
      expect(minervaService.eliminarCuenta).not.toHaveBeenCalled();
    });

    it('should call eliminarCuenta and redirect to login on success', (done) => {
      spyOn(window, 'confirm').and.returnValue(true);
      spyOn(router, 'navigate');
      spyOn(localStorage, 'removeItem');
      minervaService.eliminarCuenta.and.returnValue(of({ success: true }));

      component.eliminarCuenta();

      expect(minervaService.eliminarCuenta).toHaveBeenCalled();
      expect(notificationService.success).toHaveBeenCalledWith('Cuenta eliminada correctamente.');
      expect(localStorage.removeItem).toHaveBeenCalledWith('auth_session');

      setTimeout(() => {
        expect(router.navigate).toHaveBeenCalledWith(['/login']);
        done();
      }, 1600);
    });

    it('should show error message on failure', () => {
      spyOn(window, 'confirm').and.returnValue(true);
      minervaService.eliminarCuenta.and.returnValue(
        throwError(() => ({ error: { message: 'Error al eliminar cuenta' } }))
      );

      component.eliminarCuenta();

      expect(notificationService.error).toHaveBeenCalledWith('Error al eliminar cuenta');
    });
  });

  describe('setTab', () => {
    it('should change active tab and clear forms', () => {
      component.passwordActual = 'test';
      component.passwordNuevo = 'test';
      component.passwordConfirm = 'test';
      component.mensaje = { tipo: 'ok', texto: 'test' };

      component.setTab('seguridad');

      expect(component.activeTab).toBe('seguridad');
      expect(component.mensaje).toBeNull();
      expect(component.passwordActual).toBe('');
      expect(component.passwordNuevo).toBe('');
      expect(component.passwordConfirm).toBe('');
    });
  });

  describe('cerrarSesion', () => {
    it('should call authService.logout', () => {
      component.cerrarSesion();
      expect(authService.logout).toHaveBeenCalled();
    });
  });
});
