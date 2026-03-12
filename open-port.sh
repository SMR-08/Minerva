#!/bin/bash
# Script para abrir el puerto 9122 en el firewall del servidor
# Ejecutar en el servidor: 150.214.56.73

echo "=== Abriendo puerto 9122 para Minerva ==="
echo ""

# Detectar qué firewall está activo
if command -v ufw &> /dev/null && sudo ufw status | grep -q "Status: active"; then
    echo "✓ Detectado UFW (Ubuntu Firewall)"
    sudo ufw allow 9122/tcp
    sudo ufw status | grep 9122
    
elif command -v firewall-cmd &> /dev/null && sudo firewall-cmd --state &> /dev/null; then
    echo "✓ Detectado firewalld (CentOS/RHEL)"
    sudo firewall-cmd --permanent --add-port=9122/tcp
    sudo firewall-cmd --reload
    sudo firewall-cmd --list-ports | grep 9122
    
else
    echo "✓ Usando iptables directo"
    sudo iptables -I INPUT -p tcp --dport 9122 -j ACCEPT
    sudo iptables -L INPUT -n | grep 9122
    
    # Intentar guardar las reglas
    if command -v netfilter-persistent &> /dev/null; then
        sudo netfilter-persistent save
    elif command -v iptables-save &> /dev/null; then
        sudo iptables-save > /etc/iptables/rules.v4 2>/dev/null || echo "Advertencia: No se pudieron guardar las reglas automáticamente"
    fi
fi

echo ""
echo "=== Verificando puerto ==="
netstat -tlnp | grep 9122 || ss -tlnp | grep 9122

echo ""
echo "=== Prueba desde el servidor ==="
curl -I http://150.214.56.73:9122/health

echo ""
echo "✓ Puerto 9122 abierto. Prueba desde tu máquina local:"
echo "  curl -I http://150.214.56.73:9122/health"
