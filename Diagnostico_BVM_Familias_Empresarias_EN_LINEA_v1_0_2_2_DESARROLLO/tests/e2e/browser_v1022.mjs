/**
 * Pruebas de NAVEGADOR de la versión 1.0.2.2 (Chromium vía playwright).
 *
 * Cubre lo que solo puede comprobarse renderizando de verdad:
 *   A  portada: el CTA de participantes no navega a una liga inválida y el
 *      panel de ayuda abre de forma accesible (E02, E03).
 *   F  el formulario de registro NO se muestra cuando el participante nuevo
 *      no puede registrarse (E38).
 *   G  aviso de privacidad: casilla y textos sin empalme, label clicable y
 *      foco visible, en escritorio y en móvil 390×844 y 375×667 (E39-E42).
 *   K  cero errores de JavaScript en consola (E78).
 *
 * Deja capturas en qa-evidence/v1.0.2.2/screenshots/.
 *
 * Uso: PLAYWRIGHT_MODULE=<ruta> BASE_URL=... node tests/e2e/browser_v1022.mjs
 */
import { mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8098';
const ADMIN_USER = process.env.BVM_E2E_USER || 'bvm.e2e';
const ADMIN_PASS = process.env.BVM_E2E_PASS || 'contrasena-segura-e2e-1';
const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const SHOTS = process.env.BVM_SHOTS_DIR || join(ROOT, 'qa-evidence', 'v1.0.2.2', 'screenshots');
mkdirSync(SHOTS, { recursive: true });

const pwModule = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const { chromium } = pwModule.chromium ? pwModule : pwModule.default;

let failures = 0;
function check(name, cond, detail) {
  if (cond) { console.log('[OK]    ' + name); }
  else { failures++; console.log('[FALLA] ' + name + (detail ? ' — ' + detail : '')); }
}

/**
 * Recolector de errores REALES de JavaScript: excepciones no capturadas y
 * mensajes de consola de la propia aplicación. Se descartan los avisos que el
 * navegador emite por respuestas HTTP («Failed to load resource»): la consulta
 * inicial de sesión de participante responde 401 cuando todavía no hay
 * participación en curso, y ese 401 es el comportamiento correcto.
 */
function jsErrorCollector(page) {
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  page.on('console', (m) => {
    if (m.type() !== 'error') { return; }
    const text = m.text();
    if (/^Failed to load resource/.test(text)) { return; }
    errors.push(text);
  });
  return errors;
}

// ---------- preparación de datos por API (mismo servidor) ----------
const jar = new Map();
async function api(path, opts = {}) {
  const headers = Object.assign({}, opts.headers || {});
  if (jar.size) { headers['Cookie'] = Array.from(jar.entries()).map(([k, v]) => k + '=' + v).join('; '); }
  const res = await fetch(BASE + path, { ...opts, headers, redirect: 'manual' });
  for (const sc of (res.headers.getSetCookie ? res.headers.getSetCookie() : [])) {
    const [pair] = sc.split(';');
    const i = pair.indexOf('=');
    jar.set(pair.slice(0, i).trim(), pair.slice(i + 1).trim());
  }
  return res;
}
async function apiJson(path, body, csrf) {
  const res = await api(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-Token': csrf } : {}) },
    body: JSON.stringify(body),
  });
  try { return await res.json(); } catch { return {}; }
}
async function csrfOf(path) {
  const html = await (await api(path)).text();
  const m = html.match(/name="csrf-token" content="([^"]+)"/);
  return m ? m[1] : null;
}

const loginCsrf = await csrfOf('/acceso-bvm.php');
const login = await apiJson('/api/auth/login.php', { username: ADMIN_USER, password: ADMIN_PASS }, loginCsrf);
const adminCsrf = login.csrf_token || loginCsrf;
if (!login.ok) {
  console.log('[FALLA] no fue posible autenticarse para las pruebas de navegador 1.0.2.2');
  process.exit(1);
}
const abierta = await apiJson('/api/families/create.php',
  { family_name: 'Familia QA Navegador', expected_participants: 4 }, adminCsrf);
await apiJson('/api/families/update.php', { id: abierta.family.id, status: 'abierta' }, adminCsrf);
const borrador = await apiJson('/api/families/create.php', { family_name: 'Familia QA Navegador Borrador' }, adminCsrf);

const browser = await chromium.launch({
  executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium',
  args: ['--no-sandbox'],
});

