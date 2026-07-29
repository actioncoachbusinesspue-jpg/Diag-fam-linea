<?php
declare(strict_types=1);

/**
 * Control de intentos (login BVM, clave de familia, código personal).
 * Guarda solo hashes HMAC — nunca usuarios, IPs ni claves en claro.
 */
final class LoginAttemptRepository
{
    public static function isLocked(string $identifier): bool
    {
        $st = Database::pdo()->prepare('SELECT locked_until FROM login_attempts WHERE identifier_hash = ?');
        $st->execute([bvm_hmac($identifier)]);
        $lockedUntil = $st->fetchColumn();
        return $lockedUntil !== false && $lockedUntil !== null && strtotime((string)$lockedUntil) > time();
    }

    public static function registerFailure(string $identifier): void
    {
        $max = (int)bvm_config('security.max_login_attempts', 5);
        $lockMinutes = (int)bvm_config('security.lockout_minutes', 15);
        $hash = bvm_hmac($identifier);
        $ipHash = bvm_client_ip_hash();
        $now = bvm_now();
        Database::transaction(function (PDO $pdo) use ($hash, $ipHash, $now, $max, $lockMinutes) {
            $st = $pdo->prepare('SELECT attempts FROM login_attempts WHERE identifier_hash = ?');
            $st->execute([$hash]);
            $attempts = $st->fetchColumn();
            if ($attempts === false) {
                $ins = $pdo->prepare(
                    'INSERT INTO login_attempts (identifier_hash, ip_hash, attempts, locked_until, updated_at)
                     VALUES (?, ?, 1, NULL, ?)'
                );
                $ins->execute([$hash, $ipHash, $now]);
                return;
            }
            $attempts = (int)$attempts + 1;
            $lockedUntil = null;
            if ($attempts >= $max) {
                $lockedUntil = gmdate('Y-m-d H:i:s', time() + 60 * $lockMinutes);
                $attempts = 0; // el contador reinicia al aplicar el bloqueo
            }
            $up = $pdo->prepare(
                'UPDATE login_attempts SET attempts = ?, locked_until = ?, ip_hash = ?, updated_at = ? WHERE identifier_hash = ?'
            );
            $up->execute([$attempts, $lockedUntil, $ipHash, $now, $hash]);
        });
    }

    public static function clear(string $identifier): void
    {
        $st = Database::pdo()->prepare('DELETE FROM login_attempts WHERE identifier_hash = ?');
        $st->execute([bvm_hmac($identifier)]);
    }
}
