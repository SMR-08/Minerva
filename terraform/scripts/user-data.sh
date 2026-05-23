#!/bin/bash
# ==============================================================================
# Minerva — User Data (bootstrap de instancia EC2)
# ==============================================================================
set -euo pipefail

exec > /var/log/minerva-setup.log 2>&1
echo "[$(date)] Iniciando setup de Minerva..."

# --- Instalar dependencias ---
apt-get update -qq
apt-get install -y -qq docker.io docker-compose-v2 git make curl

# Habilitar Docker
systemctl enable --now docker
usermod -aG docker ubuntu

# --- Clonar repositorio ---
cd /home/ubuntu
if [ ! -d "Minerva" ]; then
  git clone --branch ${github_branch} ${github_repo} Minerva
fi
cd Minerva
chown -R ubuntu:ubuntu /home/ubuntu/Minerva

# --- Configurar entorno ---
if [ ! -f ".env" ]; then
  cp .env.production.example .env
  # Generar passwords seguros
  DB_PASS=$(openssl rand -base64 24 | tr -d '/+=' | head -c 32)
  DB_ROOT=$(openssl rand -base64 24 | tr -d '/+=' | head -c 32)
  CALLBACK_SECRET=$(openssl rand -base64 32)
  APP_KEY_VAL=$(openssl rand -base64 32)
  sed -i "s|CAMBIAR_CONTRASEÑA_SEGURA_AQUI|$DB_PASS|" .env
  sed -i "s|CAMBIAR_ROOT_PASSWORD_AQUI|$DB_ROOT|" .env
  sed -i "s|CAMBIAR_GENERAR_CON_OPENSSL_RAND_BASE64_32|$CALLBACK_SECRET|" .env
  sed -i "s|APP_KEY=|APP_KEY=base64:$APP_KEY_VAL|" .env
  # Obtener DNS publico via IMDSv2
  TOKEN=$(curl -sf -X PUT "http://169.254.169.254/latest/api/token" \
    -H "X-aws-ec2-metadata-token-ttl-seconds: 60")
  PUBLIC_DNS=$(curl -sf -H "X-aws-ec2-metadata-token: $TOKEN" \
    http://169.254.169.254/latest/meta-data/public-hostname || echo "localhost")
  sed -i "s|TU_IP_SERVIDOR|$PUBLIC_DNS|g" .env
  sed -i "s|^APP_URL=.*|APP_URL=http://$PUBLIC_DNS:${app_port}|" .env
fi

# --- Pull imagenes pre-construidas (si IMAGE_REGISTRY esta configurado) ---
source .env 2>/dev/null || true
if [ -n "$${IMAGE_REGISTRY:-}" ]; then
  echo "[$(date)] Descargando imagenes de $${IMAGE_REGISTRY}..."
  docker compose -f docker-compose.production.yml pull || true
fi

# --- Levantar servicios ---
export DEV=0
make init DEV=0 || true

# --- Marcar como listo ---
touch /tmp/setup_complete
echo "[$(date)] Setup completado. Minerva disponible en puerto ${app_port}"
