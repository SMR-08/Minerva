# ==============================================================================
# IA GPU — Instancia con GPU para microservicios de IA (condicional)
# ==============================================================================
# Se crea SOLO si enable_ia_gpu = true.
# Levanta una instancia con GPU, instala Docker + NVIDIA toolkit,
# clona el repo, y levanta los servicios IA conectados al Redis de la app.
#
# Uso:
#   terraform apply -var="enable_ia_gpu=true"
#   terraform apply -var="enable_ia_gpu=true" -var="ia_instance_type=g5.xlarge"
# ==============================================================================

# --- Locals: determinar qué CIDRs abrir para Redis ---
locals {
  # Si enable_ia_gpu=true, el SG se abre via aws_security_group_rule (abajo).
  # Si no, usar ia_server_cidr (IP externa configurada manualmente).
  ia_cidr_blocks = var.enable_ia_gpu ? [] : var.ia_server_cidr
}

# --- AMI: Deep Learning Base (NVIDIA drivers preinstalados) ---
data "aws_ami" "deep_learning" {
  count       = var.enable_ia_gpu ? 1 : 0
  most_recent = true
  owners      = ["amazon"]

  filter {
    name   = "name"
    values = ["Deep Learning Base OSS Nvidia Driver GPU AMI (Ubuntu 22.04)*"]
  }

  filter {
    name   = "architecture"
    values = ["x86_64"]
  }
}

# --- Security Group para instancia IA ---
resource "aws_security_group" "ia" {
  count       = var.enable_ia_gpu ? 1 : 0
  name        = "${var.project_name}-ia-sg"
  description = "Servidor IA GPU: SSH + salida total"
  vpc_id      = data.aws_vpc.default.id

  ingress {
    description = "SSH"
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = [var.allowed_ssh_cidr]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = { Name = "${var.project_name}-ia-sg" }
}

# --- Instancia EC2 con GPU ---
resource "aws_instance" "ia" {
  count                  = var.enable_ia_gpu ? 1 : 0
  ami                    = data.aws_ami.deep_learning[0].id
  instance_type          = var.ia_instance_type
  key_name               = var.key_name
  vpc_security_group_ids = [aws_security_group.ia[0].id]

  associate_public_ip_address = true

  root_block_device {
    volume_size = var.ia_volume_size
    volume_type = "gp3"
  }

  user_data = base64encode(templatefile("${path.module}/scripts/ia-user-data.sh", {
    github_repo        = var.github_repo
    github_branch      = var.github_branch
    redis_password     = random_password.redis.result
    app_url            = var.domain_name != "" ? "https://${var.domain_name}" : "http://APP_IP_PENDING"
    ia_callback_secret = random_password.redis.result
  }))

  tags = {
    Name    = "${var.project_name}-ia-gpu"
    Project = var.project_name
  }
}

# --- Abrir Redis en el SG de la app para la instancia IA (automático) ---
resource "aws_security_group_rule" "ia_to_redis" {
  count             = var.enable_ia_gpu ? 1 : 0
  type              = "ingress"
  from_port         = 6379
  to_port           = 6379
  protocol          = "tcp"
  cidr_blocks       = ["${aws_instance.ia[0].public_ip}/32"]
  security_group_id = aws_security_group.app.id
  description       = "Redis desde instancia IA GPU"
}
