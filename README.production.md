# 🦉 Minerva - Rama MVP-deploy (Producción)

Esta rama contiene la configuración optimizada para despliegue en producción.

## 🎯 Diferencias con la rama main

Esta rama incluye:

- ✅ **Dockerfiles de producción** optimizados (multi-stage builds)
- ✅ **docker-compose.production.yml** con health checks y resource limits
- ✅ **Nginx Gateway** como punto de entrada único (puerto 9122)
- ✅ **Frontend optimizado** - Build estático servido por Nginx (~50MB)
- ✅ **Backend optimizado** - PHP opcache, sin código de desarrollo
- ✅ **Configuración de seguridad** - CORS restringido, APP_DEBUG=false
- ✅ **Makefile.production** con comandos específicos para producción
- ✅ **Documentación de despliegue** completa

## 🚀 Despliegue Rápido

```bash
# 1. Clonar esta rama en el servidor
git clone -b MVP-deploy <URL_REPO> /opt/minerva
cd /opt/minerva

# 2. Configurar entorno
cp .env.production .env
nano .env  # Cambiar contraseñas de BD

# 3. Desplegar
make -f Makefile.production prod-init

# 4. Verificar
make -f Makefile.production prod-status
```

**Acceso:** http://150.214.56.73:9122

## 📚 Documentación

Lee [DEPLOYMENT.md](./DEPLOYMENT.md) para la guía completa de despliegue.

## 🔧 Comandos Principales

```bash
# Ver todos los comandos
make -f Makefile.production help

# Gestión
make -f Makefile.production prod-up      # Levantar
make -f Makefile.production prod-down    # Detener
make -f Makefile.production prod-restart # Reiniciar
make -f Makefile.production prod-logs    # Ver logs
make -f Makefile.production prod-status  # Estado

# Mantenimiento
make -f Makefile.production prod-backup  # Backup BD
make -f Makefile.production prod-migrate # Migraciones
```

## ⚙️ Arquitectura de Producción

```
Internet (Puerto 9122)
         ↓
   Nginx Gateway
    ↓         ↓
Frontend    Backend API
(Nginx)     (Laravel)
              ↓
           MariaDB
              
    IA Services (GPU)
    ↓           ↓
   ASR      Diarizador
```

**Características:**
- Un solo puerto expuesto (9122)
- Servicios internos aislados
- Health checks automáticos
- Resource limits configurados
- Logs rotados automáticamente
- Restart automático en caso de fallo

## 🔒 Seguridad

- CORS restringido a IP del servidor
- APP_DEBUG=false (no expone errores)
- Rate limiting en API y uploads
- Headers de seguridad configurados
- Contraseñas no hardcodeadas
- PHP opcache habilitado

## 📊 Requisitos del Servidor

- Docker Engine 24.0+
- Docker Compose v2.20+
- NVIDIA Driver 535+
- NVIDIA Container Toolkit 1.14+
- 24GB RAM mínimo
- 50GB disco libre
- GPU NVIDIA con CUDA

## 🐛 Troubleshooting

Ver [DEPLOYMENT.md](./DEPLOYMENT.md) sección de Troubleshooting.

## 📝 Notas

- Primera ejecución: Los modelos de IA se descargan automáticamente (~10 min)
- Build inicial: ~15-20 minutos
- Espacio en disco: ~15GB para modelos + datos
- Solo usar esta rama para producción, no para desarrollo

---

**Para desarrollo, usa la rama `main` con el docker-compose.yml original.**
