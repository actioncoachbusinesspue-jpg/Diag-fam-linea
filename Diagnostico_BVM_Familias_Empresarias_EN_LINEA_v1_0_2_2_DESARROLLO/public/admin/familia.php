<?php
require_once dirname(__DIR__) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
require_once BVM_PRIVATE_DIR . '/admin_layout.php';
$user = bvm_require_admin_page();
bvm_security_headers(null, true);

$familyId = (int)($_GET['id'] ?? 0);
$family = $familyId > 0 ? FamilyRepository::findById($familyId) : null;
if (!$family) {
    http_response_code(404);
    bvm_admin_header($user, 'Familia no encontrada', 'familias', ['Familias' => 'familias.php', 'No encontrada' => null]);
    echo '<div class="alert alert-error">La familia solicitada no existe.</div><p><a class="btn btn-secondary" href="familias.php">Volver a familias</a></p>';
    bvm_admin_footer();
    exit;
}

bvm_admin_header($user, (string)$family['family_name'], 'familias', [
    'Familias' => 'familias.php',
    (string)$family['family_name'] => null,
]);
?>
<h1 id="family-title"><?= e((string)$family['family_name']) ?></h1>
<div id="page-msg" role="status" aria-live="polite"></div>

<section class="panel" aria-labelledby="resumen-t">
  <h2 id="resumen-t">Avance</h2>
  <div id="summary" class="hint">Cargando…</div>
  <div id="capacity-note" aria-live="polite"></div>
</section>

<section class="panel" aria-labelledby="invitacion-t">
  <h2 id="invitacion-t">Invitación</h2>
  <p>Liga pública de esta familia:</p>
  <p><code id="invite-url"></code>
     <button type="button" class="btn btn-quiet" id="copy-url">Copiar liga</button></p>
  <p class="hint">La clave de acceso solo se muestra al crearla o regenerarla. Si la familia la perdió, regenérela y compártala de nuevo (la anterior deja de funcionar).</p>
  <button type="button" class="btn btn-secondary" id="regen-key">Regenerar clave de acceso</button>
  <div id="key-result"></div>
</section>

<section class="panel" aria-labelledby="config-t">
  <h2 id="config-t">Configuración</h2>
  <form id="config-form">
    <div class="form-row">
      <div>
        <label for="cfg-name">Nombre</label>
        <input id="cfg-name" type="text" maxlength="150" required>
      </div>
      <div>
        <label for="cfg-expected">Participantes esperados</label>
        <input id="cfg-expected" type="number" min="1" max="500">
      </div>
    </div>
    <label class="scale-option" style="margin-top:6px;">
      <input type="checkbox" id="cfg-enforce-limit">
      <span><span class="lbl">Cerrar nuevos registros al alcanzar el número esperado</span>
      <span class="desc">Al activarlo, las personas ya registradas podrán continuar, pero no se aceptarán
      nuevos participantes cuando se alcance el cupo. Si lo desactiva, el número esperado es solo una meta
      y el excedente se muestra como referencia.</span></span>
    </label>
    <div class="form-row">
      <div>
        <label for="cfg-opens">Fecha de apertura</label>
        <input id="cfg-opens" type="date">
      </div>
      <div>
        <label for="cfg-closes">Fecha de cierre</label>
        <input id="cfg-closes" type="date">
      </div>
    </div>
    <p class="hint">Fechas interpretadas en hora de Ciudad de México. La familia abre a las 00:00 de la
    fecha de apertura y acepta respuestas durante todo el día de la fecha de cierre.</p>
    <div class="form-row">
      <div>
        <label for="cfg-status">Estado</label>
        <select id="cfg-status">
          <option value="borrador">Borrador (aún no acepta respuestas)</option>
          <option value="abierta">Abierta (acepta respuestas)</option>
          <option value="cerrada">Cerrada (ya no acepta respuestas)</option>
          <option value="archivada">Archivada</option>
        </select>
      </div>
      <div>
        <label for="cfg-report-date">Fecha del reporte</label>
        <input id="cfg-report-date" type="date">
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Guardar cambios</button>
    </div>
  </form>
