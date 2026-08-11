/**
 * Verificaciones estáticas E45-E49 del prompt maestro sobre el código público
 * y las respuestas servidas:
 *
 *  - E47: ninguna ruta del código público contiene localhost/127.0.0.1
 *         (en particular, la redirección 127.0.0.1:8099 de la entrega
 *         anterior no debe existir en ninguna parte del proyecto);
 *  - E48: la demostración servida neutraliza AppModeManager.enterAdmin;
 *  - E49: no hay contraseñas ni PIN utilizables en el frontend propio;
 *  - E46: los errores de API no exponen trazas PHP, SQL ni rutas internas.
 *
 * Uso: BASE_URL=... node tests/e2e/static_checks.mjs (servidor ya corriendo)
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8098';
const ROOT = join(fileURLToPath(new URL('.', import.meta.url)), '..', '..');

let failures = 0;
function check(name, cond, detail) {
  if (cond) { console.log('[OK]    ' + name); }
  else { failures++; console.log('[FALLA] ' + name + (detail ? ' — ' + detail : '')); }
}

function walk(dir) {
  const out = [];
  for (const entry of readdirSync(dir)) {
    const p = join(dir, entry);
    if (statSync(p).isDirectory()) { out.push(...walk(p)); }
    else { out.push(p); }
  }
  return out;
}

// ---------- E47: sin localhost/127.0.0.1 en el código propio ----------
// Se examina todo el código fuente desplegable (public/ y private/, excepto
// el motor de referencia, que se sirve adaptado y se verifica ya servido).
{
  const files = [...walk(join(ROOT, 'public')), ...walk(join(ROOT, 'private'))]
    .filter((f) => !f.includes('reference-app'))
    // health_checks.php contiene los literales 'localhost'/'127.0.0.1'
    // precisamente para DETECTAR una base_url mal configurada; no son rutas.
    .filter((f) => !f.endsWith('health_checks.php'))
    .filter((f) => /\.(php|js|css|html|htaccess)$/.test(f) || f.endsWith('.htaccess'));
  const offenders = [];
  for (const f of files) {
    const src = readFileSync(f, 'utf8');
    // La prohibición del prompt es sobre RUTAS/URLs (redirecciones, ligas,
    // fetch). El host de MySQL 'localhost' de la configuración es legítimo
    // en hosting compartido y no es una ruta web.
    if (/https?:\/\/(?:localhost|127\.0\.0\.1)|\/\/localhost|127\.0\.0\.1/i.test(src)) {
      offenders.push(f.replace(ROOT + '/', ''));
    }
  }
  check('E47 código desplegable sin rutas con localhost/127.0.0.1', offenders.length === 0, offenders.join(', '));
}

// ---------- E49: sin credenciales o PIN utilizables en el frontend propio ----------
{
  const files = walk(join(ROOT, 'public', 'assets'));
  const offenders = [];
  for (const f of files) {
    const src = readFileSync(f, 'utf8');
    if (/localPin|password\s*[:=]\s*['"][^'"]+['"]|contrase(?:ñ|n)a\s*[:=]\s*['"][^'"]+['"]/i.test(src)) {
      offenders.push(f.replace(ROOT + '/', ''));
    }
  }
  check('E49 assets propios sin contraseñas ni PIN embebidos', offenders.length === 0, offenders.join(', '));
}

// ---------- Comprobaciones sobre las páginas servidas ----------
{
  const res = await fetch(BASE + '/demostracion.php');
  const html = await res.text();
  check('E48 demo servida neutraliza AppModeManager.enterAdmin',
    html.includes('AppModeManager.enterAdmin = function()'));
  check('E47b demo servida sin la redirección 127.0.0.1:8099 de la entrega anterior',
    !html.includes('127.0.0.1:8099'));
  check('E49b demo servida con PIN local deshabilitado y vacío',
    html.includes('localPinEnabled: false') && html.includes("localPin: ''"));
}

// ---------- E46: errores sin trazas PHP/SQL ----------
{
  const probes = [
    ['/api/families/detail.php?id=abc', 'GET', null],
    ['/api/participants/access.php', 'POST', '{"slug":"x","access_code":"y"'], // JSON malformado
    ['/api/responses/save.php', 'POST', 'no-es-json'],
  ];
  const leaks = [];
  for (const [path, method, body] of probes) {
    const res = await fetch(BASE + path, {
      method,
      headers: body ? { 'Content-Type': 'application/json' } : {},
      body: body || undefined,
    });
    const text = await res.text();
    if (/Fatal error|Stack trace|SQLSTATE|Warning: |on line \d+|\/private\//.test(text)) {
      leaks.push(path + ' → ' + text.slice(0, 80));
    }
  }
  check('E46 errores de API sin trazas PHP/SQL ni rutas internas', leaks.length === 0, leaks.join(' | '));
}

// ---------- 1.0.2.2: referencia metodológica congelada y casilla de cupo ----------
{
  const { createHash } = await import('node:crypto');
  const { readFileSync } = await import('node:fs');
  const { dirname, join } = await import('node:path');
  const { fileURLToPath } = await import('node:url');
  const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
  // Hash registrado en qa-evidence/v1.0.2.2/CONGELAMIENTO_HASHES.md al abrir la
  // versión. Si cambia, el motor metodológico se editó en disco: falla la suite.
  const FROZEN = '5a89db7793c264e1b7ab930d5490b72b8f8d96e320c9b404e9c1a8d1104346bf';
  const sha = (p) => createHash('sha256').update(readFileSync(p)).digest('hex');
  const refA = sha(join(root, 'reference', 'Diagnostico_BVM_Familias_Empresarias_EN_LINEA_DESARROLLO.html'));
  const refB = sha(join(root, 'private', 'reference-app', 'referencia_app.html'));
  check('E-REF referencia metodológica congelada (SHA-256 sin cambios)',
    refA === FROZEN && refB === FROZEN, refA + ' / ' + refB);

  const familias = readFileSync(join(root, 'public', 'admin', 'familias.php'), 'utf8');
  const checkboxTag = (familias.match(/<input type="checkbox" id="enforce_limit"[^>]*>/) || [''])[0];
  check('E24 la casilla de límite de cupo nace DESMARCADA en el formulario',
    checkboxTag !== '' && !/checked/.test(checkboxTag), checkboxTag);
  check('E25 al reiniciar el formulario la casilla queda desmarcada explícitamente',
    /getElementById\('enforce_limit'\)\.checked = false;/.test(familias));

  const createApi = readFileSync(join(root, 'public', 'api', 'families', 'create.php'), 'utf8');
  check('E24b el backend guarda «referencia» cuando la casilla no viaja',
    /enforce_participant_limit'\] \?\? false/.test(createApi));
}

console.log(failures === 0 ? '\nESTÁTICAS: TODAS LAS PRUEBAS PASARON' : `\nESTÁTICAS: ${failures} FALLAS`);
process.exit(failures === 0 ? 0 : 1);
