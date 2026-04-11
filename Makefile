# ==============================================================================
# Minerva - Makefile de Automatización
# Uso: make <comando>
# ==============================================================================

# Cargar variables del .env global (si existe)
-include .env
export

# ==============================================================================
# MODO
# DEV=1 (por defecto) usa docker-compose.yml (desarrollo)
# DEV=0 usa docker-compose.production.yml (producción)
# ==============================================================================
DEV ?= 1

ifeq ($(DEV),0)
COMPOSE_FILE := docker-compose.production.yml
else
COMPOSE_FILE := docker-compose.yml
endif

DC := docker compose -f $(COMPOSE_FILE)

# Ruta absoluta al directorio del proyecto
PROJECT_ROOT := $(shell pwd)

.PHONY: help mode init up down restart build logs status \
        front-up front-down front-logs back-up back-down back-logs ia-up ia-down ia-logs \
        migrate seed shell-backend shell-frontend shell-db \
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
	@echo "Modo actual: DEV=$(DEV) (compose: $(COMPOSE_FILE))"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  $(VERDE)%-20s$(RESET) %s\n", $$1, $$2}'
	@echo ""

mode: ## Mostrar modo actual (DEV=1/0) y compose activo
	@echo "DEV=$(DEV)"
	@echo "COMPOSE_FILE=$(COMPOSE_FILE)"
	@echo "DC=$(DC)"
	@echo ""
	@echo "Ejemplos:"
	@echo "  make up"
	@echo "  DEV=0 make back-up"
	@echo "  DEV=0 make ia-up"
	@echo ""

# ==============================================================================
# INICIALIZACIÓN COMPLETA
# ==============================================================================

init: ## 🚀 Inicialización completa (DEV=1) o producción esencial (DEV=0)
	@echo "$(AZUL)═══ 🚀 Inicializando Minerva... ═══$(RESET)"
	@if [ "$(DEV)" = "0" ]; then \
		echo "$(AMARILLO)Modo producción (DEV=0): asegúrate de haber copiado .env.production.example -> .env$(RESET)"; \
		if [ ! -f .env ]; then \
			echo "$(ROJO)❌ ERROR: No existe archivo .env$(RESET)"; \
			echo "$(AMARILLO)  cp .env.production.example .env$(RESET)"; \
			exit 1; \
		fi; \
		if grep -q "CAMBIAR_CONTRASEÑA_SEGURA_AQUI" .env 2>/dev/null; then \
			echo "$(ROJO)❌ ERROR: Debes cambiar DB_PASSWORD/DB_ROOT_PASSWORD en .env$(RESET)"; \
			exit 1; \
		fi; \
	else \
		if [ ! -f .env ]; then \
			echo "$(AMARILLO)📄 Creando .env desde .env.example...$(RESET)"; \
			cp .env.example .env; \
		else \
			echo "$(VERDE)✓ .env ya existe.$(RESET)"; \
		fi; \
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
	$(DC) build
	@# 6. Levantar servicios
	@echo "$(AMARILLO)🐳 Levantando servicios...$(RESET)"
	$(DC) up -d
	@# 7. Instalar dependencias de Composer
	@echo "$(AMARILLO)📦 Instalando dependencias PHP (Composer)...$(RESET)"
	$(DC) exec -T laravel-app composer install --no-interaction
	@# 8. Instalar dependencias de npm y compilar assets (solo DEV)
	@if [ "$(DEV)" != "0" ]; then \
		echo "$(AMARILLO)📦 Instalando dependencias npm...$(RESET)"; \
		$(MAKE) build-assets; \
	fi
	@# 9. Permisos de storage
	@$(MAKE) permisos
	@# 10. Ejecutar migraciones y seeders
	@echo "$(AMARILLO)🗄️ Ejecutando migraciones y seeders...$(RESET)"
	$(DC) exec -T laravel-app php artisan migrate --force --seed
	@if [ "$(DEV)" = "0" ]; then \
		echo "$(AMARILLO)⚡ Optimizando Laravel (cache config/rutas/vistas)...$(RESET)"; \
		$(DC) exec -T laravel-app php artisan config:cache; \
		$(DC) exec -T laravel-app php artisan route:cache; \
		$(DC) exec -T laravel-app php artisan view:cache; \
	fi
	@# 11. Iniciar workers de procesamiento
	@echo "$(AMARILLO)👷 Iniciando workers de procesamiento...$(RESET)"
	$(DC) up -d --scale laravel-worker=$${WORKER_REPLICAS:-1} laravel-worker
	@echo "$(VERDE)✓ Workers iniciados.$(RESET)"
	@echo ""
	@echo "$(VERDE)═══ ✅ Minerva inicializada correctamente ═══$(RESET)"
	@echo "$(VERDE)  Frontend:  http://localhost:$${FRONTEND_PORT:-4200}$(RESET)"
	@echo "$(VERDE)  Backend:   http://localhost:$${LARAVEL_PORT:-8001}$(RESET)"
	@echo "$(VERDE)  IA (ASR):  http://localhost:$${IA_ASR_PORT:-8002}$(RESET)"
	@echo "$(VERDE)  Base Datos: localhost:$${DB_EXTERNAL_PORT:-3307}$(RESET)"
	@echo ""
	@echo "$(AMARILLO)  Para ver el estado de la cola: make cola-estado$(RESET)"
	@echo "$(AMARILLO)  Para escalar workers: make scale-workers N=3$(RESET)"

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
	$(DC) up -d
	@echo "$(VERDE)✓ Servicios levantados.$(RESET)"

