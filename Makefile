# ==============================================================================
# Minerva - Makefile de Automatización
# Uso: make <comando>
# ==============================================================================

# Cargar variables del .env global (si existe)
-include .env
export

.PHONY: help init up down restart build logs status \
        migrate seed shell-backend shell-frontend \
        clean permisos generar-env-laravel build-assets

# --- Colores ---
AZUL     := $(shell printf '\033[1;34m')
VERDE    := $(shell printf '\033[1;32m')
AMARILLO := $(shell printf '\033[1;33m')
ROJO     := $(shell printf '\033[1;31m')
RESET    := $(shell printf '\033[0m')

help: ## Mostrar esta ayuda
	@echo ""
	@echo "$(AZUL)╔══════════════════════════════════════════╗$(RESET)"
	@echo "$(AZUL)║     🦉 Minerva - Comandos Disponibles    ║$(RESET)"
	@echo "$(AZUL)╚══════════════════════════════════════════╝$(RESET)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  $(VERDE)%-20s$(RESET) %s\n", $$1, $$2}'
	@echo ""

# ==============================================================================
# INICIALIZACIÓN COMPLETA
# ==============================================================================

init: ## 🚀 Inicialización completa (primera vez): .env, build, up, dependencias, migraciones
	@echo "$(AZUL)═══ 🚀 Inicializando Minerva... ═══$(RESET)"
	@# 1. Crear .env global si no existe
	@if [ ! -f .env ]; then \
		echo "$(AMARILLO)📄 Creando .env desde .env.example...$(RESET)"; \
		cp .env.example .env; \
	else \
		echo "$(VERDE)✓ .env ya existe.$(RESET)"; \
	fi
	@# 2. Verificar y generar APP_KEY si está vacía
	@if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null || [ -z "$$(grep '^APP_KEY=' .env | cut -d'=' -f2)" ]; then \
		echo "$(AMARILLO)🔑 Generando APP_KEY...$(RESET)"; \
		NEW_KEY="base64:$$(openssl rand -base64 32)"; \
		sed -i "s|^APP_KEY=.*|APP_KEY=$$NEW_KEY|" .env; \
		echo "$(VERDE)✓ APP_KEY generada en .env raíz.$(RESET)"; \
	else \
		echo "$(VERDE)✓ APP_KEY ya configurada.$(RESET)"; \
	fi
	@# 3. Generar .env de Laravel desde el .env global
	@$(MAKE) generar-env-laravel
	@# 4. Crear carpetas compartidas
	@mkdir -p Shared/entrada Shared/salida
	@echo "$(VERDE)✓ Carpetas compartidas creadas.$(RESET)"
	@# 5. Construir imágenes
	@echo "$(AMARILLO)🐳 Construyendo imágenes Docker...$(RESET)"
	docker compose build
	@# 6. Levantar servicios
	@echo "$(AMARILLO)🐳 Levantando servicios...$(RESET)"
	docker compose up -d
	@# 7. Instalar dependencias de Composer
	@echo "$(AMARILLO)📦 Instalando dependencias PHP (Composer)...$(RESET)"
	docker compose exec laravel-app composer install --no-interaction
	@# 8. Instalar dependencias de npm y compilar assets
	@echo "$(AMARILLO)📦 Instalando dependencias npm...$(RESET)"
	cd Backend && npm install
	@echo "$(AMARILLO)🔨 Compilando assets con Vite...$(RESET)"
	cd Backend && npm run build
	@# 9. Permisos de storage
	@$(MAKE) permisos
	@# 10. Ejecutar migraciones y seeders
	@echo "$(AMARILLO)🗄️ Ejecutando migraciones y seeders...$(RESET)"
	docker compose exec laravel-app php artisan migrate --force --seed
	@echo ""
	@echo "$(VERDE)═══ ✅ Minerva inicializada correctamente ═══$(RESET)"
	@echo "$(VERDE)  Frontend:  http://localhost:$${FRONTEND_PORT:-4200}$(RESET)"
	@echo "$(VERDE)  Backend:   http://localhost:$${LARAVEL_PORT:-8001}$(RESET)"
	@echo "$(VERDE)  IA (ASR):  http://localhost:$${IA_ASR_PORT:-8002}$(RESET)"
	@echo "$(VERDE)  Base Datos: localhost:$${DB_EXTERNAL_PORT:-3307}$(RESET)"

