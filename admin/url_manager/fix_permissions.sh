# Conectar al servidor
ssh usuario@clancy.es

# Ir al directorio web
cd /var/www/html
# o
cd /home/usuario/public_html

# Verificar permisos
ls -la url-manager/

# Corregir permisos
chmod 755 url-manager/
chmod 644 url-manager/*
chmod 755 url-manager/  # El directorio debe ser 755
