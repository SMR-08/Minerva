# Minerva — Plataforma de Transcripción Inteligente

> **TFG 2º DAW** — Desarrollo de Aplicaciones Web
> Fecha: Mayo 2026

---

## ¿Qué es Minerva?

Minerva es una plataforma web que transforma grabaciones de clase en apuntes organizados automáticamente. El estudiante graba su clase, sube el audio, y Minerva:

1. **Transcribe** el audio usando IA (Qwen3-ASR)
2. **Identifica quién habla** — distingue profesor de alumnos (diarización con Senko)
3. **Organiza** el contenido por Asignatura > Tema > Transcripción
4. **Muestra** la transcripción con segmentos por hablante en tiempo real

El resultado es una transcripción diarizada donde cada intervención está etiquetada con su hablante, accesible desde cualquier navegador.

---

## Arquitectura General

```
┌─────────────┐         ┌──────────────────────────────────────┐
│  Angular 17 │  HTTPS  │           Backend Laravel 11          │
│    (SPA)    │────────▶│  API REST + Sanctum Auth + Redis Q   │
│  :4200 dev  │◀────────│  :8001 dev / :9122 prod (gateway)    │
└─────────────┘         └──────────────┬───────────────────────┘
                                       │
                          Redis BRPOP   │  Cola unificada (minerva_tasks)
                                       │
                                       ▼
              ┌─────────────────────────────────────────────────────┐
              │              Servicio IA (FastAPI) — UMA DGX         │
              │                                                     │
              │  ┌───────────┐  ┌──────────────┐  ┌────────────┐  │
              │  │ ASR Qwen3 │  │ Diarizador   │  │ Resumidor  │  │
              │  │  GPU 0    │  │ Senko GPU 1  │  │ Qwen3.5    │  │
              │  │  :8000    │  │  :8000       │  │ GPU 2      │  │
              │  └───────────┘  └──────────────┘  └────────────┘  │
              └────────────────────────┬────────────────────────────┘
                                       │
                          Callbacks     │  POST /api/ia/callback
                                       ▼
              ┌──────────────────────────────────────────────────────┐
              │  Bravo (proxy nginx :7897) → Charlie (DGX :8000)    │
              └──────────────────────────────────────────────────────┘
```

### Flujo de procesamiento

```
1. Usuario sube audio → Laravel valida y guarda en storage
2. Laravel encola tarea en Redis (RPUSH minerva_tasks)
3. IA (worker_loop) lee tarea con BRPOP
4. IA descarga audio desde Laravel (GET /api/internal/audio-download)
5. IA procesa: ASR → Diarización → Post-procesamiento
6. IA envía progreso en tiempo real (POST /api/ia/sse-update)
7. IA envía resultado (POST /api/ia/callback, estado=COMPLETADO)
8. IA genera resumen automático (Qwen3.5-0.8B)
9. IA envía resumen (POST /api/ia/callback, estado=LISTO)
10. Frontend hace polling cada 2s para mostrar progreso
```

---

## Stack Tecnológico

| Capa | Tecnología | Versión | Justificación |
|------|-----------|---------|---------------|
| Frontend | Angular (standalone) | 17 | Componentes sin módulos, signals |
| Backend | Laravel (API REST) | 11 | Sanctum para SPA auth, queues nativas |
| Base de datos | MariaDB | latest | Compatible MySQL, ligera |
| Cola | Redis (BRPOP) | 7-alpine | Cola unificada IA ↔ Laravel |
| IA - ASR | Qwen3-ASR | 0.6B | Transcripción multilengüe con timestamps |
| IA - Diarización | Senko (pyannote VAD) | — | Identificación de hablantes |
| IA - Resumen | Qwen3.5-0.8B | — | Resumen estructurado de clases |
| GPU (producción) | NVIDIA DGX | 4x V100 32GB | Inferencia paralela (UMA) |
| Contenedores | Docker / docker-compose | — | Orquestación de servicios |
| Gateway (prod) | Nginx | alpine | Reverse proxy + rate limiting + HTTPS |
| CI/CD | GitHub Actions | — | Tests automáticos + deploy |
| IaC | Terraform + AWS | — | ALB + ASG + ACM (HTTPS) |
| HTTPS | AWS ACM + Cloudflare DNS | — | Certificado gratuito auto-renovable |

---

## Requisitos del sistema

### Desarrollo
- Docker + Docker Compose
- NVIDIA GPU con drivers + nvidia-container-toolkit
- `nvidia-persistenced` activo (el Makefile lo detecta)
- 8GB+ VRAM (modo compact) o 16GB+ (modo full)
- 16GB RAM mínimo