generar-env-laravel: ## 🔧 Generar Backend/.env desde el .env global
	@echo "$(AMARILLO)🔧 Generando Backend/.env desde variables globales...$(RESET)"
	@echo "APP_NAME=$(APP_NAME)" > Backend/.env
	@echo "APP_ENV=$(APP_ENV)" >> Backend/.env
	@echo "APP_KEY=$(APP_KEY)" >> Backend/.env
	@echo "APP_DEBUG=$(APP_DEBUG)" >> Backend/.env
	@echo "APP_URL=$(APP_URL)" >> Backend/.env
	@echo "APP_LOCALE=$(APP_LOCALE)" >> Backend/.env
	@echo "APP_FALLBACK_LOCALE=$(APP_FALLBACK_LOCALE)" >> Backend/.env
	@echo "APP_FAKER_LOCALE=$(APP_FAKER_LOCALE)" >> Backend/.env
	@echo "BCRYPT_ROUNDS=$(BCRYPT_ROUNDS)" >> Backend/.env
	@echo "LOG_CHANNEL=$(LOG_CHANNEL)" >> Backend/.env
	@echo "LOG_STACK=$(LOG_STACK)" >> Backend/.env
	@echo "LOG_DEPRECATIONS_CHANNEL=null" >> Backend/.env
	@echo "LOG_LEVEL=$(LOG_LEVEL)" >> Backend/.env
	@echo "DB_CONNECTION=$(DB_CONNECTION)" >> Backend/.env
	@echo "DB_HOST=minerva-db" >> Backend/.env
	@echo "DB_PORT=3306" >> Backend/.env
	@echo "DB_DATABASE=$(DB_DATABASE)" >> Backend/.env
	@echo "DB_USERNAME=$(DB_USERNAME)" >> Backend/.env
	@echo "DB_PASSWORD=$(DB_PASSWORD)" >> Backend/.env
	@echo "SESSION_DRIVER=$(SESSION_DRIVER)" >> Backend/.env
	@echo "SESSION_LIFETIME=$(SESSION_LIFETIME)" >> Backend/.env
	@echo "SESSION_ENCRYPT=false" >> Backend/.env
	@echo "SESSION_PATH=/" >> Backend/.env
	@echo "SESSION_DOMAIN=null" >> Backend/.env
	@echo "BROADCAST_CONNECTION=$(BROADCAST_CONNECTION)" >> Backend/.env
	@echo "FILESYSTEM_DISK=$(FILESYSTEM_DISK)" >> Backend/.env
	@echo "QUEUE_CONNECTION=$(QUEUE_CONNECTION)" >> Backend/.env
	@echo "CACHE_STORE=$(CACHE_STORE)" >> Backend/.env
	@echo "MAIL_MAILER=$(MAIL_MAILER)" >> Backend/.env
	@echo "AI_BACKEND_URL=$(AI_BACKEND_URL)" >> Backend/.env
	@echo "AI_SERVICE_URL=$(AI_SERVICE_URL)" >> Backend/.env
	@echo "AI_INPUT_PATH=$(RUTA_CONTENEDOR_ENTRADA)" >> Backend/.env
	@echo "AI_TIMEOUT=$(AI_TIMEOUT)" >> Backend/.env
	@echo "$(VERDE)✓ Backend/.env generado.$(RESET)"

# ==============================================================================
# CICLO DE VIDA
# ==============================================================================

up: ## ▶️  Levantar todos los servicios
	@echo "$(AZUL)▶️  Levantando servicios...$(RESET)"
	docker compose up -d
	@echo "$(VERDE)✓ Servicios levantados.$(RESET)"

down: ## ⏹️  Detener todos los servicios
	@echo "$(ROJO)⏹️  Deteniendo servicios...$(RESET)"
	docker compose down
	@echo "$(VERDE)✓ Servicios detenidos.$(RESET)"

restart: ## 🔄 Reiniciar todos los servicios
	@echo "$(AMARILLO)🔄 Reiniciando servicios...$(RESET)"
	docker compose restart
	@echo "$(VERDE)✓ Servicios reiniciados.$(RESET)"

build: ## 🔨 Reconstruir imágenes Docker (sin caché)
	@echo "$(AMARILLO)🔨 Reconstruyendo imágenes...$(RESET)"
	docker compose build --no-cache

logs: ## 📋 Ver logs en tiempo real de todos los servicios
	docker compose logs -f

status: ## 📊 Ver estado de los contenedores
	docker compose ps

# ==============================================================================
# LARAVEL - Migraciones y Base de Datos
# ==============================================================================

migrate: ## 🗄️  Ejecutar migraciones de Laravel
	@echo "$(AMARILLO)🗄️ Ejecutando migraciones...$(RESET)"
	docker compose exec laravel-app php artisan migrate --force

seed: ## 🌱 Ejecutar seeders de Laravel
	@echo "$(AMARILLO)🌱 Ejecutando seeders...$(RESET)"
	docker compose exec laravel-app php artisan db:seed

migrate-fresh: ## 💥 Recrear toda la base de datos (¡DESTRUCTIVO!)
	@echo "$(ROJO)💥 Recreando base de datos desde cero...$(RESET)"
	docker compose exec laravel-app php artisan migrate:fresh --seed

permisos: ## 🔐 Corregir permisos de storage de Laravel
	@echo "$(AMARILLO)🔐 Corrigiendo permisos...$(RESET)"
	docker compose exec laravel-app chown -R www-data:www-data storage bootstrap/cache
	docker compose exec laravel-app chmod -R 775 storage bootstrap/cache
	@echo "$(VERDE)✓ Permisos corregidos.$(RESET)"

build-assets: ## 🎨 Compilar assets frontend con Vite
	@echo "$(AMARILLO)🎨 Compilando assets con Vite...$(RESET)"
	cd Backend && npm run build
	@echo "$(VERDE)✓ Assets compilados.$(RESET)"

# ==============================================================================
# ACCESO A LOS CONTENEDORES
# ==============================================================================

shell-backend: ## 🐚 Abrir shell en el contenedor de Laravel
	docker compose exec laravel-app bash

shell-frontend: ## 🐚 Abrir shell en el contenedor de Angular
	docker compose exec frontend sh

shell-db: ## 🐚 Acceder a la consola de MariaDB
	docker compose exec minerva-db mariadb -u$${DB_USERNAME:-minerva} -p$${DB_PASSWORD:-minerva_secret} $${DB_DATABASE:-backend_minerva}

# ==============================================================================
# LIMPIEZA
# ==============================================================================

clean: ## 🧹 Limpiar todo (contenedores, volúmenes, imágenes del proyecto)
	@echo "$(ROJO)🧹 Limpiando todo el entorno Minerva...$(RESET)"
	docker compose down -v --rmi local
	@echo "$(VERDE)✓ Limpieza completa.$(RESET)"
