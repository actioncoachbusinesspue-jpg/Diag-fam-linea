/**
 * Siembra de datos para la evidencia de reporte (HITO 9 / sección 19 del
 * prompt maestro). Crea, contra un servidor recién instalado:
 *
 *  - Familia real controlada  : "Familia Robles" (3 esperados, 2 finalizados
 *    con resultados mixtos → lectura preliminar).
 *  - Familia de estrés        : 12 esperados, 10 registrados con nombres
 *    largos, todos-altos, todos-bajos, empates y 2 participantes incompletos.
 *
 * Escribe qa-evidence/tmp/evidence_ids.json con los IDs y credenciales
 * necesarios para generate_pdfs.mjs.
 *
 * Uso: BASE_URL=... EVIDENCE_OUT=... node seed_evidence.mjs
 */
import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname } from 'node:path';

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8097';
const OUT = process.env.EVIDENCE_OUT || 'qa-evidence/tmp/evidence_ids.json';
const ADMIN_USER = 'bvm.evidencia';
const ADMIN_PASS = 'contrasena-evidencia-segura-1';

function fail(msg, extra) {
  console.error('[SEED FALLA] ' + msg + (extra ? ' — ' + JSON.stringify(extra) : ''));
  process.exit(1);
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
  };
}

// ---------- Instalador ----------
{
  const c = makeClient();
  const { csrf, html } = await c.getPage('/instalar.php');
  if (html.includes('quedó bloqueado')) {
    fail('El instalador ya estaba bloqueado: use una base limpia para la evidencia.');
  }
  const body = new URLSearchParams({
    csrf_token: csrf, install_token: process.env.INSTALL_TOKEN || 'token-instalacion-evidencia',
    name: 'Equipo BVM Evidencia', username: ADMIN_USER, password: ADMIN_PASS, password2: ADMIN_PASS,
  });
  const res = await c.request('/instalar.php', {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body,
  });
  if (!(await res.text()).includes('creada correctamente')) fail('El instalador no creó la cuenta.');
  console.log('[SEED] Administrador de evidencia creado');
}

// ---------- Sesión BVM ----------
const admin = makeClient();
{
  const { csrf } = await admin.getPage('/acceso-bvm.php');
  const r = await admin.postJson('/api/auth/login.php', { username: ADMIN_USER, password: ADMIN_PASS }, csrf);
  if (!r.data.ok) fail('Login de evidencia', r.data);
  admin.csrf = r.data.csrf_token;
}

async function createFamily(name, expected) {
  const r = await admin.postJson('/api/families/create.php',
    { family_name: name, expected_participants: expected }, admin.csrf);
  if (!r.data.ok) fail('Crear familia ' + name, r.data);
  const open = await admin.postJson('/api/families/update.php',
    { id: r.data.family.id, status: 'abierta' }, admin.csrf);
  if (!open.data.ok) fail('Abrir familia ' + name, open.data);
  return { family: r.data.family, accessCode: r.data.access_code };
}

/**
 * Registra un participante en su propia "sesión de dispositivo", guarda sus
 * respuestas (answers: arreglo de 20 valores 1-5; puede ser parcial con null)
 * y finaliza si finalize=true.
 */
