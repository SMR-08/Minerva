# 🦉 Minerva - Proyecto TFG

**Minerva** es una plataforma de transcripción de audio asistida por IA que combina reconocimiento automático del habla (ASR), diarización de hablantes, y una gestión organizada de transcripciones a través de una aplicación web completa.

---

## 📑 Índice

- [Arquitectura](#-arquitectura)
- [Requisitos del Sistema](#-requisitos-del-sistema)
- [Instalación Rápida](#-instalación-rápida)
- [Configuración (.env)](#-configuración-env)
- [Carpeta Compartida (Shared)](#-carpeta-compartida-shared)
- [Comandos Disponibles (Make)](#-comandos-disponibles-make)
- [Testing](#-testing)
- [Producción y despliegue distribuido](#-producción-y-despliegue-distribuido)
- [Servicios y Puertos](#-servicios-y-puertos)
- [API del Backend Laravel](#-api-del-backend-laravel)
- [API del Backend IA](#-api-del-backend-ia)
- [Frontend (Angular 17)](#-frontend-angular-17)
- [Estructura del Proyecto](#-estructura-del-proyecto)

---

## 🏗️ Arquitectura

### Flujo "Patata Caliente" (Audio Efímero)

Minerva usa una arquitectura donde el archivo de audio **nunca se almacena permanentemente**:

```
┌──────────────┐  multipart  ┌────────────────────┐  streaming  ┌──────────────────┐
│   Frontend   │ ───────────▶ │  Backend (Laravel) │ ──────────▶ │  Backend IA      │
│  Angular 17  │             │  PHP-FPM + Nginx   │             │  ASR + Diarizador│
│  :4200       │             │  :8001             │             │  :8002           │
└──────────────┘             └─────────┬──────────┘             └────────┬─────────┘
                                       │                                 │
                                       ▼                                 ▼
                              ┌─────────────────┐              ┌──────────────────┐
                              │    MariaDB       │              │  GPU (NVIDIA)    │
                              │    :3307         │              │  CUDA            │
                              └─────────────────┘              │  /tmp/{uuid}.wav │───🗑️
                                       ▲                       └──────────────────┘
                                       │                                 │
                                       └─────────── JSON ────────────────┘
                                   (solo texto, ~50KB)
```

**Características clave:**
- ✅ **Proxy streaming**: Laravel recibe y reenvía el audio a IA sin guardarlo
- ✅ **Archivo efímero**: Solo existe en `/tmp` de IA durante el procesamiento
- ✅ **Limpieza automática**: El archivo se elimina después de procesar
- ✅ **SSE**: Actualizaciones en tiempo real vía Server-Sent Events
- ✅ **Colas**: Múltiples peticiones se encolan y procesan secuencialmente

### Estados del Procesamiento

| Estado | Descripción | UI Usuario |
|--------|-------------|------------|
| `SUBIENDO` | Upload en progreso | Barra de subida |
| `ENCOLADO` | Esperando turno | "Posición #X en cola" |
| `PROCESANDO` | IA trabajando | Barra de progreso con etapa |
| `COMPLETADO` | Terminado | Botón "Ver resultado" |
| `FALLIDO` | Error | Mensaje claro + reintentar |

---

## 💻 Requisitos del Sistema

### Hardware Mínimo

| Componente | Mínimo | Recomendado |
|---|---|---|
| **CPU** | 4 núcleos | 8+ núcleos |
| **RAM** | 16 GB | 32 GB |
| **GPU NVIDIA** | 6 GB VRAM (ej. GTX 1660) | 12+ GB VRAM (ej. RTX 3060) |
| **Disco** | 40 GB libres | 80+ GB SSD |

> [!WARNING]
> Los modelos de IA (Qwen3-ASR + Senko) requieren GPU NVIDIA con soporte CUDA. Sin GPU, solo podrás usar el Frontend y Backend Laravel.

### Límites de Archivos

| Concepto | Valor |
|----------|-------|
| **Tamaño máximo** | 2 GB |
| **Formatos** | WAV, MP3, M4A, FLAC, OGG |
| **Timeout upload** | 2 horas |
| **Timeout procesamiento** | 2 horas |
| **Duración máxima** | ~3 horas de audio |

### Software Requerido

| Software | Versión Mínima | Verificar con |
|---|---|---|
| **Docker Engine** | 24.0+ | `docker --version` |
| **Docker Compose** | v2.20+ | `docker compose version` |
| **NVIDIA Driver** | 535+ | `nvidia-smi` |
| **NVIDIA Container Toolkit** | 1.14+ | `nvidia-ctk --version` |
| **Git** | 2.30+ | `git --version` |
| **Make** | 4.0+ | `make --version` |

### Instalación de Dependencias (Arch Linux / CachyOS)

```bash
# Docker
sudo pacman -S docker docker-compose
sudo systemctl enable --now docker
sudo usermod -aG docker $USER

# NVIDIA Container Toolkit
yay -S nvidia-container-toolkit
sudo nvidia-ctk runtime configure --runtime=docker
sudo systemctl restart docker

# Make y Git
sudo pacman -S make git
```

---

## 🚀 Instalación Rápida

```bash
# 1. Clonar el repositorio
git clone https://github.com/SMR-08/Minerva.git Minerva
cd Minerva

# 2. Inicialización completa (un solo comando)
make init
```

El comando `make init` realiza automáticamente:
1. ✅ Copia `.env.example` → `.env`
2. ✅ Crea las carpetas compartidas (`Shared/`) (legacy)
3. ✅ Construye las imágenes Docker
4. ✅ Levanta todos los servicios (incluyendo workers)
5. ✅ Instala dependencias PHP (Composer)
6. ✅ Genera la `APP_KEY` de Laravel
7. ✅ Corrige permisos de `storage/`
8. ✅ Ejecuta migraciones y seeders de la base de datos
9. ✅ Inicia los workers de procesamiento de cola

---

## ⚙️ Configuración (.env)

El archivo `.env` en la raíz controla **toda** la configuración. Está dividido en secciones:

| Sección | Variables Clave | Descripción |
|---|---|---|
| **Frontend** | `FRONTEND_PORT` | Puerto del servidor Angular |
| **Laravel** | `LARAVEL_PORT`, `DB_*`, `APP_KEY` | Puerto API, credenciales BD |
| **IA** | `MODELO_ASR`, `DISPOSITIVO_ASR`, `ID_GPU` | Modelo, dispositivo CUDA, GPU |
| **Comunicación** | `AI_BACKEND_URL`, `URL_DIARIZADOR` | URLs internas Docker |
| **Patata Caliente** | `IA_UPLOAD_URL`, `IA_CALLBACK_SECRET`, `LARAVEL_URL` | Upload streaming y callbacks |
| **Límites** | `AUDIO_MAX_SIZE_MB`, `UPLOAD_TIMEOUT_HOURS` | Tamaño máximo (2048MB), timeout (2h) |
| **Workers** | `WORKER_REPLICAS` | Número de workers de procesamiento |

### Variables nuevas para "Patata Caliente"

```bash
# Upload streaming a IA
IA_UPLOAD_URL=http://minerva-asr:8000

# Secret para autenticar callbacks (¡cambiar en producción!)
IA_CALLBACK_SECRET=tu_secreto_super_seguro

# URL de Laravel vista desde IA
LARAVEL_URL=http://laravel-app:80

# Límites de upload
AUDIO_MAX_SIZE_MB=2048
UPLOAD_TIMEOUT_HOURS=2

# Workers de procesamiento
WORKER_REPLICAS=2
```

Consulta `.env.example` para ver todas las opciones con documentación inline.

---

## 📂 Carpeta Compartida (Shared) - Legacy

> [!NOTE]
> En la arquitectura actual "Patata Caliente", **ya no se usa la carpeta Shared/** para archivos de audio.
> Se mantiene solo para compatibilidad con versiones anteriores.

**Flujo anterior (legacy):**
```
Shared/
├── entrada/    ← Laravel depositaba los audios aquí
└── salida/     ← La IA escribía las transcripciones aquí
```

**Flujo actual (Patata Caliente):**
1. El usuario sube un audio a través del Frontend.
2. Laravel hace **proxy streaming** del audio directamente a IA.
3. IA guarda temporalmente en `/tmp/{uuid}.wav`.
4. IA procesa y elimina el archivo inmediatamente.
5. IA envía el resultado (JSON ~50KB) a Laravel vía callback HTTP.
6. Laravel persiste la transcripción en la base de datos.

**Ventajas del nuevo flujo:**
- ✅ Sin almacenamiento innecesario de archivos grandes (2GB+)
- ✅ Backend ligero (no necesita espacio para audios)
- ✅ Limpieza automática después de procesar
- ✅ Funciona con IA en servidor separado (solo HTTP)

---

## 🛠️ Comandos Disponibles (Make)

```bash
make help             # Muestra todos los comandos disponibles

# --- Ciclo de vida ---
make init             # 🚀 Inicialización completa (primera vez)
make up               # ▶️  Levantar servicios
make down             # ⏹️  Detener servicios
make restart          # 🔄 Reiniciar servicios
make build            # 🔨 Reconstruir imágenes (sin caché)
make logs             # 📋 Ver logs en tiempo real
make status           # 📊 Estado de contenedores

# --- Base de datos ---
make migrate          # 🗄️  Ejecutar migraciones
make seed             # 🌱 Ejecutar seeders
make migrate-fresh    # 💥 Recrear BD completa (¡DESTRUCTIVO!)

# --- Colas y Workers ---
make cola-estado      # 📊 Ver estado de la cola de procesamiento
make cola-limpiar     # 🧹 Limpiar jobs fallidos de la cola
make scale-workers N=3 # ▶️  Escalar workers (ej: N=3)
make worker-logs      # 📋 Ver logs del worker de procesamiento
make sse-logs         # 📡 Ver logs de eventos SSE

# --- Testing ---
make test             # 🧪 Ejecutar todos los tests (backend + E2E)
make test-backend     # 🧪 Tests de Laravel con Pest
make test-e2e         # 🎭 Tests E2E con Playwright (dev)
make test-e2e-ui      # 🎭 Playwright en modo UI interactivo
make test-e2e-report  # 📊 Generar y mostrar reporte HTML
make test-e2e-prod    # 🎭 Tests E2E contra producción

# --- Acceso a contenedores ---
make shell-backend    # 🐚 Shell en Laravel
make shell-frontend   # 🐚 Shell en Angular
make shell-db         # 🐚 Consola MariaDB

# --- Permisos y limpieza ---
make permisos         # 🔐 Corregir permisos de storage
make clean            # 🧹 Limpiar todo (contenedores, volúmenes, imágenes)
```

---

## 🧪 Testing

Minerva cuenta con una suite de tests automatizada en **3 niveles** que cubre el flujo completo de usuario, la API REST y la arquitectura del código.

### Resumen de Tests

| Nivel | Herramienta | Tests | Assertions |
|-------|-------------|-------|------------|
| **Feature (API)** | Pest PHP | 53 | 131 |
| **Arquitectura** | Pest Arch | 8 | 19 |
| **Unit** | PHPUnit | 2 | 2 |
| **E2E (Navegador)** | Playwright | 18 | - |
| **TOTAL** | | **81** | **152** |

### Tests de Backend (Pest PHP)

Tests de integración contra la API REST de Laravel. Se ejecutan con SQLite en memoria para máxima velocidad.

```bash
# Ejecutar todos los tests de backend
make test-backend

# O directamente
cd Backend && ./vendor/bin/pest
```

**Cobertura por módulo:**

| Archivo | Qué prueba | Tests |
|---------|-----------|-------|
| `AuthTest.php` | Registro, login, logout, tokens Sanctum | 15 |
| `AsignaturaTest.php` | CRUD completo, aislamiento por usuario | 13 |
| `TemaTest.php` | CRUD, vinculación con asignatura, orden | 14 |
| `ProcesamientoAudioTest.php` | Subida de audio, validaciones, estados | 6 |
| `TranscripcionTest.php` | Listado, detalle, aislamiento | 5 |
| `Arch.php` | Estructura del código, buenas prácticas Laravel | 8 |

### Tests E2E (Playwright)

Tests de extremo a extremo que automatizan el navegador y verifican el flujo completo de usuario.

```bash
# Ejecutar tests E2E (contra frontend de desarrollo)
make test-e2e

# Modo UI interactivo (depuración visual)
make test-e2e-ui

# Generar reporte HTML con screenshots y vídeos
make test-e2e-report

# Ejecutar contra entorno de producción
make test-e2e-prod
```

**Flujos cubiertos:**

| Test | Qué verifica |
|------|-------------|
| `registro.spec.ts` | Registro exitoso, email duplicado, contraseñas no coinciden, campos vacíos, contraseña corta |
| `login.spec.ts` | Login correcto, credenciales incorrectas, botón disabled |
| `dashboard.spec.ts` | Carga correcta, secciones visibles, auth guard |
| `asignaturas.spec.ts` | Crear, ver temas, eliminar |
| `temas.spec.ts` | Crear, eliminar |
| `navegacion-completa.spec.ts` | Flujo E2E completo: registro → login → crear asignatura → crear tema → navegar → logout |

**Cambio de entorno:** Los tests E2E se configuran desde `e2e/playwright.config.ts`. Por defecto apuntan al servidor de desarrollo (`localhost:4200`). Para producción:

```bash
ENV=prod npx playwright test
```

### CI/CD (GitHub Actions)

Cada push o pull request ejecuta automáticamente:

1. Tests de backend (Pest) con base de datos MariaDB real
2. Tests E2E (Playwright) con frontend y backend levantados
3. Generación de reporte HTML y vídeos de fallos como artifacts

El workflow se encuentra en `.github/workflows/tests.yml`.

---

## 🚀 Producción y despliegue distribuido

- Switch `DEV=1/0` en el `Makefile`:
  - `DEV=1` (default): `docker-compose.yml` (desarrollo)
  - `DEV=0`: `docker-compose.production.yml` (producción)

- En producción puedes levantar componentes por separado con `profiles`:
  - `DEV=0 make front-up` (gateway + frontend)
  - `DEV=0 make back-up` (backend + BD)
  - `DEV=0 make ia-up` (IA)

Guía completa: `docs/DEPLOYMENT.md`.

---

## 🌐 Servicios y Puertos

| Servicio | Puerto Host | Puerto Interno | Contenedor | Acceso |
|---|---|---|---|---|
| **Frontend (Angular)** | `4200` | `4200` | `minerva-frontend` | `http://localhost:4200` |
| **Backend (Laravel API)** | `8001` | `80` | `minerva-nginx` | `http://localhost:8001/api` |
| **Panel Admin (Laravel)** | `8001` | `80` | `minerva-nginx` | `http://localhost:8001/admin` |
| **Base de Datos (MariaDB)** | `3307` | `3306` | `minerva-db` | `localhost:3307` |
| **IA - ASR** | `8002` | `8000` | `minerva-asr` | `http://localhost:8002` |
| **IA - Diarizador** | — | `8000` | `minerva-diarizador` | Solo interno |

> **Panel Admin:** Accede con `admin@minerva.com` / `admin123` para gestionar usuarios y sistema.  
> **Diarizador:** No expone puertos al host, solo se comunica internamente con el ASR.

---

## 📡 API del Backend Laravel

Base URL: `http://localhost:8001/api`

### Autenticación

| Método | Endpoint | Descripción |
|---|---|---|
| `POST` | `/register` | Registrar nuevo usuario |
| `POST` | `/login` | Iniciar sesión (token) |
| `POST` | `/logout` | Cerrar sesión |
| `GET` | `/me` | Datos del usuario autenticado |

### Asignaturas y Temas

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/asignaturas` | Listar asignaturas del usuario |
| `POST` | `/asignaturas` | Crear asignatura |
| `GET` | `/asignaturas/{id}/temas` | Listar temas de una asignatura |
| `POST` | `/temas` | Crear tema |

### Transcripciones e IA

| Método | Endpoint | Descripción |
|---|---|---|
| `POST` | `/transcripciones/upload` | Subir archivo de audio para transcribir |
| `GET` | `/transcripciones` | Listar transcripciones del usuario |
| `GET` | `/transcripciones/{id}` | Detalle completo de una transcripción |
| `DELETE` | `/transcripciones/{id}` | Eliminar transcripción |

> [!NOTE]
> La documentación completa de rutas se puede generar con: `make shell-backend` → `php artisan route:list --json`

---

## 🤖 API del Backend IA

Base URL (interna): `http://minerva-asr:8000` | Acceso host: `http://localhost:8002`

### Servicio ASR (Reconocimiento de Voz)

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/salud` | Estado del servicio y modelo cargado |
| `POST` | `/transcribir` | Transcribe un archivo de audio |
| `POST` | `/procesar_corpus` | Analiza representatividad de un corpus |

**`POST /transcribir`** — Ejemplo:
```json
{
  "ruta_audio": "/app/compartido/entrada/<uuid>/audio.wav",
  "idioma": "es"
}
```

**Respuesta:**
```json
{
  "transcripcion": "Texto transcrito...",
  "conversacion": { "hablantes": [...] },
  "duracion_segundos": 120.5
}
```

### Servicio Diarizador

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/salud` | Estado del servicio |
| `POST` | `/diarizar` | Identifica hablantes en un audio |

> [!NOTE]
> El diarizador solo es accesible internamente desde el servicio ASR.

---

## 🖥️ Frontend (Angular 17)

El frontend es una SPA construida con Angular 17 que se comunica exclusivamente con la API de Laravel.

**Características principales:**
- Gestión de asignaturas, temas y transcripciones.
- Subida de archivos de audio con seguimiento del proceso de transcripción.
- Visualización de transcripciones planas y con diarización.
- Sistema de tags/etiquetas.
- Panel de administración.

**Desarrollo:**
```bash
# Levantar solo el frontend con hot-reload
make up  # Ya incluye el frontend

# O acceder a su shell para tareas específicas
make shell-frontend
npm run build   # Build de producción
```

---

## 📁 Estructura del Proyecto

```
Minerva/
├── .env                    # Variables de entorno (generado desde .env.example)
├── .env.example            # Plantilla de configuración
├── .gitignore              # Exclusiones de Git
├── docker-compose.yml      # Orquestación de todos los servicios
├── docker-compose.production.yml  # Configuración de producción
├── Makefile                # Comandos de automatización
├── README.md               # Este archivo
│
├── Shared/                 # 📂 Carpeta compartida IA ↔ Laravel (legacy)
│   ├── entrada/            #    Audios subidos por usuarios
│   └── salida/             #    Transcripciones generadas por IA
│
├── Frontend/               # 🖥️ Angular 17
│   ├── src/
│   ├── package.json
│   └── angular.json
│
├── Backend/                # ⚙️ Laravel (PHP 8.4)
│   ├── app/
│   ├── database/
│   ├── routes/
│   ├── tests/              # 🧪 Tests de Pest (Feature + Arch)
│   ├── docker/             #    Config Nginx + PHP
│   ├── Dockerfile
│   └── composer.json
│
├── e2e/                    # 🎭 Tests E2E con Playwright
│   ├── pages/              #    Page Object Model
│   ├── tests/              #    Specs de tests
│   └── playwright.config.ts
│
├── .github/
│   └── workflows/          # 🔄 CI/CD con GitHub Actions
│       └── tests.yml
│
└── IA/                     # 🤖 FastAPI + GPU
    ├── main.py             #    API principal ASR
    ├── ASR/                #    Módulo de reconocimiento
    ├── DIARIZADOR/         #    Módulo de diarización
    ├── Dockerfile.asr
    └── Dockerfile.diarizador
```

---

## 📜 Licencia

Proyecto TFG - Universidad. Todos los derechos reservados.
