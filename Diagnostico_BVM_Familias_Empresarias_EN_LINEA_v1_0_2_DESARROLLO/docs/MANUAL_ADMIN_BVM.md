# Manual de Administración BVM

## Entrar y salir

- Entre por **Acceso BVM** en la página inicial (usuario y contraseña).
- La sesión expira tras 45 minutos de inactividad.
- Tras 5 intentos fallidos el acceso se bloquea temporalmente (15 minutos).
- Salga siempre con **Cerrar sesión**.

## Crear una familia

1. **Familias → Crear familia o empresa**: nombre, participantes esperados
   (opcional), la casilla **«Cerrar nuevos registros al alcanzar el número
   esperado»** y la ventana de fechas (opcional). Las fechas se interpretan
   en hora de Ciudad de México: la familia abre a las 00:00 de la fecha de
   apertura y acepta respuestas durante todo el día de la fecha de cierre.
2. Al crearla, el sistema muestra **una sola vez**:
   - la **liga** de invitación (`participar.php?f=…`), y
   - la **clave de acceso** (formato `ROBLES-8K4P7M`; las claves emitidas antes
     de la versión 1.0.2, con 4 caracteres, siguen funcionando).
   Use el botón *Copiar invitación* y compártala por el medio que la familia
   prefiera. Si la clave se pierde, **regenérela** en la ficha de la familia
   (la anterior deja de funcionar).
3. La familia nace en estado **Borrador**. Cambie a **Abierta** cuando quiera
   empezar a recibir respuestas.

## Estados de la familia

| Estado | Efecto |
|---|---|
| Borrador | No acepta registros ni respuestas |
| Abierta | Acepta registros y respuestas (dentro de las fechas, si las definió) |
| Cerrada | Ya no acepta nuevas participaciones ni cambios |
| Archivada | Oculta del listado activo |

## Número esperado de participantes y cupo (1.0.2)

El número esperado puede operar de dos maneras:

- **Límite real (casilla activada, predeterminado).** Al alcanzar el número
  esperado, ya no se aceptan registros nuevos: la persona ve «Esta
  aplicación ya alcanzó el número de participantes autorizado. Si ya se
  registró, utilice su código personal para continuar.» Quienes ya están
  registrados pueden continuar y finalizar sin restricción. Usted puede en
  cualquier momento **aumentar el número esperado** o **desactivar el
  límite**.
- **Solo referencia (casilla desactivada).** El número esperado es una meta:
  se aceptan registros adicionales y la ficha lo señala como **Excedido ·
  referencia**.

Estados de cupo que verá en el listado y la ficha:

| Estado | Significado |
|---|---|
| Disponible | Hay lugares por debajo del 80 % del esperado |
| Cerca del límite | Se alcanzó el 80 % del esperado |
| Completo | Registrados = esperados (con límite: cierra nuevos registros) |
| Excedido | Registrados > esperados (solo posible en modo referencia) |

Si el esperado está vacío, no existe límite en ningún modo.

## Seguir el avance

La ficha de cada familia muestra **X registrados de Y autorizados**, en
proceso, finalizados, porcentaje contra los esperados y última actividad
(mostrada en hora de Ciudad de México). Por confidencialidad **nunca** se
muestran respuestas individuales, solo estatus y avance.

## Ayudar a un participante

- **Perdió su código personal** → botón *Nuevo código* junto al participante.
  Se genera uno nuevo (el anterior deja de servir) y se muestra una sola vez.
- **Finalizó por error** → *Reabrir* (requiere escribir REABRIR). La acción
  queda registrada en auditoría con su usuario.
- **Nombre duplicado** → el sistema pide distinguirlos (p. ej. una inicial).

## Radiografía y reporte

*Abrir radiografía y reporte* carga el motor metodológico validado con los
datos actuales de la familia: síntesis, dimensiones, alineación,
conversaciones y el **reporte integral de 12 páginas**. Para imprimir o
generar PDF: escala 100 %, márgenes 0, fondos activados, encabezados del
navegador desactivados (Carta o A4, 12 páginas exactas).

Si la familia sigue abierta o faltan participaciones, trate la lectura como
preliminar y decida con criterio consultivo antes de compartirla.

## Respaldos

- **Exportar respaldo JSON**: descarga la familia completa en el formato
  compatible con la versión local.
- **Importar respaldo**: pestaña *Importar respaldo* → seleccionar archivo →
  revisar la vista previa → elegir *crear nueva* o *reemplazar* (requiere
  escribir REEMPLAZAR) → confirmar. Si algo falla, no se escribe nada.

## Eliminar una familia

En la ficha, *Zona de cuidado* → requiere escribir el nombre exacto.
Es definitivo: borra participaciones y respuestas. Exporte un respaldo antes.

## Estado de la instalación

`admin/salud.php` (con su sesión iniciada) muestra la verificación completa
de la instalación: base de datos, esquema 1.0.2, zona horaria, sesiones y
protección de carpetas. El equipo técnico también puede ejecutarla por
terminal con `php tools/health-check.php`.

## Notas sobre claves y códigos

- La clave de familia y el código personal **se muestran una sola vez**;
  guárdelos en el momento. Regenerarlos invalida el anterior y siempre pide
  confirmación.
- El código personal del participante es indispensable para continuar desde
  otro dispositivo; recuérdele guardarlo.
