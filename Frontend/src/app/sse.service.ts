import { Injectable } from '@angular/core';
import { environment } from '../environments/environment';

export interface SseEvent {
  estado: string;
  progreso?: number;
  etapa?: string;
  posicion?: number;
  eta_segundos?: number;
  mensaje?: string;
  url?: string;
  error?: string;
}

@Injectable({
  providedIn: 'root'
})
export class SseService {
  private apiUrl = environment.apiUrl;

  conectar(uuid: string, token: string, onEvent: (event: SseEvent) => void, onError?: (err: any) => void, onComplete?: () => void): EventSource {
    const eventSource = new EventSource(`${this.apiUrl}/transcripciones/${uuid}/estado?token=${encodeURIComponent(token)}`);

    eventSource.onmessage = (msg) => {
      try {
        const data: SseEvent = JSON.parse(msg.data);
        onEvent(data);

        if (data.estado === 'COMPLETADO' || data.estado === 'FALLIDO') {
          eventSource.close();
          onComplete?.();
        }
      } catch (e) {
        console.error('Error parseando SSE:', e);
      }
    };

    eventSource.onerror = (err) => {
      console.error('Error en conexión SSE:', err);
      onError?.(err);
    };

    return eventSource;
  }
}
