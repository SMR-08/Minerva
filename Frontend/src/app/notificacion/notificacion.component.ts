import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subscription } from 'rxjs';
import { NotificationService, Notificacion } from '../notification.service';

@Component({
  selector: 'app-notificacion',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="notif-container">
      @for (n of notificaciones; track n.id) {
        <div class="notif-toast" [class]="'notif-' + n.tipo" (click)="dismiss(n.id)">
          <div class="notif-icon">
            @if (n.tipo === 'success') {
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            } @else if (n.tipo === 'error') {
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            } @else if (n.tipo === 'warning') {
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            } @else {
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            }
          </div>
          <span class="notif-text">{{ n.mensaje }}</span>
          <button class="notif-close" (click)="$event.stopPropagation(); dismiss(n.id)">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
      }
    </div>
  `,
  styles: [`
    .notif-container {
      position: fixed;
      top: 24px;
      right: 24px;
      z-index: 999999;
      display: flex;
      flex-direction: column;
      gap: 10px;
      pointer-events: none;
      max-width: 420px;
    }
    .notif-toast {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 16px;
      border-radius: 10px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.15);
      pointer-events: auto;
      cursor: pointer;
      animation: notifIn 0.3s ease;
      font-family: 'Cormorant Garamond', serif;
      font-size: 0.95rem;
      line-height: 1.4;
    }
    @keyframes notifIn {
      from { opacity: 0; transform: translateX(100px); }
      to { opacity: 1; transform: translateX(0); }
    }
    @keyframes notifOut {
      from { opacity: 1; transform: translateX(0); }
      to { opacity: 0; transform: translateX(100px); }
    }
    .notif-icon {
      flex-shrink: 0;
      display: flex;
      align-items: center;
    }
    .notif-text {
      flex: 1;
      font-weight: 500;
    }
    .notif-close {
      flex-shrink: 0;
      width: 28px; height: 28px;
      border: none;
      background: transparent;
      border-radius: 6px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0.6;
      transition: all 0.2s;
    }
    .notif-close:hover { opacity: 1; background: rgba(0,0,0,0.08); }
    .notif-success {
      background: #F0FDF4;
      border: 1px solid #86EFAC;
      color: #166534;
    }
    .notif-success .notif-icon { color: #22C55E; }
    .notif-error {
      background: #FEF2F2;
      border: 1px solid #FECACA;
      color: #991B1B;
    }
    .notif-error .notif-icon { color: #EF4444; }
    .notif-warning {
      background: #FFFBEB;
      border: 1px solid #FDE68A;
      color: #92400E;
    }
    .notif-warning .notif-icon { color: #F59E0B; }
    .notif-info {
      background: #EFF6FF;
      border: 1px solid #BFDBFE;
      color: #1E40AF;
    }
    .notif-info .notif-icon { color: #3B82F6; }
  `]
})
export class NotificacionComponent implements OnInit, OnDestroy {
  notificaciones: Notificacion[] = [];
  private sub?: Subscription;

  constructor(private notifService: NotificationService) {}

  ngOnInit(): void {
    this.sub = this.notifService.notificaciones$.subscribe(n => {
      this.notificaciones = n;
    });
  }

  ngOnDestroy(): void {
    this.sub?.unsubscribe();
  }

  dismiss(id: string): void {
    this.notifService.dismiss(id);
  }
}
