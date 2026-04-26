import { Component, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-modal',
  standalone: true,
  imports: [CommonModule],
  template: `
    @if (show) {
      <div class="modal-overlay" (click)="cerrar.emit()">
        <div class="modal-card" [class.modal-sm]="size === 'sm'" [class.modal-lg]="size === 'lg'" (click)="$event.stopPropagation()">
          <div class="modal-header">
            <h3 class="modal-title">{{ titulo }}</h3>
            <button class="modal-close" (click)="cerrar.emit()">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
            </button>
          </div>
          <div class="modal-body">
            <ng-content></ng-content>
          </div>
          <ng-content select="[modal-actions]"></ng-content>
        </div>
      </div>
    }
  `,
  styles: [`
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 99999;
      padding: 24px;
    }
    .modal-card {
      background: #FFFFFF;
      border-radius: 12px;
      width: 100%;
      max-width: 480px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.15);
      animation: modalIn 0.2s ease;
    }
    .modal-sm { max-width: 360px; }
    .modal-lg { max-width: 640px; }
    @keyframes modalIn {
      from { opacity: 0; transform: scale(0.95) translateY(10px); }
      to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px 24px 0;
    }
    .modal-title {
      font-family: 'Cinzel', serif;
      font-size: 1.1rem;
      font-weight: 600;
      color: #1a1a1a;
      margin: 0;
    }
    .modal-close {
      width: 32px; height: 32px;
      border: none;
      background: transparent;
      border-radius: 6px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #999;
      transition: all 0.2s;
    }
    .modal-close:hover { background: #F3F4F6; color: #1a1a1a; }
    .modal-body {
      padding: 20px 24px;
      font-family: 'Cormorant Garamond', serif;
      font-size: 1rem;
      color: #1a1a1a;
    }
    :host ::ng-deep [modal-actions] {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      padding: 0 24px 20px;
    }
  `]
})
export class ModalComponent {
  @Input() show = false;
  @Input() titulo = '';
  @Input() size: 'sm' | 'md' | 'lg' = 'md';
  @Output() cerrar = new EventEmitter<void>();
}
