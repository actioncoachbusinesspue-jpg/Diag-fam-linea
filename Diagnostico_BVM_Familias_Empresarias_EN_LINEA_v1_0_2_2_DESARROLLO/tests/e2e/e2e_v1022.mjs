/**
 * Batería E2E de la versión 1.0.2.2 (prompt maestro correctivo, sección 18).
 *
 * Se ejecuta DESPUÉS de e2e_api.mjs sobre el mismo servidor y la misma base
 * (el administrador ya existe). Cubre los bloques:
 *   A portada · B estados · C fechas · D cupo · E sesiones · F mensajes
 *   H reporte · I eliminación · J seguridad de la nueva salida de participante
 *
 * Uso: BASE_URL=http://127.0.0.1:8098 node tests/e2e/e2e_v1022.mjs
 */
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8098';
const ADMIN_USER = process.env.BVM_E2E_USER || 'bvm.e2e';
const ADMIN_PASS = process.env.BVM_E2E_PASS || 'contrasena-segura-e2e-1';

let failures = 0;
function check(name, cond, detail) {
  if (cond) { console.log('[OK]    ' + name); }
  else { failures++; console.log('[FALLA] ' + name + (detail ? ' — ' + detail : '')); }
}

function makeClient() {
  const jar = new Map();
  async function request(path, opts = {}) {
    const headers = Object.assign({}, opts.headers || {});
    if (jar.size) {
      headers['Cookie'] = Array.from(jar.entries()).map(([k, v]) => k + '=' + v).join('; ');
    }
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
      try { data = await res.json(); } catch { /* no JSON */ }
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

/** Fecha local (America/Mexico_City) desplazada $days días, en AAAA-MM-DD. */
function localDate(days = 0) {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Mexico_City', year: 'numeric', month: '2-digit', day: '2-digit',
  }).formatToParts(new Date());
  const y = +parts.find((p) => p.type === 'year').value;
  const m = +parts.find((p) => p.type === 'month').value;
  const d = +parts.find((p) => p.type === 'day').value;
  const base = new Date(Date.UTC(y, m - 1, d));
  base.setUTCDate(base.getUTCDate() + days);
  return base.toISOString().slice(0, 10);
}

const admin = makeClient();
{
  const { csrf } = await admin.getPage('/acceso-bvm.php');
  const login = await admin.postJson('/api/auth/login.php', { username: ADMIN_USER, password: ADMIN_PASS }, csrf);
  if (!login.data.ok) {
    console.log('[FALLA] No fue posible iniciar sesión administrativa para la batería 1.0.2.2');
    process.exit(1);
  }
  admin.csrf = login.data.csrf_token || csrf;
}

/** Crea una familia y devuelve {id, slug, invite_url, access_code}. */
async function createFamily(name, extra = {}) {
  const { data } = await admin.postJson('/api/families/create.php',
    Object.assign({ family_name: name }, extra), admin.csrf);
  if (!data.ok) { throw new Error('No se pudo crear ' + name + ': ' + (data.error || '')); }
  return {
    id: data.family.id,
    slug: data.family.public_slug,
    invite: data.family.invite_url,
    code: data.access_code,
    family: data.family,
  };
}
function setFamily(id, fields) {
  return admin.postJson('/api/families/update.php', Object.assign({ id }, fields), admin.csrf);
}

/** Cliente de participante ya autorizado con la clave de la familia. */
async function participantClient(fam) {
  const c = makeClient();
  const page = await c.getPage('/participar.php?f=' + fam.slug);
  c.csrf = page.csrf;
  const acc = await c.postJson('/api/participants/access.php', { slug: fam.slug, access_code: fam.code }, c.csrf);
  return { c, acc, page };
}

