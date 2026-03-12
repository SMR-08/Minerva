#!/bin/bash
# Script para aplicar el fix en el servidor

echo "🔧 Aplicando fix del build de Frontend..."
echo ""

cd /opt/minerva

# 1. Detener servicios si están corriendo
echo "⏹️  Deteniendo servicios..."
make -f Makefile.production prod-down 2>/dev/null || true

# 2. Pull de los cambios
echo "📥 Descargando cambios..."
git pull origin MVP-deploy

# 3. Limpiar imágenes antiguas del frontend
echo "🧹 Limpiando imagen antigua del frontend..."
docker rmi minerva-frontend:production 2>/dev/null || true

# 4. Rebuild solo del frontend
echo "🔨 Reconstruyendo imagen del frontend..."
docker compose -f docker-compose.production.yml build minerva-frontend

# 5. Levantar todos los servicios
echo "▶️  Levantando servicios..."
make -f Makefile.production prod-up

echo ""
echo "✅ Fix aplicado. Verificando estado..."
sleep 5
make -f Makefile.production prod-status
