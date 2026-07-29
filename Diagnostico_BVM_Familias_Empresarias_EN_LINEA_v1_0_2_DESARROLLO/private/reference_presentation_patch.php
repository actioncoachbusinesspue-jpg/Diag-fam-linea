<?php
declare(strict_types=1);

/**
 * reference_presentation_patch.php — parche de PRESENTACIÓN exclusivo de la
 * versión en línea (1.0.2, Mejora 6). Se inyecta en memoria mediante
 * reference_renderer.php; el HTML maestro en disco NUNCA se modifica.
 *
 * Qué hace (y qué no):
 *   1. Empates en «Dimensión de mayor coincidencia / mayor diferencia»
 *      (pantalla, impresión, demo y reporte real): cuando dos o más
 *      dimensiones muestran el MISMO valor de dispersión ya calculado,
 *      se nombran todas en lugar de una sola.
 *        - empate de dos:  «A / B»
 *        - empate de tres: «Empate entre: A, B y C»
 *        - empate de cuatro: «Coincidencia equivalente en las cuatro
 *          dimensiones» (o «Diferencia equivalente…» en la tarjeta de
 *          mayor diferencia).
 *   2. Cuando el nivel de madurez EXISTENTE de una dimensión es bajo
 *      («No existe» o «Incipiente»), el encabezado de evidencia dice
 *      «Afirmaciones relativamente más consolidadas». No se crea ningún
 *      umbral nuevo: se usa la clasificación de madurez ya calculada.
 *   3. La nota metodológica declara que los indicadores describen las
 *      percepciones de quienes participaron y no constituyen una
 *      estimación estadística de una población más amplia.
 *
 * NO cambia fórmulas, puntuaciones, umbrales ni metodología: trabaja sobre
 * la estructura de análisis ya calculada y solo ajusta texto de salida.
 * Resuelve empates por igualdad exacta del valor mostrado (redondeado a 2
 * decimales por el propio motor).
 */

function bvm_reference_presentation_patch(): string
{
    return <<<'HTML'
<script>
/* BVM en línea 1.0.2 — parche de presentación (empates y textos editoriales).
   Trabaja sobre los resultados ya calculados; no altera ningún cálculo. */
(function(){
  'use strict';
  if (typeof AdminDashboard === 'undefined') { return; }

  function officialLabels(){
    var labels = {};
    QUESTIONNAIRE.dimensions.forEach(function(d){ labels[d.id] = d.name; });
    return labels;
  }

  /* Dimensiones empatadas en el extremo pedido, por igualdad EXACTA del
     valor mostrado (el motor ya redondea la dispersión a 2 decimales). */
  function tiedDimensions(valueMap, extreme){
    var dims = Object.keys(valueMap);
    var best = null;
    dims.forEach(function(d){
      var v = valueMap[d];
      if (best === null) { best = v; return; }
      best = (extreme === 'min') ? Math.min(best, v) : Math.max(best, v);
    });
    return dims.filter(function(d){ return valueMap[d] === best; });
  }

  function tieValueText(names, allEqualText){
    if (names.length === 2) { return names[0] + ' / ' + names[1]; }
    if (names.length === 3) { return 'Empate entre: ' + names[0] + ', ' + names[1] + ' y ' + names[2]; }
    return allEqualText; // cuatro dimensiones equivalentes
  }

  /* Reemplaza una tarjeta de indicador manteniendo la estructura original.
     Reduce ligeramente la fuente cuando el texto es largo para conservar
     la página 8 sin desbordamiento (Carta y A4). */
  function replaceIndicatorCard(html, singularLabel, pluralLabel, originalValue, names, allEqualText){
    if (names.length <= 1) { return html; }
    var text = tieValueText(names, allEqualText);
    var size = names.length === 2 ? '13.5px' : '12.5px';
    var from = '<div class="ind-label">' + singularLabel + '</div><div class="ind-value" style="font-size:17px;">' + originalValue + '</div>';
    var to = '<div class="ind-label">' + pluralLabel + '</div><div class="ind-value" style="font-size:' + size + ';">' + text + '</div>';
    return html.split(from).join(to);
  }

  /* 1) Empates en la alineación (pantalla + página 8 del reporte + demo). */
  var origAlineacionBody = AdminDashboard.renderAlineacionBody;
  AdminDashboard.renderAlineacionBody = function(c, opts){
    var html = origAlineacionBody.call(this, c, opts);
    try {
      var labels = officialLabels();
      var disp = c.alignment.dimensionDispersion;
      var minNames = tiedDimensions(disp, 'min').map(function(d){ return labels[d]; });
      var maxNames = tiedDimensions(disp, 'max').map(function(d){ return labels[d]; });
      html = replaceIndicatorCard(
        html,
        'Dimensión de mayor coincidencia',
        'Dimensiones de mayor coincidencia',
        labels[c.alignment.mostAlignedDimension],
        minNames,
        'Coincidencia equivalente en las cuatro dimensiones'
      );
      html = replaceIndicatorCard(
        html,
        'Dimensión de mayor diferencia',
        'Dimensiones de mayor diferencia',
        labels[c.alignment.mostDispersedDimension],
        maxNames,
        'Diferencia equivalente en las cuatro dimensiones'
      );
    } catch (e) { /* ante cualquier imprevisto, conservar la salida original */ }
    return html;
  };

  /* 2) Evidencia de dimensión: si el nivel de madurez ya calculado es bajo,
        «Afirmaciones relativamente más consolidadas». Sin umbrales nuevos. */
  var origDimCard = AdminDashboard.renderDimensionCardContent;
  AdminDashboard.renderDimensionCardContent = function(c, dimId, opts){
    var html = origDimCard.call(this, c, dimId, opts);
    try {
      var level = getMaturityLevel(c.scoring.normalizedDimensionScores[dimId]);
      if (level && (level.id === 'no_existe' || level.id === 'incipiente')) {
        html = html.split('>Afirmaciones más consolidadas<')
                   .join('>Afirmaciones relativamente más consolidadas<');
      }
    } catch (e) { /* conservar la salida original */ }
    return html;
  };

  /* 3) Nota metodológica: alcance muestral explícito. */
  var SAMPLE_SCOPE_NOTE = 'Los indicadores describen las percepciones de quienes participaron y no constituyen una estimación estadística de una población más amplia.';
  var origMethodology = AdminDashboard.renderMethodologyBody;
  AdminDashboard.renderMethodologyBody = function(){
    var html = origMethodology.apply(this, arguments);
    try {
      if (html.indexOf('no constituyen una estimación estadística') === -1) {
        var anchor = INTERPRETATION_RULES.disclaimers.noValidation;
        html = html.split(anchor).join(anchor + ' ' + SAMPLE_SCOPE_NOTE);
      }
    } catch (e) { /* conservar la salida original */ }
    return html;
  };
})();
</script>
HTML;
}