</section>

<section class="panel" aria-labelledby="participantes-t">
  <h2 id="participantes-t">Participantes</h2>
  <div class="table-wrap">
    <table class="bvm-table">
      <thead>
        <tr>
          <th scope="col">Nombre</th>
          <th scope="col">Perfil</th>
          <th scope="col">Estatus</th>
          <th scope="col">Avance</th>
          <th scope="col"><span class="sr-only">Acciones</span></th>
        </tr>
      </thead>
      <tbody id="participants-body"><tr><td colspan="5" class="hint">Cargando…</td></tr></tbody>
    </table>
  </div>
  <p class="hint">Por confidencialidad, esta pantalla nunca muestra respuestas individuales, únicamente estatus y avance.</p>
</section>

<section class="panel" aria-labelledby="acciones-t">
  <h2 id="acciones-t">Resultados y respaldos</h2>
  <div class="form-actions" style="margin-top:0;">
    <a class="btn btn-primary" href="reporte.php?id=<?= (int)$family['id'] ?>">Abrir radiografía y reporte</a>
    <a class="btn btn-secondary" href="<?= e(bvm_base_url()) ?>/api/families/export.php?id=<?= (int)$family['id'] ?>">Exportar respaldo JSON</a>
  </div>
</section>

<section class="panel" aria-labelledby="peligro-t">
  <h2 id="peligro-t">Zona de cuidado</h2>
  <p class="hint">Eliminar una familia borra definitivamente sus participaciones y respuestas. Esta acción requiere doble confirmación y no puede deshacerse.</p>
  <button type="button" class="btn btn-danger" id="delete-btn">Eliminar familia definitivamente</button>
  <div id="delete-confirm" hidden style="margin-top:12px;">
    <label for="delete-name">Escriba el nombre exacto de la familia para confirmar</label>
    <input id="delete-name" type="text" autocomplete="off">
    <div class="form-actions">
      <button type="button" class="btn btn-danger" id="delete-final">Confirmar eliminación definitiva</button>
      <button type="button" class="btn btn-quiet" id="delete-cancel">Cancelar</button>
    </div>
  </div>
</section>
<?php
$familyIdJs = (int)$family['id'];
$script = <<<JS
var FAMILY_ID = $familyIdJs;
JS;
$script .= <<<'JS'

function fmtDate(v) {
  if (!v) { return '—'; }
  var d = new Date(String(v).replace(' ', 'T') + (String(v).indexOf('Z') === -1 ? 'Z' : ''));
  // Los timestamps llegan en UTC; se muestran en la zona configurada del servidor.
  return isNaN(d) ? v : d.toLocaleString('es-MX', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: BvmApi.timezone() });
}
function statusLabel(s) {
  return { borrador: 'Borrador', abierta: 'Abierta', cerrada: 'Cerrada', archivada: 'Archivada',
           en_proceso: 'En proceso', finalizado: 'Finalizado' }[s] || s;
}
function setMsg(kind, text) {
  document.getElementById('page-msg').innerHTML =
    text ? '<div class="alert alert-' + kind + '">' + BvmApi.escapeHtml(text) + '</div>' : '';
  if (text) { window.scrollTo({ top: 0, behavior: 'smooth' }); }
}

