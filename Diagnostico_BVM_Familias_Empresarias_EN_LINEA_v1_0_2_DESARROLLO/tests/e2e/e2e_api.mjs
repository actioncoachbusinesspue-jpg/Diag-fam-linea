/**
 * E2E de API y páginas sobre el servidor PHP embebido (ver run_e2e.sh).
 * Cubre los escenarios críticos del prompt maestro:
 * carga pública, demo sin login, admin protegido, login/lockout/CSRF,
 * crear familia, liga+clave, registro remoto, autosave, continuidad,
 * finalización y bloqueo, aislamiento entre familias e IDs manipulados.
 */
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8098';

let failures = 0;
function check(name, cond, detail) {
  if (cond) { console.log('[OK]    ' + name); }
  else { failures++; console.log('[FALLA] ' + name + (detail ? ' — ' + detail : '')); }
}

/** Cliente con manejo manual de cookies (jar por "dispositivo"). */
function makeClient() {
  const jar = new Map();
  function cookieHeader() {
    return Array.from(jar.entries()).map(([k, v]) => k + '=' + v).join('; ');
  }
  async function request(path, opts = {}) {
    const headers = Object.assign({}, opts.headers || {});
    if (jar.size) { headers['Cookie'] = cookieHeader(); }
    const res = await fetch(BASE + path, { ...opts, headers, redirect: 'manual' });
    const setCookies = res.headers.getSetCookie ? res.headers.getSetCookie() : [];
    for (const sc of setCookies) {
      const [pair] = sc.split(';');
      const idx = pair.indexOf('=');
      const name = pair.slice(0, idx).trim();
      const value = pair.slice(idx + 1).trim();
      if (value === 'deleted' || sc.includes('Max-Age=0')) { jar.delete(name); }
      else { jar.set(name, value); }
    }
    return res;
  }
  return {
    request,
    async getPage(path) {
      const res = await request(path);
      const html = await res.text();
      const m = html.match(/name="csrf-token" content="([^"]+)"/)
        || html.match(/name="csrf_token" value="([^"]+)"/);
      return { res, html, csrf: m ? m[1] : null };
    },
    async postJson(path, body, csrf) {
      const res = await request(path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-Token': csrf } : {}) },
        body: JSON.stringify(body),
      });
      let data = {};
      try { data = await res.json(); } catch { /* respuesta no JSON */ }
      return { res, data };
    },
    async getJson(path) {
      const res = await request(path);
      let data = {};
      try { data = await res.json(); } catch { /* no JSON */ }
      return { res, data };
    },
  };
}

// ============================================================
// E01-E03 — páginas públicas y protección de admin
// ============================================================
const anon = makeClient();
{
  const { res, html } = await anon.getPage('/index.php');
  check('E01 carga pública (200, tres caminos)', res.status === 200
    && html.includes('Responder el diagnóstico')
    && html.includes('Conocer la radiografía BVM')
    && html.includes('Acceso BVM'));
}
{
  const { res, html } = await anon.getPage('/demostracion.php');
  check('E02 demostración sin login', res.status === 200
    && html.includes('Familia Horizonte')
    && html.includes('almacenamiento local deshabilitado')
    && html.includes('enterCommercial'));
}
{
  const res = await anon.request('/admin/familias.php');
  check('E03 admin redirige a login sin sesión', res.status === 302
    && String(res.headers.get('location')).includes('acceso-bvm.php'));
  const res2 = await anon.request('/admin/reporte.php?id=1');
  check('E03b reporte protegido', res2.status === 302);
}
{
  const { res } = await anon.getJson('/api/families/list.php');
  check('API admin sin sesión → 401', res.status === 401);
}

// ============================================================
// Instalador: crea el primer administrador y luego se bloquea
// ============================================================
const installer = makeClient();
{
  const { csrf } = await installer.getPage('/instalar.php');
  const body = new URLSearchParams({
    csrf_token: csrf, install_token: 'token-instalacion-e2e',
    name: 'Equipo BVM', username: 'bvm.e2e', password: 'contrasena-segura-e2e-1', password2: 'contrasena-segura-e2e-1',
  });
  const res = await installer.request('/instalar.php', {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body,
  });
  const html = await res.text();
  check('Instalador crea administrador', html.includes('creada correctamente'));
  const again = await installer.request('/instalar.php');
  check('Instalador queda bloqueado', (await again.text()).includes('quedó bloqueado'));
}

