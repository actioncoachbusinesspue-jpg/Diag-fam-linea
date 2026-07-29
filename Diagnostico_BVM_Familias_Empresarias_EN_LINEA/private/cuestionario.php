<?php
declare(strict_types=1);

/**
 * cuestionario.php — definición de servidor del cuestionario BVM-FE-1.2.
 *
 * REGLA METODOLÓGICA: los textos oficiales de las 20 afirmaciones, la escala
 * y las preguntas externas viven en public/assets/js/bvm-cuestionario.js,
 * extraídos VERBATIM del archivo maestro estable. Aquí solo se define lo que
 * el servidor necesita para VALIDAR (ids, rangos, catálogos), nunca para
 * recalcular ni reinterpretar la metodología.
 */

const BVM_QUESTIONNAIRE_VERSION = 'BVM-FE-1.2';
const BVM_SCHEMA_VERSION = 5;
const BVM_TOTAL_QUESTIONS = 20;
const BVM_EXTERNAL_QUESTION_IDS = ['ext1', 'ext2'];

const BVM_GENERATIONS = [
    'Primera generación',
    'Segunda generación',
    'Tercera generación o posterior',
    'Prefiero no indicarlo',
];

const BVM_PARTICIPATION_ROLES = [
    'Dirección o liderazgo operativo',
    'Consejo, órgano de gobierno o comité familiar',
    'Accionista o propietario sin rol operativo',
    'Futuro propietario o heredero',
    'Otro rol patrimonial',
    'Prefiero no indicarlo',
];

const BVM_FAMILY_STATUSES = ['borrador', 'abierta', 'cerrada', 'archivada'];
const BVM_PARTICIPANT_STATUSES = ['en_proceso', 'finalizado'];

/** Normalización de nombre idéntica a la de referencia (espacios + minúsculas). */
function bvm_normalize_name(string $name): string
{
    $collapsed = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
    return mb_strtolower($collapsed, 'UTF-8');
}
