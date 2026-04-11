import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, tap } from 'rxjs';
import { environment } from '../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private storageKey = 'auth_session';
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient, private router: Router) {}

  private getDeviceName(): string {
    return navigator.userAgent || 'Angular-Minerva-Client';
  }

  registerUser(datos: { nombre: string; email: string; contrasena: string }): Observable<any> {
    const payload = {
      nombre_completo: datos.nombre,
      email: datos.email,
      password: datos.contrasena,
      device_name: this.getDeviceName()
    };
    return this.http.post(`${this.apiUrl}/register`, payload).pipe(
      tap((res: any) => {
        if (res.token) {
          this.saveSession(res.usuario.email, res.token);
        }
      })
    );
  }

  login(email: string, password: string): Observable<any> {
    const payload = {
      email: email,
      password: password,
      device_name: this.getDeviceName()
    };
    return this.http.post(`${this.apiUrl}/login`, payload).pipe(
      tap((res: any) => {
        if (res.token) {
          this.saveSession(res.usuario.email, res.token);
        }
      })
    );
  }

  private saveSession(usuario: string, token: string): void {
    const session = { usuario, token, timestamp: Date.now() };
    localStorage.setItem(this.storageKey, JSON.stringify(session));
  }

  logout(): void {
    const session = this.getUser();
    if (session) {
      this.http.post(`${this.apiUrl}/logout`, {}, {
        headers: { 'Authorization': `Bearer ${session.token}` }
      }).subscribe();
    }
    localStorage.removeItem(this.storageKey);
    this.router.navigate(['/login']);
  }

  isLoggedIn(): boolean {
    return !!localStorage.getItem(this.storageKey);
  }

  getUser(): { usuario: string; token: string } | null {
    const raw = localStorage.getItem(this.storageKey);
    if (!raw) return null;
    try {
      const parsed = JSON.parse(raw);
      return { usuario: parsed.usuario, token: parsed.token };
    } catch {
      return null;
    }
  }
}

