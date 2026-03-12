# ✅ CHECKLIST DE DESPLIEGUE - Minerva MVP Production

## 📋 Resumen de Cambios

La rama `MVP-deploy` está lista con la siguiente configuración:

### ✅ Archivos Creados (16 archivos nuevos)

**Configuración Docker:**
- ✅ `.dockerignore` (raíz)
- ✅ `docker-compose.production.yml`
- ✅ `Frontend/.dockerignore`
- ✅ `Frontend/Dockerfile.production`
- ✅ `Frontend/nginx.conf`
- ✅ `Backend/.dockerignore`
- ✅ `Backend/Dockerfile.production`
- ✅ `Backend/docker/php/production.ini`
- ✅ `IA/.dockerignore`
- ✅ `nginx-gateway/Dockerfile`
- ✅ `nginx-gateway/nginx.conf`

**Configuración y Documentación:**
- ✅ `.env.production` (template)
- ✅ `Makefile.production`
- ✅ `DEPLOYMENT.md` (guía completa)
- ✅ `README.production.md` (resumen)

**Modificaciones:**
- ✅ `Backend/config/cors.php` (CORS dinámico según entorno)

---

## 🎯 Configuración Aplicada

### Seguridad
- ✅ CORS restringido a `http://150.214.56.73:9122` en producción
- ✅ `APP_DEBUG=false`
- ✅ `LOG_LEVEL=warning`
- ✅ Rate limiting: 10 req/s en API, 2 req/s en uploads
- ✅ Headers de seguridad (X-Frame-Options, X-Content-Type-Options, etc.)
- ✅ PHP opcache habilitado
- ✅ Contraseñas no hardcodeadas (template con placeholders)

### Optimización
- ✅ Frontend: Build estático con Nginx (~50MB vs ~500MB)
- ✅ Backend: Multi-stage build, composer --no-dev, opcache
- ✅ Gzip compression habilitado
- ✅ Cache headers para assets estáticos (1 año)
- ✅ Resource limits en todos los servicios
- ✅ Logs rotados automáticamente (10MB x 3 archivos)

### Arquitectura
- ✅ Nginx Gateway como punto único de entrada (puerto 9122)
- ✅ Todos los servicios internos (no expuestos)
- ✅ Health checks configurados en todos los servicios
- ✅ Restart policy: always
- ✅ Volúmenes persistentes para BD y cachés de IA

---

## 🚀 Pasos para Desplegar (En el Servidor)

### 1. Clonar la rama MVP-deploy

```bash
ssh usuario@150.214.56.73
git clone -b MVP-deploy <URL_DEL_REPOSITORIO> /opt/minerva
cd /opt/minerva
```

### 2. Configurar entorno

```bash
# Copiar template
cp .env.production .env

# Editar y cambiar contraseñas
nano .env
```

**IMPORTANTE:** Cambiar estas líneas en `.env`:
```bash
DB_PASSWORD=CAMBIAR_CONTRASEÑA_SEGURA_AQUI
DB_ROOT_PASSWORD=CAMBIAR_ROOT_PASSWORD_AQUI
```

Generar contraseñas seguras:
```bash
openssl rand -base64 32
```

### 3. Desplegar

```bash
# Inicialización completa (tarda ~15-20 min la primera vez)
make -f Makefile.production prod-init
```

Este comando hace:
- Verifica configuración
- Genera APP_KEY automáticamente
- Construye imágenes Docker optimizadas
- Levanta todos los servicios
- Ejecuta migraciones de BD
- Optimiza Laravel (config/route/view cache)

### 4. Verificar

```bash
# Ver estado de contenedores
make -f Makefile.production prod-status

# Todos deben estar "healthy"
```

### 5. Probar

Abrir en navegador:
```
http://150.214.56.73:9122
```

---

## 🔍 Verificación Post-Despliegue

### Checklist de Verificación:

```bash
# 1. Frontend carga
curl -I http://150.214.56.73:9122/

# 2. API responde
curl http://150.214.56.73:9122/api/health

# 3. Ver logs
make -f Makefile.production prod-logs

# 4. Verificar GPU (en el servidor)
nvidia-smi

# 5. Verificar servicios IA
docker compose -f docker-compose.production.yml exec minerva-asr curl -f http://localhost:8000/salud
docker compose -f docker-compose.production.yml exec minerva-diarizador curl -f http://localhost:8000/salud
```

### Tests Funcionales:

- [ ] Registrar usuario
- [ ] Iniciar sesión
- [ ] Crear asignatura
- [ ] Crear tema
- [ ] Subir archivo de audio
- [ ] Verificar que la transcripción se procesa
- [ ] Verificar que la GPU se usa durante el procesamiento