// ============================================================
// A — PORTADA (E01-E05)
// ============================================================
{
  const anon = makeClient();
  const { res, html } = await anon.getPage('/index.php');
  check('E01 portada pública carga', res.status === 200 && html.includes('Responder el diagnóstico'));
  check('E02 el CTA de participantes NO enlaza a participar.php sin liga',
    !/href="participar\.php"/.test(html) && html.includes('Ya recibí una invitación'));
  check('E03 el panel de ayuda explica que se requiere la liga específica',
    html.includes('id="invite-help"')
    && html.includes('liga de invitación que BVM le compartió')
    && html.includes('participar.php?f='));
  check('E03b la portada no enumera familias ni expone ligas reales',
    !/participar\.php\?f=[a-z0-9]{6,}/.test(html));
  const demo = await anon.request('/demostracion.php');
  check('E04 la demostración abre desde la portada', demo.status === 200);
  const acceso = await anon.request('/acceso-bvm.php');
  check('E05 Acceso BVM abre', acceso.status === 200);

  const sinLiga = await anon.getPage('/participar.php');
  check('E02b participar.php sin liga responde con guía, no con error seco',
    sinLiga.res.status === 200
    && sinLiga.html.includes('Necesita la liga completa')
    && !sinLiga.html.includes('id="app"'));
  const ligaMala = await anon.getPage('/participar.php?f=noexistealguna');
  check('E02c liga inexistente sigue siendo segura',
    ligaMala.res.status === 200 && !ligaMala.html.includes('id="app"'));
}

