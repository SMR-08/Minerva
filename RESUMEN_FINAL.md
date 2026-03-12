# 🚀 Minerva - Despliegue MVP Completado

## ✅ Estado Actual

**Rama**: `MVP-deploy`  
**Servidor**: 150.214.56.73  
**Puerto**: 9122  
**Todos los servicios**: ✅ Healthy

### Servicios Desplegados
- ✅ Gateway (nginx) - Puerto 9122
- ✅ Frontend (Angular 17) - Producción
- ✅ Backend (Laravel + PHP-FPM) - Producción
- ✅ Base de datos (MariaDB) - Containerizada
- ✅ ASR (Qwen3 + CUDA 12.4) - GPU 1
- ✅ Diarizador (Senko + CUDA 12.4) - GPU 1

## 🔧 Problemas Resueltos (7 fixes)

1. ✅ Frontend build error (@esbuild/aix-ppc64)
2. ✅ GPU mismatch (ID 0 → ID 1)
3. ✅ CUDA incompatibility (12.9 → 12.4)
4. ✅ IA health checks (/salud → /estado)
5. ✅ Laravel health check (php-fpm-healthcheck → pgrep)
6. ✅ Missing procps package
7. ✅ Frontend/Gateway health checks (wget → curl)

## ⚠️ Acción Requerida: Abrir Firewall

**Problema**: El puerto 9122 está bloqueado para conexiones externas.

**Solución**: Ejecuta en el servidor (150.214.56.73):

```bash
# Opción 1: Script automático
cd ~/shared_dockers/jupyter_duales/Mateo/Minerva/Minerva
sudo bash open-port.sh

# Opción 2: Manual (Ubuntu/UFW)
sudo ufw allow 9122/tcp
sudo ufw status

# Opción 3: Manual (iptables)
sudo iptables -I INPUT -p tcp --dport 9122 -j ACCEPT
sudo netfilter-persistent save
```

**Verificación** (desde tu máquina local):
```bash
curl -I http://150.214.56.73:9122/health
# Debe responder: HTTP/1.1 200 OK
```

## 📋 Commits Realizados

```
c80c92e docs: agregar guías de pruebas y solución de firewall
fe4d2d3 fix: instalar curl en gateway para health checks
0b3b273 fix: cambiar health check del frontend de wget a curl
561f784 fix: instalar procps en backend para health check con pgrep
5a26bfc fix: cambiar health check de Laravel a pgrep php-fpm
a7582a6 fix: cambiar health checks de /salud a /estado en servicios IA
4648a00 fix: cambiar CUDA 12.9 a 12.4 para compatibilidad
761a0a5 config: cambiar GPU por defecto a ID 1
9e8ceec fix: usar npm ci --force para deps opcionales
6c506b1 chore: añadir script para aplicar fix del frontend
... (15 commits total)
```

## 📚 Documentación Creada

- `DEPLOYMENT.md` - Guía completa de despliegue
- `CHECKLIST.md` - Lista de verificación
- `TEST_DEPLOYMENT.md` - Guía de pruebas
- `FIREWALL_FIX.md` - Solución del firewall
- `QUICK_START.txt` - Referencia rápida
- `README.production.md` - Resumen de la rama
- `open-port.sh` - Script para abrir puerto
- `Makefile.production` - Comandos de producción
- `deploy.sh` - Script de despliegue automatizado

## 🎯 Próximos Pasos

### 1. Abrir el Firewall (OBLIGATORIO)
```bash
# En el servidor
sudo ufw allow 9122/tcp
```

### 2. Verificar Acceso Externo
```bash
# Desde tu máquina local
curl -I http://150.214.56.73:9122/health
curl -s http://150.214.56.73:9122/ | head -10
```

### 3. Probar en el Navegador
- **Frontend**: http://150.214.56.73:9122/
- **Admin**: http://150.214.56.73:9122/admin
- **API**: http://150.214.56.73:9122/api/

### 4. Subir la Rama al Repositorio (Opcional)
```bash
# En local
git push origin MVP-deploy
```

## 🔍 Comandos Útiles

### En el servidor:
```bash
# Ver estado de contenedores
docker ps --format "table {{.Names}}\t{{.Status}}"

# Ver logs
docker compose -f docker-compose.production.yml logs -f

# Reiniciar servicios
make -f Makefile.production prod-restart

# Bajar todo
make -f Makefile.production prod-down

# Levantar todo
make -f Makefile.production prod-up
```

## 📊 Recursos Configurados

- **Frontend**: 256MB RAM, 0.5 CPU
- **Backend**: 1GB RAM, 1 CPU
- **Database**: 2GB RAM, 1 CPU
- **ASR**: GPU 1, 4GB VRAM
- **Diarizador**: GPU 1, 4GB VRAM
- **Gateway**: 256MB RAM, 0.5 CPU

## 🎓 Para la Presentación

1. ✅ Aplicación desplegada en producción
2. ✅ Docker Compose con todos los servicios
3. ✅ Health checks configurados
4. ✅ Logs centralizados
5. ✅ Optimización de recursos
6. ✅ Seguridad (CORS, rate limiting)
7. ⏳ Acceso externo (pendiente firewall)

## 🆘 Troubleshooting

Si algo falla:
```bash
# Ver logs de un servicio específico
docker logs minerva-gateway
docker logs minerva-frontend
docker logs minerva-app

# Verificar conectividad interna
docker exec minerva-gateway curl http://minerva-frontend:80
docker exec minerva-gateway curl http://minerva-nginx:80

# Reiniciar un servicio específico
docker compose -f docker-compose.production.yml restart minerva-gateway
```

---

**¡Despliegue completado!** Solo falta abrir el puerto 9122 en el firewall para acceso externo.