### Producción
- **AWS** (app): EC2 via ALB con HTTPS (ACM) — `https://minerva.mayger.uk`
- **UMA** (IA): DGX Station 4x V100 32GB (Charlie) + proxy nginx (Bravo)
- Docker en ambos (Charlie sin docker-compose, usa scripts)
- Guía completa: [`docs/DEPLOY.md`](docs/DEPLOY.md)

---

## Inicio Rápido (Desarrollo)

```bash
# 1. Clonar
git clone <repo> && cd Minerva

# 2. Inicializar (crea .env, construye, levanta, migra, seedea)
make init

# 3. Verificar que todo funciona
make health

# 4. Acceder
# Frontend:  http://localhost:4200
# Backend:   http://localhost:8001
# IA (ASR):  http://localhost:8002
# Admin:     http://localhost:8001/admin (admin@minerva.com / admin123)
```

### Comandos esenciales

```bash
make help          # Ver todos los comandos disponibles
make up            # Levantar servicios
make down          # Detener servicios
make health        # Verificar conectividad entre servicios
make test-backend  # Ejecutar 55 tests (Pest)
make logs          # Ver logs en tiempo real
make status        # Estado de contenedores
```

---

## Despliegue en Producción

Minerva soporta dos modos de despliegue:

### Monolítico (todo en un servidor)

```bash
cp .env.production.example .env
nano .env   # Configurar IPs, passwords, APP_KEY
DEV=0 make init
```

### Distribuido (servicios separados)

Cada servicio puede estar en un servidor diferente. La comunicación se configura en `.env`:

```bash
# Servidor WEB (Frontend + Backend)
DEV=0 make back-up
DEV=0 make front-up

# Servidor IA (GPU)
DEV=0 make ia-up
```

Variables críticas para modo distribuido:

| Variable | Qué configura | Ejemplo |
|----------|--------------|----------|
| `IA_UPLOAD_URL` | Cómo Backend llega a IA | `http://IP_SERVIDOR_IA:8002` |
| `LARAVEL_URL` | Cómo IA llega a Backend (callbacks) | `http://IP_SERVIDOR_WEB:9122` |
| `CORS_ALLOWED_ORIGINS` | Orígenes permitidos para el frontend | `http://IP:9122` |
| `SANCTUM_STATEFUL_DOMAINS` | Dominios SPA para auth | `IP:9122,IP` |

### Verificación post-despliegue

```bash
DEV=0 make check-env   # Valida configuración
DEV=0 make health      # Verifica conectividad
```

### AWS con Terraform

Infraestructura como código en `terraform/`. Incluye ALB, ASG, Security Groups, ACM (HTTPS) y escalado automático.

**Dependencias**: `aws-cli` v2, `terraform` >= 1.5

**Desplegar desde cero**:

```bash
cd terraform/
terraform init
terraform apply -var="domain_name=minerva.mayger.uk"
# → Crear CNAMEs en Cloudflare (ver output acm_validation_records)
# → gh workflow run "Desplegar Minerva" -f instance_ip=<IP>
```

Ver [`docs/DEPLOY.md`](docs/DEPLOY.md) para guía paso a paso.

---

## Estructura del Proyecto

```
Minerva/
├── Backend/           Laravel 11 API REST (PHP 8.3)
│   ├── app/
│   │   ├── Models/        8 modelos (Usuario, Asignatura, Tema, Transcripcion...)
│   │   ├── Http/          Controllers, Requests, Resources, Middleware
│   │   ├── Services/      AudioProcessing, Asignatura, Tema, Transcripcion
│   │   ├── Jobs/          AudioProcessingJob (Redis queue)
│   │   └── Policies/      Asignatura, Tema (user-scoped)
│   ├── database/          Migraciones, seeders, factories
│   ├── tests/             71 tests (Pest) + 8 arch tests
│   └── config/audio.php   Configuración centralizada de IA/audio
├── Frontend/          Angular 17 SPA (standalone components)
│   └── src/app/
│       ├── dashboard/         Vista principal
│       ├── asignatura-view/   Detalle de asignatura + temas
│       ├── transcripcion-view/ Vista diarizada + resumen IA
│       ├── formulario-subida/  Upload de audio + tracking
│       ├── pipes/             MarkdownPipe (resumen → HTML)
│       └── services/          Auth, Minerva, SSE, Notification
├── IA/                FastAPI (Python) — ASR + Diarización + Resumen
│   ├── main.py            Orquestador: worker_loop, /estado, procesar_resumen
│   ├── procesamiento.py   Post-procesamiento (6 etapas)
│   ├── ASR/asr.py         Wrapper Qwen3-ASR
│   ├── DIARIZADOR/        Senko + ffmpeg
│   └── resumidor/         Microservicio Qwen3.5-0.8B (modo full)
├── deploy/
│   ├── bravo/             Proxy nginx (docker-compose v1)
│   └── charlie/           Scripts ia-up.sh / ia-down.sh (DGX sin compose)
├── terraform/         IaC: ALB + ASG + ACM + scaling
├── nginx-gateway/     Reverse proxy producción (puerto 9122)
├── e2e/               Tests Playwright (Page Object Model)
├── docs/              DEPLOY.md (guía paso a paso)
├── openspec/          Documentación técnica del proyecto
├── docker-compose.yml          Desarrollo
├── docker-compose.production.yml  Producción (profiles: front, back, ia)
└── Makefile           Orquestador (DEV=1/0)
```

