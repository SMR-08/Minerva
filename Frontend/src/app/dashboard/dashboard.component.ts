import { Component, signal, OnInit, computed, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { MinervaService, Asignatura, Transcripcion, Tema } from '../minerva.service';
import { AuthService } from '../auth.service';
import { NotificationService } from '../notification.service';
import { ModalComponent } from '../modal/modal.component';

interface AsignaturaExtended extends Asignatura {
  temas?: Tema[];
  transcripciones?: number;
  fecha_actualizacion?: string;
}

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, ModalComponent],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.css'
})
export class DashboardComponent implements OnInit {
  asignaturas = signal<AsignaturaExtended[]>([]);
  transcripciones = signal<Transcripcion[]>([]);
  searchQuery = '';
  userMenuOpen = false;

  menuAbiertoId = signal<number | null>(null);
  modalCrear = false;
  modalEditar = false;
  modalConfirmar = false;
  asignaturaEditando: AsignaturaExtended | null = null;
  nombreInput = '';
  profesorInput = '';
  descripcionInput = '';
  colorInput = '#4A6B8A';

  actividades = computed(() => {
    return this.transcripciones()
      .sort((a, b) => {
        const da = a.fecha_procesamiento ? new Date(a.fecha_procesamiento).getTime() : 0;
        const db = b.fecha_procesamiento ? new Date(b.fecha_procesamiento).getTime() : 0;
        return db - da;
      })
      .slice(0, 5)
      .map(t => ({
        titulo: t.titulo,
        asignatura: this._getAsignaturaName(t.id_tema),
        tiempo: this.formatearFecha(t.fecha_procesamiento)
      }));
  });

  constructor(
    private minervaService: MinervaService,
    private authService: AuthService,
    private notifService: NotificationService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.cargarDatos();
  }

  cargarDatos(): void {
    this.minervaService.getAsignaturas().subscribe({
      next: (data) => {
        const extended = data.map(a => ({
          ...a,
          temas: [],
          transcripciones: 0,
          fecha_actualizacion: new Date().toISOString()
        }));
        this.asignaturas.set(extended);

        let loaded = 0;
        extended.forEach(a => {
          this.minervaService.getTemas(a.id_asignatura).subscribe({
            next: (temas) => {
              const current = this.asignaturas();
              this.asignaturas.set(current.map(asig => {
                if (asig.id_asignatura === a.id_asignatura) {
                  return { ...asig, temas };
                }
                return asig;
              }));
              loaded++;
              if (loaded === extended.length) {
                this._updateTranscriptionCounts();
              }
            },
            error: () => { loaded++; }
          });
        });
      },
      error: (err) => console.error('Error dashboard asignaturas', err)
    });

    this.minervaService.getTranscripciones().subscribe({
      next: (data) => {
        this.transcripciones.set(data);
        setTimeout(() => this._updateTranscriptionCounts(), 500);
      },
      error: (err) => console.error('Error dashboard transcripciones', err)
    });
  }

  private _updateTranscriptionCounts(): void {
    const current = this.asignaturas();
    const trans = this.transcripciones();
    this.asignaturas.set(current.map(asig => {
      const temas = asig.temas || [];
      const transCount = trans.filter(t => temas.some(tema => tema.id_tema === t.id_tema)).length;
      return { ...asig, transcripciones: transCount };
    }));
  }

  private _getAsignaturaName(temaId: number): string {
    for (const asig of this.asignaturas()) {
      if (asig.temas?.some(t => t.id_tema === temaId)) return asig.nombre;
    }
    return '';
  }

  onSearch(): void {}

  toggleUserMenu(): void { this.userMenuOpen = !this.userMenuOpen; }

  cerrarSesion(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }

  @HostListener('document:click')
  cerrarMenusGlobal(): void {
    this.menuAbiertoId.set(null);
  }

  toggleMenu(asigId: number, event: Event): void {
    event.stopPropagation();
    this.menuAbiertoId.set(this.menuAbiertoId() === asigId ? null : asigId);
  }

  cerrarMenu(): void {
    this.menuAbiertoId.set(null);
  }

  abrirModalCrear(): void {
    this.nombreInput = '';
    this.modalCrear = true;
    this.cerrarMenu();
  }

  crearAsignatura(): void {
    if (!this.nombreInput?.trim()) return;
    this.minervaService.crearAsignatura(this.nombreInput.trim()).subscribe({
      next: () => {
        this.modalCrear = false;
        this.cargarDatos();
        this.notifService.success('Asignatura creada correctamente');
      },
      error: (err) => {
        console.error('Error al crear asignatura', err);
        this.notifService.error('Error al crear la asignatura');
      }
    });
  }

  abrirModalEditar(asig: AsignaturaExtended, event: Event): void {
    event.stopPropagation();
    this.asignaturaEditando = asig;
    this.nombreInput = asig.nombre;
    this.profesorInput = asig.profesor || '';
    this.descripcionInput = asig.descripcion || '';
    this.colorInput = asig.color_hex || '#4A6B8A';
    this.modalEditar = true;
    this.cerrarMenu();
  }

  guardarAsignatura(): void {
    if (!this.nombreInput?.trim() || !this.asignaturaEditando) return;
    this.minervaService.actualizarAsignatura(this.asignaturaEditando.id_asignatura, {
      nombre: this.nombreInput.trim(),
      profesor: this.profesorInput.trim() || undefined,
      descripcion: this.descripcionInput.trim() || undefined,
      color_hex: this.colorInput,
    }).subscribe({
      next: () => {
        this.modalEditar = false;
        this.asignaturaEditando = null;
        this.cargarDatos();
        this.notifService.success('Asignatura actualizada');
      },
      error: (err) => {
        console.error('Error al editar asignatura', err);
        this.notifService.error('Error al editar la asignatura');
      }
    });
  }

  abrirModalConfirmar(asig: AsignaturaExtended, event: Event): void {
    event.stopPropagation();
    this.asignaturaEditando = asig;
    this.modalConfirmar = true;
    this.cerrarMenu();
  }

  eliminarAsignatura(): void {
    if (!this.asignaturaEditando) return;
    this.minervaService.eliminarAsignatura(this.asignaturaEditando.id_asignatura).subscribe({
      next: () => {
        this.modalConfirmar = false;
        this.asignaturaEditando = null;
        this.cargarDatos();
        this.notifService.success('Asignatura eliminada');
      },
      error: (err) => {
        console.error('Error al eliminar asignatura', err);
        this.notifService.error('Error al eliminar la asignatura');
      }
    });
  }

  verAsignatura(asignatura: AsignaturaExtended): void {
    this.router.navigate(['/asignatura', asignatura.id_asignatura]);
  }

  formatearFecha(fechaStr: string): string {
    if (!fechaStr) return 'Reciente';
    const fecha = new Date(fechaStr);
    const ahora = new Date();
    const difMs = ahora.getTime() - fecha.getTime();
    const difMin = Math.floor(difMs / 60000);
    if (difMin < 60) return `Hace ${difMin} min`;
    const difHoras = Math.floor(difMin / 60);
    if (difHoras < 24) return `Hace ${difHoras} horas`;
    const difDias = Math.floor(difHoras / 24);
    if (difDias === 1) return 'Hace 1 día';
    if (difDias < 7) return `Hace ${difDias} días`;
    return fecha.toLocaleDateString();
  }
}
