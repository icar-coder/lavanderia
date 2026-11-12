# Registro de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

## [2.1] — 2026-10-11

### Agregado (feat)
- **Nombres y apellidos separados** en el registro de clientes (módulo Clientes y alta rápida desde Órdenes). Con RIF J o G el primer campo pasa a «Razón social» y no se piden apellidos. La columna `nombre` se conserva como nombre completo, por lo que tickets, reportes y búsquedas no cambian.
- **Aviso por WhatsApp semiautomático y gratuito:** al pasar una orden a «Listo» se abre el mensaje ya redactado (editable) y el empleado solo pulsa «Enviar». Las tarjetas muestran un punto verde mientras el cliente no ha sido avisado. Reemplaza la integración con CallMeBot, que no podía escribir a los clientes y retrasaba el tablero.
- **Tooltips** de ayuda en cada campo de los formularios de cliente.
- Migración **v4** (nombres/apellidos) y **v5** (documentos antiguos sin prefijo pasan a `V-…`).

### Seguridad
- **Protección CSRF** en todas las peticiones POST (token por sesión; `validaciones.js` lo añade a cada `fetch`).
- **Límite de intentos de inicio de sesión:** 5 fallos desde la misma IP bloquean 5 minutos.
- Cookie de sesión `HttpOnly` y `SameSite=Lax`; nuevo token al iniciar sesión.
- El parámetro `next` del login solo acepta páginas del propio sistema.
- Las peticiones AJAX con la sesión vencida reciben un JSON 401 y el navegador lleva al login (antes recibían el HTML del login y fallaban en silencio).
- Los errores de PHP ya no se muestran en pantalla (se registran en el log): rompían las respuestas JSON y exponían rutas del servidor.
- El texto de búsqueda se escapa dentro de `LIKE` (`%` ya no lista todos los clientes).

### Corregido (fix)
- **El sistema dejaba de funcionar si no había ninguna tasa registrada** (instalación nueva): `obtenerTasaHoy()` devolvía `false`.
- **El tablero permitía saltar o retroceder estados** (p. ej. Recibido → Entregado, o Entregado → Lavando) y repetir un cambio con doble clic. Ahora solo se avanza al estado siguiente y se verifica en la base de datos.
- Un campo enviado como lista (`nombre[]=…`) provocaba un error fatal y una página en blanco; ahora se rechaza con un mensaje claro en todos los formularios.
- La salida de inventario mayor que el stock se ajustaba a 0 en silencio; ahora se rechaza indicando el stock disponible.
- El perfil (nombre, cargo, foto) se guardaba en el navegador con claves compartidas: otro usuario en la misma computadora veía el nombre y la foto del anterior. Ahora es propio de cada usuario y el nombre se guarda en la base de datos.
- Nombres de servicios o insumos con apóstrofo rompían los botones (JavaScript en línea mal escapado).
- La fecha del panel salía en inglés y usaba `strftime()`, obsoleta en PHP 8.1.
- Inicial del avatar dañada en nombres que empiezan con tilde (Á, É…).
- Activar/desactivar clientes, usuarios, servicios y prendas ignoraba los errores del servidor.

### Mejorado (validación)
- Órdenes: total mayor que cero, sin servicios repetidos, límites de notas y observaciones también en el navegador.
- Servicios: precio mayor que 0, nombre sin duplicados y con caracteres válidos.
- Insumos: nombre sin duplicados; los campos opcionales vacíos se toman como 0.
- Usuarios: contraseña de 6 a 72 caracteres (límite real de bcrypt), distinta de la actual, validada también al restablecer; no se puede desactivar el propio usuario.
- Clientes: correo en minúsculas, dirección de al menos 5 caracteres si se indica, teléfono aceptado con espacios, guiones o paréntesis.
- Reportes y caja: fechas inexistentes (2026-02-31) o con formato inválido se reemplazan por el período actual.
- `setup.php` valida nombre, usuario y contraseña con las mismas reglas que el resto del sistema.
- Cualquier fallo de conexión no atendido muestra un aviso en lugar de quedar en silencio.

## [2.0] — 2026-09-30

### Agregado (feat)
- **Documento de identidad con tipo:** V, E, P (pasaporte), J y G, en clientes y en el registro rápido desde órdenes. Ya no se asume siempre "V".
- **Prendas por orden:** tipo y cantidad de cada prenda recibida, con observación (color, marca, daños). Se muestran en el resumen de la orden, en el ticket, en el tablero y se confirman al entregar.
- **Catálogo de tipos de prenda** administrable en Configuración (agregar, renombrar, activar/desactivar).
- **Respaldo y restauración de la base de datos** en Configuración → Respaldo de datos: crear/descargar/eliminar respaldos, restaurar desde un respaldo guardado o un archivo subido, con confirmación por contraseña, respaldo de seguridad previo y reversión automática ante errores.
- **Centro de Ayuda** (`ayuda.php`): manual rápido por módulo, preguntas frecuentes con buscador, políticas resumidas y créditos institucionales.
- **Manual de usuario detallado y Políticas en PDF** descargables (`ayuda_pdf.php`).
- **Tooltips** en botones y en íconos de ayuda `(?)` en todo el sistema.
- **Cancelación de órdenes** en estado Recibido: anula el cobro en caja y devuelve los insumos.
- Activar/desactivar servicios desde Configuración.
- Método de pago **Punto de venta** (tarjeta de débito/crédito); nombres legibles de los métodos en ticket y reportes.
- **Migraciones automáticas** versionadas (`config/migraciones.php`).

### Corregido (fix)
- **Seguridad:** `ticket.php` no exigía sesión; cualquiera podía ver datos personales de clientes cambiando el `id`.
- **Seguridad:** `setup.php?force` permitía crear administradores sin autenticación.
- **Seguridad:** XSS en el buscador de clientes de Órdenes (nombres insertados con `innerHTML` sin escapar).
- El total de la orden se tomaba del navegador; ahora se recalcula y valida en el servidor.
- Se descontaban insumos por unidades/pares como si fueran kilos; ahora solo por servicios en Kg.
- El tablero mostraba siempre "Múltiples servicios"; ahora lista los servicios reales y el total de prendas.
- El enlace de WhatsApp usaba el número local (`0414…`); ahora usa el formato internacional (`58414…`).
- La paginación de Clientes ignoraba el filtro de búsqueda.
- "Registrado por" en tasa y caja quedaba vacío (`$_SESSION['usuario']` inexistente).
- La carpeta de fotos de perfil no se creaba si no existía.
- Advertencia de PHP 8.1+ por conversión implícita de float en los tiempos del tablero.
- Los permisos del rol Vendedor mostraban módulos que luego le eran denegados.
- Errores internos de base de datos expuestos al usuario; ahora se registran en el log y se muestra un mensaje claro.

### Mejorado
- Validación en cliente **y** servidor de todos los formularios (clientes, órdenes, prendas, caja, inventario, servicios, tasa, usuarios, perfil): solo números en cédulas y montos, máscaras de entrada, longitudes máximas, campos marcados en rojo con el motivo del error.
- Teléfonos venezolanos (04XX/02XX) o internacionales con `+`.
- Detección de documentos duplicados, incluidos registros antiguos sin prefijo.
- Comparación de la clave maestra en tiempo constante (`hash_equals`).

### Documentación (docs)
- README reescrito (estructura, instalación, funcionalidades, roles).
- `schema.sql` actualizado a la versión 2 (tablas nuevas, roles `admin`/`vendedor`, sin contraseña de ejemplo).
- Créditos institucionales: proyecto universitario de la Universidad Politécnica Territorial de los Llanos "Juana Ramírez" (UPTLL).
