import { Injectable } from '@angular/core';
import { Subject } from 'rxjs';

export type TipoNotificacion = 'success' | 'error' | 'info' | 'warning';

export interface Notificacion {
  id: string;
  tipo: TipoNotificacion;
  mensaje: string;
  duracion: number;
}

@Injectable({
  providedIn: 'root'
})
export class NotificationService {
  private notificaciones = new Subject<Notificacion[]>();
  private activas: Notificacion[] = [];
  private contador = 0;

  notificaciones$ = this.notificaciones.asObservable();

  private emitir(): void {
    this.notificaciones.next([...this.activas]);
  }

  private agregar(tipo: TipoNotificacion, mensaje: string, duracion: number): void {
    const id = `notif-${++this.contador}`;
    const notif: Notificacion = { id, tipo, mensaje, duracion };
    this.activas.push(notif);
    this.emitir();

    if (duracion > 0) {
      setTimeout(() => this.dismiss(id), duracion);
    }
  }

  success(mensaje: string, duracion = 4000): void {
    this.agregar('success', mensaje, duracion);
  }

  error(mensaje: string, duracion = 6000): void {
    this.agregar('error', mensaje, duracion);
  }

  info(mensaje: string, duracion = 4000): void {
    this.agregar('info', mensaje, duracion);
  }

  warning(mensaje: string, duracion = 5000): void {
    this.agregar('warning', mensaje, duracion);
  }

  dismiss(id: string): void {
    this.activas = this.activas.filter(n => n.id !== id);
    this.emitir();
  }
}