async function runParticipant(slug, accessCode, spec) {
  const device = makeClient();
  const { csrf } = await device.getPage('/participar.php?f=' + slug);
  if (!csrf) fail('participar.php sin CSRF para ' + spec.name);
  const acc = await device.postJson('/api/participants/access.php', { slug, access_code: accessCode }, csrf);
  if (!acc.data.ok) fail('Clave rechazada para ' + spec.name, acc.data);
  const reg = await device.postJson('/api/participants/register.php', {
    name: spec.name, generation: spec.generation,
    participation_role: spec.role, consent: true,
  }, csrf);
  if (!reg.data.ok) fail('Registro de ' + spec.name, reg.data);

  let revision = 0;
  for (let q = 1; q <= 20; q++) {
    const value = spec.answers[q - 1];
    if (value == null) continue;
    const r = await device.postJson('/api/responses/save.php',
      { question_id: q, value, revision, current_index: q }, csrf);
    if (!r.data.ok) fail('Autosave A' + q + ' de ' + spec.name, r.data);
    revision = r.data.revision;
  }
  if (spec.external) {
    for (const [extId, value] of Object.entries(spec.external)) {
      const r = await device.postJson('/api/responses/external.php',
        { question_id: extId, value, revision }, csrf);
      if (!r.data.ok) fail('Externa ' + extId + ' de ' + spec.name, r.data);
      revision = r.data.revision;
    }
  }
  if (spec.finalize) {
    const fin = await device.postJson('/api/responses/finalize.php', {}, csrf);
    if (!fin.data.ok) fail('Finalizar ' + spec.name, fin.data);
  }
  console.log('[SEED]   ' + spec.name + (spec.finalize ? ' (finalizado)' : ' (incompleto)'));
}

const G = {
  primera: 'Primera generación',
  segunda: 'Segunda generación',
  tercera: 'Tercera generación o posterior',
};
const R = {
  direccion: 'Dirección o liderazgo operativo',
  consejo: 'Consejo, órgano de gobierno o comité familiar',
  accionista: 'Accionista o propietario sin rol operativo',
  heredero: 'Futuro propietario o heredero',
};

// ---------- Familia real controlada (2 de 3 → lectura preliminar) ----------
console.log('[SEED] Familia real controlada');
const real = await createFamily('Familia Robles', 3);
const realSlug = real.family.invite_url.split('f=')[1];
await runParticipant(realSlug, real.accessCode, {
  name: 'Ana Robles Garza', generation: G.segunda, role: R.direccion,
  answers: [4, 3, 4, 2, 3, 3, 2, 2, 1, 2, 3, 4, 3, 3, 2, 2, 1, 2, 2, 1],
  external: { ext1: 4, ext2: 3 }, finalize: true,
});
await runParticipant(realSlug, real.accessCode, {
  name: 'Luis Robles Garza', generation: G.primera, role: R.consejo,
  answers: [5, 4, 4, 3, 4, 2, 3, 2, 2, 2, 4, 4, 3, 2, 3, 1, 2, 1, 2, 2],
  external: { ext1: 3, ext2: 4 }, finalize: true,
});

// ---------- Familia de estrés (10 registrados de 12, nombres largos) ----------
console.log('[SEED] Familia de estrés');
const stress = await createFamily('Familia Echeverría-Montemayor de los Robledales', 12);
const stressSlug = stress.family.invite_url.split('f=')[1];

const all = (v) => Array.from({ length: 20 }, () => v);
// Patrón que produce puntuaciones idénticas en las cuatro dimensiones
// (cada bloque de 5 afirmaciones suma lo mismo) → empate.
const tiePattern = [3, 4, 2, 5, 1, 1, 5, 2, 4, 3, 4, 3, 5, 1, 2, 2, 1, 3, 4, 5];

