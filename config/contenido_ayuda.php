<?php
// ============================================================
//  config/contenido_ayuda.php — Textos del Centro de Ayuda
//  Lavandería Superior
//
//  Fuente única para ayuda.php (versión simplificada) y
//  ayuda_pdf.php (manual y políticas detallados en PDF).
// ============================================================

/* ── MANUAL RÁPIDO: pasos esenciales por módulo ───────────────── */
function manualRapido(): array {
    return [
        ['id' => 'manual-inicio', 'titulo' => 'Primeros pasos', 'pasos' => [
            'Ingresa con tu <b>usuario y contraseña</b>. Si olvidaste la contraseña, pide al administrador que la restablezca.',
            'Al entrar verás el <b>Dashboard</b> con los ingresos del día, órdenes pendientes y alertas de inventario.',
            'Usa la barra lateral para moverte entre módulos. Pasa el mouse sobre los botones y los íconos <span class="help-dot">?</span> para ver ayudas rápidas.',
            'Verifica cada mañana que la <b>tasa BCV</b> esté actualizada (aparece en la barra lateral).',
        ]],
        ['id' => 'manual-clientes', 'titulo' => 'Registrar un cliente', 'pasos' => [
            'Entra a <b>Clientes → Nuevo Cliente</b> (o créalo directamente desde una orden).',
            'Elige el <b>tipo de documento</b>: <b>V</b> venezolano, <b>E</b> extranjero residente, <b>P</b> pasaporte, <b>J</b> o <b>G</b> RIF de empresa u organismo. Luego escribe solo el número; el sistema no admite letras en la cédula.',
            'Escribe el teléfono de WhatsApp: 11 dígitos (ej. 04141234567). Si es un número extranjero, comienza con <b>+</b> y el código del país.',
            'Escribe los <b>nombres</b> y los <b>apellidos</b> en sus casillas (solo letras). Si el documento es un RIF <b>J</b> o <b>G</b>, la primera casilla cambia a <b>Razón social</b> y no se piden apellidos.',
            'Presiona <b>Guardar Cliente</b>. Si un dato es incorrecto, el campo se marca en rojo con la explicación.',
        ]],
        ['id' => 'manual-ordenes', 'titulo' => 'Registrar una orden', 'pasos' => [
            '<b>1. Cliente:</b> búscalo por nombre, documento o teléfono y selecciónalo. Si no existe, usa <b>Nuevo cliente</b>.',
            '<b>2. Servicios:</b> haz clic en los servicios que pide el cliente (ej. Lavado por peso).',
            '<b>3. Detalle:</b> ajusta la cantidad (kilos o unidades). El precio sale del catálogo y puede ajustarse si hay un acuerdo especial.',
            '<b>4. Prendas recibidas:</b> indica qué prendas entrega y cuántas (ej. 3 Camisas, 2 Pantalones). Usa los botones rápidos o «Agregar otro tipo de prenda». Anota color, marca o daños previos en «Observación».',
            'Selecciona el <b>método de pago</b>, agrega notas si hacen falta y presiona <b>Registrar Orden</b>.',
            'Se abre el <b>ticket de recepción</b> para imprimir y entregar al cliente. El pago queda registrado en la caja del día.',
        ]],
        ['id' => 'manual-kanban', 'titulo' => 'Seguimiento y entrega', 'pasos' => [
            'En <b>Tablero</b> cada columna es una etapa: Recibido → Lavando → Secando → Listo → Entregado.',
            'Presiona el botón de la tarjeta para avanzar la orden a la siguiente etapa.',
            'Las tarjetas marcadas como <b>URGENTE</b> vencen en menos de 2 horas; <b>ATRASADO</b> ya pasaron su hora prometida.',
            'Al marcar <b>Entregado</b>, confirma con el cliente la cantidad de prendas indicada; luego se imprime el comprobante de entrega.',
        ]],
        ['id' => 'manual-caja', 'titulo' => 'Caja, inventario y reportes', 'pasos' => [
            '<b>Caja:</b> los cobros de órdenes se registran solos. Registra aquí otros ingresos o egresos (proveedores, nómina, suministros).',
            '<b>Inventario:</b> registra entradas y salidas de insumos. El sistema descuenta automáticamente detergente y demás insumos por cada kilo lavado.',
            '<b>Reportes:</b> elige un rango de fechas para ver ingresos, servicios más vendidos y mejores clientes; puedes exportarlos a PDF.',
        ]],
        ['id' => 'manual-config', 'titulo' => 'Configuración y respaldos (administrador)', 'pasos' => [
            '<b>Tasa de Cambio:</b> registra la tasa BCV del día.',
            '<b>Servicios</b> y <b>Tipos de prenda:</b> agrega, edita o desactiva elementos del catálogo.',
            '<b>Respaldo de datos:</b> presiona «Crear respaldo ahora» al cierre de cada día y guarda el archivo descargado fuera del equipo.',
            'Para <b>restaurar</b>, elige un respaldo guardado o sube un archivo .sql y confirma con tu contraseña. Antes se crea un respaldo de seguridad automático.',
        ]],
    ];
}

