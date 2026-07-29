/* ============================================================
   bvm-cuestionario.js — cuestionario BVM-FE-1.2
   EXTRAÍDO VERBATIM del archivo maestro estable
   (Diagnostico_BVM_Familias_Empresarias.html, v1.6.11).
   NO EDITAR: cualquier cambio rompe la paridad metodológica.
   ============================================================ */
const QUESTIONNAIRE = {
  scale: [
    { value:1, label:'No existe', description:'No contamos con esta práctica, criterio o mecanismo.' },
    { value:2, label:'Incipiente', description:'Ocurre en algunos casos, pero depende de personas o esfuerzos aislados.' },
    { value:3, label:'Parcial', description:'Existe, aunque se aplica de manera irregular o no todos lo comprenden de la misma forma.' },
    { value:4, label:'Estable', description:'Se aplica habitualmente, aunque todavía presenta algunos vacíos.' },
    { value:5, label:'Consolidado', description:'Está claramente definido, se aplica consistentemente y se revisa cuando es necesario.' }
  ],
  scaleInstructions: 'Responda pensando en lo que ocurre habitualmente, no solamente en las intenciones de la familia ni en un caso aislado. Una calificación de 5 implica que la capacidad está claramente definida, se aplica de manera consistente y puede reconocerse en la práctica.',
  dimensions: [
    { id:'relaciones', name:'Relaciones familiares' },
    { id:'gobierno', name:'Gobierno y toma de decisiones' },
    { id:'desarrollo', name:'Desarrollo del patrimonio' },
    { id:'continuidad', name:'Continuidad y legado' }
  ],
  items: [
    { id:1, dim:'relaciones', text:'En nuestra familia podemos expresar desacuerdos sobre asuntos patrimoniales importantes sin evitar la conversación ni deteriorar la relación.' },
    { id:2, dim:'relaciones', text:'Los acuerdos familiares importantes quedan explícitos y pueden revisarse posteriormente sin depender de la memoria o de interpretaciones personales.' },
    { id:3, dim:'relaciones', text:'Los temas familiares difíciles se abordan antes de que se conviertan en problemas mayores.' },
    { id:4, dim:'relaciones', text:'Cuando existen diferencias sobre el patrimonio, podemos tratarlas con criterios y espacios acordados sin trasladarlas al plano personal.' },
    { id:5, dim:'relaciones', text:'Contamos con una forma acordada de avanzar cuando no alcanzamos consenso sobre una decisión familiar importante.' },
    { id:6, dim:'gobierno', text:'En las decisiones patrimoniales relevantes está claro quién propone, quién analiza, quién decide y quién es responsable de ejecutar.' },
    { id:7, dim:'gobierno', text:'Las decisiones patrimoniales importantes se toman dentro de los plazos que requieren las oportunidades o los riesgos.' },
    { id:8, dim:'gobierno', text:'Después de una decisión importante quedan definidos los responsables, los plazos y la forma de verificar su cumplimiento.' },
    { id:9, dim:'gobierno', text:'Nuestras reglas y mecanismos de decisión seguirían funcionando si aumentara el número de familiares que participan en las decisiones patrimoniales.' },
    { id:10, dim:'gobierno', text:'Los acuerdos sobre decisiones relevantes quedan suficientemente claros para que quienes participan los comprendan y apliquen de la misma manera.' },
    { id:11, dim:'desarrollo', text:'Contamos con una práctica periódica para identificar nuevas fuentes de crecimiento y generación de valor patrimonial.' },
    { id:12, dim:'desarrollo', text:'Existe una visión compartida, con prioridades claras, sobre el patrimonio que queremos construir durante los próximos diez o veinte años.' },
    { id:13, dim:'desarrollo', text:'Revisamos periódicamente si nuestras decisiones e inversiones patrimoniales están generando los resultados esperados y realizamos ajustes cuando es necesario.' },
    { id:14, dim:'desarrollo', text:'Las oportunidades que cumplen nuestros criterios de evaluación se convierten oportunamente en decisiones, responsables y acciones concretas.' },
    { id:15, dim:'desarrollo', text:'Contamos con criterios claros y compartidos para comparar oportunidades, rendimientos, riesgos y necesidades de liquidez antes de decidir.' },
    { id:16, dim:'continuidad', text:'Las nuevas generaciones reciben experiencias, responsabilidades y retroalimentación progresivas para prepararse para su futura participación.' },
    { id:17, dim:'continuidad', text:'La familia ha definido qué quiere preservar, qué está dispuesta a transformar y qué espera transmitir a las siguientes generaciones.' },
    { id:18, dim:'continuidad', text:'Contamos con criterios explícitos para asignar futuras responsabilidades con base en capacidades, preparación y compromiso, y no únicamente por parentesco o antigüedad.' },
    { id:19, dim:'continuidad', text:'El conocimiento y la experiencia necesarios para cuidar y desarrollar el patrimonio se transfieren de manera planificada entre generaciones.' },
    { id:20, dim:'continuidad', text:'Existe una ruta acordada para las futuras transiciones de liderazgo, propiedad y participación familiar.' }
  ],
  // v1.0: preguntas externas — NUNCA participan en el índice, las dimensiones,
  // el radar, la matriz, el nivel de madurez ni el orden de brechas.
  externalQuestions: [
    { id:'ext1', key:'priorityNextTwelveMonths',
      text:'Considerando los temas que acaba de responder, ¿qué tan prioritario considera que sería para su familia fortalecer estas capacidades durante los próximos doce meses?',
      options:[
        { value:1, label:'No es una prioridad actualmente.' },
        { value:2, label:'Es una prioridad baja.' },
        { value:3, label:'Conviene analizarlo con mayor profundidad.' },
        { value:4, label:'Es una prioridad importante.' },
        { value:5, label:'Es una prioridad inmediata.' }
      ] },
    { id:'ext2', key:'willingnessToEngage',
      text:'¿Qué tan dispuesto(a) estaría a participar en un proceso de conversaciones y acciones para convertir los principales hallazgos en acuerdos, mecanismos y capacidades concretas?',
      options:[
        { value:1, label:'Aún no lo considero necesario.' },
        { value:2, label:'Necesitaría comprender mejor el alcance.' },
        { value:3, label:'Estaría dispuesto(a) a explorarlo.' },
        { value:4, label:'Estaría dispuesto(a) a participar.' },
        { value:5, label:'Considero importante comenzar.' }
      ] }
  ],
  externalQuestionsIntro: 'Las siguientes respuestas no modifican la calificación de la familia.',
  // Campos de identificación — separados metodológicamente:
  // una persona puede pertenecer a una generación Y tener una forma de
  // participación operativa al mismo tiempo; no son la misma pregunta.
  generations: [
    'Primera generación',
    'Segunda generación',
    'Tercera generación o posterior',
    'Prefiero no indicarlo'
  ],
  // v1.6: se eliminó "participationTypes" (Forma de participación actual).
  // Tres de sus cinco opciones repetían las de participationRoles con otras
  // palabras, así que el integrante declaraba lo mismo dos veces y ninguno de
  // los dos campos alimentaba el cálculo. Se conserva el rol patrimonial, que
  // es el más informativo para BVM.
  // v1.0: rol relacionado con empresa, propiedad o patrimonio.
  participationRoles: [
    'Dirección o liderazgo operativo',
    'Consejo, órgano de gobierno o comité familiar',
    'Accionista o propietario sin rol operativo',
    'Futuro propietario o heredero',
    'Otro rol patrimonial',
    'Prefiero no indicarlo'
  ]
};


// ============================================================
// normalizeLikert — convierte una respuesta de escala 1-5 a una
// intensidad de 0 a 1, para uso interno en cálculos compuestos.
// 1 → 0.00, 2 → 0.25, 3 → 0.50, 4 → 0.75, 5 → 1.00

if (typeof module !== "undefined" && module.exports) { module.exports = { QUESTIONNAIRE: QUESTIONNAIRE }; }
