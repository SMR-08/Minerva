## Estado Actual (21 mayo 2026)

### Funcional ✅
- Auth completo (registro, login, logout, Sanctum tokens)
- CRUD Asignaturas, Temas, Tags con Policies
- Pipeline audio: upload → Redis job → ASR → diarización → callback
- SSE polling para progreso en tiempo real
- 55/55 tests Pest pasando
- Docker Compose dev funcional
- Conectividad Backend ↔ IA verificada (ambas direcciones)

### Pendiente
- Resumidor de clase (feature core)
- Terraform AWS (requisito TFG)
- Tests E2E estables (Playwright inestable)
- Documentación actualizada
- **Cola unificada con concurrencia ajustable** (prioridad BAJA, post-TFG): N workers = N audios simultáneos. Requiere reescribir IA (eliminar asyncio.Lock) y posiblemente worker. Reescritura aceptable.
- **Sistema de Logging** (JSON estructurado + Loki-ready): ver `openspec/logging-debug-systems.md`
- **Sistema de Debug** (granular por módulo): ver `openspec/logging-debug-systems.md`
- **Refactor MVC Laravel**: ver `openspec/laravel-refactor.md`
- **Refactor Laravel MVC** (prioridad MEDIA, esta semana): ver `openspec/refactors/` para plan detallado con 5 artifacts
- **Refactor IA Service** (prioridad MEDIA): monolito distribuido, no microservicios reales. Bugs críticos: modelo load/unload por request, ruta diarizador rota, worker.py divergente. Ver `openspec/refactors/ia-service-audit.md`
- **Refactor Frontend Angular** (prioridad MEDIA): LayoutComponent sin usar, Tags sin UI, progress tracking muerto, sin paginación. Ver `openspec/refactors/frontend-P*.md`

### Problemas resueltos hoy
- `.env` híbrido PROD/DEV causaba CORS bloqueado y mismatches
- DB sin seedear → FK errors en registro
- `SANCTUM_STATEFUL_DOMAINS` faltante
- Makefile no generaba todas las vars necesarias en Backend/.env
- **`AudioProcessingJob`**: `->attach()->asMultipart()->post()` incorrecto → fix a `->attach()->post()`
- **Permisos storage**: `uploads/` era `root:root drwx------` → www-data:www-data 775
- **GPU_MODE=compact** pero MODELO_ASR=1.7B (5.5GB VRAM) → corregido a 0.6B (2GB VRAM)
- **Pipeline E2E verificado**: upload → store → queue → worker → IA → callback → estado OK

## Comunicación entre servicios

### Variables de entorno críticas

| Variable | Uso | Dev | Prod |
|----------|-----|-----|------|
| `IA_UPLOAD_URL` | Laravel→IA (upload audio) | `http://minerva-asr:8000` | `http://minerva-asr:8000` |
| `LARAVEL_URL` | IA→Laravel (callbacks) | `http://minerva-nginx:80` | `http://minerva-prod-nginx:80` |
| `IA_CALLBACK_SECRET` | Auth de callbacks IA→Laravel | default (dev) | openssl rand -base64 32 |
| `CORS_ALLOWED_ORIGINS` | CORS Laravel (solo prod) | `*` (via APP_ENV=local) | IP:puerto real |
| `SANCTUM_STATEFUL_DOMAINS` | Sanctum SPA auth | `localhost:4200,localhost` | `IP:9122,IP` |
| `APP_ENV` | Modo Laravel | `local` | `production` |

### Autenticación de callbacks IA→Laravel

Los callbacks de IA no usan Sanctum. Usan un header `X-Callback-Secret` validado
contra `config('audio.ia.callback_secret')`. Esto es correcto porque:
- La IA es un servicio interno, no un usuario
- El secret se comparte via env vars en la misma red Docker
- No necesita tokens de usuario

### Endpoints internos (no expuestos al frontend)

| Endpoint | Dirección | Auth | Propósito |
|----------|-----------|------|-----------|
| `POST /api/ia/callback` | IA→Laravel | X-Callback-Secret | Resultado final |
| `POST /api/ia/sse-update` | IA→Laravel | X-Callback-Secret | Progreso parcial |
| `POST /upload` | Laravel→IA | X-Callback-Secret | Enviar audio |
| `GET /estado` | Laravel→IA | ninguna | Health check |
| `GET /estado_cola` | Laravel→IA | ninguna | Estado cola IA |

## Plan de estabilización (Día 1)

1. ✅ Regenerar .env correcto para desarrollo
2. ✅ Verificar conectividad bidireccional Backend ↔ IA
3. ⏳ Test E2E real: upload audio → transcripción completa
4. ⏳ Documentar flujo en OpenSpec
5. ⏳ Asegurar que `make init` funciona from scratch

## Decisiones de diseño

### ¿Por qué polling en vez de SSE real?
- PHP-FPM no soporta conexiones long-lived eficientemente
- Con 20 usuarios concurrentes, SSE bloquearía 20 workers PHP
- Polling cada 2s libera el worker en <100ms
- Trade-off aceptable para un TFG

### ¿Por qué Redis queue en vez de procesamiento síncrono?
- El audio puede tardar 30min-2h en procesarse
- HTTP timeout mataría la conexión
- La cola permite reintentos automáticos (3 intentos con backoff)
- El worker es independiente del request HTTP

### ¿Por qué "Patata Caliente" (streaming directo)?
- El audio se envía directamente de Laravel a IA via HTTP multipart
- No se usa filesystem compartido (eliminado)
- Más simple, menos puntos de fallo
- El archivo temporal solo existe en el contenedor IA durante procesamiento
