/* ============================================================
   bvm-participante.js — flujo del participante en línea.
   La base de datos es la fuente de verdad: cada respuesta se guarda
   individualmente en el servidor con reintentos y control de revisión.
   localStorage NO almacena respuestas.
   ============================================================ */
(function () {
  'use strict';

  var app = document.getElementById('app');
  if (!app) { return; }
  var SLUG = app.getAttribute('data-slug');

  var state = {
    stage: 'cover',        // cover | key | resume | register | code | question | review | external | final | locked | conflict
    familyName: '',
    openForParticipation: true,
    acceptingNew: true,    // false cuando el cupo autorizado ya se llenó
    participant: null,     // { name, status, current_index, revision, answers, external_answers }
    answers: new Array(20).fill(null),
    externalAnswers: {},
    revision: 0,
    currentIndex: 0,
    personalCode: null,
    saving: '',            // '' | saving | saved | offline | error
    lastError: ''
  };

  function esc(s) { return BvmApi.escapeHtml(s); }

  // ---------- guardado automático ----------
  var saveQueue = Promise.resolve();

  function setSaveStatus(kind) {
    state.saving = kind;
    var el = document.getElementById('save-status');
    if (!el) { return; }
    el.className = 'save-status ' + kind;
    el.textContent = kind === 'saving' ? 'Guardando…'
      : kind === 'saved' ? 'Guardado.'
      : kind === 'offline' ? 'Sin conexión; reintentando…'
      : '';
  }

  function queueSave(path, payload) {
    setSaveStatus('saving');
    saveQueue = saveQueue.then(function () {
      payload.revision = state.revision;
      return BvmApi.postWithRetry(path, payload, 4, function () { setSaveStatus('offline'); })
        .then(function (d) {
          if (d.ok) {
            state.revision = d.revision;
            setSaveStatus('saved');
            return;
          }
          if (d.conflict) { state.stage = 'conflict'; render(); return; }
          if (d.locked) { state.stage = 'locked'; render(); return; }
          setSaveStatus('offline');
          state.lastError = d.error || 'No fue posible guardar.';
        })
        .catch(function () { setSaveStatus('offline'); });
    });
    return saveQueue;
  }

  function saveAnswer(qIndex, value) {
    state.answers[qIndex] = value;
    queueSave('/api/responses/save.php', {
      question_id: qIndex + 1,
      value: value,
      current_index: Math.max(state.currentIndex, qIndex + 1)
    });
  }

  function saveExternal(extId, value) {
    state.externalAnswers[extId] = value;
    queueSave('/api/responses/external.php', { question_id: extId, value: value });
  }

  // ---------- plantillas ----------
  function answeredCount() {
    return state.answers.filter(function (a) { return a != null; }).length;
  }

  function stageCover() {
    return '<div class="question-card">' +
      '<h1>Responder el diagnóstico</h1>' +
      '<p>Usted fue invitado(a) a participar en el Diagnóstico BVM' +
      (state.familyName ? ' de <strong>' + esc(state.familyName) + '</strong>' : '') + '.</p>' +
      '<p>Responderá 20 afirmaciones de manera individual. No existen respuestas correctas o incorrectas. ' +
      'La duración aproximada es de 15 a 20 minutos y puede pausar y continuar después, incluso desde otro dispositivo.</p>' +
      '<div class="form-actions">' +
      '<button class="btn btn-primary" data-action="to-key">Comenzar</button>' +
      '<button class="btn btn-quiet" data-action="to-resume">Continuar donde me quedé</button>' +
      '</div>' +
      '<p class="hint" style="margin-top:14px;"><a href="demostracion.php">¿Desea conocer primero la herramienta? Vea la demostración con una familia ficticia.</a></p>' +
      '</div>';
  }

  function stageKey(mode) {
    return '<div class="question-card">' +
      '<h1>' + (mode === 'resume' ? 'Continuar mi participación' : 'Clave de la familia') + '</h1>' +
      '<p>Ingrese la clave que recibió junto con esta liga (por ejemplo: ROBLES-8K4P7M).</p>' +
      '<div id="stage-msg"></div>' +
      '<label for="family-key">Clave de la familia</label>' +
      '<input id="family-key" type="text" autocomplete="off" autocapitalize="characters" placeholder="FAMILIA-XXXXXX">' +
      (mode === 'resume'
        ? '<label for="personal-code">Su código personal de continuidad</label>' +
          '<input id="personal-code" type="text" autocomplete="off" autocapitalize="characters" placeholder="XXXX-XXXX">' +
          '<p class="hint">Es el código que se le mostró al registrarse. Si lo perdió, solicite uno nuevo al equipo BVM.</p>'
        : '') +
      '<div class="form-actions">' +
      '<button class="btn btn-primary" data-action="' + (mode === 'resume' ? 'do-resume' : 'check-key') + '">Continuar</button>' +
      '<button class="btn btn-quiet" data-action="to-cover">Volver</button>' +
      '</div></div>';
  }

  function stageRegister() {
    var genOptions = QUESTIONNAIRE.generations.map(function (g) {
      return '<option value="' + esc(g) + '">' + esc(g) + '</option>';
    }).join('');
    var roleOptions = QUESTIONNAIRE.participationRoles.map(function (r) {
      return '<option value="' + esc(r) + '">' + esc(r) + '</option>';
    }).join('');
    var canRegister = state.openForParticipation && state.acceptingNew;
    var blockNotice = '';
    if (!state.openForParticipation) {
      blockNotice = '<div class="alert alert-info">Esta familia no está aceptando nuevas participaciones en este momento.</div>';
    } else if (!state.acceptingNew) {
      blockNotice = '<div class="alert alert-info">Esta aplicación ya alcanzó el número de participantes autorizado. ' +
        'Si ya se registró, utilice su código personal en «Continuar donde me quedé».</div>';
    }
    return '<div class="question-card">' +
      '<h1>Registro individual</h1>' +
      blockNotice +
      '<div id="stage-msg"></div>' +
      '<p><strong>Aviso de privacidad.</strong> Sus respuestas individuales son confidenciales: se integran ' +
      'únicamente en resultados agregados de la familia. Su nombre se utiliza solo para administrar el avance ' +
      'y nunca se asocia públicamente con sus respuestas.</p>' +
      '<label for="reg-name">Nombre completo</label>' +
      '<input id="reg-name" type="text" maxlength="120" autocomplete="name">' +
      '<label for="reg-generation">Generación</label>' +
      '<select id="reg-generation"><option value="">Seleccione…</option>' + genOptions + '</select>' +
      '<label for="reg-role">Rol relacionado con la empresa, propiedad o patrimonio</label>' +
      '<select id="reg-role"><option value="">Seleccione…</option>' + roleOptions + '</select>' +
      '<label class="scale-option" style="margin-top:16px;"><input type="checkbox" id="reg-consent">' +
      '<span><span class="lbl">He leído y acepto el aviso de privacidad</span>' +
      '<span class="desc">Acepto participar y que mis respuestas se integren en resultados agregados.</span></span></label>' +
      '<div class="form-actions">' +
      '<button class="btn btn-primary" data-action="do-register"' + (canRegister ? '' : ' disabled') + '>Registrarme y comenzar</button>' +
      '<button class="btn btn-quiet" data-action="to-cover">Volver</button>' +
      '</div></div>';
  }

  function stageCode() {
    return '<div class="question-card">' +
      '<h1>Su código personal de continuidad</h1>' +
      '<p>Guarde este código ahora. Lo necesitará para continuar desde otro dispositivo o si cierra esta ventana. ' +
      '<strong>No volverá a mostrarse.</strong></p>' +
      '<p class="code-badge" id="personal-code-badge">' + esc(state.personalCode || '') + '</p><br>' +
      '<button class="btn btn-secondary" data-action="copy-code">Copiar código</button>' +
      '<div class="form-actions">' +
      '<button class="btn btn-primary" data-action="start-questions">Comenzar el cuestionario</button>' +
      '</div></div>';
  }

  function stageQuestion() {
    var i = state.currentIndex;
    var item = QUESTIONNAIRE.items[i];
    var selected = state.answers[i];
    var options = QUESTIONNAIRE.scale.map(function (opt) {
      return '<label class="scale-option' + (selected === opt.value ? ' selected' : '') + '">' +
        '<input type="radio" name="answer" value="' + opt.value + '"' + (selected === opt.value ? ' checked' : '') + '>' +
        '<span><span class="lbl">' + opt.value + ' · ' + esc(opt.label) + '</span> ' +
        '<span class="desc">' + esc(opt.description) + '</span></span></label>';
    }).join('');
    return '<div class="question-card">' +
      '<div class="question-progress"><span>Afirmación ' + (i + 1) + ' de 20</span>' +
      '<span id="save-status" class="save-status ' + state.saving + '" role="status" aria-live="polite"></span></div>' +
      '<div class="progress-track" aria-hidden="true"><div class="progress-fill" style="width:' + (100 * answeredCount() / 20) + '%"></div></div>' +
      (i === 0 ? '<p class="hint" style="margin-top:12px;">' + esc(QUESTIONNAIRE.scaleInstructions) + '</p>' : '') +
      '<p class="question-text" style="margin-top:14px;">' + esc(item.text) + '</p>' +
      '<div class="scale-options" role="radiogroup" aria-label="Su respuesta">' + options + '</div>' +
      '<div class="form-actions">' +
      '<button class="btn btn-quiet" data-action="prev-question"' + (i === 0 ? ' disabled' : '') + '>Anterior</button>' +
      (i < 19
        ? '<button class="btn btn-primary" data-action="next-question"' + (selected == null ? ' disabled' : '') + '>Siguiente</button>'
        : '<button class="btn btn-primary" data-action="to-review"' + (selected == null ? ' disabled' : '') + '>Revisar mis respuestas</button>') +
      '</div>' +
      '<p class="hint">Puede cerrar esta ventana en cualquier momento: sus respuestas quedan guardadas.</p>' +
      '</div>';
  }

  function stageReview() {
    var rows = QUESTIONNAIRE.items.map(function (item, i) {
      var v = state.answers[i];
      var label = v == null ? '—' : v + ' · ' + QUESTIONNAIRE.scale[v - 1].label;
      return '<tr><td>' + (i + 1) + '</td><td>' + esc(item.text) + '</td>' +
        '<td style="white-space:nowrap;">' + esc(label) + '</td>' +
        '<td><button class="btn btn-quiet" data-action="edit-question" data-index="' + i + '">Cambiar</button></td></tr>';
    }).join('');
    var complete = answeredCount() === 20;
    return '<div class="question-card">' +
      '<h1>Revise sus respuestas</h1>' +
      '<div class="question-progress"><span>' + answeredCount() + ' de 20 respondidas</span>' +
      '<span id="save-status" class="save-status" role="status" aria-live="polite"></span></div>' +
      '<div class="table-wrap"><table class="bvm-table"><thead><tr><th>#</th><th>Afirmación</th><th>Respuesta</th><th></th></tr></thead>' +
      '<tbody>' + rows + '</tbody></table></div>' +
      '<div class="form-actions">' +
      '<button class="btn btn-primary" data-action="to-external"' + (complete ? '' : ' disabled') + '>Continuar</button>' +
      '</div></div>';
  }

  function stageExternal() {
    var blocks = QUESTIONNAIRE.externalQuestions.map(function (q) {
      var selected = state.externalAnswers[q.id];
      var opts = q.options.map(function (opt) {
        return '<label class="scale-option' + (selected === opt.value ? ' selected' : '') + '">' +
          '<input type="radio" name="' + q.id + '" value="' + opt.value + '"' + (selected === opt.value ? ' checked' : '') + '>' +
          '<span><span class="lbl">' + esc(opt.label) + '</span></span></label>';
      }).join('');
      return '<p class="question-text">' + esc(q.text) + '</p>' +
        '<div class="scale-options" role="radiogroup" data-ext="' + q.id + '">' + opts + '</div>';
    }).join('<hr style="border:none;border-top:1px solid var(--bvm-divider);margin:22px 0;">');
    var complete = state.externalAnswers.ext1 != null && state.externalAnswers.ext2 != null;
    return '<div class="question-card">' +
      '<h1>Dos preguntas finales</h1>' +
      '<p class="hint">' + esc(QUESTIONNAIRE.externalQuestionsIntro) + '</p>' +
      '<div class="question-progress"><span></span><span id="save-status" class="save-status" role="status" aria-live="polite"></span></div>' +
      blocks +
      '<div class="form-actions">' +
      '<button class="btn btn-quiet" data-action="to-review">Volver a revisar</button>' +
      '<button class="btn btn-primary" data-action="do-finalize"' + (complete ? '' : ' disabled') + '>Finalizar mi participación</button>' +
      '</div>' +
      '<p class="hint">Después de finalizar, sus respuestas quedarán bloqueadas y no podrá modificarlas.</p>' +
      '</div>';
  }

  function stageFinal() {
    return '<div class="question-card">' +
      '<h1>Participación finalizada</h1>' +
      '<div class="alert alert-success" role="status">Gracias. Sus respuestas fueron registradas y quedaron bloqueadas.</div>' +
      '<p>El equipo BVM integrará las percepciones de todos los participantes en la radiografía de su familia.</p>' +
      '<a class="btn btn-secondary" href="index.php">Salir</a>' +
      '</div>';
  }

  function stageLocked() {
    return '<div class="question-card">' +
      '<h1>Participación ya finalizada</h1>' +
      '<div class="alert alert-info">Esta participación ya fue finalizada y no puede modificarse. ' +
      'Si necesita reabrirla, solicítelo al equipo BVM.</div>' +
      '<a class="btn btn-secondary" href="index.php">Volver al inicio</a>' +
      '</div>';
  }

  function stageConflict() {
    return '<div class="question-card">' +
      '<h1>Cambios más recientes en otro dispositivo</h1>' +
      '<div class="alert alert-info">Detectamos que esta participación se editó desde otro dispositivo. ' +
      'Para no sobrescribir nada, recargue y continúe desde la versión más reciente.</div>' +
      '<button class="btn btn-primary" data-action="reload-state">Recargar mis respuestas</button>' +
      '</div>';
  }

  // ---------- render ----------
  function render() {
    var html;
    switch (state.stage) {
      case 'cover': html = stageCover(); break;
      case 'key': html = stageKey('new'); break;
      case 'resume': html = stageKey('resume'); break;
      case 'register': html = stageRegister(); break;
      case 'code': html = stageCode(); break;
      case 'question': html = stageQuestion(); break;
      case 'review': html = stageReview(); break;
      case 'external': html = stageExternal(); break;
      case 'final': html = stageFinal(); break;
      case 'locked': html = stageLocked(); break;
      case 'conflict': html = stageConflict(); break;
      default: html = stageCover();
    }
    app.innerHTML = html;
    var h1 = app.querySelector('h1');
    if (h1) { h1.setAttribute('tabindex', '-1'); h1.focus(); }
    bindEvents();
  }

  function showStageMsg(text) {
    var el = document.getElementById('stage-msg');
    if (el) { el.innerHTML = '<div class="alert alert-error" role="alert">' + esc(text) + '</div>'; }
  }

  function applyParticipant(p) {
    state.participant = p;
    state.answers = (p.answers || new Array(20).fill(null)).slice();
    while (state.answers.length < 20) { state.answers.push(null); }
    state.externalAnswers = p.external_answers || {};
    state.revision = p.revision || 0;
    state.currentIndex = Math.min(19, p.current_index || 0);
    if (p.status === 'finalizado') {
      state.stage = 'locked';
    } else {
      var firstUnanswered = state.answers.indexOf(null);
      state.currentIndex = firstUnanswered === -1 ? 19 : firstUnanswered;
      state.stage = 'question';
    }
  }

  // ---------- eventos ----------
  function bindEvents() {
    Array.prototype.forEach.call(app.querySelectorAll('[data-action]'), function (el) {
      el.addEventListener('click', function () { handleAction(el.getAttribute('data-action'), el); });
    });
    Array.prototype.forEach.call(app.querySelectorAll('input[name="answer"]'), function (radio) {
      radio.addEventListener('change', function () {
        saveAnswer(state.currentIndex, parseInt(radio.value, 10));
        render();
      });
    });
    Array.prototype.forEach.call(app.querySelectorAll('[data-ext]'), function (group) {
      var extId = group.getAttribute('data-ext');
      Array.prototype.forEach.call(group.querySelectorAll('input[type=radio]'), function (radio) {
        radio.addEventListener('change', function () {
          saveExternal(extId, parseInt(radio.value, 10));
          render();
        });
      });
    });
  }

  function handleAction(action, el) {
    switch (action) {
      case 'to-cover': state.stage = 'cover'; render(); break;
      case 'to-key': state.stage = 'key'; render(); break;
      case 'to-resume': state.stage = 'resume'; render(); break;
      case 'check-key':
      case 'do-resume': {
        var key = (document.getElementById('family-key') || {}).value || '';
        if (!key.trim()) { showStageMsg('Ingrese la clave de la familia.'); return; }
        BvmApi.post('/api/participants/access.php', { slug: SLUG, access_code: key }).then(function (d) {
          if (!d.ok) { showStageMsg(d.error || 'Clave incorrecta.'); return; }
          state.familyName = d.family.family_name;
          state.openForParticipation = d.family.open_for_participation;
          state.acceptingNew = d.family.accepting_new_registrations !== false;
          if (action === 'check-key') {
            state.stage = 'register';
            render();
            return;
          }
          var code = (document.getElementById('personal-code') || {}).value || '';
          BvmApi.post('/api/participants/resume.php', { personal_code: code }).then(function (r) {
            if (!r.ok) { showStageMsg(r.error || 'Código no reconocido.'); return; }
            applyParticipant(r.participant);
            render();
          });
        });
        break;
      }
      case 'do-register': {
        var consent = document.getElementById('reg-consent').checked;
        BvmApi.post('/api/participants/register.php', {
          name: document.getElementById('reg-name').value,
          generation: document.getElementById('reg-generation').value,
          participation_role: document.getElementById('reg-role').value,
          consent: consent
        }).then(function (d) {
          if (!d.ok) {
            if (d.family_full) { state.acceptingNew = false; render(); }
            showStageMsg(d.error || 'No fue posible registrarse.');
            return;
          }
          state.personalCode = d.personal_code;
          state.participant = d.participant;
          state.revision = 0;
          state.stage = 'code';
          render();
        });
        break;
      }
      case 'copy-code':
        navigator.clipboard.writeText(state.personalCode || '').then(function () {
          el.textContent = 'Código copiado';
        });
        break;
      case 'start-questions':
        state.personalCode = null; // no conservarlo en memoria más de lo necesario
        state.stage = 'question';
        state.currentIndex = 0;
        render();
        break;
      case 'prev-question':
        if (state.currentIndex > 0) { state.currentIndex--; render(); }
        break;
      case 'next-question':
        if (state.answers[state.currentIndex] != null && state.currentIndex < 19) {
          state.currentIndex++;
          render();
        }
        break;
      case 'edit-question':
        state.currentIndex = parseInt(el.getAttribute('data-index'), 10);
        state.stage = 'question';
        render();
        break;
      case 'to-review': state.stage = 'review'; render(); break;
      case 'to-external': state.stage = 'external'; render(); break;
      case 'do-finalize':
        if (!window.confirm('¿Desea finalizar su participación? Después de finalizar, las respuestas quedarán bloqueadas y no podrá modificarlas.')) { return; }
        saveQueue.then(function () {
          BvmApi.post('/api/responses/finalize.php', {}).then(function (d) {
            if (d.ok) { state.stage = 'final'; render(); }
            else if (d.locked) { state.stage = 'locked'; render(); }
            else { window.alert(d.error || 'No fue posible finalizar.'); }
          });
        });
        break;
      case 'reload-state':
        loadExistingSession(true);
        break;
    }
  }

  // ---------- arranque: ¿hay sesión activa de participante? ----------
  function loadExistingSession(force) {
    fetch(BvmApi.base() + '/api/participants/state.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok) {
          state.familyName = d.family.family_name;
          applyParticipant(d.participant);
        } else if (force) {
          state.stage = 'cover';
        }
        render();
      })
      .catch(function () { render(); });
  }

  loadExistingSession(false);
})();
