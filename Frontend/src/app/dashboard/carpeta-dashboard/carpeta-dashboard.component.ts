import { Component, inject, signal } from '@angular/core';
import { Carpeta } from '../interfaces/carpeta';
import { Router, ActivatedRoute, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';

@Component({
  selector: 'app-carpeta-dashboard',
  standalone: true,
  imports: [],
  templateUrl: './carpeta-dashboard.component.html',
  styleUrl: './carpeta-dashboard.component.css'
})
export class CarpetaDashboardComponent {
  carpetas = signal<Carpeta[]>([
    { id: 1 , nombre: 'Matemáticas', color: 'azul' },
    { id: 2 , nombre: 'Literatura', color: 'amarillo' },
    { id: 3 , nombre: 'Inglés', color: 'verde' },
    { id: 4 , nombre: 'Física', color: 'azul' }
  ]);

  protected ruta = inject(Router);
  private rutaActiva = inject(ActivatedRoute);

  carpetaSeleccionada = signal<Carpeta | undefined>(undefined);

  constructor() {
    this.ruta.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe(() => {
      this.actualizarCarpetaSeleccionada();
    });
    // Inicializar al cargar
    this.actualizarCarpetaSeleccionada();
  }

  actualizarCarpetaSeleccionada() {
    const url = this.ruta.url;
    // La url puede ser /dashboard o /dashboard/123
    const partes = url.split('/');
    if (partes.length === 3 && partes[1] === 'dashboard') {
      const id = parseInt(partes[2]);
      if (!isNaN(id)) {
        this.carpetaSeleccionada.set(this.carpetas().find(c => c.id === id));
        return;
      }
    }
    this.carpetaSeleccionada.set(undefined);
  }

  irACarpeta(id: number) {
    this.ruta.navigate(['/dashboard', id]);
  }

  crearNueva() {
    console.log('Crear nueva asignatura');
  }
}
