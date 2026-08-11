# Resumen ejecutivo — Diagnóstico BVM en línea, versión 1.0.2.2

Fecha: 2026-08-11 · Dirigido a: Dirección General BVM
Entrega: `Diagnostico_BVM_Familias_Empresarias_EN_LINEA_v1_0_2_2.zip`
Base de partida: 1.0.2.1 auditada (el resumen de esa versión y de las
anteriores está en `docs/RESUMEN_EJECUTIVO.md` y en `CHANGELOG.md`).

## 1. Qué se corrigió

Los once hallazgos detectados después de la instalación real en Hostinger,
todos de lógica y de experiencia de uso. Ninguno exigió reconstruir el sistema.

| # | Hallazgo | Corrección |
|---|---|---|
| 1 | La portada llevaba al participante a «Liga de invitación no válida» | El CTA ya no abre `participar.php` sin familia: explica que se requiere la liga propia y abre un panel de ayuda accesible, con enlace a la demostración |
| 2 | El flujo Borrador → Abierta → Invitación confundía al administrador | Confirmación explícita de «Borrador · Todavía no acepta participantes», separación entre resguardar datos y enviar la invitación, acción primaria «Configurar y abrir participación» y guía de 7 pasos |
| 3 | «Participantes esperados» bloqueaba registros por sí solo | Es una meta de referencia; el límite solo se activa marcando expresamente la casilla, que ahora nace desmarcada. La confirmación declara «Cupo: referencia» o «Cupo: límite activo» |
| 4 | El estado de la familia solo se comprobaba al registrar | Nueva política central `ParticipationPolicy` aplicada a los seis puntos del ciclo (registrar, reanudar, estado, guardar, externas, finalizar), con códigos de motivo estructurados |
| 5 | Mensajes ambiguos y formulario visible aunque no se pudiera registrar | Mensaje específico por motivo (con fecha cuando aplica) y el formulario no se muestra cuando un participante nuevo no puede registrarse |
| 6 | Riesgo de arrastrar la sesión de una familia a otra | La sesión se vincula a participante + familia + liga; abrir otra liga limpia la anterior; «Salir de esta participación» hace cierre real sin tocar la sesión administrativa |
| 7 | `closes_at >= opens_at` no se validaba al editar | Validación obligatoria en el servidor, combinando lo enviado con lo guardado; 422 con mensaje claro |
| 8 | «Eliminar definitivamente» solo ocultaba la familia | Ahora elimina de verdad familia, participaciones y respuestas en una transacción, sin huérfanos, con conteos previos y doble confirmación; se separa de «Archivar», que conserva |
| 9 | El aviso de privacidad se veía empalmado | Casilla y textos en bloques separados, label completo clicable, foco visible; verificado en escritorio y en dos tamaños de móvil |
| 10 | Se invitaba a abrir un reporte definitivo vacío | Con 0 finalizados no hay reporte; con avance parcial se abre «lectura preliminar» declarada en pantalla; con todo finalizado, el reporte completo |
| 11 | Etiquetas de estado contradictorias («Borrador» + «Disponible») | El estado de la aplicación es la etiqueta primaria y gobierna la participación; el cupo es secundario |

## 2. Archivos modificados

**Nuevos (6):** `private/services/ParticipationPolicy.php`,
`public/api/participants/logout.php`, `private/config.dev.example.php`,
`docs/QA_V1_0_2_2.md`, `docs/DEPLOY_1_0_2_2_DESDE_1_0_2_1.md`,
`docs/ROLLBACK_1_0_2_2.md` (más las suites `tests/e2e/e2e_v1022.mjs` y
`tests/e2e/browser_v1022.mjs`).

**Modificados — aplicación (25):** `private/bootstrap.php`, `private/auth.php`,
`private/health_checks.php`, `private/reference_renderer.php`,
`private/repositories/FamilyRepository.php`,
`private/repositories/ParticipantRepository.php`,
`public/index.php`, `public/participar.php`,
`public/admin/familias.php`, `public/admin/familia.php`,
`public/admin/reporte.php`,
`public/api/families/{create,update,delete,detail,import}.php`,
`public/api/participants/{access,register,resume,state}.php`,
`public/api/responses/{save,external,finalize}.php`,
`public/assets/css/bvm-app.css`, `public/assets/js/bvm-participante.js`.