/* ── PREGUNTAS FRECUENTES ─────────────────────────────────────── */
function preguntasFrecuentes(): array {
    return [
        ['¿Cómo registro a un cliente extranjero?',
         'En el campo de documento elige <b>E</b> si tiene cédula de extranjero venezolana, o <b>P</b> si solo tiene pasaporte (el pasaporte sí admite letras). Su teléfono puede ser internacional comenzando con <b>+</b> y el código del país, por ejemplo +573001234567.'],
        ['¿Por qué no puedo escribir letras en la cédula?',
         'Las cédulas venezolanas y de extranjeros solo tienen números. La letra (V, E, J, G o P) se elige en el selector de la izquierda, así se evitan errores como «V-V12345» o espacios.'],
        ['El sistema dice que el documento ya existe. ¿Qué hago?',
         'Ese cliente ya está registrado. Búscalo por su número en <b>Clientes</b> o en el buscador de la orden. Si estaba desactivado, reactívalo desde la lista de clientes.'],
        ['¿Es obligatorio registrar las prendas?',
         'No es obligatorio, pero sí muy recomendable: las prendas se imprimen en el ticket y sirven para verificar la entrega y atender reclamos. Si no registras prendas, el sistema te pedirá confirmación.'],
        ['¿Cómo agrego un tipo de prenda que no aparece?',
         'Un administrador puede agregarlo en <b>Configuración → Tipos de prenda</b>. Mientras tanto, puedes usar «Otro» y describir la prenda en la observación.'],
        ['¿Cómo se calcula el monto en bolívares?',
         'Todos los precios están en dólares y se convierten con la <b>tasa BCV del día</b> registrada en Configuración. Si hoy no se registró, se usa la última disponible.'],
        ['¿Por qué una orden aparece como URGENTE o ATRASADO?',
         'La fecha prometida se calcula con el tiempo estimado del servicio más largo de la orden. URGENTE: faltan menos de 2 horas; ATRASADO: ya pasó la hora prometida.'],
        ['¿Cómo reimprimo un ticket?',
         'En el <b>Tablero</b>, presiona el ícono de impresora de la tarjeta de la orden.'],
        ['¿El cliente recibe aviso cuando su ropa está lista?',
         'Al mover la orden a <b>Listo</b>, aparece el aviso para el cliente con el mensaje ya escrito: pulsa <b>Abrir WhatsApp</b> y luego <b>Enviar</b>. Las órdenes sin avisar muestran un punto verde en el botón de WhatsApp de la tarjeta, que también sirve para reenviar el aviso.'],
        ['¿Cada cuánto debo hacer un respaldo?',
         'Se recomienda <b>uno diario</b> al cerrar, y guardar el archivo en un pendrive o en la nube. Los respaldos se crean en <b>Configuración → Respaldo de datos</b>.'],
        ['Restauré un respaldo y me sacó del sistema. ¿Es normal?',
         'Sí, si tu usuario no existía en ese respaldo. Ingresa con un usuario que sí exista en esos datos. Recuerda que antes de restaurar se guarda un respaldo de seguridad para volver atrás.'],
        ['Olvidé mi contraseña.',
         'Pide al administrador que la restablezca desde <b>Usuarios</b>; para ello se necesita la clave maestra del sistema.'],
        ['¿Qué hago si aparece «Acceso restringido»?',
         'Tu rol no tiene permiso para ese módulo. Solo los administradores acceden a Caja, Inventario, Reportes, Usuarios y Configuración.'],
    ];
}

