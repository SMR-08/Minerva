import { Component, signal, OnInit, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MinervaService, Asignatura, Transcripcion, Tema } from '../minerva.service';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.css'
})
export class DashboardComponent implements OnInit {
  asignaturas = signal<Asignatura[]>([]);
  transcripciones = signal<Transcripcion[]>([]);
  
  // Estado para la vista de Temas y Transcripciones
  asignaturaSeleccionada = signal<Asignatura | null>(null);
  temasAsignatura = signal<Tema[]>([]);
  
  temaSeleccionado = signal<Tema | null>(null);
  transcripcionSeleccionada = signal<Transcripcion | null>(null);

  // Computed para transformar transcripciones en el formato de "Actividad" que espera la vista global
  actividades = computed(() => {
    return this.transcripciones().map(t => ({
      titulo: t.titulo,
      asignatura: t.tema?.asignatura?.nombre || 'General',
      tiempo: this.formatearFecha(t.fecha_procesamiento)
    }));
  });

  // Computed para las transcripciones del tema seleccionado
  transcripcionesTema = computed(() => {
    const temaId = this.temaSeleccionado()?.id_tema;
    if (!temaId) return [];
    return this.transcripciones().filter(t => t.id_tema === temaId);
  });

  // Computed para los segmentos de la transcripción seleccionada
  segmentos = computed(() => {
    return this.transcripcionSeleccionada()?.texto_diarizado || [];
  });

  constructor(private minervaService: MinervaService) {}

  ngOnInit(): void {
    this.cargarDatos();
  }

  cargarDatos(): void {
    this.minervaService.getAsignaturas().subscribe({
      next: (data) => this.asignaturas.set(data),
      error: (err) => console.error('Error dashboard asignaturas', err)
    });

    this.minervaService.getTranscripciones().subscribe({
      next: (data) => this.transcripciones.set(data),
      error: (err) => console.error('Error dashboard transcripciones', err)
    });
  }

  // --- LÓGICA DE ASIGNATURAS ---

  crearNuevaAsignatura(): void {
    const nombre = window.prompt('Nombre de la nueva asignatura:');
    if (nombre && nombre.trim()) {
      this.minervaService.crearAsignatura(nombre.trim()).subscribe({
        next: () => this.cargarDatos(),
        error: (err) => console.error('Error al crear asignatura', err)
      });
    }
  }

  eliminarAsignatura(id: number, event: Event): void {
    event.stopPropagation(); // Evitar navegar al hacer clic en borrar
    if (window.confirm('¿Estás seguro de que deseas eliminar esta asignatura? Se borrarán todos sus temas y transcripciones.')) {
      this.minervaService.eliminarAsignatura(id).subscribe({
        next: () => this.cargarDatos(),
        error: (err) => console.error('Error al eliminar asignatura', err)
      });
    }
  }

  // --- LÓGICA DE TEMAS ---

  verTemas(asignatura: Asignatura): void {
    this.asignaturaSeleccionada.set(asignatura);
    this.temaSeleccionado.set(null);
    this.transcripcionSeleccionada.set(null);
    this.cargarTemas(asignatura.id_asignatura);
  }

  volverAAsignaturas(): void {
    this.asignaturaSeleccionada.set(null);
    this.temasAsignatura.set([]);
    this.temaSeleccionado.set(null);
    this.transcripcionSeleccionada.set(null);
  }

  cargarTemas(idAsignatura: number): void {
    this.minervaService.getTemas(idAsignatura).subscribe({
      next: (data) => this.temasAsignatura.set(data),
      error: (err) => console.error('Error al cargar temas', err)
    });
  }

  crearNuevoTema(): void {
    const asigActual = this.asignaturaSeleccionada();
    if (!asigActual) return;

    const nombre = window.prompt(`Nombre del nuevo tema para ${asigActual.nombre}:`);
    if (nombre && nombre.trim()) {
      this.minervaService.crearTema(asigActual.id_asignatura, nombre.trim()).subscribe({
        next: () => this.cargarTemas(asigActual.id_asignatura), // Recargar la lista de temas
        error: (err) => console.error('Error al crear tema', err)
      });
    }
  }

  eliminarTema(idTema: number, event: Event): void {
    event.stopPropagation();
    const asigActual = this.asignaturaSeleccionada();
    if (!asigActual) return;

    if (window.confirm('¿Estás seguro de que deseas eliminar este tema? Se borrarán sus audios/transcripciones asociados.')) {
      this.minervaService.eliminarTema(idTema).subscribe({
        next: () => {
          this.cargarTemas(asigActual.id_asignatura);
          this.cargarDatos(); // Refresh actvidades global
        },
        error: (err) => console.error('Error al eliminar tema', err)
      });
    }
  }

  // --- LÓGICA DE TRANSCRIPCIONES (DENTRO DEL TEMA) ---
  
  verTranscripcionesDeTema(tema: Tema): void {
    this.temaSeleccionado.set(tema);
  }
  
  volverATemas(): void {
    this.temaSeleccionado.set(null);
    this.transcripcionSeleccionada.set(null);
  }

  // --- LÓGICA DE DETALLE DE TRANSCRIPCIÓN ---

  verDetalleTranscripcion(transcripcion: Transcripcion): void {
    this.transcripcionSeleccionada.set(transcripcion);
  }

  volverATranscripciones(): void {
    this.transcripcionSeleccionada.set(null);
  }

  // --- UTILIDADES ---

  formatearFecha(fechaStr: string): string {
    if (!fechaStr) return 'Reciente';
    const fecha = new Date(fechaStr);
    const ahora = new Date();
    const difMs = ahora.getTime() - fecha.getTime();
    const difMin = Math.floor(difMs / 60000);
    
    if (difMin < 60) return `Hace ${difMin} min`;
    const difHoras = Math.floor(difMin / 60);
    if (difHoras < 24) return `Hace ${difHoras}h`;
    
    return fecha.toLocaleDateString();
  }
}
