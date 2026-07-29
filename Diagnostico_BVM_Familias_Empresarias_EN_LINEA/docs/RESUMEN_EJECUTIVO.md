# Resumen ejecutivo de entrega — Diagnóstico BVM en línea (MVP 1.0.0)

Fecha: 2026-07-29 · Dirigido a: Dirección General BVM

## Qué se construyó

Una plataforma web real (PHP 8 + MySQL/MariaDB + PDO, sin frameworks pesados
ni CDNs, compatible con hosting compartido de Hostinger) que sustituye a la
"demostración en línea" auditada. No es un HTML: es una aplicación con rutas
públicas y administrativas separadas, API JSON, autenticación de servidor y
base de datos central como única fuente de verdad.

- **Página pública** con tres caminos: responder el diagnóstico (liga +
  clave), demostración guiada Familia Horizonte y Acceso BVM.
- **Acceso BVM real**: usuario y contraseña validados en el servidor
  (`password_hash/verify`), sesión PHP con cookie HttpOnly, regeneración de
  ID, expiración por inactividad, CSRF en toda mutación, límite de intentos
  con bloqueo temporal y auditoría de accesos. Instalador inicial con token
  privado que se bloquea solo tras crear la primera cuenta.
- **Administración de familias**: alta, edición, estados (borrador/abierta/
  cerrada/archivada), vigencias, liga con slug aleatorio, clave familiar
  aleatoria (hash, una sola vista, regenerable), invitación copiable, avance
  y última actividad, reapertura auditada, doble confirmación para eliminar,
  exportación/importación de respaldos.
- **Flujo del participante multi-dispositivo**: clave de familia,
  consentimiento, código personal de continuidad (hash, una sola vista),
  guardado automático respuesta por respuesta con transacción y número de
  revisión (conflicto entre dos dispositivos → 409 sin pérdida de datos),
  reanudación desde cualquier equipo, finalización con bloqueo (423).
- **Radiografía y reporte integral de 12 páginas** servidos con el motor del
  archivo maestro íntegro y datos inyectados desde MySQL.

## Qué se conservó

La metodología completa, sin cambios: A1–A20 verbatim, escala 1–5, cuatro
dimensiones, preguntas externas y su exclusión del índice, fórmulas,
normalización, ponderación, umbrales, dispersión, alineación, matrices,
narrativas, empates, lectura preliminar, estructura de 12 páginas, página 11
y cierre ejecutivo. La paridad es **por construcción** (la plataforma sirve
el motor del maestro, no lo reimplementa) y además se verifica con una prueba
automática de paridad con tolerancia cero. El archivo maestro local no se
modificó.

## Qué se corrigió respecto a la entrega anterior

- Ya no existe `AppModeManager.enterAdmin()` alcanzable en la pieza pública:
  la administración vive en rutas protegidas por sesión de servidor.
- Ya no hay PIN/contraseñas en JavaScript ni en HTML.
- Ya no hay redirecciones a `127.0.0.1`/localhost: todas las rutas son
  relativas o derivan de `BASE_URL`.
- `memoryFallback` dejó de ser "persistencia": los datos viven en MySQL; el
  modo memoria del navegador se usa solo para renderizar demo y reporte sin
  dejar rastro local.
- La demostración quedó aislada: datos ficticios fijos, sin escritura en la
  base real (prueba automática de conteo antes/después) y sin funciones
  administrativas.

## Cómo se instala

Guía paso a paso en `DEPLOY_HOSTINGER.md` (base MySQL en hPanel, usuario,
`schema.sql`, configuración privada fuera de `public_html`, HTTPS,
instalador, health-check, smoke test, respaldo y rollback). La versión local
temporal `public_html/diagnostico-bvm/` no se toca; la nueva se prueba en
`diagnostico-bvm-online-dev/` y se publica en `diagnostico-bvm-online/`.

## Cómo se usa

`docs/MANUAL_ADMIN_BVM.md` (equipo BVM) y `docs/GUIA_PARTICIPANTE.md`
(integrantes de la familia). Flujo: BVM crea la familia → comparte liga +
clave → los participantes responden desde sus dispositivos → BVM consulta el
avance → cierra → genera el reporte de 12 páginas.

## Pruebas ejecutadas

Todas automatizadas y en verde (evidencia en `qa-evidence/`):

- **Integración PHP** (32 verificaciones): hashes, aislamiento, restricción
  1–5 en base, conflicto de revisión, bloqueo tras finalizar, respaldos.
- **E2E de API** (~50 escenarios E01–E40): login/lockout/CSRF, familias,
  participante remoto multi-dispositivo, XSS, SQLi, IDOR, importación.
- **E2E de navegador** (Chromium): demo sin errores y en memoria, reporte
  real con 12 páginas `fits:true`, móvil 390×844.
- **Paridad metodológica**: Familia Horizonte por la ruta del maestro y por
  la ruta de la base — todos los indicadores idénticos (diferencia 0).
- **Evidencia de reporte**: 6 PDFs (demo/real/estrés × Carta/A4) emitidos
  tras auditoría de 12 páginas `fits:true`, 72 PNGs y 6 hojas de contacto
  inspeccionadas (ver `qa-evidence/MATRIZ_DE_PRUEBAS.md`).

## Pruebas pendientes (en el hosting final)

1. Health-check y suite de humo contra MySQL/MariaDB real de Hostinger (las
   pruebas locales usan SQLite funcionalmente equivalente; la capa PDO es
   común).
2. Impresión manual desde el diálogo del navegador (Carta y A4) como
   confirmación adicional a la auditoría automática.
3. Revisión de accesibilidad con lector de pantalla.
4. Validación del texto de privacidad/consentimiento por BVM y su asesor
   jurídico (el texto actual es configurable y provisional).

## Riesgos residuales y limitaciones

- Hosting compartido: sin HTTPS activo las cookies pierden el atributo
  Secure; activar SSL antes de usar con clientes reales (paso obligatorio en
  la guía).
- El rate limit se apoya en la base de datos (suficiente para el volumen
  esperado; no es una protección anti-DDoS de infraestructura).
- MVP con un administrador principal; el modelo de datos ya admite roles y
  asignaciones de consultor, pero la interfaz de gestión de usuarios
  adicionales queda para una iteración posterior.
- Los respaldos JSON exportados contienen respuestas: tratarlos como
  información confidencial (ver `docs/SEGURIDAD_Y_PRIVACIDAD.md`).

## Recomendación

**GO condicionado**: publicar en `diagnostico-bvm-online-dev/`, ejecutar los
4 puntos pendientes sobre el hosting real y, con ese smoke test en verde,
promover a `diagnostico-bvm-online/`. No se identifica ningún bloqueo de
producto ni de metodología.