// ============================================================
// B — ESTADOS (E06-E14) y F — MENSAJES (E33-E38)
// ============================================================
{
  const fam = await createFamily('Familia QA Estados');
  check('E06 la familia nueva nace en Borrador', fam.family.status === 'borrador');

  // Borrador: sin registro y con mensaje específico.
  {
    const { c, acc } = await participantClient(fam);
    check('E33 Borrador informa motivo family_draft',
      acc.data.ok === true && acc.data.policy.register_reason === 'family_draft'
      && acc.data.policy.can_register === false && acc.data.policy.can_resume === false);
    check('E33b mensaje exacto de Borrador',
      acc.data.policy.register_message === 'Esta aplicación aún no ha sido habilitada por BVM.');
    const reg = await c.postJson('/api/participants/register.php', {
      name: 'Persona Borrador', generation: 'Primera generación',
      participation_role: 'Otro rol patrimonial', consent: true,
    }, c.csrf);
    check('E07 Borrador no acepta registros',
      reg.res.status === 409 && reg.data.reason_code === 'family_draft');
  }

  // Abierta: registro, guardado y finalización.
  await setFamily(fam.id, { status: 'abierta' });
  const { c: p1 } = await participantClient(fam);
  let code1 = null;
  {
    const reg = await p1.postJson('/api/participants/register.php', {
      name: 'Ana Estados', generation: 'Primera generación',
      participation_role: 'Otro rol patrimonial', consent: true,
    }, p1.csrf);
    check('E09 abrir la familia habilita el registro', reg.data.ok === true);
    code1 = reg.data.personal_code;
    const save = await p1.postJson('/api/responses/save.php', { question_id: 1, value: 4, revision: 0 }, p1.csrf);
    check('E09b con la familia Abierta se guarda', save.data.ok === true);
  }

  // Borrador otra vez: ni guardar ni finalizar (Hallazgo 4).
  await setFamily(fam.id, { status: 'borrador' });
  {
    const save = await p1.postJson('/api/responses/save.php', { question_id: 2, value: 3, revision: 1 }, p1.csrf);
    check('E08 Borrador no guarda respuestas',
      save.res.status === 409 && save.data.reason_code === 'family_draft');
    const ext = await p1.postJson('/api/responses/external.php', { question_id: 'ext1', value: 3, revision: 1 }, p1.csrf);
    check('E08b Borrador no guarda preguntas externas',
      ext.res.status === 409 && ext.data.reason_code === 'family_draft');
    const fin = await p1.postJson('/api/responses/finalize.php', {}, p1.csrf);
    check('E08c Borrador no finaliza',
      fin.res.status === 409 && fin.data.reason_code === 'family_draft');
  }

  // Cerrada.
  await setFamily(fam.id, { status: 'cerrada' });
  {
    const save = await p1.postJson('/api/responses/save.php', { question_id: 2, value: 3, revision: 1 }, p1.csrf);
    check('E11 Cerrada no guarda',
      save.res.status === 409 && save.data.reason_code === 'family_closed');
    check('E36 mensaje exacto de Cerrada',
      save.data.error === 'BVM ha cerrado esta aplicación y ya no recibe nuevas respuestas.');
    const fin = await p1.postJson('/api/responses/finalize.php', {}, p1.csrf);
    check('E12 Cerrada no finaliza cambios',
      fin.res.status === 409 && fin.data.reason_code === 'family_closed');
    const { c, acc } = await participantClient(fam);
    const reg = await c.postJson('/api/participants/register.php', {
      name: 'Nueva Persona', generation: 'Primera generación',
      participation_role: 'Otro rol patrimonial', consent: true,
    }, c.csrf);
    check('E10 Cerrada no acepta registros',
      reg.res.status === 409 && reg.data.reason_code === 'family_closed');
    const res = await c.postJson('/api/participants/resume.php', { personal_code: code1 }, c.csrf);
    check('E10b Cerrada no permite reanudar para editar',
      res.res.status === 409 && res.data.reason_code === 'family_closed');
    check('E38 la interfaz recibe can_register=false y can_resume=false',
      acc.data.policy.can_register === false && acc.data.policy.can_resume === false);
  }

  // Reapertura.
  await setFamily(fam.id, { status: 'abierta' });
  {
    const { c } = await participantClient(fam);
    const res = await c.postJson('/api/participants/resume.php', { personal_code: code1 }, c.csrf);
    check('E13 reabrir restaura el flujo del participante incompleto', res.data.ok === true);
    const save = await c.postJson('/api/responses/save.php',
      { question_id: 2, value: 3, revision: res.data.participant.revision }, c.csrf);
    check('E13b tras reabrir se vuelve a guardar', save.data.ok === true);
  }

  // Archivada.
  await setFamily(fam.id, { status: 'archivada' });
  {
    const { c, acc } = await participantClient(fam);
    check('E14 Archivada no admite participación',
      acc.data.policy.can_register === false && acc.data.policy.lifecycle_reason === 'family_archived');
    const reg = await c.postJson('/api/participants/register.php', {
      name: 'Otra Persona', generation: 'Primera generación',
      participation_role: 'Otro rol patrimonial', consent: true,
    }, c.csrf);
    check('E14b Archivada rechaza registros',
      reg.res.status === 409 && reg.data.reason_code === 'family_archived');
  }
}

