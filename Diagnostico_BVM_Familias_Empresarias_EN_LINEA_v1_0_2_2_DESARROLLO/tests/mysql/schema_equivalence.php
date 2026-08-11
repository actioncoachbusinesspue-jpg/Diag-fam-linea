<?php
/**
 * Verifica que la ruta de MIGRACIÓN (0001..0004 en orden) produce exactamente
 * las mismas tablas, columnas e índices que una instalación NUEVA con
 * database/schema.sql. Es la garantía de que actualizar 1.0.1 → 1.0.2 no
 * pierde ni difiere estructura.
 *
 * Uso: php schema_equivalence.php HOST PORT USER PASS DB_NUEVA DB_MIGRADA
 */
declare(strict_types=1);

[$_, $host, $port, $user, $pass, $dbNew, $dbMig] = $argv + [null, '127.0.0.1', '3306', 'root', '', 'a', 'b'];

$pdo = new PDO("mysql:host=$host;port=$port", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function snapshot(PDO $pdo, string $db): array
{
    $st = $pdo->prepare(
        'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
         FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?
         ORDER BY TABLE_NAME, COLUMN_NAME'
    );
    $st->execute([$db]);
    $cols = $st->fetchAll();

    $st = $pdo->prepare(
        'SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE,
                GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
         FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ?
         GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE
         ORDER BY TABLE_NAME, INDEX_NAME'
    );
    $st->execute([$db]);
    $idx = $st->fetchAll();

    $st = $pdo->prepare(
        'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME'
    );
    $st->execute([$db]);
    $tables = $st->fetchAll();

    return ['columns' => $cols, 'indexes' => $idx, 'tables' => $tables];
}

$a = snapshot($pdo, $dbNew);
$b = snapshot($pdo, $dbMig);

$failures = 0;
foreach (['tables', 'columns', 'indexes'] as $part) {
    if ($a[$part] === $b[$part]) {
        echo "[OK]    Equivalencia de $part (instalación nueva == migración 0001..0004)\n";
    } else {
        $failures++;
        echo "[FALLA] $part difiere entre instalación nueva y migrada\n";
        $onlyA = array_map('json_encode', array_udiff($a[$part], $b[$part], fn($x, $y) => strcmp(json_encode($x), json_encode($y))));
        $onlyB = array_map('json_encode', array_udiff($b[$part], $a[$part], fn($x, $y) => strcmp(json_encode($x), json_encode($y))));
        foreach (array_slice($onlyA, 0, 5) as $d) { echo "        solo en nueva  : $d\n"; }
        foreach (array_slice($onlyB, 0, 5) as $d) { echo "        solo en migrada: $d\n"; }
    }
}

echo $failures === 0 ? "\nEQUIVALENCIA DE ESQUEMA: PASA\n" : "\nEQUIVALENCIA DE ESQUEMA: $failures FALLAS\n";
exit($failures === 0 ? 0 : 1);
