# CHANGELOG — Diagnóstico BVM en línea

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
