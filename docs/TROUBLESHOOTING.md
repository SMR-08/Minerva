# Troubleshooting — Minerva

## Ver logs y estado

```bash
# Estado
DEV=0 make status

# Logs (todo)
DEV=0 make logs

# Logs por componente
DEV=0 make front-logs
DEV=0 make back-logs
DEV=0 make ia-logs

# Uso de recursos
docker stats --no-stream
```

## Puerto del gateway (9122) no accesible desde fuera

Si desde el servidor funciona (`curl http://localhost:9122/health`) pero desde fuera hay timeout, probablemente es firewall.

### UFW (Ubuntu/Debian)

```bash
sudo ufw status
sudo ufw allow 9122/tcp
sudo ufw status numbered
```

### firewalld (RHEL/CentOS)

```bash
sudo firewall-cmd --state
sudo firewall-cmd --permanent --add-port=9122/tcp
sudo firewall-cmd --reload
sudo firewall-cmd --list-ports | grep 9122
```

### iptables

```bash
sudo iptables -I INPUT -p tcp --dport 9122 -j ACCEPT
sudo iptables -L INPUT -n | grep 9122

# Guardado (según distro)
sudo netfilter-persistent save
```

### Verificación

```bash
curl -I http://<IP_PUBLICA>:9122/health
curl -s http://<IP_PUBLICA>:9122/ | head -20
```

## Health checks

- Gateway: `GET /health`
- IA: `GET /estado` (ASR y diarizador)

Diagnóstico rápido:

```bash
DEV=0 make status

# Gateway
docker logs --tail 200 minerva-gateway

# Backend
docker logs --tail 200 minerva-app
docker logs --tail 200 minerva-nginx

# IA
docker logs --tail 200 minerva-asr
docker logs --tail 200 minerva-diarizador
```

## Problemas típicos

### El gateway no enruta (multi-servidor)

Revisa variables en `.env` (servidor WEB):

- `FRONTEND_UPSTREAM`
- `BACKEND_UPSTREAM`

Y valida la config:

```bash
docker exec -it minerva-gateway nginx -t
```

### GPU no detectada en IA

```bash
nvidia-smi
nvidia-ctk --version

# (si aplica)
sudo nvidia-ctk runtime configure --runtime=docker
sudo systemctl restart docker
```

### CORS

- Ajusta `CORS_ALLOWED_ORIGINS` en `.env`.
- El CORS “real” está en Laravel, el del gateway es solo respaldo.
