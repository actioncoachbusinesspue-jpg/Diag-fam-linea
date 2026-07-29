<?php
declare(strict_types=1);

/**
 * FamilyDataService — construye el objeto "familia" EXACTAMENTE en la forma
 * que espera el StorageAdapter del motor de referencia (schemaVersion 5,
 * cuestionario BVM-FE-1.2). Es el puente entre MySQL y el motor metodológico,
 * y también el formato de respaldo compatible con la versión local.
 */
final class FamilyDataService
{
    private static function iso(?string $dbDate): ?string
    {
        if (!$dbDate) {
            return null;
        }
        $ts = strtotime($dbDate . ' UTC');
        return $ts === false ? null : gmdate('Y-m-d\TH:i:s.000\Z', $ts);
    }

    /** Objeto familia en formato de referencia. */
    public static function buildFamilyObject(array $family, ?array $participants = null): array
    {
        $familyKey = 'srv_fam_' . $family['id'];
        $participants = $participants ?? ParticipantRepository::listByFamily((int)$family['id']);
        $list = [];
        foreach ($participants as $p) {
            $list[] = [
                'participantId' => (string)$p['public_id'],
                'participantName' => (string)$p['participant_name'],
                'familyId' => $familyKey,
                'familyName' => (string)$family['family_name'],
                'generation' => (string)$p['generation'],
                'participationRole' => (string)$p['participation_role'],
                'questionnaireVersion' => (string)$family['questionnaire_version'],
                'answers' => ResponseRepository::answersArray((int)$p['id']),
                'externalAnswers' => (object)ResponseRepository::externalAnswersMap((int)$p['id']),
                'status' => (string)$p['status'],
                'currentIndex' => (int)$p['current_index'],
                'createdAt' => self::iso((string)$p['created_at']),
                'updatedAt' => self::iso((string)$p['updated_at']),
                'completedAt' => self::iso($p['completed_at'] ? (string)$p['completed_at'] : null),
            ];
        }
        return [
            'familyId' => $familyKey,
            'familyName' => (string)$family['family_name'],
            'questionnaireVersion' => (string)$family['questionnaire_version'],
            'createdAt' => self::iso((string)$family['created_at']),
            'expectedParticipants' => $family['expected_participants'] !== null ? (int)$family['expected_participants'] : null,
            'reportDate' => $family['report_date'] ? substr((string)$family['report_date'], 0, 10) : gmdate('Y-m-d'),
            'archived' => false,
            'participants' => $list,
        ];
    }

    /** Respaldo completo compatible con importBackup() de la versión local. */
    public static function buildBackup(array $family): array
    {
        return [
            'appName' => 'Diagnóstico BVM para Familias Empresarias',
            'schemaVersion' => BVM_SCHEMA_VERSION,
            'storageArchitectureVersion' => '2',
            'questionnaireVersion' => BVM_QUESTIONNAIRE_VERSION,
            'appVersion' => '1.6.11-online',
            'backupType' => 'real-family-data',
            'exportDate' => gmdate('Y-m-d\TH:i:s.000\Z'),
            'family' => self::buildFamilyObject($family),
        ];
    }
}