---

## Base de Datos

### Modelo Entidad-Relación

```
Usuario (1) ─────┬───── (*) Asignatura (1) ─── (*) Tema (1) ─── (*) Transcripcion
            │                                                    │
            ├───── (*) Tag ──────────────────────────────────────────┘ (M:N)
            ├───── (1) Rol
            ├───── (1) EstadoUsuario
            └───── (*) HistorialAcceso
```

### Tablas principales

| Tabla | Propósito | Campos clave |
|-------|----------|---------------|
| `usuarios` | Usuarios del sistema | email, password_hash, id_rol, id_estado |
| `asignaturas` | Materias del estudiante | nombre, profesor, color_hex, id_usuario |
| `temas` | Temas dentro de asignatura | nombre, orden, id_asignatura |
| `transcripciones` | Audio procesado | uuid, estado, texto_plano, texto_diarizado (JSON), progreso |
| `tags` | Etiquetas personales | nombre, color_hex, id_usuario |
| `roles` | ADMIN, USUARIO | nombre |
| `estados_usuario` | ACTIVO, SUSPENDIDO, BANEADO | nombre |
| `historial_accesos` | Auditoría de login | ip_acceso, user_agent, fecha |

### Estados de procesamiento

```
SUBIENDO → ENCOLADO → PROCESANDO → COMPLETADO → RESUMIENDO → LISTO
                                  → FALLIDO
```

Durante PROCESANDO, se actualizan `progreso_porcentaje` (0-100) y `etapa_actual` (INICIANDO, ASR, DIARIZACION, POSTPROCESAMIENTO). Tras COMPLETADO, si AUTO_SUMMARY=true, se genera resumen automático (RESUMIENDO → LISTO).

---

## API REST

### Autenticación (Sanctum)

| Endpoint | Método | Auth | Descripción |
|----------|--------|------|-------------|
| `/api/register` | POST | — | Registro (nombre, email, password) |
| `/api/login` | POST | — | Login → devuelve token Sanctum |
| `/api/logout` | POST | Bearer | Cierra sesión (elimina token) |
| `/api/user` | GET | Bearer | Datos del usuario autenticado |

### Recursos (CRUD)

| Recurso | Endpoints | Auth | Notas |
|---------|-----------|------|-------|
| Asignaturas | GET/POST/PUT/DELETE `/api/asignaturas` | Bearer | User-scoped via Policy |
| Temas | GET/POST/PUT/DELETE `/api/temas` | Bearer | Filtro: `?asignatura_id=X` |
| Tags | GET/POST/DELETE `/api/tags` | Bearer | User-scoped |
| Transcripciones | GET/PUT/DELETE `/api/transcripciones/{id}` | Bearer | Incluye texto diarizado |

### Procesamiento de Audio

| Endpoint | Método | Auth | Descripción |
|----------|--------|------|-------------|
| `/api/temas/{id}/procesar-audio` | POST | Bearer | Upload multipart (audio + titulo + idioma) |
| `/api/transcripciones/{uuid}/estado` | GET | Token query | Polling de progreso (cada 2s) |
| `/api/ia/estado` | GET | Bearer | Health check del servicio IA |

### Callbacks internos (IA → Laravel)

| Endpoint | Método | Auth | Descripción |
|----------|--------|------|-------------|
| `/api/ia/callback` | POST | X-Callback-Secret | Resultado final de procesamiento |
| `/api/ia/sse-update` | POST | X-Callback-Secret | Actualización de progreso |

---

## Servicio de IA

### Pipeline de procesamiento (6 etapas)

