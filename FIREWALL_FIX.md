# Solución: Abrir Puerto 9122 en el Firewall

## Problema
- Desde el servidor (localhost): ✅ Todo funciona
- Desde fuera (150.214.56.73:9122): ❌ Conexión bloqueada/timeout
- Ping funciona: ✅ (ICMP permitido)

## Causa
El firewall del servidor está bloqueando el puerto 9122.

## Solución

### Opción 1: UFW (Ubuntu Firewall)

```bash
# Verificar si UFW está activo
sudo ufw status

# Abrir puerto 9122
sudo ufw allow 9122/tcp

# Verificar que se agregó la regla
sudo ufw status numbered
```

### Opción 2: iptables (Firewall directo)

```bash
# Ver reglas actuales
sudo iptables -L -n -v

# Abrir puerto 9122
sudo iptables -I INPUT -p tcp --dport 9122 -j ACCEPT

# Guardar reglas (Ubuntu/Debian)
sudo netfilter-persistent save

# O en CentOS/RHEL
sudo service iptables save
```

### Opción 3: firewalld (CentOS/RHEL)

```bash
# Verificar si firewalld está activo
sudo firewall-cmd --state

# Abrir puerto 9122
sudo firewall-cmd --permanent --add-port=9122/tcp
sudo firewall-cmd --reload

# Verificar
sudo firewall-cmd --list-ports
```

## Verificación

Después de abrir el puerto, prueba desde tu máquina local:

```bash
# Debe responder en menos de 1 segundo
curl -I http://150.214.56.73:9122/health

# Debe mostrar el HTML de Angular
curl -s http://150.214.56.73:9122/ | head -10
```

## Puertos que Necesitas Abrir

Para Minerva en producción:
- **9122** (Gateway principal) - OBLIGATORIO
- **8002** (ASR - opcional, solo si necesitas acceso directo externo)

Los demás servicios están en red interna y no necesitan exposición externa.

## Troubleshooting

### Si sigue sin funcionar:

```bash
# 1. Verificar que Docker está escuchando en todas las interfaces
netstat -tlnp | grep 9122
# Debe mostrar: 0.0.0.0:9122 (no 127.0.0.1:9122)

# 2. Verificar reglas de iptables de Docker
sudo iptables -L DOCKER -n -v

# 3. Verificar logs del gateway en tiempo real
docker logs -f minerva-gateway

# 4. Probar desde el servidor hacia sí mismo usando la IP pública
curl -I http://150.214.56.73:9122/health
```

### Si el servidor está detrás de un router/NAT:

Es posible que necesites configurar port forwarding en el router:
- Puerto externo: 9122
- Puerto interno: 9122
- IP destino: 150.214.56.73

## Comandos Rápidos

```bash
# Abrir puerto (elige el comando según tu sistema)
sudo ufw allow 9122/tcp                                    # Ubuntu/Debian con UFW
sudo iptables -I INPUT -p tcp --dport 9122 -j ACCEPT      # iptables directo
sudo firewall-cmd --permanent --add-port=9122/tcp         # CentOS/RHEL

# Verificar desde tu máquina local
curl -I http://150.214.56.73:9122/health
```
