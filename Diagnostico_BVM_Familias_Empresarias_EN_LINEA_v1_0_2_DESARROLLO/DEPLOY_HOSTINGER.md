# Despliegue en Hostinger — Diagnóstico BVM en línea (versión 1.0.2.1)

Instrucciones exactas para el encargado del hosting. No requieren Node.js:
la aplicación publicada funciona solo con PHP y MySQL/MariaDB.

## 0. Reglas previas

- **NO tocar** `public_html/diagnostico-bvm/` (versión temporal local).
- Publicar primero en el ambiente de desarrollo
  (`diagnostico-bvm-online-dev`) y solo tras aprobación formal repetir el
  proceso en producción (`diagnostico-bvm-online`) con su PROPIA base de
  datos.
- Antes de actualizar una instalación 1.0.1 existente, siga
  `docs/MIGRACION_1_0_1_A_1_0_2.md` (respaldos + migraciones 0003/0004).

## 1. Arquitectura de carpetas

Desde la versión 1.0.2.1 **todos** los puntos de entrada públicos resuelven
la carpeta privada mediante un localizador único (`public/bvm_paths.php`),
que soporta las DOS estructuras siguientes **sin editar ninguna ruta**.
Ambas estructuras están probadas de extremo a extremo (página pública,
login, administración, APIs, participante, demostración, reporte y
health-check) por `tests/e2e/run_e2e.sh`, que las ejecuta las dos en cada
corrida.

### Opción A — RECOMENDADA PARA PRODUCCIÓN: `private/` fuera del área publicada

Subir el proyecto completo FUERA del área publicada y publicar únicamente
`public/`:

```
/home/USUARIO/bvm-online/                  ← NO accesible por URL
    private/                               ← config.php, bootstrap, repositorios,
                                             servicios, reference-app
    tools/                                 ← health-check por terminal
    public/                                ← LO ÚNICO que se publica
        index.php
        participar.php
        demostracion.php
        acceso-bvm.php
        instalar.php
        bvm_paths.php                      ← localizador (responde 404 por URL)
        admin/
        api/
        assets/
        .htaccess
```

Pasos:

1. Suba la carpeta del proyecto a `/home/USUARIO/bvm-online/` (fuera de
   `public_html`). No suba `tests/`, `qa-evidence/`, `reference/` ni
   `database/`.
2. En hPanel cree el subdominio o dominio de la aplicación y establezca su
   **raíz del documento** (document root) en
   `/home/USUARIO/bvm-online/public`.
3. Resultado: `private/` y `tools/` quedan estructuralmente fuera del
   alcance de cualquier URL, sin depender de `.htaccess`.

Si su plan no permite elegir la raíz del documento, use la Opción B.

### Opción B — Subcarpeta estándar de `public_html` (sin document root propio)

El **contenido** de `public/` se copia directamente a la carpeta de la
instalación, y `private/` y `tools/` se copian DENTRO de esa misma carpeta:

```
public_html/diagnostico-bvm-online/
  ├── index.php, participar.php, demostracion.php, acceso-bvm.php,
  │   instalar.php, bvm_paths.php          ← contenido de public/
  ├── admin/
  ├── api/
  ├── assets/
  ├── .htaccess                            ← el de public/
  ├── private/     ← con su .htaccess "Require all denied" INTACTO
  └── tools/       ← con su .htaccess "Require all denied" INTACTO
```

El localizador detecta automáticamente que `private/` es subcarpeta de la
instalación: no hay que editar ningún `require`. (En versiones anteriores a
1.0.2.1 esta estructura NO funcionaba porque las rutas relativas buscaban
`private/` un nivel arriba; quedó corregido y cubierto por pruebas.)

En esta opción es OBLIGATORIO, antes de aprobar el ambiente:

1. Abrir en ventana privada `https://SU-DOMINIO/diagnostico-bvm-online/private/config.php`
   → debe responder **403** (o 404), nunca mostrar contenido ni descargarlo.
2. Abrir `https://SU-DOMINIO/diagnostico-bvm-online/private/` → **403** y sin
   listado de directorio.
3. Abrir `https://SU-DOMINIO/diagnostico-bvm-online/database/schema.sql` (si
   subió `database/`; lo recomendado es NO subirla) → **403/404**.
4. Guardar capturas de las tres respuestas en la evidencia (qa-evidence).

> Un `.htaccess` correcto en Apache/LiteSpeed (Hostinger lo respeta) bloquea
> el acceso, pero **no equivale** a tener la carpeta fuera de `public_html`:
> un error de configuración del servidor o una migración de plan puede
> exponerla. Por eso la Opción A es la recomendada para producción.

No subir nunca: `tests/`, `qa-evidence/`, `reference/`, `database/` (el SQL
se importa por phpMyAdmin, no se sirve por web) ni archivos de desarrollo.

## 2. Crear la base de datos

hPanel → **Bases de datos MySQL** → crear base y usuario nuevos.
Anotar en un lugar privado (nunca en documentación pública):

- host (normalmente `localhost`)
- nombre de la base
- usuario
- contraseña

## 3. Importar el esquema

hPanel → **phpMyAdmin** → seleccionar la base → pestaña **Importar** →
subir `database/schema.sql` → Continuar. Deben crearse **8 tablas**
(admin_users, families, participants, responses, external_responses,
audit_events, login_attempts, family_assignments).

