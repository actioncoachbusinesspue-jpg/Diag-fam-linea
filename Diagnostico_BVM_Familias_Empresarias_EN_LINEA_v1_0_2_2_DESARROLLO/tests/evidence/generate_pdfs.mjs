/**
 * Genera la evidencia de reporte del prompt maestro (sección 19):
 *
 *   demo_letter.pdf / demo_a4.pdf     — Familia Horizonte (demostración)
 *   real_letter.pdf / real_a4.pdf     — familia real controlada
 *   stress_letter.pdf / stress_a4.pdf — familia de estrés (8-12 participantes)
 *   tie_letter.pdf / tie_a4.pdf       — familia con empates exactos (1.0.2)
 *
 * Para cada documento: navega, activa la pestaña del reporte integral,
 * ejecuta la auditoría del maestro (prepareBvmPrint) y EXIGE 12 páginas con
 * fits:true antes de imprimir. Sale con error si cualquier documento falla.
 *
 * Uso: BASE_URL=... EVIDENCE_IDS=... PDF_DIR=... node generate_pdfs.mjs
 */
import { readFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8097';
const IDS = JSON.parse(readFileSync(process.env.EVIDENCE_IDS || 'qa-evidence/tmp/evidence_ids.json', 'utf8'));
const PDF_DIR = process.env.PDF_DIR || 'qa-evidence/pdf';
mkdirSync(PDF_DIR, { recursive: true });

const pwModule = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const { chromium } = pwModule.chromium ? pwModule : pwModule.default;

const browser = await chromium.launch({
  executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium',
  args: ['--no-sandbox'],
});

let failures = 0;
function check(name, cond, detail) {
  if (cond) { console.log('[OK]    ' + name); }
  else { failures++; console.log('[FALLA] ' + name + (detail ? ' — ' + detail : '')); }
}

async function login(page) {
  await page.goto(BASE + '/acceso-bvm.php', { waitUntil: 'networkidle' });
  await page.fill('#username', IDS.admin_user);
  await page.fill('#password', IDS.admin_pass);
  await Promise.all([page.waitForURL('**/admin/**'), page.click('#submit-btn')]);
}

/**
 * Abre `url`, monta el reporte integral, audita y emite Carta + A4.
 * `label` es el prefijo de archivo (demo|real|stress).
 */
/**
 * Verificaciones del parche de presentación 1.0.2 (empates) sobre la página
 * ya cargada del reporte de la familia de empates. Además de comprobar el
 * documento real (empate de 2), ejercita los casos sintéticos de 3 y 4
 * dimensiones contra la MISMA función parchada que usa el reporte.
 */
async function assertTieRendering(page) {
  // Documento real (la familia de empates produce empates de DOS en ambas tarjetas).
  const r = await page.evaluate(() => {
    const doc = document.body.innerHTML;
    return {
      pluralCoincidencia: doc.includes('Dimensiones de mayor coincidencia'),
      pluralDiferencia: doc.includes('Dimensiones de mayor diferencia'),
      twoWaySeparator: /Dimensiones de mayor coincidencia<\/div><div class="ind-value"[^>]*>[^<]+ \/ [^<]+</.test(doc),
      relativamente: doc.includes('Afirmaciones relativamente más consolidadas'),
    };
  });
  check('tie: tarjeta en plural (mayor coincidencia)', r.pluralCoincidencia);
  check('tie: tarjeta en plural (mayor diferencia)', r.pluralDiferencia);
  check('tie: empate de dos usa separador " / "', r.twoWaySeparator);
  check('tie: nivel bajo usa «relativamente más consolidadas»', r.relativamente);

  // Casos sintéticos de 3 y 4 dimensiones contra la MISMA función parchada,
  // clonando el contexto real y sustituyendo solo la dispersión mostrada.
  const synthetic = await page.evaluate(() => {
    const base = AdminDashboard._computed;
    if (!base || !base.alignment) { return { error: 'contexto de análisis no disponible' }; }
    function withDispersion(map) {
      const a = Object.assign({}, base.alignment, { dimensionDispersion: map });
      const dims = Object.keys(map);
      let mostAligned = dims[0], mostDispersed = dims[0];
      dims.forEach(function (d) {
        if (map[d] < map[mostAligned]) mostAligned = d;
        if (map[d] > map[mostDispersed]) mostDispersed = d;
      });
      a.mostAlignedDimension = mostAligned;
      a.mostDispersedDimension = mostDispersed;
      return AdminDashboard.renderAlineacionBody(Object.assign({}, base, { alignment: a }), { showTechButton: false });
    }
    const three = withDispersion({ relaciones: 0.5, gobierno: 0.5, desarrollo: 0.5, continuidad: 0.9 });
    const four = withDispersion({ relaciones: 0.5, gobierno: 0.5, desarrollo: 0.5, continuidad: 0.5 });
    const unique = withDispersion({ relaciones: 0.2, gobierno: 0.5, desarrollo: 0.7, continuidad: 0.9 });
    return {
      threeOk: three.includes('Empate entre: '),
      fourOk: four.includes('Coincidencia equivalente en las cuatro dimensiones')
        && four.includes('Diferencia equivalente en las cuatro dimensiones'),
      uniqueOk: unique.includes('Dimensión de mayor coincidencia')
        && !unique.includes('Dimensiones de mayor coincidencia'),
    };
  }).catch((e) => ({ error: String(e) }));
  if (synthetic.error) {
    check('tie: casos sintéticos 3 y 4 ejecutables', false, synthetic.error);
  } else {
    check('tie: empate de tres usa «Empate entre: …»', synthetic.threeOk === true);
    check('tie: empate de cuatro usa texto de equivalencia', synthetic.fourOk === true);
    check('tie: máximo único conserva la tarjeta original', synthetic.uniqueOk === true);
  }
}

async function emitReport(context, url, label, requireLogin, opts = {}) {
  const page = await context.newPage();
  const consoleErrors = [];
  page.on('pageerror', (e) => consoleErrors.push(String(e)));
  page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });

  if (requireLogin) await login(page);
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(800);

  const audit = await page.evaluate(async () => {
    StateManager.setAdmin({ tab: 'reporte-integral' });
    await new Promise((r) => setTimeout(r, 500));
    const rows = await window.prepareBvmPrint();
    return {
      pages: rows.length,
      allFit: window.__BVM_PRINT_QA_PASS__,
      overflowing: rows.filter((r) => !r.fits).map((r) => r.page),
    };
  });
  check(label + ': 12 páginas auditadas', audit.pages === 12, String(audit.pages));
  check(label + ': todas caben (fits:true)', audit.allFit === true,
    'páginas con exceso: ' + audit.overflowing.join(','));
  check(label + ': sin errores JS', consoleErrors.length === 0, consoleErrors.slice(0, 3).join(' | '));

  for (const [suffix, format] of [['letter', 'Letter'], ['a4', 'A4']]) {
    const path = join(PDF_DIR, label + '_' + suffix + '.pdf');
    await page.pdf({
      path, format, printBackground: true,
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      displayHeaderFooter: false,
    });
    console.log('[OK]    ' + label + ': escrito ' + path);
  }
  if (opts.keepOpen) { return page; }
  await page.close();
  return null;
}

// Demostración (pública, Familia Horizonte, sin login)
{
  const context = await browser.newContext();
  await emitReport(context, BASE + '/demostracion.php', 'demo', false);
  await context.close();
}
// Familia real controlada
{
  const context = await browser.newContext();
  await emitReport(context, BASE + '/admin/reporte.php?id=' + IDS.real_family_id, 'real', true);
  await context.close();
}
// Familia de estrés
{
  const context = await browser.newContext();
  await emitReport(context, BASE + '/admin/reporte.php?id=' + IDS.stress_family_id, 'stress', true);
  await context.close();
}
// Familia de empates (1.0.2): PDF + verificación del parche de presentación
if (IDS.tie_family_id) {
  const context = await browser.newContext();
  const page = await emitReport(context, BASE + '/admin/reporte.php?id=' + IDS.tie_family_id, 'tie', true, { keepOpen: true });
  await assertTieRendering(page);
  await page.close();
  await context.close();
}

await browser.close();
console.log(failures === 0 ? '\nPDF: LOS 8 DOCUMENTOS PASARON LA AUDITORÍA' : `\nPDF: ${failures} FALLAS`);
process.exit(failures === 0 ? 0 : 1);
