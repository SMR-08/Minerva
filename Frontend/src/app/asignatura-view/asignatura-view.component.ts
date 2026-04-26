import { Component, signal, OnInit, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink, ActivatedRoute } from '@angular/router';
import { MinervaService, Asignatura, Transcripcion, Tema } from '../minerva.service';
import { AuthService } from '../auth.service';
import { NotificationService } from '../notification.service';
import { ModalComponent } from '../modal/modal.component';

@Component({
  selector: 'app-asignatura-view',
  standalone: true,
  imports: [CommonModule, RouterLink, FormsModule, ModalComponent],
  templateUrl: './asignatura-view.component.html',
  styleUrl: './asignatura-view.component.css'
})
export class AsignaturaViewComponent implements OnInit {
  asignatura = signal<Asignatura | null>(null);
  temas = signal<Tema[]>([]);
  transcripciones = signal<Transcripcion[]>([]);
  userMenuOpen = false;

  menuTemaId = signal<number | null>(null);
  menuTransId = signal<number | null>(null);

  modalCrearTema = false;
  modalEditarTema = false;
  modalEliminarTema = false;
  temaEditando: Tema | null = null;
  temaInput = '';

  modalEditarTrans = false;
  modalEliminarTrans = false;
  transEditando: Transcripcion | null = null;
  transTituloInput = '';

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

  @HostListener('document:click')
  cerrarMenusGlobal(): void {
    this.menuTemaId.set(null);
    this.menuTransId.set(null);
  }

  toggleMenuTema(temaId: number, event: Event): void {
    event.stopPropagation();
    this.menuTemaId.set(this.menuTemaId() === temaId ? null : temaId);
    this.menuTransId.set(null);
  }

  toggleMenuTrans(transId: number, event: Event): void {
    event.stopPropagation();
    this.menuTransId.set(this.menuTransId() === transId ? null : transId);
    this.menuTemaId.set(null);
  }

  abrirModalCrearTema(): void {
    this.temaInput = '';
    this.modalCrearTema = true;
    this.menuTemaId.set(null);
  }

  crearTema(): void {
    const asig = this.asignatura();
    if (!asig || !this.temaInput?.trim()) return;
    this.minervaService.crearTema(asig.id_asignatura, this.temaInput.trim()).subscribe({
      next: () => {
        this.modalCrearTema = false;
        this.recargarTemas();
        this.notifService.success('Tema creado correctamente');
      },
      error: () => this.notifService.error('Error al crear el tema')
    });
  }

  abrirModalEditarTema(tema: Tema, event: Event): void {
    event.stopPropagation();
    this.temaEditando = tema;
    this.temaInput = tema.nombre;
    this.modalEditarTema = true;
    this.menuTemaId.set(null);
  }

  guardarTema(): void {
    if (!this.temaEditando || !this.temaInput?.trim()) return;
    this.minervaService.actualizarTema(this.temaEditando.id_tema, {
      nombre: this.temaInput.trim()
    }).subscribe({
      next: () => {
        this.modalEditarTema = false;
        this.temaEditando = null;
        this.recargarTemas();
        this.notifService.success('Tema actualizado');
      },
      error: () => this.notifService.error('Error al editar el tema')
    });
  }

  abrirModalEliminarTema(tema: Tema, event: Event): void {
    event.stopPropagation();
    this.temaEditando = tema;
    this.modalEliminarTema = true;
    this.menuTemaId.set(null);
  }

  eliminarTema(): void {
    if (!this.temaEditando) return;
    this.minervaService.eliminarTema(this.temaEditando.id_tema).subscribe({
      next: () => {
        this.modalEliminarTema = false;
        this.temaEditando = null;
        this.recargarTemas();
        this.notifService.success('Tema eliminado');
      },
      error: () => this.notifService.error('Error al eliminar el tema')
    });
  }

  abrirModalEditarTrans(trans: Transcripcion, event: Event): void {
    event.stopPropagation();
    this.transEditando = trans;
    this.transTituloInput = trans.titulo;
    this.modalEditarTrans = true;
    this.menuTransId.set(null);
  }

  guardarTranscripcion(): void {
    if (!this.transEditando || !this.transTituloInput?.trim()) return;
    this.minervaService.actualizarTranscripcion(this.transEditando.id_transcripcion, {
      titulo: this.transTituloInput.trim()
    }).subscribe({
      next: (res) => {
        this.modalEditarTrans = false;
        this.transEditando = null;
        const current = this.transcripciones();
        this.transcripciones.set(current.map(t =>
          t.id_transcripcion === res.id_transcripcion ? res : t
        ));
        this.notifService.success('Título actualizado');
      },
      error: () => this.notifService.error('Error al editar la transcripción')
    });
  }

  abrirModalEliminarTrans(trans: Transcripcion, event: Event): void {
    event.stopPropagation();
    this.transEditando = trans;
    this.modalEliminarTrans = true;
    this.menuTransId.set(null);
  }

  eliminarTranscripcion(): void {
    if (!this.transEditando) return;
    this.minervaService.eliminarTranscripcion(this.transEditando.id_transcripcion).subscribe({
      next: () => {
        this.modalEliminarTrans = false;
        const id = this.transEditando!.id_transcripcion;
        this.transEditando = null;
        this.transcripciones.set(this.transcripciones().filter(t => t.id_transcripcion !== id));
        this.notifService.success('Transcripción eliminada');
      },
      error: () => this.notifService.error('Error al eliminar la transcripción')
    });
  }

  private recargarTemas(): void {
    const asig = this.asignatura();
    if (!asig) return;
    this.minervaService.getTemas(asig.id_asignatura).subscribe({
      next: (temas) => this.temas.set(temas)
    });
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
