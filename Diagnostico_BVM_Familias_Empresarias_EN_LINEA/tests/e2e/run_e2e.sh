#!/usr/bin/env bash
# E2E de la aplicación completa sobre el servidor embebido de PHP + SQLite.
# Uso: bash tests/e2e/run_e2e.sh
set -u
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WORK="$(mktemp -d)"
DB="$WORK/e2e.sqlite"
PORT="${BVM_E2E_PORT:-8098}"
BASE="http://127.0.0.1:$PORT"

cat > "$WORK/config.php" <<PHP
<?php return [
  'db' => ['driver' => 'sqlite', 'sqlite_path' => '$DB'],
  'app' => [
    'key' => 'clave-e2e-$RANDOM$RANDOM',
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

# Esquema
php -r '
$pdo = new PDO("sqlite:" . $argv[1]);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(file_get_contents($argv[2]));
' "$DB" "$ROOT/tests/fixtures/schema_sqlite.sql" || exit 1

php -S 127.0.0.1:$PORT -t "$ROOT/public" >"$WORK/server.log" 2>&1 &
SERVER_PID=$!
trap 'kill $SERVER_PID 2>/dev/null; rm -rf "$WORK"' EXIT
sleep 1

BASE_URL="$BASE" node "$ROOT/tests/e2e/e2e_api.mjs"
STATUS=$?

# Pruebas de navegador (opcionales): requieren playwright-core y Chromium.
# Exporte PLAYWRIGHT_MODULE con la ruta al paquete playwright-core.
if [ $STATUS -eq 0 ] && [ -n "${PLAYWRIGHT_MODULE:-}" ]; then
  BASE_URL="$BASE" node "$ROOT/tests/e2e/e2e_browser.mjs"
  STATUS=$?
fi

if [ $STATUS -ne 0 ]; then
  echo "--- server.log (últimas líneas) ---"
  tail -30 "$WORK/server.log"
fi
exit $STATUS
