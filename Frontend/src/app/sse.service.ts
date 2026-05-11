import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
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

export interface PollingHandler {
  detener: () => void;
}

@Injectable({
  providedIn: 'root'
})
export class SseService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  async conectar(uuid: string, token: string, onEvent: (event: SseEvent) => void, onError?: (err: any) => void, onComplete?: () => void): Promise<PollingHandler> {
    let tempToken = token;
    try {
      const res = await firstValueFrom(
        this.http.post<{token: string}>(`${this.apiUrl}/sse/token`, {}, {
          headers: { Authorization: `Bearer ${token}` }
        })
      );
      tempToken = res.token;
    } catch {
      // fallback al token original
    }

    const intervalId = setInterval(async () => {
      try {
        const response = await firstValueFrom(
          this.http.get<SseEvent>(`${this.apiUrl}/transcripciones/${uuid}/estado?token=${encodeURIComponent(tempToken)}`)
        );
        onEvent(response);

        if (response.estado === 'COMPLETADO' || response.estado === 'FALLIDO') {
          clearInterval(intervalId);
          onComplete?.();
        }
      } catch (err) {
        console.error('Error en polling:', err);
        onError?.(err);
      }
    }, 2000);

    return {
      detener: () => clearInterval(intervalId)
    };
  }
}