// ============================================================
// C — FECHAS (E15-E20)
// ============================================================
{
  const fam = await createFamily('Familia QA Fechas');
  await setFamily(fam.id, { status: 'abierta', opens_at: localDate(2), closes_at: localDate(9) });
  {
    const { acc } = await participantClient(fam);
    check('E15 antes de la apertura la participación está bloqueada',
      acc.data.policy.register_reason === 'not_started');
    const dd = localDate(2).split('-');
    check('E34 mensaje específico de «aún no inicia» con fecha DD/MM/AAAA',
      acc.data.policy.register_message === 'La participación estará disponible a partir del ' +
      dd[2] + '/' + dd[1] + '/' + dd[0] + '.');
  }
  await setFamily(fam.id, { opens_at: localDate(0), closes_at: localDate(0) });
  {
    const { c, acc } = await participantClient(fam);
    check('E16/E17 el día de apertura y de cierre acepta participación',
      acc.data.policy.can_register === true);
    const reg = await c.postJson('/api/participants/register.php', {
      name: 'Persona En Fecha', generation: 'Primera generación',
      participation_role: 'Otro rol patrimonial', consent: true,
    }, c.csrf);
    check('E17b registro válido dentro del periodo', reg.data.ok === true);
  }
  await setFamily(fam.id, { opens_at: localDate(-9), closes_at: localDate(-1) });
  {
    const { acc } = await participantClient(fam);
    check('E18 después del cierre la participación está bloqueada',
      acc.data.policy.register_reason === 'ended');
    const dd = localDate(-1).split('-');
    check('E35 mensaje específico de periodo concluido con fecha',
      acc.data.policy.register_message === 'El periodo de participación concluyó el ' +
      dd[2] + '/' + dd[1] + '/' + dd[0] + '.');
  }
  {
    const bad = await setFamily(fam.id, { opens_at: '2026-05-10', closes_at: '2026-05-09' });
    check('E19 cierre anterior a la apertura → 422',
      bad.res.status === 422 && /no puede ser anterior/.test(bad.data.error || ''));
    const same = await setFamily(fam.id, { opens_at: '2026-05-10', closes_at: '2026-05-10' });
    check('E20 mismo día de apertura y cierre → válido', same.data.ok === true);
    // Editar SOLO una fecha tampoco puede dejar un periodo imposible.
    const onlyClose = await setFamily(fam.id, { closes_at: '2026-05-01' });
    check('E19b editar solo el cierre también valida contra la apertura guardada',
      onlyClose.res.status === 422);
    const badFormat = await setFamily(fam.id, { closes_at: '10/05/2026' });
    check('E19c formato de fecha inválido → 422', badFormat.res.status === 422);
    const report = await setFamily(fam.id, { report_date: '2020-01-01' });
    check('E19d report_date es editorial y no altera apertura/cierre', report.data.ok === true);
  }
}

// ============================================================
// D — CUPO (E21-E26)
// ============================================================
async function registerN(fam, names) {
  const results = [];
  for (const name of names) {
    const { c } = await participantClient(fam);
    const reg = await c.postJson('/api/participants/register.php', {
      name, generation: 'Primera generación', participation_role: 'Otro rol patrimonial', consent: true,
    }, c.csrf);
    results.push({ c, reg });
  }
  return results;
}
{
  // E24 — la casilla no viaja: el backend debe guardar «referencia».
  const ref = await createFamily('Familia QA Cupo Referencia', { expected_participants: 3 });
  check('E24 sin casilla expresa el cupo se guarda como REFERENCIA',
    ref.family.enforce_participant_limit === false);
  await setFamily(ref.id, { status: 'abierta' });
  const r = await registerN(ref, ['Ref Uno', 'Ref Dos', 'Ref Tres', 'Ref Cuatro']);
  check('E21 expected=3 con límite desactivado: el cuarto participante entra',
    r.every((x) => x.reg.data.ok === true));
  check('E21b el cuarto registro se marca como excedente de referencia',
    r[3].reg.data.over_reference === true);
  {
    const { data } = await admin.getJson('/api/families/detail.php?id=' + ref.id);
    check('E26 la configuración guardada coincide con lo enviado (referencia)',
      data.family.enforce_participant_limit === false
      && data.family.capacity.mode === 'referencia'
      && data.family.capacity.state === 'excedido'
      && data.family.registered_count === 4);
  }

  const lim = await createFamily('Familia QA Cupo Limite',
    { expected_participants: 3, enforce_participant_limit: true });
  check('E26b el límite activo se guarda tal como se pidió',
    lim.family.enforce_participant_limit === true);
  await setFamily(lim.id, { status: 'abierta' });
  const l = await registerN(lim, ['Lim Uno', 'Lim Dos', 'Lim Tres']);
  check('E22a con límite activo entran los tres esperados', l.every((x) => x.reg.data.ok === true));
  const cuarto = await registerN(lim, ['Lim Cuatro']);
  check('E22 el cuarto recibe 409 capacity_reached',
    cuarto[0].reg.res.status === 409 && cuarto[0].reg.data.reason_code === 'capacity_reached');
  check('E37 mensaje exacto de cupo completo',
    cuarto[0].reg.data.error === 'Se alcanzó el número autorizado de participantes.');
  {
    const save = await l[0].c.postJson('/api/responses/save.php', { question_id: 1, value: 5, revision: 0 }, l[0].c.csrf);
    check('E23 el cupo lleno no bloquea a quienes ya están registrados', save.data.ok === true);
    const { c, acc } = await participantClient(lim);
    check('E23b con cupo lleno la política sigue permitiendo continuidad',
      acc.data.policy.can_register === false && acc.data.policy.can_resume === true);
    const resumed = await c.postJson('/api/participants/resume.php',
      { personal_code: l[1].reg.data.personal_code }, c.csrf);
    check('E23c reanudar con cupo lleno funciona', resumed.data.ok === true);
  }

  const sinCupo = await createFamily('Familia QA Sin Cupo');
  await setFamily(sinCupo.id, { status: 'abierta' });
  const s = await registerN(sinCupo, ['Sin Uno', 'Sin Dos']);
  check('E-cupo sin número esperado nunca bloquea', s.every((x) => x.reg.data.ok === true));
}