| # | Etapa | Tecnología | Qué hace |
|---|-------|-----------|----------|
| 1 | ASR | Qwen3-ASR | Transcribe audio → lista de palabras con timestamps |
| 2 | Diarización | Senko (pyannote VAD) | Identifica segmentos por hablante |
| 3 | Alineación | Custom (60% overlap) | Asigna cada palabra a un hablante |
| 4 | Suavizado | Custom | Fusiona fragmentos cortos (<1s) |
| 5 | Corrección | Custom | Resuelve segmentos "DESCONOCIDO" |
| 6 | Roles | Custom | Profesor = hablante con más duración |

### Modelos disponibles

| Modelo | VRAM | GPU | Uso |
|--------|------|-----|-----|
| Qwen3-ASR-0.6B | ~2GB | GPU 0 | Transcripción multilengüe |
| Qwen3-ForcedAligner-0.6B | ~2GB | GPU 0 | Alineación palabra-timestamp |
| Senko (pyannote) | ~3GB | GPU 1 | Diarización de hablantes |
| Qwen3.5-0.8B | ~2GB | GPU 2 | Resumen estructurado de clases |

### Concurrencia

La IA procesa **un audio a la vez** (asyncio.Lock). Los demás se encolan. Esto es intencional: la GPU no puede paralelizar inferencia de modelos grandes sin fragmentar VRAM.

---

## Testing

### Backend (71 tests, Pest)

```bash
make test-backend
```

| Suite | Tests | Cobertura |
|-------|-------|-----------|
| Auth | 15 | Registro, login, logout, validaciones, cuenta inactiva |
| Asignaturas | 13 | CRUD completo, aislamiento por usuario, validaciones |
| Temas | 14 | CRUD con binding a asignatura, ordenamiento |
| Audio | 6 | Upload, validación de formato, creación de transcripción |
| Callback IA | 7 | COMPLETADO, RESUMIENDO, LISTO, FALLIDO, seguridad secret |
| Transcripciones | 5 | Listado, detalle, aislamiento por usuario |
| Arquitectura | 8 | No dd/dump, namespaces correctos, no env() en controllers |

### E2E (Playwright)

```bash
make test-e2e
```

6 specs con Page Object Model: auth, login, registro, dashboard, asignaturas, navegación completa.

### CI/CD

GitHub Actions ejecuta tests automáticamente en cada PR a main.

---

## Decisiones de Diseño

### ¿Por qué polling en vez de SSE real?

PHP-FPM no soporta conexiones long-lived eficientemente. Con 20 usuarios concurrentes, SSE bloquearía 20 workers PHP. Polling cada 2s libera el worker en <100ms.

### ¿Por qué Redis queue en vez de procesamiento síncrono?

El audio puede tardar 30min-2h en procesarse. Un HTTP timeout mataría la conexión. La cola permite reintentos automáticos (3 intentos con backoff exponencial).

### ¿Por qué "Patata Caliente" (streaming directo)?

El audio se envía directamente de Laravel a IA via HTTP multipart. No se usa filesystem compartido. Más simple, menos puntos de fallo.

### ¿Por qué Qwen3-ASR?

Modelo open-source multilengüe con timestamps a nivel de palabra. Soporta español, inglés, francés, alemán, portugués, italiano, catalán, euskera, gallego. Funciona en GPU consumer (RTX 3070 Ti).

### ¿Por qué Senko para diarización?

Combina pyannote VAD con clustering de embeddings. Funciona sin necesidad de saber cuántos hablantes hay. Ligero comparado con alternativas.

---

## Seguridad

| Aspecto | Implementación |
|---------|----------------|
| Auth frontend | Sanctum tokens (Bearer) |
| Auth admin | Sesión web + middleware `EsAdmin` (id_rol=1) |
| Auth callbacks IA | Header `X-Callback-Secret` validado con `hash_equals()` |
| Aislamiento datos | Policies + scoped queries (usuario solo ve lo suyo) |
| CORS | Configurado por env var, restrictivo en producción |
| Rate limiting | Throttle en rutas sensibles (login: 10/min, registro: 5/min) |
| Soft deletes | Datos nunca se borran físicamente |
| Auditoría | HistorialAcceso registra cada login (IP, user-agent) |

---

## Estado Actual y Roadmap

### Implementado

- Autenticación completa (registro, login, logout, admin)
- CRUD Asignaturas, Temas, Tags con policies
- Pipeline completo de audio: upload → cola unificada → ASR → diarización → callback
- Resumen automático con IA (Qwen3.5-0.8B, modo dual: microservicio o local)
- Frontend funcional con todas las vistas + estados RESUMIENDO/LISTO
- Markdown pipe para renderizar resúmenes
- Docker DEV + PROD con profiles
- Deploy distribuido: AWS (app) + UMA DGX (IA) via proxy
- HTTPS con AWS ACM + dominio personalizado (minerva.mayger.uk)
- Cola unificada Redis (BRPOP) — IA consume directamente sin worker PHP
- Makefile como orquestador completo
- 71 tests backend + 8 tests arquitectura + E2E Playwright
- CI/CD con GitHub Actions (tests + deploy automático)
- Panel de administración web
- Terraform IaC (ALB + ASG + ACM + scaling)
- Documentación de despliegue (docs/DEPLOY.md)

