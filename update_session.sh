#!/bin/bash

# crear_sesion_update.sh

# Buscar todos los archivos PHP que contengan session_start()
echo "Buscando archivos con session_start()..."

# Crear backup primero
echo "Creando backups..."
find . -name "*.php" -exec grep -l "session_start()" {} \; | while read file; do
    cp "$file" "$file.backup"
    echo "Backup creado: $file.backup"
done

# Actualizar archivos
echo -e "\nActualizando archivos..."
find . -name "*.php" -exec grep -l "session_start()" {} \; | while read file; do
    # Usar sed para insertar la línea después de session_start();
    sed -i '' '/session_start();/a\
// Sesión de 15 días\
setcookie(session_name(), session_id(), time() + 12960000, "/");
' "$file"
    echo "Actualizado: $file"
done

echo -e "\n✅ ¡Proceso completado!"
