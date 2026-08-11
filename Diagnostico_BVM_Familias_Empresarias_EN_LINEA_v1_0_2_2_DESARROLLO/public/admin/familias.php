<?php
require_once dirname(__DIR__) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
require_once BVM_PRIVATE_DIR . '/admin_layout.php';
$user = bvm_require_admin_page();
bvm_security_headers(null, true);
bvm_admin_header($user, 'Familias', 'familias', ['Familias' => null]);
?>
<h1>Familias y empresas</h1>
<p class="hint">Cree una familia, comparta su liga y clave, y consulte el avance en tiempo real.</p>

<section class="panel guide-panel" aria-labelledby="guia-t">
  <h2 id="guia-t">Cómo se opera una aplicación</h2>
  <ol class="guide-steps">
    <li>Cree la familia (nace en <strong>Borrador</strong>: todavía no acepta participantes).</li>
    <li>Revise fechas y cupo.</li>
    <li>Cambie el estado a <strong>Abierta</strong>.</li>
    <li>Comparta la liga y la clave con la familia.</li>
    <li>Consulte los avances.</li>
    <li>Cierre la aplicación.</li>
    <li>Genere el reporte.</li>
  </ol>
</section>

<section class="panel" aria-labelledby="nueva-familia-t">
  <h2 id="nueva-familia-t">Crear familia o empresa</h2>
  <form id="create-form">
    <div class="form-row">
      <div>
        <label for="family_name">Nombre de la familia o empresa</label>
        <input id="family_name" type="text" required maxlength="150" placeholder="Ej. Familia Robles">
      </div>
      <div>
        <label for="expected">Participantes esperados (opcional)</label>
        <input id="expected" type="number" min="1" max="500" placeholder="Ej. 6">
      </div>
    </div>
    <div class="form-row">
      <div>
        <label for="opens_at">Fecha de apertura (opcional)</label>
        <input id="opens_at" type="date">
      </div>
      <div>
        <label for="closes_at">Fecha de cierre (opcional)</label>
        <input id="closes_at" type="date">
      </div>
    </div>
    <p class="hint">Fechas interpretadas en hora de Ciudad de México.</p>
    <!-- 1.0.2.2 — Hallazgo 3: «participantes esperados» es una meta de
         referencia. La casilla nace DESMARCADA: escribir un número no activa
         por sí solo el límite. -->
    <label class="consent-option" for="enforce_limit">
      <input type="checkbox" id="enforce_limit">
      <span class="consent-text">
        <span class="lbl">Cerrar nuevos registros al alcanzar el número esperado</span>
        <span class="desc">Predeterminado: <strong>desactivado</strong>. El número esperado es una meta de
        referencia y no bloquea a nadie. Si activa esta casilla, al alcanzarse el número no se aceptarán
        participantes nuevos (las personas ya registradas siempre pueden continuar y finalizar).</span>
      </span>
    </label>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Crear familia</button>
    </div>
  </form>
  <div id="create-result"></div>
</section>

<section class="panel" aria-labelledby="lista-t">
  <h2 id="lista-t">Familias registradas</h2>
  <div class="form-row" style="margin-bottom:12px;">
    <div>
      <label for="search" class="sr-only">Buscar familia</label>
      <input id="search" type="text" placeholder="Buscar por nombre…">
    </div>
    <div>
      <label for="status-filter" class="sr-only">Filtrar por estado</label>
      <select id="status-filter">
        <option value="">Todos los estados</option>
        <option value="borrador">Borrador</option>
        <option value="abierta">Abierta</option>
        <option value="cerrada">Cerrada</option>
        <option value="archivada">Archivada</option>
      </select>
    </div>
  </div>
  <div class="table-wrap">
    <table class="bvm-table" id="families-table">
      <thead>
        <tr>
          <th scope="col">Familia</th>
          <th scope="col">Estado</th>
          <th scope="col">Avance</th>
          <th scope="col">Última actividad</th>
          <th scope="col"><span class="sr-only">Acciones</span></th>
        </tr>
      </thead>
      <tbody id="families-body">
        <tr><td colspan="5" class="hint">Cargando…</td></tr>
      </tbody>
    </table>
  </div>
  <p class="hint" id="empty-state" hidden>
    Todavía no hay familias. Cree la primera con el formulario superior:
    el sistema generará una liga única y una clave de acceso para compartir.
  </p>
