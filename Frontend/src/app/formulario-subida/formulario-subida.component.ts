import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, ActivatedRoute, RouterLink } from '@angular/router';
import { MinervaService, Asignatura, Tema } from '../minerva.service';
import { AuthService } from '../auth.service';

@Component({
  selector: 'app-formulario-subida',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './formulario-subida.component.html',
  styleUrl: './formulario-subida.component.css'
})
export class FormularioSubidaComponent implements OnInit {
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

  constructor(
    private minervaService: MinervaService,
    private authService: AuthService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

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
    this.mensaje = 'Subiendo y procesando audio...';

    const formData = new FormData();
    formData.append('audio', this.archivoSeleccionado);
    formData.append('nombre', this.formulario.nombre);
    formData.append('idioma', 'auto');

    this.minervaService.subirAudio(formData, this.formulario.id_tema).subscribe({
      next: (res) => {
        this.mensaje = '¡Archivo procesado con éxito!';
        this.error = false;
        this.cargando = false;
      },
      error: (err) => {
        console.error('Error subida:', err);
        this.mensaje = err.error?.error || 'Error al procesar el archivo';
        this.error = true;
        this.cargando = false;
      }
    });
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