> Actualización desde 1.0.1: NO importe schema.sql; aplique en orden
> `database/migrations/0003_enforce_participant_limit.sql` y
> `database/migrations/0004_resume_token_lookup.sql`
> (ver `docs/MIGRACION_1_0_1_A_1_0_2.md`).

## 4. Configurar credenciales

1. Copiar `private/config.example.php` como `private/config.php`.
2. Completar `db.host`, `db.name`, `db.user`, `db.pass`.
3. Generar la APP_KEY (hPanel → Avanzado → Terminal, o cualquier PHP local):
   ```
   php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
   ```
   y pegarla en `app.key`.
4. Definir `app.base_url`, por ejemplo:
   `https://sudominio.com/diagnostico-bvm-online-dev`
5. Verificar `app.timezone` (predeterminado `America/Mexico_City`):
   las fechas de apertura y cierre se interpretan en esa zona.
6. Configurar el aislamiento de sesiones según el ambiente:
   - **Desarrollo**: `session_name = 'BVMDEVSESSID'`,
     `session_cookie_path = '/diagnostico-bvm-online-dev/'`
   - **Producción**: `session_name = 'BVMSESSID'`,
     `session_cookie_path = '/diagnostico-bvm-online/'`
   Nunca use la misma combinación en ambos ambientes.
7. Definir un `app.install_token` temporal (una frase aleatoria larga).

## 5. HTTPS y versión de PHP

- hPanel → **SSL**: activar el certificado del dominio (la cookie de sesión
  se marca `Secure` automáticamente cuando hay HTTPS).
- hPanel → **Configuración PHP**: seleccionar PHP 8.2 (o la 8.x estable
  disponible).

## 6. Crear el primer administrador

1. Abrir `https://sudominio.com/diagnostico-bvm-online-dev/instalar.php`.
2. Ingresar el token de instalación y crear la cuenta (contraseña de 12+
   caracteres; evitar el usuario "admin").
3. Verificar que al recargar `instalar.php` aparece **"quedó bloqueado"**.
4. **Borrar el valor de `install_token` en `private/config.php`.**

## 7. Verificación (health-check)

Por terminal (hPanel → Avanzado → Terminal):

```
php tools/health-check.php
```

Debe terminar en `RESULTADO: TODO CORRECTO` (las advertencias se revisan una
por una; cualquier FALLA detiene la publicación). Verifica PHP, PDO, driver
MySQL, conexión, las 8 tablas, las columnas e índice de 1.0.2, InnoDB,
utf8mb4, APP_KEY, zona horaria, base_url, sesión (nombre y ruta de cookie),
token de instalación, motor de referencia, protección de `private/` y
`database/`, versión de esquema, administrador y permisos de config.php.

**Sin terminal**: inicie sesión BVM y abra `admin/salud.php` (misma
verificación, protegida por sesión). **PROHIBIDO** copiar
`tools/health-check.php` al área pública: ya no funciona por web y la
práctica queda eliminada de esta guía.

## 8. Prueba funcional completa

1. Página pública carga con los tres caminos.
2. La demostración abre a Familia Horizonte sin contraseña.
3. Acceso BVM: login correcto e incorrecto (mensaje genérico).
4. Crear una familia de prueba (cupo esperado 2, límite activado) → copiar
   liga y clave (formato `PREFIJO-XXXXXX`).
5. Desde un teléfono (red distinta si es posible): abrir la liga, ingresar
   clave, registrarse, responder 2–3 afirmaciones, cerrar el navegador.
6. Desde otra computadora: misma liga + clave + código personal → las
   respuestas están ahí; terminar y finalizar.
7. Registrar un segundo participante y verificar que un TERCER registro es
   rechazado con el mensaje de cupo lleno; la reanudación del primero sigue
   funcionando.
8. En Administración: el avance muestra «X registrados de Y autorizados»;
   abrir radiografía y reporte; imprimir (Carta y A4, márgenes 0, fondos
   activados, encabezados del navegador desactivados: 12 páginas exactas).
9. Probar URL directa a `private/` (ventana privada) → 403/404.
10. Cerrar sesión y verificar que la cookie (`BVMDEVSESSID` o `BVMSESSID`)
    desaparece.
11. Eliminar la familia de prueba (doble confirmación).

## 9. Publicación final

Solo con aprobación formal: repetir los pasos sobre
`public_html/diagnostico-bvm-online/` con su propia base de datos NUEVA
(no reutilizar la de desarrollo), con `session_name = 'BVMSESSID'` y
`session_cookie_path = '/diagnostico-bvm-online/'`, y proteger o eliminar
la carpeta `-dev`.

## 10. Respaldos y rollback

- **Respaldo de datos**: hPanel → phpMyAdmin → Exportar (formato SQL) →
  guardar con fecha. También por familia: Administración BVM → familia →
  *Exportar respaldo JSON*.
- **Respaldo de archivos**: descargar la carpeta completa desde el
  Administrador de archivos.
- **Rollback**: restaurar los archivos del ZIP anterior y, si hubo cambios de
  datos, importar el SQL respaldado desde phpMyAdmin (las migraciones 0003 y
  0004 son aditivas y reversibles: ver instrucciones de reversa dentro de
  cada archivo).
- Si una importación de familia falla, la transacción revierte sola: la base
  queda como estaba.