</section>
<?php
$script = <<<'JS'
var allFamilies = [];

function statusLabel(s) {
  return { borrador: 'Borrador', abierta: 'Abierta', cerrada: 'Cerrada', archivada: 'Archivada' }[s] || s;
}

/**
 * Etiqueta SECUNDARIA de cupo (1.0.2.2 — Hallazgo 11).
 * La etiqueta primaria es SIEMPRE el estado de la aplicación y es la que
 * gobierna la participación. El cupo nunca puede contradecirla: una familia en
 * Borrador jamás se muestra como «Disponible», porque no acepta registros.
 */
function capacityChip(f) {
  var c = f.capacity || {};
  if (f.status === 'cerrada') {
    return ' <span class="meta-chip">· ' + f.finished_count + ' finalizados</span>';
  }
  if (f.status !== 'abierta') {
    // Borrador / Archivada: solo se describe la CONFIGURACIÓN del cupo.
    var cfg = c.state === 'sin_limite'
      ? 'Cupo sin límite'
      : (c.mode === 'referencia' ? 'Cupo en modo referencia' : 'Cupo con límite activo');
    return ' <span class="meta-chip">· ' + cfg + '</span>';
  }
  if (c.state === 'sin_limite') { return ' <span class="meta-chip">· Cupo sin límite</span>'; }
  var labels = {
    disponible: 'Disponible',
    cerca_del_limite: 'Cerca del límite',
    completo: 'Completo',
    excedido: 'Excedido'
  };
  var label = labels[c.state] || c.state;
  if (c.mode === 'referencia') { label += ' (referencia)'; }
  return ' <span class="status-chip cap-' + c.state + '">' + label + '</span>';
}

function fmtDate(v) {
  if (!v) { return '—'; }
  var d = new Date(v.replace(' ', 'T') + (v.indexOf('Z') === -1 ? 'Z' : ''));
  if (isNaN(d)) { return v; }
  // Los timestamps llegan en UTC; se muestran en la zona configurada del servidor.
  return d.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric', timeZone: BvmApi.timezone() });
}

function renderFamilies() {
  var q = document.getElementById('search').value.trim().toLowerCase();
  var st = document.getElementById('status-filter').value;
  var body = document.getElementById('families-body');
  var rows = allFamilies.filter(function (f) {
    if (st && f.status !== st) { return false; }
    if (q && f.family_name.toLowerCase().indexOf(q) === -1) { return false; }
    return true;
  });
  document.getElementById('empty-state').hidden = allFamilies.length > 0;
  if (!rows.length) {
    body.innerHTML = '<tr><td colspan="5" class="hint">' +
      (allFamilies.length ? 'Ninguna familia coincide con el filtro.' : 'Sin familias registradas.') + '</td></tr>';
    return;
  }
  body.innerHTML = rows.map(function (f) {
    var progress = f.expected_participants
      ? f.registered_count + ' registrados de ' + f.expected_participants + ' autorizados · ' + f.finished_count + ' finalizados'
      : f.finished_count + ' finalizados · ' + f.registered_count + ' registrados';
    var pct = f.progress_pct == null ? null : Math.min(100, f.progress_pct);
    return '<tr>' +
      '<td><strong>' + BvmApi.escapeHtml(f.family_name) + '</strong><br><span class="hint">Creada: ' + fmtDate(f.created_at) + '</span></td>' +
      '<td><span class="status-chip status-' + f.status + '">' + statusLabel(f.status) + '</span>' + capacityChip(f) + '</td>' +
      '<td>' + progress + (pct == null ? '' :
        '<div class="progress-track" role="img" aria-label="' + pct + ' por ciento"><div class="progress-fill" style="width:' + pct + '%"></div></div>') + '</td>' +
      '<td>' + fmtDate(f.last_activity_at) + '</td>' +
      '<td><a class="btn btn-quiet" href="familia.php?id=' + f.id + '">Abrir</a></td>' +
      '</tr>';
  }).join('');
}

