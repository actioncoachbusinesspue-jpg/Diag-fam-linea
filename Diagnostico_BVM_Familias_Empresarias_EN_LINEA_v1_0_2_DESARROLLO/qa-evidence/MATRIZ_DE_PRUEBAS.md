# Matriz de pruebas — Diagnóstico BVM en línea (versión 1.0.2)

Ejecución: 2026-07-29 · Entorno: PHP 8.4 (servidor embebido) + SQLite +
**MariaDB 10.11 real** + Chromium vía playwright-core.
Logs completos en `qa-evidence/logs/` (integracion, paridad, e2e, mysql,
evidencia).

## Pruebas obligatorias 1.0.2 (sección 18 del prompt de mejora)

| Bloque | Casos | Cobertura | Resultado |
|---|---|---|---|
| A. Registro y cupo (1–10) | expected NULL sin límite; 3 de 3 permitidos; 4.º → 409 family_full; reanudar con cupo lleno; disputa simultánea del último lugar (un ganador, un 409, conteo exacto — 5 rondas con procesos reales sobre MariaDB); aumentar cupo permite registro; desactivar límite permite excedente; modo referencia muestra excedido; familia cerrada rechaza; duplicado no consume cupo | integración + e2e_api E50-E53 + tests/mysql/concurrency | PASA |
| B. Código personal (11–18) | lookup hash al crear; reanudación por índice (un candidato); código incorrecto falla; legacy con lookup NULL funciona; backfill tras acierto; regenerar invalida el anterior; HMAC ausente del frontend (no viaja en respuestas); código ausente de logs | integración + e2e_api | PASA |
| C. Clave de familia (19–22) | 6 caracteres aleatorios; clave de 4 previa sigue verificando; regeneración con formato nuevo; rate limit intacto | integración + e2e_api E10/E12 | PASA |
| D. Tiempo (23–27) | apertura/cierre en America/Mexico_City; cierre inclusivo todo el día; UTC adelantado no cambia el estado local; report_date conserva el día; health-check valida timezone | integración (12 casos) + health-check | PASA |
| E. Sesiones (28–34) | nombre configurable por ambiente; ruta de cookie normalizada y con fallback; logout borra con los mismos atributos; Secure bajo HTTPS; HttpOnly + SameSite=Lax | integración + e2e_api (logout invalida sesión) | PASA |
| F. Empates (35–42) | máximo único intacto; empate de 2 con « / »; empate de 3 «Empate entre…»; empate de 4 «equivalente en las cuatro»; mínimo empatado (misma tarjeta de mayor diferencia); Carta y A4 de la familia de empates con 12 páginas `fits:true`, página 8 sin overflow | tests/evidence (tie_letter/tie_a4 + asertos en navegador) | PASA |
| G. Seguridad y roles (43–53) | participante sin acceso admin; aislamiento entre familias; consultor deshabilitado (crear lanza error; login/sesión bloqueados); CSRF; SQLi; XSS; IDOR; sin localhost en código desplegable; sin credenciales; private/database protegidos (estructural o .htaccess + prueba 403 documentada) | e2e_api + static_checks + integración + health-check | PASA |
| H. MySQL (54–60) | schema nuevo; migración 0001→0004 estructuralmente idéntica a schema.sql; rollback documentado (en migraciones y MIGRACION_1_0_1_A_1_0_2); FOR UPDATE con bloqueo real; transacciones y rollback; índices; utf8mb4 de 4 bytes | tests/mysql sobre MariaDB 10.11 | PASA |
| I. Regresión (61–74) | login, familias, A1–A20, externas, autosave, conflicto 409, bloqueo 423, importación, exportación, demo, reporte, móvil, cero errores JS, cero errores PHP visibles | e2e_api + e2e_browser + static_checks | PASA |

## Matriz 1.0.1 (regresión completa re-ejecutada en 1.0.2)

## Escenarios E01–E40 del prompt maestro