function loadDetail() {
  fetch(BvmApi.base() + '/api/families/detail.php?id=' + FAMILY_ID, { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.ok) { throw new Error(d.error); }
      var f = d.family;
      document.getElementById('invite-url').textContent = f.invite_url;
      document.getElementById('cfg-name').value = f.family_name;
      document.getElementById('cfg-expected').value = f.expected_participants || '';
      document.getElementById('cfg-enforce-limit').checked = !!f.enforce_participant_limit;
      document.getElementById('cfg-opens').value = f.opens_at ? f.opens_at.substring(0, 10) : '';
      document.getElementById('cfg-closes').value = f.closes_at ? f.closes_at.substring(0, 10) : '';
      document.getElementById('cfg-status').value = f.status;
      document.getElementById('cfg-report-date').value = f.report_date ? f.report_date.substring(0, 10) : '';

      var inProgress = f.registered_count - f.finished_count;
      var pctText = f.progress_pct == null ? '' :
        ' · ' + Math.min(100, f.progress_pct) + '% del objetivo';
      var cap = f.capacity || {};
      var capLabels = { disponible: 'Disponible', cerca_del_limite: 'Cerca del límite', completo: 'Completo', excedido: 'Excedido' };
      var capChip = (cap.state && cap.state !== 'sin_limite')
        ? ' <span class="status-chip cap-' + cap.state + '">' + (capLabels[cap.state] || cap.state) +
          (cap.mode === 'referencia' ? ' · referencia' : '') + '</span>'
        : '';
      var registeredText = f.expected_participants
        ? '<strong>' + f.registered_count + '</strong> registrados de <strong>' + f.expected_participants + '</strong> ' +
          (cap.mode === 'referencia' ? 'esperados (referencia)' : 'autorizados')
        : '<strong>' + f.registered_count + '</strong> registrados';
      document.getElementById('summary').innerHTML =
        '<span class="status-chip status-' + f.status + '">' + statusLabel(f.status) + '</span>' + capChip + ' ' +
        registeredText + ' · ' +
        '<strong>' + inProgress + '</strong> en proceso · ' +
        '<strong>' + f.finished_count + '</strong> finalizados' + (f.expected_participants ? pctText : '');
      var capMsg = '';
      if (cap.state === 'completo' && cap.mode === 'limite') {
        capMsg = 'El cupo autorizado está completo: no se aceptan nuevos registros. Las personas ya registradas pueden continuar y finalizar. Puede aumentar el número esperado o desactivar el límite.';
      } else if (cap.state === 'cerca_del_limite') {
        capMsg = 'La familia está cerca del cupo (' + cap.registered + ' de ' + cap.expected + ').';
      } else if (cap.state === 'excedido') {
        capMsg = 'El número esperado opera como referencia y ya fue superado (' + cap.registered + ' de ' + cap.expected + ').';
      }
      document.getElementById('capacity-note').innerHTML = capMsg
        ? '<div class="alert alert-warning" role="status">' + BvmApi.escapeHtml(capMsg) + '</div>'
        : '';

      var body = document.getElementById('participants-body');
      if (!d.participants.length) {
        body.innerHTML = '<tr><td colspan="5" class="hint">Aún no hay participantes registrados. Comparta la liga y la clave para comenzar.</td></tr>';
        return;
      }
      body.innerHTML = d.participants.map(function (p) {
        var actions = '<button type="button" class="btn btn-quiet btn-regen-code" data-id="' + p.id + '">Nuevo código</button>';
        if (p.status === 'finalizado') {
          actions += ' <button type="button" class="btn btn-quiet btn-reopen" data-id="' + p.id + '" data-name="' + BvmApi.escapeHtml(p.name) + '">Reabrir</button>';
        }
        return '<tr>' +
          '<td><strong>' + BvmApi.escapeHtml(p.name) + '</strong></td>' +
          '<td class="hint">' + BvmApi.escapeHtml(p.generation) + '<br>' + BvmApi.escapeHtml(p.participation_role) + '</td>' +
          '<td><span class="status-chip status-' + p.status + '">' + statusLabel(p.status) + '</span></td>' +
          '<td>' + p.answered_count + ' de 20<br><span class="hint">Actividad: ' + fmtDate(p.updated_at) + '</span></td>' +
          '<td>' + actions + '</td>' +
          '</tr>';
      }).join('');

      Array.prototype.forEach.call(document.querySelectorAll('.btn-regen-code'), function (btn) {
        btn.addEventListener('click', function () {
          if (!window.confirm('¿Generar un nuevo código personal? El anterior dejará de funcionar.')) { return; }
          BvmApi.post('/api/participants/admin-actions.php', { participant_id: parseInt(btn.dataset.id, 10), action: 'regenerate_code' })
            .then(function (r) {
              if (r.ok) {
                setMsg('success', 'Nuevo código personal (compártalo de forma segura; no volverá a mostrarse): ' + r.personal_code);
              } else { setMsg('error', r.error || 'No fue posible regenerar el código.'); }
            });
        });
      });
      Array.prototype.forEach.call(document.querySelectorAll('.btn-reopen'), function (btn) {
        btn.addEventListener('click', function () {
          var typed = window.prompt('Reapertura excepcional de "' + btn.dataset.name + '".\n' +
            'Esta acción queda registrada en auditoría. Escriba REABRIR para confirmar:');
          if (typed === null) { return; }
          BvmApi.post('/api/participants/admin-actions.php', { participant_id: parseInt(btn.dataset.id, 10), action: 'reopen', confirm: typed })
            .then(function (r) {
              if (r.ok) { setMsg('success', 'Participación reabierta.'); loadDetail(); }
              else { setMsg('error', r.error || 'No fue posible reabrir.'); }
            });
        });
      });
    })
    .catch(function () { setMsg('error', 'No fue posible cargar la información de la familia.'); });
}