/** Rectángulos de la casilla de consentimiento ya renderizada. */
async function consentGeometry(page) {
  return page.evaluate(() => {
    const label = document.querySelector('.consent-option');
    const box = document.getElementById('reg-consent');
    const lbl = label.querySelector('.lbl');
    const desc = label.querySelector('.desc');
    const r = (el) => { const b = el.getBoundingClientRect(); return { top: b.top, bottom: b.bottom, left: b.left, right: b.right, w: b.width, h: b.height }; };
    return {
      label: r(label), box: r(box), lbl: r(lbl), desc: r(desc),
      docWidth: document.documentElement.scrollWidth,
      viewWidth: window.innerWidth,
    };
  });
}

/** Abre el registro de una familia con la clave dada. */
async function openRegister(page, fam) {
  await page.goto(BASE + '/participar.php?f=' + fam.family.public_slug, { waitUntil: 'networkidle' });
  await page.waitForSelector('[data-action="to-key"]');
  await page.click('[data-action="to-key"]');
  await page.fill('#family-key', fam.access_code);
  await page.click('[data-action="check-key"]');
  // La pantalla resultante es el formulario de registro O el aviso específico
  // de bloqueo: se espera a que exista una de las dos, nunca a un h1 genérico.
  await page.waitForSelector('#reg-consent, .alert.alert-info', { timeout: 15000 });
}

// ============================================================
// A — PORTADA en navegador (E02/E03)
// ============================================================
{
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  const jsErrors = jsErrorCollector(page);

  await page.goto(BASE + '/index.php', { waitUntil: 'networkidle' });
  const before = page.url();
  await page.click('#invite-help-open');
  await page.waitForSelector('#invite-help:not([hidden])');
  const panelVisible = await page.isVisible('#invite-help');
  check('E02 el CTA de participantes NO navega a una liga inválida', page.url() === before);
  check('E03 el CTA abre un panel de ayuda accesible', panelVisible);
  check('E03b el panel explica que debe abrirse la liga completa',
    (await page.textContent('#invite-help')).includes('liga propia de su familia'));
  check('E03c el CTA declara su estado con aria-expanded',
    (await page.getAttribute('#invite-help-open', 'aria-expanded')) === 'true');
  await page.screenshot({ path: join(SHOTS, 'E03_portada_panel_invitacion.png'), fullPage: true });
  await page.keyboard.press('Escape');
  check('E03d el panel se cierra con Escape', !(await page.isVisible('#invite-help')));
  check('E78 portada sin errores de JavaScript', jsErrors.length === 0, jsErrors.join(' | '));
  await page.close();
}

// ============================================================
// G — AVISO DE PRIVACIDAD (E39-E42) en tres tamaños
// ============================================================
for (const vp of [
  { name: 'E39_escritorio_1280x900', width: 1280, height: 900 },
  { name: 'E40_movil_390x844', width: 390, height: 844 },
  { name: 'E41_movil_375x667', width: 375, height: 667 },
]) {
  const page = await browser.newPage({ viewport: { width: vp.width, height: vp.height } });
  const jsErrors = jsErrorCollector(page);

  await openRegister(page, abierta);
  const g = await consentGeometry(page);

  check(vp.name + ': el texto del aviso NO se empalma con el de participación',
    g.desc.top >= g.lbl.bottom - 0.5,
    'lbl.bottom=' + g.lbl.bottom.toFixed(1) + ' desc.top=' + g.desc.top.toFixed(1));
  check(vp.name + ': la casilla queda junto a su etiqueta',
    g.box.top >= g.label.top - 0.5 && g.box.bottom <= g.label.bottom + 0.5
    && g.box.right <= g.lbl.left + 0.5);
  check(vp.name + ': la casilla tiene tamaño usable (>=18px)', g.box.w >= 18 && g.box.h >= 18);
  check(vp.name + ': sin desbordamiento horizontal', g.docWidth <= g.viewWidth + 1);

  // E42 — label completo clicable y accesible.
  await page.click('.consent-option .desc');
  check(vp.name + ' (E42): al pulsar el texto del label se marca la casilla',
    await page.isChecked('#reg-consent'));
  await page.click('.consent-option .lbl');
  check(vp.name + ' (E42b): el label completo alterna la casilla',
    (await page.isChecked('#reg-consent')) === false);
  const described = await page.getAttribute('#reg-consent', 'aria-describedby');
  check(vp.name + ' (E42c): la casilla declara su descripción accesible',
    typeof described === 'string' && described.includes('consent-desc'));

  // Foco visible (contorno del contenedor con :focus-within).
  await page.focus('#reg-consent');
  const outline = await page.evaluate(() => {
    const el = document.querySelector('.consent-option');
    const cs = getComputedStyle(el);
    return { style: cs.outlineStyle, width: cs.outlineWidth };
  });
  check(vp.name + ': el foco de teclado es visible',
    outline.style !== 'none' && parseFloat(outline.width) > 0);

  await page.screenshot({ path: join(SHOTS, vp.name + '_privacidad.png'), fullPage: true });
  check(vp.name + ': sin errores de JavaScript', jsErrors.length === 0, jsErrors.join(' | '));
  await page.close();
}

