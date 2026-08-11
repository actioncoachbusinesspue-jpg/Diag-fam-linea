<?php
declare(strict_types=1);

/**
 * ParticipationPolicy — FUENTE ÚNICA DE VERDAD del ciclo de vida de la
 * participación (versión 1.0.2.2).
 *
 * Antes de esta versión, la regla «la familia debe estar abierta» solo existía
 * al REGISTRAR: reanudar, guardar, responder las preguntas externas y finalizar
 * no la comprobaban. Un participante registrado seguía respondiendo aunque la
 * familia estuviera en Borrador, Cerrada, Archivada o fuera de fechas.
 *
 * Todas las decisiones de participación —de los seis endpoints— pasan ahora por
 * esta clase. Ningún endpoint reimplementa la regla.
 *
 * Reglas (idénticas para todos los puntos de entrada):
 *
 *   BORRADOR   : sin registros, sin reanudar para responder, sin guardar, sin finalizar.
 *   ABIERTA
 *     · dentro de fechas y con cupo    : todo permitido.
 *     · dentro de fechas y cupo lleno  : SIN nuevos registros; quien ya está
 *                                        registrado reanuda, guarda y finaliza.
 *     · antes de la fecha de apertura  : nada (motivo not_started).
 *     · después de la fecha de cierre  : nada (motivo ended).
 *   CERRADA    : nada (motivo family_closed).
 *   ARCHIVADA  : nada (motivo family_archived).
 *
 *   REAPERTURA : al volver una familia Cerrada a Abierta dentro de fechas, los
 *                participantes incompletos continúan sin ningún paso adicional
 *                (la política se evalúa en cada petición, no se almacena).
 *
 * Las fechas se interpretan SIEMPRE en la zona configurada
 * (app.timezone, predeterminado America/Mexico_City): la familia abre a las
 * 00:00 locales de opens_at y acepta durante todo el día de closes_at.
 */
final class ParticipationPolicy
{
    // ---- Códigos de motivo estructurados (contrato con la interfaz) --------
    public const OK                = 'ok';
    public const FAMILY_DRAFT      = 'family_draft';
    public const NOT_STARTED       = 'not_started';
    public const ENDED             = 'ended';
    public const FAMILY_CLOSED     = 'family_closed';
    public const FAMILY_ARCHIVED   = 'family_archived';
    public const CAPACITY_REACHED  = 'capacity_reached';
    public const FAMILY_NOT_FOUND  = 'family_not_found';

    private array $family;
    private array $capacity;
    private string $lifecycleReason;

    private function __construct(array $family, array $capacity)
    {
        $this->family = $family;
        $this->capacity = $capacity;
        $this->lifecycleReason = self::evaluateLifecycle($family);
    }

    /**
     * Política de una familia. $registered evita una consulta cuando el
     * llamador ya contó los participantes (por ejemplo dentro de la
     * transacción de registro, con la fila de la familia bloqueada).
     */
    public static function forFamily(array $family, ?int $registered = null): self
    {
        if ($registered === null) {
            $registered = count(ParticipantRepository::listByFamily((int)$family['id']));
        }
        return new self($family, FamilyRepository::capacity($family, $registered));
    }

    /** Estado del ciclo de vida SIN considerar el cupo. */
    private static function evaluateLifecycle(array $family): string
    {
        $status = (string)$family['status'];
        if ($status === 'archivada') {
            return self::FAMILY_ARCHIVED;
        }
        if ($status === 'borrador') {
            return self::FAMILY_DRAFT;
        }
        if ($status === 'cerrada') {
            return self::FAMILY_CLOSED;
        }
        // status === 'abierta': deciden las fechas, en la zona configurada.
        return self::dateWindowReason($family['opens_at'] ?? null, $family['closes_at'] ?? null);
    }

    /**
     * ÚNICA implementación de la ventana de fechas de la aplicación:
     * abre a las 00:00 locales de opens_at y acepta TODO el día de closes_at;
     * las fechas NULL no restringen. Devuelve OK, NOT_STARTED o ENDED.
     */
    public static function dateWindowReason($opensAt, $closesAt, ?string $todayLocal = null): string
    {
        $today = $todayLocal ?? bvm_local_today();
        $opens = self::dateOnly($opensAt);
        $closes = self::dateOnly($closesAt);
        if ($opens !== null && $opens > $today) {
            return self::NOT_STARTED;
        }
        if ($closes !== null && $closes < $today) {
            return self::ENDED;
        }
        return self::OK;
    }

    private static function dateOnly($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return substr((string)$value, 0, 10);
    }

    // ---- Preguntas de la política -----------------------------------------

    /** ¿Puede registrarse un participante NUEVO? (ciclo de vida + cupo) */
    public function canRegisterNewParticipant(): bool
    {
        return $this->reasonToRegister() === self::OK;
    }

    /** ¿Puede un participante YA registrado retomar su participación? */
    public function canResumeParticipant(): bool
    {
        return $this->lifecycleReason === self::OK;
    }

    /** ¿Pueden guardarse respuestas (autosave y preguntas externas)? */
    public function canSaveResponses(): bool
    {
        return $this->lifecycleReason === self::OK;
    }

