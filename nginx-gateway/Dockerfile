FROM nginx:alpine

LABEL maintainer="mmaygir1509@g.educaan.es"
LABEL description="Nginx Gateway - Reverse proxy unificado para Minerva"

# curl: health checks
# gettext: envsubst para templating de nginx.conf en runtime
RUN apk add --no-cache curl gettext

# Copiar plantilla de configuración (se renderiza en CMD)
COPY nginx.conf /etc/nginx/nginx.conf.template

# Crear directorio para logs
RUN mkdir -p /var/log/nginx

# Exponer puerto 9122
EXPOSE 9122

CMD ["sh", "-c", "envsubst < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf && nginx -g 'daemon off;'"]
