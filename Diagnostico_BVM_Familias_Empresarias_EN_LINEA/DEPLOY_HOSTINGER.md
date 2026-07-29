# Despliegue en Hostinger — Diagnóstico BVM en línea

Instrucciones exactas para el encargado del hosting. No requieren Node.js:
la aplicación publicada funciona solo con PHP y MySQL/MariaDB.

## 0. Reglas previas

- **NO tocar** `public_html/diagnostico-bvm/` (versión temporal local).
- Publicar primero en `public_html/diagnostico-bvm-online-dev/` (pruebas,
  no indexada) y solo tras aprobación formal copiar a
  `public_html/diagnostico-bvm-online/`.

## 1. Crear la carpeta

En el Administrador de archivos de hPanel crear:

```
public_html/diagnostico-bvm-online-dev/
```

## 2. Crear la base de datos

hPanel → **Bases de datos MySQL** → crear base y usuario nuevos.
Anotar en un lugar privado (nunca en documentación pública):

- host (normalmente `localhost`)
- nombre de la base
- usuario
- contraseña

## 3. Importar el esquema

hPanel → **phpMyAdmin** → seleccionar la base → pestaña **Importar** →
subir `database/schema.sql` → Continuar. Deben crearse 7 tablas.

## 4. Subir los archivos

Estructura recomendada dentro de la carpeta creada:

```
diagnostico-bvm-online-dev/
  ├── (contenido de public/  — index.php, participar.php, admin/, api/, assets/, .htaccess)
  ├── private/               — con su .htaccess "Require all denied"
  └── tools/
```

> Si su plan permite carpetas FUERA de `public_html`, coloque `private/` ahí
> y ajuste las rutas `require` de `public/*.php` (una línea por archivo).
> Con el `.htaccess` incluido, mantener `private/` dentro también es seguro
> en Apache/LiteSpeed (Hostinger lo respeta).

No subir: `tests/`, `qa-evidence/`, `reference/`, `database/` (el SQL ya se
importó), ni archivos de desarrollo.

## 5. Configurar credenciales

1. Copiar `private/config.example.php` como `private/config.php`.
2. Completar `db.host`, `db.name`, `db.user`, `db.pass`.
3. Generar la APP_KEY (hPanel → Avanzado → Terminal, o cualquier PHP local):
   ```
   php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
   ```
   y pegarla en `app.key`.
4. Definir `app.base_url`, por ejemplo:
   `https://sudominio.com/diagnostico-bvm-online-dev`
5. Definir un `app.install_token` temporal (una frase aleatoria larga).

## 6. HTTPS y versión de PHP

- hPanel → **SSL**: activar el certificado del dominio (la cookie de sesión
  se marca `Secure` automáticamente cuando hay HTTPS).
- hPanel → **Configuración PHP**: seleccionar PHP 8.2 (o la 8.x estable
  disponible).

## 7. Crear el primer administrador

1. Abrir `https://sudominio.com/diagnostico-bvm-online-dev/instalar.php`.
2. Ingresar el token de instalación y crear la cuenta (contraseña de 12+
   caracteres; evitar el usuario "admin").
3. Verificar que al recargar `instalar.php` aparece **"quedó bloqueado"**.
4. **Borrar el valor de `install_token` en `private/config.php`.**

## 8. Verificación (health-check)

Por terminal: `php tools/health-check.php` — debe terminar en
`RESULTADO: TODO CORRECTO`. (Alternativa sin terminal: copiar el archivo a la
carpeta pública, abrirlo en el navegador y **borrarlo inmediatamente**.)

## 9. Prueba funcional completa

1. Página pública carga con los tres caminos.
2. La demostración abre a Familia Horizonte sin contraseña.
3. Acceso BVM: login correcto e incorrecto (mensaje genérico).
4. Crear una familia de prueba → copiar liga y clave.
5. Desde un teléfono (red distinta si es posible): abrir la liga, ingresar
   clave, registrarse, responder 2–3 afirmaciones, cerrar el navegador.
6. Desde otra computadora: misma liga + clave + código personal → las
   respuestas están ahí; terminar y finalizar.
7. En Administración: el avance se refleja; abrir radiografía y reporte;
   imprimir (Carta y A4, márgenes 0, fondos activados, encabezados del
   navegador desactivados: 12 páginas exactas).
8. Cerrar sesión.
9. Eliminar la familia de prueba (doble confirmación).

## 10. Publicación final

Solo con aprobación formal: repetir 1–9 sobre
`public_html/diagnostico-bvm-online/` con su propia base de datos NUEVA
(no reutilizar la de desarrollo), y proteger o eliminar la carpeta `-dev`.

## 11. Respaldos y rollback

- **Respaldo de datos**: hPanel → phpMyAdmin → Exportar (formato SQL) →
  guardar con fecha. También por familia: Administración BVM → familia →
  *Exportar respaldo JSON*.
- **Respaldo de archivos**: descargar la carpeta completa desde el
  Administrador de archivos.
- **Rollback**: restaurar los archivos del ZIP anterior y, si hubo cambios de
  datos, importar el SQL respaldado desde phpMyAdmin. La carpeta
  `diagnostico-bvm-online-dev/` siempre conserva la última versión aprobada
  anterior mientras no se elimine.
- Si una importación de familia falla, la transacción revierte sola: la base
  queda como estaba.
