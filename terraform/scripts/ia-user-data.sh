#!/bin/bash
# ==============================================================================
# Minerva IA — User Data (instancia GPU)
# ==============================================================================
# Se ejecuta al crear la instancia. Instala Docker + NVIDIA toolkit,
# clona el repo, configura .env y levanta los microservicios IA.
# ==============================================================================
set -e
exec > /var/log/minerva-ia-setup.log 2>&1

echo "=== Minerva IA Setup — $(date) ==="

# --- Instalar Docker ---
apt-get update -y
apt-get install -y docker.io docker-compose-v2 curl git

# --- NVIDIA Container Toolkit ---
curl -fsSL https://nvidia.github.io/libnvidia-container/gpgkey | \
  gpg --dearmor -o /usr/share/keyrings/nvidia-container-toolkit-keyring.gpg
curl -s -L https://nvidia.github.io/libnvidia-container/stable/deb/nvidia-container-toolkit.list | \
  sed 's#deb https://#deb [signed-by=/usr/share/keyrings/nvidia-container-toolkit-keyring.gpg] https://#g' | \
  tee /etc/apt/sources.list.d/nvidia-container-toolkit.list
apt-get update -y
apt-get install -y nvidia-container-toolkit
nvidia-ctk runtime configure --runtime=docker
systemctl enable docker && systemctl restart docker

# --- Clonar repositorio ---
cd /home/ubuntu
git clone -b ${github_branch} ${github_repo} Minerva
cd Minerva

# --- Detectar IP de la app (Redis) ---
# La instancia de la app está en el mismo VPC. Buscar por tag.
APP_IP=$(curl -s http://169.254.169.254/latest/meta-data/local-ipv4 | \
  sed 's/\.[0-9]*$/.*/') # Fallback: misma subnet
# Intentar resolver via metadata del ASG (mismo VPC = IP privada accesible)
TOKEN=$(curl -s -X PUT "http://169.254.169.254/latest/api/token" \
  -H "X-aws-ec2-metadata-token-ttl-seconds: 21600")
REGION=$(curl -s -H "X-aws-ec2-metadata-token: $TOKEN" \
  http://169.254.169.254/latest/meta-data/placement/region)

# Buscar instancia de la app por tag
APP_PRIVATE_IP=$(aws ec2 describe-instances \
  --region "$REGION" \
  --filters "Name=tag:Name,Values=minerva-app" "Name=instance-state-name,Values=running" \
  --query "Reservations[0].Instances[0].PrivateIpAddress" \
  --output text 2>/dev/null || echo "")

if [ -z "$APP_PRIVATE_IP" ] || [ "$APP_PRIVATE_IP" = "None" ]; then
  echo "WARN: No se encontró instancia app por tag. Usando variable."
  APP_PRIVATE_IP="REDIS_HOST_NOT_FOUND"
fi

echo "App Redis IP: $APP_PRIVATE_IP"

# --- Crear .env ---
cat > .env <<ENVFILE
REDIS_URL=redis://:${redis_password}@$APP_PRIVATE_IP:6379/0
LARAVEL_URL=${app_url}
IA_CALLBACK_SECRET=${ia_callback_secret}
GPU_MODE=compact
GPU_CONCURRENCY=1
AUTO_SUMMARY=true
SUMMARY_MODEL=Qwen/Qwen3.5-0.8B-Instruct
DISPOSITIVO_ASR=cuda:0
RUTA_TEMPORAL=/app/compartido/entrada
ENVFILE

# --- Levantar servicios IA ---
docker compose -f docker-compose.production.yml --profile ia up -d

echo "=== Minerva IA Setup COMPLETE — $(date) ==="
echo "Redis: $APP_PRIVATE_IP:6379"
echo "Callbacks: ${app_url}"