---

## 📊 Comandos Útiles

```bash
# Ver todos los comandos
make -f Makefile.production help

# Gestión básica
make -f Makefile.production prod-up       # Levantar
make -f Makefile.production prod-down     # Detener
make -f Makefile.production prod-restart  # Reiniciar
make -f Makefile.production prod-logs     # Ver logs
make -f Makefile.production prod-status   # Estado

# Mantenimiento
make -f Makefile.production prod-backup   # Backup BD
make -f Makefile.production prod-migrate  # Migraciones
make -f Makefile.production prod-optimize # Optimizar Laravel

# Acceso
make -f Makefile.production prod-shell-backend  # Shell Laravel
make -f Makefile.production prod-shell-db       # Consola MySQL
```

---

## 📁 Estructura Final

```
Minerva/ (rama MVP-deploy)
├── .dockerignore                    # Optimización de builds
├── .env.production                  # Template de configuración
├── docker-compose.production.yml    # Orquestación optimizada
├── Makefile.production              # Comandos de producción
├── DEPLOYMENT.md                    # Guía completa (8KB)
├── README.production.md             # Resumen rápido
│
├── nginx-gateway/                   # Gateway unificado
│   ├── Dockerfile
│   └── nginx.conf                   # Puerto 9122, rate limiting
│
├── Frontend/
│   ├── .dockerignore
│   ├── Dockerfile.production        # Multi-stage: build + nginx
│   └── nginx.conf                   # Gzip, cache headers
│
├── Backend/
│   ├── .dockerignore
│   ├── Dockerfile.production        # Multi-stage: composer + runtime
│   ├── config/cors.php              # CORS dinámico
│   └── docker/php/production.ini    # Opcache, seguridad
│
└── IA/
    └── .dockerignore
```

---

## ⚠️ Notas Importantes

### Requisitos del Servidor:
- Docker Engine 24.0+
- Docker Compose v2.20+
- NVIDIA Driver 535+
- NVIDIA Container Toolkit 1.14+
- 24GB RAM mínimo
- 50GB disco libre
- GPU NVIDIA con CUDA
- Puerto 9122 abierto en firewall

### Primera Ejecución:
- Build de imágenes: ~15-20 minutos
- Descarga de modelos IA: ~10 minutos (automático)
- Espacio total usado: ~15GB (modelos) + ~5GB (imágenes Docker)

### Diferencias con Desarrollo:
- Puerto único: 9122 (vs múltiples puertos en dev)
- Sin hot-reload (builds estáticos)
- Sin volúmenes de código (solo runtime)
- Logs limitados (rotación automática)
- CORS restringido (solo IP del servidor)
- APP_DEBUG=false (no expone errores)

---

## 🐛 Troubleshooting Rápido

### Contenedor no healthy:
```bash
docker compose -f docker-compose.production.yml logs <nombre-contenedor>
docker compose -f docker-compose.production.yml restart <nombre-contenedor>
```

### GPU no detectada:
```bash
sudo nvidia-ctk runtime configure --runtime=docker
sudo systemctl restart docker
docker compose -f docker-compose.production.yml restart minerva-asr minerva-diarizador
```

### Puerto 9122 no accesible:
```bash
sudo ufw allow 9122/tcp
sudo ufw status
```

### BD no conecta:
```bash
# Verificar credenciales
cat .env | grep DB_

# Ver logs
docker compose -f docker-compose.production.yml logs minerva-db
```

---

## 📞 Documentación Completa

Para más detalles, consulta:
- **[DEPLOYMENT.md](./DEPLOYMENT.md)** - Guía completa de despliegue
- **[README.production.md](./README.production.md)** - Resumen de la rama

---

## ✅ Estado del Proyecto

- ✅ Rama `MVP-deploy` creada
- ✅ 16 archivos de configuración añadidos
- ✅ Commit realizado con mensaje descriptivo
- ✅ Documentación completa incluida
- ✅ Listo para push y despliegue

### Próximos Pasos:

1. **Push de la rama:**
   ```bash
   git push origin MVP-deploy
   ```

2. **En el servidor:**
   ```bash
   git clone -b MVP-deploy <URL> /opt/minerva
   cd /opt/minerva
   cp .env.production .env
   nano .env  # Cambiar contraseñas
   make -f Makefile.production prod-init
   ```

3. **Verificar:**
   ```bash
   make -f Makefile.production prod-status
   # Abrir: http://150.214.56.73:9122
   ```

---

**¡Minerva está lista para producción! 🦉**

Tiempo estimado de despliegue: 30-40 minutos (incluyendo builds y descarga de modelos)
