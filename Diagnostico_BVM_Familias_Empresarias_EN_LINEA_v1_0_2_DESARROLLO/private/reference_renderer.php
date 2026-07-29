<?php
declare(strict_types=1);

/**
 * reference_renderer.php — sirve el motor metodológico de referencia
 * (copia íntegra del archivo maestro estable) en dos modos:
 *
 *   - 'demo'   : Familia Horizonte, pública, aislada y en memoria.
 *   - 'report' : radiografía y reporte de una familia real (solo admin),
 *                con los datos inyectados desde MySQL.
 *
 * El HTML de referencia NUNCA se modifica en disco: la adaptación se hace
 * en memoria al servirlo, mediante dos inyecciones:
 *   1. En <head>: deshabilita localStorage → StorageAdapter usa su fallback
 *      de memoria (los datos reales jamás persisten en el navegador).
 *   2. Al final del <body>: arranque del modo correspondiente.
 *
 * Así la paridad metodológica es por construcción: fórmulas, cuestionario,
 * interpretación y reporte de 12 páginas son EXACTAMENTE los del maestro.
 */

function bvm_reference_app_html(): string
{
    $path = BVM_PRIVATE_DIR . '/reference-app/referencia_app.html';
    $html = @file_get_contents($path);
    if ($html === false) {
        http_response_code(503);
        bvm_security_headers(null, true);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Falta el motor de referencia (private/reference-app/referencia_app.html).\n";
        exit;
    }
    return $html;
}

/**
 * @param string     $mode      'demo' | 'report'
 * @param array|null $familyData objeto familia (formato StorageAdapter) para 'report'
 * @param string     $returnUrl  a dónde regresa el botón "salir"
 */
function bvm_render_reference_app(string $mode, ?array $familyData, string $returnUrl): void
{
    $html = bvm_reference_app_html();

    // 1) Deshabilitar localStorage ANTES de que el motor lo pruebe:
    //    testAvailability() captura la excepción y activa el modo memoria.
    $disableStorage = <<<HTML
<script>
/* BVM en línea: los datos viven en el servidor; el navegador solo usa memoria. */
(function(){
  try{
    Object.defineProperty(window, 'localStorage', {
      configurable: false,
      get: function(){ throw new Error('BVM-online: almacenamiento local deshabilitado'); }
    });
  }catch(e){}
})();
</script>
HTML;

    $returnUrlJson = json_encode($returnUrl, JSON_UNESCAPED_SLASHES);

    if ($mode === 'report') {
        $familyJson = json_encode(
            $familyData,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        $bootstrap = <<<HTML
<script>
/* BVM en línea — arranque en modo radiografía/reporte con datos del servidor. */
document.addEventListener('DOMContentLoaded', function(){
  var fam = $familyJson;
  // El recorrido de bienvenida no aplica al uso administrativo en línea.
  OnboardingManager.runSequence = function(){};
  GuidedTourManager.hasCompletedOrSkipped = function(){ return true; };
  StorageAdapter.saveStore({ families: [fam], activeFamilyId: fam.familyId }, 'real');
  AppModeManager.exitToSelector = function(){ window.location.href = $returnUrlJson; };
  AppModeManager.enterAdmin();
});
</script>
HTML;
    } else {
        $bootstrap = <<<HTML
<script>
/* BVM en línea — arranque directo de la demostración Familia Horizonte. */
document.addEventListener('DOMContentLoaded', function(){
  AppModeManager.exitToSelector = function(){ window.location.href = $returnUrlJson; };
  // Sección 15 del prompt maestro: en la pieza pública no debe existir un
  // camino al modo administrativo, ni siquiera desde la consola. La
  // administración real vive en el servidor (admin/ + sesión); aquí la
  // llamada se neutraliza. openAdmin() del selector resuelve
  // AppModeManager.enterAdmin en el momento de la llamada, así que esta
  // sustitución también anula ese camino.
  AppModeManager.enterAdmin = function(){
    console.info('Demostración BVM: la administración no está disponible aquí; requiere iniciar sesión en el servidor.');
  };
  AppModeManager.enterCommercial();
});
</script>
HTML;
    }

    // Inyecciones sin tocar el archivo en disco. El parche de presentación
    // (empates y textos editoriales 1.0.2) se inserta ANTES del arranque para
    // que los envoltorios estén activos desde el primer render.
    require_once BVM_PRIVATE_DIR . '/reference_presentation_patch.php';
    $presentationPatch = bvm_reference_presentation_patch();
    $html = preg_replace('/<head>/', '<head>' . "\n" . $disableStorage, $html, 1);
    $html = str_replace('</body></html>', $presentationPatch . "\n" . $bootstrap . "\n</body></html>", $html);

    bvm_security_headers(bvm_csp_reference_app(), true);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}
