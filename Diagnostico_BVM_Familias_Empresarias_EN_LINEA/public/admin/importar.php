<?php
require_once dirname(__DIR__, 2) . '/private/bootstrap.php';
require_once BVM_PRIVATE_DIR . '/admin_layout.php';
$user = bvm_require_admin_page();
bvm_security_headers(null, true);
bvm_admin_header($user, 'Importar respaldo', 'importar', ['Familias' => 'familias.php', 'Importar respaldo' => null]);
?>
<h1>Importar respaldo de la versión local</h1>
<p class="hint">Recupere una familia respaldada desde la versión local (archivo .json).
   La importación nunca es automática: primero verá una vista previa y deberá confirmarla.</p>

<section class="panel">
  <h2>1. Seleccionar archivo</h2>
  <label for="backup-file">Archivo de respaldo (.json, máximo 2 MB)</label>
  <input id="backup-file" type="file" accept="application/json,.json">
  <div id="import-msg" role="alert"></div>
</section>

<section class="panel" id="preview-panel" hidden>
  <h2>2. Vista previa</h2>
  <div class="table-wrap">
    <table class="bvm-table">
      <tbody id="preview-body"></tbody>
    </table>
  </div>
  <h3 style="margin-top:18px;">3. ¿Cómo desea importar?</h3>
  <label class="scale-option"><input type="radio" name="import-mode" value="create" checked>
    <span><span class="lbl">Crear como familia nueva</span>
    <span class="desc">Se crea una familia cerrada con liga y clave nuevas. No afecta ninguna familia existente.</span></span></label>
  <label class="scale-option"><input type="radio" name="import-mode" value="replace">
    <span><span class="lbl">Reemplazar una familia existente</span>
    <span class="desc">Sustituye las participaciones de la familia seleccionada por las del respaldo. Requiere confirmación expresa.</span></span></label>
  <div id="replace-target" hidden>
    <label for="replace-select">Familia a reemplazar</label>
    <select id="replace-select"></select>
    <label for="replace-confirm">Escriba REEMPLAZAR para confirmar</label>
    <input id="replace-confirm" type="text" autocomplete="off">
  </div>
  <div class="form-actions">
    <button type="button" class="btn btn-primary" id="do-import">Confirmar importación</button>
    <button type="button" class="btn btn-quiet" id="cancel-import">Cancelar</button>
  </div>
</section>
<?php
$script = <<<'JS'
var parsedBackup = null;

function msg(kind, text) {
  document.getElementById('import-msg').innerHTML =
    text ? '<div class="alert alert-' + kind + '">' + BvmApi.escapeHtml(text) + '</div>' : '';
}

document.getElementById('backup-file').addEventListener('change', function () {
  var file = this.files[0];
  msg('', '');
  document.getElementById('preview-panel').hidden = true;
  if (!file) { return; }
  if (file.size > 2 * 1024 * 1024) { msg('error', 'El archivo excede 2 MB.'); return; }
  if (!/\.json$/i.test(file.name)) { msg('error', 'Solo se aceptan archivos .json.'); return; }
  var reader = new FileReader();
  reader.onload = function () {
    try { parsedBackup = JSON.parse(reader.result); }
    catch (e) { msg('error', 'El archivo no es un JSON válido.'); return; }
    BvmApi.post('/api/families/import.php', { phase: 'preview', backup: parsedBackup }).then(function (d) {
      if (!d.ok) { msg('error', d.error || 'El respaldo no es válido.'); return; }
      var p = d.preview;
      document.getElementById('preview-body').innerHTML =
        '<tr><th scope="row">Familia</th><td>' + BvmApi.escapeHtml(p.family_name) + '</td></tr>' +
        '<tr><th scope="row">Participantes</th><td>' + p.participants + ' (' + p.finished + ' finalizados)</td></tr>' +
        '<tr><th scope="row">Nombres</th><td>' + p.participant_names.map(BvmApi.escapeHtml).join(', ') + '</td></tr>' +
        '<tr><th scope="row">Fecha del respaldo</th><td>' + BvmApi.escapeHtml(p.export_date || '—') + '</td></tr>' +
        '<tr><th scope="row">Versión</th><td>Cuestionario ' + BvmApi.escapeHtml(p.questionnaire_version || '—') +
        ' · estructura ' + p.schema_version + '</td></tr>';
      document.getElementById('preview-panel').hidden = false;
      loadReplaceOptions();
    });
  };
  reader.readAsText(file);
});

function loadReplaceOptions() {
  fetch(BvmApi.base() + '/api/families/list.php?archivadas=1', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.ok) { return; }
      document.getElementById('replace-select').innerHTML = d.families.map(function (f) {
        return '<option value="' + f.id + '">' + BvmApi.escapeHtml(f.family_name) + ' (' + f.registered_count + ' participantes)</option>';
      }).join('');
    });
}

Array.prototype.forEach.call(document.querySelectorAll('input[name="import-mode"]'), function (radio) {
  radio.addEventListener('change', function () {
    document.getElementById('replace-target').hidden = radio.value !== 'replace' || !radio.checked;
  });
});

document.getElementById('cancel-import').addEventListener('click', function () {
  parsedBackup = null;
  document.getElementById('backup-file').value = '';
  document.getElementById('preview-panel').hidden = true;
  msg('', '');
});

document.getElementById('do-import').addEventListener('click', function () {
  var mode = document.querySelector('input[name="import-mode"]:checked').value;
  var payload = { phase: 'commit', backup: parsedBackup, mode: mode };
  if (mode === 'replace') {
    payload.replace_family_id = parseInt(document.getElementById('replace-select').value, 10);
    payload.confirm_replace = document.getElementById('replace-confirm').value.trim();
  }
  BvmApi.post('/api/families/import.php', payload).then(function (d) {
    if (!d.ok) { msg('error', d.error || 'La importación falló; no se realizó ningún cambio.'); return; }
    var extra = d.access_code
      ? ' Nueva clave de acceso (guárdela ahora): ' + d.access_code
      : '';
    msg('success', 'Importación completada: ' + d.imported_participants + ' participaciones.' + extra);
    document.getElementById('preview-panel').hidden = true;
    setTimeout(function () { window.location.href = 'familia.php?id=' + d.family_id; }, 2500);
  });
});
JS;
bvm_admin_footer($script);
