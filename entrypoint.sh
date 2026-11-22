#!/bin/sh
set -e

# Limpiar módulos MPM conflictivos
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*

# Configurar el puerto dinámico asignado por Render o Railway (o 80 por defecto)
PORT_TO_USE=${PORT:-80}
sed -i "s/80/${PORT_TO_USE}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Iniciar Apache
exec apache2-foreground