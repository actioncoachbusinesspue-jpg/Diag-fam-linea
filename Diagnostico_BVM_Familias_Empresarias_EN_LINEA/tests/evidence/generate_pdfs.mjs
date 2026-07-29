/**
 * Genera la evidencia de reporte del prompt maestro (sección 19):
 *
 *   demo_letter.pdf / demo_a4.pdf     — Familia Horizonte (demostración)
 *   real_letter.pdf / real_a4.pdf     — familia real controlada
 *   stress_letter.pdf / stress_a4.pdf — familia de estrés (8-12 participantes)
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
async function emitReport(context, url, label, requireLogin) {
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
  await page.close();
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

await browser.close();
console.log(failures === 0 ? '\nPDF: LOS 6 DOCUMENTOS PASARON LA AUDITORÍA' : `\nPDF: ${failures} FALLAS`);
process.exit(failures === 0 ? 0 : 1);
