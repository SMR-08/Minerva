# Audit Servicio IA — Refactors y Plan

## Veredicto: Monolito distribuido, NO microservicios

| Componente | Realidad | Comunicación |
|-----------|----------|--------------|
| ASR (Qwen3) | Librería in-process (`from ASR.asr import transcribir_audio`) | Llamada directa |
| Diarizador (Senko) | Microservicio real via HTTP | `POST /diarizar` |
| Post-procesamiento | Módulo compartido (`procesamiento.py`) | In-process |

El ASR NO es un servicio separado. El modelo se carga, usa y descarga en cada request dentro del mismo contenedor que el orquestador.

---

## Bugs críticos encontrados

### 1. Modelo se carga/descarga en CADA request (asr.py:80-110)
El modelo Qwen3-ASR (2-5GB VRAM) se carga desde disco, se mueve a GPU, procesa, y se descarga. Cada vez. Penalización de 2-5 segundos por request + fragmentación de GPU.

**Fix**: Singleton — cargar una vez en `lifespan()`, mantener en memoria GPU.

### 2. Ruta de archivo rota entre ASR y Diarizador (main.py:163-166)
- ASR copia a `/app/compartido/entrada/{uuid}.wav`
- Diarizador busca en `/app/audios/entrada/{uuid}.wav`
- Son filesystems DIFERENTES sin volumen compartido en la config por defecto

**Fix**: Enviar ruta absoluta `/tmp/{uuid}.wav` (como hace worker.py correctamente).

### 3. worker.py es un fork divergente de main.py (~60% duplicado)
Diferencias silenciosas:
- Auth: `Authorization: Bearer` vs `X-Callback-Secret`
- URLs: `diarizador` vs `minerva-diarizador`
- Retry: main.py tiene 5 reintentos + fallback disco, worker.py tiene 0
- Bloqueo: worker.py llama ASR síncronamente (bloquea event loop)

**Fix**: Extraer módulo compartido, eliminar worker.py o unificarlo.

### 4. Cola en memoria sin persistencia (asyncio.Queue)
Si el servicio se reinicia: todos los jobs encolados se pierden, el job en proceso se abandona.

---

## Anti-patrones

| Anti-patrón | Dónde | Impacto |
|-------------|-------|---------|
| `print()` en vez de `logging` | Todo main.py, worker.py | Sin niveles, sin estructura, sin correlación |
| Cola in-memory sin persistencia | main.py:64 | Pérdida de datos en restart |
| Modelo load/unload por request | ASR/asr.py:80-110 | +2-5s latencia, fragmentación GPU |
| Código duplicado main.py/worker.py | 60% overlap | Divergencia silenciosa |
| Estado global mutable | Lock, Queue, procesando_actual | No escalable |
| Sin validación de input | main.py:256 | Sin check de tipo/tamaño de archivo |
| Secret inseguro por defecto | main.py:22-24 | Riesgo en producción |

---

## Lo que falta para producción

### Crítico
- Tests (cero tests de cualquier tipo)
- Logging estructurado (JSON, niveles, correlation IDs)
- Validación de input (tipo archivo, tamaño, path traversal)
- Modelo singleton (no load/unload por request)
- Fix ruta diarizador
- Unificar main.py/worker.py

### Alto
- Métricas (Prometheus: request count, duration, queue depth, GPU memory)
- Health check real (`/health` con verificación de GPU + diarizador + modelo)
- Graceful shutdown (drain queue, completar job en vuelo)
- Circuit breaker para diarizador (no esperar 600s si está caído)
- Pinear dependencias (qwen-asr, senko)

### Medio
- API versioning (`/api/v1/upload`)
- Pydantic models para errores
- Rate limiting
- OpenTelemetry tracing
- Type checking (mypy)

---

## Plan de ejecución por fases

### Fase 1: Fix lo roto (1-2 días)
1. Fix ruta diarizador en main.py (enviar absoluta /tmp/)
2. Extraer módulo compartido: `callbacks.py` (notificar, actualizar_progreso)
3. Unificar auth a X-Callback-Secret
4. Eliminar worker.py (o convertirlo en CLI que usa el módulo compartido)

### Fase 2: Producción (3-5 días)
5. Singleton model loading en lifespan()
6. Reemplazar print() por logging estructurado
7. Pydantic request/response models
8. Validación de upload (content-type, max size, magic bytes)
9. Health check real (/health)
10. Graceful shutdown

### Fase 3: Observabilidad (3-5 días)
11. Prometheus metrics
12. Correlation IDs (uuid del job como trace)
13. Circuit breaker para diarizador
14. Retry con jitter en callbacks

### Fase 4: Escalabilidad (5-10 días, post-TFG)
15. Redis-backed job queue
16. Separar ASR como servicio HTTP propio
17. API versioning
18. Multi-GPU support (eliminar asyncio.Lock global)

---

## Módulo procesamiento.py — El más limpio

Funciones puras sin side effects. Bien diseñado:
- `alinear_transcripcion()` — asignación palabra→hablante por overlap 60%
- `suavizar_transcripcion()` — merge fragmentos <1s
- `asignar_hablantes_desconocidos()` — resolución por contexto
- `asignar_roles()` — Profesor = max duración

Issues menores: magic numbers (0.6, 1.0) deberían ser configurables, "DESCONOCIDO" hardcodeado.

---

## Esfuerzo total estimado
- Fase 1+2: ~5-7 días developer Python
- Fase 3: ~3-5 días adicionales
- Fase 4: Post-TFG (requiere decisión arquitectónica sobre cola unificada)
