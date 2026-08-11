#!/usr/bin/env bash
# E2E de la aplicación completa sobre el servidor embebido de PHP + SQLite.
#
# v1.0.2.1: la suite corre DOS veces, una por cada estructura de despliegue
# documentada en DEPLOY_HOSTINGER.md §1 (el localizador público bvm_paths.php
# debe resolver private/ en ambas):
#
#   A (recomendada)  : document root = public/, private/ un nivel arriba.
#   B (subcarpeta)   : contenido de public/ copiado a una carpeta que además
#                      contiene private/ y tools/ (public_html estándar,
#                      sin document root personalizado).
#
# En cada estructura se ejecutan: API E2E, verificaciones estáticas,
# health-check por CLI y (si PLAYWRIGHT_MODULE está definido) navegador.
#
# Uso: bash tests/e2e/run_e2e.sh
set -u
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TOTAL_STATUS=0

run_layout() { # $1 = A|B, $2 = docroot, $3 = ruta del health-check
  local LAYOUT="$1" DOCROOT="$2" HEALTH="$3"
  local WORK; WORK="$(mktemp -d)"
  local DB="$WORK/e2e.sqlite"
  local PORT="${BVM_E2E_PORT:-8098}"
  local BASE="http://127.0.0.1:$PORT"

  cat > "$WORK/config.php" <<PHP
<?php return [
  'db' => ['driver' => 'sqlite', 'sqlite_path' => '$DB'],
  'app' => [
    'key' => 'clave-e2e-con-longitud-suficiente-$RANDOM$RANDOM',
    'env' => 'development',
    'base_url' => '$BASE',
    'session_name' => 'BVME2E',
    'session_lifetime_minutes' => 45,
    'install_token' => 'token-instalacion-e2e',
  ],
  'security' => ['max_login_attempts' => 3, 'lockout_minutes' => 15],
];
PHP
  export BVM_CONFIG_FILE="$WORK/config.php"

  php -r '
  $pdo = new PDO("sqlite:" . $argv[1]);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec(file_get_contents($argv[2]));
  ' "$DB" "$ROOT/tests/fixtures/schema_sqlite.sql" || return 1

  echo
  echo "================ ESTRUCTURA $LAYOUT — docroot: $DOCROOT ================"
  php -S 127.0.0.1:$PORT -t "$DOCROOT" >"$WORK/server.log" 2>&1 &
  local SERVER_PID=$!
  sleep 1

  local STATUS=0
  BASE_URL="$BASE" node "$ROOT/tests/e2e/e2e_api.mjs"
  STATUS=$?

  # Batería correctiva 1.0.2.2 (portada, estados, fechas, cupo, sesiones,
  # mensajes, reporte y eliminación definitiva). Reutiliza el administrador
  # creado por e2e_api.mjs sobre la misma base.
  if [ $STATUS -eq 0 ]; then
    BASE_URL="$BASE" node "$ROOT/tests/e2e/e2e_v1022.mjs"
    STATUS=$?
  fi

  # Verificaciones estáticas E45-E49 (código fuente + respuestas servidas)
  if [ $STATUS -eq 0 ]; then
    BASE_URL="$BASE" node "$ROOT/tests/e2e/static_checks.mjs"
    STATUS=$?
  fi

  # Health-check por CLI dentro de la estructura activa (0=OK, 1=avisos).
  # Se usa la misma config pero SIN install_token: en la vida real el token
  # se borra tras instalar; aquí sigue presente solo porque la suite E2E lo
  # necesita para probar el instalador.
  if [ $STATUS -eq 0 ]; then
    sed "s/'install_token' => '[^']*'/'install_token' => ''/" "$WORK/config.php" > "$WORK/config_health.php"
    BVM_CONFIG_FILE="$WORK/config_health.php" php "$HEALTH" >"$WORK/health.log" 2>&1
    local HC=$?
    if [ $HC -le 1 ]; then
      echo "[OK]    health-check CLI en estructura $LAYOUT ($([ $HC -eq 0 ] && echo 'todo correcto' || echo 'correcto con advertencias'))"
    else
      echo "[FALLA] health-check CLI en estructura $LAYOUT"
      tail -30 "$WORK/health.log"
      STATUS=1
    fi
  fi

  # Pruebas de navegador (opcionales): requieren playwright-core y Chromium.
  if [ $STATUS -eq 0 ] && [ -n "${PLAYWRIGHT_MODULE:-}" ]; then
    BASE_URL="$BASE" node "$ROOT/tests/e2e/e2e_browser.mjs"
    STATUS=$?
    if [ $STATUS -eq 0 ]; then
      # Navegador 1.0.2.2: portada, privacidad en tres tamaños y formulario
      # oculto. Las capturas se generan una sola vez (estructura A).
      if [ "$LAYOUT" = "A" ]; then
        BASE_URL="$BASE" node "$ROOT/tests/e2e/browser_v1022.mjs"
      else
        BASE_URL="$BASE" BVM_SHOTS_DIR="$WORK/shots" node "$ROOT/tests/e2e/browser_v1022.mjs"
      fi
      STATUS=$?
    fi
  fi

  # E79/E80 — el registro del servidor no debe contener errores, warnings,
  # notices ni deprecations de PHP durante TODA la corrida.
  if [ $STATUS -eq 0 ]; then
    if grep -Eq "PHP (Warning|Notice|Fatal error|Parse error|Deprecated)" "$WORK/server.log"; then
      echo "[FALLA] E80 el registro de PHP contiene warnings/notices en estructura $LAYOUT"
      grep -E "PHP (Warning|Notice|Fatal error|Parse error|Deprecated)" "$WORK/server.log" | head -10
      STATUS=1
    else
      echo "[OK]    E79/E80 sin errores ni warnings de PHP en el registro (estructura $LAYOUT)"
    fi
  fi

  if [ $STATUS -ne 0 ]; then
    echo "--- server.log (últimas líneas) ---"
    tail -30 "$WORK/server.log"
  fi
  kill $SERVER_PID 2>/dev/null
  rm -rf "$WORK"
  return $STATUS
}

# ---------- Estructura A: public/ como document root ----------
run_layout A "$ROOT/public" "$ROOT/tools/health-check.php" || TOTAL_STATUS=1

# ---------- Estructura B: subcarpeta estándar de public_html ----------
# Contenido de public/ + private/ + tools/ en la MISMA carpeta.
FLAT="$(mktemp -d)/diagnostico-bvm-online"
mkdir -p "$FLAT"
cp -a "$ROOT/public/." "$FLAT/"
cp -a "$ROOT/private" "$FLAT/private"
cp -a "$ROOT/tools" "$FLAT/tools"
run_layout B "$FLAT" "$FLAT/tools/health-check.php" || TOTAL_STATUS=1
rm -rf "$(dirname "$FLAT")"

echo
if [ $TOTAL_STATUS -eq 0 ]; then
  echo "E2E EN AMBAS ESTRUCTURAS (A y B): TODAS LAS PRUEBAS PASARON"
else
  echo "E2E: FALLAS EN AL MENOS UNA ESTRUCTURA"
fi
exit $TOTAL_STATUS
