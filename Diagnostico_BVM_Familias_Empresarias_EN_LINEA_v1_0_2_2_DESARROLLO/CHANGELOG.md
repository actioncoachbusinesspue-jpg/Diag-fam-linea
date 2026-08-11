# CHANGELOG — Diagnóstico BVM en línea

## 1.0.2.2 — 2026-08-11 (corrección funcional y de experiencia)

Corrección de los hallazgos detectados tras la instalación real en Hostinger.
**Sin cambios de metodología, sin cambios de esquema y sin pérdida de datos:**
la base de datos de 1.0.2.1 se conserva exactamente como está. Paridad numérica
verificada con diferencia 0 y hash del motor metodológico sin cambios.

### Hallazgo 1 — La portada llevaba a una liga inválida
- El CTA «Comenzar o continuar» abría `participar.php` sin familia, de modo que
  el participante caía en «Liga de invitación no válida».
- Ahora la tarjeta explica que se requiere la liga propia de la familia y el
  botón «Ya recibí una invitación» abre un panel accesible (teclado, `Escape`,
  `aria-expanded`) que indica cómo localizarla. No busca familias por nombre, no
  las enumera y no expone ninguna liga.
- Enlace secundario «Conocer primero la demostración».
- `participar.php` sin liga responde con una guía —no con un error seco— y
  sigue siendo seguro.

### Hallazgo 2 — El flujo Borrador → Abierta → Invitación era confuso
- La regla de seguridad NO cambia: Borrador sigue sin aceptar registros ni
  respuestas.
- Al crear una familia se confirma «Familia creada correctamente / Estado:
  Borrador / Todavía no acepta participantes», con la advertencia «No envíe
  todavía esta invitación».
- Se distingue **guardar datos de invitación** (resguardo interno, disponible
  siempre) de **copiar la invitación para enviar** (solo con la familia
  Abierta).
- Acción primaria «Configurar y abrir participación», con confirmación
  explícita; al abrir, la familia declara «Lista para recibir participantes».
- Guía contextual de 7 pasos en el panel de familias.

### Hallazgo 3 — «Participantes esperados» activaba el límite por sí solo
- El número esperado es una **meta de referencia**. La casilla «Cerrar nuevos
  registros al alcanzar el número esperado» nace DESMARCADA al crear una familia
  y al importar respaldos antiguos.
- El backend guarda exactamente la decisión enviada y la confirmación declara
  «Cupo: referencia» o «Cupo: límite activo».
- El formulario se reinicia con la casilla desmarcada, sin confundir su estado
  con el de la familia recién creada.
- Las familias existentes conservan su configuración actual.

### Hallazgo 4 — El estado de la familia solo se comprobaba al registrar
- Nueva clase `ParticipationPolicy` (`private/services/`): **fuente única de
  verdad** de estado + fechas + cupo, aplicada por `register`, `resume`,
  `state`, `save`, `external` y `finalize`.
- Códigos de motivo estructurados: `family_draft`, `not_started`, `ended`,
  `family_closed`, `family_archived`, `capacity_reached`, `family_not_found`.
- Borrador, Cerrada, Archivada y fuera de fechas: sin registro, sin reanudar
  para responder, sin guardar y sin finalizar. Cupo lleno: solo se bloquean los
  registros NUEVOS; quien ya está registrado continúa y finaliza. Al reabrir una
  familia, los participantes incompletos continúan sin ningún paso adicional.
- `FamilyRepository::isOpenForParticipation()` y `bvm_local_date_is_open()`
  delegan en la política: la regla existe una sola vez en el código.

### Hallazgo 5 — Mensajes genéricos y formulario visible cuando no procedía
- Cada bloqueo tiene su mensaje específico, con la fecha en DD/MM/AAAA cuando
  corresponde.
- Si un participante NUEVO no puede registrarse, el formulario **no se
  renderiza**: ni nombre, ni generación, ni rol, ni consentimiento, ni botón.
- «Continuar donde me quedé» permanece visible solo cuando el bloqueo es de
  cupo y la familia sigue Abierta y dentro de fechas.

