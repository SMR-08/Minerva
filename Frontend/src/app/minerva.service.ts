import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../environments/environment';

export interface Asignatura {
  id_asignatura: number;
  nombre: string;
  profesor?: string;
  descripcion?: string;
  color_hex?: string;
}

export interface Tema {
  id_tema: number;
  id_asignatura: number;
  nombre: string;
  asignatura?: Asignatura;
}

export interface Transcripcion {
  id_transcripcion: number;
  id_tema: number;
  titulo: string;
  estado?: string;
  progreso_porcentaje?: number;
  etapa_actual?: string | null;
  uuid_referencia?: string;
  fecha_procesamiento: string;
  duracion_segundos: number;
  texto_plano?: string;
  texto_diarizado?: any[];
  resumen_ia?: string;
  error_mensaje?: string;
  tema?: {
    nombre: string;
    asignatura?: Asignatura;
  };
}

@Injectable({
  providedIn: 'root'
})
export class MinervaService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getAsignaturas(): Observable<Asignatura[]> {
    return this.http.get<Asignatura[]>(`${this.apiUrl}/asignaturas`);
  }

  getTemas(idAsignatura: number): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/temas?asignatura_id=${idAsignatura}`);
  }

  crearTema(idAsignatura: number, nombre: string): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/temas`, {
      id_asignatura: idAsignatura,
      nombre: nombre
    });
  }

  eliminarTema(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/temas/${id}`);
  }

  crearAsignatura(nombre: string): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/asignaturas`, { nombre });
  }

  eliminarAsignatura(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/asignaturas/${id}`);
  }

  getTags(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/tags`);
  }

  crearTag(nombre: string, color: string = '#6B7280'): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/tags`, { nombre, color_hex: color });
  }

  eliminarTag(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/tags/${id}`);
  }

  subirAudio(formData: FormData, id_tema: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/temas/${id_tema}/procesar-audio`, formData);
  }

  getTranscripciones(): Observable<Transcripcion[]> {
    return this.http.get<Transcripcion[]>(`${this.apiUrl}/transcripciones`);
  }

  getTranscripcion(id: number): Observable<Transcripcion> {
    return this.http.get<Transcripcion>(`${this.apiUrl}/transcripciones/${id}`);
  }

  actualizarAsignatura(id: number, datos: Partial<Asignatura>): Observable<any> {
    return this.http.put(`${this.apiUrl}/asignaturas/${id}`, datos);
  }

  actualizarTema(id: number, datos: { nombre?: string }): Observable<any> {
    return this.http.put(`${this.apiUrl}/temas/${id}`, datos);
  }

  actualizarTranscripcion(id: number, datos: { titulo?: string }): Observable<any> {
    return this.http.put(`${this.apiUrl}/transcripciones/${id}`, datos);
  }

  eliminarTranscripcion(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/transcripciones/${id}`);
  }

  verificarEstadoIA(): Observable<any> {
    return this.http.get(`${this.apiUrl}/ia/estado`);
  }
}
