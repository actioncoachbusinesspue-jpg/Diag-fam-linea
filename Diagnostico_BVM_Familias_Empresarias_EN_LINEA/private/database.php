<?php
declare(strict_types=1);

/**
 * database.php — conexión PDO única (MySQL/MariaDB en producción,
 * SQLite únicamente para pruebas locales automatizadas).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $driver = bvm_config('db.driver', 'mysql');
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        if ($driver === 'sqlite') {
            $path = bvm_config('db.sqlite_path');
            if (!$path) {
                throw new RuntimeException('db.sqlite_path no configurado');
            }
            $pdo = new PDO('sqlite:' . $path, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                bvm_config('db.host', 'localhost'),
                bvm_config('db.name', ''),
                bvm_config('db.charset', 'utf8mb4')
            );
            $pdo = new PDO($dsn, (string)bvm_config('db.user', ''), (string)bvm_config('db.pass', ''), $options);
        }
        self::$pdo = $pdo;
        return $pdo;
    }

    /** Ejecuta un bloque dentro de una transacción con rollback automático. */
    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        $alreadyIn = $pdo->inTransaction();
        if (!$alreadyIn) {
            $pdo->beginTransaction();
        }
        try {
            $result = $fn($pdo);
            if (!$alreadyIn) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if (!$alreadyIn && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
