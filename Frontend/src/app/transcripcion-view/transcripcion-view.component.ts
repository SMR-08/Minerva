import { Component, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink, ActivatedRoute } from '@angular/router';
import { MinervaService, Transcripcion } from '../minerva.service';
import { AuthService } from '../auth.service';
import { NotificationService } from '../notification.service';
import { ModalComponent } from '../modal/modal.component';

interface Segmento {
  hablante: string;
  inicio: number;
  texto: string;
}

@Component({
  selector: 'app-transcripcion-view',
  standalone: true,
  imports: [CommonModule, RouterLink, FormsModule, ModalComponent],
  templateUrl: './transcripcion-view.component.html',
  styleUrl: './transcripcion-view.component.css'
})
export class TranscripcionViewComponent implements OnInit {
  transcripcion = signal<Transcripcion | null>(null);
  segmentos = signal<Segmento[]>([]);
  userMenuOpen = false;
  hablanateActivo = signal<string>('Todos');
  estadoTranscripcion = signal<string>('');
  errorMensaje = signal<string>('');

  modalEditar = false;
  modalEliminar = false;
  tituloInput = '';

  constructor(
    private minervaService: MinervaService,
    private authService: AuthService,
    private notifService: NotificationService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    const id = parseInt(this.route.snapshot.paramMap.get('id') || '0', 10);
    if (!id) { this.router.navigate(['/dashboard']); return; }

    this.minervaService.getTranscripciones().subscribe({
      next: (data) => {
        const trans = data.find(t => t.id_transcripcion === id);
        if (trans) {
          this.transcripcion.set(trans);
          this.estadoTranscripcion.set(trans.estado || '');
          this.errorMensaje.set(trans.error_mensaje || '');
          const diarizado = trans.texto_diarizado as Segmento[] | undefined;
          if (diarizado && Array.isArray(diarizado)) {
            this.segmentos.set(diarizado);
          }
        } else {
          this.router.navigate(['/dashboard']);
        }
      },
      error: () => this.router.navigate(['/dashboard'])
    });
  }

  abrirModalEditar(): void {
    this.tituloInput = this.transcripcion()?.titulo || '';
    this.modalEditar = true;
  }

  guardarTitulo(): void {
    const trans = this.transcripcion();
    if (!trans || !this.tituloInput?.trim()) return;
    this.minervaService.actualizarTranscripcion(trans.id_transcripcion, {
      titulo: this.tituloInput.trim()
    }).subscribe({
      next: (res) => {
        this.modalEditar = false;
        this.transcripcion.set(res);
        this.notifService.success('Título actualizado');
      },
      error: () => this.notifService.error('Error al editar el título')
    });
  }

  abrirModalEliminar(): void {
    this.modalEliminar = true;
  }

  eliminarTranscripcion(): void {
    const trans = this.transcripcion();
    if (!trans) return;
    this.minervaService.eliminarTranscripcion(trans.id_transcripcion).subscribe({
      next: () => {
        this.modalEliminar = false;
        this.notifService.success('Transcripción eliminada');
        const idAsig = this.getIdAsignatura();
        if (idAsig) {
          this.router.navigate(['/asignatura', idAsig]);
        } else {
          this.router.navigate(['/dashboard']);
        }
      },
      error: () => this.notifService.error('Error al eliminar la transcripción')
    });
  }

  getHablanates(): string[] {
    const hablanates = new Set<string>();
    this.segmentos().forEach(s => hablanates.add(s.hablante));
    return Array.from(hablanates);
  }

  getHablanateColor(hablante: string): string {
    const colors: Record<string, string> = {
      'Profesor': '#4A6B8A',
      'Alumno': '#4A6B8A',
      'Hablante 1': '#5A8A5A',
      'Hablante 2': '#5A8A5A',
    };
    return colors[hablante] || '#6B7280';
  }

  getHablanateBg(hablante: string): string {
    const colors: Record<string, string> = {
      'Profesor': '#E8EEF5',
      'Alumno': '#E8EEF5',
      'Hablante 1': '#E8F5E8',
      'Hablante 2': '#E8F5E8',
    };
    return colors[hablante] || '#F0F2F5';
  }

  formatearTiempo(segundos: number): string {
    const mins = Math.floor(segundos / 60);
    const secs = Math.floor(segundos % 60);
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  }

  formatearDuracion(segundos: number): string {
    if (!segundos || segundos <= 0) return '';
    const mins = Math.floor(segundos / 60);
    if (mins >= 60) {
      const h = Math.floor(mins / 60);
      const m = mins % 60;
      return `${h}h ${m}min`;
    }
    return `${mins} min`;
  }

  getNombreAsignatura(): string {
    return this.transcripcion()?.tema?.asignatura?.nombre || '';
  }

  getColorAsignatura(): string {
    return this.transcripcion()?.tema?.asignatura?.color_hex || '#4A6B8A';
  }

  getTextColorForBg(bg: string): string {
    if (!bg || bg === '#4A6B8A') return '#FFFFFF';
    const hex = bg.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return luminance > 0.6 ? '#1a1a1a' : '#FFFFFF';
  }

  getNombreProfesor(): string {
    return this.transcripcion()?.tema?.asignatura?.profesor || '';
  }

  getNombreTema(): string {
    return this.transcripcion()?.tema?.nombre || '';
  }

  getIdAsignatura(): number | null {
    return this.transcripcion()?.tema?.asignatura?.id_asignatura || null;
  }

  toggleUserMenu(): void { this.userMenuOpen = !this.userMenuOpen; }

  cerrarSesion(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }
}