    /** ¿Puede finalizarse una participación? */
    public function canFinalize(): bool
    {
        return $this->lifecycleReason === self::OK;
    }

    /**
     * Motivo estructurado de la acción indicada.
     * $action: 'register' | 'resume' | 'save' | 'finalize'
     * Devuelve self::OK cuando la acción está permitida.
     */
    public function reasonCode(string $action = 'register'): string
    {
        return $action === 'register' ? $this->reasonToRegister() : $this->lifecycleReason;
    }

    private function reasonToRegister(): string
    {
        if ($this->lifecycleReason !== self::OK) {
            return $this->lifecycleReason;
        }
        return $this->capacity['accepting_new'] ? self::OK : self::CAPACITY_REACHED;
    }

    /** Motivo del ciclo de vida, sin el cupo (útil para la interfaz). */
    public function lifecycleReason(): string
    {
        return $this->lifecycleReason;
    }

    public function capacity(): array
    {
        return $this->capacity;
    }

    // ---- Traducción a mensajes y a HTTP ------------------------------------

    /**
     * Código HTTP que corresponde a un motivo de bloqueo.
     * 409 (conflicto con el estado del recurso) en todos los casos de ciclo de
     * vida y de cupo; 404 cuando la familia no existe.
     */
    public static function httpStatus(string $reasonCode): int
    {
        return $reasonCode === self::FAMILY_NOT_FOUND ? 404 : 409;
    }

    /**
     * Mensaje humano por motivo, con las fechas de ESTA familia.
     * La interfaz web traduce los códigos por su cuenta (ver
     * assets/js/bvm-participante.js); este texto sirve a clientes sin
     * JavaScript, a las respuestas JSON directas y a las pruebas.
     */
    public function message(string $reasonCode): string
    {
        return self::messageFor($reasonCode, $this->family);
    }

    public static function messageFor(string $reasonCode, array $family = []): string
    {
        $opens = self::humanDate($family['opens_at'] ?? null);
        $closes = self::humanDate($family['closes_at'] ?? null);
        switch ($reasonCode) {
            case self::FAMILY_DRAFT:
                return 'Esta aplicación aún no ha sido habilitada por BVM.';
            case self::NOT_STARTED:
                return $opens !== ''
                    ? 'La participación estará disponible a partir del ' . $opens . '.'
                    : 'La participación todavía no ha sido habilitada.';
            case self::ENDED:
                return $closes !== ''
                    ? 'El periodo de participación concluyó el ' . $closes . '.'
                    : 'El periodo de participación ya concluyó.';
            case self::FAMILY_CLOSED:
                return 'BVM ha cerrado esta aplicación y ya no recibe nuevas respuestas.';
            case self::FAMILY_ARCHIVED:
                return 'Esta aplicación fue archivada por BVM y ya no admite participación.';
            case self::CAPACITY_REACHED:
                return 'Se alcanzó el número autorizado de participantes.';
            case self::FAMILY_NOT_FOUND:
                return 'Familia no encontrada.';
            default:
                return '';
        }
    }

    /** DD/MM/AAAA a partir de una fecha DATE (nunca se convierte de zona). */
    private static function humanDate($value): string
    {
        $date = self::dateOnly($value);
        if ($date === null || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            return '';
        }
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }

    /**
     * Retrato completo de la política para la interfaz: la UI decide qué
     * mostrar (formulario de registro, botón de continuidad, aviso) SIN
     * reimplementar ninguna regla.
     */
    public function describe(): array
    {
        $registerReason = $this->reasonToRegister();
        return [
            'can_register'        => $registerReason === self::OK,
            'can_resume'          => $this->canResumeParticipant(),
            'register_reason'     => $registerReason,
            'lifecycle_reason'    => $this->lifecycleReason,
            'register_message'    => $this->message($registerReason),
            'lifecycle_message'   => $this->message($this->lifecycleReason),
            'opens_at'            => self::dateOnly($this->family['opens_at'] ?? null),
            'closes_at'           => self::dateOnly($this->family['closes_at'] ?? null),
            'status'              => (string)$this->family['status'],
        ];
    }
}

/**
 * Guardia común de los endpoints de participación: carga la familia, evalúa la
 * política para $action ('register' | 'resume' | 'save' | 'finalize') y corta
 * con un JSON de motivo estructurado si la acción no procede.
 *
 * Devuelve [familia, política] cuando la acción está permitida.
 */
function bvm_require_participation_allowed(int $familyId, string $action): array
{
    $family = $familyId > 0 ? FamilyRepository::findById($familyId) : null;
    if (!$family) {
        bvm_json_error(
            ParticipationPolicy::messageFor(ParticipationPolicy::FAMILY_NOT_FOUND),
            ParticipationPolicy::httpStatus(ParticipationPolicy::FAMILY_NOT_FOUND),
            ['reason_code' => ParticipationPolicy::FAMILY_NOT_FOUND]
        );
    }
    $policy = ParticipationPolicy::forFamily($family);
    $reason = $policy->reasonCode($action);
    if ($reason !== ParticipationPolicy::OK) {
        bvm_json_error(
            $policy->message($reason),
            ParticipationPolicy::httpStatus($reason),
            ['reason_code' => $reason, 'blocked' => true]
        );
    }
    return [$family, $policy];
}