// ============================================================
// E — SESIONES ENTRE FAMILIAS (E27-E32)
// ============================================================
{
  const famA = await createFamily('Familia QA Sesion A');
  const famB = await createFamily('Familia QA Sesion B');
  await setFamily(famA.id, { status: 'abierta' });
  await setFamily(famB.id, { status: 'abierta' });

  const nav = makeClient(); // UN MISMO navegador
  let pageA = await nav.getPage('/participar.php?f=' + famA.slug);
  await nav.postJson('/api/participants/access.php', { slug: famA.slug, access_code: famA.code }, pageA.csrf);
  const regA = await nav.postJson('/api/participants/register.php', {
    name: 'Persona De A', generation: 'Primera generación',
    participation_role: 'Otro rol patrimonial', consent: true,
  }, pageA.csrf);
  check('E27 participar en la Familia A', regA.data.ok === true);
  await nav.postJson('/api/responses/save.php',
    { question_id: 1, value: 5, revision: 0 }, regA.data.csrf_token || pageA.csrf);

  const stateA = await nav.getJson('/api/participants/state.php?f=' + famA.slug);
  check('E27b el estado de A responde con los datos de A',
    stateA.data.ok === true && stateA.data.participant.name === 'Persona De A');

  // Abrir la liga de B en el mismo navegador.
  const pageB = await nav.getPage('/participar.php?f=' + famB.slug);
  check('E28 la liga de B abre en el mismo navegador', pageB.res.status === 200);
  const stateB = await nav.getJson('/api/participants/state.php?f=' + famB.slug);
  check('E29 en B no aparece ningún dato de A',
    stateB.res.status === 401 && !JSON.stringify(stateB.data).includes('Persona De A'));

  const accB = await nav.postJson('/api/participants/access.php', { slug: famB.slug, access_code: famB.code }, pageB.csrf);
  check('E28b B funciona correctamente tras el cambio', accB.data.ok === true);
  const regB = await nav.postJson('/api/participants/register.php', {
    name: 'Persona De B', generation: 'Primera generación',
    participation_role: 'Otro rol patrimonial', consent: true,
  }, pageB.csrf);
  check('E28c se puede participar en B con normalidad', regB.data.ok === true);

  // Volver a A exige el código de continuidad.
  pageA = await nav.getPage('/participar.php?f=' + famA.slug);
  const backA = await nav.getJson('/api/participants/state.php?f=' + famA.slug);
  check('E31 volver a A requiere código de continuidad', backA.res.status === 401);
  await nav.postJson('/api/participants/access.php', { slug: famA.slug, access_code: famA.code }, pageA.csrf);
  const resumeA = await nav.postJson('/api/participants/resume.php',
    { personal_code: regA.data.personal_code }, pageA.csrf);
  check('E31b con el código personal se recupera la participación de A',
    resumeA.data.ok === true && resumeA.data.participant.name === 'Persona De A');

  // E30 — salida real del participante.
  const salida = await nav.postJson('/api/participants/logout.php', {}, pageA.csrf);
  check('E30 «Salir de esta participación» responde ok', salida.data.ok === true);
  const afterLogout = await nav.getJson('/api/participants/state.php?f=' + famA.slug);
  check('E30b tras salir no queda sesión de participante', afterLogout.res.status === 401);
  const saveAfter = await nav.postJson('/api/responses/save.php',
    { question_id: 2, value: 2, revision: 1 }, pageA.csrf);
  check('E30c tras salir no se puede guardar', saveAfter.res.status === 401);
  const noCsrf = await nav.postJson('/api/participants/logout.php', {});
  check('E30d la salida exige CSRF', noCsrf.res.status === 403);

  // E32 — la salida del participante no destruye la sesión administrativa.
  const mixto = makeClient();
  const loginPage = await mixto.getPage('/acceso-bvm.php');
  const login = await mixto.postJson('/api/auth/login.php',
    { username: ADMIN_USER, password: ADMIN_PASS }, loginPage.csrf);
  const mixtoCsrf = login.data.csrf_token || loginPage.csrf;
  const pPage = await mixto.getPage('/participar.php?f=' + famB.slug);
  await mixto.postJson('/api/participants/access.php', { slug: famB.slug, access_code: famB.code }, pPage.csrf);
  await mixto.postJson('/api/participants/register.php', {
    name: 'Admin Que Participa', generation: 'Primera generación',
    participation_role: 'Otro rol patrimonial', consent: true,
  }, pPage.csrf);
  const out = await mixto.postJson('/api/participants/logout.php', {}, pPage.csrf);
  check('E32a la salida del participante responde ok en sesión mixta', out.data.ok === true);
  const adminStill = await mixto.getJson('/api/families/list.php');
  check('E32 la sesión administrativa BVM sobrevive a la salida del participante',
    adminStill.res.status === 200 && adminStill.data.ok === true);
}

