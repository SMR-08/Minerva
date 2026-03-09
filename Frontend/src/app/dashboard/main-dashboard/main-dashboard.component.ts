import { Component, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Actividad } from '../interfaces/actividad';
import { CarpetaDashboardComponent } from '../carpeta-dashboard/carpeta-dashboard.component';

@Component({
  selector: 'app-main-dashboard',
  standalone: true,
  imports: [CommonModule, CarpetaDashboardComponent],
  templateUrl: './main-dashboard.component.html',
  styleUrl: './main-dashboard.component.css',
})
export class DashboardComponent {
  actividades = signal<Actividad[]>([
    { titulo: 'Clase 1 - Introducción a Derivadas', carpeta: 'Matemáticas', tiempo: 'Hace 2h'},
    { titulo: 'Clase 2 - Historia del Arte', carpeta: 'Literatura', tiempo: 'Hace 4h'},
    { titulo: 'Clase 3 - Verbos Irregulares', carpeta: 'Inglés', tiempo: 'Ayer'}
  ]);



}
