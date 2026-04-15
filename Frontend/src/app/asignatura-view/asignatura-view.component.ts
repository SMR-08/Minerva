import { Component, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink, ActivatedRoute } from '@angular/router';
import { MinervaService, Asignatura, Transcripcion, Tema } from '../minerva.service';
import { AuthService } from '../auth.service';

@Component({
  selector: 'app-asignatura-view',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './asignatura-view.component.html',
  styleUrl: './asignatura-view.component.css'
})
export class AsignaturaViewComponent implements OnInit {
  asignatura = signal<Asignatura | null>(null);
  temas = signal<Tema[]>([]);
  transcripciones = signal<Transcripcion[]>([]);
  userMenuOpen = false;

  constructor(
    private minervaService: MinervaService,
    private authService: AuthService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    const id = parseInt(this.route.snapshot.paramMap.get('id') || '0', 10);
    if (!id) { this.router.navigate(['/dashboard']); return; }

    this.minervaService.getAsignaturas().subscribe({
      next: (data) => {
        const asig = data.find(a => a.id_asignatura === id);
        if (!asig) { this.router.navigate(['/dashboard']); return; }
        this.asignatura.set(asig);
        this.minervaService.getTemas(id).subscribe({
          next: (temas) => {
            this.temas.set(temas);
            this.minervaService.getTranscripciones().subscribe({
              next: (trans) => this.transcripciones.set(trans)
            });
          }
        });
      }
    });
  }

  transcripcionesPorTema(temaId: number): Transcripcion[] {
    return this.transcripciones().filter(t => t.id_tema === temaId);
  }

  totalTranscripciones(): number {
    const ids = this.temas().map(t => t.id_tema);
    return this.transcripciones().filter(t => ids.includes(t.id_tema)).length;
  }

  subirAudioParaTema(tema: Tema): void {
    this.router.navigate(['/subir'], { queryParams: { temaId: tema.id_tema } });
  }

  verDetalleTranscripcion(transcripcion: Transcripcion): void {
    this.router.navigate(['/transcripcion', transcripcion.id_transcripcion]);
  }

  openTemaMenu(temaId: number, event: Event): void {
    event.stopPropagation();
    if (window.confirm('¿Eliminar este tema y sus transcripciones?')) {
      const asig = this.asignatura();
      if (!asig) return;
      this.minervaService.eliminarTema(temaId).subscribe({
        next: () => this.minervaService.getTemas(asig.id_asignatura).subscribe({
          next: (temas) => this.temas.set(temas)
        })
      });
    }
  }

  crearNuevoTema(): void {
    const asig = this.asignatura();
    if (!asig) return;
    const nombre = window.prompt(`Nombre del nuevo tema para ${asig.nombre}:`);
    if (nombre?.trim()) {
      this.minervaService.crearTema(asig.id_asignatura, nombre.trim()).subscribe({
        next: () => this.minervaService.getTemas(asig.id_asignatura).subscribe({
          next: (temas) => this.temas.set(temas)
        })
      });
    }
  }

  toggleUserMenu(): void { this.userMenuOpen = !this.userMenuOpen; }

  cerrarSesion(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }

  formatearDuracion(segundos: number): string {
    if (!segundos || segundos <= 0) return '';
    const mins = Math.floor(segundos / 60);
    if (mins >= 60) {
      const h = Math.floor(mins / 60);
      const m = mins % 60;
      return `${h}h ${m}min`;
    }
    return `${mins} minutos`;
  }
}
