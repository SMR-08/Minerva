# ==============================================================================
# Minerva - Makefile Orquestador
# ==============================================================================
# Uso: make <comando>
# Modo: DEV=1 (desarrollo) | DEV=0 (producción)
# ==============================================================================
-include .env
export
GPU_MODE ?= compact
DEV ?= 1
ifeq ($(DEV),0)
COMPOSE_FILE := docker-compose.production.yml
else
COMPOSE_FILE := docker-compose.yml
endif
DC := docker compose -f $(COMPOSE_FILE)
PROJECT_ROOT := $(shell pwd)
AZUL     := $(shell printf '\033[1;34m')
VERDE    := $(shell printf '\033[1;32m')
AMARILLO := $(shell printf '\033[1;33m')
ROJO     := $(shell printf '\033[1;31m')
RESET    := $(shell printf '\033[0m')
.PHONY: help mode init up down restart build logs status check-env health \
        front-up front-down front-logs back-up back-down back-logs ia-up ia-down ia-logs \
        migrate seed migrate-fresh permisos generar-env-laravel build-assets \
        shell-backend shell-frontend shell-db \
        cola-estado cola-limpiar scale-workers worker-logs sse-logs \
        clean test test-backend test-e2e

# ==============================================================================
# AYUDA
# ==============================================================================
help: ## Mostrar esta ayuda
	@echo ""
	@echo "$(AZUL)╔══════════════════════════════════════════╗$(RESET)"
	@echo "$(AZUL)║      Minerva - Comandos Disponibles      ║$(RESET)"
	@echo "$(AZUL)╚══════════════════════════════════════════╝$(RESET)"
	@echo ""
	@echo "Modo: DEV=$(DEV) | Compose: $(COMPOSE_FILE) | GPU: $(GPU_MODE)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' Makefile | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  $(VERDE)%-20s$(RESET) %s\n", $$1, $$2}'
	@echo ""

mode: ## Mostrar modo actual y ejemplos de uso
	@echo "DEV=$(DEV) | COMPOSE_FILE=$(COMPOSE_FILE) | GPU_MODE=$(GPU_MODE)"
	@echo ""
	@echo "Uso:"
	@echo "  make init              Desarrollo completo"
	@echo "  make health            Verificar conectividad"
	@echo "  DEV=0 make init        Producción monolítica"
	@echo "  DEV=0 make back-up     Solo backend (prod)"
	@echo "  DEV=0 make ia-up       Solo IA (prod)"

