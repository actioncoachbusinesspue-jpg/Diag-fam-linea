# Diagnóstico BVM para Familias Empresarias — versión en línea (1.0.2.2)

Plataforma web (PHP 8 + MySQL/MariaDB) del Diagnóstico BVM: permite a BVM
administrar varias familias, invitarlas mediante una liga y una clave, recibir
respuestas remotas desde cualquier dispositivo con guardado automático
centralizado, y generar la radiografía y el reporte integral de 12 páginas con
**exactamente** la metodología del archivo maestro estable.

## Regla de protección del trabajo

- El archivo maestro local `Diagnostico_BVM_Familias_Empresarias.html` **no se
  modifica, renombra ni sustituye**. Este proyecto es independiente.
- `reference/Diagnostico_BVM_Familias_Empresarias_EN_LINEA_DESARROLLO.html`
  es la copia de referencia funcional e inmutable (ver
  `reference/NO_MODIFICAR_ARCHIVO_ESTABLE.txt`).
- `private/reference-app/referencia_app.html` es la copia que la plataforma
  sirve para la demostración y el reporte. Nunca se edita: las adaptaciones se
  inyectan en memoria al servirla (ver `private/reference_renderer.php`).

## Arquitectura

```
public/                 ← único directorio expuesto al web
  bvm_paths.php         localizador único de private/ (soporta ambas
                        estructuras de despliegue; 404 por URL directa)
  index.php             página inicial (3 caminos)
  participar.php        flujo del participante (liga + clave + código personal)
  demostracion.php      demo pública Familia Horizonte (motor maestro, en memoria)
  acceso-bvm.php        login BVM (validación en servidor)
  instalar.php          instalador inicial (token + autobloqueo)
  admin/                familias, detalle, importar, reporte (motor maestro + datos MySQL)
  api/                  endpoints JSON (auth, families, participants, responses, reports)
  assets/               CSS/JS propios, sin CDNs
private/                configuración, seguridad, repositorios, motor de referencia,
                        parche de presentación 1.0.2 (empates) y health_checks
private/services/       ParticipationPolicy: fuente ÚNICA de verdad del ciclo de
                        vida de la participación (1.0.2.2)
database/schema.sql     esquema MySQL/MariaDB (estado 1.0.2)
database/migrations/    migraciones numeradas 0001-0004 (ver su README)
tools/                  health-check (solo CLI), importación CLI
tests/                  integración (PHP, SQLite o MySQL), paridad (Node),
                        E2E (API + navegador), mysql/ (suite sobre MySQL real)
qa-evidence/            evidencia de pruebas
```

Decisiones clave:

- **Paridad por construcción.** La demostración y el reporte NO reimplementan
  la metodología: sirven el motor del maestro íntegro. En el navegador se
  deshabilita `localStorage` (el propio `StorageAdapter` del maestro cae a su
  modo memoria) y los datos reales se inyectan desde MySQL al arrancar. Las
  respuestas reales nunca persisten en el navegador.
- **La base de datos es la fuente de verdad.** El participante guarda cada
  respuesta individualmente (transacción, validación 1–5, número de revisión
  para detectar edición simultánea, bloqueo tras finalizar).
- **Sin secretos en el frontend.** Autenticación con `password_hash/verify`,
  sesiones PHP con cookie HttpOnly/SameSite, CSRF en toda mutación, límite de
  intentos con bloqueo temporal, mensajes genéricos.
- **Claves irrecuperables por diseño.** La clave de familia y el código
  personal se almacenan solo como hash; se muestran una única vez y pueden
  regenerarse desde Administración BVM. Desde 1.0.2 la reanudación usa
  además un HMAC de localización indexado (nunca el código en claro).
- **Cupo con garantía de concurrencia (1.0.2).** El número esperado de
  participantes puede actuar como límite real: la validación completa ocurre
  en una transacción con `SELECT … FOR UPDATE`, de modo que dos registros
  simultáneos no pueden exceder el cupo.
- **Tiempo con una sola regla (1.0.2).** Persistencia técnica en UTC;
  apertura/cierre y presentación administrativa en `app.timezone`
  (predeterminado America/Mexico_City), con cierre inclusivo todo el día.
- **Una sola política de participación (1.0.2.2).** `ParticipationPolicy`
  decide, para CUALQUIER punto del ciclo (registrar, reanudar, consultar
  estado, guardar, responder las externas, finalizar), si la acción procede y
  por qué no: `family_draft`, `not_started`, `ended`, `family_closed`,
  `family_archived`, `capacity_reached`. Ningún endpoint reimplementa la regla
  y la interfaz traduce los códigos a mensajes específicos.
