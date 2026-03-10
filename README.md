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
- [Servicios y Puertos](#-servicios-y-puertos)
- [API del Backend Laravel](#-api-del-backend-laravel)
- [API del Backend IA](#-api-del-backend-ia)
- [Frontend (Angular 17)](#-frontend-angular-17)
- [Estructura del Proyecto](#-estructura-del-proyecto)

---

## 🏗️ Arquitectura

```
┌──────────────┐     HTTP      ┌────────────────────┐     HTTP     ┌──────────────────┐
│   Frontend   │ ────────────▶ │  Backend (Laravel)  │ ──────────▶ │  Backend IA      │
│  Angular 17  │               │  PHP-FPM + Nginx    │             │  ASR + Diarizador│
│  :4200       │               │  :8001              │             │  :8002           │
└──────────────┘               └─────────┬──────────┘             └────────┬─────────┘
                                         │                                 │
                                         ▼                                 ▼
                                ┌─────────────────┐             ┌──────────────────┐
                                │    MariaDB       │             │  GPU (NVIDIA)    │
                                │    :3307         │             │  CUDA            │
                                └─────────────────┘             └──────────────────┘
                                                    
                                         ┌──────────────────────┐
                                         │   Shared/            │
                                         │   ├── entrada/       │ ◀── Audios upload
                                         │   └── salida/        │ ──▶ Transcripciones
                                         └──────────────────────┘
```

Todos los servicios corren en la red Docker `minerva-network`. Laravel e IA comparten la carpeta `Shared/` para intercambio de archivos de audio y transcripciones.

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
2. ✅ Crea las carpetas compartidas (`Shared/`)
3. ✅ Construye las imágenes Docker
4. ✅ Levanta todos los servicios
5. ✅ Instala dependencias PHP (Composer)
6. ✅ Genera la `APP_KEY` de Laravel
7. ✅ Corrige permisos de `storage/`
8. ✅ Ejecuta migraciones y seeders de la base de datos

---

## ⚙️ Configuración (.env)

El archivo `.env` en la raíz controla **toda** la configuración. Está dividido en secciones:

| Sección | Variables Clave | Descripción |
|---|---|---|
| **Frontend** | `FRONTEND_PORT` | Puerto del servidor Angular |
| **Laravel** | `LARAVEL_PORT`, `DB_*`, `APP_KEY` | Puerto API, credenciales BD |
| **IA** | `MODELO_ASR`, `DISPOSITIVO_ASR`, `ID_GPU` | Modelo, dispositivo CUDA, GPU |
| **Comunicación** | `AI_BACKEND_URL`, `URL_DIARIZADOR` | URLs internas Docker |
| **Shared** | `SHARED_DIR_ENTRADA`, `SHARED_DIR_SALIDA` | Rutas de intercambio de archivos |

Consulta `.env.example` para ver todas las opciones con documentación inline.

---

## 📂 Carpeta Compartida (Shared)

La carpeta `Shared/` es el punto de negociación entre Laravel e IA:

```
Shared/
├── entrada/    ← Laravel deposita los audios aquí
└── salida/     ← La IA escribe las transcripciones aquí
```

**Flujo de trabajo:**
1. El usuario sube un audio a través del Frontend.
2. Laravel lo guarda en `Shared/entrada/<uuid>/`.
3. Laravel envía una petición HTTP al servicio ASR con la ruta del archivo.
4. El ASR procesa el audio y escribe el resultado en `Shared/salida/<uuid>/`.
5. Laravel lee la transcripción de `Shared/salida/` y la persiste en la base de datos.

**Configuración:**
- Las rutas del host se definen en `.env` con `SHARED_DIR_ENTRADA` y `SHARED_DIR_SALIDA`.
- Las rutas del contenedor se definen con `RUTA_CONTENEDOR_ENTRADA` y `RUTA_CONTENEDOR_SALIDA`.
- Ambos backends ven exactamente las mismas rutas internas, lo que garantiza coherencia.

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

# --- Acceso a contenedores ---
make shell-backend    # 🐚 Shell en Laravel
make shell-frontend   # 🐚 Shell en Angular
make shell-db         # 🐚 Consola MariaDB

# --- Permisos y limpieza ---
make permisos         # 🔐 Corregir permisos de storage
make clean            # 🧹 Limpiar todo (contenedores, volúmenes, imágenes)
```

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
├── Makefile                # Comandos de automatización
├── README.md               # Este archivo
│
├── Shared/                 # 📂 Carpeta compartida IA ↔ Laravel
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
│   ├── docker/             #    Config Nginx + PHP
│   ├── Dockerfile
│   └── composer.json
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
