/**
 * Prueba de PARIDAD METODOLÓGICA — Familia Horizonte.
 *
 * 1. Extrae VERBATIM el motor de cálculo del archivo maestro de referencia
 *    (QUESTIONNAIRE, SCORING_CONFIG, ScoringEngine, AlignmentEngine, etc.).
 * 2. Calcula los resultados de Familia Horizonte con los datos embebidos
 *    (SAMPLE_FAMILY_DATA) — la ruta que usa el HTML estable.
 * 3. Calcula los resultados con el objeto familia producido por la BASE DE
 *    DATOS en línea (qa-evidence/parity/family_from_db.json, generado por
 *    tests/integration/run_integration.php).
 * 4. Compara cada indicador. La diferencia permitida es 0.
 *
 * Uso: node tests/parity/parity_check.mjs
 */
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const referencePath = join(root, 'reference', 'Diagnostico_BVM_Familias_Empresarias_EN_LINEA_DESARROLLO.html');
const source = readFileSync(referencePath, 'utf8');

function slice(startAnchor, endAnchor) {
  const start = source.indexOf(startAnchor);
  if (start === -1) { throw new Error('Ancla no encontrada: ' + startAnchor); }
  const end = source.indexOf(endAnchor, start + startAnchor.length);
  if (end === -1) { throw new Error('Ancla final no encontrada: ' + endAnchor); }
  return source.slice(start, end);
}

// Segmentos verbatim del maestro (las anclas son declaraciones únicas).
const engineSource = [
  slice('const APP_CONFIG = {', 'const PRIVACY_CONFIG'),
  slice('const QUESTIONNAIRE = {', 'const SCORING_CONFIG'),
  slice('const SCORING_CONFIG = {', 'const INTERPRETATION_RULES'),
  slice('const SAMPLE_FAMILY_DATA = {', '// MVP LOCAL BVM'),
  slice('function round2(n)', 'function showToast'),
  slice('const ValidationEngine = {', 'const NarrativeEngine'),
  // Los const del script no se adjuntan al contexto del VM: exportación explícita.
  'globalThis.__BVM_ENGINE__ = { ScoringEngine, AlignmentEngine, SAMPLE_FAMILY_DATA_v1, getMaturityLevel, getDispersionLevel };',
].join('\n\n');

const context = { console };
vm.createContext(context);
vm.runInContext(engineSource, context, { filename: 'motor-referencia.js' });

const { ScoringEngine, AlignmentEngine, SAMPLE_FAMILY_DATA_v1, getMaturityLevel, getDispersionLevel } = context.__BVM_ENGINE__;

function fullResults(participants) {
  const scoring = ScoringEngine.buildFullScoring(participants);
  const alignment = AlignmentEngine.buildFullAlignment(participants);
  return {
    questionAverages: scoring.questionAverages,
    dimensionScores: scoring.dimensionScores,
    normalizedDimensionScores: scoring.normalizedDimensionScores,
    generalIndex: scoring.generalIndex,
    generalLevel: getMaturityLevel(scoring.generalIndex).id,
    externalQuestionAverages: scoring.externalQuestionAverages,
    questionDispersion: alignment.questionDispersion,
    dimensionDispersion: alignment.dimensionDispersion,
    generalDispersion: alignment.generalDispersion,
    generalDispersionLevel: getDispersionLevel(alignment.generalDispersion).id,
    finishedCount: alignment.finishedCount,
    alignmentInterpretable: alignment.alignmentInterpretable,
    mostAlignedDimension: alignment.mostAlignedDimension,
    mostDispersedDimension: alignment.mostDispersedDimension,
  };
}

let failures = 0;
function check(name, cond, detail) {
  if (cond) { console.log('[OK]    ' + name); }
  else { failures++; console.log('[FALLA] ' + name + (detail ? ' — ' + detail : '')); }
}

// --- 1. Ruta del maestro: datos embebidos -----------------------------------
const master = fullResults(SAMPLE_FAMILY_DATA_v1().participants);

// Valores documentados del perfil Horizonte en el maestro (v1.6.11):
check('Relaciones = 69/100', master.normalizedDimensionScores.relaciones === 69,
  String(master.normalizedDimensionScores.relaciones));
check('Gobierno = 56/100', master.normalizedDimensionScores.gobierno === 56,
  String(master.normalizedDimensionScores.gobierno));
check('Desarrollo = 58/100', master.normalizedDimensionScores.desarrollo === 58,
  String(master.normalizedDimensionScores.desarrollo));
check('Continuidad = 37/100', master.normalizedDimensionScores.continuidad === 37,
  String(master.normalizedDimensionScores.continuidad));
check('Índice general = 55/100 (Parcial)', master.generalIndex === 55 && master.generalLevel === 'parcial',
  master.generalIndex + '/' + master.generalLevel);

// --- 2. Ruta en línea: objeto familia producido por la base de datos --------
const dbJsonPath = join(root, 'qa-evidence', 'parity', 'family_from_db.json');
let online = null;
try {
  const familyFromDb = JSON.parse(readFileSync(dbJsonPath, 'utf8'));
  online = fullResults(familyFromDb.participants);
} catch (e) {
  check('Objeto familia desde la base disponible', false,
    'ejecute antes: php tests/integration/run_integration.php (' + e.message + ')');
}

if (online) {
  const diffs = [];
  const compare = (label, a, b) => {
    const same = JSON.stringify(a) === JSON.stringify(b);
    if (!same) { diffs.push({ label, master: a, online: b }); }
    check('Paridad: ' + label, same, JSON.stringify(a) + ' vs ' + JSON.stringify(b));
  };
  compare('promedios por afirmación (20)', master.questionAverages, online.questionAverages);
  compare('promedios crudos por dimensión', master.dimensionScores, online.dimensionScores);
  compare('índices dimensionales 0-100', master.normalizedDimensionScores, online.normalizedDimensionScores);
  compare('índice general', master.generalIndex, online.generalIndex);
  compare('nivel de madurez', master.generalLevel, online.generalLevel);
  compare('promedios de preguntas externas', master.externalQuestionAverages, online.externalQuestionAverages);
  compare('dispersión por afirmación (20)', master.questionDispersion, online.questionDispersion);
  compare('dispersión por dimensión', master.dimensionDispersion, online.dimensionDispersion);
  compare('dispersión general y nivel',
    [master.generalDispersion, master.generalDispersionLevel],
    [online.generalDispersion, online.generalDispersionLevel]);
  compare('alineación (interpretable, extremos)',
    [master.alignmentInterpretable, master.mostAlignedDimension, master.mostDispersedDimension],
    [online.alignmentInterpretable, online.mostAlignedDimension, online.mostDispersedDimension]);

  const outDir = join(root, 'qa-evidence', 'parity');
  mkdirSync(outDir, { recursive: true });
  writeFileSync(join(outDir, 'parity_report.json'), JSON.stringify({
    generatedBy: 'tests/parity/parity_check.mjs',
    result: failures === 0 ? 'PARIDAD EXACTA' : 'DIFERENCIAS DETECTADAS',
    master, online, diffs,
  }, null, 2));
  console.log('\nEvidencia escrita en qa-evidence/parity/parity_report.json');
}

console.log(failures === 0 ? '\nPARIDAD: TODAS LAS PRUEBAS PASARON' : `\nPARIDAD: ${failures} FALLAS — LA ENTREGA DEBE DETENERSE`);
process.exit(failures === 0 ? 0 : 1);
