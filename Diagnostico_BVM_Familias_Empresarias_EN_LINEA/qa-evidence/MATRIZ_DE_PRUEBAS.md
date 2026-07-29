# Matriz de pruebas — Diagnóstico BVM en línea (MVP 1.0.0)

Ejecución: 2026-07-29 · Entorno: PHP 8.4 (servidor embebido) + SQLite (equivalente
funcional del esquema MySQL) + Chromium vía playwright-core.
Logs completos en `qa-evidence/logs/`.

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
| E10 | Liga y clave generadas (slug aleatorio, PREFIJO-XXXX) | e2e_api | PASA |
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
| E32 | Móvil 375×667 | mismo CSS fluido validado en E31 | PASA* |
| E33 | Teclado y foco | foco gestionado por página (h1 tabindex, focus-visible); revisión manual pendiente en hosting | PARCIAL |
| E34 | Importación de respaldo local (previa+commit+rechazos) | e2e_api | PASA |
| E35 | Exportación de respaldo en línea | e2e_api | PASA |
| E36 | Familia cerrada no acepta registros (409) | e2e_api | PASA |
| E37 | Participante registrado puede terminar | e2e_api (E17-E18) | PASA |
| E38 | Demostración no cambia base real | e2e_api (conteo antes/después) | PASA |
| E39 | Dos dispositivos detectan conflicto (409 revisión) | e2e_api + integración | PASA |
| E40 | Cero errores JS visibles (demo y reporte) | e2e_browser (console/pageerror) | PASA |

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

## No ejecutado en este entorno

- Pruebas contra MySQL/MariaDB real (el esquema MySQL se entrega y la capa
  PDO es común; verificar con health-check en Hostinger).
- Generación de PDFs de estrés y hojas de contacto de 72 páginas.
- Revisión manual completa de accesibilidad con lector de pantalla.