const stressSpecs = [
  { name: 'María de los Ángeles Echeverría-Montemayor de la Garza y Villarreal',
    generation: G.primera, role: R.direccion, answers: all(5), external: { ext1: 5, ext2: 5 }, finalize: true },
  { name: 'Juan Pablo Maximiliano Echeverría-Montemayor y Fernández de Castro',
    generation: G.primera, role: R.consejo, answers: all(1), external: { ext1: 1, ext2: 1 }, finalize: true },
  { name: 'Guadalupe Fernanda Echeverría-Montemayor viuda de Iturriaga y Solórzano',
    generation: G.segunda, role: R.accionista, answers: tiePattern, external: { ext1: 3, ext2: 3 }, finalize: true },
  { name: 'José Emiliano Echeverría-Montemayor Robledal de la Peña y Gorostiaga',
    generation: G.segunda, role: R.direccion,
    answers: [5, 5, 4, 5, 4, 2, 1, 2, 1, 2, 4, 5, 4, 4, 5, 1, 2, 1, 1, 1],
    external: { ext1: 4, ext2: 2 }, finalize: true },
  { name: 'Ana Cristina de Todos los Santos Echeverría-Montemayor y Zambrano',
    generation: G.segunda, role: R.consejo,
    answers: [2, 1, 2, 1, 2, 5, 4, 5, 5, 4, 1, 2, 1, 1, 2, 4, 5, 5, 4, 5],
    external: { ext1: 2, ext2: 4 }, finalize: true },
  { name: 'Rodrigo Sebastián Echeverría-Montemayor Iturriaga de los Robledales',
    generation: G.tercera, role: R.heredero,
    answers: [3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3],
    external: { ext1: 3, ext2: 3 }, finalize: true },
  { name: 'Valentina Isabel Echeverría-Montemayor y Solórzano de Gorostiaga',
    generation: G.tercera, role: R.heredero,
    answers: [4, 5, 3, 4, 5, 2, 3, 1, 2, 2, 5, 4, 5, 4, 3, 2, 1, 2, 3, 1],
    external: { ext1: 5, ext2: 2 }, finalize: true },
  { name: 'Francisco Javier de la Santísima Trinidad Echeverría-Montemayor',
    generation: G.primera, role: R.accionista,
    answers: [1, 2, 2, 1, 1, 4, 5, 4, 4, 5, 2, 1, 1, 2, 2, 5, 4, 4, 5, 5],
    external: { ext1: 1, ext2: 5 }, finalize: true },
  // Incompletos: registrados pero sin finalizar (uno parcial, uno sin respuestas)
  { name: 'Renata Alejandra Echeverría-Montemayor Villarreal y de la Garza',
    generation: G.tercera, role: R.heredero,
    answers: [4, 3, 5, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
    finalize: false },
  { name: 'Diego Armando Echeverría-Montemayor Zambrano de los Robledales',
    generation: G.tercera, role: R.heredero, answers: all(null), finalize: false },
];
for (const spec of stressSpecs) {
  await runParticipant(stressSlug, stress.accessCode, spec);
}

// ---------- Familia de empates (1.0.2, Mejora 6) ----------
// Diseñada para producir EMPATES EXACTOS de dispersión entre dimensiones:
//   P1 y P2 responden todo 2; P3 responde 3 en A1–A10 (relaciones + gobierno)
//   y 2 en A11–A20 (desarrollo + continuidad).
//   → dispersión de relaciones = gobierno (empate de mayor diferencia)
//   → dispersión de desarrollo = continuidad = 0 (empate de mayor coincidencia)
// Además todas las dimensiones quedan en nivel bajo (≈25–33 de 100), con lo
// que la evidencia de dimensión debe decir «Afirmaciones relativamente más
// consolidadas» (mejora editorial, sin umbrales nuevos).
console.log('[SEED] Familia de empates');
const tie = await createFamily('Familia Empate Controlado', 3);
const tieSlug = tie.family.invite_url.split('f=')[1];
const half = (a, b) => Array.from({ length: 20 }, (_, i) => (i < 10 ? a : b));
const tieSpecs = [
  { name: 'Primo Empate Uno', generation: G.primera, role: R.direccion,
    answers: half(2, 2), external: { ext1: 3, ext2: 3 }, finalize: true },
  { name: 'Prima Empate Dos', generation: G.segunda, role: R.consejo,
    answers: half(2, 2), external: { ext1: 3, ext2: 3 }, finalize: true },
  { name: 'Primo Empate Tres', generation: G.tercera, role: R.heredero,
    answers: half(3, 2), external: { ext1: 3, ext2: 3 }, finalize: true },
];
for (const spec of tieSpecs) {
  await runParticipant(tieSlug, tie.accessCode, spec);
}

mkdirSync(dirname(OUT), { recursive: true });
writeFileSync(OUT, JSON.stringify({
  admin_user: ADMIN_USER, admin_pass: ADMIN_PASS,
  real_family_id: real.family.id, stress_family_id: stress.family.id,
  tie_family_id: tie.family.id,
}, null, 2));
console.log('[SEED] Listo. IDs escritos en ' + OUT);
