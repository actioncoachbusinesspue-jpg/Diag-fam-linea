<?php
declare(strict_types=1);

/**
 * Auditoría de eventos. metadata_json NUNCA incluye respuestas individuales,
 * contraseñas ni claves en claro.
 */
final class AuditRepository
{
    public static function log(
        string $eventType,
        ?int $adminUserId = null,
        ?int $familyId = null,
        ?int $participantId = null,
        array $metadata = []
    ): void {
        try {
            $st = Database::pdo()->prepare(
                'INSERT INTO audit_events (admin_user_id, family_id, participant_id, event_type, metadata_json, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $st->execute([
                $adminUserId, $familyId, $participantId, $eventType,
                $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
                bvm_now(),
            ]);
        } catch (Throwable $e) {
            // La auditoría nunca debe tirar la operación principal.
            error_log('audit_error: ' . $e->getMessage());
        }
    }
}