### Hallazgo 6 — Sesión de participante entre familias
- La sesión queda vinculada a participante + familia + liga pública.
- Abrir la liga de otra familia limpia automáticamente la sesión anterior; en
  ningún caso se restauran datos de la Familia A dentro del flujo de la B.
- Nuevo `api/participants/logout.php` y botón «Salir de esta participación»:
  cierre real (destruye identidad, familia, liga y clave validada, y renueva el
  identificador de sesión). **No cierra la sesión administrativa BVM.**

### Hallazgo 7 — Validación de fechas al editar
- `closes_at >= opens_at` se valida en el servidor también al actualizar,
  combinando lo enviado con lo ya guardado (editar una sola fecha tampoco puede
  dejar un periodo imposible). Respuesta 422 con mensaje claro.
- Apertura sin cierre, cierre sin apertura y mismo día siguen siendo válidos.
  `report_date` continúa siendo una fecha editorial.

### Hallazgo 8 — «Eliminar definitivamente» no eliminaba
- Antes solo escribía `deleted_at`: los datos permanecían en la base.
- Ahora una transacción elimina respuestas, respuestas externas, participantes,
  asignaciones y la familia. Sin huérfanos, verificado en SQLite y en MariaDB.
- **Sin cambios de esquema**: el modelo ya definía `ON DELETE CASCADE`.
- Se separa de **Archivar**, que conserva todo y solo impide participar.
- Antes de confirmar se muestran los conteos exactos (participantes, respuestas
  y respuestas externas) y se exige escribir el nombre completo más una
  confirmación adicional. La auditoría guarda solo metadatos no sensibles.

### Hallazgo 9 — Aviso de privacidad empalmado
- Casilla y textos en bloques separados (`.consent-option`), label completo
  clicable, `aria-describedby`, foco de teclado visible y casilla de 20 px.
- Verificado midiendo la geometría real en 1280×900, 390×844 y 375×667.
- El contenido legal no cambió.

### Hallazgo 10 — Reporte sin participaciones finalizadas
- Con 0 finalizados no se ofrece ni se sirve el reporte definitivo: la
  administración muestra «Reporte disponible cuando exista al menos una
  participación finalizada» y `admin/reporte.php` responde con ese aviso aunque
  se escriba la URL a mano. La demostración sigue disponible aparte.
- Con avance parcial: «Abrir lectura preliminar», y el reporte declara en
  pantalla «Lectura preliminar con X de Y participaciones finalizadas».
  El aviso se oculta al imprimir: el PDF conserva sus 12 páginas y su paridad.
- Con todas las participaciones esperadas finalizadas: «Abrir radiografía y
  reporte». Sin número esperado no se afirma «todas»: se informa el número de
  finalizados. Ningún cálculo cambió.

### Hallazgo 11 — Estados contradictorios en administración
- La etiqueta primaria es siempre el estado de la aplicación y es la que
  gobierna la participación; el cupo es secundario:
  «Borrador · Cupo en modo referencia», «Abierta · Disponible»,
  «Abierta · Excedido (referencia)», «Cerrada · N finalizados».
- Nunca se muestra «Borrador» junto a «Disponible».

### Pruebas
- E2E en las dos estructuras de despliegue: 418 comprobaciones, 0 fallas
  (incluye la batería nueva de la sección 18 y las pruebas de navegador).
- Integración: 98 comprobaciones, 0 fallas. MySQL/MariaDB real: 127, 0 fallas.
- Paridad metodológica: diferencia 0. Los 8 PDF (demo/real/estrés/empates ×
  Carta/A4) auditan 12 páginas.
- Sin warnings ni notices de PHP y sin errores de JavaScript.
- Detalle y límites en `docs/QA_V1_0_2_2.md`.

### Archivos nuevos
- `private/services/ParticipationPolicy.php`
- `public/api/participants/logout.php`
- `docs/QA_V1_0_2_2.md`, `docs/DEPLOY_1_0_2_2_DESDE_1_0_2_1.md`,
  `docs/ROLLBACK_1_0_2_2.md`
- `tests/e2e/e2e_v1022.mjs`, `tests/e2e/browser_v1022.mjs`

## 1.0.2.1 — 2026-07-29 (corrección de compatibilidad de despliegue)

