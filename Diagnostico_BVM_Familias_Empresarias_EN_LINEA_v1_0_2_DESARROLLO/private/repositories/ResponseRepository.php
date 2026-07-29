<?php
declare(strict_types=1);

final class ResponseRepository
{
    /**
     * Guarda (upsert) UNA respuesta del cuestionario dentro de una transacción,
     * con control de revisión para detectar ediciones simultáneas.
     *
     * @return array{ok:bool, conflict?:bool, locked?:bool, revision?:int}
     */
    public static function saveAnswer(int $participantId, int $questionId, int $value, int $clientRevision, ?int $currentIndex): array
    {
        return Database::transaction(function (PDO $pdo) use ($participantId, $questionId, $value, $clientRevision, $currentIndex) {
            $st = $pdo->prepare('SELECT status, revision, current_index FROM participants WHERE id = ?' . self::forUpdate($pdo));
            $st->execute([$participantId]);
            $p = $st->fetch();
            if (!$p) {
                return ['ok' => false];
            }
            if ($p['status'] === 'finalizado') {
                return ['ok' => false, 'locked' => true];
            }
            // Conflicto: otro dispositivo confirmó una revisión más reciente.
            if ((int)$p['revision'] > $clientRevision) {
                return ['ok' => false, 'conflict' => true, 'revision' => (int)$p['revision']];
            }
            $now = bvm_now();
            self::upsert($pdo, 'responses', $participantId, (string)$questionId, $value, $now);
            $newRevision = (int)$p['revision'] + 1;
            $idx = $currentIndex !== null ? max((int)$p['current_index'], $currentIndex) : (int)$p['current_index'];
            $up = $pdo->prepare('UPDATE participants SET revision = ?, current_index = ?, updated_at = ? WHERE id = ?');
            $up->execute([$newRevision, $idx, $now, $participantId]);
            return ['ok' => true, 'revision' => $newRevision];
        });
    }

    /** Igual que saveAnswer pero para las dos preguntas externas (ext1/ext2). */
    public static function saveExternalAnswer(int $participantId, string $questionId, int $value, int $clientRevision): array
    {
        return Database::transaction(function (PDO $pdo) use ($participantId, $questionId, $value, $clientRevision) {
            $st = $pdo->prepare('SELECT status, revision FROM participants WHERE id = ?' . self::forUpdate($pdo));
            $st->execute([$participantId]);
            $p = $st->fetch();
            if (!$p) {
                return ['ok' => false];
            }
            if ($p['status'] === 'finalizado') {
                return ['ok' => false, 'locked' => true];
            }
            if ((int)$p['revision'] > $clientRevision) {
                return ['ok' => false, 'conflict' => true, 'revision' => (int)$p['revision']];
            }
            $now = bvm_now();
            self::upsert($pdo, 'external_responses', $participantId, $questionId, $value, $now);
            $newRevision = (int)$p['revision'] + 1;
            $up = $pdo->prepare('UPDATE participants SET revision = ?, updated_at = ? WHERE id = ?');
            $up->execute([$newRevision, $now, $participantId]);
            return ['ok' => true, 'revision' => $newRevision];
        });
    }

    private static function upsert(PDO $pdo, string $table, int $participantId, string $questionId, int $value, string $now): void
    {
        $st = $pdo->prepare("SELECT id FROM $table WHERE participant_id = ? AND question_id = ?");
        $st->execute([$participantId, $questionId]);
        $id = $st->fetchColumn();
        if ($id === false) {
            $ins = $pdo->prepare(
                "INSERT INTO $table (participant_id, question_id, answer_value, created_at, updated_at) VALUES (?, ?, ?, ?, ?)"
            );
            $ins->execute([$participantId, $questionId, $value, $now, $now]);
        } else {
            $up = $pdo->prepare("UPDATE $table SET answer_value = ?, updated_at = ? WHERE id = ?");
            $up->execute([$value, $now, (int)$id]);
        }
    }

    /** SELECT ... FOR UPDATE solo donde el motor lo soporta (MySQL/MariaDB). */
    private static function forUpdate(PDO $pdo): string
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }

    /** Respuestas 1..20 como arreglo posicional [q1..q20] con null en faltantes. */
    public static function answersArray(int $participantId): array
    {
        $st = Database::pdo()->prepare('SELECT question_id, answer_value FROM responses WHERE participant_id = ?');
        $st->execute([$participantId]);
        $answers = array_fill(0, BVM_TOTAL_QUESTIONS, null);
        foreach ($st->fetchAll() as $r) {
            $q = (int)$r['question_id'];
            if ($q >= 1 && $q <= BVM_TOTAL_QUESTIONS) {
                $answers[$q - 1] = (int)$r['answer_value'];
            }
        }
        return $answers;
    }

    public static function externalAnswersMap(int $participantId): array
    {
        $st = Database::pdo()->prepare('SELECT question_id, answer_value FROM external_responses WHERE participant_id = ?');
        $st->execute([$participantId]);
        $map = [];
        foreach ($st->fetchAll() as $r) {
            $map[(string)$r['question_id']] = (int)$r['answer_value'];
        }
        return $map;
    }

    public static function answeredCount(int $participantId): int
    {
        $st = Database::pdo()->prepare('SELECT COUNT(*) FROM responses WHERE participant_id = ?');
        $st->execute([$participantId]);
        return (int)$st->fetchColumn();
    }
}