// ============================================================
// H — REPORTE (E43-E45)
// ============================================================
async function completeParticipant(fam, name) {
  const { c } = await participantClient(fam);
  const reg = await c.postJson('/api/participants/register.php', {
    name, generation: 'Primera generación', participation_role: 'Otro rol patrimonial', consent: true,
  }, c.csrf);
  let revision = 0;
  for (let q = 1; q <= 20; q++) {
    const r = await c.postJson('/api/responses/save.php',
      { question_id: q, value: ((q % 5) + 1), revision, current_index: q }, c.csrf);
    revision = r.data.revision;
  }
  for (const ext of ['ext1', 'ext2']) {
    const r = await c.postJson('/api/responses/external.php', { question_id: ext, value: 4, revision }, c.csrf);
    revision = r.data.revision;
  }
  const fin = await c.postJson('/api/responses/finalize.php', {}, c.csrf);
  return { reg, fin };
}
{
  const fam = await createFamily('Familia QA Reporte', { expected_participants: 2 });
  await setFamily(fam.id, { status: 'abierta' });

  const vacio = await admin.request('/admin/reporte.php?id=' + fam.id);
  const vacioHtml = await vacio.text();
  check('E43 con 0 finalizados NO se presenta un reporte definitivo',
    vacio.status === 200
    && vacioHtml.includes('Reporte disponible cuando exista al menos una participación finalizada')
    && !vacioHtml.includes('StorageAdapter.saveStore'));

  await completeParticipant(fam, 'Reporte Uno');
  const parcial = await admin.request('/admin/reporte.php?id=' + fam.id);
  const parcialHtml = await parcial.text();
  check('E44 con avance parcial el reporte declara lectura preliminar',
    parcial.status === 200
    && parcialHtml.includes('Lectura preliminar con 1 de 2 participaciones finalizadas')
    && parcialHtml.includes('StorageAdapter.saveStore'));
  check('E44b el aviso preliminar no se imprime (PDF intacto)',
    /@media print\{\.bvm-online-notice\{display:none/.test(parcialHtml));

  await completeParticipant(fam, 'Reporte Dos');
  const completo = await admin.request('/admin/reporte.php?id=' + fam.id);
  const completoHtml = await completo.text();
  check('E45 con todos los esperados finalizados el reporte va sin aviso preliminar',
    completo.status === 200
    && !completoHtml.includes('Lectura preliminar')
    && completoHtml.includes('StorageAdapter.saveStore'));

  const { data } = await admin.getJson('/api/families/detail.php?id=' + fam.id);
  check('E45b el detalle informa finalizados y esperados para el CTA',
    data.family.finished_count === 2 && data.family.expected_participants === 2);
}

// ============================================================
// I — ELIMINACIÓN (E53-E57)
// ============================================================
{
  const conservar = await createFamily('Familia QA Archivar');
  await setFamily(conservar.id, { status: 'abierta' });
  await completeParticipant(conservar, 'Archivo Uno');
  const arch = await setFamily(conservar.id, { status: 'archivada' });
  check('E53a archivar responde ok', arch.data.ok === true);
  const detArch = await admin.getJson('/api/families/detail.php?id=' + conservar.id);
  check('E53 archivar CONSERVA familia, participaciones y respuestas',
    detArch.data.ok === true
    && detArch.data.family.status === 'archivada'
    && detArch.data.participants.length === 1
    && detArch.data.dependents.responses === 20
    && detArch.data.dependents.external_responses === 2);

  const borrar = await createFamily('Familia QA Eliminar');
  await setFamily(borrar.id, { status: 'abierta' });
  await completeParticipant(borrar, 'Elimina Uno');
  const testigo = await createFamily('Familia QA Testigo');
  await setFamily(testigo.id, { status: 'abierta' });
  await completeParticipant(testigo, 'Testigo Uno');

  const detBorrar = await admin.getJson('/api/families/detail.php?id=' + borrar.id);
  check('E54a antes de eliminar se conoce el alcance exacto',
    detBorrar.data.dependents.participants === 1
    && detBorrar.data.dependents.responses === 20
    && detBorrar.data.dependents.external_responses === 2);

  const sinNombre = await admin.postJson('/api/families/delete.php',
    { id: borrar.id, confirm_name: 'Nombre Equivocado' }, admin.csrf);
  check('E54 eliminar exige el nombre exacto (doble confirmación)',
    sinNombre.res.status === 422);

  const del = await admin.postJson('/api/families/delete.php',
    { id: borrar.id, confirm_name: 'Familia QA Eliminar' }, admin.csrf);
  check('E55 la eliminación definitiva informa lo eliminado',
    del.data.ok === true && del.data.deleted.participants === 1
    && del.data.deleted.responses === 20 && del.data.deleted.external_responses === 2);

  const detDeleted = await admin.getJson('/api/families/detail.php?id=' + borrar.id);
  check('E55b la familia deja de resolverse en administración', detDeleted.res.status === 404);
  const anon = makeClient();
  const liga = await anon.getPage('/participar.php?f=' + borrar.slug);
  check('E57 la liga eliminada deja de funcionar',
    liga.res.status === 200 && !liga.html.includes('id="app"'));

  const detTestigo = await admin.getJson('/api/families/detail.php?id=' + testigo.id);
  check('E56 los datos de otras familias quedan intactos',
    detTestigo.data.ok === true
    && detTestigo.data.participants.length === 1
    && detTestigo.data.dependents.responses === 20);
}

console.log('');
if (failures === 0) {
  console.log('BATERÍA 1.0.2.2: TODAS LAS PRUEBAS PASARON');
} else {
  console.log('BATERÍA 1.0.2.2: ' + failures + ' FALLAS');
}
process.exit(failures === 0 ? 0 : 1);