Corrección puntual sobre 1.0.2. Sin cambios de metodología, base de datos,
reportes ni funciones: solo la resolución de rutas hacia `private/` y la
guía de despliegue.

- **Problema corregido:** la Opción B de `DEPLOY_HOSTINGER.md` (contenido de
  `public/` copiado a una subcarpeta estándar de `public_html`, con
  `private/` dentro) no funcionaba: los puntos de entrada usaban rutas del
  tipo `dirname(__DIR__) . '/private/bootstrap.php'`, que en esa estructura
  buscan `private/` un nivel arriba de la instalación.
- **Estrategia única y centralizada:** nuevo `public/bvm_paths.php`.
  Los 31 puntos de entrada públicos ahora requieren ese localizador por una
  ruta relativa interna a la carpeta pública (idéntica en cualquier
  despliegue) y el localizador resuelve `private/bootstrap.php` probando,
  en orden: la variable de entorno `BVM_PRIVATE_DIR` (opcional),
  `../private` (Estructura A, recomendada) y `./private` (Estructura B).
  Accedido directamente por URL responde 404. `tools/` ya funcionaba en
  ambas estructuras y no cambia.
- **Pruebas:** `tests/e2e/run_e2e.sh` ahora ejecuta la suite completa DOS
  veces — Estructura A (document root en `public/`) y Estructura B
  (carpeta aplanada con `private/` y `tools/` dentro) — cubriendo página
  pública, login, administración, APIs, participante, demostración,
  reporte (navegador) y health-check por CLI en cada una. Resultado:
  TODAS LAS PRUEBAS PASARON en ambas.
- `DEPLOY_HOSTINGER.md` §1 reescrito con las dos estructuras exactas y
  probadas.

## 1.0.2 — 2026-07-29 (endurecimiento controlado)

Actualización puntual sobre 1.0.1: sin cambios de metodología, sin pérdida de
datos, compatible con instalaciones existentes. Migraciones incluidas:
`0003_enforce_participant_limit.sql` y `0004_resume_token_lookup.sql`
(procedimiento en `docs/MIGRACION_1_0_1_A_1_0_2.md`).

### Mejora 1 — Control de participantes esperados
- `families.enforce_participant_limit` (predeterminado: límite ACTIVO).
  Con límite activo, al alcanzar el número esperado se rechazan nuevos
  registros con HTTP 409 («Esta aplicación ya alcanzó el número de
  participantes autorizado…»); continuar y finalizar nunca se bloquea.
  Con límite desactivado, el número esperado es una meta y el excedente se
  muestra como referencia.
- Concurrencia garantizada: estado/fechas, conteo, límite, duplicado e
  inserción ocurren dentro de una transacción con `SELECT … FOR UPDATE`
  sobre la familia; dos registros simultáneos no pueden exceder el cupo
  (verificado con procesos reales contra MariaDB).
- Administración: «X registrados de Y autorizados», estados Disponible /
  Cerca del límite (80%) / Completo / Excedido, modo límite o referencia,
  checkbox con ayuda al crear y configurar; exportación e importación
  incluyen `enforceParticipantLimit`.

### Mejora 2 — Reanudación eficiente por código personal
- `participants.resume_token_lookup_hash` (HMAC-SHA256 con APP_KEY del
  código normalizado) + índice único por familia: la reanudación localiza
  UN candidato por índice y valida con `password_verify`, sin recorrer a
  todos los participantes.
- Datos previos (columna NULL): fallback acotado SOLO a esas filas y
  backfill automático tras el primer acierto. El código personal sigue sin
  guardarse en claro y no aparece en bitácoras.

### Mejora 3 — Clave de familia más robusta
- Nuevas claves y regeneraciones: 6 caracteres aleatorios
  (`ROBLES-8K4P7M`), configurable con
  `security.family_access_random_length` (rango 6–10, fallback 6).
- Las claves de 4 caracteres previas siguen funcionando; no se fuerza
  regeneración ni se altera ningún hash existente.

### Mejora 4 — Zona horaria correcta
- `app.timezone` (predeterminado `America/Mexico_City`), validada al
  arrancar (falla explícita si es inválida).
