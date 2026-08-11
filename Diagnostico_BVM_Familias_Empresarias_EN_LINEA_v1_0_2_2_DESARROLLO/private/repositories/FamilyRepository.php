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
        int $createdBy,
        // 1.0.2.2: el número esperado es una META por defecto. Solo se
        // convierte en límite rígido si el administrador lo pide expresamente.
        bool $enforceParticipantLimit = false
    ): array {
        $slug = self::uniqueSlug();
        $accessCode = bvm_family_access_code($familyName);
        $now = bvm_now();
        $st = Database::pdo()->prepare(
            'INSERT INTO families
               (public_slug, family_name, access_code_hash, expected_participants, enforce_participant_limit,
                questionnaire_version, report_date, status, opens_at, closes_at, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $slug, trim($familyName), password_hash($accessCode, PASSWORD_DEFAULT),
            $expectedParticipants, $enforceParticipantLimit ? 1 : 0, BVM_QUESTIONNAIRE_VERSION,
            bvm_local_today(), 'borrador', $opensAt, $closesAt, $createdBy, $now, $now,
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
        $allowed = ['family_name', 'expected_participants', 'enforce_participant_limit', 'status', 'opens_at', 'closes_at', 'report_date', 'archived_at'];
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

    /**
     * Conteo de datos dependientes de una familia (para la confirmación previa
     * a la eliminación definitiva y para la auditoría).
     */
    public static function dependentCounts(int $id): array
    {
        $pdo = Database::pdo();
        $one = function (string $sql) use ($pdo, $id): int {
            $st = $pdo->prepare($sql);
            $st->execute([$id]);
            return (int)$st->fetchColumn();
        };
        return [
            'participants' => $one('SELECT COUNT(*) FROM participants WHERE family_id = ?'),
            'responses' => $one(
                'SELECT COUNT(*) FROM responses r
                   JOIN participants p ON p.id = r.participant_id
                  WHERE p.family_id = ?'
            ),
            'external_responses' => $one(
                'SELECT COUNT(*) FROM external_responses e
                   JOIN participants p ON p.id = e.participant_id
                  WHERE p.family_id = ?'
            ),
        ];
    }

    /**
     * ARCHIVAR — conservación. Mantiene la familia y TODOS sus datos; solo deja
     * de aceptar participación y se filtra bajo «Archivada».
     */
    public static function archive(int $id): bool
    {
        return self::update($id, ['status' => 'archivada', 'archived_at' => bvm_now()]);
    }

    /**
     * ELIMINAR DEFINITIVAMENTE — acción destructiva REAL (1.0.2.2, Hallazgo 8).
     *
     * Hasta 1.0.2.1 esta operación solo escribía `deleted_at`: la familia
     * desaparecía de la interfaz pero participaciones y respuestas seguían en la
     * base, contradiciendo el texto del botón y de su ayuda.
     *
     * Ahora borra en UNA transacción la familia y todos sus datos dependientes.
     * El esquema ya define ON DELETE CASCADE (participants→families,
     * responses/external_responses→participants, family_assignments→families) en
     * MySQL/MariaDB y en el fixture SQLite (con PRAGMA foreign_keys = ON), pero
     * el borrado de hijos es EXPLÍCITO y en orden para no depender de que el
     * motor tenga las claves activas: el resultado es el mismo en ambos y nunca
     * quedan participaciones ni respuestas huérfanas.
     *
     * No requiere ningún cambio de esquema.
     *
     * Devuelve el conteo de lo eliminado.
     */
    public static function hardDelete(int $id): array
    {
        $counts = self::dependentCounts($id);
        Database::transaction(function (PDO $pdo) use ($id) {
            $exec = function (string $sql) use ($pdo, $id): void {
                $st = $pdo->prepare($sql);
                $st->execute([$id]);
            };
            $exec('DELETE FROM responses WHERE participant_id IN (SELECT id FROM participants WHERE family_id = ?)');
            $exec('DELETE FROM external_responses WHERE participant_id IN (SELECT id FROM participants WHERE family_id = ?)');
            $exec('DELETE FROM participants WHERE family_id = ?');
            $exec('DELETE FROM family_assignments WHERE family_id = ?');
            $exec('DELETE FROM families WHERE id = ?');
        });
        return $counts;
    }

    /**
     * ¿La familia acepta participación en este momento?
     * Las fechas se interpretan en la zona configurada (America/Mexico_City):
     * abre a las 00:00 locales de opens_at y acepta TODO el día de closes_at.
     */
    public static function isOpenForParticipation(array $family): bool
    {
        // Delegado a la política central (1.0.2.2): el ciclo de vida tiene una
        // sola implementación. El cupo NO interviene aquí (se pasa 0 registrados
        // precisamente para evaluar solo estado + fechas).
        return ParticipationPolicy::forFamily($family, 0)->lifecycleReason() === ParticipationPolicy::OK;
    }

    /**
     * Estado de cupo de una familia dado su número de registrados.
     * mode : 'sin_limite' | 'limite' | 'referencia'
     * state: 'sin_limite' | 'disponible' | 'cerca_del_limite' | 'completo' | 'excedido'
     */
    public static function capacity(array $family, int $registered): array
    {
        $expected = $family['expected_participants'] !== null ? (int)$family['expected_participants'] : null;
        $enforce = (int)($family['enforce_participant_limit'] ?? 1) === 1;
        if ($expected === null) {
            return [
                'expected' => null,
                'registered' => $registered,
                'enforce_participant_limit' => $enforce,
                'mode' => 'sin_limite',
                'state' => 'sin_limite',
                'accepting_new' => true,
            ];
        }
        if ($registered > $expected) {
            $state = 'excedido';
        } elseif ($registered >= $expected) {
            $state = 'completo';
        } elseif ($expected > 0 && $registered >= (int)ceil($expected * 0.8)) {
            $state = 'cerca_del_limite';
        } else {
            $state = 'disponible';
        }
        return [
            'expected' => $expected,
            'registered' => $registered,
            'enforce_participant_limit' => $enforce,
            'mode' => $enforce ? 'limite' : 'referencia',
            'state' => $state,
            'accepting_new' => !$enforce || $registered < $expected,
        ];
    }
}