// ============================================================
// E04-E08 — login incorrecto, lockout, login correcto, CSRF
// ============================================================
const admin = makeClient();
{
  const { csrf } = await admin.getPage('/acceso-bvm.php');
  const bad = await admin.postJson('/api/auth/login.php', { username: 'bvm.e2e', password: 'incorrecta' }, csrf);
  check('E04 login incorrecto → 401 mensaje genérico', bad.res.status === 401
    && bad.data.error.includes('Usuario o contraseña incorrectos'));

  const noCsrf = await admin.postJson('/api/auth/login.php', { username: 'bvm.e2e', password: 'contrasena-segura-e2e-1' });
  check('E08 CSRF ausente rechazado (403)', noCsrf.res.status === 403);

  // Lockout: max_login_attempts = 3 en esta configuración
  await admin.postJson('/api/auth/login.php', { username: 'bloqueo.e2e', password: 'x' }, csrf);
  await admin.postJson('/api/auth/login.php', { username: 'bloqueo.e2e', password: 'x' }, csrf);
  await admin.postJson('/api/auth/login.php', { username: 'bloqueo.e2e', password: 'x' }, csrf);
  const locked = await admin.postJson('/api/auth/login.php', { username: 'bloqueo.e2e', password: 'x' }, csrf);
  check('E05 bloqueo por intentos (429)', locked.res.status === 429);

  const good = await admin.postJson('/api/auth/login.php', { username: 'bvm.e2e', password: 'contrasena-segura-e2e-1' }, csrf);
  check('E06 login correcto', good.data.ok === true);
  admin.csrf = good.data.csrf_token;
}

// ============================================================
// E09-E10 — crear familia, liga y clave
// ============================================================
let familyA, accessCodeA, familyB, accessCodeB;
{
  const a = await admin.postJson('/api/families/create.php', { family_name: 'Familia Robles', expected_participants: 3 }, admin.csrf);
  check('E09 crear familia', a.data.ok === true && a.data.family.family_name === 'Familia Robles');
  familyA = a.data.family; accessCodeA = a.data.access_code;
  check('E10 liga y clave generadas', /participar\.php\?f=[a-z0-9]{12}$/.test(familyA.invite_url)
    && /^ROBLES-[A-Z2-9]{4}$/.test(accessCodeA));

  const b = await admin.postJson('/api/families/create.php', { family_name: 'Familia Sierra' }, admin.csrf);
  familyB = b.data.family; accessCodeB = b.data.access_code;

  const open = await admin.postJson('/api/families/update.php', { id: familyA.id, status: 'abierta' }, admin.csrf);
  check('Abrir familia A', open.data.ok === true);
  await admin.postJson('/api/families/update.php', { id: familyB.id, status: 'abierta' }, admin.csrf);
}

// ============================================================
// E11-E19 — participante remoto (contexto de navegador distinto)
// ============================================================
const device1 = makeClient();
let personalCode;
{
  const slugA = familyA.invite_url.split('f=')[1];
  const { res, csrf } = await device1.getPage('/participar.php?f=' + slugA);
  check('E11 liga abre desde otro contexto', res.status === 200 && csrf !== null);
  device1.csrf = csrf;

  const wrong = await device1.postJson('/api/participants/access.php', { slug: slugA, access_code: 'ROBLES-MALA' }, csrf);
  check('E12 clave incorrecta rechazada', wrong.res.status === 401);

  const okKey = await device1.postJson('/api/participants/access.php', { slug: slugA, access_code: accessCodeA }, csrf);
  check('Clave correcta aceptada', okKey.data.ok === true && okKey.data.family.open_for_participation === true);

  const reg = await device1.postJson('/api/participants/register.php', {
    name: 'Ana Robles', generation: 'Segunda generación',
    participation_role: 'Accionista o propietario sin rol operativo', consent: true,
  }, csrf);
  check('E13 registro de participante', reg.data.ok === true);
  check('E14 código personal generado', /^[A-Z2-9]{4}-[A-Z2-9]{4}$/.test(reg.data.personal_code));
  personalCode = reg.data.personal_code;

  const dup = await device1.postJson('/api/participants/register.php', {
    name: 'ana  robles', generation: 'Segunda generación',
    participation_role: 'Accionista o propietario sin rol operativo', consent: true,
  }, csrf);
  check('Duplicado advertido (409)', dup.res.status === 409 && dup.data.duplicate === true);

  // E15 guardar respuestas 1..20 con revisión
  let revision = 0, saveOk = true;
  for (let q = 1; q <= 20; q++) {
    const r = await device1.postJson('/api/responses/save.php',
      { question_id: q, value: ((q % 5) + 1), revision, current_index: q }, csrf);
    if (!r.data.ok) { saveOk = false; break; }
    revision = r.data.revision;
  }
  check('E15 autosave de 20 respuestas', saveOk);

  const badValue = await device1.postJson('/api/responses/save.php', { question_id: 1, value: 9, revision }, csrf);
  check('Valor fuera de 1-5 rechazado', badValue.res.status === 422);
  const sqlInj = await device1.postJson('/api/responses/save.php', { question_id: "1; DROP TABLE responses;--", value: 3, revision }, csrf);
  check('E26 inyección SQL rechazada por validación', sqlInj.res.status === 422);
  device1.revision = revision;
}

