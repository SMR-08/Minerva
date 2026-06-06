# Minerva — Plataforma de Transcripción Inteligente

**Autores:** Mateo y Rubén
**TFG 2º DAW** — Desarrollo de Aplicaciones Web
**Fecha:** Mayo 2026

---

## Descripción

Minerva es una plataforma web que transforma grabaciones de clase en apuntes organizados automáticamente. El estudiante graba su clase, sube el audio, y Minerva:

1. **Transcribe** el audio usando IA (Qwen3-ASR)
2. **Identifica quién habla** — distingue profesor de alumnos (diarización con Senko)
3. **Organiza** el contenido por Asignatura > Tema > Transcripción
4. **Muestra** la transcripción con segmentos por hablante en tiempo real

El resultado es una transcripción diarizada donde cada intervención está etiquetada con su hablante, accesible desde cualquier navegador.

| Recurso | URL |
|----------|-----|
| Aplicación desplegada | [minerva.mayger.uk](https://minerva.mayger.uk) |
| Diseño en Figma | [Minerva Web — Figma](https://www.figma.com/design/CMwmHxUjoHDtTMKvl1DHUM/Minerva-Web?node-id=0-1&p=f&t=PrI0trVs9ldK5gI6-0) |

---

## Objetivos del proyecto

El objetivo principal de Minerva es ofrecer una herramienta que permita **transcribir y diarizar grabaciones de clase de hasta 1 hora en menos de 10 minutos**, de forma cómoda y sin fricción para el estudiante. La plataforma abstrae toda la complejidad del procesamiento de audio con IA para que el usuario solo tenga que preocuparse de grabar, subir y leer.

---

## Tutorial de uso

Flujo completo desde que un nuevo usuario llega a la plataforma hasta que obtiene su primera transcripción diarizada:

1. **Landing page** — El usuario accede a [minerva.mayger.uk](https://minerva.mayger.uk) y ve la página de bienvenida con la propuesta de valor y el botón «Comenzar ahora».
2. **Registro** — Crea su cuenta con nombre, email y contraseña. Si ya tiene cuenta, hace login directamente.
3. **Dashboard** — Una vez autenticado, llega a su panel principal. Aquí ve sus asignaturas (o un estado vacío si acaba de empezar).
4. **Crear asignatura** — Crea una asignatura (ej. «Matemáticas») con nombre, profesor y color identificativo.
5. **Crear tema** — Dentro de la asignatura, crea un tema (ej. «Derivadas»).
6. **Subir audio** — Dentro del tema, pulsa «Subir audio». Selecciona el archivo (WAV, MP3, M4A, OGG, FLAC), elige idioma (o «auto») y opcionalmente escribe un título. Pulsa «Procesar».
7. **Seguimiento en tiempo real** — Aparece una tarjeta de transcripción con barra de progreso. El sistema muestra la etapa actual: *Subiendo → Encolado → Procesando (ASR / Diarización / Postprocesado) → Completado → Resumiendo → Listo*.
8. **Resultado** — Cuando el estado es «Listo», el usuario abre la transcripción y ve el texto diarizado: cada intervención etiquetada con «Profesor» o «Alumno 1», «Alumno 2», etc. También dispone del resumen automático generado por IA.
9. **Organización continua** — El usuario puede crear más asignaturas, temas y subir más audios. Las transcripciones quedan organizadas jerárquicamente y puede filtrarlas, editarlas o etiquetarlas.

---

## Arquitectura General

```
┌─────────────┐         ┌──────────────────────────────────────┐
│  Angular 17 │  HTTPS  │           Backend Laravel 12          │
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

### Infraestructura y servicios

| Capa | Tecnología | Versión |
|------|-----------|---------|
| Frontend | Angular (standalone components) | 17.3 |
| Backend API | Laravel (REST) | 12 |
| Backend runtime | PHP | 8.2+ |
| Base de datos | MariaDB | latest |
| Cola / caché | Redis | 7-alpine |
| Autenticación | Laravel Sanctum (SPA tokens) | 4.0 |
| Cola jobs | Laravel Queues (Redis driver) | — |
| Cliente Redis | Predis | 3.4 |
| Assets backend | Vite + Tailwind CSS | 7 + 4 |
| Estilos frontend | CSS3 (component-scoped) | — |
| Lenguaje frontend | TypeScript | 5.4 |
| Reactividad | RxJS | 7.8 |

### Modelos de IA

| Modelo | Rol | GPU | VRAM aprox. | Notas |
|--------|-----|-----|------------|-------|
| Qwen3-ASR-1.7B | Transcripción | GPU 0 | ~4 GB | Multilengüe, timestamps a nivel de palabra. Configurable a 0.6B para 8 GB VRAM. |
| Qwen3-ForcedAligner-0.6B | Alineación fonética | GPU 0 | ~2 GB | Word-level forced alignment |
| Senko (pyannote VAD) | Diarización | GPU 1 | ~3 GB | Identificación de hablantes sin conocer número previo |
| Qwen3.5-0.8B | Resumen | GPU 2 | ~2 GB | Resumen estructurado en markdown |

### Stack IA (Python)

| Librería | Propósito |
|----------|----------|
| FastAPI + Uvicorn | Servidor HTTP asíncrono del worker y microservicios |
| PyTorch + transformers | Carga e inferencia de modelos HuggingFace |
| qwen-asr | Wrapper específico de Qwen3-ASR |
| pyannote-audio | Diarización (VAD + embeddings + clustering) |
| aiohttp | Cliente HTTP asíncrono para callbacks y descarga |
| redis-py (async) | Consumo de cola Redis unificada |
| soundfile | Lectura de metadatos de audio |

### DevOps y calidad

| Herramienta | Uso |
|-------------|-----|
| Docker + docker-compose | Contenedores para desarrollo y producción |
| Nginx (gateway) | Reverse proxy, HTTPS, rate limiting (producción) |
| GitHub Actions | CI/CD: tests automáticos + deploy |
| Terraform + AWS | IaC: ALB, ASG, ACM (HTTPS), Security Groups, escalado |
| AWS ACM + Cloudflare | Certificado HTTPS gratuito auto-renovable |
| Pest (PHP) | Tests unitarios e integración backend (71 tests) |
| Playwright | Tests E2E frontend (6 specs, Page Object Model) |
| Karma + Jasmine | Tests unitarios Angular |

### Hardware de producción

| Recurso | Especificación |
|---------|---------------|
| GPU | NVIDIA DGX Station — 4x V100 32 GB |
| CPU | Intel Xeon (UMA) |
| Proxy IA | Bravo (nginx, puerto 7897) → Charlie (DGX, puerto 8000) |
| Servidor app | AWS EC2 con ALB + ASG |

---

## Requisitos del sistema

### Desarrollo
- Docker + Docker Compose
- NVIDIA GPU con drivers + nvidia-container-toolkit
- `nvidia-persistenced` activo (el Makefile lo detecta)
- 8 GB+ VRAM (modo compact, ASR 0.6B) o 16 GB+ (modo full, ASR 1.7B + resumidor)
- 16 GB RAM mínimo

### Producción
- **AWS** (app): EC2 via ALB con HTTPS (ACM) — `https://minerva.mayger.uk`
- **UMA** (IA): DGX Station 4x V100 32 GB (Charlie) + proxy nginx (Bravo)
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

#### Ciclo de vida

```bash
make init            # Inicialización completa (crea .env, build, up, migrate, seed)
make up              # Levantar todos los servicios
make down            # Detener y eliminar contenedores
make restart         # Reiniciar todos los servicios
make build           # Reconstruir imágenes sin caché
make clean           # Limpiar todo (contenedores + volúmenes + imágenes)
```

#### Componentes individuales

```bash
make front-up         # Levantar solo Frontend
make front-down       # Bajar solo Frontend
make front-logs       # Logs del Frontend
make back-up          # Levantar solo Backend (app + db + redis + worker)
make back-down        # Bajar solo Backend
make back-logs        # Logs del Backend
make ia-up            # Levantar solo IA (ASR + Diarizador + Resumidor)
make ia-down          # Bajar solo IA
make ia-logs          # Logs de IA
```

#### Base de datos

```bash
make migrate          # Ejecutar migraciones
make seed             # Ejecutar seeders
make migrate-fresh    # Recrear BD desde cero (DESTRUCTIVO)
make permisos         # Corregir permisos de storage
make build-assets     # Compilar assets frontend (solo DEV)
```

#### Colas y workers

```bash
make cola-estado      # Ver tareas pendientes en la cola unificada
make cola-limpiar     # Limpiar jobs fallidos
make scale-workers N=3 # Escalar workers (N=3)
make worker-logs      # Ver logs del worker
make sse-logs         # Ver logs de SSE/callbacks
```

#### Diagnóstico y acceso

```bash
make health           # Verificar conectividad entre servicios
make check-env        # Validar configuración del .env
make status           # Estado de los contenedores
make logs             # Ver logs en tiempo real de todos los servicios
make mode             # Mostrar modo actual (DEV/PROD) y ejemplos
make shell-backend    # Abrir shell en Laravel
make shell-frontend   # Abrir shell en Angular
make shell-db         # Abrir consola MariaDB
```

#### Testing

```bash
make test             # Ejecutar todos los tests
make test-backend     # Ejecutar tests Laravel (Pest, 71 tests)
make test-e2e         # Ejecutar tests E2E (Playwright)
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
├── Backend/           Laravel 12 API REST (PHP 8.2+)
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
│       ├── dashboard/         Vista principal con buscador y actividad reciente
│       ├── asignatura-view/   Detalle de asignatura + temas
│       ├── transcripcion-view/ Vista diarizada + resumen IA
│       ├── formulario-subida/  Upload de audio + tracking
│       ├── landing-page/      Página de bienvenida pública
│       ├── pipes/             MarkdownPipe (resumen → HTML)
│       └── services/          Auth, Minerva, SSE, Notification
├── IA/                FastAPI (Python) — ASR + Diarización + Resumen
│   ├── main.py            Orquestador: worker_loop, /estado, procesar_resumen
│   ├── worker.py           Worker alternativo de cola Redis
│   ├── procesamiento.py   Post-procesamiento (6 etapas)
│   ├── logger.py           Logging JSON estructurado
│   ├── gpu_manager.py      Gestión de carga/descarga de modelos en VRAM
│   ├── ASR/asr.py         Wrapper Qwen3-ASR + Qwen3-ForcedAligner
│   ├── DIARIZADOR/        Senko (pyannote VAD + clustering)
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
| `usuarios` | Usuarios del sistema | id_usuario, email, password_hash, nombre_completo, id_rol, id_estado |
| `asignaturas` | Materias del estudiante | id_asignatura, nombre, profesor, color_hex, id_usuario |
| `temas` | Temas dentro de asignatura | id_tema, nombre, orden, id_asignatura |
| `transcripciones` | Audio procesado | id_transcripcion, uuid_referencia, estado, texto_plano, texto_diarizado (JSON), resumen_ia, progreso_porcentaje |
| `tags` | Etiquetas personales | id_tag, nombre, color_hex, id_usuario |
| `roles` | ADMIN, USUARIO | id_rol, nombre, descripcion |
| `estados_usuario` | ACTIVO, SUSPENDIDO, BANEADO | id_estado, nombre |
| `historial_accesos` | Auditoría de login | id_acceso, ip_acceso, user_agent, fecha_acceso |
| `transcripciones_tags` | Tabla pivote M:N | id_transcripcion, id_tag |
| `personal_access_tokens` | Tokens Sanctum | tokenable_type, tokenable_id, token, abilities |

### Estados de procesamiento

```
SUBIENDO → ENCOLADO → PROCESANDO → COMPLETADO → RESUMIENDO → LISTO
                                  → FALLIDO
```

Durante PROCESANDO, se actualizan `progreso_porcentaje` (0-100) y `etapa_actual` (INICIANDO, ASR, DIARIZACION, POSTPROCESAMIENTO). Tras COMPLETADO, si AUTO_SUMMARY=true, se genera resumen automático (RESUMIENDO → LISTO).

---

## API REST

### Endpoints públicos

| Método | Ruta | Auth | Descripción |
|--------|------|------|-------------|
| POST | `/api/register` | — | Registro de usuario (nombre, email, password) |
| POST | `/api/login` | — | Inicio de sesión → devuelve token Sanctum |
| POST | `/api/ia/callback` | X-Callback-Secret | Callback de resultado final de IA |
| POST | `/api/ia/sse-update` | X-Callback-Secret | Actualización de progreso de IA |
| GET | `/api/internal/audio-download/{uuid}` | Bearer (secret) | Descarga de audio para el worker IA |
| GET | `/api/transcripciones/{uuid}/estado` | Token (query param) | SSE polling de progreso |

### Endpoints protegidos (Bearer Sanctum)

#### Autenticación y perfil

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/logout` | Cerrar sesión (revoca token) |
| GET | `/api/user` | Datos del usuario autenticado |
| PATCH | `/api/user/profile` | Actualizar nombre y preferencias |
| PATCH | `/api/user/password` | Cambiar contraseña |
| DELETE | `/api/user` | Eliminar cuenta |
| POST | `/api/sse/token` | Generar token temporal SSE (30s TTL, un solo uso) |

#### CRUD Asignaturas

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/asignaturas` | Listar asignaturas del usuario |
| POST | `/api/asignaturas` | Crear asignatura |
| GET | `/api/asignaturas/{id}` | Ver detalle de asignatura |
| PUT/PATCH | `/api/asignaturas/{id}` | Actualizar asignatura |
| DELETE | `/api/asignaturas/{id}` | Eliminar asignatura |

#### CRUD Temas

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/temas` | Listar temas (filtro: `?asignatura_id=X`) |
| POST | `/api/temas` | Crear tema |
| GET | `/api/temas/{id}` | Ver detalle de tema |
| PUT/PATCH | `/api/temas/{id}` | Actualizar tema |
| DELETE | `/api/temas/{id}` | Eliminar tema |

#### CRUD Etiquetas

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/tags` | Listar etiquetas del usuario |
| POST | `/api/tags` | Crear etiqueta |
| DELETE | `/api/tags/{id}` | Eliminar etiqueta |

#### Procesamiento de audio y transcripciones

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/ia/estado` | Health check del servicio IA |
| GET | `/api/transcripciones` | Listar transcripciones del usuario |
| POST | `/api/temas/{id}/procesar-audio` | Subir audio multipart (archivo + título + idioma) |
| GET | `/api/transcripciones/{id}` | Ver transcripción con segmentos diarizados |
| PUT | `/api/transcripciones/{id}` | Editar transcripción (título, tags) |
| DELETE | `/api/transcripciones/{id}` | Eliminar transcripción |

### Endpoints de administración (Bearer + rol ADMIN)

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/admin/usuarios` | Crear usuario |
| PUT/PATCH | `/api/admin/usuarios/{id}` | Actualizar usuario |
| DELETE | `/api/admin/usuarios/{id}` | Eliminar usuario |

---

## Servicio de IA

### Pipeline de procesamiento (6 etapas)

| # | Etapa | Tecnología | Qué hace |
|---|-------|-----------|----------|
| 1 | ASR | Qwen3-ASR | Transcribe audio → lista de palabras con timestamps |
| 2 | Diarización | Senko (pyannote VAD) | Identifica segmentos por hablante |
| 3 | Alineación | Custom (60% overlap) | Asigna cada palabra a un hablante |
| 4 | Suavizado | Custom | Fusiona fragmentos cortos (<1s) |
| 5 | Corrección | Custom | Resuelve segmentos «DESCONOCIDO» |
| 6 | Roles | Custom | Profesor = hablante con más duración |

### Modelos disponibles

| Modelo | VRAM | GPU | Uso |
|--------|------|-----|-----|
| Qwen3-ASR-1.7B | ~4 GB | GPU 0 | Transcripción multilengüe (default) |
| Qwen3-ASR-0.6B | ~2 GB | GPU 0 | Alternativa ligera para 8 GB VRAM |
| Qwen3-ForcedAligner-0.6B | ~2 GB | GPU 0 | Alineación palabra-timestamp |
| Senko (pyannote) | ~3 GB | GPU 1 | Diarización de hablantes |
| Qwen3.5-0.8B | ~2 GB | GPU 2 | Resumen estructurado de clases |

### Concurrencia

La IA procesa **un audio a la vez** (asyncio.Semaphore configurable con `GPU_CONCURRENCY`). Los demás se encolan en Redis. Esto es intencional: la GPU no puede paralelizar inferencia de modelos grandes sin fragmentar VRAM.

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

El audio puede tardar 10-20m en procesarse. Un HTTP timeout mataría la conexión. La cola permite reintentos automáticos (3 intentos con backoff exponencial).

### ¿Por qué «Patata Caliente» (streaming directo)?

El audio se envía directamente de Laravel a IA via HTTP multipart. No se usa filesystem compartido. Más simple, menos puntos de fallo.

### ¿Por qué Qwen3-ASR?

Modelo open-source multilengüe con timestamps a nivel de palabra. Soporta español, inglés, francés, alemán, portugués, italiano, catalán, euskera, gallego y más de 20 idiomas adicionales.

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
| Rate limiting | Throttle en rutas sensibles (login: 30/min, register: 30/min) |
| Soft deletes | Datos nunca se borran físicamente |
| Auditoría | HistorialAcceso registra cada login (IP, user-agent) |

---

## Observabilidad y depuración

### Modos de ejecución: DEV=1 vs DEV=0

El Makefile orquestador acepta la variable `DEV` para cambiar entre los dos modos de trabajo:

| Variable | Modo | Comportamiento |
|----------|------|---------------|
| `DEV=1` | **Desarrollo** | Usa `docker-compose.yml`. Hot reload en Angular y Laravel. Debug granular activo. Sin optimizaciones de caché. Puertos expuestos directamente (:4200, :8001, :8002). |
| `DEV=0` | **Producción** | Usa `docker-compose.production.yml` con profiles. Nginx como gateway único (:9122). Caché de config/route/view. Logs en JSON estructurado. Debug desactivado aunque las variables estén a 1. |

```bash
# Modo actual y ejemplos
make mode

# Forzar modo producción en cualquier comando
DEV=0 make init
DEV=0 make up
DEV=0 make health
```

### Logging estructurado (Producción)

En modo `DEV=0`, todos los servicios emiten logs JSON con un schema base común:

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

En modo `DEV=1`, cada subsistema tiene su flag independiente en `.env`:

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

Seguridad: tokens se muestran truncados, payloads >500 chars se truncan. En producción (`DEV=0`) el debug se ignora aunque las variables estén a 1.

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
| `MODELO_ASR` | Modelo de transcripción | `Qwen/Qwen3-ASR-1.7B` |
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

## Licencia

Proyecto académico — TFG 2º DAW.
