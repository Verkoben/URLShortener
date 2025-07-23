# Crear directorio para PHPMailer
cd /var/www/html
sudo mkdir -p libraries/PHPMailer
cd libraries/PHPMailer

# Descargar PHPMailer
sudo wget https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.8.1.zip
sudo unzip v6.8.1.zip
sudo mv PHPMailer-6.8.1/src/* .
sudo rm -rf PHPMailer-6.8.1 v6.8.1.zip

# Dar permisos
sudo chown -R www-data:www-data /var/www/html/libraries