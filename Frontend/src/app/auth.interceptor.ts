import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { NotificationService } from './notification.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const notifService = inject(NotificationService);
  const sessionRaw = localStorage.getItem('auth_session');

  if (sessionRaw) {
    const session = JSON.parse(sessionRaw);
    const authReq = req.clone({
      setHeaders: {
        Authorization: `Bearer ${session.token}`
      }
    });
    return next(authReq).pipe(
      catchError((error) => {
        if (error.status === 401) {
          localStorage.removeItem('auth_session');
          notifService.error('Sesión expirada. Inicia sesión de nuevo.');
          router.navigate(['/login']);
        }
        return throwError(() => error);
      })
    );
  }

  return next(req);
};
