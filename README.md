# FinTrack - Control de Finanzas Personales

<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="Laravel Logo">
</p>

FinTrack es una aplicación web robusta construida con **Laravel** e **Inertia.js (Vue 3)** diseñada para simplificar el seguimiento de gastos, la gestión de tarjetas de crédito y el control de finanzas personales con una interfaz moderna y eficiente.

---

## 🚀 Requisitos del Sistema
Para ejecutar este proyecto, necesitarás tener instalado:
- **PHP 8.2+** (con extensiones: sqlite3, curl, xml, mbstring, zip)
- **Composer** (Gestor de dependencias de PHP)
- **Node.js 18+ & NPM** (Para los assets del frontend)
- **SQLite** (Base de datos predeterminada, ligera y sin configuración)

---

## 💻 Instalación Local (Windows / Linux)

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/Cristian31306/FinTrack.git
   cd fintrack
   ```

2. **Instalar dependencias de PHP:**
   ```bash
   composer install
   ```

3. **Instalar dependencias de Frontend:**
   ```bash
   npm install
   ```

4. **Configurar el entorno:**
   - Crea una copia del archivo de ejemplo:
     ```bash
     cp .env.example .env
     ```
   - Genera la clave única de la aplicación:
     ```bash
     php artisan key:generate
     ```

5. **Preparar la Base de Datos:**
   - Asegúrate de que el archivo existe (o créalo manualmente):
     ```bash
     # En Linux/Mac
     touch database/database.sqlite
     # En Windows (PowerShell)
     New-Item database/database.sqlite -ItemType File
     ```
   - Ejecuta las migraciones para crear las tablas:
     ```bash
     php artisan migrate --seed
     ```

6. **Compilar el Frontend:**
   ```bash
   npm run build
   ```

7. **Iniciar el servidor:**
   ```bash
   php artisan serve
   ```
   Accede a: `http://localhost:8000`

---

## 🌐 Despliegue en VPS (Ubuntu + Nginx)

### 1. Instalación de Dependencias
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install php8.2-fpm php8.2-sqlite3 php8.2-curl php8.2-xml php8.2-mbstring php8.2-zip unzip nginx nodejs npm -y
```

### 2. Configuración de Nginx
Crea un archivo en `/etc/nginx/sites-available/fintrack`:
```nginx
server {
    listen 80;
    server_name tu-dominio.com;
    root /var/www/fintrack/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```
*Activa el sitio y reinicia Nginx:*
```bash
sudo ln -s /etc/nginx/sites-available/fintrack /etc/nginx/sites-enabled/
sudo systemctl restart nginx
```

### 3. Permisos Cruciales
El servidor web necesita permisos para escribir en el almacenamiento:
```bash
sudo chown -R www-data:www-data /var/www/fintrack
sudo chmod -R 775 /var/www/fintrack/storage
sudo chmod -R 775 /var/www/fintrack/bootstrap/cache
```

---

## 📧 Configuración de Correo (Brevo / SMTP)
Para que los correos de verificación funcionen correctamente, especialmente en Sudamérica, utiliza esta configuración en tu `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay-offshore-southamerica-east-v2.sendinblue.com
MAIL_PORT=587
MAIL_USERNAME=tu_usuario_brevo
MAIL_PASSWORD=tu_master_password_o_api_key
MAIL_FROM_ADDRESS="info@fintrack.algorah.bond"
```

**Importante:** Cada vez que modifiques el `.env`, limpia la caché de configuración:
```bash
php artisan config:clear
```

---

## 🛠 Comandos de Diagnóstico
He incluido herramientas personalizadas para verificar que todo esté en orden:

*   **Probar Conexión Email:**
    ```bash
    php artisan app:test-mail correo@ejemplo.com
    ```
*   **Probar Notificación de Verificación:**
    ```bash
    php artisan app:test-verification correo@ejemplo.com
    ```

---

## ⚖ Licencia
Este proyecto es software de código abierto bajo la licencia [MIT](https://opensource.org/licenses/MIT).