- Apertura a las 00:00 locales y cierre inclusivo todo el día local;
  timestamps técnicos siguen en UTC; `report_date` es editorial y no se
  convierte de zona. Presentación administrativa en hora local con la
  leyenda «Fechas interpretadas en hora de Ciudad de México».

### Mejora 5 — Sesiones dev/producción aisladas
- `app.session_cookie_path` configurable (normalizada, fallback `/`).
  Combinaciones documentadas: `BVMSESSID` + `/diagnostico-bvm-online/`
  (producción) y `BVMDEVSESSID` + `/diagnostico-bvm-online-dev/` (dev).
- Alta y borrado de la cookie con exactamente los mismos atributos
  (path, Secure bajo HTTPS, HttpOnly, SameSite=Lax).

### Mejora 6 — Empates en el reporte (presentación, no cálculo)
- `private/reference_presentation_patch.php`, inyectado en memoria por el
  renderer (el HTML maestro en disco no se toca): empates por igualdad
  exacta del valor mostrado en «Dimensión de mayor coincidencia/diferencia»
  — dos: «A / B»; tres: «Empate entre: A, B y C»; cuatro: «Coincidencia
  equivalente en las cuatro dimensiones». Aplica en pantalla, impresión,
  demo y reporte real; paridad numérica de Familia Horizonte intacta (0).
- Con nivel de madurez bajo (clasificación existente), el encabezado de
  evidencia dice «Afirmaciones relativamente más consolidadas».
- La nota metodológica declara que los indicadores describen las
  percepciones de quienes participaron y no constituyen una estimación
  estadística de una población más amplia.

### Mejora 7 — Health-check realmente útil
- `private/health_checks.php` con 21 verificaciones y resultado
  OK/ADVERTENCIA/FALLA: PHP, PDO, driver MySQL en producción, conexión,
  las 8 tablas (incluida `family_assignments`), columnas e índice 1.0.2,
  InnoDB, utf8mb4, APP_KEY, zona horaria, base_url (sin localhost, HTTPS
  en producción), session_name, session_cookie_path, install_token,
  motor de referencia, protección de `private/` y `database/`, versión de
  esquema, administrador y permisos de config.php.
- Ejecución SOLO por CLI (`php tools/health-check.php`) o por la ruta
  administrativa protegida `admin/salud.php`. Queda prohibido (y el
  archivo ya no lo permite) publicarlo temporalmente.

### Mejora 8 — `private/` fuera del área publicada
- `DEPLOY_HOSTINGER.md` reescrito: arquitectura recomendada con raíz de
  documento en `public/`; fallback con `.htaccess` + prueba 403
  obligatoria con evidencia. `tools/.htaccess` añadido.

### Mejora 9 — Validación sobre MySQL/MariaDB real
- `tests/mysql/`: la suite de integración completa corre sobre MySQL
  (`BVM_IT_DRIVER=mysql`); pruebas específicas (ENUM, CHECK, FK/cascada,
  unique con NULLs múltiples, utf8mb4 de 4 bytes, fechas, rollback,
  bloqueo real de `FOR UPDATE`); equivalencia estructural entre migración
  0001→0004 e instalación nueva; concurrencia real del cupo con dos
  procesos. Ejecutada contra MariaDB 10.11: TODAS LAS SUITES PASARON.

### Mejora 10 — Roles sin falsa seguridad (Opción A)
- Solo existe el rol administrador: crear un consultor lanza error, y un
  consultor insertado manualmente en la base no puede iniciar sesión
  (bloqueo en login y en la validación de sesión). `family_assignments`
  queda preparada para una versión futura y documentada como no usada.

## 1.0.1 — 2026-07-29

- La demostración pública neutraliza `AppModeManager.enterAdmin` al servirse:
  tampoco desde la consola del navegador se puede pasar al modo
  administrativo (sección 15 / E48).
- Nuevas verificaciones estáticas automatizadas (`tests/e2e/static_checks.mjs`,
  integradas a `run_e2e.sh`): sin rutas localhost/127.0.0.1 en el código
  desplegable ni en la demo servida (E47), sin contraseñas/PIN utilizables en
  el frontend (E49) y errores de API sin trazas PHP/SQL (E46).