// E16-E17 — cerrar navegador y reanudar desde otro contexto
const device2 = makeClient();
{
  const slugA = familyA.invite_url.split('f=')[1];
  const { csrf } = await device2.getPage('/participar.php?f=' + slugA);
  device2.csrf = csrf;
  await device2.postJson('/api/participants/access.php', { slug: slugA, access_code: accessCodeA }, csrf);
  const resume = await device2.postJson('/api/participants/resume.php', { personal_code: personalCode }, csrf);
  check('E17 reanudar desde otro dispositivo', resume.data.ok === true
    && resume.data.participant.answers.filter((v) => v != null).length === 20);
  device2.revision = resume.data.participant.revision;

  // E39 — el dispositivo 1 (revisión vieja tras un guardado del 2) detecta conflicto
  const s2 = await device2.postJson('/api/responses/save.php', { question_id: 1, value: 5, revision: device2.revision, current_index: 20 }, csrf);
  check('Dispositivo 2 guarda', s2.data.ok === true);
  const conflict = await device1.postJson('/api/responses/save.php', { question_id: 2, value: 4, revision: device1.revision }, device1.csrf);
  check('E39 conflicto entre dispositivos detectado (409)', conflict.res.status === 409 && conflict.data.conflict === true);

  // Externas + finalizar en dispositivo 2
  let rev = s2.data.revision;
  const e1 = await device2.postJson('/api/responses/external.php', { question_id: 'ext1', value: 4, revision: rev }, csrf);
  rev = e1.data.revision;
  const e2 = await device2.postJson('/api/responses/external.php', { question_id: 'ext2', value: 5, revision: rev }, csrf);
  check('Preguntas externas guardadas', e2.data.ok === true);

  const fin = await device2.postJson('/api/responses/finalize.php', {}, csrf);
  check('E18 finalizar participación', fin.data.ok === true);

  const after = await device2.postJson('/api/responses/save.php', { question_id: 3, value: 2, revision: 999 }, csrf);
  check('E19 editar tras finalizar → 423 bloqueado', after.res.status === 423 && after.data.locked === true);
}

// ============================================================
// E20-E24 — segundo participante, avance admin, privacidad, aislamiento
// ============================================================
{
  const slugA = familyA.invite_url.split('f=')[1];
  const device3 = makeClient();
  const { csrf } = await device3.getPage('/participar.php?f=' + slugA);
  await device3.postJson('/api/participants/access.php', { slug: slugA, access_code: accessCodeA }, csrf);
  const reg = await device3.postJson('/api/participants/register.php', {
    name: 'Luis Robles', generation: 'Primera generación',
    participation_role: 'Dirección o liderazgo operativo', consent: true,
  }, csrf);
  check('E20 segundo participante desde otro equipo', reg.data.ok === true);

  const det = await admin.getJson('/api/families/detail.php?id=' + familyA.id);
  check('E21 administrador ve avance actualizado', det.data.ok === true
    && det.data.family.registered_count === 2 && det.data.family.finished_count === 1);
  check('Privacidad: detalle sin respuestas individuales',
    !JSON.stringify(det.data.participants).includes('"answers"'));

  // E22 participante no puede ver resultados ni administración
  const asParticipant = await device3.getJson('/api/families/list.php');
  check('E22 participante no accede a API admin', asParticipant.res.status === 401);
  const repPage = await device3.request('/admin/reporte.php?id=' + familyA.id);
  check('E22b participante no accede al reporte', repPage.status === 302);

  // E23 la clave de A no permite entrar a B
  const slugB = familyB.invite_url.split('f=')[1];
  const cross = await device3.postJson('/api/participants/access.php', { slug: slugB, access_code: accessCodeA }, csrf);
  check('E23 Familia A no accede a Familia B', cross.res.status === 401);

  // E24 IDs manipulados
  const notFound = await admin.getJson('/api/families/detail.php?id=99999');
  check('E24 ID inexistente → 404', notFound.res.status === 404);
  const injected = await admin.getJson('/api/families/detail.php?id=1%20OR%201=1');
  check('E24b ID manipulado no filtra datos', injected.res.status === 404 || injected.data.family?.id === 1);
}

