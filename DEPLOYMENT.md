# 🦉 Minerva - Despliegue en Producción

## 📋 Guía Rápida de Despliegue

Esta guía te ayudará a desplegar Minerva en el servidor de producción (150.214.56.73:9122).

---

## ✅ Pre-requisitos en el Servidor

Antes de desplegar, asegúrate de que el servidor tiene:

- [x] Docker Engine 24.0+
- [x] Docker Compose v2.20+
- [x] NVIDIA Driver 535+
- [x] NVIDIA Container Toolkit 1.14+
- [x] Puerto 9122 abierto en el firewall
- [x] Mínimo 24GB RAM
- [x] Mínimo 50GB espacio libre en disco
- [x] GPU NVIDIA con soporte CUDA

### Verificar requisitos:

```bash
docker --version
docker compose version
nvidia-smi
nvidia-ctk --version
```

---

## 🚀 Pasos de Despliegue

### 1. Clonar el repositorio en el servidor

```bash
# Conectar al servidor
ssh usuario@150.214.56.73

# Clonar la rama MVP-deploy
git clone -b MVP-deploy <URL_DEL_REPO> /opt/minerva
cd /opt/minerva
```

### 2. Configurar variables de entorno

```bash
# Copiar template de producción
cp .env.production .env

# Editar configuración
nano .env
```

**IMPORTANTE**: Debes cambiar estas variables en `.env`:

- `APP_KEY` - Se generará automáticamente si está vacía
- `DB_PASSWORD` - Cambiar por una contraseña segura
- `DB_ROOT_PASSWORD` - Cambiar por una contraseña segura

Ejemplo de contraseñas seguras:
```bash
# Generar contraseñas aleatorias
openssl rand -base64 32
```

### 3. Inicializar Minerva

```bash
# Ejecutar inicialización completa
make -f Makefile.production prod-init
```

Este comando:
- ✅ Verifica la configuración
- ✅ Genera APP_KEY si es necesario
- ✅ Crea carpetas compartidas
- ✅ Construye imágenes Docker (tarda ~15 min)
- ✅ Levanta todos los servicios
- ✅ Ejecuta migraciones de base de datos
- ✅ Optimiza Laravel para producción

### 4. Verificar el despliegue

```bash
# Ver estado de contenedores
make -f Makefile.production prod-status

# Ver logs en tiempo real
make -f Makefile.production prod-logs
```

**Todos los contenedores deben estar "healthy".**

### 5. Probar la aplicación

Abre en el navegador:
```
http://150.214.56.73:9122
```

Deberías ver el frontend de Minerva cargando.

---

## 🔧 Comandos Útiles

### Gestión de servicios

```bash
# Ver todos los comandos disponibles
make -f Makefile.production help

# Levantar servicios
make -f Makefile.production prod-up

# Detener servicios
make -f Makefile.production prod-down

# Reiniciar servicios
make -f Makefile.production prod-restart

# Ver logs
make -f Makefile.production prod-logs

# Ver estado
make -f Makefile.production prod-status
```

### Base de datos

```bash
# Ejecutar migraciones
make -f Makefile.production prod-migrate

# Crear backup
make -f Makefile.production prod-backup

# Acceder a consola MySQL
make -f Makefile.production prod-shell-db
```

### Optimización

```bash
# Optimizar Laravel (después de cambios)
make -f Makefile.production prod-optimize

# Limpiar cachés
make -f Makefile.production prod-clear-cache
```

### Acceso a contenedores

```bash
# Shell en Laravel
make -f Makefile.production prod-shell-backend

# Dentro del contenedor puedes ejecutar:
php artisan route:list
php artisan tinker
```

---

## 🔍 Verificación Post-Despliegue

### Checklist de verificación:

- [ ] Frontend carga en `http://150.214.56.73:9122/`
- [ ] API responde en `http://150.214.56.73:9122/api/...`
- [ ] Puedes registrar un usuario
- [ ] Puedes iniciar sesión
- [ ] Puedes crear una asignatura
- [ ] Puedes subir un archivo de audio
- [ ] La transcripción se procesa correctamente
- [ ] GPU está siendo utilizada (verificar con `nvidia-smi` en el servidor)

### Verificar GPU:

```bash
# En el servidor
watch -n 1 nvidia-smi

# Luego sube un audio para transcribir y verás la GPU en uso
```

### Verificar logs:

```bash
# Ver logs de todos los servicios
make -f Makefile.production prod-logs

# Ver logs de un servicio específico
docker compose -f docker-compose.production.yml logs -f minerva-asr
docker compose -f docker-compose.production.yml logs -f laravel-app
```

---

## 🐛 Troubleshooting

### Problema: Contenedor no está "healthy"

```bash
# Ver logs del contenedor problemático
docker compose -f docker-compose.production.yml logs <nombre-contenedor>

# Reiniciar el contenedor
docker compose -f docker-compose.production.yml restart <nombre-contenedor>
```

