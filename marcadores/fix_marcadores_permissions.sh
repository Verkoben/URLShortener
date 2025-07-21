#!/bin/bash

# fix_marcadores_permissions.sh - Arreglar permisos de marcadores
# Uso: ./fix_marcadores_permissions.sh

echo "🔧 Arreglando permisos de marcadores..."

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Variables
MARCADORES_PATH="/var/www/html/marcadores"
WEB_USER="www-data"
WEB_GROUP="www-data"

# Función para mostrar mensajes
log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Verificar si se ejecuta como root
if [ "$EUID" -ne 0 ]; then
    log_error "Este script debe ejecutarse como root"
    echo "Uso: sudo ./fix_marcadores_permissions.sh"
    exit 1
fi

# Verificar que existe la carpeta marcadores
if [ ! -d "$MARCADORES_PATH" ]; then
    log_error "La carpeta $MARCADORES_PATH no existe"
    exit 1
fi

log_info "Iniciando arreglo de permisos en $MARCADORES_PATH"

# 1. Cambiar ownership a www-data
log_info "Cambiando ownership a $WEB_USER:$WEB_GROUP..."
chown -R $WEB_USER:$WEB_GROUP $MARCADORES_PATH
if [ $? -eq 0 ]; then
    log_success "Ownership cambiado correctamente"
else
    log_error "Error cambiando ownership"
    exit 1
fi

# 2. Permisos para directorios (755 = rwxr-xr-x)
log_info "Configurando permisos de directorios (755)..."
find $MARCADORES_PATH -type d -exec chmod 755 {} \;
if [ $? -eq 0 ]; then
    log_success "Permisos de directorios configurados"
else
    log_warning "Algunos directorios no se pudieron modificar"
fi

# 3. Permisos para archivos PHP y HTML (644 = rw-r--r--)
log_info "Configurando permisos de archivos .php y .html (644)..."
find $MARCADORES_PATH -name "*.php" -exec chmod 644 {} \;
find $MARCADORES_PATH -name "*.html" -exec chmod 644 {} \;
find $MARCADORES_PATH -name "*.htm" -exec chmod 644 {} \;
log_success "Permisos de archivos web configurados"

# 4. Permisos para archivos de configuración (600 = rw-------)
log_info "Configurando permisos de archivos de configuración (600)..."
if [ -f "$MARCADORES_PATH/config.php" ]; then
    chmod 600 $MARCADORES_PATH/config.php
    log_success "config.php configurado como 600"
fi

# 5. Permisos para archivos temporales y cache (666 = rw-rw-rw-)
log_info "Configurando permisos de archivos temporales..."
find $MARCADORES_PATH -name "*.tmp" -exec chmod 666 {} \; 2>/dev/null
find $MARCADORES_PATH -name "*.cache" -exec chmod 666 {} \; 2>/dev/null
find $MARCADORES_PATH -name "*.flag" -exec chmod 666 {} \; 2>/dev/null
find $MARCADORES_PATH -name "geo_cache_*" -exec chmod 666 {} \; 2>/dev/null
log_success "Permisos de archivos temporales configurados"

# 6. Crear/verificar directorios necesarios
log_info "Verificando directorios necesarios..."

# Directorio para cache
if [ ! -d "$MARCADORES_PATH/cache" ]; then
    mkdir -p $MARCADORES_PATH/cache
    chown $WEB_USER:$WEB_GROUP $MARCADORES_PATH/cache
    chmod 755 $MARCADORES_PATH/cache
    log_success "Directorio cache creado"
fi

# Directorio para logs
if [ ! -d "$MARCADORES_PATH/logs" ]; then
    mkdir -p $MARCADORES_PATH/logs
    chown $WEB_USER:$WEB_GROUP $MARCADORES_PATH/logs
    chmod 755 $MARCADORES_PATH/logs
    log_success "Directorio logs creado"
fi

# Directorio para uploads si existe
if [ -d "$MARCADORES_PATH/uploads" ]; then
    chmod 755 $MARCADORES_PATH/uploads
    log_success "Directorio uploads configurado"
fi

# 7. Archivos especiales con permisos específicos
log_info "Configurando archivos especiales..."

# Archivo de tracking disabled (si existe)
if [ -f "$MARCADORES_PATH/tracking_disabled.flag" ]; then
    chmod 666 $MARCADORES_PATH/tracking_disabled.flag
    log_success "tracking_disabled.flag configurado"
fi

# Archivos JavaScript y CSS (644)
find $MARCADORES_PATH -name "*.js" -exec chmod 644 {} \; 2>/dev/null
find $MARCADORES_PATH -name "*.css" -exec chmod 644 {} \; 2>/dev/null

# Archivos JSON (644)
find $MARCADORES_PATH -name "*.json" -exec chmod 644 {} \; 2>/dev/null

log_success "Archivos especiales configurados"

# 8. Verificar permisos de Apache
log_info "Verificando configuración de Apache..."

# Verificar que www-data puede acceder
if sudo -u $WEB_USER test -r $MARCADORES_PATH; then
    log_success "www-data puede leer el directorio"
else
    log_warning "www-data no puede leer el directorio"
fi

if sudo -u $WEB_USER test -w $MARCADORES_PATH; then
    log_success "www-data puede escribir en el directorio"
else
    log_warning "www-data no puede escribir en el directorio"
fi

# 9. Mostrar resumen de permisos
echo ""
log_info "=== RESUMEN DE PERMISOS ==="
echo ""
echo "📁 Directorios: 755 (rwxr-xr-x)"
echo "📄 Archivos PHP/HTML: 644 (rw-r--r--)"
echo "🔒 Config files: 600 (rw-------)"  
echo "💾 Archivos temporales: 666 (rw-rw-rw-)"
echo "👤 Owner: $WEB_USER:$WEB_GROUP"
echo ""

# 10. Verificar archivos críticos
log_info "Verificando archivos críticos..."

critical_files=("config.php" "functions.php" "analytics.php" "api.php" "index.php")

for file in "${critical_files[@]}"; do
    if [ -f "$MARCADORES_PATH/$file" ]; then
        perm=$(stat -c "%a" "$MARCADORES_PATH/$file")
        owner=$(stat -c "%U:%G" "$MARCADORES_PATH/$file")
        echo "📄 $file: $perm ($owner)"
    else
        log_warning "$file no encontrado"
    fi
done

echo ""

# 11. Test final
log_info "Realizando test final..."

# Test de escritura
test_file="$MARCADORES_PATH/test_permissions_$(date +%s).tmp"
if sudo -u $WEB_USER touch $test_file 2>/dev/null; then
    sudo -u $WEB_USER rm $test_file
    log_success "Test de escritura PASSED"
else
    log_error "Test de escritura FAILED"
fi

# Test de lectura  
if sudo -u $WEB_USER test -r "$MARCADORES_PATH/index.php" 2>/dev/null; then
    log_success "Test de lectura PASSED"
else
    log_error "Test de lectura FAILED"
fi

echo ""
log_success "¡Permisos de marcadores arreglados correctamente!"
echo ""
log_info "Para aplicar cambios, puedes reiniciar Apache:"
echo "sudo systemctl restart apache2"
echo ""
log_info "Para verificar que todo funciona:"
echo "https://0ln.eu/marcadores/"
echo ""
