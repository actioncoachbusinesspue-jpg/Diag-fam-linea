# Migración desde la versión local

## Qué se migra

Los respaldos JSON creados por la versión local estable
(`Diagnostico_BVM_Familias_Empresarias.html` → Administración → Crear
respaldo). Formato: `schemaVersion 5`, cuestionario `BVM-FE-1.2`.

## Ruta recomendada (pantalla)

1. En la versión local: **Crear respaldo** (descarga un `.json`).
2. En la versión en línea: **Acceso BVM → Importar respaldo**.
3. Seleccione el archivo (solo `.json`, máximo 2 MB).
4. Revise la **vista previa**: familia, participantes, finalizados, fecha y
   versión. Nada se importa todavía.
5. Elija:
   - **Crear como familia nueva** (recomendado): genera liga y clave nuevas y
     queda en estado *Cerrada* (datos históricos, no abre participación).
   - **Reemplazar una familia existente**: sustituye sus participaciones;
     exige escribir `REEMPLAZAR`.
6. Confirme. La importación corre en una transacción: si algo falla, la base
   queda exactamente como estaba (rollback automático) y se registra en
   auditoría.

Notas:

- Los respaldos de demostración (`demo-data`) se rechazan siempre.
- Un respaldo con `schemaVersion` distinto de 5 se rechaza con explicación
  (los cuestionarios anteriores no son comparables y nunca se reinterpretan).
- Los códigos personales no viajan en el respaldo (solo existen como hash);
  si la familia necesitara continuar respondiendo, regenere códigos desde la
  ficha de la familia.

## Ruta alternativa (línea de comandos)

```bash
php tools/import-local-backup.php ruta/al/respaldo.json
```

Muestra el resumen, pide confirmación (`SI`) e importa como familia nueva.

## Verificación posterior

Abra la radiografía de la familia importada y compare contra el reporte de la
versión local: los indicadores deben coincidir exactamente (misma metodología,
mismos datos). La prueba automatizada `tests/parity/parity_check.mjs`
demuestra esta equivalencia con Familia Horizonte.

## Exportar desde la versión en línea

Cada familia puede exportarse como JSON compatible (ficha de la familia →
*Exportar respaldo JSON*), utilizable de vuelta en la versión local o como
respaldo frío. Para respaldo integral de la base, use phpMyAdmin (ver
DEPLOY_HOSTINGER.md §11).
