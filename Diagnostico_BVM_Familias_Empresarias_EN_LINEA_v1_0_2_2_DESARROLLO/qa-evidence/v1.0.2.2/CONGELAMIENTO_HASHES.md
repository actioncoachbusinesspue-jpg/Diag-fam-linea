# Congelamiento de referencias metodológicas — v1.0.2.2

Los dos archivos siguientes contienen el motor metodológico congelado
(A1–A20, escala 1–5, cuatro dimensiones, ponderaciones, fórmulas, umbrales,
matrices, reporte de 12 páginas y datos de Familia Horizonte).
**No se editan en disco en esta versión.** Cualquier ajuste de presentación del
reporte se aplica por inyección externa (`private/reference_presentation_patch.php`),
nunca sobre estos archivos.

## SHA-256 al INICIAR el HITO 0 (copia exacta desde v1.0.2.1)

| Archivo | SHA-256 |
|---|---|
| `reference/Diagnostico_BVM_Familias_Empresarias_EN_LINEA_DESARROLLO.html` | `5a89db7793c264e1b7ab930d5490b72b8f8d96e320c9b404e9c1a8d1104346bf` |
| `private/reference-app/referencia_app.html` | `5a89db7793c264e1b7ab930d5490b72b8f8d96e320c9b404e9c1a8d1104346bf` |

Ambos archivos son binariamente idénticos: `private/reference-app/` es la copia
que sirve la aplicación y `reference/` la referencia de auditoría.

## Verificación al CERRAR la versión

    sha256sum reference/Diagnostico_BVM_Familias_Empresarias_EN_LINEA_DESARROLLO.html \
              private/reference-app/referencia_app.html

El resultado debe coincidir exactamente con la tabla anterior.
La comprobación automatizada vive en `tests/e2e/static_checks.mjs`
(prueba «E-REF referencia metodológica congelada»), que compara el hash real
contra la constante registrada y falla la suite si difiere.