# ==============================================================================
# INICIALIZACIÓN
# ==============================================================================
init: ## Inicialización completa (DEV=1 desarrollo | DEV=0 producción)
	@echo "$(AZUL)Inicializando Minerva [DEV=$(DEV)]$(RESET)"
	@# --- .env ---
	@if [ ! -f .env ]; then \
		if [ "$(DEV)" = "0" ]; then \
			echo "$(ROJO)[ERROR] No existe .env. Ejecuta:$(RESET)"; \
			echo "  cp .env.production.example .env && nano .env"; \
			exit 1; \
		else \
			echo "$(AMARILLO)[WARN] Creando .env desde .env.example...$(RESET)"; \
			cp .env.example .env; \
		fi; \
	fi
	@$(MAKE) --no-print-directory check-env
	@# --- GPU check ---
	@if ! ls /run/nvidia-persistenced/socket >/dev/null 2>&1; then \
		echo "$(AMARILLO)[WARN] nvidia-persistenced no activo. Intentando activar...$(RESET)"; \
		systemctl start nvidia-persistenced 2>/dev/null || true; \
		sleep 1; \
		if ! ls /run/nvidia-persistenced/socket >/dev/null 2>&1; then \
			echo "$(ROJO)[ERROR] GPU no disponible. Los servicios de IA no arrancarán.$(RESET)"; \
			echo "$(ROJO)[ERROR] Ejecuta: sudo systemctl start nvidia-persistenced$(RESET)"; \
			echo "$(AMARILLO)[WARN] Continuando sin IA...$(RESET)"; \
		fi; \
	fi
	@# --- APP_KEY ---
	@if [ -z "$$(grep '^APP_KEY=' .env | cut -d'=' -f2)" ]; then \
		echo "$(AMARILLO)Generando APP_KEY...$(RESET)"; \
		KEY=$$(openssl rand -base64 32); \
		sed -i "s|^APP_KEY=.*|APP_KEY=base64:$$KEY|" .env; \
		echo "$(VERDE)[OK] APP_KEY generada.$(RESET)"; \
	fi
	@# --- Backend/.env ---
	@$(MAKE) --no-print-directory generar-env-laravel
	@# --- Build ---
	@echo "$(AMARILLO)Construyendo imágenes...$(RESET)"
	@if [ "$(DEV)" = "0" ]; then \
		PROFILES="--profile front --profile back"; \
		if ls /run/nvidia-persistenced/socket >/dev/null 2>&1; then \
			PROFILES="$$PROFILES --profile ia"; \
		fi; \
		$(DC) $$PROFILES build; \
	else \
		$(DC) build; \
	fi
	@# --- Limpiar contenedores huérfanos ---
	@docker ps -a --format '{{.Names}}' | grep minerva | xargs -r docker rm -f 2>/dev/null || true
	@# --- Up (sin worker, necesita migraciones primero) ---
	@echo "$(AMARILLO)Levantando servicios...$(RESET)"
	@if [ "$(DEV)" = "0" ]; then \
		PROFILES="--profile front --profile back"; \
		if ls /run/nvidia-persistenced/socket >/dev/null 2>&1; then \
			PROFILES="$$PROFILES --profile ia"; \
		else \
			echo "$(AMARILLO)[INFO] Sin GPU: servicios de IA omitidos.$(RESET)"; \
		fi; \
		$(DC) $$PROFILES up -d --scale laravel-worker=0; \
	else \
		$(DC) up -d --scale laravel-worker=0; \
	fi
	@# --- Esperar DB + Laravel ---
	@echo "$(AMARILLO)Esperando servicios...$(RESET)"
	@for i in $$(seq 1 15); do \
		$(DC) exec -T minerva-db healthcheck.sh --connect 2>/dev/null && break || sleep 2; \
	done
	@for i in $$(seq 1 20); do \
		$(DC) exec -T laravel-app php -r "echo 'ready';" 2>/dev/null && break || sleep 3; \
	done
	@# --- Inyectar .env en contenedor (volumen Docker no lo tiene) ---
	@if [ "$(DEV)" = "0" ]; then \
		docker cp Backend/.env minerva-app:/var/www/.env 2>/dev/null || true; \
	fi
	@# --- Composer (solo DEV) ---
	@if [ "$(DEV)" != "0" ]; then \
		echo "$(AMARILLO)Instalando dependencias PHP...$(RESET)"; \
		$(DC) exec -T laravel-app composer install --no-interaction 2>/dev/null; \
	fi
	@# --- Permisos ---
	@$(MAKE) --no-print-directory permisos
	@# --- Migraciones + Seed ---
	@echo "$(AMARILLO)Migraciones...$(RESET)"
	@$(DC) exec -T laravel-app php artisan migrate --force
	@echo "$(AMARILLO)Datos iniciales...$(RESET)"
	@$(DC) exec -T laravel-app php artisan db:seed --force 2>/dev/null || true
	@# --- Optimizar (solo PROD) ---
	@if [ "$(DEV)" = "0" ]; then \
		echo "$(AMARILLO)Optimizando Laravel...$(RESET)"; \
		$(DC) exec -T laravel-app php artisan config:cache; \
		$(DC) exec -T laravel-app php artisan route:cache; \
		$(DC) exec -T laravel-app php artisan view:cache; \
	fi
	@# --- Workers (despues de migraciones) ---
	@echo "$(AMARILLO)Iniciando workers...$(RESET)"
	@if [ "$(DEV)" = "0" ]; then \
		$(DC) --profile back up -d laravel-worker; \
		sleep 3; \
		docker cp Backend/.env minerva-worker:/var/www/.env 2>/dev/null || true; \
	else \
		$(DC) up -d --scale laravel-worker=$${WORKER_REPLICAS:-1} laravel-worker 2>/dev/null || true; \
	fi
	@# --- Resumen ---
	@echo ""
	@echo "$(VERDE)[OK] Minerva lista$(RESET)"
	@if [ "$(DEV)" != "0" ]; then \
		echo "  Frontend:  http://localhost:$${FRONTEND_PORT:-4200}"; \
		echo "  Backend:   http://localhost:$${LARAVEL_PORT:-8001}"; \
		echo "  IA (ASR):  http://localhost:$${IA_ASR_PORT:-8002}"; \
	else \
		echo "  Gateway: $$(grep '^APP_URL=' .env | cut -d'=' -f2-):$${GATEWAY_PORT:-9122}"; \
	fi
	@echo ""
	@echo "$(AMARILLO)Siguiente: make health$(RESET)"
	@echo ""

