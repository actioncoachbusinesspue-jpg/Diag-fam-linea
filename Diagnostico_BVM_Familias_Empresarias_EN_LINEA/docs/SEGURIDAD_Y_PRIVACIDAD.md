# Seguridad y privacidad

## Autenticación BVM

- Contraseñas con `password_hash` (bcrypt por defecto de PHP) y
  `password_verify`; nunca en claro, nunca en JavaScript/HTML/localStorage.
- Sesiones PHP: cookie `HttpOnly`, `Secure` con HTTPS, `SameSite=Lax`
  (justificación: los participantes llegan por ligas compartidas — clic
  externo — y `Strict` rompería esa primera navegación; toda mutación exige
  además token CSRF).
- Regeneración del ID de sesión al iniciar sesión; expiración por
  inactividad (45 min); cierre de sesión que destruye la sesión y la cookie.
- Límite de intentos con bloqueo temporal (por usuario+IP, almacenados solo
  como HMAC). Mensajes genéricos: no se revela si un usuario existe.
- Instalador con token privado, solo activo sin usuarios, autobloqueado; sin
  credenciales predeterminadas.

## Claves y códigos

| Secreto | Almacenamiento | Recuperación |
|---|---|---|
| Contraseña BVM | hash bcrypt | cambio manual |
| Clave de familia | hash bcrypt | regenerar (invalida la anterior) |
| Código personal | hash bcrypt | regenerar desde Administración |

Todos se generan con `random_int`/`random_bytes` (criptográficos), se
muestran una sola vez y jamás viajan del servidor al navegador público.
El slug de la liga es aleatorio y no predecible; la liga identifica a la
familia pero **no** autoriza: la clave autoriza a responder y ninguna de las
dos permite entrar a Administración BVM.

## Protección de datos del participante

- Las respuestas individuales se guardan solo en MySQL; los reportes y la
  interfaz administrativa muestran únicamente resultados agregados y avance.
- localStorage no almacena respuestas (la demo y el reporte fuerzan el modo
  memoria del motor de referencia).
- La auditoría (`audit_events`) registra eventos, nunca respuestas ni
  contraseñas. Las IPs se guardan únicamente como HMAC con la APP_KEY.
- No se solicita correo electrónico ni datos innecesarios.

## Contra qué se protege

- **SQL injection**: 100 % consultas preparadas PDO; validación estricta de
  tipos y catálogos; sin SQL dinámico con entrada del usuario.
- **XSS**: escape en servidor (`htmlspecialchars`) y en cliente
  (`escapeHtml`) de todo dato de usuario; CSP sin orígenes externos;
  sin `eval`; sin `innerHTML` con datos sin escapar.
- **CSRF**: token de sesión obligatorio (encabezado o campo) en toda mutación.
- **IDOR / manipulación de IDs**: cada endpoint revalida propiedad y
  existencia; los participantes solo alcanzan su propia participación vía
  sesión de servidor; IDs inexistentes → 404 sin filtrar información.
- **Fuerza bruta**: rate limit en login BVM, clave de familia y código
  personal.
- **Edición tras cierre**: el servidor rechaza (HTTP 423) cualquier
  escritura de una participación finalizada; reabrir exige acción
  administrativa auditada.
- **Exposición de archivos**: `private/`, `database/` y `config.php`
  denegados por `.htaccess`; listado de directorios desactivado; API y
  administración con `Cache-Control: no-store`.
- **Errores**: en producción `display_errors=0`; el usuario nunca ve trazas.

## Encabezados enviados

`Content-Security-Policy` (self + inline propio, sin terceros),
`X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`,
`Permissions-Policy` (cámara/micrófono/geolocalización bloqueados),
`X-Frame-Options: DENY` / `frame-ancestors 'none'`.

## Aislamiento de la demostración

`demostracion.php` sirve el motor con almacenamiento en memoria y sin ninguna
llamada a la base de datos: abrir, usar o cerrar la demo no puede leer ni
escribir filas reales (verificado por prueba E38).

## Riesgos residuales conocidos

- El reporte administrativo carga las respuestas de la familia en la memoria
  del navegador del administrador (necesarias para dispersión/alineación).
  Mitigación: solo tras login BVM, sin persistencia, `no-store`.
- En hosting compartido, la seguridad del panel y de la base depende también
  de las credenciales de Hostinger: usar 2FA en hPanel.
- El MVP tiene un solo rol efectivo (administrador); la tabla ya contempla
  `consultor` para asignaciones futuras por familia.