function loadFamilies() {
  fetch(BvmApi.base() + '/api/families/list.php?archivadas=1', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.ok) { throw new Error(d.error || 'Error'); }
      allFamilies = d.families;
      renderFamilies();
    })
    .catch(function () {
      document.getElementById('families-body').innerHTML =
        '<tr><td colspan="5"><div class="alert alert-error">No fue posible cargar las familias. Recargue la página.</div></td></tr>';
    });
}

document.getElementById('search').addEventListener('input', renderFamilies);
document.getElementById('status-filter').addEventListener('change', renderFamilies);

document.getElementById('create-form').addEventListener('submit', function (ev) {
  ev.preventDefault();
  var out = document.getElementById('create-result');
  out.innerHTML = '';
  BvmApi.post('/api/families/create.php', {
    family_name: document.getElementById('family_name').value,
    expected_participants: document.getElementById('expected').value || null,
    enforce_participant_limit: document.getElementById('enforce_limit').checked,
    opens_at: document.getElementById('opens_at').value || null,
    closes_at: document.getElementById('closes_at').value || null
  }).then(function (d) {
    if (!d.ok) {
      out.innerHTML = '<div class="alert alert-error">' + BvmApi.escapeHtml(d.error || 'No fue posible crear la familia.') + '</div>';
      return;
    }
    // El formulario se limpia por completo y la casilla de límite vuelve
    // explícitamente a DESMARCADA: nunca debe confundirse el estado del
    // formulario con la configuración de la familia recién creada.
    document.getElementById('create-form').reset();
    document.getElementById('enforce_limit').checked = false;

    var capText = d.family.expected_participants
      ? (d.family.enforce_participant_limit
          ? 'Cupo: límite activo (' + d.family.expected_participants + ' participantes)'
          : 'Cupo: referencia (' + d.family.expected_participants + ' participantes esperados)')
      : 'Cupo: sin límite';

    out.innerHTML =
      '<div class="alert alert-success created-box" role="status">' +
      '<p class="created-title"><strong>Familia creada correctamente</strong></p>' +
      '<p>' + BvmApi.escapeHtml(d.family.family_name) + '<br>' +
      'Estado: <span class="status-chip status-borrador">Borrador</span> ' +
      '<span class="meta-chip">· ' + BvmApi.escapeHtml(capText) + '</span></p>' +
      '<p class="created-warning"><strong>Todavía no acepta participantes.</strong> ' +
      'No envíe todavía esta invitación: la familia permanece en Borrador.</p>' +
      '<p class="hint">Guarde ahora estos datos para su resguardo interno: la clave solo se muestra una vez.</p>' +
      '<p>Liga de invitación: <code>' + BvmApi.escapeHtml(d.family.invite_url) + '</code><br>' +
      'Clave de acceso: <span class="code-badge">' + BvmApi.escapeHtml(d.access_code) + '</span></p>' +
      '<div class="form-actions">' +
      '<a class="btn btn-primary" href="familia.php?id=' + d.family.id + '#config-t">Revisar configuración y abrir</a>' +
      '<button type="button" class="btn btn-quiet" id="copy-invite">Guardar datos de invitación</button>' +
      '</div></div>';
    document.getElementById('copy-invite').addEventListener('click', function () {
      // Resguardo interno: NO es el envío de la invitación (la familia sigue
      // en Borrador y no aceptaría a nadie).
      var text = 'Diagnóstico BVM — datos de invitación (resguardo interno)\n' +
        'Familia: ' + d.family.family_name + '\n' +
        'Estado al generarse: Borrador (no acepta participantes todavía)\n' +
        'Liga: ' + d.family.invite_url + '\nClave de la familia: ' + d.access_code;
      navigator.clipboard.writeText(text).then(function () {
        document.getElementById('copy-invite').textContent = 'Datos copiados para resguardo';
      });
    });
    loadFamilies();
  });
});

loadFamilies();
JS;
bvm_admin_footer($script);
