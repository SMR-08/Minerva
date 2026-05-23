# Minerva — Sistemas de Logging y Debug

> Decisiones: 2026-05-22
> Estado: PENDIENTE (diseño aprobado, implementacion por hacer)

---

## Principio rector

No parches. Sistemas solidos, mantenibles, profesionales. Bases para construir encima.

---

## Sistema 1: Logging (Produccion)

### Proposito
Observabilidad. Saber que paso, cuando, donde, y poder correlacionar entre servicios.

### Diseño

**Formato**: JSON estructurado (una linea por evento). Todos los servicios emiten el mismo schema base.

**Schema base** (campos obligatorios en todo log):
```json
{
  "timestamp": "2026-05-22T14:30:00.123Z",
  "level": "info|warning|error|critical",
  "service": "laravel|worker|asr|diarizador|gateway|frontend",
  "trace_id": "uuid-correlacion-entre-servicios",
  "message": "Descripcion legible del evento",
  "context": {}
}
```

**Campos adicionales por tipo de evento**:
```json
{
  "request_id": "uuid-del-request-http",
  "user_id": 42,
  "duration_ms": 1523,
  "error": { "class": "Exception", "message": "...", "trace": "..." }
}
```

### Implementacion por servicio

| Servicio | Como |
|----------|------|
| Laravel (app + worker) | Canal custom `json-structured` en `config/logging.php`. Monolog JsonFormatter. |
| IA (ASR) | Python `logging` con `json` formatter. Mismo schema. |
| IA (Diarizador) | Idem ASR. |
| Nginx Gateway | `log_format json_combined` en nginx.conf. |
| Frontend | No aplica en produccion (logs del browser son del usuario). |

### Correlacion entre servicios (trace_id)

El `uuid_referencia` de cada transcripcion sirve como trace_id natural:
- Laravel genera UUID al crear transcripcion
- Lo pasa a la IA en el POST /upload
- La IA lo incluye en callbacks
- Todos los logs de ese audio comparten el mismo trace_id

### Niveles

| Level | Cuando |
|-------|--------|
| `info` | Operacion normal completada (upload recibido, transcripcion completada) |
| `warning` | Algo inesperado pero recuperable (retry de job, timeout parcial) |
| `error` | Fallo que afecta al usuario (job fallido, IA no responde) |
| `critical` | Sistema degradado (DB caida, Redis muerto, GPU OOM) |

### Destino

- **Ahora**: stdout (capturado por Docker json-file driver con rotacion)
- **AWS (futuro)**: stdout → Promtail → Loki → Grafana
- **Transicion**: Zero cambios en codigo. Solo agregar Promtail sidecar.

### Rotacion (Docker)
Ya configurado en docker-compose.production.yml:
```yaml
logging:
  driver: "json-file"
  options:
    max-size: "10m"
    max-file: "3"
```

---

## Sistema 2: Debug (Desarrollo)

### Proposito
Introspeccion. Ver datos internos, timings, payloads, decisiones del codigo. Solo en desarrollo.

### Diseño

**Granularidad por modulo**. Cada subsistema tiene su flag independiente.

### Variables de entorno

```env
# Activar/desactivar debug por modulo (0=off, 1=on)
DEBUG_AUDIO=0      # Upload, storage, streaming a IA
DEBUG_AUTH=0       # Login, tokens, policies, guards
DEBUG_IA=0         # Comunicacion con ASR/Diarizador, callbacks
DEBUG_QUEUE=0      # Jobs, dispatch, retry, failed
DEBUG_SSE=0        # Polling, progress updates, ETA
DEBUG_DB=0         # Queries (con bindings), slow queries
```

### Que emite cada modulo cuando esta activo

| Modulo | Datos |
|--------|-------|
| `DEBUG_AUDIO` | Tamaño archivo, formato detectado, ruta storage, duracion stream |
| `DEBUG_AUTH` | Token generado (parcial), policy evaluada, resultado, user_id |
| `DEBUG_IA` | Payload enviado a IA, response status, headers, duracion HTTP |
| `DEBUG_QUEUE` | Job dispatched (class, queue, delay), attempts, backoff |
| `DEBUG_SSE` | Polling request, estado actual, ETA calculada, posicion cola |
| `DEBUG_DB` | Query SQL con bindings, duracion ms (solo >10ms por defecto) |

### Implementacion en Laravel

