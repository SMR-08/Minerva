# Despliegue (DEV/PROD) — Minerva

Esta guía describe cómo levantar Minerva en **desarrollo** (DEV) y en **producción** (PROD), tanto en modo **monolítico** (todo en un servidor) como **distribuido** (Front/Back en un servidor web y IA en servidor universitario).

## Switch DEV=1/0 (Makefile)

- **DEV=1** (por defecto): usa `docker-compose.yml` (desarrollo)
- **DEV=0**: usa `docker-compose.production.yml` (producción)

Ejemplos:

```bash
# Desarrollo
make init
make up

# Producción
DEV=0 make init
DEV=0 make back-up
DEV=0 make front-up
DEV=0 make ia-up
```

## Producción con profiles (un solo compose)

`docker-compose.production.yml` define perfiles para poder levantar componentes por separado:

- `front`: `minerva-frontend` + `nginx-gateway`
- `back`: `laravel-app` + `laravel-web` + `minerva-db`
- `ia`: `minerva-asr` + `minerva-diarizador`

### Monolítico (Front + Back + IA en el mismo servidor)

```bash
cp .env.production.example .env
nano .env

DEV=0 make init

# Alternativa equivalente sin Make:
# docker compose -f docker-compose.production.yml --profile front --profile back --profile ia up -d
```

### Solo Front + Back (sin IA)

```bash
cp .env.production.example .env
nano .env

DEV=0 make back-up
DEV=0 make front-up
```

> Nota: si el backend apunta a IA interna por defecto, ajusta `AI_BACKEND_URL` para que apunte al servidor de IA (ver despliegue distribuido).

### Solo IA

```bash
cp .env.production.example .env
nano .env

DEV=0 make ia-up
```

## Multi-servidor (distribuido)

Objetivo típico:

- **Servidor WEB**: `front` + `back` + `nginx-gateway`
- **Servidor IA (universidad)**: `ia`

### 1) Servidor WEB (Front + Back)

En el servidor web:

```bash
cp .env.production.example .env
nano .env

# Gateway debe apuntar al backend local, pero puede apuntar a otro host
# BACKEND_UPSTREAM=http://minerva-nginx:80 (monolito / same-host)
# BACKEND_UPSTREAM=http://APP_HOST:8080    (distribuido)

DEV=0 make back-up
DEV=0 make front-up
```

Variables relevantes:

- `BACKEND_UPSTREAM`: upstream HTTP del backend para el gateway.
- `FRONTEND_UPSTREAM`: upstream HTTP del frontend para el gateway.

### 2) Servidor IA

En el servidor de IA:

```bash
cp .env.production.example .env
nano .env

DEV=0 make ia-up
```

En el **servidor WEB**, asegúrate de configurar en `.env`:

- `AI_BACKEND_URL=http://<HOST_IA>:8000` (o el puerto que expongas)

## Puertos (mínimo)

| Componente | Puerto | Nota |
|---|---:|---|
| Gateway | `9122` | Puerto único público (por defecto) |
| Backend (solo interno) | `80` | Vía `laravel-web` dentro de Docker |
| Frontend (solo interno) | `80` | Vía `minerva-frontend` dentro de Docker |
| IA ASR (opcional externo) | `8002` | En producción, normalmente no se expone |

## Notas de seguridad

- No versionar `.env` real (solo `.env.production.example`).
- CORS real lo gestiona Laravel (`Backend/config/cors.php`) con `CORS_ALLOWED_ORIGINS`.
- El gateway añade headers CORS solo como respaldo.