# ==============================================================================
# GENERACIÓN DE CONFIGURACIÓN
# ==============================================================================
generar-env-laravel: ## Generar Backend/.env desde el .env global
	@echo "$(AMARILLO)Generando Backend/.env...$(RESET)"
	@echo "APP_NAME=$(APP_NAME)" > Backend/.env
	@echo "APP_ENV=$(APP_ENV)" >> Backend/.env
	@echo "APP_KEY=$$(grep '^APP_KEY=' .env | cut -d'=' -f2-)" >> Backend/.env
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
	@echo "REDIS_HOST=$(REDIS_HOST)" >> Backend/.env
	@echo "REDIS_PASSWORD=$(REDIS_PASSWORD)" >> Backend/.env
	@echo "REDIS_PORT=$(REDIS_PORT)" >> Backend/.env
	@echo "IA_UPLOAD_URL=$(IA_UPLOAD_URL)" >> Backend/.env
	@echo "LARAVEL_URL=$(LARAVEL_URL)" >> Backend/.env
	@echo "IA_CALLBACK_SECRET=$(IA_CALLBACK_SECRET)" >> Backend/.env
	@echo "AI_TIMEOUT=$(AI_TIMEOUT)" >> Backend/.env
	@echo "AUDIO_MAX_SIZE_MB=$(AUDIO_MAX_SIZE_MB)" >> Backend/.env
	@echo "SSE_HEARTBEAT_SECONDS=$(SSE_HEARTBEAT_SECONDS)" >> Backend/.env
	@echo "SSE_POLL_INTERVAL_MICROSECONDS=$(SSE_POLL_INTERVAL_MICROSECONDS)" >> Backend/.env
	@echo "CORS_ALLOWED_ORIGINS=$(CORS_ALLOWED_ORIGINS)" >> Backend/.env
	@echo "SANCTUM_STATEFUL_DOMAINS=$(SANCTUM_STATEFUL_DOMAINS)" >> Backend/.env
	@echo "$(VERDE)[OK] Backend/.env generado.$(RESET)"

# ==============================================================================
# CICLO DE VIDA
# ==============================================================================
up: ## Levantar todos los servicios
	@echo "$(AZUL)Levantando servicios...$(RESET)"
	@docker ps -a --filter status=exited --filter status=created --format '{{.Names}}' | grep minerva | xargs -r docker rm -f 2>/dev/null || true
	@if [ "$(DEV)" = "0" ]; then \
		$(DC) --profile front --profile back --profile ia up -d; \
	else \
		$(DC) up -d; \
	fi
	@echo "$(VERDE)[OK] Servicios levantados.$(RESET)"

