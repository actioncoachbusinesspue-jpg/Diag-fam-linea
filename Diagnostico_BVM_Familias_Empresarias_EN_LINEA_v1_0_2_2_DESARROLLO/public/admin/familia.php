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
  <!-- Acción principal del ciclo de vida: abrir la participación (Borrador →
       Abierta) o cerrarla. Se calcula en loadDetail() según el estado real. -->
  <div class="form-actions" id="lifecycle-actions"></div>
</section>

<section class="panel" aria-labelledby="invitacion-t">
  <h2 id="invitacion-t">Invitación</h2>
  <!-- 1.0.2.2 — Hallazgo 2: se distingue «guardar datos de invitación» (permitido
       siempre) de «enviar invitación» (solo cuando la familia ya está Abierta). -->
  <div id="invite-state"></div>
  <p>Liga pública de esta familia:</p>
  <p><code id="invite-url"></code>
     <button type="button" class="btn btn-quiet" id="copy-url">Copiar liga</button></p>
  <div class="form-actions" id="invite-actions"></div>
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
    <!-- Misma presentación en bloques que el consentimiento: el título de la
         casilla y su explicación nunca se empalman en una sola línea. -->
    <label class="consent-option" for="cfg-enforce-limit">
      <input type="checkbox" id="cfg-enforce-limit">
      <span class="consent-text">
        <span class="lbl">Cerrar nuevos registros al alcanzar el número esperado</span>
        <span class="desc">Al activarlo, las personas ya registradas podrán continuar, pero no se aceptarán
        nuevos participantes cuando se alcance el cupo. Si lo desactiva, el número esperado es solo una meta
        y el excedente se muestra como referencia.</span>
      </span>
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
  <!-- 1.0.2.2 — Hallazgo 10: nunca se invita a abrir un reporte definitivo
       cuando no existe ninguna participación finalizada. -->
  <div id="report-note"></div>
  <div class="form-actions" style="margin-top:0;" id="report-actions"></div>
  <p class="hint">La demostración con la Familia Horizonte (datos ficticios) está siempre disponible en
     <a href="<?= e(bvm_base_url()) ?>/demostracion.php">la portada pública</a>.</p>
</section>

<section class="panel" aria-labelledby="peligro-t">
  <h2 id="peligro-t">Zona de cuidado</h2>

  <h3>Archivar</h3>
  <p class="hint">Archivar <strong>conserva</strong> la familia, sus participaciones y sus respuestas.
     La aplicación deja de aceptar participación y la familia aparece en el filtro «Archivada».
     Es una acción reversible desde el estado de la configuración.</p>
  <button type="button" class="btn btn-secondary" id="archive-btn">Archivar familia (conserva los datos)</button>

  <hr style="border:none;border-top:1px solid var(--bvm-divider);margin:22px 0;">

  <h3>Eliminar definitivamente</h3>
  <p class="hint">Elimina de la base de datos la familia y <strong>todos</strong> sus datos dependientes:
     participaciones, respuestas y respuestas externas. No es archivar y no puede deshacerse.</p>
  <button type="button" class="btn btn-danger" id="delete-btn">Eliminar familia definitivamente</button>
  <div id="delete-confirm" hidden style="margin-top:12px;">
    <div id="delete-preview"></div>
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

var LAST_FAMILY = null;

/** Cambia el estado de la familia con confirmación explícita. */
function setStatus(newStatus, confirmText, okText) {
  if (!window.confirm(confirmText)) { return; }
  BvmApi.post('/api/families/update.php', { id: FAMILY_ID, status: newStatus }).then(function (d) {
    if (d.ok) { setMsg('success', okText); loadDetail(); }
    else { setMsg('error', d.error || 'No fue posible cambiar el estado.'); }
  });
}

/**
 * Acción principal del ciclo de vida (Hallazgo 2). Una familia en Borrador NO
 * ofrece un botón que sugiera que ya puede enviarse la invitación: ofrece
 * «Revisar configuración y abrir participación», que cambia el estado de forma
 * explícita y confirmada.
 */
