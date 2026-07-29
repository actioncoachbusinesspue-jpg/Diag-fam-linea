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

console.log(failures === 0 ? '\nESTÁTICAS: TODAS LAS PRUEBAS PASARON' : `\nESTÁTICAS: ${failures} FALLAS`);
process.exit(failures === 0 ? 0 : 1);
