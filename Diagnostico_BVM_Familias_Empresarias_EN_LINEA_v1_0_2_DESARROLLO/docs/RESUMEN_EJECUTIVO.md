# Resumen ejecutivo de entrega — Diagnóstico BVM en línea, versión 1.0.2

Fecha: 2026-07-29 · Dirigido a: Dirección General BVM
Entrega: `Diagnostico_BVM_Familias_Empresarias_EN_LINEA_v1_0_2.zip`
(el resumen de la construcción original del MVP está en el CHANGELOG, 1.0.0)

La versión 1.0.2 es una **mejora controlada** sobre la 1.0.1: endurece cupo,
reanudación, claves, tiempo, sesiones, instalación y validación de base de
datos, y corrige la presentación de empates en el reporte. **No** cambia la
metodología (A1–A20, fórmulas, umbrales, narrativas, 12 páginas) ni pierde
datos existentes. El ZIP 1.0.1 y el HTML maestro local permanecen intactos
como puntos de restauración y referencia.

## Mejoras entregadas

1. **Cupo de participantes** — `enforce_participant_limit` por familia
   (límite real o solo referencia), 409 con mensaje claro al llenarse,
   reanudación nunca bloqueada, estados Disponible / Cerca del límite /
   Completo / Excedido con avisos sobrios al 80 % y 100 %, y garantía de
   concurrencia mediante transacción con `SELECT … FOR UPDATE` (verificada
   con procesos simultáneos reales sobre MariaDB).
2. **Reanudación indexada** — HMAC-SHA256 de localización + índice único:
   un candidato por consulta en lugar de recorrer a todos los participantes;
   fallback y backfill automático para participantes previos; el código
   personal sigue sin existir en claro en ninguna parte.
3. **Clave de familia** — 6 caracteres aleatorios (`ROBLES-8K4P7M`,
   configurable 6–10); las claves de 4 anteriores siguen funcionando sin
   regenerar nada.
4. **Zona horaria** — decisiones de apertura/cierre y presentación en
   `America/Mexico_City` (configurable y validada al arrancar); persistencia
   técnica en UTC; `report_date` editorial sin conversión.
5. **Sesiones aisladas** — nombre y ruta de cookie por ambiente
   (`BVMSESSID` + `/diagnostico-bvm-online/` vs `BVMDEVSESSID` +
   `/diagnostico-bvm-online-dev/`), borrado con atributos idénticos.
6. **Empates en el reporte** — parche de PRESENTACIÓN inyectado en memoria
   (el maestro en disco no se toca): «A / B», «Empate entre: A, B y C»,
   «Coincidencia equivalente en las cuatro dimensiones»; «Afirmaciones
   relativamente más consolidadas» cuando la madurez ya es baja; nota
   metodológica con alcance muestral. Paridad numérica: diferencia 0.
7. **Health-check integral** — 21 verificaciones OK/ADVERTENCIA/FALLA
   (incluye las 8 tablas, columnas e índice 1.0.2, InnoDB, utf8mb4,
   APP_KEY, timezone, base_url, sesiones, install_token, protección de
   carpetas, versión de esquema); solo CLI o ruta administrativa protegida
   (`admin/salud.php`); queda prohibido publicarlo.
8. **Instalación endurecida** — guía con `private/` fuera del área
   publicada como arquitectura recomendada; fallback `.htaccess` con prueba
   403 obligatoria y evidencia.
9. **Validación MySQL/MariaDB real** — `tests/mysql/` ejecutada contra
   MariaDB 10.11: integración completa, ENUM/CHECK/FK/unique/utf8mb4/
   rollback/FOR UPDATE, equivalencia migración-vs-esquema y concurrencia.
10. **Roles sin falsa seguridad** — Opción A: solo administrador; el rol
    consultor no puede crearse ni iniciar sesión; `family_assignments`
    queda documentada como preparación futura, sin afirmar aislamiento.

## Migraciones y compatibilidad

- `0003_enforce_participant_limit.sql` y `0004_resume_token_lookup.sql`
  (aditivas, reversibles, con instrucciones de respaldo).
- Actualización 1.0.1 → 1.0.2 sin pérdida: usuarios, familias,
  participantes, respuestas, estados, slugs, hashes, códigos, reportes y
  respaldos se conservan. Procedimiento en
  `docs/MIGRACION_1_0_1_A_1_0_2.md` (respaldos → código → migraciones →
  health-check → pruebas → rollback).
- Verificado en MariaDB: la base migrada 0001→0004 es estructuralmente
  idéntica a una instalación nueva con `schema.sql`.

## Archivos principales modificados o nuevos

- `private/`: bootstrap (tiempo), auth (sesiones), security (clave 6 +
  lookup HMAC), repositorios Family/Participant/AdminUser (cupo, lookup,
  roles), `health_checks.php` (nuevo), `reference_presentation_patch.php`
  (nuevo), reference_renderer, config.example, admin_layout.
