<?php
declare(strict_types=1);

final class ParticipantRepository
{
    /**
     * Registra un participante y devuelve [participant, personalCode].
     * El código personal solo se persiste como hash.
     */
    public static function create(int $familyId, string $name, string $generation, string $role): array
    {
        $code = bvm_personal_code();
        $now = bvm_now();
        $participant = Database::transaction(function (PDO $pdo) use ($familyId, $name, $generation, $role, $code, $now) {
            $st = $pdo->prepare(
                'INSERT INTO participants
                   (public_id, family_id, participant_name, normalized_name, generation, participation_role,
                    resume_token_hash, status, current_index, revision, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'en_proceso\', 0, 0, ?, ?)'
            );
            $st->execute([
                bvm_public_id('p'), $familyId, trim($name), bvm_normalize_name($name),
                $generation, $role, password_hash($code, PASSWORD_DEFAULT), $now, $now,
            ]);
            return self::findById((int)$pdo->lastInsertId());
        });
        return [$participant, $code];
    }

    public static function findById(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM participants WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function findByNormalizedName(int $familyId, string $name): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM participants WHERE family_id = ? AND normalized_name = ?');
        $st->execute([$familyId, bvm_normalize_name($name)]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Localiza al participante de una familia cuyo código personal coincide. */
    public static function findByPersonalCode(int $familyId, string $code): ?array
    {
        $code = strtoupper(trim($code));
        $st = Database::pdo()->prepare('SELECT * FROM participants WHERE family_id = ?');
        $st->execute([$familyId]);
        foreach ($st->fetchAll() as $p) {
            if (password_verify($code, (string)$p['resume_token_hash'])) {
                return $p;
            }
        }
        return null;
    }

    public static function listByFamily(int $familyId): array
    {
        $st = Database::pdo()->prepare('SELECT * FROM participants WHERE family_id = ? ORDER BY created_at ASC, id ASC');
        $st->execute([$familyId]);
        return $st->fetchAll();
    }

    /** Regeneración administrativa del código personal (devuelve el nuevo código). */
    public static function regeneratePersonalCode(int $participantId): ?string
    {
        $p = self::findById($participantId);
        if (!$p) {
            return null;
        }
        $code = bvm_personal_code();
        $st = Database::pdo()->prepare('UPDATE participants SET resume_token_hash = ?, updated_at = ? WHERE id = ?');
        $st->execute([password_hash($code, PASSWORD_DEFAULT), bvm_now(), $participantId]);
        return $code;
    }

    public static function updateProgress(int $participantId, int $currentIndex, int $revision): void
    {
        $st = Database::pdo()->prepare(
            'UPDATE participants SET current_index = ?, revision = ?, updated_at = ? WHERE id = ?'
        );
        $st->execute([$currentIndex, $revision, bvm_now(), $participantId]);
    }

    public static function finalize(int $participantId): bool
    {
        $now = bvm_now();
        $st = Database::pdo()->prepare(
            "UPDATE participants SET status = 'finalizado', completed_at = ?, updated_at = ?
             WHERE id = ? AND status <> 'finalizado'"
        );
        $st->execute([$now, $now, $participantId]);
        return $st->rowCount() > 0;
    }

    /**
     * Reapertura excepcional (con autorización expresa + auditoría en el endpoint).
     */
    public static function reopen(int $participantId): bool
    {
        $st = Database::pdo()->prepare(
            "UPDATE participants SET status = 'en_proceso', completed_at = NULL, updated_at = ? WHERE id = ?"
        );
        $st->execute([bvm_now(), $participantId]);
        return $st->rowCount() > 0;
    }
}
