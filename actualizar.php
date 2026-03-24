<?php

/**
 * Script de Automatización de Despliegue y Mantenimiento para FinTrack
 * Uso: php actualizar.php
 *
 * NOTAS CLAVE:
 * - El archivo .env en el VPS NUNCA debe ir en Git. Este script lo protege.
 * - Si modificas el .env manualmente y la web da un 500, ejecuta esto.
 * - Este script verifica que las variables de IA estén configuradas.
 */

function ejecutar($comando) {
    echo "\n\033[32m[EJECUTANDO]\033[0m: $comando\n";
    passthru($comando . ' 2>&1', $resultado);
    if ($resultado !== 0) {
        echo "\033[31m[ADVERTENCIA] El comando retornó el código: $resultado\033[0m\n";
    }
}

function leerEnv($path) {
    if (!file_exists($path)) return [];
    $vars = [];
    foreach (file($path) as $linea) {
        $linea = trim($linea);
        if (empty($linea) || str_starts_with($linea, '#')) continue;
        if (str_contains($linea, '=')) {
            [$clave, $valor] = explode('=', $linea, 2);
            $vars[trim($clave)] = trim($valor);
        }
    }
    return $vars;
}

function verificarVariableEnv($vars, $clave, $descripcion) {
    if (empty($vars[$clave]) || $vars[$clave] === 'tu-api-key-aqui' || $vars[$clave] === '') {
        echo "\033[31m[ERROR CRÍTICO]\033[0m La variable \033[33m$clave\033[0m no está configurada en .env\n";
        echo "  → $descripcion\n";
        return false;
    }
    echo "\033[32m  ✔ $clave\033[0m está configurada.\n";
    return true;
}

// ─── BANNER ───────────────────────────────────────────────────────────────────
echo "\n\033[34m╔══════════════════════════════════════════════════╗\033[0m\n";
echo "\033[34m║       ACTUALIZANDO FINTRACK - VPS DEPLOY         ║\033[0m\n";
echo "\033[34m╚══════════════════════════════════════════════════╝\033[0m\n";

$esWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$esVPS = !$esWindows;

// ─── PASO 0: Verificar .env antes de continuar ────────────────────────────────
echo "\n\033[33m[PASO 0] Verificando configuración del .env...\033[0m\n";
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    echo "\033[31m[CRÍTICO] No se encontró el archivo .env\033[0m\n";
    echo "  → Copia el .env.example a .env y configura las variables antes de continuar.\n";
    exit(1);
}
$env = leerEnv($envPath);
$ok = true;

// Variables esenciales para la plataforma
$ok &= verificarVariableEnv($env, 'APP_KEY', 'Ejecuta: php artisan key:generate');
$ok &= verificarVariableEnv($env, 'DB_CONNECTION', 'Debe estar configurada la conexión de BD (ej: sqlite o mysql)');

// Variables para la IA - Gemini
echo "\n\033[33m  [IA] Verificando credenciales de Gemini AI...\033[0m\n";
$ok &= verificarVariableEnv($env, 'GEMINI_API_KEY', 'Obtén tu API Key en: https://aistudio.google.com/app/apikey');

// Variables de modelo Gemini (si aplica)
if (!empty($env['GEMINI_MODEL'])) {
    echo "\033[32m  ✔ GEMINI_MODEL\033[0m definido como: {$env['GEMINI_MODEL']}\n";
} else {
    echo "\033[33m  ℹ GEMINI_MODEL\033[0m no está en .env (el código usará el modelo por defecto configurado en GeminiService.php)\n";
}

if (!$ok) {
    echo "\n\033[31mCORRIGE los errores del .env antes de continuar.\033[0m\n";
    exit(1);
}
echo "\n\033[32m  .env verificado correctamente.\033[0m\n";

// ─── PASO 1: Modo mantenimiento ───────────────────────────────────────────────
echo "\n\033[33m[PASO 1] Entrando en modo mantenimiento...\033[0m\n";
ejecutar('php artisan down');

// ─── PASO 2: Git pull ─────────────────────────────────────────────────────────
echo "\n\033[33m[PASO 2] Obteniendo últimos cambios de Git...\033[0m\n";
ejecutar('git pull origin main');

// ─── PASO 3: Composer install ─────────────────────────────────────────────────
echo "\n\033[33m[PASO 3] Instalando dependencias PHP...\033[0m\n";
ejecutar('composer install --no-interaction --prefer-dist --optimize-autoloader');

// ─── PASO 4: Migraciones ──────────────────────────────────────────────────────
echo "\n\033[33m[PASO 4] Ejecutando migraciones de BD...\033[0m\n";
ejecutar('php artisan migrate --force');

// ─── PASO 5: NPM y build de Assets ────────────────────────────────────────────
echo "\n\033[33m[PASO 5] Instalando NPM y compilando assets...\033[0m\n";
ejecutar('npm install');
ejecutar('npm run build');

// ─── PASO 6: Optimizar y limpiar caché ───────────────────────────────────────
echo "\n\033[33m[PASO 6] Limpiando y optimizando caché...\033[0m\n";
ejecutar('php artisan optimize:clear');
ejecutar('php artisan config:cache');
ejecutar('php artisan route:cache');
ejecutar('php artisan view:cache');

// Regenerar Ziggy (rutas para Vue/Inertia)
echo "\n\033[33m  Regenerando rutas Ziggy para frontend...\033[0m\n";
ejecutar('php artisan ziggy:generate');

// ─── PASO 7: Storage link ─────────────────────────────────────────────────────
echo "\n\033[33m[PASO 7] Verificando enlace de almacenamiento...\033[0m\n";
ejecutar('php artisan storage:link');

// ─── PASO 8: Permisos (solo VPS/Linux) ───────────────────────────────────────
if ($esVPS) {
    echo "\n\033[33m[PASO 8] Aplicando permisos seguros (Linux)...\033[0m\n";
    ejecutar('sudo chown -R $USER:www-data storage bootstrap/cache database');
    ejecutar('sudo find storage bootstrap/cache database -type f -exec chmod 664 {} \;');
    ejecutar('sudo find storage bootstrap/cache database -type d -exec chmod 775 {} \;');
} else {
    echo "\n\033[33m[PASO 8] Omitiendo permisos Linux (Windows local detectado).\033[0m\n";
}

// ─── PASO 9: Salir de mantenimiento ──────────────────────────────────────────
echo "\n\033[33m[PASO 9] Saliendo del modo mantenimiento...\033[0m\n";
ejecutar('php artisan up');

// ─── RESUMEN FINAL ────────────────────────────────────────────────────────────
echo "\n\033[34m╔══════════════════════════════════════════════════╗\033[0m\n";
echo "\033[32m║        ✔ ACTUALIZACIÓN COMPLETADA CON ÉXITO       ║\033[0m\n";
echo "\033[34m╚══════════════════════════════════════════════════╝\033[0m\n";
echo "\n\033[33mVariables de .env recomendadas para las nuevas funciones:\033[0m\n";
echo "  GEMINI_API_KEY   → Clave de API de Google AI Studio (para el asistente)\n";
echo "  GEMINI_MODEL     → (Opcional) Nombre del modelo. Ej: gemini-2.0-flash\n";
echo "  APP_URL          → URL pública del VPS. Ej: https://fintrack.midominio.com\n\n";
