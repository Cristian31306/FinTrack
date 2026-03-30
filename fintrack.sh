#!/bin/bash

# ==============================================================================
# 🚀 FINTRACK - INSTALADOR & ACTUALIZADOR MAESTRO 🚀
# Un solo comando para dominar todo el despliegue.
# ==============================================================================

# --- CONFIGURACIÓN DEL PROYECTO ---
REPO_URL="https://github.com/tu-usuario/fintrack.git" # <-- ¡CÁMBIALO POR TU REPO!
DIR_PROYECTO="fintrack"

# --- COLORES Y DISEÑO ---
C_DEFAULT="\033[0m"
C_BLUE="\033[1;34m"
C_GREEN="\033[1;32m"
C_RED="\033[1;31m"
C_YELLOW="\033[1;33m"
C_CYAN="\033[1;36m"

declare -a ERRORES=()

# --- FUNCIONES DE INTERFAZ ---
imprimir_banner() {
    clear
    echo -e "${C_CYAN}╔══════════════════════════════════════════════════════════╗${C_DEFAULT}"
    echo -e "${C_CYAN}║                🚀 FINTRACK CLI INSTALLER                 ║${C_DEFAULT}"
    echo -e "${C_CYAN}╚══════════════════════════════════════════════════════════╝${C_DEFAULT}"
    echo -e "Bienvenido. Analizando tu entorno de trabajo...\n"
}

paso() { echo -e "\n${C_BLUE}▶ $1...${C_DEFAULT}"; }
exito() { echo -e "${C_GREEN}  ✔ $1${C_DEFAULT}"; }
advertencia() { echo -e "${C_YELLOW}  ⚠ $1${C_DEFAULT}"; }
error_fatal() { echo -e "\n${C_RED}❌ ERROR FATAL: $1${C_DEFAULT}"; exit 1; }

ejecutar() {
    local descripcion="$1"
    local comando="$2"
    local ignorar_fallo="${3:-false}"

    echo -e "${C_YELLOW}[⚙] Ejecutando: ${comando}${C_DEFAULT}"
    eval "$comando"
    local exit_code=$?

    if [ $exit_code -eq 0 ]; then
        exito "$descripcion completado."
    else
        if [ "$ignorar_fallo" = false ]; then
            echo -e "${C_RED}  ✖ Falló: $descripcion (Código $exit_code)${C_DEFAULT}"
            ERRORES+=("$descripcion")
        else
            advertencia "Falló $descripcion, pero se omitirá (ignorado)."
        fi
    fi
}

preguntar() {
    local pregunta="$1"
    while true; do
        read -p "$(echo -e ${C_CYAN}"? ${pregunta} (s/n): "${C_DEFAULT})" respuesta
        case $respuesta in
            [Ss]* ) return 0;;
            [Nn]* ) return 1;;
            * ) echo "Por favor responde 's' para sí, o 'n' para no.";;
        esac
    done
}

# --- 1. DETECCIÓN DE ENTORNO ---
imprimir_banner

paso "Detectando Sistema Operativo"
OS="Desconocido"
case "$OSTYPE" in
  linux*)   OS="Linux" ;;
  darwin*)  OS="Mac" ;; 
  msys*|cygwin*|win32*) OS="Windows" ;;
esac
exito "Sistema detectado: $OS"

# --- 2. VERIFICACIÓN E INSTALACIÓN DE SOFTWARE BASE ---
paso "Comprobando dependencias del sistema"

instalar_paquetes() {
    if [ "$OS" == "Linux" ]; then
        ejecutar "Actualizando APT y descargando herramientas" "sudo apt update && sudo apt install -y git php-cli php-mbstring php-xml php-curl php-sqlite3 composer nodejs npm unzip curl"
    elif [ "$OS" == "Mac" ]; then
        if ! command -v brew &> /dev/null; then
            ejecutar "Instalando Homebrew" '/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"'
        fi
        ejecutar "Instalando herramientas (Mac)" "brew install git php composer node"
    elif [ "$OS" == "Windows" ]; then
        ejecutar "Instalando herramientas (Windows - Winget)" "winget install -e --id Git.Git && winget install -e --id PHP.PHP && winget install -e --id getcomposer.Composer && winget install -e --id OpenJS.NodeJS"
        advertencia "En Windows, puede que necesites reiniciar tu terminal después de esto."
    fi
}

FALTA_SOFTWARE=false
for cmd in git php composer npm; do
    if ! command -v $cmd &> /dev/null; then
        advertencia "Falta: $cmd"
        FALTA_SOFTWARE=true
    fi
done

if [ "$FALTA_SOFTWARE" = true ]; then
    if preguntar "Faltan herramientas esenciales. ¿Deseas instalarlas automáticamente?"; then
        instalar_paquetes
    else
        error_fatal "No se puede continuar sin git, php, composer y npm."
    fi
else
    exito "Todas las herramientas base están instaladas."
fi

