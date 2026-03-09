import { HttpInterceptorFn } from '@angular/common/http';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const sessionRaw = localStorage.getItem('auth_session');
  
  if (sessionRaw) {
    const session = JSON.parse(sessionRaw);
    const authReq = req.clone({
      setHeaders: {
        Authorization: `Bearer ${session.token}`
      }
    });
    return next(authReq);
  }

  return next(req);
};
