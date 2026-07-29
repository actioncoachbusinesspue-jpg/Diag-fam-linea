# Manual de Administración BVM

## Entrar y salir

- Entre por **Acceso BVM** en la página inicial (usuario y contraseña).
- La sesión expira tras 45 minutos de inactividad.
- Tras 5 intentos fallidos el acceso se bloquea temporalmente (15 minutos).
- Salga siempre con **Cerrar sesión**.

## Crear una familia

1. **Familias → Crear familia o empresa**: nombre, participantes esperados
   (opcional) y ventana de fechas (opcional).
2. Al crearla, el sistema muestra **una sola vez**:
   - la **liga** de invitación (`participar.php?f=…`), y
   - la **clave de acceso** (formato `ROBLES-8K4P`).
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

## Seguir el avance

La ficha de cada familia muestra registrados, en proceso, finalizados,
porcentaje contra los esperados y última actividad. Por confidencialidad
**nunca** se muestran respuestas individuales, solo estatus y avance.

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
