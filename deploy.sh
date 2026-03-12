#!/bin/bash
# ==============================================================================
# Script de Despliegue Rápido - Minerva Production
# Ejecutar en el servidor: 150.214.56.73
# ==============================================================================

set -e  # Exit on error

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║  🦉 Minerva - Despliegue Automático en Producción           ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Variables
REPO_URL="${1:-}"
INSTALL_DIR="/opt/minerva"
BRANCH="MVP-deploy"

# Función para imprimir con color
print_step() {
    echo -e "${BLUE}▶ $1${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Verificar que se pasó la URL del repo
if [ -z "$REPO_URL" ]; then
    print_error "Debes proporcionar la URL del repositorio"
    echo "Uso: $0 <URL_REPOSITORIO>"
    echo "Ejemplo: $0 https://github.com/usuario/minerva.git"
    exit 1
fi

echo ""
print_step "1. Verificando requisitos del sistema..."

# Verificar Docker
if ! command -v docker &> /dev/null; then
    print_error "Docker no está instalado"
    exit 1
fi
print_success "Docker instalado: $(docker --version | cut -d' ' -f3)"

# Verificar Docker Compose
if ! docker compose version &> /dev/null; then
    print_error "Docker Compose no está instalado"
    exit 1
fi
print_success "Docker Compose instalado: $(docker compose version | cut -d' ' -f4)"

# Verificar NVIDIA
if ! command -v nvidia-smi &> /dev/null; then
    print_warning "nvidia-smi no encontrado - ¿GPU disponible?"
else
    print_success "NVIDIA Driver instalado"
fi

# Verificar NVIDIA Container Toolkit
if ! command -v nvidia-ctk &> /dev/null; then
    print_warning "nvidia-ctk no encontrado - Servicios IA podrían no funcionar"
else
    print_success "NVIDIA Container Toolkit instalado"
fi

echo ""
print_step "2. Clonando repositorio..."

if [ -d "$INSTALL_DIR" ]; then
    print_warning "El directorio $INSTALL_DIR ya existe"
    read -p "¿Deseas eliminarlo y continuar? (s/N): " confirm
    if [ "$confirm" = "s" ] || [ "$confirm" = "S" ]; then
        rm -rf "$INSTALL_DIR"
        print_success "Directorio eliminado"
    else
        print_error "Instalación cancelada"
        exit 1
    fi
fi

git clone -b "$BRANCH" "$REPO_URL" "$INSTALL_DIR"
print_success "Repositorio clonado en $INSTALL_DIR"

cd "$INSTALL_DIR"

echo ""
print_step "3. Configurando variables de entorno..."

if [ ! -f .env ]; then
    cp .env.production .env
    print_success "Archivo .env creado desde template"
    
    echo ""
    print_warning "IMPORTANTE: Debes configurar las contraseñas de la base de datos"
    echo ""
    echo "Generando contraseñas seguras..."
    DB_PASS=$(openssl rand -base64 32)
    ROOT_PASS=$(openssl rand -base64 32)
    
    sed -i "s|DB_PASSWORD=CAMBIAR_CONTRASEÑA_SEGURA_AQUI|DB_PASSWORD=$DB_PASS|" .env
    sed -i "s|DB_ROOT_PASSWORD=CAMBIAR_ROOT_PASSWORD_AQUI|DB_ROOT_PASSWORD=$ROOT_PASS|" .env
    
    print_success "Contraseñas generadas y configuradas automáticamente"
    
    echo ""
    print_warning "Revisa el archivo .env si necesitas ajustar algo:"
    echo "  nano .env"
    echo ""
    read -p "¿Deseas editar .env ahora? (s/N): " edit_env
    if [ "$edit_env" = "s" ] || [ "$edit_env" = "S" ]; then
        nano .env
    fi
else
    print_success "Archivo .env ya existe"
fi

echo ""
print_step "4. Verificando configuración..."

# Verificar que las contraseñas fueron cambiadas
if grep -q "CAMBIAR_CONTRASEÑA_SEGURA_AQUI" .env; then
    print_error "Las contraseñas de la BD no han sido configuradas"
    echo "Edita el archivo .env y cambia DB_PASSWORD y DB_ROOT_PASSWORD"
    exit 1
fi
print_success "Contraseñas de BD configuradas"

# Verificar APP_KEY
if ! grep -q '^APP_KEY=base64:' .env || [ -z "$(grep '^APP_KEY=' .env | cut -d'=' -f2)" ]; then
    print_warning "APP_KEY no configurada, se generará automáticamente"
fi

echo ""
print_step "5. Iniciando despliegue (esto puede tardar 15-20 minutos)..."
echo ""

make -f Makefile.production prod-init

echo ""
print_success "¡Despliegue completado!"

echo ""
print_step "6. Verificando estado de los servicios..."
echo ""

make -f Makefile.production prod-status

echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║  ✅ Minerva desplegada correctamente                        ║"
echo "║                                                              ║"
echo "║  Acceso: http://150.214.56.73:9122                          ║"
echo "║                                                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
echo "Comandos útiles:"
echo "  cd $INSTALL_DIR"
echo "  make -f Makefile.production prod-status   # Ver estado"
echo "  make -f Makefile.production prod-logs     # Ver logs"
echo "  make -f Makefile.production prod-backup   # Crear backup"
echo ""
echo "Documentación completa en: $INSTALL_DIR/DEPLOYMENT.md"
echo ""
