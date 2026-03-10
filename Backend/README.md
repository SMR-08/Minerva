# Minerva Backend API (Laravel 12)

Este es el backend oficial del proyecto Minerva. Proporciona una API RESTful para la gestión de usuarios, asignaturas y temas, además de actuar como puente (Bridge) con el servicio de Inteligencia Artificial.

## 🚀 Guía Rápida para Frontend (Angular 17+)

### 1. Endpoint Base
`http://localhost:8000/api`

### 2. Autenticación (Sanctum)
El sistema utiliza Tokens Bearer estándar.
- **Login:** Devuelve un token de texto plano (`1|abcdef...`).
- **Requests:** Debes incluir este token en el header `Authorization` de **todas** las peticiones protegidas.

### Por que usamos Sanctum y tokens para la API.
Es bastante facil, tu quieres que alguien acceda a la API sin pertenecer como usuario de la aplicacion?, no verdad?.
Pues con Sanctum puedes hacerlo facilmente. 
Al tener un token siempre pasa por un usuario de la BBDD, por lo que puedes controlar que el usuario tenga permiso para acceder a ciertas API, aplicar cadencias, controles etc.... 


---

## 📡 Referencia de API

### Autenticación
| Método | Endpoint | Descripción | Body Requerido |
| :--- | :--- | :--- | :--- |
| `POST` | `/register` | Registrar Usuario | `{ "nombre_completo": "...", "email": "...", "password": "...", "device_name": "web" }` |
| `POST` | `/login` | Iniciar sesión | `{ "email": "...", "password": "...", "device_name": "web" }` |
| `POST` | `/logout` | Cerrar sesión | *(Token)* |
| `GET` | `/user` | Obtener perfil usuario | *(Token)* |

### IA Bridge (Gestión de Audio)
| Método | Endpoint | Descripción | Body Requerido |
| :--- | :--- | :--- | :--- |
| `GET` | `/ia/estado` | Verificar estado IA/Cola | *(Token)* |
| `POST` | `/temas/{id}/procesar-audio` | Subir y Transcribir | `FormData`: `audio` (File), `idioma` (opcional: 'es', 'auto') |
| `GET` | `/transcripciones/{id}` | Ver resultado transcripción | *(Token)* |

### Recursos (CRUD)
**Asignaturas** (`/asignaturas`) y **Temas** (`/temas`).
- Métodos estándar: `GET` (listar), `POST` (crear), `PUT` (actualizar), `DELETE` (eliminar).
- *Nota: Para filtrar temas por asignatura: `GET /api/temas?asignatura_id=1`*

---

## ⚙️ Configuración del Entorno (.env)

El archivo `.env` controla el comportamiento de la aplicación en los distintos entornos (Local, Docker, Producción).

### Variables Personalizadas (Nuestro Código)

| Variable | Descripción | Ejemplo |
| :--- | :--- | :--- |
| `AI_BACKEND_URL` | Dirección del servicio Python de IA. | `http://host.docker.internal:8000` |
| `AI_INPUT_PATH` | Ruta absoluta **local** donde PHP guardará los audios. | `/var/www/AI_Input` |
| `AI_TIMEOUT` | Tiempo máximo de espera para la IA (segundos). | `300` |
| `WWWUSER` | Usuario de sistema para Docker (evitar errores de permisos). | `minerva` |
| `APP_PORT` | Puerto externo del contenedor Backend. | `8001` |

### Notas de Docker
- Si usas Docker, la `AI_BACKEND_URL` debe apuntar a la red interna o usar `host.docker.internal` para acceder al host.
- El volumen de audios debe estar sincronizado entre este Backend y el servicio de IA.

---

## 🔐 Panel de Administración

El backend incluye un panel de administración web accesible en `/admin` con las siguientes características:

### Acceso
- **URL:** `http://localhost:8001/admin`
- **Credenciales por defecto:** `admin@minerva.com` / `admin123`

### Funcionalidades
- ✅ Sistema de autenticación con sesiones web
- ✅ Protección de rutas mediante middleware (`auth:web` + `es_admin`)
- ✅ Verificación de rol administrador (id_rol = 1)
- ✅ Funcionalidad "Recordar sesión"
- ✅ Gestión de usuarios del sistema
- ✅ Consola de debug para pruebas de IA
- ✅ Dashboard con estadísticas del sistema

### Seguridad
- Las contraseñas se hashean con bcrypt (12 rounds)
- CSRF protection habilitado en todos los formularios
- Sesiones regeneradas después del login
- Usuarios no-admin son rechazados automáticamente
- Intended redirect: redirige a la página intentada después del login

### Notas Técnicas
- El sistema usa el modelo `Usuario` (tabla `usuarios`) en lugar del modelo `User` estándar de Laravel
- Compatible con la autenticación API (Sanctum) sin conflictos
- El middleware `EsAdmin` detecta automáticamente peticiones web vs API

---

## 🛠️ Comandos Útiles

```bash
# Instalación inicial
composer install
php artisan key:generate
php artisan migrate --seed

# Despliegue Docker
docker-compose up -d --build

# Ver rutas disponibles
docker-compose exec app php artisan route:list
```
