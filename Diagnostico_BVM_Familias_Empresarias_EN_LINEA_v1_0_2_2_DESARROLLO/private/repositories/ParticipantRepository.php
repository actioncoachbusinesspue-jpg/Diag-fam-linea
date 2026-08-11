<?php
declare(strict_types=1);

final class ParticipantRepository
{
    /**
     * Registra un participante y devuelve [participant, personalCode].
     * El código personal solo se persiste como hash (+ HMAC de localización).
     */
    public static function create(int $familyId, string $name, string $generation, string $role): array
    {
        $code = bvm_personal_code();
        $participant = Database::transaction(function (PDO $pdo) use ($familyId, $name, $generation, $role, $code) {
            return self::insertParticipant($pdo, $familyId, $name, $generation, $role, $code);
        });
        return [$participant, $code];
    }

    /** INSERT compartido por create() y register(). Debe llamarse dentro de una transacción. */
    private static function insertParticipant(PDO $pdo, int $familyId, string $name, string $generation, string $role, string $code): array
    {
        $now = bvm_now();
        $st = $pdo->prepare(
            'INSERT INTO participants
               (public_id, family_id, participant_name, normalized_name, generation, participation_role,
                resume_token_hash, resume_token_lookup_hash, status, current_index, revision, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'en_proceso\', 0, 0, ?, ?)'
        );
        $st->execute([
            bvm_public_id('p'), $familyId, trim($name), bvm_normalize_name($name),
            $generation, $role, password_hash($code, PASSWORD_DEFAULT),
            bvm_resume_token_lookup($code), $now, $now,
        ]);
        return self::findById((int)$pdo->lastInsertId());
    }

    /**
     * Registro público con control de cupo a prueba de concurrencia.
     *
     * Toda la validación decisiva ocurre DENTRO de una transacción con la fila
     * de la familia bloqueada (SELECT ... FOR UPDATE en MySQL/MariaDB), de modo
     * que dos registros simultáneos no pueden exceder el límite: validar estado
     * y fechas → contar → validar cupo → validar duplicado → insertar.
     *
     * La decisión de si la familia admite un registro nuevo NO se reimplementa
     * aquí: la toma ParticipationPolicy (fuente única de verdad) con la fila
     * bloqueada y el conteo real, de modo que estado, fechas y cupo se evalúan
     * con exactamente las mismas reglas que en el resto de los endpoints.
     *
     * Devuelve:
     *   ['ok' => true,  'participant' => ..., 'personal_code' => ..., 'over_reference' => bool]
     *   ['ok' => false, 'error' => 'duplicate']
     *   ['ok' => false, 'error' => 'policy', 'reason_code' => <ParticipationPolicy::*>, 'family' => array]
     */
    public static function register(int $familyId, string $name, string $generation, string $role): array
    {
        $code = bvm_personal_code();
        $result = Database::transaction(function (PDO $pdo) use ($familyId, $name, $generation, $role, $code) {
            $lock = Database::isMysql() ? ' FOR UPDATE' : ''; // SQLite serializa escrituras por sí mismo
            $st = $pdo->prepare('SELECT * FROM families WHERE id = ? AND deleted_at IS NULL' . $lock);
            $st->execute([$familyId]);
            $family = $st->fetch();
            if (!$family) {
                return ['ok' => false, 'error' => 'policy', 'reason_code' => ParticipationPolicy::FAMILY_NOT_FOUND, 'family' => []];
            }

            $st = $pdo->prepare('SELECT COUNT(*) FROM participants WHERE family_id = ?');
            $st->execute([$familyId]);
            $registered = (int)$st->fetchColumn();

            $policy = ParticipationPolicy::forFamily($family, $registered);
            $reason = $policy->reasonCode('register');
            if ($reason !== ParticipationPolicy::OK) {
                return ['ok' => false, 'error' => 'policy', 'reason_code' => $reason, 'family' => $family];
            }
            $expected = $family['expected_participants'] !== null ? (int)$family['expected_participants'] : null;
            $enforce = (int)($family['enforce_participant_limit'] ?? 1) === 1;

            $st = $pdo->prepare('SELECT id FROM participants WHERE family_id = ? AND normalized_name = ?');
            $st->execute([$familyId, bvm_normalize_name($name)]);
            if ($st->fetchColumn() !== false) {
                return ['ok' => false, 'error' => 'duplicate'];
            }

            $participant = self::insertParticipant($pdo, $familyId, $name, $generation, $role, $code);
            return [
                'ok' => true,
                'participant' => $participant,
                'over_reference' => $expected !== null && !$enforce && ($registered + 1) > $expected,
            ];
        });
        if (!empty($result['ok'])) {
            $result['personal_code'] = $code;
        }
        return $result;
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

    /**
     * Localiza al participante de una familia cuyo código personal coincide.
     *
     * Camino principal (1.0.2): consulta indexada por
     * (family_id, resume_token_lookup_hash) → password_verify sobre UN único
     * candidato. Costo constante, sin recorrer la familia.
     *
     * Fallback SOLO para datos previos a 1.0.2: revisa únicamente filas con
     * resume_token_lookup_hash IS NULL y, tras un acierto, escribe el lookup
     * para que ese participante nunca vuelva a requerir el recorrido.
     */
    public static function findByPersonalCode(int $familyId, string $code): ?array
    {
        $code = strtoupper(trim($code));
        $lookup = bvm_resume_token_lookup($code);

        $st = Database::pdo()->prepare(
            'SELECT * FROM participants WHERE family_id = ? AND resume_token_lookup_hash = ?'
        );
        $st->execute([$familyId, $lookup]);
        $candidate = $st->fetch();
        if ($candidate) {
            return password_verify($code, (string)$candidate['resume_token_hash']) ? $candidate : null;
        }

        // Participantes legados (columna NULL): recorrido acotado + backfill.
        $st = Database::pdo()->prepare(
            'SELECT * FROM participants WHERE family_id = ? AND resume_token_lookup_hash IS NULL'
        );
        $st->execute([$familyId]);
        foreach ($st->fetchAll() as $p) {
            if (password_verify($code, (string)$p['resume_token_hash'])) {
                $up = Database::pdo()->prepare(
                    'UPDATE participants SET resume_token_lookup_hash = ?, updated_at = ? WHERE id = ?'
                );
                $up->execute([$lookup, bvm_now(), (int)$p['id']]);
                $p['resume_token_lookup_hash'] = $lookup;
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
        $st = Database::pdo()->prepare(
            'UPDATE participants SET resume_token_hash = ?, resume_token_lookup_hash = ?, updated_at = ? WHERE id = ?'
        );
        $st->execute([password_hash($code, PASSWORD_DEFAULT), bvm_resume_token_lookup($code), bvm_now(), $participantId]);
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