- Prueba de navegador adicional: móvil 375×667 sin desborde (E32).
- `database/migrations/` con política de migraciones para hosting compartido:
  `0001_esquema_inicial.sql` (esquema 1.0.0) y `0002_family_assignments.sql`
  (tabla de asignación de familias a consultores del modelo de datos del
  prompt maestro; el MVP aún no la usa). `schema.sql` y el fixture SQLite
  incluyen la tabla.

## 1.0.0 — 2026-07-29 (MVP en línea)

Primera versión de la plataforma en línea (PHP 8 + MySQL/MariaDB), construida
sobre el archivo maestro estable v1.6.11 sin modificarlo.

### Hito 0-1 — Base técnica
- Proyecto separado con `public/`, `private/`, `database/`, `tools/`, `tests/`.
- Copia de referencia inmutable + `NO_MODIFICAR_ARCHIVO_ESTABLE.txt`.
- `config.example.php` sin secretos; conexión PDO; esquema MySQL con claves
  foráneas, índices, unicidad, CHECK 1-5 y borrado lógico.
- Instalador seguro (token privado, autobloqueo) y health-check.

### Hito 2 — Autenticación
- Login de servidor (`password_hash/verify`), sesión con cookie HttpOnly,
  SameSite=Lax justificado, regeneración de ID, expiración por inactividad,
  CSRF, límite de intentos con bloqueo temporal y mensajes genéricos.

### Hito 3 — Familias
- CRUD con estados (borrador/abierta/cerrada/archivada), fechas de vigencia,
  slug aleatorio no predecible, clave de familia hasheada mostrada una sola
  vez y regenerable, avance en tiempo real, eliminación con doble
  confirmación, reapertura de participación auditada.

### Hito 4 — Participante
- Cuestionario BVM-FE-1.2 extraído verbatim del maestro.
- Registro con consentimiento, detección de duplicados por nombre
  normalizado, código personal aleatorio hasheado (una sola vista).
- Autosave por respuesta (transacción, validación 1-5, revisión para
  conflictos multi-dispositivo), continuidad entre dispositivos,
  finalización con bloqueo (HTTP 423).

### Hito 5-6 — Resultados y paridad
- Demo pública y reporte admin sirven el motor del maestro íntegro con
  localStorage deshabilitado (modo memoria) y datos inyectados desde MySQL:
  fórmulas, umbrales, narrativas, Familia Horizonte y reporte de 12 páginas
  idénticos por construcción.
- Prueba de paridad automática: indicadores de Horizonte por la ruta del
  maestro y por la ruta de la base de datos — diferencia exigida: 0.

### Hito 7 — Migración
- Exportación JSON por familia (formato local compatible).
- Importación con vista previa, confirmación, transacción con rollback,
  modos crear/reemplazar y rechazo de respaldos demo o de otra versión.
- Herramienta CLI `tools/import-local-backup.php`.

### Hito 8-9 — Pruebas
- Integración PHP (SQLite) con los datos exactos de Familia Horizonte.
- E2E de API (instalador, login/lockout/CSRF, familias, participante remoto,
  conflicto entre dispositivos, aislamiento, XSS/SQLi, importación).
- E2E de navegador (Chromium): demo en memoria sin errores de consola,
  reporte real con 12 páginas `fits:true`, móvil 390×844.

### Hito 9-12 — Evidencia de reporte y entrega
- Generador automatizado de evidencia (`tests/evidence/`): siembra una familia
  real controlada y una familia de estrés (12 esperados, 10 registrados con
  nombres largos, todos-altos, todos-bajos, empates e incompletos) contra la
  API real y emite los 6 PDFs (`demo|real|stress` × `Carta|A4`) solo si la
  auditoría del maestro confirma 12 páginas `fits:true`.
- 72 páginas renderizadas a PNG + 6 hojas de contacto para inspección visual
  (`qa-evidence/pdf/`, `qa-evidence/png/`, `qa-evidence/contact-sheets/`).
- Resumen ejecutivo de entrega con recomendación GO/NO-GO
  (`docs/RESUMEN_EJECUTIVO.md`).

### Metodología
- Sin cambios: A1-A20, escala, fórmulas, ponderaciones, umbrales, matrices,
  narrativas y reporte permanecen exactamente como en v1.6.11.