- `public/`: api `participants/register|access`, `families/create|update|
  detail|list|import|export`, `auth/login`; admin `familias|familia|salud`
  (nuevo); assets `bvm-participante.js`, `bvm-api.js`, `bvm-app.css`.
- `database/`: `schema.sql`, migraciones 0003/0004, README.
- `tools/`: `health-check.php` (solo CLI), `.htaccess` (nuevo).
- `tests/`: integración ampliada (~60 casos nuevos), fixture SQLite, e2e
  (E50–E53 y ajustes), evidencia (familia de empates + asertos), `mysql/`
  (nuevo, 5 componentes).
- Documentación: README, CHANGELOG, DEPLOY_HOSTINGER, MANUAL_ADMIN,
  GUIA_PARTICIPANTE, SEGURIDAD_Y_PRIVACIDAD, MIGRACION_1_0_1_A_1_0_2
  (nuevo), MATRIZ_DE_PRUEBAS y este resumen.

Sin cambios: HTML maestro local, `reference/` congelada,
`private/reference-app/referencia_app.html` (byte a byte), cuestionario,
motor de cálculo y estructura de 12 páginas.

## Pruebas

**Ejecutadas en esta entrega — todas en verde (logs en `qa-evidence/logs/`):**

- Integración PHP (SQLite): flujo completo + cupo + lookup + tiempo +
  sesiones + roles.
- Paridad metodológica de Familia Horizonte con el parche de empates
  activo: diferencia 0 en todos los indicadores.
- E2E de API + verificaciones estáticas + navegador (Chromium): regresión
  1.0.1 completa y casos nuevos de cupo (E50–E53).
- Suite MySQL sobre **MariaDB 10.11 real**: integración completa,
  específicas de motor, equivalencia de esquema y concurrencia (5 rondas
  de disputa del último lugar con procesos reales).
- Evidencia de reporte: **8 PDFs** (demo/real/estrés/empates × Carta/A4),
  cada uno con 12 páginas exactas y `fits:true`, 96 PNGs y 8 hojas de
  contacto; asertos de empate 2/3/4 y de máximo único en el navegador.

**Preparadas pero no ejecutadas aquí:** ninguna.

**Pendiente en Hostinger:** health-check sobre el servidor real, prueba
funcional de DEPLOY_HOSTINGER.md §8 (incluye cupo y URL directa a
`private/`), impresión manual de verificación, y el MySQL/MariaDB de
Hostinger específicamente.

## Riesgos residuales

- La validación de base de datos se hizo sobre MariaDB 10.11 local; el
  motor específico de Hostinger se confirma con el health-check y la prueba
  funcional del despliegue (riesgo bajo: capa PDO común, esquema estándar).
- En el fallback de instalación (Opción B), la protección de `private/`
  depende de `.htaccess`: la prueba 403 con evidencia es obligatoria antes
  de aprobar.
- El reporte administrativo carga los datos de la familia en el navegador
  del administrador (necesario para el motor); mitigado con sesión,
  `no-store` y sin persistencia.
- Accesibilidad: revisión manual con lector de pantalla pendiente.
- Los respaldos JSON exportados contienen respuestas: tratarlos como
  información confidencial.

## Instalación y rollback

- Instalación nueva: `DEPLOY_HOSTINGER.md` (arquitectura recomendada, 8
  tablas, health-check por terminal, aislamiento dev/prod).
- Actualización: `docs/MIGRACION_1_0_1_A_1_0_2.md`.
- Rollback: restaurar archivos 1.0.1 (ZIP intacto); los datos no requieren
  cambios para volver; reversa SQL opcional documentada en cada migración.

## Lista de verificación para el encargado (Hostinger)

1. Respaldo SQL de la base 1.0.1.
2. Respaldo de archivos 1.0.1.
3. Crear/usar el ambiente dev separado.
4. Configurar PHP 8.2.
5. Configurar la base dev propia.
6. Configurar `app.timezone`.
7. Configurar `session_name` de dev.
8. Configurar `session_cookie_path` de dev.
9. Configurar `APP_KEY`.
10. Ejecutar migraciones 0003 y 0004.
11. Ejecutar `php tools/health-check.php` por terminal.
12. Confirmar 8 tablas y columnas nuevas (el health-check lo reporta).
13. Crear una familia de prueba (esperados 2, límite activo).
14. Probar el cupo (tercer registro rechazado; reanudación intacta).
15. Probar desde dos dispositivos.
16. Probar la reanudación con código personal.
17. Probar el reporte en Carta y A4 (12 páginas exactas).
18. Cerrar sesión (la cookie desaparece).
19. Probar URL directa a `private/` → 403/404.
20. Obtener aprobación formal.
21. Repetir en producción con base separada y cookies de producción.
22. Conservar el ZIP 1.0.1 para rollback.

## Recomendación

**GO condicionado a staging**: la versión 1.0.2 está lista para instalarse
en el ambiente de desarrollo de Hostinger. Con el health-check en verde y
la prueba funcional aprobada ahí (pasos 3–19), procede publicar en
producción. No queda ningún hallazgo abierto en el código ni en las pruebas
ejecutadas.
