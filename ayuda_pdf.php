<?php
// ============================================================
//  ayuda_pdf.php — Manual de usuario y Políticas en PDF (mPDF)
//  Lavandería Superior
//  Uso: ayuda_pdf.php?doc=manual | ayuda_pdf.php?doc=politicas
// ============================================================
require_once __DIR__ . '/config/init.php';
requireAuth('ayuda');
require_once ROOT . 'config/contenido_ayuda.php';

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    exit('Falta la librería mPDF. Ejecute: composer require mpdf/mpdf');
}
require_once $autoload;

$doc = entrada($_GET, 'doc', 'manual') === 'politicas' ? 'politicas' : 'manual';
$inst  = h(PROYECTO_INSTITUCION);
$ubic  = h(PROYECTO_UBICACION);
$ver   = SISTEMA_VERSION;
$fecha = date('d/m/Y');

$css = <<<CSS
<style>
  body { font-family: dejavusans; font-size: 10pt; color: #1e293b; line-height: 1.55; }
  h1 { font-size: 20pt; color: #1e3a8a; margin: 0 0 6pt; }
  h2 { font-size: 14pt; color: #1e3a8a; border-bottom: 1.5pt solid #bfdbfe; padding-bottom: 3pt; margin: 18pt 0 8pt; }
  h3 { font-size: 11.5pt; color: #1e40af; margin: 12pt 0 5pt; }
  p  { margin: 0 0 6pt; text-align: justify; }
  ul, ol { margin: 0 0 8pt 0; padding-left: 16pt; }
  li { margin-bottom: 3pt; }
  b { color: #0f172a; }
  table.t { width: 100%; border-collapse: collapse; margin: 6pt 0 10pt; }
  table.t th { background: #1e3a8a; color: #fff; font-size: 9pt; padding: 5pt 6pt; text-align: left; }
  table.t td { border-bottom: 0.5pt solid #cbd5e1; padding: 5pt 6pt; font-size: 9pt; vertical-align: top; }
  table.t tr:nth-child(even) td { background: #f1f5f9; }
  .nota { background: #eff6ff; border-left: 3pt solid #2563eb; padding: 7pt 10pt; margin: 8pt 0; font-size: 9.5pt; }
  .alerta { background: #fffbeb; border-left: 3pt solid #d97706; padding: 7pt 10pt; margin: 8pt 0; font-size: 9.5pt; }
  .kbd { font-family: dejavusansmono; background: #e2e8f0; padding: 1pt 4pt; font-size: 9pt; }
  .portada { text-align: center; padding-top: 120pt; }
  .portada .sis { font-size: 30pt; font-weight: bold; color: #1e3a8a; }
  .portada .tit { font-size: 16pt; color: #334155; margin-top: 10pt; }
  .portada .inst { margin-top: 150pt; font-size: 11pt; color: #334155; }
  .portada .inst b { font-size: 12.5pt; color: #1e3a8a; }
  .firma td { padding-top: 40pt; text-align: center; font-size: 9pt; }
</style>
CSS;

function portada(string $titulo, string $sub): string {
    global $inst, $ubic, $ver, $fecha;
    return <<<HTML
<div class="portada">
  <div class="sis">Lavandería Superior</div>
  <div class="tit">{$titulo}</div>
  <div style="color:#64748b;margin-top:6pt;">{$sub}</div>
  <div style="margin-top:14pt;color:#64748b;font-size:9.5pt;">Versión del sistema {$ver} &bull; {$fecha}</div>
  <div class="inst">
    Proyecto universitario<br>
    <b>{$inst}</b><br>
    {$ubic}
  </div>
</div>
HTML;
}

ob_start();

if ($doc === 'manual'): ?>
<?= portada('Manual de Usuario', 'Sistema de información para la automatización de servicios de lavandería') ?>
<tocpagebreak links="on" toc-preHTML="&lt;h1&gt;Índice&lt;/h1&gt;" toc-bookmarkText="Índice" />

<h2>1. Introducción</h2>
<p>Lavandería Superior es un sistema web que automatiza la operación diaria de una lavandería: registro de clientes, recepción de órdenes con el detalle de servicios y prendas, seguimiento del proceso de lavado, entrega, control de caja multimoneda (USD/Bs.), inventario de insumos, reportes y respaldo de la información.</p>
<p>Este manual explica cada módulo paso a paso. Dentro del sistema también encontrará ayudas rápidas: al pasar el mouse sobre los botones y los íconos <b>(?)</b> aparece una breve explicación.</p>

<h3>1.1 Roles de usuario</h3>
<table class="t">
  <tr><th width="25%">Rol</th><th>Acceso</th></tr>
  <tr><td><b>Administrador</b></td><td>Todos los módulos: Dashboard, Órdenes, Tablero, Clientes, Caja, Inventario, Reportes, Usuarios, Configuración (tasa, servicios, tipos de prenda, respaldos) y Ayuda.</td></tr>
  <tr><td><b>Vendedor</b></td><td>Operación diaria: Dashboard, Órdenes, Tablero, Clientes, tickets y Ayuda. Caja, Inventario, Reportes, Usuarios y Configuración son exclusivos del administrador.</td></tr>
</table>

<h2>2. Acceso al sistema</h2>
<ol>
  <li>Abra el navegador (Chrome, Edge o Firefox) e ingrese la dirección del sistema, por ejemplo <span class="kbd">http://localhost/lavanderia</span>.</li>
  <li>Escriba su <b>usuario</b> y <b>contraseña</b> y presione <b>Ingresar</b>.</li>
  <li>Para salir, use el ícono de <b>cerrar sesión</b> ubicado junto a su nombre, en la parte inferior de la barra lateral.</li>
</ol>
<div class="nota">Si olvidó su contraseña, solicite al administrador que la restablezca desde el módulo <b>Usuarios</b>. Las contraseñas deben tener al menos 6 caracteres.</div>

<h2>3. Interfaz general</h2>
<ul>
  <li><b>Barra lateral:</b> menú de módulos agrupados (Operaciones, Clientes, Finanzas, Sistema), la tasa BCV vigente y el perfil del usuario.</li>
  <li><b>Encabezado:</b> título del módulo y botones de acción principales.</li>
  <li><b>Mensajes emergentes:</b> confirman las operaciones (verde), advierten (amarillo) o informan errores (rojo) en la esquina inferior derecha.</li>
  <li><b>Campos con error:</b> se marcan en rojo con un mensaje debajo que explica cómo corregirlos.</li>
  <li><b>Ayudas rápidas:</b> pase el mouse o enfoque con el teclado cualquier botón o ícono (?) para ver su descripción.</li>
</ul>

<h2>4. Dashboard</h2>
<p>Pantalla inicial con el resumen del día: ingresos en USD, órdenes registradas, órdenes pendientes y urgentes, kilos procesados, gráfico de ingresos de los últimos 7 días, estado de las órdenes, últimas órdenes y alertas de inventario bajo.</p>

<h2>5. Clientes</h2>
<h3>5.1 Registrar o editar un cliente</h3>
<ol>
  <li>Entre a <b>Clientes</b> y presione <b>Nuevo Cliente</b> (o el ícono de lápiz para editar uno existente).</li>
  <li>Complete los campos obligatorios (marcados con <b>*</b>) y presione <b>Guardar Cliente</b>.</li>
</ol>
<h3>5.2 Documento de identidad</h3>
<p>El documento se compone de un <b>tipo</b> (selector) y un <b>número</b>. El número no admite letras (excepto el pasaporte) ni espacios; el sistema los elimina automáticamente mientras escribe.</p>
<table class="t">
  <tr><th width="10%">Tipo</th><th width="32%">Uso</th><th>Formato aceptado</th><th width="22%">Se guarda como</th></tr>
  <tr><td><b>V</b></td><td>Venezolano</td><td>6 a 8 dígitos</td><td>V-12345678</td></tr>
  <tr><td><b>E</b></td><td>Extranjero con cédula venezolana</td><td>6 a 10 dígitos</td><td>E-84123456</td></tr>
  <tr><td><b>P</b></td><td>Extranjero con pasaporte</td><td>5 a 15 letras o números</td><td>P-AB123456</td></tr>
  <tr><td><b>J</b></td><td>Empresa (RIF jurídico)</td><td>9 dígitos (8 + verificador)</td><td>J-12345678-9</td></tr>
  <tr><td><b>G</b></td><td>Organismo público (RIF)</td><td>9 dígitos</td><td>G-20000123-4</td></tr>
</table>
<p>No se permite registrar dos clientes con el mismo tipo y número de documento.</p>
<h3>5.3 Teléfono y demás datos</h3>
<ul>
  <li><b>Teléfono (WhatsApp):</b> número venezolano de 11 dígitos que inicie en 04XX (móvil) o 02XX (fijo), por ejemplo 04141234567. Los números extranjeros inician con <b>+</b> y el código de país, con 8 a 15 dígitos (ej. +573001234567).</li>
  <li><b>Nombre:</b> de 3 a 120 caracteres; solo letras, espacios y los signos . ' - &amp;.</li>
  <li><b>Email</b> (opcional): debe tener un formato válido (usuario@dominio.com).</li>
  <li><b>Dirección</b> (opcional): máximo 255 caracteres.</li>
</ul>
<h3>5.4 Otras acciones</h3>
<ul>
  <li><b>Buscar:</b> por nombre, documento o teléfono.</li>
  <li><b>Desactivar / Activar:</b> un cliente desactivado no aparece al crear órdenes, pero conserva su historial.</li>
  <li><b>WhatsApp:</b> al hacer clic en el teléfono se abre un chat con el cliente.</li>
  <li><b>PDF:</b> exporta el listado de clientes.</li>
</ul>

<h2>6. Registrar una orden</h2>
<p>El módulo <b>Órdenes</b> guía el registro en cuatro bloques numerados.</p>
<h3>6.1 Paso 1 — Cliente</h3>
<p>Escriba al menos dos caracteres del nombre, documento o teléfono y elija el cliente en la lista. Si no existe, presione <b>Nuevo cliente</b>, complete nombre, documento y teléfono y presione <b>Guardar y usar</b>. Para cambiar el cliente elegido presione la <b>✕</b>.</p>
<h3>6.2 Paso 2 — Servicios</h3>
<p>Haga clic en los servicios solicitados. Cada servicio agrega una línea al detalle; si lo pulsa de nuevo, el cursor se ubica en su cantidad.</p>
<h3>6.3 Paso 3 — Detalle de la orden</h3>
<ul>
  <li><b>Cantidad:</b> kilos (servicios por Kg) o unidades/pares/piezas, entre 0,1 y 999.</li>
  <li><b>Precio:</b> se toma del catálogo; puede modificarse para acuerdos especiales (entre 0 y 9.999 USD).</li>
  <li>Use <b>Agregar línea manualmente</b> para elegir un servicio desde una lista, y la <b>×</b> para eliminar una línea.</li>
</ul>
<h3>6.4 Paso 4 — Prendas recibidas</h3>
<p>Registre las prendas que entrega el cliente. Esta información se imprime en el ticket y se usa para verificar la entrega.</p>
<ol>
  <li>Pulse un botón rápido (ej. <b>+ Camisa</b>): agrega una fila con cantidad 1; cada clic adicional suma una unidad.</li>
  <li>Para otros tipos, use <b>Agregar otro tipo de prenda</b> y elíjalo en la lista.</li>
  <li>Ajuste la cantidad con los botones <b>−</b> / <b>+</b> o escribiendo un número entero entre 1 y 999.</li>
  <li>En <b>Observación</b> anote color, marca o daños previos (máximo 150 caracteres).</li>
</ol>
<div class="nota">Registrar prendas no es obligatorio, pero es muy recomendable. Si no se registra ninguna, el sistema pide confirmación antes de guardar.</div>
<h3>6.5 Resumen y registro</h3>
<ol>
  <li>Elija el <b>método de pago</b>: Efectivo USD, Efectivo Bs., Pago Móvil, Punto de venta (tarjeta de débito o crédito), Transferencia o Mixto.</li>
  <li>Opcional: escriba <b>notas</b> (máximo 500 caracteres).</li>
  <li>Verifique el total en USD y su equivalente en bolívares según la tasa BCV del día.</li>
  <li>Presione <b>Registrar Orden</b>. El botón se habilita solo cuando hay un cliente y al menos un servicio.</li>
</ol>
<p>Al registrar: se guarda la orden con fecha prometida (según el servicio de mayor duración), se asienta el cobro en la caja, se descuentan los insumos por los kilos lavados y se abre el <b>ticket de recepción</b> para imprimir.</p>
<div class="alerta">Por seguridad, el sistema recalcula el total en el servidor a partir de las cantidades y precios; no es posible alterar el monto desde el navegador.</div>

<h2>7. Tablero (seguimiento)</h2>
<p>Muestra las órdenes en columnas por etapa: <b>Recibido → Lavando → Secando → Listo → Entregado</b>. Cada tarjeta indica número, cliente, servicios, cantidad de prendas, tiempo restante y monto. La página se actualiza sola cada 60 segundos.</p>
<ul>
  <li><b>Avanzar:</b> el botón de la tarjeta mueve la orden a la siguiente etapa.</li>
  <li><b>Listo:</b> el sistema prepara el aviso de WhatsApp con el mensaje escrito; el empleado lo abre y pulsa Enviar. El botón de WhatsApp de la tarjeta muestra un punto verde mientras el cliente no ha sido avisado.</li>
  <li><b>Entregado:</b> se pide confirmar que el cliente recibe la cantidad de prendas registrada y luego se imprime el comprobante de entrega.</li>
  <li><b>Cancelar:</b> solo en la etapa Recibido. Se solicita el motivo; se revierte el cobro en caja y se devuelven los insumos al inventario.</li>
  <li><b>Etiquetas:</b> URGENTE (vence en menos de 2 horas) y ATRASADO (pasó la hora prometida).</li>
  <li><b>Ticket:</b> el ícono de impresora permite reimprimir el ticket.</li>
</ul>

<h2>8. Tickets</h2>
<p>Formato para impresora térmica de 80 mm. El <b>ticket de recepción</b> incluye datos del cliente, servicios, prendas recibidas con su total, montos en USD y Bs., tasa aplicada, fecha prometida y observaciones. El <b>comprobante de entrega</b> confirma la entrega y el pago.</p>

<h2>9. Caja</h2>
<p>Libro de movimientos del día. Los cobros de órdenes se registran automáticamente como ingresos. Para registrar otro movimiento, elija tipo (Ingreso/Egreso), concepto (Servicio, Proveedor, Nómina, Suministros, Otro), descripción y monto en USD mayor que cero; el sistema muestra su equivalente en bolívares. Use el selector de fecha para consultar días anteriores.</p>

<h2>10. Inventario</h2>
<p>Control de insumos (detergente, suavizante, etc.). Para cada insumo se define unidad, stock actual, stock mínimo, consumo por kilo lavado y costo. Cuando el stock llega al mínimo aparece una alerta en el Dashboard.</p>
<ul>
  <li><b>Entrada / Salida:</b> ajusta el stock con una cantidad mayor que cero.</li>
  <li><b>Descuento automático:</b> al registrar una orden, se descuenta el consumo por kilo de cada insumo multiplicado por los kilos de los servicios medidos en Kg.</li>
</ul>

<h2>11. Reportes</h2>
<p>Seleccione un rango de fechas para ver: total de órdenes, ingresos en USD y Bs., ticket promedio, kilos procesados, ingresos por día, servicios más solicitados, métodos de pago, mejores clientes y egresos. Con <b>Exportar PDF</b> se descarga el reporte del período.</p>

<h2>12. Usuarios (administrador)</h2>
<ul>
  <li><b>Crear usuario:</b> nombre, usuario, contraseña (mínimo 6 caracteres), rol y la <b>clave maestra</b> del sistema.</li>
  <li><b>Restablecer contraseña:</b> requiere la clave maestra.</li>
  <li><b>Activar / Desactivar:</b> un usuario desactivado no puede ingresar. No es posible desactivarse a sí mismo.</li>
  <li><b>Cambiar mi contraseña:</b> cualquier usuario puede cambiarla indicando la actual.</li>
</ul>

<h2>13. Configuración (administrador)</h2>
<table class="t">
  <tr><th width="24%">Sección</th><th>Descripción</th></tr>
  <tr><td><b>Perfil</b></td><td>Nombre para mostrar, cargo y foto (JPG, PNG o WEBP, máximo 5 MB).</td></tr>
  <tr><td><b>Modo y Color</b></td><td>Modo oscuro o claro y color de acento.</td></tr>
  <tr><td><b>Tipografía / Interfaz</b></td><td>Fuentes, tamaño de texto, redondez de esquinas y barra lateral compacta. «Restablecer» vuelve la apariencia a los valores de fábrica sin tocar los datos.</td></tr>
  <tr><td><b>Tasa de Cambio</b></td><td>Registro diario de la tasa BCV (obligatoria, mayor que cero) y la paralela (opcional, solo informativa). Muestra el historial.</td></tr>
  <tr><td><b>Servicios</b></td><td>Catálogo con nombre, unidad (Kg, Unidad, Par, Pieza), precio en USD y tiempo estimado (0,5 a 720 horas). Se pueden desactivar sin perder el historial.</td></tr>
  <tr><td><b>Tipos de prenda</b></td><td>Catálogo de prendas para las órdenes. Los primeros 12 activos aparecen como botones rápidos.</td></tr>
  <tr><td><b>Respaldo de datos</b></td><td>Crear, descargar, restaurar y eliminar respaldos (ver sección 14).</td></tr>
  <tr><td><b>Sistema</b></td><td>Información técnica: versiones de PHP y base de datos, zona horaria y datos del proyecto.</td></tr>
</table>

<h2>14. Respaldo y restauración de la base de datos</h2>
<h3>14.1 Crear un respaldo</h3>
<ol>
  <li>Vaya a <b>Configuración → Respaldo de datos</b>.</li>
  <li>Presione <b>Crear respaldo ahora</b>. Se genera un archivo <span class="kbd">.sql</span> con toda la información y se descarga automáticamente.</li>
  <li>Guarde una copia fuera del equipo (pendrive, correo o nube).</li>
</ol>
<h3>14.2 Restaurar</h3>
<ol>
  <li>Elija <b>Restaurar</b> en un respaldo de la lista, o seleccione/arrastre un archivo <span class="kbd">.sql</span> (máximo 50 MB) y presione <b>Restaurar archivo</b>.</li>
  <li>Confirme el aviso y escriba su <b>contraseña de administrador</b>.</li>
  <li>El sistema crea primero un <b>respaldo de seguridad</b> («Seguridad (antes de restaurar)») y luego reemplaza los datos.</li>
  <li>Si ocurre un error durante el proceso, se revierten los cambios automáticamente.</li>
</ol>
<div class="alerta"><b>Importante:</b> restaurar reemplaza <b>todos</b> los datos actuales. Si su usuario no existe en el respaldo restaurado, la sesión se cerrará y deberá ingresar con un usuario válido de esos datos.</div>
<p>Los respaldos se guardan en la carpeta <span class="kbd">/respaldos</span> del servidor, protegida contra el acceso desde el navegador. Solo se aceptan archivos que contengan las tablas del sistema.</p>

<h2>15. Resumen de validaciones</h2>
<table class="t">
  <tr><th width="26%">Campo</th><th>Regla</th></tr>
  <tr><td>Nombre de cliente</td><td>Obligatorio, 3 a 120 caracteres, solo letras y . ' - &amp;</td></tr>
  <tr><td>Documento</td><td>Según tipo (ver 5.2). Sin letras salvo pasaporte. Único por cliente.</td></tr>
  <tr><td>Teléfono</td><td>11 dígitos 04XX/02XX, o internacional con + y 8 a 15 dígitos.</td></tr>
  <tr><td>Email</td><td>Opcional; formato válido.</td></tr>
  <tr><td>Cantidad de servicio</td><td>Número entre 0,1 y 999.</td></tr>
  <tr><td>Precio de servicio</td><td>Número entre 0 y 9.999 USD.</td></tr>
  <tr><td>Cantidad de prendas</td><td>Entero entre 1 y 999 por línea.</td></tr>
  <tr><td>Notas de la orden</td><td>Máximo 500 caracteres.</td></tr>
  <tr><td>Tasa BCV</td><td>Número mayor que cero.</td></tr>
  <tr><td>Monto de caja</td><td>Mayor que cero; descripción obligatoria.</td></tr>
  <tr><td>Contraseña</td><td>Mínimo 6 caracteres.</td></tr>
</table>
<p>Todas las reglas se verifican en el navegador (mientras escribe) y nuevamente en el servidor antes de guardar.</p>

<h2>16. Solución de problemas</h2>
<table class="t">
  <tr><th width="35%">Problema</th><th>Solución</th></tr>
  <tr><td>No puedo escribir letras en la cédula</td><td>Es intencional. Elija la letra en el selector de tipo; para pasaportes use el tipo P.</td></tr>
  <tr><td>«Ya existe un cliente con ese documento»</td><td>Busque el cliente existente; si está inactivo, reactívelo.</td></tr>
  <tr><td>El botón «Registrar Orden» está deshabilitado</td><td>Falta seleccionar el cliente o agregar al menos un servicio.</td></tr>
  <tr><td>El monto en Bs. no coincide</td><td>Verifique que la tasa BCV del día esté registrada en Configuración.</td></tr>
  <tr><td>No se abre el ticket</td><td>Permita las ventanas emergentes del sitio en el navegador.</td></tr>
  <tr><td>«Acceso restringido»</td><td>Su rol no tiene permiso para ese módulo; consulte al administrador.</td></tr>
  <tr><td>Error de conexión a la base de datos</td><td>Verifique que Apache y MySQL estén iniciados en el panel de XAMPP.</td></tr>
  <tr><td>La restauración rechaza el archivo</td><td>Use un archivo .sql generado por el sistema, codificado en UTF-8 y menor de 50 MB.</td></tr>
</table>

<h2>17. Glosario</h2>
<table class="t">
  <tr><td width="26%"><b>Orden</b></td><td>Solicitud de servicio de un cliente, con sus servicios, prendas y pago.</td></tr>
  <tr><td><b>Fecha prometida</b></td><td>Fecha y hora estimadas en que la orden estará lista.</td></tr>
  <tr><td><b>Tasa BCV</b></td><td>Tipo de cambio oficial publicado por el Banco Central de Venezuela.</td></tr>
  <tr><td><b>Insumo</b></td><td>Producto consumible del proceso de lavado (detergente, suavizante, etc.).</td></tr>
  <tr><td><b>Respaldo</b></td><td>Copia completa de la base de datos en un archivo .sql.</td></tr>
  <tr><td><b>RIF</b></td><td>Registro de Información Fiscal de personas jurídicas u organismos.</td></tr>
  <tr><td><b>Kanban / Tablero</b></td><td>Vista en columnas que muestra el avance de cada orden.</td></tr>
</table>

<?php else: ?>
<?= portada('Políticas del Servicio y del Sistema', 'Condiciones de servicio, tratamiento de datos y uso de la información') ?>
<pagebreak />
<h1>Políticas del Servicio y del Sistema</h1>
<p>Las siguientes políticas regulan la prestación del servicio de lavandería y el uso del sistema de información. Son de aplicación para clientes, personal y administradores. Vigentes desde el <?= $fecha ?>.</p>
<?php foreach (politicas() as $i => $pol): ?>
<h2><?= ($i + 1) . '. ' . h($pol['titulo']) ?></h2>
<p><i><?= h($pol['resumen']) ?></i></p>
<ol>
  <?php foreach ($pol['detalle'] as $d): ?><li><?= $d ?></li><?php endforeach; ?>
</ol>
<?php endforeach; ?>

<h2><?= count(politicas()) + 1 ?>. Aceptación</h2>
<p>Al registrar una orden y recibir el ticket, el cliente declara conocer y aceptar estas políticas, que se encuentran a su disposición en el local. El negocio podrá actualizarlas; la versión vigente es la publicada en el sistema.</p>

<table class="firma" width="100%">
  <tr>
    <td width="50%">_____________________________<br>Administración</td>
    <td width="50%">_____________________________<br>Fecha</td>
  </tr>
</table>
<?php endif;

$html = ob_get_clean();

$mpdf = new \Mpdf\Mpdf([
    'mode'              => 'utf-8',
    'format'            => 'A4',
    'margin_top'        => 18,
    'margin_right'      => 16,
    'margin_bottom'     => 20,
    'margin_left'       => 16,
    'margin_footer'     => 8,
    'default_font'      => 'dejavusans',
    'default_font_size' => 10,
    'tempDir'           => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mpdf',
]);
$titulo = $doc === 'manual' ? 'Manual de Usuario' : 'Políticas del Servicio';
$mpdf->SetTitle("Lavandería Superior — {$titulo}");
$mpdf->SetAuthor('Lavandería Superior — Proyecto universitario ' . PROYECTO_SIGLAS);
$mpdf->SetCreator('Sistema Lavandería Superior v' . SISTEMA_VERSION);
$mpdf->h2toc = ['H2' => 0, 'H3' => 1];
$mpdf->h2bookmarks = ['H2' => 0, 'H3' => 1];
$mpdf->SetFooter(
    '<table width="100%"><tr>' .
    '<td style="font-size:7.5pt;color:#94a3b8;">Lavandería Superior &bull; ' . $titulo . ' &bull; ' . PROYECTO_SIGLAS . '</td>' .
    '<td style="font-size:7.5pt;color:#94a3b8;text-align:right;">Página {PAGENO} de {nbpg}</td>' .
    '</tr></table>'
);
$mpdf->WriteHTML($css . $html);
$archivo = $doc === 'manual' ? 'Manual_Usuario_Lavanderia_Superior.pdf' : 'Politicas_Lavanderia_Superior.pdf';
$mpdf->Output($archivo, \Mpdf\Output\Destination::DOWNLOAD);
exit;