down: ## Detener y eliminar contenedores
	@echo "$(ROJO)Deteniendo servicios...$(RESET)"
	@if [ "$(DEV)" = "0" ]; then \
		$(DC) --profile front --profile back --profile ia down; \
	else \
		$(DC) down; \
	fi
	@echo "$(VERDE)[OK] Servicios detenidos.$(RESET)"

restart: ## Reiniciar todos los servicios
	@$(MAKE) --no-print-directory down
	@$(MAKE) --no-print-directory up

build: ## Reconstruir imágenes Docker (sin caché)
	@echo "$(AMARILLO)Reconstruyendo imágenes...$(RESET)"
	@if [ "$(DEV)" = "0" ]; then \
		$(DC) --profile front --profile back --profile ia build --no-cache; \
	else \
		$(DC) build --no-cache; \
	fi

logs: ## Ver logs en tiempo real
	@if [ "$(DEV)" = "0" ]; then \
		$(DC) --profile front --profile back --profile ia logs -f; \
	else \
		$(DC) logs -f; \
	fi

status: ## Ver estado de los contenedores
	@if [ "$(DEV)" = "0" ]; then \
		$(DC) --profile front --profile back --profile ia ps; \
	else \
		$(DC) ps; \
	fi

# ==============================================================================
# DIAGNÓSTICO Y VALIDACIÓN
# ==============================================================================
check-env: ## Validar configuración del .env
	@echo "$(AZUL)Validando .env [$(DEV)]$(RESET)"
	@ERRORES=0; \
	if [ ! -f .env ]; then echo "$(ROJO)[ERROR] No existe .env$(RESET)"; exit 1; fi; \
	if [ "$(DEV)" = "0" ]; then \
		echo "$(AMARILLO)Modo: PRODUCCIÓN$(RESET)"; \
		if grep -q "CAMBIAR_" .env; then \
			echo "$(ROJO)  [ERROR] Hay valores por cambiar (busca CAMBIAR_)$(RESET)"; ERRORES=1; \
		fi; \
		if grep -q "TU_IP_SERVIDOR" .env; then \
			echo "$(ROJO)  [ERROR] Falta configurar IP del servidor$(RESET)"; ERRORES=1; \
		fi; \
	else \
		echo "$(AMARILLO)Modo: DESARROLLO$(RESET)"; \
	fi; \
	IA_URL=$$(grep '^IA_UPLOAD_URL=' .env | cut -d'=' -f2); \
	LAR_URL=$$(grep '^LARAVEL_URL=' .env | cut -d'=' -f2); \
	echo "  IA_UPLOAD_URL = $$IA_URL"; \
	echo "  LARAVEL_URL   = $$LAR_URL"; \
	if echo "$$IA_URL" | grep -q "minerva-asr"; then \
		echo "  $(AMARILLO)IA: nombre Docker interno (misma red)$(RESET)"; \
	else \
		echo "  $(VERDE)IA: dirección externa (distribuido)$(RESET)"; \
	fi; \
	if echo "$$LAR_URL" | grep -q "minerva-nginx"; then \
		echo "  $(AMARILLO)Laravel: nombre Docker interno (misma red)$(RESET)"; \
	else \
		echo "  $(VERDE)Laravel: dirección externa (distribuido)$(RESET)"; \
	fi; \
	if [ "$$ERRORES" = "1" ]; then \
		echo "$(ROJO)Corrige los errores antes de continuar.$(RESET)"; exit 1; \
	fi; \
	echo "$(VERDE)[OK] Configuración válida.$(RESET)"

