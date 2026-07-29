#!/usr/bin/env bash
# Genera la evidencia de reporte (HITO 9): siembra familias real y de estrés,
# emite los 6 PDFs (demo/real/estrés × Carta/A4), los renderiza a PNG y
# compone hojas de contacto.
#
# Requisitos locales (NO son requisitos de producción):
#   - PLAYWRIGHT_MODULE : ruta a playwright-core (index.mjs)
#   - PDF2PNG_MODULE    : ruta a pdf-to-png-converter (opcional si es resoluble)
#   - CHROMIUM_PATH     : binario de Chromium (por defecto /opt/pw-browsers/chromium)
#
# Uso: bash tests/evidence/run_evidence.sh
set -u
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WORK="$(mktemp -d)"
DB="$WORK/evidence.sqlite"
PORT="${BVM_EVIDENCE_PORT:-8097}"
BASE="http://127.0.0.1:$PORT"

cat > "$WORK/config.php" <<PHP
<?php return [
  'db' => ['driver' => 'sqlite', 'sqlite_path' => '$DB'],
  'app' => [
    'key' => 'clave-evidencia-$RANDOM$RANDOM',
    'env' => 'development',
    'base_url' => '$BASE',
    'session_name' => 'BVMEVID',
    'session_lifetime_minutes' => 45,
    'install_token' => 'token-instalacion-evidencia',
  ],
  'security' => ['max_login_attempts' => 5, 'lockout_minutes' => 15],
];
PHP

export BVM_CONFIG_FILE="$WORK/config.php"

php -r '
$pdo = new PDO("sqlite:" . $argv[1]);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(file_get_contents($argv[2]));
' "$DB" "$ROOT/tests/fixtures/schema_sqlite.sql" || exit 1

php -S 127.0.0.1:$PORT -t "$ROOT/public" >"$WORK/server.log" 2>&1 &
SERVER_PID=$!
trap 'kill $SERVER_PID 2>/dev/null; rm -rf "$WORK"' EXIT
sleep 1

IDS="$WORK/evidence_ids.json"
BASE_URL="$BASE" EVIDENCE_OUT="$IDS" node "$ROOT/tests/evidence/seed_evidence.mjs" \
  && BASE_URL="$BASE" EVIDENCE_IDS="$IDS" PDF_DIR="$ROOT/qa-evidence/pdf" \
     node "$ROOT/tests/evidence/generate_pdfs.mjs" \
  && PDF_DIR="$ROOT/qa-evidence/pdf" PNG_DIR="$ROOT/qa-evidence/png" \
     SHEET_DIR="$ROOT/qa-evidence/contact-sheets" \
     node "$ROOT/tests/evidence/render_pngs.mjs"
STATUS=$?

if [ $STATUS -ne 0 ]; then
  echo "--- server.log (últimas líneas) ---"
  tail -30 "$WORK/server.log"
fi
exit $STATUS
