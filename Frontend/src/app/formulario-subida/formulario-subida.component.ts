import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MinervaService, Asignatura, Tema } from '../minerva.service';

@Component({
  selector: 'app-formulario-subida',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './formulario-subida.component.html',
  styleUrl: './formulario-subida.component.css'
})
export class FormularioSubidaComponent implements OnInit {
  formulario = {
    nombre: '',
    id_asignatura: null as number | null,
    id_tema: null as number | null
  };

  asignaturas: Asignatura[] = [];
  todosLosTemas: Tema[] = [];
  temasFiltrados: Tema[] = [];

  archivoSeleccionado: File | null = null;
  mensaje: string = '';
  error: boolean = false;
  enviado: boolean = false;
  cargando: boolean = false;

  constructor(private minervaService: MinervaService) {}

  ngOnInit(): void {
    this.cargarDatos();
  }

  cargarDatos(): void {
    this.minervaService.getAsignaturas().subscribe({
      next: (data) => this.asignaturas = data,
      error: (err) => console.error('Error cargando asignaturas', err)
    });
  }

  onAsignaturaChange(): void {
    this.formulario.id_tema = null;
    this.temasFiltrados = [];
    
    if (this.formulario.id_asignatura) {
      this.minervaService.getTemas(this.formulario.id_asignatura).subscribe({
        next: (data) => this.temasFiltrados = data,
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
    // Agregamos idioma por defecto
    formData.append('idioma', 'auto');

    this.minervaService.subirAudio(formData, this.formulario.id_tema).subscribe({
      next: (res) => {
        this.mensaje = '¡Archivo procesado con éxito!';
        this.error = false;
        this.cargando = false;
        // this.onLimpiar();
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
    this.temasFiltrados = [];
  }
}

