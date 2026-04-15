import { Component, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink, ActivatedRoute } from '@angular/router';
import { MinervaService, Transcripcion } from '../minerva.service';
import { AuthService } from '../auth.service';

interface Segmento {
  hablante: string;
  inicio: number;
  texto: string;
}

@Component({
  selector: 'app-transcripcion-view',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './transcripcion-view.component.html',
  styleUrl: './transcripcion-view.component.css'
})
export class TranscripcionViewComponent implements OnInit {
  transcripcion = signal<Transcripcion | null>(null);
  segmentos = signal<Segmento[]>([]);
  userMenuOpen = false;
  hablanateActivo = signal<string>('Todos');

  constructor(
    private minervaService: MinervaService,
    private authService: AuthService,
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

  getHablanates(): string[] {
    const hablanates = new Set<string>();
    this.segmentos().forEach(s => hablanates.add(s.hablante));
    return Array.from(hablanates);
  }

  getHablanateColor(hablante: string): string {
    const colors: Record<string, string> = {
      'Profesor': '#5A8A5A',
      'Alumno': '#4A6B8A',
      'Hablante 1': '#5A8A5A',
      'Hablante 2': '#4A6B8A',
    };
    return colors[hablante] || '#6B7280';
  }

  getHablanateBg(hablante: string): string {
    const colors: Record<string, string> = {
      'Profesor': '#E8F5E8',
      'Alumno': '#E8EEF5',
      'Hablante 1': '#E8F5E8',
      'Hablante 2': '#E8EEF5',
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