**Modificados — documentación (3):** `CHANGELOG.md`, `README.md`,
`DEPLOY_HOSTINGER.md`.

**Modificados — pruebas (6):** `tests/e2e/{e2e_api.mjs, run_e2e.sh,
static_checks.mjs}`, `tests/integration/run_integration.php`,
`tests/mysql/{concurrency_test.php, worker_register.php}`.

**No se tocó:** el HTML maestro de referencia, la copia servida del motor
metodológico, `reference_presentation_patch.php`, el cuestionario, las
fórmulas, los umbrales ni las 12 páginas del reporte.

## 3. ¿Cambió el esquema de base de datos?

**No.** v1.0.2.2 no requiere cambios de esquema: mismas 8 tablas, mismas
columnas, mismos índices. La eliminación definitiva funciona con las claves
foráneas que ya existían. El despliegue preserva exactamente la base actual.

## 4. Pruebas ejecutadas y resultados

| Suite | Comprobaciones | Fallas |
|---|---|---|
| E2E completa, estructuras Hostinger A y B (incluye la batería nueva y navegador) | 396 | 0 |
| Integración PHP (política, cupo, fechas, archivar/eliminar) | 98 | 0 |
| MySQL/MariaDB real (esquema, migración, concurrencia, cascada) | 127 | 0 |
| Paridad metodológica contra el maestro congelado | 15 | 0 |
| Health-check sobre MariaDB | 21 | 0 |
| PDF del reporte: demo, real, estrés y empates × Carta y A4 | 8 documentos × 12 páginas | 0 |

- Paridad numérica: **diferencia 0**.
- SHA-256 del motor metodológico: **sin cambios**.
- Sin warnings ni notices de PHP; sin errores de JavaScript.

Detalle, método y límites: `docs/QA_V1_0_2_2.md`.

## 5. Riesgos residuales

1. **La eliminación definitiva ya no tiene deshacer.** Es lo que el botón
   siempre prometió, ahora es cierto: doble confirmación y conteos previos, pero
   la recuperación depende del respaldo SQL.
2. **Cambio de comportamiento visible para el equipo BVM:** al crear familias
   nuevas, el número esperado ya no bloquea registros salvo que se marque la
   casilla. Las familias existentes conservan su configuración.
3. **Un participante activo en el momento del cierre** verá el aviso de bloqueo
   al guardar la siguiente respuesta. Sus respuestas previas quedan guardadas.
4. **Pendiente de verificación en el hosting:** que `/private/`, `/database/` y
   `/tools/` respondan 403/404 por URL directa (depende de Apache, no
   comprobable con el servidor de pruebas).
5. **El texto legal del aviso de privacidad** sigue sujeto a validación
   jurídica de BVM: solo se corrigió su presentación.

## 6. Recomendación

**GO para staging** (`/diagnostico-bvm-online-dev/`): el paquete está listo,
con plantilla de configuración dev incluida y guía paso a paso.

**GO condicionado para producción**, sujeto a que en staging se confirme:

1. health-check con `RESULTADO: TODO CORRECTO` y versión 1.0.2.2;
2. los seis casos de QA manual de `docs/QA_V1_0_2_2.md` §5;
3. `403/404` en `/private/`, `/private/config.php`, `/database/` y `/tools/`;
4. un reporte real impreso con 12 páginas;
5. aprobación expresa de BVM sobre el nuevo comportamiento del cupo.

Cumplidos esos cinco puntos, la actualización de producción es una sustitución
de archivos sin migración, con rollback documentado y sin tocar la base de
datos.

**Estado de defectos:** cero errores conocidos P0/P1/P2 después de la batería
definida. No se afirma «cero bugs absolutos».
