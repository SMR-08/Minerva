# ==============================================================================
# Variables
# ==============================================================================

variable "aws_region" {
  description = "Region AWS (Academy Lab usa us-east-1)"
  type        = string
  default     = "us-east-1"
}

variable "project_name" {
  description = "Nombre del proyecto (usado en tags y nombres)"
  type        = string
  default     = "minerva"
}

# --- Compute ---

variable "instance_type" {
  description = "Tipo de instancia EC2 (escalado vertical: cambiar este valor)"
  type        = string
  default     = "t3.medium" # 2 vCPU, 4GB — balance coste/rendimiento
}

variable "key_name" {
  description = "Key pair para SSH (Academy Lab pre-crea 'vockey')"
  type        = string
  default     = "vockey"
}

variable "volume_size" {
  description = "Tamano del disco raiz en GB"
  type        = number
  default     = 25
}

# --- Scaling (horizontal) ---

variable "asg_min" {
  description = "Minimo de instancias en el ASG"
  type        = number
  default     = 1
}

variable "asg_desired" {
  description = "Instancias deseadas en el ASG"
  type        = number
  default     = 1
}

variable "asg_max" {
  description = "Maximo de instancias en el ASG (escalado horizontal)"
  type        = number
  default     = 3
}

variable "scale_up_cpu_threshold" {
  description = "Porcentaje de CPU para escalar hacia arriba"
  type        = number
  default     = 70
}

variable "scale_down_cpu_threshold" {
  description = "Porcentaje de CPU para escalar hacia abajo"
  type        = number
  default     = 30
}

# --- Red ---

variable "allowed_ssh_cidr" {
  description = "CIDR permitido para SSH (restringir a tu IP en produccion)"
  type        = string
  default     = "0.0.0.0/0" # En Lab es aceptable, en prod usar IP fija
}

variable "ia_server_cidr" {
  description = "CIDRs del servidor IA (UMA) para acceso a Redis (cola unificada)"
  type        = list(string)
  default     = ["150.214.52.0/24", "150.214.40.119/32"]
}

variable "app_port" {
  description = "Puerto del gateway Nginx en las instancias"
  type        = number
  default     = 9122
}

# --- Aplicacion ---

variable "github_repo" {
  description = "URL del repositorio para clonar en las instancias"
  type        = string
  default     = "https://github.com/SMR-08/Minerva.git"
}

variable "github_branch" {
  description = "Branch a desplegar"
  type        = string
  default     = "main"
}

# --- Dominio ---

variable "domain_name" {
  description = "Dominio personalizado para HTTPS (ej: minerva.mayger.uk). Dejar vacio para solo HTTP."
  type        = string
  default     = ""
}
