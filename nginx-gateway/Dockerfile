FROM nginx:alpine

LABEL maintainer="mmaygir1509@g.educaan.es"
LABEL description="Nginx Gateway - Reverse proxy unificado para Minerva"

# Instalar curl para health checks
RUN apk add --no-cache curl

# Copiar configuración personalizada
COPY nginx.conf /etc/nginx/nginx.conf

# Crear directorio para logs
RUN mkdir -p /var/log/nginx

# Exponer puerto 9122
EXPOSE 9122

CMD ["nginx", "-g", "daemon off;"]
