/**
 * E2E de navegador (Chromium via playwright-core) sobre demostración y reporte.
 * Requiere el servidor de run_e2e.sh o equivalente ya corriendo, con la
 * variable BASE_URL. Verifica:
 *  - la demostración arranca sola en modo Familia Horizonte, sin login,
 *    sin errores de consola y sin escribir en localStorage;
 *  - el reporte real (previa autenticación) renderiza las 12 páginas con
 *    los datos del servidor y la auditoría de impresión pasa (fits:true);
 *  - móvil 390×844 renderiza la página pública sin desbordes.
 *
 * Uso: PLAYWRIGHT_MODULE=<ruta a playwright-core> BASE_URL=... node e2e_browser.mjs
 */
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8098';
const pwModule = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const { chromium } = pwModule.chromium ? pwModule : pwModule.default;

let failures = 0;
function check(name, cond, detail) {
  if (cond) { console.log('[OK]    ' + name); }
  else { failures++; console.log('[FALLA] ' + name + (detail ? ' — ' + detail : '')); }
}

const browser = await chromium.launch({
  executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium',
  args: ['--no-sandbox'],
});

// ---------- Demostración pública ----------
{
  const page = await browser.newPage();
  const consoleErrors = [];
  page.on('pageerror', (e) => consoleErrors.push(String(e)));
  page.on('console', (msg) => { if (msg.type() === 'error') { consoleErrors.push(msg.text()); } });

  await page.goto(BASE + '/demostracion.php', { waitUntil: 'networkidle' });
  await page.waitForTimeout(600);

  const body = await page.textContent('body');
  check('Demo entra directo al modo comercial (Familia Horizonte)', body.includes('Familia Horizonte'));
  check('Demo sin errores de JavaScript', consoleErrors.length === 0, consoleErrors.slice(0, 3).join(' | '));

  const lsCount = await page.evaluate(() => {
    try { return window.localStorage.length; } catch (e) { return 'deshabilitado'; }
  });
  check('Demo: localStorage deshabilitado (modo memoria)', lsCount === 'deshabilitado', String(lsCount));

  const memoryMode = await page.evaluate(() => StorageAdapter.isUsingMemoryFallback());
  check('Demo: StorageAdapter en memoria', memoryMode === true);

  const generalIndex = await page.evaluate(() => {
    const fam = StorageAdapter.getFamily('FAM_HORIZONTE_DEMO');
    const scoring = ScoringEngine.buildFullScoring(fam.participants);
    return scoring.generalIndex;
  });
  check('Demo: índice general Horizonte = 55', generalIndex === 55, String(generalIndex));

  // E48 — ni siquiera desde consola se puede pasar al modo administrativo.
  const afterEnterAdmin = await page.evaluate(() => {
    AppModeManager.enterAdmin();
    const s = StateManager.get();
    return { view: s.view, commercial: s.admin.commercial };
  });
  check('E48 demo: enterAdmin() desde consola queda neutralizado',
    afterEnterAdmin.view === 'admin' && afterEnterAdmin.commercial === true,
    JSON.stringify(afterEnterAdmin));
  await page.close();
}

// ---------- Reporte real (autenticado) ----------
{
  const context = await browser.newContext();
  const page = await context.newPage();
  const consoleErrors = [];
  page.on('pageerror', (e) => consoleErrors.push(String(e)));

  // Login
  await page.goto(BASE + '/acceso-bvm.php', { waitUntil: 'networkidle' });
  await page.fill('#username', 'bvm.e2e');
  await page.fill('#password', 'contrasena-segura-e2e-1');
  await Promise.all([page.waitForURL('**/admin/**'), page.click('#submit-btn')]);
  check('Login de navegador llega a administración', page.url().includes('/admin/'));

  // Reporte de la familia 1 (creada por e2e_api.mjs)
  await page.goto(BASE + '/admin/reporte.php?id=1', { waitUntil: 'networkidle' });
  await page.waitForTimeout(800);
  const body = await page.textContent('body');
  check('Reporte carga con la familia real', body.includes('Familia Robles'));
  check('Reporte sin errores de JavaScript', consoleErrors.length === 0, consoleErrors.slice(0, 3).join(' | '));

  const fromServer = await page.evaluate(() => {
    const fam = StorageAdapter.getActiveFamily();
    return fam ? { name: fam.familyName, participants: fam.participants.length } : null;
  });
  check('Motor lee datos del servidor (3 participantes)',
    fromServer && fromServer.name === 'Familia Robles' && fromServer.participants === 3,
    JSON.stringify(fromServer));

  // Auditoría de impresión del propio maestro: 12 páginas, todas fits:true.
  // El reporte se monta al activar su pestaña.
  const audit = await page.evaluate(async () => {
    StateManager.setAdmin({ tab: 'reporte-integral' });
    await new Promise((r) => setTimeout(r, 400));
    const result = await window.prepareBvmPrint();
    return { pages: result.length, allFit: window.__BVM_PRINT_QA_PASS__ };
  });
  check('Reporte: 12 páginas auditadas', audit.pages === 12, String(audit.pages));
  check('Reporte: todas las páginas caben (fits:true)', audit.allFit === true);

  await context.close();
}

// ---------- Móvil 390×844 ----------
{
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  await page.goto(BASE + '/index.php', { waitUntil: 'networkidle' });
  const overflow = await page.evaluate(() =>
    document.documentElement.scrollWidth - document.documentElement.clientWidth);
  check('E31 móvil 390×844 sin desborde horizontal', overflow <= 1, overflow + 'px');
  const demoBtn = await page.textContent('body');
  check('E31b acciones visibles en móvil', demoBtn.includes('Iniciar demostración guiada'));
  await context.close();
}

// ---------- Móvil 375×667 ----------
{
  const context = await browser.newContext({ viewport: { width: 375, height: 667 } });
  const page = await context.newPage();
  await page.goto(BASE + '/index.php', { waitUntil: 'networkidle' });
  const overflow = await page.evaluate(() =>
    document.documentElement.scrollWidth - document.documentElement.clientWidth);
  check('E32 móvil 375×667 sin desborde horizontal', overflow <= 1, overflow + 'px');
  await context.close();
}

await browser.close();
console.log(failures === 0 ? '\nNAVEGADOR: TODAS LAS PRUEBAS PASARON' : `\nNAVEGADOR: ${failures} FALLAS`);
process.exit(failures === 0 ? 0 : 1);
