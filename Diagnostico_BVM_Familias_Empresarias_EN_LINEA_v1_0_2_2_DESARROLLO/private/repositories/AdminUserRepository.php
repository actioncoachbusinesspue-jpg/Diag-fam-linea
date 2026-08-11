<?php
declare(strict_types=1);

final class AdminUserRepository
{
    public static function count(): int
    {
        return (int)Database::pdo()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    }

    public static function findById(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM admin_users WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM admin_users WHERE username = ? AND active = 1');
        $st->execute([$username]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function create(string $name, string $username, string $password, string $role = 'administrador'): int
    {
        // Versión 1.0.2 — Opción A de roles: SOLO existe el rol administrador.
        // El rol consultor y family_assignments quedan preparados en el esquema
        // pero deshabilitados: crear un consultor sin filtro por asignación
        // daría una falsa sensación de aislamiento (vería todas las familias).
        if ($role !== 'administrador') {
            throw new InvalidArgumentException(
                'El rol consultor no está habilitado en esta versión; solo pueden crearse administradores.'
            );
        }
        $st = Database::pdo()->prepare(
            'INSERT INTO admin_users (name, username, password_hash, role, active, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, ?, ?)'
        );
        $now = bvm_now();
        $st->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role, $now, $now]);
        return (int)Database::pdo()->lastInsertId();
    }

    public static function touchLogin(int $id): void
    {
        $st = Database::pdo()->prepare('UPDATE admin_users SET last_login_at = ?, updated_at = ? WHERE id = ?');
        $now = bvm_now();
        $st->execute([$now, $now, $id]);
    }
}