function renderLifecycle(f) {
  var box = document.getElementById('lifecycle-actions');
  if (f.status === 'borrador') {
    box.innerHTML = '<button type="button" class="btn btn-primary" id="open-participation">' +
      'Configurar y abrir participación</button>' +
      '<span class="hint">Revise el nombre, las fechas y el cupo antes de abrir.</span>';
    document.getElementById('open-participation').addEventListener('click', function () {
      setStatus('abierta',
        'La familia "' + f.family_name + '" pasará a estado Abierta y comenzará a aceptar participantes ' +
        'según sus fechas y su cupo. ¿Desea abrir la participación?',
        'Lista para recibir participantes. Ya puede compartir la liga y la clave.');
    });
  } else if (f.status === 'abierta') {
    box.innerHTML = '<button type="button" class="btn btn-secondary" id="close-participation">' +
      'Cerrar la aplicación</button>' +
      '<span class="hint">Al cerrarla dejará de recibir respuestas nuevas y cambios.</span>';
    document.getElementById('close-participation').addEventListener('click', function () {
      setStatus('cerrada',
        '¿Cerrar la aplicación de "' + f.family_name + '"? Los participantes ya no podrán responder ' +
        'ni finalizar. Podrá reabrirla más adelante si lo necesita.',
        'Aplicación cerrada.');
    });
  } else if (f.status === 'cerrada') {
    box.innerHTML = '<button type="button" class="btn btn-secondary" id="reopen-participation">' +
      'Reabrir participación</button>' +
      '<span class="hint">Los participantes con respuestas incompletas podrán continuar.</span>';
    document.getElementById('reopen-participation').addEventListener('click', function () {
      setStatus('abierta',
        '¿Reabrir la participación de "' + f.family_name + '"? Se aceptarán respuestas de nuevo ' +
        'si las fechas lo permiten.',
        'Participación reabierta.');
    });
  } else {
    box.innerHTML = '<span class="hint">Familia archivada: no admite participación. ' +
      'Puede reactivarla cambiando el estado en la configuración.</span>';
  }
}

/** Diferencia resguardar los datos de invitación de ENVIAR la invitación. */
function renderInvitation(f) {
  var stateBox = document.getElementById('invite-state');
  var actions = document.getElementById('invite-actions');
  var abierta = f.status === 'abierta';
  stateBox.innerHTML = abierta
    ? '<div class="alert alert-success" role="status">Lista para recibir participantes.</div>'
    : (f.status === 'borrador'
        ? '<div class="alert alert-warning" role="status"><strong>No envíe todavía esta invitación:</strong> ' +
          'la familia permanece en Borrador y no aceptará registros ni respuestas.</div>'
        : '<div class="alert alert-info" role="status">Esta aplicación no está aceptando participación ' +
          '(estado: ' + statusLabel(f.status) + ').</div>');
  actions.innerHTML = '<button type="button" class="btn ' + (abierta ? 'btn-primary' : 'btn-quiet') + '" id="copy-invite-text">' +
    (abierta ? 'Copiar invitación para enviar' : 'Guardar datos de invitación (resguardo)') + '</button>';
  document.getElementById('copy-invite-text').addEventListener('click', function () {
    var btn = document.getElementById('copy-invite-text');
    var text = abierta
      ? 'Le invitamos a responder el Diagnóstico BVM.\nLiga: ' + f.invite_url +
        '\nClave de la familia: (la clave que BVM le compartió)'
      : 'Diagnóstico BVM — datos de invitación (resguardo interno)\nFamilia: ' + f.family_name +
        '\nEstado: ' + statusLabel(f.status) + ' (no acepta participantes)\nLiga: ' + f.invite_url;
    navigator.clipboard.writeText(text).then(function () {
      btn.textContent = abierta ? 'Invitación copiada' : 'Datos copiados para resguardo';
    });
  });
}

/**
 * Acceso al reporte según participaciones finalizadas (Hallazgo 10):
 *   0 finalizados      → sin reporte definitivo.
 *   parcial            → «Abrir lectura preliminar».
 *   todos los esperados→ «Abrir radiografía y reporte».
 * No cambia ningún cálculo del reporte.
 */
function renderReportAccess(f) {
  var note = document.getElementById('report-note');
  var actions = document.getElementById('report-actions');
  var exportBtn = '<a class="btn btn-secondary" href="' + BvmApi.base() +
    '/api/families/export.php?id=' + FAMILY_ID + '">Exportar respaldo JSON</a>';

  if (!f.finished_count) {
    note.innerHTML = '<div class="alert alert-info" role="status">' +
      'Reporte disponible cuando exista al menos una participación finalizada.</div>';
    actions.innerHTML = '<button type="button" class="btn btn-primary" disabled ' +
      'aria-disabled="true">Abrir radiografía y reporte</button>' + exportBtn;
    return;
  }
  var expected = f.expected_participants;
  var complete = expected != null && f.finished_count >= expected;
  if (complete || expected == null) {
    note.innerHTML = expected == null
      ? '<p class="hint">' + f.finished_count + ' participaciones finalizadas.</p>'
      : '<p class="hint">Las ' + expected + ' participaciones esperadas están finalizadas.</p>';
    actions.innerHTML = '<a class="btn btn-primary" href="reporte.php?id=' + FAMILY_ID + '">' +
      'Abrir radiografía y reporte</a>' + exportBtn;
    return;
  }
  note.innerHTML = '<div class="alert alert-warning" role="status">Lectura preliminar: ' +
    f.finished_count + ' de ' + expected + ' participaciones finalizadas.</div>';
  actions.innerHTML = '<a class="btn btn-primary" href="reporte.php?id=' + FAMILY_ID + '">' +
    'Abrir lectura preliminar</a>' + exportBtn;
}