// ============================================================
// F — FORMULARIO OCULTO CUANDO NO SE PUEDE REGISTRAR (E38)
// ============================================================
{
  const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
  await openRegister(page, borrador);
  const html = await page.content();
  check('E38 en Borrador no se muestran nombre, generación, rol ni consentimiento',
    !html.includes('id="reg-name"') && !html.includes('id="reg-generation"')
    && !html.includes('id="reg-role"') && !html.includes('id="reg-consent"'));
  check('E38b tampoco se muestra el botón «Registrarme»',
    !html.includes('data-action="do-register"'));
  check('E33 el motivo mostrado es el específico de Borrador',
    (await page.textContent('.question-card')).includes('Esta aplicación aún no ha sido habilitada por BVM.'));
  check('E38c en Borrador no se ofrece «Continuar donde me quedé»',
    !html.includes('data-action="to-resume"'));
  await page.screenshot({ path: join(SHOTS, 'E38_borrador_sin_formulario.png'), fullPage: true });
  await page.close();
}

// ============================================================
// F — CUPO LLENO: mensaje específico y continuidad visible (E37)
// ============================================================
{
  const lleno = await apiJson('/api/families/create.php',
    { family_name: 'Familia QA Navegador Cupo', expected_participants: 1, enforce_participant_limit: true }, adminCsrf);
  await apiJson('/api/families/update.php', { id: lleno.family.id, status: 'abierta' }, adminCsrf);
  const slug = lleno.family.public_slug;
  const pageCsrf = await csrfOf('/participar.php?f=' + slug);
  // Ocupa el único lugar con un cliente aparte (no comparte sesión con el navegador).
  const otro = new Map();
  const res = await fetch(BASE + '/participar.php?f=' + slug);
  for (const sc of (res.headers.getSetCookie ? res.headers.getSetCookie() : [])) {
    const [pair] = sc.split(';');
    const i = pair.indexOf('=');
    otro.set(pair.slice(0, i).trim(), pair.slice(i + 1).trim());
  }
  const html = await res.text();
  const csrf = (html.match(/name="csrf-token" content="([^"]+)"/) || [])[1];
  const cookie = Array.from(otro.entries()).map(([k, v]) => k + '=' + v).join('; ');
  const post = (p, b) => fetch(BASE + p, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Cookie: cookie },
    body: JSON.stringify(b),
  });
  await post('/api/participants/access.php', { slug, access_code: lleno.access_code });
  await post('/api/participants/register.php', {
    name: 'Único Lugar', generation: 'Primera generación',
    participation_role: 'Otro rol patrimonial', consent: true,
  });

  const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
  await openRegister(page, lleno);
  const text = await page.textContent('.question-card');
  const content = await page.content();
  check('E37 con el cupo lleno el mensaje es el específico',
    text.includes('Se alcanzó el número autorizado de participantes.'));
  check('E37b se conserva «Continuar donde me quedé» (la familia sigue abierta)',
    content.includes('data-action="to-resume"'));
  check('E38d con cupo lleno tampoco se muestra el formulario de registro',
    !content.includes('id="reg-name"'));
  await page.screenshot({ path: join(SHOTS, 'E37_cupo_lleno.png'), fullPage: true });
  await page.close();
  void pageCsrf;
}

await browser.close();
console.log('');
if (failures === 0) {
  console.log('NAVEGADOR 1.0.2.2: TODAS LAS PRUEBAS PASARON');
} else {
  console.log('NAVEGADOR 1.0.2.2: ' + failures + ' FALLAS');
}
process.exit(failures === 0 ? 0 : 1);