| # | Escenario | Cobertura | Resultado |
|---|---|---|---|
| E01 | Carga pública | e2e_api | PASA |
| E02 | Demostración sin login | e2e_api + e2e_browser | PASA |
| E03 | Acceso directo a admin redirige a login | e2e_api | PASA |
| E04 | Login incorrecto (mensaje genérico) | e2e_api | PASA |
| E05 | Bloqueo por intentos (429) | e2e_api | PASA |
| E06 | Login correcto | e2e_api + e2e_browser | PASA |
| E07 | Cierre de sesión invalida la sesión | e2e_api | PASA |
| E08 | CSRF ausente rechazado (403) | e2e_api | PASA |
| E09 | Crear familia | e2e_api | PASA |
| E10 | Liga y clave generadas (slug aleatorio, PREFIJO-XXXXXX desde 1.0.2) | e2e_api | PASA |
| E11 | Abrir liga desde contexto distinto | e2e_api (jar de cookies separado) | PASA |
| E12 | Clave incorrecta rechazada (401) | e2e_api | PASA |
| E13 | Registro de participante con consentimiento | e2e_api | PASA |
| E14 | Código personal generado (XXXX-XXXX, solo hash) | e2e_api + integración | PASA |
| E15 | Guardar 20 respuestas (autosave transaccional) | e2e_api + integración | PASA |
| E16 | Cerrar navegador sin perder datos | e2e_api (jar nuevo) | PASA |
| E17 | Reanudar desde otro contexto con código personal | e2e_api | PASA |
| E18 | Finalizar | e2e_api + integración | PASA |
| E19 | Editar tras finalizar → 423 bloqueado | e2e_api + integración | PASA |
| E20 | Segundo participante desde otro equipo | e2e_api | PASA |
| E21 | Admin ve avance actualizado (2 registrados, 1 finalizado) | e2e_api | PASA |
| E22 | Participante no ve resultados ni admin (401/302) | e2e_api | PASA |
| E23 | Clave de familia A no accede a familia B | e2e_api + integración | PASA |
| E24 | ID manipulado → 404 sin filtrar datos | e2e_api | PASA |
| E25 | XSS almacenado escapado en admin | e2e_api | PASA |
| E26 | SQL injection rechazada (validación + PDO preparado) | e2e_api + integración | PASA |
| E27 | Reporte real con datos del servidor | e2e_api + e2e_browser | PASA |
| E28 | Reporte demo (Familia Horizonte) | e2e_browser (demo activa el mismo motor) | PASA |
| E29 | Carta 12 páginas | e2e_browser: `prepareBvmPrint()` = 12 páginas, `fits:true` | PASA* |
| E30 | A4 12 páginas | mismo motor/auditoría del maestro validado en E29 | PASA* |
| E31 | Móvil 390×844 sin desborde | e2e_browser | PASA |
| E32 | Móvil 375×667 | e2e_browser (viewport dedicado) | PASA |
| E33 | Teclado y foco | foco gestionado por página (h1 tabindex, focus-visible); revisión manual pendiente en hosting | PARCIAL |
| E34 | Importación de respaldo local (previa+commit+rechazos) | e2e_api | PASA |
| E35 | Exportación de respaldo en línea | e2e_api | PASA |
| E36 | Familia cerrada no acepta registros (409) | e2e_api | PASA |
| E37 | Participante registrado puede terminar | e2e_api (E17-E18) | PASA |
| E38 | Demostración no cambia base real | e2e_api (conteo antes/después) | PASA |
| E39 | Dos dispositivos detectan conflicto (409 revisión) | e2e_api + integración | PASA |
| E40 | Cero errores JS visibles (demo y reporte) | e2e_browser (console/pageerror) | PASA |

## Escenarios E45–E50 (numeración del prompt maestro)

| # | Escenario | Cobertura | Resultado |
|---|---|---|---|
| E45 | Cero errores JS visibles | e2e_browser (equivale a E40 de la tabla anterior) | PASA |
| E46 | Cero errores PHP visibles (sin trazas, SQL ni rutas internas en errores de API) | static_checks | PASA |
| E47 | Ninguna ruta contiene localhost/127.0.0.1 (incluida la redirección 127.0.0.1:8099 de la entrega anterior) | static_checks (código desplegable + demo servida) | PASA |
| E48 | AppModeManager.enterAdmin no disponible en público: neutralizado al servir la demo y verificado desde consola | static_checks + e2e_browser | PASA |
| E49 | Sin password/PIN utilizables en frontend (assets propios y demo servida con PIN deshabilitado y vacío) | static_checks | PASA |
| E50 | Recarga/reingreso conserva datos desde el servidor | e2e_api E16-E17 (jar de cookies nuevo + reanudación con código personal) | PASA |