function loadDetail() {
  fetch(BvmApi.base() + '/api/families/detail.php?id=' + FAMILY_ID, { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.ok) { throw new Error(d.error); }
      var f = d.family;
      f.dependents = d.dependents || null;
      document.getElementById('invite-url').textContent = f.invite_url;
      document.getElementById('cfg-name').value = f.family_name;
      document.getElementById('cfg-expected').value = f.expected_participants || '';
      document.getElementById('cfg-enforce-limit').checked = !!f.enforce_participant_limit;
      document.getElementById('cfg-opens').value = f.opens_at ? f.opens_at.substring(0, 10) : '';
      document.getElementById('cfg-closes').value = f.closes_at ? f.closes_at.substring(0, 10) : '';
      document.getElementById('cfg-status').value = f.status;
      document.getElementById('cfg-report-date').value = f.report_date ? f.report_date.substring(0, 10) : '';

      LAST_FAMILY = f;
      var inProgress = f.registered_count - f.finished_count;
      var pctText = f.progress_pct == null ? '' :
        ' · ' + Math.min(100, f.progress_pct) + '% del objetivo';
      var cap = f.capacity || {};
      var capLabels = { disponible: 'Disponible', cerca_del_limite: 'Cerca del límite', completo: 'Completo', excedido: 'Excedido' };
      // El estado de la aplicación gobierna; el cupo es SIEMPRE secundario y
      // nunca puede leerse como «acepta participantes» si no es así (Hallazgo 11).
      var capChip;
      if (f.status !== 'abierta') {
        capChip = ' <span class="meta-chip">· ' + (cap.state === 'sin_limite'
          ? 'Cupo sin límite'
          : (cap.mode === 'referencia' ? 'Cupo en modo referencia' : 'Cupo con límite activo')) + '</span>';
      } else if (cap.state && cap.state !== 'sin_limite') {
        capChip = ' <span class="status-chip cap-' + cap.state + '">' + (capLabels[cap.state] || cap.state) +
          (cap.mode === 'referencia' ? ' (referencia)' : '') + '</span>';
      } else {
        capChip = ' <span class="meta-chip">· Cupo sin límite</span>';
      }
      renderLifecycle(f);
      renderInvitation(f);
      renderReportAccess(f);
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

document.getElementById('archive-btn').addEventListener('click', function () {
  var name = LAST_FAMILY ? LAST_FAMILY.family_name : 'esta familia';
  setStatus('archivada',
    '¿Archivar "' + name + '"? Se conservan la familia, sus participaciones y todas sus respuestas; ' +
    'únicamente deja de aceptar participación.',
    'Familia archivada. Sus datos se conservan.');
});

// Primer paso de la doble confirmación: mostrar el alcance EXACTO de la
// eliminación (participaciones, respuestas y respuestas externas reales).
document.getElementById('delete-btn').addEventListener('click', function () {
  var dep = (LAST_FAMILY && LAST_FAMILY.dependents) || { participants: 0, responses: 0, external_responses: 0 };
  document.getElementById('delete-preview').innerHTML =
    '<div class="alert alert-error" role="alert">' +
    'Se eliminarán de la base de datos:<br>' +
    '<strong>' + dep.participants + '</strong> participantes<br>' +
    '<strong>' + dep.responses + '</strong> respuestas<br>' +
    '<strong>' + dep.external_responses + '</strong> respuestas externas<br>' +
    '<strong>Esta acción no puede deshacerse.</strong></div>';
  document.getElementById('delete-confirm').hidden = false;
  document.getElementById('delete-name').focus();
});
document.getElementById('delete-cancel').addEventListener('click', function () {
  document.getElementById('delete-confirm').hidden = true;
  document.getElementById('delete-name').value = '';
});
document.getElementById('delete-final').addEventListener('click', function () {
  // Segunda confirmación explícita, además del nombre exacto escrito.
  if (!window.confirm('Esta acción elimina definitivamente la familia y todos sus datos. No puede deshacerse. ¿Continuar?')) { return; }
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