Crear `app/Support/Debug.php`:
```php
<?php
namespace App\Support;

use Illuminate\Support\Facades\Log;

class Debug
{
    public static function audio(string $message, array $context = []): void
    {
        if (!config('app.debug_modules.audio')) return;
        Log::channel('debug')->debug("[AUDIO] {$message}", $context);
    }

    public static function auth(string $message, array $context = []): void
    {
        if (!config('app.debug_modules.auth')) return;
        Log::channel('debug')->debug("[AUTH] {$message}", $context);
    }

    public static function ia(string $message, array $context = []): void
    {
        if (!config('app.debug_modules.ia')) return;
        Log::channel('debug')->debug("[IA] {$message}", $context);
    }

    // ... queue, sse, db
}
```

### Implementacion en Python (IA)

```python
import os, json, sys

class Debug:
    @staticmethod
    def ia(message: str, **context):
        if os.getenv("DEBUG_IA", "0") != "1":
            return
        print(json.dumps({
            "level": "debug",
            "module": "ia",
            "message": message,
            **context
        }), file=sys.stderr, flush=True)
```

### Seguridad

- Debug NUNCA expone secrets (passwords, tokens completos, API keys)
- Tokens se muestran truncados: `tok_abc...xyz`
- Payloads grandes se truncan a 500 chars con `[TRUNCATED]`
- En produccion (APP_ENV=production), debug se ignora aunque la variable este en 1

### Destino

- **Desarrollo**: Canal `debug` separado → `storage/logs/debug.log` (no contamina laravel.log)
- **Produccion**: Desactivado por defecto. Si se activa, va a stderr (visible en docker logs pero no persiste)

---

## Configuracion Laravel

### config/logging.php (canales nuevos)

```php
'channels' => [
    // ... canales existentes ...

    'structured' => [
        'driver' => 'monolog',
        'handler' => StreamHandler::class,
        'with' => ['stream' => 'php://stdout'],
        'formatter' => JsonFormatter::class,
        'level' => 'info',
    ],

    'debug' => [
        'driver' => 'single',
        'path' => storage_path('logs/debug.log'),
        'level' => 'debug',
    ],
],
```

### config/app.php (debug modules)

```php
'debug_modules' => [
    'audio' => (bool) env('DEBUG_AUDIO', false),
    'auth'  => (bool) env('DEBUG_AUTH', false),
    'ia'    => (bool) env('DEBUG_IA', false),
    'queue' => (bool) env('DEBUG_QUEUE', false),
    'sse'   => (bool) env('DEBUG_SSE', false),
    'db'    => (bool) env('DEBUG_DB', false),
],
```

---

## Orden de implementacion

```
1. Crear app/Support/Debug.php (clase estatica con metodos por modulo)
2. Crear config entries (debug_modules en config/app.php)
3. Crear canal 'structured' en config/logging.php
4. Crear canal 'debug' en config/logging.php
5. Agregar variables DEBUG_* a .env.example
6. Implementar Debug::audio() en AudioProcessingService + Job
7. Implementar Debug::ia() en AudioProcessingService (HTTP calls)
8. Implementar Debug::queue() en AudioProcessingJob
9. Implementar Debug::sse() en SseController
10. Implementar Debug::auth() en AuthController
11. Configurar log_format JSON en nginx-gateway
12. Configurar JSON logging en IA (Python)
13. Agregar trace_id (uuid_referencia) a todos los logs de un audio
14. Documentar en README seccion "Observabilidad"
```

Despues de cada paso: `make test-backend` debe seguir verde.

---

## Uso esperado

### Desarrollo (debug activo)
```bash
# .env
DEBUG_AUDIO=1
DEBUG_IA=1

# Ver debug en tiempo real
tail -f Backend/storage/logs/debug.log
```

Output:
```
[2026-05-22 14:30:00] debug [AUDIO] Archivo recibido {"size_mb":45.2,"format":"mp3","original":"clase-fisica.mp3"}
[2026-05-22 14:30:00] debug [AUDIO] Almacenado en storage {"path":"uploads/abc123.mp3","disk":"local"}
[2026-05-22 14:30:01] debug [IA] POST a ASR {"url":"http://minerva-asr:8000/upload","uuid":"abc-123","size_mb":45.2}
[2026-05-22 14:30:01] debug [IA] Response ASR {"status":200,"duration_ms":523}
```

### Produccion (solo logging estructurado)
```bash
# docker logs minerva-app | jq '.level == "error"'
{"timestamp":"2026-05-22T14:30:00Z","level":"error","service":"laravel","trace_id":"abc-123","message":"IA no responde","context":{"url":"http://minerva-asr:8000","timeout":7200}}
```

### Buscar todos los logs de un audio especifico
```bash
docker logs minerva-app 2>&1 | jq 'select(.trace_id == "abc-123")'
docker logs minerva-asr 2>&1 | jq 'select(.trace_id == "abc-123")'
```
