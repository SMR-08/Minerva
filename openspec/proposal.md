# Minerva — OpenSpec Proposal

## Visión

Minerva es una plataforma de transcripción y organización de apuntes para estudiantes.
El estudiante graba su clase, la sube a Minerva, y el sistema:

1. **Transcribe** el audio (ASR con Qwen3)
2. **Diariza** identificando profesor vs alumnado (Senko/pyannote)
3. **Organiza** por Asignatura > Tema > Transcripción
4. **Resume** cada clase automáticamente (pendiente)
5. **Construye comprensión progresiva** del temario (stretch goal)

## Contexto: TFG 2º DAW

- **Deadline**: 28 de mayo 2026
- **Requisitos obligatorios**: Laravel MVC + APIs REST, Angular, Terraform AWS
- **Fase actual**: Prototipo funcional — core duro operativo

## Arquitectura

```
┌─────────────┐     HTTP/JSON      ┌──────────────┐
│  Angular 17 │ ──────────────────▶ │  Laravel 11  │
│  (SPA)      │ ◀────────────────── │  (API REST)  │
│  :4200 dev  │     Sanctum Token   │  :8001 dev   │
└─────────────┘                     └──────┬───────┘
                                           │
                              Redis Queue   │  AudioProcessingJob
                              (process_audio)│
                                           ▼
                                    ┌──────────────┐
                                    │  FastAPI IA  │
                                    │  (ASR+Diar)  │
                                    │  :8002 dev   │
                                    └──────┬───────┘
                                           │
                              Callback POST │  /api/ia/callback
                              SSE Update    │  /api/ia/sse-update
                                           ▼
                                    ┌──────────────┐
                                    │  Laravel     │
                                    │  (Nginx:80)  │
                                    └──────────────┘
```

### Flujo de procesamiento de audio ("Patata Caliente")

```
1. Frontend POST /api/temas/{id}/procesar-audio (multipart: audio file)
2. Laravel: valida, guarda en storage, crea Transcripcion (ENCOLADO)
3. Laravel: dispatch AudioProcessingJob → Redis queue
4. Worker: lee job, stream audio a IA POST /upload (multipart)
5. IA: encola internamente, procesa secuencialmente
6. IA: actualiza progreso → POST /api/ia/sse-update (X-Callback-Secret)
7. IA: al completar → POST /api/ia/callback (X-Callback-Secret)
8. Laravel: actualiza Transcripcion (COMPLETADO + datos)
9. Frontend: polling GET /api/transcripciones/{uuid}/estado cada 2s
```

## Stack Tecnológico

| Capa | Tecnología | Versión |
|------|-----------|---------|
| Frontend | Angular (standalone components) | 17 |
| Backend | Laravel (MVC + Services) | 11 |
| Auth | Sanctum (token SPA) | — |
| DB | MariaDB | latest |
| Queue | Redis | 7-alpine |
| IA ASR | Qwen3-ASR | 0.6B/1.7B |
| IA Diarización | Senko (pyannote VAD) | — |
| GPU | NVIDIA CUDA | RTX 3070 Ti |
| Contenedores | Docker Compose | — |
| Orquestación | Makefile (DEV=1/0) | — |
| Despliegue | Terraform + AWS (pendiente) | — |