### Pendiente

| Feature | Prioridad | Notas |
|---------|-----------|-------|
| Mapa mental Mermaid | Baja | Columna existe en BD, stretch goal |
| Refactor MVC Laravel | Media | 2 fat controllers, enum EstadoTranscripcion, query scopes. Ver `openspec/laravel-refactor.md` |
| Multi-idioma frontend | Baja | i18n Angular |

---

## Configuración de Referencia

### Variables de entorno principales

| Variable | Propósito | Default (dev) |
|----------|----------|---------------|
| `DEV` | Modo Makefile | `1` |
| `GPU_MODE` | compact (8GB) / full (16GB+) | `compact` |
| `IA_UPLOAD_URL` | Backend → IA | `http://minerva-asr:8000` |
| `LARAVEL_URL` | IA → Backend (callbacks) | `http://minerva-nginx:80` |
| `IA_CALLBACK_SECRET` | Auth de callbacks | (generar con openssl) |
| `MODELO_ASR` | Modelo de transcripción | `Qwen/Qwen3-ASR-0.6B` |
| `AUDIO_MAX_SIZE_MB` | Tamaño máximo de upload | `2048` (2GB) |
| `AI_TIMEOUT` | Timeout procesamiento | `7200` (2h) |

### Puertos

| Servicio | Desarrollo | Producción |
|----------|-----------|------------|
| Frontend | :4200 | :9122 (via gateway) |
| Backend API | :8001 | :9122/api (via gateway) |
| IA (ASR) | :8002 | interno (:8000) |
| MariaDB | :3307 | interno (:3306) |
| Redis | :6379 | interno (:6379) |
| Gateway | — | :9122 |

---

## Troubleshooting

### GPU no detectada
```bash
# Verificar nvidia-persistenced
systemctl status nvidia-persistenced
# Si está inactivo:
sudo systemctl start nvidia-persistenced
sudo systemctl enable nvidia-persistenced
```

### Servicios no conectan
```bash
make health   # Muestra qué falla
make check-env  # Valida configuración
```

### Audio se sube pero no se procesa
```bash
make worker-logs   # Ver errores del worker
make cola-estado   # Ver estado de la cola Redis
```

### CORS bloqueado
Verificar que `CORS_ALLOWED_ORIGINS` y `SANCTUM_STATEFUL_DOMAINS` en `.env` coinciden con la URL desde donde accede el frontend.

### Permisos de storage
```bash
make permisos   # Corrige ownership y chmod
```

---

## Observabilidad

### Logging estructurado (Producción)

Todos los servicios emiten logs JSON con un schema base común:

```json
{"timestamp":"2026-05-25T14:30:00Z","level":"info","service":"laravel","trace_id":"abc-123","message":"Transcripcion completada","context":{}}
```

| Servicio | Implementación |
|----------|---------------|
| Laravel (app + worker) | Canal `structured` → stdout (Monolog JsonFormatter) |
| IA (ASR + Diarizador) | `logger.py` → stdout (JSON) |
| Nginx Gateway | `log_format json_structured` → access.log |

Correlación entre servicios: el `uuid_referencia` de cada transcripción sirve como `trace_id` en todos los logs.

```bash
# Buscar todos los logs de un audio específico
docker logs minerva-app 2>&1 | jq 'select(.trace_id == "abc-123")'
docker logs minerva-asr 2>&1 | jq 'select(.trace_id == "abc-123")'
```

### Debug granular (Desarrollo)

Cada subsistema tiene su flag independiente en `.env`:

```env
DEBUG_AUDIO=1   # Upload, storage, streaming a IA
DEBUG_AUTH=0    # Login, tokens, policies
DEBUG_IA=1     # Comunicación con ASR/Diarizador
DEBUG_QUEUE=0  # Jobs, dispatch, retry
DEBUG_SSE=0    # Polling, progress updates
DEBUG_DB=0     # Queries lentas
```

```bash
# Ver debug en tiempo real
tail -f Backend/storage/logs/debug.log
```

Seguridad: tokens se muestran truncados, payloads >500 chars se truncan, en producción el debug se ignora aunque la variable esté en 1.

---

## Licencia

Proyecto académico — TFG 2º DAW.