down: ## ⏹️  Detener todos los servicios
	@echo "$(ROJO)⏹️  Deteniendo servicios...$(RESET)"
	$(DC) down
	@echo "$(VERDE)✓ Servicios detenidos.$(RESET)"

restart: ## 🔄 Reiniciar todos los servicios
	@echo "$(AMARILLO)🔄 Reiniciando servicios...$(RESET)"
	$(DC) restart
	@echo "$(VERDE)✓ Servicios reiniciados.$(RESET)"

build: ## 🔨 Reconstruir imágenes Docker (sin caché)
	@echo "$(AMARILLO)🔨 Reconstruyendo imágenes...$(RESET)"
	$(DC) build --no-cache

logs: ## 📋 Ver logs en tiempo real de todos los servicios
	$(DC) logs -f

status: ## 📊 Ver estado de los contenedores
	$(DC) ps

# ==============================================================================
# COMPONENTES (DEV: servicios | PROD: profiles)
# ==============================================================================

front-up: ## Levantar solo Front (DEV: frontend | PROD: profiles front)
	@if [ "$(DEV)" = "0" ]; then \
		$(DC) --profile front up -d; \
	else \
		$(DC) up -d frontend; \
	fi

front-down: ## Bajar solo Front (DEV: frontend | PROD: profiles front)
	@if [ "$(DEV)" = "0" ]; then \
		$(DC) --profile front stop; \
	else \
		$(DC) stop frontend; \
	fi

front-logs: ## Logs solo Front (DEV: frontend | PROD: profiles front)
	@if [ "$(DEV)" = "0" ]; then \
		$(DC) --profile front logs -f; \
	else \
		$(DC) logs -f frontend; \
	fi

back-up: ## Levantar solo Back (DEV: laravel-* + db | PROD: profiles back)
	@if [ "$(DEV)" = "0" ]; then \
		$(DC) --profile back up -d; \
	else \
		$(DC) up -d laravel-app laravel-web minerva-db; \
	fi

back-down: ## Bajar solo Back (DEV: laravel-* + db | PROD: profiles back)
	@if [ "$(DEV)" = "0" ]; then \
		$(DC) --profile back stop; \
	else \
		$(DC) stop laravel-app laravel-web minerva-db; \
	fi

back-logs: ## Logs solo Back (DEV: laravel-* + db | PROD: profiles back)
	@if [ "$(DEV)" = "0" ]; then \
		$(DC) --profile back logs -f; \
	else \
		$(DC) logs -f laravel-app laravel-web minerva-db; \
	fi

ia-up: ## Levantar solo IA (DEV: minerva-* | PROD: profiles ia)
	@if [ "$(DEV)" = "0" ]; then \
		$(DC) --profile ia up -d; \
	else \
		$(DC) up -d minerva-asr minerva-diarizador; \
	fi

ia-down: ## Bajar solo IA (DEV: minerva-* | PROD: profiles ia)
	@if [ "$(DEV)" = "0" ]; then \
		$(DC) --profile ia stop; \
	else \
		$(DC) stop minerva-asr minerva-diarizador; \
	fi

ia-logs: ## Logs solo IA (DEV: minerva-* | PROD: profiles ia)
	@if [ "$(DEV)" = "0" ]; then \
		$(DC) --profile ia logs -f; \
	else \
		$(DC) logs -f minerva-asr minerva-diarizador; \
	fi

# ==============================================================================
# LARAVEL - Migraciones y Base de Datos
# ==============================================================================

migrate: ## 🗄️  Ejecutar migraciones de Laravel
	@echo "$(AMARILLO)🗄️ Ejecutando migraciones...$(RESET)"
	$(DC) exec -T laravel-app php artisan migrate --force