// ============================================================
// E25 — XSS almacenado escapado en páginas admin
// ============================================================
{
  const xss = await admin.postJson('/api/families/create.php', { family_name: '<script>alert(1)</script> Familia' }, admin.csrf);
  check('Nombre con HTML aceptado como texto', xss.data.ok === true);
  const { html } = await admin.getPage('/admin/familia.php?id=' + xss.data.family.id);
  check('E25 XSS escapado en admin', !html.includes('<script>alert(1)</script>'));
  await admin.postJson('/api/families/delete.php', { id: xss.data.family.id, confirm_name: '<script>alert(1)</script> Familia' }, admin.csrf);
}

// ============================================================
// E27/E38 — reporte real con datos del servidor y demo aislada
// ============================================================
{
  const rep = await admin.request('/admin/reporte.php?id=' + familyA.id);
  const html = await rep.text();
  check('E27 reporte real 200 con datos inyectados', rep.status === 200
    && html.includes('__BVM_SERVER_FAMILY__') === false // los datos van embebidos, no en una global suelta sin uso
    && html.includes('Familia Robles') && html.includes('enterAdmin'));
  check('Reporte usa almacenamiento en memoria', html.includes('almacenamiento local deshabilitado'));

  // E38: la demo no toca la base — el conteo de familias no cambia al usarla
  const before = await admin.getJson('/api/families/list.php?archivadas=1');
  await anon.getPage('/demostracion.php');
  const after = await admin.getJson('/api/families/list.php?archivadas=1');
  check('E38 demo no modifica datos reales',
    before.data.families.length === after.data.families.length);
}

// ============================================================
// E36 — familia cerrada no acepta nuevos registros
// ============================================================
{
  await admin.postJson('/api/families/update.php', { id: familyA.id, status: 'cerrada' }, admin.csrf);
  const slugA = familyA.invite_url.split('f=')[1];
  const late = makeClient();
  const { csrf } = await late.getPage('/participar.php?f=' + slugA);
  await late.postJson('/api/participants/access.php', { slug: slugA, access_code: accessCodeA }, csrf);
  const reg = await late.postJson('/api/participants/register.php', {
    name: 'Tarde Robles', generation: 'Primera generación',
    participation_role: 'Otro rol patrimonial', consent: true,
  }, csrf);
  check('E36 familia cerrada no acepta registros (409)', reg.res.status === 409);
}

// ============================================================
// E35 — exportación de respaldo en línea
// ============================================================
{
  const res = await admin.request('/api/families/export.php?id=' + familyA.id);
  const body = await res.json();
  check('E35 exportación de respaldo en línea', res.status === 200
    && body.schemaVersion === 5 && body.family.participants.length === 2
    && String(res.headers.get('content-disposition')).includes('attachment'));
}

// ============================================================
// E34 — importación de respaldo local (vista previa + confirmación)
// ============================================================
{
  const res = await admin.request('/api/families/export.php?id=' + familyA.id);
  const backup = await res.json();

  const preview = await admin.postJson('/api/families/import.php', { phase: 'preview', backup }, admin.csrf);
  check('E34 vista previa de importación', preview.data.ok === true
    && preview.data.preview.participants === 2 && preview.data.preview.finished === 1);

  const commit = await admin.postJson('/api/families/import.php', { phase: 'commit', backup, mode: 'create' }, admin.csrf);
  check('E34b importación como familia nueva', commit.data.ok === true
    && commit.data.imported_participants === 2 && /-/.test(commit.data.access_code || ''));

  const badSchema = await admin.postJson('/api/families/import.php',
    { phase: 'preview', backup: { ...backup, schemaVersion: 99 } }, admin.csrf);
  check('E34c schemaVersion futuro rechazado', badSchema.res.status === 422);

  const demoBackup = await admin.postJson('/api/families/import.php',
    { phase: 'preview', backup: { ...backup, backupType: 'demo-data' } }, admin.csrf);
  check('E34d respaldo de demo rechazado', demoBackup.res.status === 422);

  // Limpieza de la familia importada
  await admin.postJson('/api/families/delete.php',
    { id: commit.data.family_id, confirm_name: backup.family.familyName }, admin.csrf);
}

// ============================================================
// E07 — cierre de sesión
// ============================================================
{
  const out = await admin.postJson('/api/auth/logout.php', {}, admin.csrf);
  check('E07 cierre de sesión', out.data.ok === true);
  const after = await admin.getJson('/api/families/list.php');
  check('Sesión invalidada tras logout', after.res.status === 401);
}

console.log(failures === 0 ? '\nE2E: TODAS LAS PRUEBAS PASARON' : `\nE2E: ${failures} FALLAS`);
process.exit(failures === 0 ? 0 : 1);