# --- 3. OBTENER / ACTUALIZAR EL CÓDIGO (LA MAGIA DE GIT) ---
paso "Gestionando Código Fuente"

ESTADO_PROYECTO="NUEVO"

# Si ya estamos dentro de una carpeta git de FinTrack
if [ -d ".git" ] && [ -f "artisan" ]; then
    exito "Proyecto FinTrack detectado. Modo: ACTUALIZACIÓN."
    ESTADO_PROYECTO="ACTUALIZANDO"
    ejecutar "Poniendo sistema en mantenimiento" "php artisan down" true
    ejecutar "Descargando últimos cambios" "git pull origin main"
else
    # Si no estamos en el proyecto, verificamos si existe la carpeta
    if [ ! -d "$DIR_PROYECTO" ]; then
        exito "Directorio limpio. Modo: INSTALACIÓN NUEVA."
        if preguntar "¿Deseas clonar FinTrack ahora mismo desde Git?"; then
            ejecutar "Clonando repositorio" "git clone $REPO_URL $DIR_PROYECTO"
            cd $DIR_PROYECTO || error_fatal "No se pudo acceder a la carpeta descargada."
        else
            error_fatal "Se canceló la descarga del proyecto."
        fi
    else
        exito "Carpeta $DIR_PROYECTO detectada. Entrando..."
        cd $DIR_PROYECTO
        ESTADO_PROYECTO="ACTUALIZANDO"
        ejecutar "Poniendo sistema en mantenimiento" "php artisan down" true
        ejecutar "Descargando últimos cambios" "git pull origin main"
    fi
fi

# --- 4. COMPILACIÓN E INSTALACIÓN (LARAVEL + VUE) ---
paso "Instalando y Compilando el Sistema"

# Archivo .env
if [ ! -f ".env" ]; then
    advertencia "Archivo .env no encontrado."
    if [ -f ".env.example" ]; then
        ejecutar "Creando archivo .env desde example" "cp .env.example .env"
        ejecutar "Generando Application Key" "php artisan key:generate"
        advertencia "¡ATENCIÓN! Creado un .env nuevo. Recuerda configurar tu GROQ_API_KEY y la Base de Datos."
    else
        error_fatal "No existe .env ni .env.example."
    fi
fi

ejecutar "Instalando dependencias PHP (Composer)" "composer install --no-interaction --prefer-dist --optimize-autoloader"
ejecutar "Ejecutando migraciones de BD" "php artisan migrate --force"
ejecutar "Instalando dependencias Frontend (NPM)" "npm install"
ejecutar "Compilando Frontend (Vite/Vue)" "npm run build"

paso "Optimizando Laravel"
ejecutar "Limpiando Cachés" "php artisan optimize:clear"
ejecutar "Generando Rutas Ziggy" "php artisan ziggy:generate"
ejecutar "Enlazando Storage" "php artisan storage:link" true

# --- 5. PERMISOS VPS ---
if [ "$OS" == "Linux" ]; then
    if preguntar "¿Estás desplegando esto en un Servidor VPS de producción (Ajustar permisos de seguridad)?"; then
        ejecutar "Ajustando propietario (www-data)" "sudo chown -R \$USER:www-data storage bootstrap/cache database" true
        ejecutar "Ajustando permisos de archivos" "sudo find storage bootstrap/cache database -type f -exec chmod 664 {} \;" true
        ejecutar "Ajustando permisos de carpetas" "sudo find storage bootstrap/cache database -type d -exec chmod 775 {} \;" true
    fi
fi

# --- 6. LEVANTAR SISTEMA ---
if [ "$ESTADO_PROYECTO" == "ACTUALIZANDO" ]; then
    ejecutar "Saliendo de modo mantenimiento" "php artisan up" true
fi

# --- REPORTE FINAL ---
echo -e "\n"
if [ ${#ERRORES[@]} -eq 0 ]; then
    echo -e "${C_GREEN}╔══════════════════════════════════════════════════════════╗${C_DEFAULT}"
    echo -e "${C_GREEN}║ ✨ ¡ÉXITO TOTAL! FINTRACK ESTÁ LISTO Y FUNCIONANDO ✨    ║${C_DEFAULT}"
    echo -e "${C_GREEN}╚══════════════════════════════════════════════════════════╝${C_DEFAULT}"
else
    echo -e "${C_RED}╔══════════════════════════════════════════════════════════╗${C_DEFAULT}"
    echo -e "${C_RED}║ ⚠️ TERMINADO, PERO HUBO ERRORES DURANTE EL PROCESO ⚠️    ║${C_DEFAULT}"
    echo -e "${C_RED}╚══════════════════════════════════════════════════════════╝${C_DEFAULT}"
    echo -e "Revisa la siguiente lista:"
    for err in "${ERRORES[@]}"; do
        echo -e "${C_YELLOW} - $err${C_DEFAULT}"
    done
fi

echo -e "\n${C_CYAN}Si es tu primera vez, no olvides revisar el archivo .env${C_DEFAULT}"