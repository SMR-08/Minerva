import { Component, OnInit, signal, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, ActivatedRoute, RouterLink } from '@angular/router';
import { MinervaService, Asignatura, Tema } from '../minerva.service';
import { AuthService } from '../auth.service';
import { SseService, SseEvent } from '../sse.service';
import { NotificationService } from '../notification.service';

@Component({
  selector: 'app-formulario-subida',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './formulario-subida.component.html',
  styleUrl: './formulario-subida.component.css'
})
export class FormularioSubidaComponent implements OnInit, OnDestroy {
  asignaturas = signal<Asignatura[]>([]);
  temasFiltrados = signal<Tema[]>([]);

  formulario = {
    nombre: '',
    id_asignatura: null as number | null,
    id_tema: null as number | null
  };

  archivoSeleccionado: File | null = null;
  mensaje: string = '';
  error: boolean = false;
  enviado: boolean = false;
  cargando: boolean = false;
  userMenuOpen = false;

  estadoTranscripcion = signal<string>('');
  progreso = signal<number>(0);
  etapaActual = signal<string>('');
  posicionCola = signal<number>(0);
  etaSegundos = signal<number>(0);
  transcripcionUrl = signal<string>('');
  mensajeError = signal<string>('');

  private eventSource: EventSource | null = null;

  constructor(
    private minervaService: MinervaService,
    private sseService: SseService,
    private authService: AuthService,
    private router: Router,
    private route: ActivatedRoute,
    private notifService: NotificationService
  ) {}

  ngOnDestroy(): void {
    this.eventSource?.close();
  }

  ngOnInit(): void {
    this.cargarDatos();

    // Check for temaId query param
    this.route.queryParams.subscribe(params => {
      if (params['temaId']) {
        const temaId = parseInt(params['temaId'], 10);
        // Find the tema and its asignatura
        this.minervaService.getAsignaturas().subscribe({
          next: (asignaturas) => {
            this.asignaturas.set(asignaturas);
            // Load all temas to find the one
            let loaded = 0;
            asignaturas.forEach(asig => {
              this.minervaService.getTemas(asig.id_asignatura).subscribe({
                next: (temas) => {
                  const found = temas.find(t => t.id_tema === temaId);
                  if (found) {
                    this.formulario.id_asignatura = asig.id_asignatura;
                    this.formulario.id_tema = temaId;
                    this.temasFiltrados.set(temas);
                  }
                  loaded++;
                },
                error: () => { loaded++; }
              });
            });
          },
          error: (err) => console.error('Error cargando asignaturas', err)
        });
      }
    });
  }

  cargarDatos(): void {
    this.minervaService.getAsignaturas().subscribe({
      next: (data) => this.asignaturas.set(data),
      error: (err) => console.error('Error cargando asignaturas', err)
    });
  }

  onAsignaturaChange(): void {
    this.formulario.id_tema = null;
    this.temasFiltrados.set([]);

    if (this.formulario.id_asignatura) {
      this.minervaService.getTemas(this.formulario.id_asignatura).subscribe({
        next: (data) => this.temasFiltrados.set(data),
        error: (err) => console.error('Error cargando temas', err)
      });
    }
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      this.archivoSeleccionado = input.files[0];
      this.enviado = false;
    }
  }

  onSubmit(): void {
    this.enviado = true;

    if (!this.formulario.nombre || !this.archivoSeleccionado || !this.formulario.id_tema) {
      this.mensaje = 'Por favor, completa los campos requeridos (Nombre, Tema y Archivo)';
      this.error = true;
      return;
    }

    this.cargando = true;
    this.mensaje = 'Subiendo archivo...';
    this.error = false;
    this.estadoTranscripcion.set('SUBIENDO');
    this.progreso.set(0);
    this.etapaActual.set('');
    this.posicionCola.set(0);
    this.etaSegundos.set(0);
    this.transcripcionUrl.set('');
    this.mensajeError.set('');

    const formData = new FormData();
    formData.append('audio', this.archivoSeleccionado);
    formData.append('nombre', this.formulario.nombre);
    formData.append('idioma', 'auto');

    this.minervaService.subirAudio(formData, this.formulario.id_tema).subscribe({
      next: (res) => {
        const idAsig = this.formulario.id_asignatura;
        this.notifService.success('Audio subido. Se procesará en segundo plano.');
        if (idAsig) {
          this.router.navigate(['/asignatura', idAsig]);
        } else {
          this.router.navigate(['/dashboard']);
        }
      },
      error: (err) => {
        console.error('Error subida:', err);
        this.cargando = false;
        this.estadoTranscripcion.set('FALLIDO');
        this.mensaje = err.error?.error || 'Error al subir el archivo';
        this.error = true;
        this.mensajeError.set(err.error?.details || err.error?.error || 'Error al subir');
        this.notifService.error(err.error?.error || 'Error al subir el archivo');
      }
    });
  }

  conectarSSE(uuid: string): void {
    this.eventSource?.close();
    const token = this.authService.getUser()?.token || '';
    this.eventSource = this.sseService.conectar(
      uuid,
      token,
      (event: SseEvent) => this.manejarEventoSSE(event),
      () => {
        if (this.estadoTranscripcion() !== 'COMPLETADO' && this.estadoTranscripcion() !== 'FALLIDO') {
          this.mensaje = 'Conexión perdida. Recarga la página para ver el estado.';
        }
      }
    );
  }

  manejarEventoSSE(event: SseEvent): void {
    this.estadoTranscripcion.set(event.estado);

    switch (event.estado) {
      case 'ENCOLADO':
        this.cargando = true;
        this.mensaje = event.mensaje || 'En cola de procesamiento...';
        this.posicionCola.set(event.posicion || 0);
        break;

      case 'PROCESANDO':
        this.cargando = true;
        this.progreso.set(event.progreso || 0);
        this.etapaActual.set(event.etapa || '');
        this.etaSegundos.set(event.eta_segundos || 0);
        this.mensaje = event.mensaje || 'Procesando...';
        break;

      case 'COMPLETADO':
        this.cargando = false;
        this.mensaje = '¡Transcripción completada!';
        this.progreso.set(100);
        this.transcripcionUrl.set(event.url || '');
        setTimeout(() => {
          this.notifService.success('Transcripción completada correctamente');
          const idAsig = this.formulario.id_asignatura;
          if (idAsig) {
            this.router.navigate(['/asignatura', idAsig]);
          } else {
            this.router.navigate(['/dashboard']);
          }
        }, 1500);
        break;

      case 'FALLIDO':
        this.cargando = false;
        this.error = true;
        this.mensaje = event.mensaje || 'Error en el procesamiento';
        this.mensajeError.set(event.error || 'Error desconocido');
        this.notifService.error(event.error || 'Error en el procesamiento');
        break;
    }
  }

  onLimpiar(): void {
    this.formulario = {
      nombre: '',
      id_asignatura: null,
      id_tema: null
    };
    this.archivoSeleccionado = null;
    this.mensaje = '';
    this.error = false;
    this.enviado = false;
    this.temasFiltrados.set([]);
    this.estadoTranscripcion.set('');
    this.progreso.set(0);
    this.etapaActual.set('');
    this.posicionCola.set(0);
    this.etaSegundos.set(0);
    this.transcripcionUrl.set('');
    this.mensajeError.set('');
  }

  toggleUserMenu(): void {
    this.userMenuOpen = !this.userMenuOpen;
  }

  cerrarSesion(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }

  volverAtras(): void {
    this.router.navigate(['/dashboard']);
  }

  formatearTamano(bytes: number): string {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }
}
