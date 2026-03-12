# Guía de Pruebas - Minerva Producción

## 1. Verificar Estado de Contenedores

```bash
# Ver todos los contenedores (todos deben estar "healthy")
docker ps --format "table {{.Names}}\t{{.Status}}"

# Verificar logs del gateway
docker logs --tail 50 minerva-gateway

# Verificar logs de todos los servicios
docker compose -f docker-compose.production.yml logs --tail 20
```

## 2. Probar el Gateway (Puerto 9122)

```bash
# Health check del gateway
curl -I http://localhost:9122/health
# Debe devolver: HTTP/1.1 200 OK

# Verificar que el puerto está escuchando
netstat -tlnp | grep 9122
```

## 3. Probar el Frontend (Angular)

```bash
# Obtener la página principal
curl -I http://localhost:9122/
# Debe devolver: HTTP/1.1 200 OK

# Ver el HTML (primeras líneas)
curl -s http://localhost:9122/ | head -20
# Debe mostrar HTML con <app-root> o similar

# Desde tu navegador:
# http://150.214.56.73:9122/
```

## 4. Probar el Backend API (Laravel)

```bash
# Health check del backend
curl http://localhost:9122/api/health
# Debe devolver JSON con status

# Probar endpoint de API
curl -I http://localhost:9122/api/
# Debe devolver: HTTP/1.1 200 o 404 (depende de la ruta)

# Ver rutas disponibles (si Laravel las expone)
curl http://localhost:9122/api/
```

## 5. Probar el Panel Admin (Laravel)

```bash
# Acceder al admin
curl -I http://localhost:9122/admin
# Debe devolver: HTTP/1.1 200 o redirección 302

# Desde tu navegador:
# http://150.214.56.73:9122/admin
```

## 6. Probar Servicios de IA

```bash
# ASR (Transcripción)
curl http://localhost:8002/estado
# Debe devolver JSON con estado del servicio

# Diarizador (interno, no expuesto)
docker exec minerva-diarizador curl http://localhost:8000/estado
```

## 7. Verificar Conectividad Interna

```bash
# Desde el gateway hacia el frontend
docker exec minerva-gateway curl -I http://minerva-frontend:80

# Desde el gateway hacia el backend
docker exec minerva-gateway curl -I http://minerva-nginx:80

# Desde el backend hacia la base de datos
docker exec minerva-app php artisan db:show
```

## 8. Pruebas Funcionales Completas

### Desde el navegador:

1. **Frontend**: http://150.214.56.73:9122/
   - Debe cargar la aplicación Angular
   - Verificar que no hay errores en la consola del navegador (F12)

2. **Admin Panel**: http://150.214.56.73:9122/admin
   - Debe cargar el panel de administración
   - Intentar login si tienes credenciales

3. **API**: http://150.214.56.73:9122/api/
   - Verificar respuesta JSON

### Prueba de carga de archivos (si aplica):

```bash
# Subir un archivo de audio para transcripción
curl -X POST http://localhost:9122/api/transcripciones/upload \
  -F "audio=@/ruta/a/archivo.wav" \
  -H "Authorization: Bearer TU_TOKEN"
```

## 9. Monitoreo de Recursos

```bash
# Ver uso de recursos
docker stats --no-stream

# Ver logs en tiempo real
docker compose -f docker-compose.production.yml logs -f

# Ver solo errores
docker compose -f docker-compose.production.yml logs | grep -i error
```

## 10. Troubleshooting

### Si el gateway está unhealthy:
```bash
docker logs minerva-gateway
docker exec minerva-gateway curl http://localhost:9122/health
```

### Si el frontend no carga:
```bash
docker logs minerva-frontend
docker exec minerva-frontend ls -la /usr/share/nginx/html
```

### Si el backend no responde:
```bash
docker logs minerva-app
docker logs minerva-nginx
docker exec minerva-app php artisan config:cache
```

### Si hay problemas de base de datos:
```bash
docker logs minerva-db
docker exec minerva-app php artisan migrate:status
```

## Checklist Rápido

- [ ] Todos los contenedores están "healthy"
- [ ] Gateway responde en puerto 9122
- [ ] Frontend carga en el navegador
- [ ] Backend API responde
- [ ] Admin panel accesible
- [ ] Servicios IA responden
- [ ] No hay errores en los logs

## Accesos Rápidos

- **Aplicación**: http://150.214.56.73:9122/
- **Admin**: http://150.214.56.73:9122/admin
- **API**: http://150.214.56.73:9122/api/
- **Health**: http://150.214.56.73:9122/health
- **ASR**: http://150.214.56.73:8002/estado (directo)
