/**
 * Renderiza cada PDF de evidencia a PNG por página (inspección visual) y
 * compone una hoja de contacto por documento (12 miniaturas + folio).
 *
 *   qa-evidence/png/<doc>/page-NN.png
 *   qa-evidence/contact-sheets/<doc>_contacto.png
 *
 * Exige exactamente 12 páginas por PDF; termina con error si difiere.
 *
 * Uso: PDF_DIR=... PNG_DIR=... SHEET_DIR=... node render_pngs.mjs
 */
import { mkdirSync, rmSync, readdirSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

const PDF_DIR = process.env.PDF_DIR || 'qa-evidence/pdf';
const PNG_DIR = process.env.PNG_DIR || 'qa-evidence/png';
const SHEET_DIR = process.env.SHEET_DIR || 'qa-evidence/contact-sheets';

const { pdfToPng } = await import(process.env.PDF2PNG_MODULE || 'pdf-to-png-converter');
const pwModule = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const { chromium } = pwModule.chromium ? pwModule : pwModule.default;

const pdfs = readdirSync(PDF_DIR).filter((f) => f.endsWith('.pdf')).sort();
if (pdfs.length === 0) {
  console.error('[FALLA] No hay PDFs en ' + PDF_DIR);
  process.exit(1);
}

let failures = 0;
const sheets = [];

for (const pdf of pdfs) {
  const doc = pdf.replace(/\.pdf$/, '');
  const outDir = join(PNG_DIR, doc);
  rmSync(outDir, { recursive: true, force: true });
  mkdirSync(outDir, { recursive: true });

  const pages = await pdfToPng(join(PDF_DIR, pdf), {
    viewportScale: 1.5,
    // Con fuentes embebidas vía FontFace el rasterizador de Node pinta bloques;
    // la sustitución estándar de pdfjs sí produce texto legible y fiel.
    disableFontFace: true,
    outputFolder: outDir,
    outputFileMaskFunc: (n) => 'page-' + String(n).padStart(2, '0') + '.png',
    returnPageContent: false,
  });
  if (pages.length === 12) {
    console.log('[OK]    ' + pdf + ' → 12 PNG en ' + outDir);
  } else {
    failures++;
    console.log('[FALLA] ' + pdf + ' tiene ' + pages.length + ' páginas (se esperaban 12)');
  }
  sheets.push({ doc, outDir, count: pages.length });
}

// ---------- Hojas de contacto ----------
mkdirSync(SHEET_DIR, { recursive: true });
const browser = await chromium.launch({
  executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium',
  args: ['--no-sandbox'],
});
const page = await browser.newPage({ viewport: { width: 1480, height: 1200 } });

for (const { doc, outDir, count } of sheets) {
  const cells = Array.from({ length: count }, (_, i) => {
    const n = String(i + 1).padStart(2, '0');
    const src = 'file://' + resolve(outDir, 'page-' + n + '.png');
    return `<figure><img src="${src}" alt="Página ${i + 1}"><figcaption>Página ${i + 1}</figcaption></figure>`;
  }).join('\n');
  const html = `<!doctype html><html><head><meta charset="utf-8"><style>
    body{ margin:0; padding:28px; background:#172541; font-family:sans-serif; }
    h1{ color:#EDCD5B; font-size:20px; font-weight:600; margin:0 0 4px; }
    p{ color:#c9d2e3; font-size:12px; margin:0 0 20px; }
    .grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
    figure{ margin:0; }
    img{ width:100%; display:block; background:#fff; box-shadow:0 2px 10px rgba(0,0,0,.45); }
    figcaption{ color:#c9d2e3; font-size:11px; text-align:center; margin-top:6px; }
  </style></head><body>
    <h1>Diagnóstico BVM — hoja de contacto: ${doc}</h1>
    <p>${count} páginas · evidencia generada automáticamente (qa-evidence/pdf/${doc}.pdf)</p>
    <div class="grid">${cells}</div>
  </body></html>`;
  const tmpHtml = join(SHEET_DIR, '.' + doc + '.tmp.html');
  writeFileSync(tmpHtml, html);
  await page.goto('file://' + resolve(tmpHtml), { waitUntil: 'networkidle' });
  const sheetPath = join(SHEET_DIR, doc + '_contacto.png');
  await page.screenshot({ path: sheetPath, fullPage: true });
  rmSync(tmpHtml);
  console.log('[OK]    Hoja de contacto: ' + sheetPath);
}

await browser.close();
console.log(failures === 0
  ? '\nPNG: TODOS LOS DOCUMENTOS RENDERIZADOS (12/12 páginas)'
  : `\nPNG: ${failures} FALLAS`);
process.exit(failures === 0 ? 0 : 1);