- **Cupo como meta, no como muro (1.0.2.2).** «Participantes esperados» es una
  referencia; solo bloquea registros si el administrador marca expresamente
  «Cerrar nuevos registros al alcanzar el número esperado». El cupo lleno nunca
  impide continuar a quien ya está registrado.
- **Sesión de participante por familia (1.0.2.2).** Vinculada a participante +
  familia + liga: abrir la liga de otra familia limpia la sesión anterior y
  «Salir de esta participación» hace un cierre real que no toca la sesión
  administrativa BVM.
- **Eliminar es eliminar (1.0.2.2).** «Archivar» conserva; «Eliminar
  definitivamente» borra en transacción familia, participaciones y respuestas,
  sin huérfanos y sin cambios de esquema.

## Instalación

Ver **DEPLOY_HOSTINGER.md** (pasos exactos para hPanel). Resumen:

1. Importar `database/schema.sql` en una base MySQL nueva (instalaciones
   1.0.1 existentes: aplicar las migraciones 0003 y 0004 según
   `docs/MIGRACION_1_0_1_A_1_0_2.md`).
2. Copiar `private/config.example.php` → `private/config.php` y completar
   credenciales, `APP_KEY`, `install_token`, `timezone`, `session_name` y
   `session_cookie_path` del ambiente.
3. Subir `public/` como raíz web y `private/`, `tools/` fuera de ella
   (arquitectura recomendada) o protegidos por los `.htaccess` incluidos
   con prueba 403 obligatoria.
4. Abrir `instalar.php`, crear el primer administrador y borrar el
   `install_token` de `config.php`.
5. Verificar con `php tools/health-check.php` (o `admin/salud.php` con
   sesión iniciada; el health-check ya no puede publicarse).

## Pruebas

```bash
php tests/integration/run_integration.php   # backend completo con datos Horizonte
node tests/parity/parity_check.mjs          # paridad metodológica exacta (tras la integración)
bash tests/e2e/run_e2e.sh                   # E2E de API sobre servidor PHP real
PLAYWRIGHT_MODULE=/ruta/a/playwright-core \
  bash tests/e2e/run_e2e.sh                 # + E2E de navegador (demo, reporte 12 págs, móvil)
PLAYWRIGHT_MODULE=... PDF2PNG_MODULE=... \
  bash tests/evidence/run_evidence.sh       # evidencia: 8 PDFs auditados (incl. empates) + PNGs + hojas de contacto
#   run_e2e.sh incluye además la batería correctiva 1.0.2.2 (tests/e2e/e2e_v1022.mjs)
#   y, con PLAYWRIGHT_MODULE, las pruebas de navegador de portada y privacidad
#   (tests/e2e/browser_v1022.mjs), en las estructuras de despliegue A y B.
bash tests/mysql/run_mysql.sh               # suite sobre MySQL/MariaDB REAL (servidor propio via
                                            # BVM_MYSQL_HOST/PORT/USER/PASS, o Docker local)
```

La prueba de paridad compara cada indicador de Familia Horizonte calculado por
el maestro contra el calculado con los datos que salen de la base de datos;
cualquier diferencia distinta de 0 detiene la entrega.

## Manuales

- `docs/MANUAL_ADMIN_BVM.md` — administración de familias y reportes.
- `docs/GUIA_PARTICIPANTE.md` — qué recibe y qué hace cada participante.
- `docs/SEGURIDAD_Y_PRIVACIDAD.md` — modelo de amenazas y decisiones.
- `docs/MIGRACION_DESDE_VERSION_LOCAL.md` — importación de respaldos locales.
- `docs/MIGRACION_1_0_1_A_1_0_2.md` — actualización 1.0.1 → 1.0.2 paso a paso.
- `docs/RESUMEN_EJECUTIVO.md` — resumen de entrega 1.0.2 y recomendación GO/NO-GO.
- `docs/RESUMEN_EJECUTIVO_1_0_2_2.md` — resumen de la corrección 1.0.2.2.
- `docs/QA_V1_0_2_2.md` — qué se probó en 1.0.2.2, con resultados y límites.
- `docs/DEPLOY_1_0_2_2_DESDE_1_0_2_1.md` — actualización paso a paso (sin migración).
- `docs/ROLLBACK_1_0_2_2.md` — vuelta atrás a 1.0.2.1.