document.getElementById('copy-url').addEventListener('click', function () {
  navigator.clipboard.writeText(document.getElementById('invite-url').textContent).then(function () {
    document.getElementById('copy-url').textContent = 'Liga copiada';
  });
});

document.getElementById('regen-key').addEventListener('click', function () {
  if (!window.confirm('¿Regenerar la clave de acceso? La clave anterior dejará de funcionar para nuevos ingresos.')) { return; }
  BvmApi.post('/api/families/regenerate-key.php', { id: FAMILY_ID }).then(function (d) {
    if (d.ok) {
      document.getElementById('key-result').innerHTML =
        '<div class="alert alert-success">Nueva clave (guárdela ahora): <span class="code-badge">' +
        BvmApi.escapeHtml(d.access_code) + '</span></div>';
    } else { setMsg('error', d.error || 'No fue posible regenerar la clave.'); }
  });
});

document.getElementById('config-form').addEventListener('submit', function (ev) {
  ev.preventDefault();
  BvmApi.post('/api/families/update.php', {
    id: FAMILY_ID,
    family_name: document.getElementById('cfg-name').value,
    expected_participants: document.getElementById('cfg-expected').value || null,
    enforce_participant_limit: document.getElementById('cfg-enforce-limit').checked,
    opens_at: document.getElementById('cfg-opens').value || null,
    closes_at: document.getElementById('cfg-closes').value || null,
    report_date: document.getElementById('cfg-report-date').value || null,
    status: document.getElementById('cfg-status').value
  }).then(function (d) {
    if (d.ok) {
      setMsg('success', 'Configuración guardada.');
      document.getElementById('family-title').textContent = document.getElementById('cfg-name').value;
      loadDetail();
    } else { setMsg('error', d.error || 'No fue posible guardar.'); }
  });
});

document.getElementById('delete-btn').addEventListener('click', function () {
  document.getElementById('delete-confirm').hidden = false;
});
document.getElementById('delete-cancel').addEventListener('click', function () {
  document.getElementById('delete-confirm').hidden = true;
  document.getElementById('delete-name').value = '';
});
document.getElementById('delete-final').addEventListener('click', function () {
  BvmApi.post('/api/families/delete.php', {
    id: FAMILY_ID,
    confirm_name: document.getElementById('delete-name').value
  }).then(function (d) {
    if (d.ok) { window.location.href = 'familias.php'; }
    else { setMsg('error', d.error || 'No fue posible eliminar.'); }
  });
});

loadDetail();
JS;
bvm_admin_footer($script);