/* ── POLÍTICAS DEL SERVICIO Y DEL SISTEMA ─────────────────────── */
function politicas(): array {
    return [
        ['id' => 'pol-recepcion', 'titulo' => 'Recepción de prendas',
         'resumen' => 'Toda prenda se registra y revisa al recibirla; el ticket es el comprobante del cliente.',
         'detalle' => [
            'Cada orden registra el tipo y la cantidad de prendas recibidas. El cliente debe revisar el ticket antes de retirarse.',
            'El personal revisa las prendas en presencia del cliente y anota manchas, roturas, decoloración o botones faltantes en la observación.',
            'El cliente debe vaciar los bolsillos. La lavandería no se hace responsable por dinero, documentos, joyas u objetos olvidados en las prendas.',
            'Se pueden rechazar prendas en mal estado o que, según su etiqueta, no admitan el tipo de lavado solicitado.',
        ]],
        ['id' => 'pol-plazos', 'titulo' => 'Plazos de entrega',
         'resumen' => 'La fecha prometida es una estimación según el servicio; se avisa si hay retrasos.',
         'detalle' => [
            'La fecha y hora prometidas se calculan según el tiempo estimado del servicio más largo de la orden y se imprimen en el ticket.',
            'Por causas ajenas (fallas eléctricas o de agua, alto volumen de trabajo) el plazo puede extenderse; en ese caso se notificará al cliente.',
            'Los servicios de tintorería, edredones y prendas delicadas requieren más tiempo.',
        ]],
        ['id' => 'pol-retiro', 'titulo' => 'Retiro de prendas',
         'resumen' => 'Se retira con el ticket o documento del titular; las prendas no retiradas generan resguardo.',
         'detalle' => [
            'Para retirar la ropa se debe presentar el ticket de recepción o el documento de identidad del titular de la orden.',
            'Al entregar, el personal verifica junto con el cliente la cantidad de prendas indicada en el ticket.',
            'Las prendas no retiradas después de <b>30 días</b> de la fecha prometida podrán generar un cargo por resguardo.',
            'Después de <b>90 días</b> sin retirar, y habiendo intentado contactar al cliente, las prendas podrán considerarse abandonadas y destinarse a donación.',
        ]],
        ['id' => 'pol-danos', 'titulo' => 'Daños, pérdidas y reclamos',
         'resumen' => 'Los reclamos se hacen dentro de 48 horas con el ticket; la responsabilidad tiene un límite.',
         'detalle' => [
            'Los reclamos deben hacerse en un plazo máximo de <b>48 horas</b> después de la entrega, presentando el ticket.',
            'La lavandería sigue las instrucciones de la etiqueta de cada prenda. No se responde por daños derivados de etiquetas incorrectas, materiales de baja calidad o desgaste normal.',
            'En caso de pérdida o daño comprobado por responsabilidad del negocio, la compensación no superará <b>diez (10) veces</b> el valor cobrado por el servicio de esa prenda.',
            'No se garantiza la eliminación total de manchas antiguas o de origen desconocido.',
        ]],
        ['id' => 'pol-pagos', 'titulo' => 'Precios y pagos',
         'resumen' => 'Precios en dólares, convertidos a bolívares con la tasa BCV del día; pago al recibir.',
         'detalle' => [
            'Los precios se expresan en dólares estadounidenses (USD) y se convierten a bolívares con la tasa oficial del Banco Central de Venezuela del día del pago.',
            'Métodos aceptados: efectivo en USD o Bs., pago móvil, punto de venta (tarjeta de débito o crédito), transferencia y pago mixto.',
            'El pago se realiza al registrar la orden y queda asentado en el libro de caja.',
            'Los servicios por peso se cobran según los kilos verificados en la balanza del local.',
        ]],
        ['id' => 'pol-cancelaciones', 'titulo' => 'Cancelaciones',
         'resumen' => 'Solo se cancela una orden antes de iniciar el lavado.',
         'detalle' => [
            'Una orden solo puede cancelarse mientras esté en estado <b>Recibido</b>, es decir, antes de iniciar el lavado.',
            'Si el servicio ya comenzó, no procede la cancelación ni el reembolso.',
        ]],
        ['id' => 'pol-privacidad', 'titulo' => 'Privacidad y protección de datos',
         'resumen' => 'Los datos del cliente se usan solo para gestionar sus órdenes y avisarle; no se comparten.',
         'detalle' => [
            'Se recogen únicamente los datos necesarios: nombre, documento de identidad, teléfono y, opcionalmente, correo y dirección.',
            'Los datos se usan solo para identificar al cliente, gestionar sus órdenes, emitir comprobantes y enviar avisos sobre sus prendas.',
            'No se venden ni se comparten con terceros, salvo requerimiento de una autoridad competente.',
            'Conforme al artículo 28 de la Constitución de la República Bolivariana de Venezuela (hábeas data), el cliente puede solicitar conocer, corregir o eliminar sus datos.',
        ]],
        ['id' => 'pol-sistema', 'titulo' => 'Uso del sistema y seguridad de la información',
         'resumen' => 'Cuentas personales, acceso por roles, contraseñas seguras y respaldos diarios.',
         'detalle' => [
            'Cada trabajador usa su propia cuenta; está prohibido compartir contraseñas. Las contraseñas se guardan cifradas (bcrypt).',
            'El acceso a módulos sensibles (configuración, usuarios, caja, inventario) está limitado al rol Administrador.',
            'El administrador debe crear un <b>respaldo diario</b> de la base de datos y conservar copias fuera del equipo.',
            'La restauración de un respaldo requiere la contraseña del administrador y genera antes un respaldo de seguridad automático.',
            'Al terminar la jornada, cada usuario debe cerrar sesión.',
        ]],
    ];
}