### Problema: Error de permisos en storage/

```bash
docker compose -f docker-compose.production.yml exec laravel-app chown -R www-data:www-data storage bootstrap/cache
docker compose -f docker-compose.production.yml exec laravel-app chmod -R 775 storage bootstrap/cache
```

### Problema: GPU no detectada

```bash
# Verificar que NVIDIA Container Toolkit está configurado
sudo nvidia-ctk runtime configure --runtime=docker
sudo systemctl restart docker

# Verificar que el contenedor puede ver la GPU
docker compose -f docker-compose.production.yml exec minerva-asr nvidia-smi
```

### Problema: Puerto 9122 no accesible

```bash
# Verificar firewall
sudo ufw status
sudo ufw allow 9122/tcp

# Verificar que el gateway está escuchando
docker compose -f docker-compose.production.yml exec nginx-gateway netstat -tlnp | grep 9122
```

### Problema: Base de datos no conecta

```bash
# Verificar que la BD está corriendo
docker compose -f docker-compose.production.yml ps minerva-db

# Ver logs de la BD
docker compose -f docker-compose.production.yml logs minerva-db

# Verificar credenciales en .env
cat .env | grep DB_
```

---

## 💾 Backup y Recuperación

### Crear backup manual:

```bash
make -f Makefile.production prod-backup
```

Los backups se guardan en `backups/minerva_backup_YYYYMMDD_HHMMSS.sql`

### Restaurar desde backup:

```bash
# Detener servicios
make -f Makefile.production prod-down

# Restaurar BD
docker compose -f docker-compose.production.yml up -d minerva-db
sleep 10
cat backups/minerva_backup_YYYYMMDD_HHMMSS.sql | \
  docker compose -f docker-compose.production.yml exec -T minerva-db \
  mariadb -u<usuario> -p<password> <database>

# Levantar todos los servicios
make -f Makefile.production prod-up
```

### Configurar backup automático (cron):

```bash
# Editar crontab
crontab -e

# Añadir línea para backup diario a las 3 AM
0 3 * * * cd /opt/minerva && make -f Makefile.production prod-backup
```

---

## 🔄 Actualización de la Aplicación

Para actualizar a una nueva versión:

```bash
cd /opt/minerva

# 1. Crear backup
make -f Makefile.production prod-backup

# 2. Detener servicios
make -f Makefile.production prod-down

# 3. Actualizar código
git pull origin MVP-deploy

# 4. Reconstruir imágenes
make -f Makefile.production prod-build

# 5. Levantar servicios
make -f Makefile.production prod-up

# 6. Ejecutar migraciones
make -f Makefile.production prod-migrate

# 7. Optimizar
make -f Makefile.production prod-optimize
```

---

## 📊 Monitoreo

### Ver uso de recursos:

```bash
# CPU y RAM de contenedores
docker stats

# GPU
watch -n 1 nvidia-smi

# Espacio en disco
df -h
du -sh /var/lib/docker/volumes/
```

### Ver logs de acceso:

```bash
# Logs del gateway (nginx)
docker compose -f docker-compose.production.yml logs nginx-gateway | grep "GET\|POST"
```

---

## 🔒 Seguridad

### Configuración aplicada:

- ✅ CORS restringido a IP del servidor
- ✅ `APP_DEBUG=false` (no expone información sensible)
- ✅ Rate limiting en API (10 req/s)
- ✅ Rate limiting en uploads (2 req/s)
- ✅ Headers de seguridad (X-Frame-Options, X-Content-Type-Options, etc.)
- ✅ PHP opcache habilitado
- ✅ Logs rotados automáticamente (max 10MB x 3 archivos)
- ✅ Contraseñas de BD no hardcodeadas

### Recomendaciones adicionales:

1. Cambiar contraseñas de BD periódicamente
2. Mantener Docker y drivers NVIDIA actualizados
3. Revisar logs regularmente
4. Configurar backups automáticos
5. Monitorear uso de disco (modelos IA ocupan espacio)

---

## 📞 Soporte

Si encuentras problemas:

1. Revisa los logs: `make -f Makefile.production prod-logs`
2. Verifica el estado: `make -f Makefile.production prod-status`
3. Consulta la sección de Troubleshooting arriba
4. Revisa la documentación del proyecto principal

---

## 📝 Notas Importantes

- **Primera ejecución**: Los modelos de IA se descargan automáticamente (~10 min)
- **Espacio en disco**: Los modelos ocupan ~15GB en caché
- **RAM**: Asegúrate de tener al menos 24GB disponibles
- **GPU**: Necesaria para los servicios de IA (ASR y Diarizador)
- **Puerto**: Solo el 9122 está expuesto, todos los demás son internos
- **Logs**: Se rotan automáticamente para no llenar el disco

---

**¡Minerva está lista para producción! 🦉**
