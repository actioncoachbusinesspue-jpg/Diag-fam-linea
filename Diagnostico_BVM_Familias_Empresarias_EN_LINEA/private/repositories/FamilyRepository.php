<?php
declare(strict_types=1);

final class FamilyRepository
{
    /**
     * Crea una familia y devuelve [family, accessCode].
     * La clave se muestra UNA vez; solo se persiste su hash.
     */
    public static function create(
        string $familyName,
        ?int $expectedParticipants,
        ?string $opensAt,
        ?string $closesAt,
        int $createdBy
    ): array {
        $slug = self::uniqueSlug();
        $accessCode = bvm_family_access_code($familyName);
        $now = bvm_now();
        $st = Database::pdo()->prepare(
            'INSERT INTO families
               (public_slug, family_name, access_code_hash, expected_participants, questionnaire_version,
                report_date, status, opens_at, closes_at, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $slug, trim($familyName), password_hash($accessCode, PASSWORD_DEFAULT),
            $expectedParticipants, BVM_QUESTIONNAIRE_VERSION,
            gmdate('Y-m-d'), 'borrador', $opensAt, $closesAt, $createdBy, $now, $now,
        ]);
        $family = self::findById((int)Database::pdo()->lastInsertId());
        return [$family, $accessCode];
    }

    private static function uniqueSlug(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $slug = bvm_random_slug(12);
            $st = Database::pdo()->prepare('SELECT id FROM families WHERE public_slug = ?');
            $st->execute([$slug]);
            if ($st->fetchColumn() === false) {
                return $slug;
            }
        }
        throw new RuntimeException('No fue posible generar un slug único.');
    }

    public static function findById(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM families WHERE id = ? AND deleted_at IS NULL');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM families WHERE public_slug = ? AND deleted_at IS NULL');
        $st->execute([$slug]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Listado administrativo con conteos de avance. */
    public static function listAll(bool $includeArchived = false): array
    {
        $sql = 'SELECT f.*,
                       (SELECT COUNT(*) FROM participants p WHERE p.family_id = f.id) AS registered_count,
                       (SELECT COUNT(*) FROM participants p WHERE p.family_id = f.id AND p.status = \'finalizado\') AS finished_count,
                       (SELECT MAX(p.updated_at) FROM participants p WHERE p.family_id = f.id) AS last_activity_at
                FROM families f
                WHERE f.deleted_at IS NULL';
        if (!$includeArchived) {
            $sql .= " AND f.status <> 'archivada'";
        }
        $sql .= ' ORDER BY f.created_at DESC';
        return Database::pdo()->query($sql)->fetchAll();
    }

    public static function update(int $id, array $fields): bool
    {
        $allowed = ['family_name', 'expected_participants', 'status', 'opens_at', 'closes_at', 'report_date', 'archived_at'];
        $sets = [];
        $vals = [];
        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $sets[] = "$k = ?";
                $vals[] = $v;
            }
        }
        if (!$sets) {
            return false;
        }
        $sets[] = 'updated_at = ?';
        $vals[] = bvm_now();
        $vals[] = $id;
        $st = Database::pdo()->prepare('UPDATE families SET ' . implode(', ', $sets) . ' WHERE id = ? AND deleted_at IS NULL');
        return $st->execute($vals);
    }

    /** Regenera la clave de familia; devuelve la clave nueva en claro (una sola vez). */
    public static function regenerateAccessCode(int $id): ?string
    {
        $family = self::findById($id);
        if (!$family) {
            return null;
        }
        $code = bvm_family_access_code((string)$family['family_name']);
        $st = Database::pdo()->prepare('UPDATE families SET access_code_hash = ?, updated_at = ? WHERE id = ?');
        $st->execute([password_hash($code, PASSWORD_DEFAULT), bvm_now(), $id]);
        return $code;
    }

    public static function verifyAccessCode(array $family, string $code): bool
    {
        return password_verify(strtoupper(trim($code)), (string)$family['access_code_hash']);
    }

    /** Borrado definitivo (con doble confirmación en la interfaz). */
    public static function hardDelete(int $id): bool
    {
        return (bool)Database::transaction(function (PDO $pdo) use ($id) {
            $st = $pdo->prepare('UPDATE families SET deleted_at = ?, updated_at = ? WHERE id = ?');
            return $st->execute([bvm_now(), bvm_now(), $id]);
        });
    }

    /** ¿La familia acepta participación en este momento? */
    public static function isOpenForParticipation(array $family): bool
    {
        if ($family['status'] !== 'abierta') {
            return false;
        }
        $today = gmdate('Y-m-d');
        if (!empty($family['opens_at']) && substr((string)$family['opens_at'], 0, 10) > $today) {
            return false;
        }
        if (!empty($family['closes_at']) && substr((string)$family['closes_at'], 0, 10) < $today) {
            return false;
        }
        return true;
    }
}
