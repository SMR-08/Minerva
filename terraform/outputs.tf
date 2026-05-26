# ==============================================================================
# Outputs
# ==============================================================================

output "alb_dns" {
  description = "URL publica del Load Balancer (punto de entrada)"
  value       = "http://${aws_lb.main.dns_name}"
}

output "asg_name" {
  description = "Nombre del Auto Scaling Group"
  value       = aws_autoscaling_group.app.name
}

output "instance_type" {
  description = "Tipo de instancia actual (escalado vertical)"
  value       = var.instance_type
}

output "scaling_config" {
  description = "Configuracion de escalado horizontal"
  value       = "min=${var.asg_min} desired=${var.asg_desired} max=${var.asg_max}"
}

output "instance_ips" {
  description = "IPs publicas de las instancias del ASG"
  value       = "Consultar: aws ec2 describe-instances --filters Name=tag:Name,Values=${var.project_name}-app Name=instance-state-name,Values=running --query Reservations[].Instances[].PublicIpAddress --output text"
}

output "ssh_command" {
  description = "Comando SSH para conectar a una instancia"
  value       = "ssh -i ~/.ssh/labsuser.pem ubuntu@<IP>"
}

output "acm_validation_records" {
  description = "Registros DNS para validar el certificado ACM (crear en Cloudflare como CNAME, proxy OFF)"
  value = var.domain_name != "" ? {
    for dvo in aws_acm_certificate.main[0].domain_validation_options : dvo.domain_name => {
      type  = dvo.resource_record_type
      name  = dvo.resource_record_name
      value = dvo.resource_record_value
    }
  } : {}
}

output "app_url" {
  description = "URL de la aplicacion"
  value       = var.domain_name != "" ? "https://${var.domain_name}" : "http://${aws_lb.main.dns_name}"
}
