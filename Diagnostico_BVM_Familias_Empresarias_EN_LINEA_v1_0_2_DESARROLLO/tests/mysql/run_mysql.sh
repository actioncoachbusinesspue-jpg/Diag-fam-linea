#!/usr/bin/env bash
# Suite de integración sobre MySQL/MariaDB REAL (versión 1.0.2, Mejora 9).
#
# Ejecuta, sobre bases temporales:
#   1. Instalación nueva: database/schema.sql → suite de integración completa
#      (la misma de tests/integration) + pruebas específicas de MySQL
#      (ENUM, CHECK, FK/cascada, unique, utf8mb4, rollback, FOR UPDATE).
#   2. Ruta de migración 1.0.1 → 1.0.2: aplica 0001..0004 en orden y verifica
#      que las columnas e índices resultantes coinciden con schema.sql.
#   3. Concurrencia del cupo: dos procesos disputan el último lugar
#      (uno gana, el otro recibe family_full).
#
# Servidor: usa las variables BVM_MYSQL_HOST/PORT/USER/PASS si ya existen;
# si no, levanta un contenedor Docker efímero (mysql:8). Docker es SOLO una
# comodidad local: producción no depende de él.
#
# Uso: bash tests/mysql/run_mysql.sh
set -u
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
STAMP="$$_$RANDOM"
DB_NEW="bvm_it_new_$STAMP"
DB_MIG="bvm_it_mig_$STAMP"

MYSQL_HOST="${BVM_MYSQL_HOST:-}"
MYSQL_PORT="${BVM_MYSQL_PORT:-3306}"
MYSQL_USER="${BVM_MYSQL_USER:-root}"
MYSQL_PASS="${BVM_MYSQL_PASS:-}"
CONTAINER=""

cleanup() {
  if [ -n "$MYSQL_HOST" ]; then
    mysql_exec "DROP DATABASE IF EXISTS $DB_NEW; DROP DATABASE IF EXISTS $DB_MIG;" 2>/dev/null
  fi
  if [ -n "$CONTAINER" ]; then
    docker rm -f "$CONTAINER" >/dev/null 2>&1
  fi
}
trap cleanup EXIT

mysql_exec() {
  php -r '
    $pdo = new PDO("mysql:host=".$argv[1].";port=".$argv[2], $argv[3], $argv[4],
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec($argv[5]);
  ' "$MYSQL_HOST" "$MYSQL_PORT" "$MYSQL_USER" "$MYSQL_PASS" "$1"
}

mysql_import() { # $1 = base, $2 = archivo sql
  php -r '
    $pdo = new PDO("mysql:host=".$argv[1].";port=".$argv[2].";dbname=".$argv[3], $argv[4], $argv[5],
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec(file_get_contents($argv[6]));
  ' "$MYSQL_HOST" "$MYSQL_PORT" "$1" "$MYSQL_USER" "$MYSQL_PASS" "$2"
}

# ---------- Servidor ----------
if [ -z "$MYSQL_HOST" ]; then
  if ! command -v docker >/dev/null; then
    echo "[MYSQL] SIN EJECUTAR: no hay servidor MySQL (defina BVM_MYSQL_HOST) ni Docker disponible."
    echo "[MYSQL] La suite queda PREPARADA pero NO ejecutada. No declare 'MySQL validado'."
    exit 3
  fi
  echo "[MYSQL] Levantando contenedor mysql:8 efímero…"
  CONTAINER="bvm-mysql-it-$STAMP"
  MYSQL_HOST=127.0.0.1
  MYSQL_PORT=33061
  MYSQL_USER=root
  MYSQL_PASS="it-pass-$STAMP"
  docker run -d --name "$CONTAINER" -e MYSQL_ROOT_PASSWORD="$MYSQL_PASS" \
    -p 127.0.0.1:$MYSQL_PORT:3306 mysql:8 >/dev/null || {
      echo "[MYSQL] SIN EJECUTAR: no fue posible iniciar el contenedor mysql:8."
      exit 3
    }
  echo -n "[MYSQL] Esperando al servidor"
  for i in $(seq 1 60); do
    if mysql_exec "SELECT 1" 2>/dev/null; then echo " listo."; break; fi
    echo -n "."; sleep 2
    if [ "$i" = 60 ]; then echo; echo "[MYSQL] El servidor no respondió a tiempo."; exit 3; fi
  done
fi

export BVM_IT_DRIVER=mysql BVM_IT_HOST="$MYSQL_HOST;port=$MYSQL_PORT" \
       BVM_IT_USER="$MYSQL_USER" BVM_IT_PASS="$MYSQL_PASS"
# PDO acepta host con ";port=" embebido en el DSN vía el config generado.

FAILS=0

# ---------- 1. Instalación nueva + suite completa ----------
echo; echo "== [1/3] Instalación nueva ($DB_NEW): schema.sql + suite de integración =="
mysql_exec "CREATE DATABASE $DB_NEW CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" || exit 1
mysql_import "$DB_NEW" "$ROOT/database/schema.sql" || exit 1
BVM_IT_DB="$DB_NEW" php "$ROOT/tests/integration/run_integration.php" || FAILS=$((FAILS+1))
BVM_IT_DB="$DB_NEW" php "$ROOT/tests/mysql/mysql_specific.php" || FAILS=$((FAILS+1))

# ---------- 2. Ruta de migración 1.0.1 → 1.0.2 ----------
echo; echo "== [2/3] Migración ($DB_MIG): 0001..0004 en orden y equivalencia con schema.sql =="
mysql_exec "CREATE DATABASE $DB_MIG CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" || exit 1
for m in 0001_esquema_inicial 0002_family_assignments 0003_enforce_participant_limit 0004_resume_token_lookup; do
  mysql_import "$DB_MIG" "$ROOT/database/migrations/$m.sql" || { echo "[FALLA] migración $m"; FAILS=$((FAILS+1)); }
done
php "$ROOT/tests/mysql/schema_equivalence.php" "$MYSQL_HOST" "$MYSQL_PORT" "$MYSQL_USER" "$MYSQL_PASS" "$DB_NEW" "$DB_MIG" || FAILS=$((FAILS+1))

# ---------- 3. Concurrencia del cupo ----------
echo; echo "== [3/3] Concurrencia: dos registros disputan el último lugar =="
BVM_IT_DB="$DB_NEW" php "$ROOT/tests/mysql/concurrency_test.php" || FAILS=$((FAILS+1))

echo
if [ "$FAILS" = 0 ]; then
  echo "MYSQL: TODAS LAS SUITES PASARON"
  exit 0
else
  echo "MYSQL: $FAILS SUITES CON FALLAS"
  exit 1
fi
