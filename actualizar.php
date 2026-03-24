<?php

/**
 * Script de Automatización de Despliegue y Mantenimiento para FinTrack
 * Uso: php actualizar.php
 * 
 * NOTA: Si modificas el .env manualmente y la web da un 500, ejecuta esto.
 * Este script se encargará de borrar cualquier caché corrupta y arreglar permisos.
 */

function ejecutar($comando) {
    echo "\n\033[32m[EJECUTANDO]\033[0m: $comando\n";
    passthru($comando . ' 2>&1', $resultado);
    if ($resultado !== 0) {
        echo "\033[31m[ADVERTENCIA] El comando retornó el código: $resultado\033[0m\n";
    }
}

echo "\n\033[34m====================================================\033[0m\n";
echo "\033[34m    INICIANDO ACTUALIZACIÓN - FINTRACK              \033[0m\n";
echo "\033[34m====================================================\033[0m\n";

// 1. Mantenimiento
echo "\n\033[33mEntrando en modo mantenimiento...\033[0m\n";
ejecutar('php artisan down');

// 2. Git pull
echo "\n\033[33mObteniendo últimos cambios de Git...\033[0m\n";
ejecutar('git pull origin main');

// 3. Composer install
echo "\n\033[33mInstalando dependencias de PHP...\033[0m\n";
ejecutar('composer install --no-interaction --prefer-dist --optimize-autoloader');

// 4. Migraciones
echo "\n\033[33mEjecutando migraciones de base de datos...\033[0m\n";
ejecutar('php artisan migrate --force');

// 5. Dependencias JS
echo "\n\033[33mInstalando dependencias de NPM...\033[0m\n";
ejecutar('npm install');

// 6. Construir Assets
echo "\n\033[33mConstruyendo assets para producción...\033[0m\n";
ejecutar('npm run build');

// 7. Optimizar y Limpiar Caché (CRÍTICO DESPUÉS DE CAMBIAR .ENV)
echo "\n\033[33mLimpiando y optimizando caché...\033[0m\n";
ejecutar('php artisan optimize:clear');

// 7.5. Enlace de Almacenamiento (Para evitar error 404 en imágenes /storage)
echo "\n\033[33mGenerando enlaces de almacenamiento si no existen...\033[0m\n";
ejecutar('php artisan storage:link');

// 8. Arreglar permisos (Para evitar errores 500 en el VPS)
// Si ejecutas esto en Windows local y falla, no pasa nada, continuará.
if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
    echo "\n\033[33mAplicando permisos seguros a carpetas críticas (puede requerir contraseña sudo)...\033[0m\n";
    ejecutar('sudo chown -R $USER:www-data storage bootstrap/cache database');
    ejecutar('sudo find storage bootstrap/cache database -type f -exec chmod 664 {} \;');
    ejecutar('sudo find storage bootstrap/cache database -type d -exec chmod 775 {} \;');
}

// 9. Salir de mantenimiento
echo "\n\033[33mSaliendo del modo mantenimiento...\033[0m\n";
ejecutar('php artisan up');

echo "\n\033[34m====================================================\033[0m\n";
echo "\033[32m       ¡ACTUALIZACIÓN COMPLETADA CON ÉXITO!        \033[0m\n";
echo "\033[34m====================================================\033[0m\n\n";
