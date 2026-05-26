# ==============================================================================
# Minerva — Terraform Infrastructure
# ==============================================================================
# Adaptado a AWS Academy Lab (credenciales temporales, $50 budget)
# Requisito TFG: escalado horizontal (ASG) + vertical (instance_type variable)
#
# Uso:
#   cd terraform/
#   terraform init
#   terraform plan
#   terraform apply
#
# Las credenciales del Lab se configuran via variables de entorno:
#   export AWS_ACCESS_KEY_ID="..."
#   export AWS_SECRET_ACCESS_KEY="..."
#   export AWS_SESSION_TOKEN="..."
# ==============================================================================

terraform {
  required_version = ">= 1.5.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.0"
    }
  }

  # Backend local — AWS Academy no garantiza acceso a S3 para state
  backend "local" {
    path = "terraform.tfstate"
  }
}

provider "aws" {
  region = var.aws_region
}

# Contraseña Redis generada automáticamente
resource "random_password" "redis" {
  length  = 32
  special = false # Redis no maneja bien caracteres especiales en URL
}

output "redis_password" {
  description = "Contraseña Redis (usar en .env como REDIS_PASSWORD)"
  value       = random_password.redis.result
  sensitive   = true
}