health: ## Verificar conectividad entre servicios
	@echo "$(AZUL)Health Check$(RESET)"
	@echo "$(AMARILLO)Backend:$(RESET)"
	@$(DC) exec -T laravel-app php -r "echo '';" 2>/dev/null && echo "  $(VERDE)[OK] PHP-FPM$(RESET)" || echo "  $(ROJO)[FAIL] PHP-FPM$(RESET)"
	@$(DC) exec -T laravel-app php -r "require '/var/www/vendor/autoload.php'; \$$a=require '/var/www/bootstrap/app.php'; \$$a->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap(); \Illuminate\Support\Facades\DB::connection()->getPdo();" 2>/dev/null && echo "  $(VERDE)[OK] Base de datos$(RESET)" || echo "  $(ROJO)[FAIL] Base de datos$(RESET)"
	@$(DC) exec -T minerva-redis redis-cli ping 2>/dev/null | grep -q "PONG" && echo "  $(VERDE)[OK] Redis$(RESET)" || echo "  $(ROJO)[FAIL] Redis$(RESET)"
	@echo "$(AMARILLO)IA:$(RESET)"
	@$(DC) exec -T laravel-app curl -sf http://minerva-asr:8000/estado 2>/dev/null | grep -q "activa" && echo "  $(VERDE)[OK] ASR accesible desde Backend$(RESET)" || echo "  $(ROJO)[FAIL] ASR no accesible desde Backend$(RESET)"
	@$(DC) exec -T laravel-app curl -sf http://minerva-asr:8000/estado_cola 2>/dev/null | grep -q "estado" && echo "  $(VERDE)[OK] Cola IA$(RESET)" || echo "  $(ROJO)[FAIL] Cola IA$(RESET)"
	@echo "$(AMARILLO)Callbacks:$(RESET)"
	@$(DC) exec -T minerva-asr curl -s -X POST http://minerva-nginx:80/api/ia/sse-update -H 'Content-Type: application/json' -H 'Accept: application/json' -H 'X-Callback-Secret: $(IA_CALLBACK_SECRET)' -d '{"uuid":"health","estado":"PROCESANDO","progreso":0}' 2>/dev/null | grep -q "uuid\|error\|message" && echo "  $(VERDE)[OK] IA -> Laravel (callback)$(RESET)" || echo "  $(ROJO)[FAIL] IA -> Laravel (callback)$(RESET)"
	@echo "$(VERDE)[OK] Health check completado$(RESET)"

# ==============================================================================
# COMPONENTES (DEV: servicios | PROD: profiles)
# ==============================================================================
front-up: ## Levantar solo Frontend
	@if [ "$(DEV)" = "0" ]; then $(DC) --profile front up -d; \
	else $(DC) up -d minerva-frontend; fi

front-down: ## Bajar solo Frontend
	@if [ "$(DEV)" = "0" ]; then $(DC) --profile front stop; \
	else $(DC) stop minerva-frontend; fi

front-logs: ## Logs del Frontend
	@if [ "$(DEV)" = "0" ]; then $(DC) --profile front logs -f; \
	else $(DC) logs -f minerva-frontend; fi

back-up: ## Levantar solo Backend (app + db + redis + worker)
	@if [ "$(DEV)" = "0" ]; then $(DC) --profile back up -d; \
	else $(DC) up -d laravel-app minerva-nginx minerva-db minerva-redis laravel-worker; fi

back-down: ## Bajar solo Backend
	@if [ "$(DEV)" = "0" ]; then $(DC) --profile back stop; \
	else $(DC) stop laravel-app minerva-nginx minerva-db minerva-redis laravel-worker; fi

back-logs: ## Logs del Backend
	@if [ "$(DEV)" = "0" ]; then $(DC) --profile back logs -f; \
	else $(DC) logs -f laravel-app minerva-nginx minerva-db minerva-redis laravel-worker; fi

ia-up: ## Levantar solo IA (ASR + Diarizador)
	@if [ "$(DEV)" = "0" ]; then $(DC) --profile ia up -d; \
	else $(DC) up -d minerva-asr minerva-diarizador; fi

ia-down: ## Bajar solo IA
	@if [ "$(DEV)" = "0" ]; then $(DC) --profile ia stop; \
	else $(DC) stop minerva-asr minerva-diarizador; fi