seed: ## 🌱 Ejecutar seeders de Laravel
	@echo "$(AMARILLO)🌱 Ejecutando seeders...$(RESET)"
	$(DC) exec -T laravel-app php artisan db:seed

migrate-fresh: ## 💥 Recrear toda la base de datos (¡DESTRUCTIVO!)
	@echo "$(ROJO)💥 Recreando base de datos desde cero...$(RESET)"
	$(DC) exec -T laravel-app php artisan migrate:fresh --seed

permisos: ## 🔐 Corregir permisos de storage de Laravel
	@echo "$(AMARILLO)🔐 Corrigiendo permisos...$(RESET)"
	$(DC) exec -T laravel-app chown -R www-data:www-data storage bootstrap/cache
	$(DC) exec -T laravel-app chmod -R 775 storage bootstrap/cache
	@echo "$(VERDE)✓ Permisos corregidos.$(RESET)"

build-assets: ## 🎨 Compilar assets frontend con Vite
	@echo "$(AMARILLO)🎨 Compilando assets con Vite...$(RESET)"
	cd Backend && npm run build
	@echo "$(VERDE)✓ Assets compilados.$(RESET)"

# ==============================================================================
# ACCESO A LOS CONTENEDORES
# ==============================================================================

shell-backend: ## 🐚 Abrir shell en el contenedor de Laravel
	$(DC) exec laravel-app bash

shell-frontend: ## 🐚 Abrir shell en el contenedor de Angular
	$(DC) exec frontend sh

shell-db: ## 🐚 Acceder a la consola de MariaDB
	$(DC) exec minerva-db mariadb -u$${DB_USERNAME:-minerva} -p$${DB_PASSWORD:-minerva_secret} $${DB_DATABASE:-backend_minerva}

# ==============================================================================
# COLAS Y WORKERS
# ==============================================================================

cola-estado: ## 📊 Ver estado de la cola de procesamiento
	@echo "$(AZUL)📊 Estado de la cola...$(RESET)"
	$(DC) exec -T laravel-app php artisan queue:monitor database

cola-limpiar: ## 🧹 Limpiar jobs fallidos de la cola
	@echo "$(AMARILLO)🧹 Limpiando jobs fallidos...$(RESET)"
	$(DC) exec -T laravel-app php artisan queue:flush

scale-workers: ## ▶️ Escalar workers de procesamiento (usage: make scale-workers N=3)
	@echo "$(AZUL)▶️ Escalando workers a $(N)...$(RESET)"
	$(DC) up -d --scale laravel-worker=$(N) laravel-worker

worker-logs: ## 📋 Ver logs del worker de procesamiento
	@echo "$(AZUL)📋 Logs del worker...$(RESET)"
	$(DC) logs -f laravel-worker

sse-logs: ## 📡 Ver logs de eventos SSE
	@echo "$(AZUL)📡 Logs de SSE...$(RESET)"
	$(DC) exec -T laravel-app tail -f storage/logs/laravel.log | grep -i "sse\|callback\|transcrip"

# ==============================================================================
# LIMPIEZA
# ==============================================================================

clean: ## 🧹 Limpiar todo (contenedores, volúmenes, imágenes del proyecto)
	@echo "$(ROJO)🧹 Limpiando todo el entorno Minerva...$(RESET)"
	$(DC) down -v --rmi local
	@echo "$(VERDE)✓ Limpieza completa.$(RESET)"

# ==============================================================================
# TESTING
# ==============================================================================

test: test-backend test-e2e ## Ejecutar todos los tests (backend + E2E)

test-backend: ## Ejecutar tests de Laravel con Pest
	@echo "$(AZUL)🧪 Ejecutando tests de Laravel...$(RESET)"
	$(DC) exec -T laravel-app ./vendor/bin/pest tests/Arch.php tests/Feature tests/Unit

test-e2e: ## Ejecutar tests E2E con Playwright (modo dev)
	@echo "$(AZUL)🎭 Ejecutando tests E2E con Playwright...$(RESET)"
	cd e2e && npx playwright test --reporter=list

test-e2e-ui: ## Ejecutar tests E2E con Playwright en modo UI
	@echo "$(AZUL)🎭 Ejecutando tests E2E en modo UI...$(RESET)"
	cd e2e && npx playwright test --ui

test-e2e-report: ## Generar y mostrar reporte HTML de Playwright
	@echo "$(AZUL)📊 Generando reporte E2E...$(RESET)"
	cd e2e && npx playwright test --reporter=html && npx playwright show-report

test-e2e-prod: ## Ejecutar tests E2E contra entorno de producción
	@echo "$(AZUL)🎭 Ejecutando tests E2E contra producción...$(RESET)"
	cd e2e && ENV=prod npx playwright test --reporter=list
