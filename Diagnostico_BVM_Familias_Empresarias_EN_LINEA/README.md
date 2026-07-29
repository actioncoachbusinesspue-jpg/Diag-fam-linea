# Diagnóstico BVM para Familias Empresarias — versión en línea

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
  index.php             página inicial (3 caminos)
  participar.php        flujo del participante (liga + clave + código personal)
  demostracion.php      demo pública Familia Horizonte (motor maestro, en memoria)
  acceso-bvm.php        login BVM (validación en servidor)
  instalar.php          instalador inicial (token + autobloqueo)
  admin/                familias, detalle, importar, reporte (motor maestro + datos MySQL)
  api/                  endpoints JSON (auth, families, participants, responses, reports)
  assets/               CSS/JS propios, sin CDNs
private/                configuración, seguridad, repositorios, motor de referencia
database/schema.sql     esquema MySQL/MariaDB
database/migrations/    migraciones numeradas (ver su README; sin seed de demo a propósito)
tools/                  health-check, importación CLI
tests/                  integración (PHP+SQLite), paridad (Node), E2E (API + navegador)
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
  regenerarse desde Administración BVM.

## Instalación

Ver **DEPLOY_HOSTINGER.md** (pasos exactos para hPanel). Resumen:

1. Importar `database/schema.sql` en una base MySQL nueva.
2. Copiar `private/config.example.php` → `private/config.php` y completar
   credenciales, `APP_KEY` e `install_token`.
3. Subir `public/` como raíz web y `private/`, `tools/` fuera de ella (o
   protegidos por los `.htaccess` incluidos).
4. Abrir `instalar.php`, crear el primer administrador y borrar el
   `install_token` de `config.php`.
5. Verificar con `php tools/health-check.php`.

## Pruebas

```bash
php tests/integration/run_integration.php   # backend completo con datos Horizonte
node tests/parity/parity_check.mjs          # paridad metodológica exacta (tras la integración)
bash tests/e2e/run_e2e.sh                   # E2E de API sobre servidor PHP real
PLAYWRIGHT_MODULE=/ruta/a/playwright-core \
  bash tests/e2e/run_e2e.sh                 # + E2E de navegador (demo, reporte 12 págs, móvil)
PLAYWRIGHT_MODULE=... PDF2PNG_MODULE=... \
  bash tests/evidence/run_evidence.sh       # evidencia: 6 PDFs auditados + PNGs + hojas de contacto
```

La prueba de paridad compara cada indicador de Familia Horizonte calculado por
el maestro contra el calculado con los datos que salen de la base de datos;
cualquier diferencia distinta de 0 detiene la entrega.

## Manuales

- `docs/MANUAL_ADMIN_BVM.md` — administración de familias y reportes.
- `docs/GUIA_PARTICIPANTE.md` — qué recibe y qué hace cada participante.
- `docs/SEGURIDAD_Y_PRIVACIDAD.md` — modelo de amenazas y decisiones.
- `docs/MIGRACION_DESDE_VERSION_LOCAL.md` — importación de respaldos locales.
- `docs/RESUMEN_EJECUTIVO.md` — resumen de entrega y recomendación GO/NO-GO.