ia-logs: ## Logs de IA
	@if [ "$(DEV)" = "0" ]; then $(DC) --profile ia logs -f; \
	else $(DC) logs -f minerva-asr minerva-diarizador; fi

# ==============================================================================
# LARAVEL - Base de datos y mantenimiento
# ==============================================================================
migrate: ## Ejecutar migraciones
	@$(DC) exec -T laravel-app php artisan migrate --force

seed: ## Ejecutar seeders
	@$(DC) exec -T laravel-app php artisan db:seed

migrate-fresh: ## Recrear BD desde cero (DESTRUCTIVO)
	@echo "$(ROJO)[WARN] Esto eliminará TODOS los datos. Ctrl+C para cancelar.$(RESET)"
	@sleep 3
	@$(DC) exec -T laravel-app php artisan migrate:fresh --seed

permisos: ## Corregir permisos de storage
	@$(DC) exec -T laravel-app chown -R www-data:www-data storage bootstrap/cache
	@$(DC) exec -T laravel-app chmod -R 775 storage bootstrap/cache
	@echo "$(VERDE)[OK] Permisos corregidos.$(RESET)"

build-assets: ## Compilar assets frontend (solo DEV)
	@cd Backend && npm install && npm run build

# ==============================================================================
# ACCESO A CONTENEDORES
# ==============================================================================
shell-backend: ## Abrir shell en Laravel
	@$(DC) exec laravel-app bash

shell-frontend: ## Abrir shell en Angular
	@$(DC) exec minerva-frontend sh

shell-db: ## Abrir consola MariaDB
	@$(DC) exec minerva-db mariadb -u$(DB_USERNAME) -p$(DB_PASSWORD) $(DB_DATABASE)

# ==============================================================================
# COLAS Y WORKERS
# ==============================================================================
cola-estado: ## Ver estado de la cola de procesamiento
	@$(DC) exec -T laravel-app php artisan queue:monitor redis:process_audio,redis:default

cola-limpiar: ## Limpiar jobs fallidos
	@$(DC) exec -T laravel-app php artisan queue:flush
	@echo "$(VERDE)[OK] Jobs fallidos limpiados.$(RESET)"

scale-workers: ## Escalar workers (make scale-workers N=3)
	@$(DC) up -d --scale laravel-worker=$${N:-2} laravel-worker
	@echo "$(VERDE)[OK] Workers escalados a $${N:-2}.$(RESET)"

worker-logs: ## Ver logs del worker
	@$(DC) logs -f laravel-worker

sse-logs: ## Ver logs de SSE/callbacks
	@$(DC) exec -T laravel-app tail -f storage/logs/laravel.log | grep -i "sse\|callback\|transcripci"

# ==============================================================================
# TESTING
# ==============================================================================
test: test-backend ## Ejecutar todos los tests

test-backend: ## Ejecutar tests Laravel (Pest)
	@echo "$(AMARILLO)Ejecutando tests...$(RESET)"
	@$(DC) exec -T laravel-app php artisan test

test-e2e: ## Ejecutar tests E2E (Playwright)
	@cd e2e && npx playwright test --reporter=list

# ==============================================================================
# LIMPIEZA
# ==============================================================================
clean: ## Limpiar todo (contenedores + volúmenes + imágenes)
	@echo "$(ROJO)[WARN] Esto eliminará TODOS los contenedores, volúmenes e imágenes de Minerva.$(RESET)"
	@echo "$(ROJO)[WARN] Ctrl+C en 5s para cancelar...$(RESET)"
	@sleep 5
	@$(MAKE) --no-print-directory down
	@docker volume ls -q | grep minerva | xargs -r docker volume rm 2>/dev/null || true
	@docker image ls -q --filter=reference='minerva-*' | xargs -r docker image rm 2>/dev/null || true
	@echo "$(VERDE)[OK] Limpieza completada.$(RESET)"