\* La auditoría geométrica `__BVM_PRINT_QA__` es la del maestro (idéntica);
Carta/A4 usan la misma caja física auditada. Se recomienda una impresión
manual de verificación en el hosting final (DEPLOY_HOSTINGER.md §9.7).

## Paridad metodológica (bloqueo de entrega si difiere)

`tests/parity/parity_check.mjs` — resultado: **PARIDAD EXACTA** (ver
`qa-evidence/parity/parity_report.json`).

- Horizonte por la ruta del maestro: Relaciones 69, Gobierno 56,
  Desarrollo 58, Continuidad 37, Índice general 55 (Parcial) — coincide con
  los valores documentados en el maestro.
- Horizonte por la ruta en línea (respuestas guardadas una a una en la base y
  exportadas por `FamilyDataService`): **todos** los indicadores idénticos
  (promedios por afirmación, dimensiones crudas y normalizadas, índice
  general, nivel, externas, dispersión por afirmación/dimensión/general y
  alineación).

## Integración de backend

`tests/integration/run_integration.php` — 32 verificaciones, todas PASAN
(hash de claves, aislamiento, restricción 1-5 en base, conflicto de revisión,
bloqueo tras finalizar, formato de exportación).

## Evidencia de reporte (sección 19 del prompt maestro)

Generada con `bash tests/evidence/run_evidence.sh` (Chromium + servidor PHP
embebido). Cada documento se emitió **después** de que la auditoría geométrica
del maestro (`prepareBvmPrint`) confirmara 12 páginas con `fits:true`; el
script termina con error ante cualquier desviación.

| Documento | Caso | Páginas | Auditoría | PDF | PNG | Hoja de contacto |
|---|---|---|---|---|---|---|
| demo_letter | Familia Horizonte (demo) | 12 | fits:true | `pdf/demo_letter.pdf` | `png/demo_letter/` | `contact-sheets/demo_letter_contacto.png` |
| demo_a4 | Familia Horizonte (demo) | 12 | fits:true | `pdf/demo_a4.pdf` | `png/demo_a4/` | `contact-sheets/demo_a4_contacto.png` |
| real_letter | Familia Robles (2 de 3 finalizados → lectura preliminar) | 12 | fits:true | `pdf/real_letter.pdf` | `png/real_letter/` | `contact-sheets/real_letter_contacto.png` |
| real_a4 | Familia Robles | 12 | fits:true | `pdf/real_a4.pdf` | `png/real_a4/` | `contact-sheets/real_a4_contacto.png` |
| stress_letter | Familia de estrés: 12 esperados, 10 registrados (8 finalizados, 2 incompletos), nombres largos, todos-altos, todos-bajos, patrón de empate, resultados mixtos | 12 | fits:true | `pdf/stress_letter.pdf` | `png/stress_letter/` | `contact-sheets/stress_letter_contacto.png` |
| stress_a4 | Familia de estrés | 12 | fits:true | `pdf/stress_a4.pdf` | `png/stress_a4/` | `contact-sheets/stress_a4_contacto.png` |
| tie_letter | Familia de empates (1.0.2): dispersión idéntica por pares de dimensiones y madurez baja en las cuatro | 12 | fits:true | `pdf/tie_letter.pdf` | `png/tie_letter/` | `contact-sheets/tie_letter_contacto.png` |
| tie_a4 | Familia de empates | 12 | fits:true | `pdf/tie_a4.pdf` | `png/tie_a4/` | `contact-sheets/tie_a4_contacto.png` |

Las 72 páginas se renderizaron a PNG (`qa-evidence/png/`) y se inspeccionaron
visualmente mediante las hojas de contacto: portadas correctas (incluida la
leyenda "X de Y participaciones finalizadas"), etiqueta "CASO FICTICIO —
DEMOSTRACIÓN BVM" persistente en la demo, cero páginas vacías, cero página 13,
cero cortes ni colisiones de pie, páginas 8-11 completas y página 12 con el
cierre premium intacto.

## No ejecutado en este entorno

- Pruebas contra el MySQL de Hostinger específicamente (la suite completa
  SÍ se ejecutó contra MariaDB 10.11 real en local — ver
  `qa-evidence/logs/mysql.log`; queda pendiente repetir el health-check y
  la prueba funcional en el servidor de Hostinger).
- Impresión manual desde el diálogo del navegador en el hosting final
  (DEPLOY_HOSTINGER.md §8) como confirmación adicional a la auditoría.
- Revisión manual completa de accesibilidad con lector de pantalla.
