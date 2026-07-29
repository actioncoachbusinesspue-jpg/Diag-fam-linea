<?php
require_once dirname(__DIR__, 2) . '/private/bootstrap.php';
require_once BVM_PRIVATE_DIR . '/admin_layout.php';
$user = bvm_require_admin_page();
bvm_security_headers(null, true);
bvm_admin_header($user, 'Familias', 'familias', ['Familias' => null]);
?>
<h1>Familias y empresas</h1>
<p class="hint">Cree una familia, comparta su liga y clave, y consulte el avance en tiempo real.</p>

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

function fmtDate(v) {
  if (!v) { return '—'; }
  var d = new Date(v.replace(' ', 'T') + (v.indexOf('Z') === -1 ? 'Z' : ''));
  if (isNaN(d)) { return v; }
  return d.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
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
      ? f.finished_count + ' de ' + f.expected_participants + ' finalizados'
      : f.finished_count + ' finalizados · ' + f.registered_count + ' registrados';
    var pct = f.progress_pct == null ? null : Math.min(100, f.progress_pct);
    return '<tr>' +
      '<td><strong>' + BvmApi.escapeHtml(f.family_name) + '</strong><br><span class="hint">Creada: ' + fmtDate(f.created_at) + '</span></td>' +
      '<td><span class="status-chip status-' + f.status + '">' + statusLabel(f.status) + '</span></td>' +
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
    opens_at: document.getElementById('opens_at').value || null,
    closes_at: document.getElementById('closes_at').value || null
  }).then(function (d) {
    if (!d.ok) {
      out.innerHTML = '<div class="alert alert-error">' + BvmApi.escapeHtml(d.error || 'No fue posible crear la familia.') + '</div>';
      return;
    }
    document.getElementById('create-form').reset();
    out.innerHTML =
      '<div class="alert alert-success" role="status">' +
      '<strong>' + BvmApi.escapeHtml(d.family.family_name) + '</strong> creada correctamente.<br>' +
      'Liga de invitación: <code>' + BvmApi.escapeHtml(d.family.invite_url) + '</code><br>' +
      'Clave de acceso (guárdela ahora — no volverá a mostrarse): ' +
      '<span class="code-badge">' + BvmApi.escapeHtml(d.access_code) + '</span><br>' +
      '<button type="button" class="btn btn-secondary" id="copy-invite">Copiar invitación</button> ' +
      '<a class="btn btn-primary" href="familia.php?id=' + d.family.id + '">Abrir familia</a>' +
      '</div>';
    document.getElementById('copy-invite').addEventListener('click', function () {
      var text = 'Le invitamos a responder el Diagnóstico BVM.\n' +
        'Liga: ' + d.family.invite_url + '\nClave de la familia: ' + d.access_code;
      navigator.clipboard.writeText(text).then(function () {
        document.getElementById('copy-invite').textContent = 'Invitación copiada';
      });
    });
    loadFamilies();
  });
});

loadFamilies();
JS;
bvm_admin_footer($script);
