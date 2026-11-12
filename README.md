# 🧺 Lavandería Superior — Sistema de Gestión

**Sistema de información para la automatización de servicios de lavandería**
Proyecto universitario · Universidad Politécnica Territorial de los Llanos "Juana Ramírez" (UPTLL) · Valle de la Pascua, estado Guárico, Venezuela

Versión **2.0**

---

## 📋 Descripción

Sistema web para la operación diaria de una lavandería: clientes, órdenes con detalle de servicios **y de prendas**, seguimiento en tablero Kanban, tickets térmicos, caja multimoneda (USD/Bs. con tasa BCV), inventario de insumos con descuento automático, reportes en PDF, **respaldo/restauración de la base de datos** y **centro de ayuda** con manual y políticas descargables.

Construido con **PHP 8 + MySQL/MariaDB + HTML/CSS/JavaScript** (sin frameworks) y **mPDF** para los documentos PDF.

---

## 🏗️ Estructura

```
lavanderia/
├── index.php              Dashboard (métricas del día)
├── ordenes.php            Registro de órdenes (cliente, servicios, prendas, pago)
├── kanban.php             Tablero de seguimiento, entrega y cancelación
├── ticket.php             Ticket de recepción / entrega (80 mm)
├── clientes.php           Gestión de clientes        · clientes_pdf.php
├── caja.php               Libro de caja
├── inventario.php         Insumos y stock            · inventario_pdf.php
├── reportes.php           Reportes por período       · reporte_pdf.php
├── usuarios.php           Usuarios y roles
├── configuracion.php      Perfil, apariencia, tasa, servicios, tipos de prenda,
│                          respaldo de datos e información del sistema
├── ayuda.php              Centro de ayuda (manual rápido, FAQ, políticas, acerca de)
├── ayuda_pdf.php          Manual de usuario y políticas detalladas en PDF
├── login.php · logout.php · setup.php (creación del primer administrador)
│
├── config/
│   ├── init.php           Arranque: sesión, zona horaria, helpers, constantes del proyecto
│   ├── database.php       Conexión PDO y funciones de negocio (órdenes, reportes…)
│   ├── auth.php           Autenticación, roles y permisos
│   ├── tasa.php           Tasa BCV y notificación WhatsApp
│   ├── validacion.php     Validación/normalización de documentos, teléfonos, nombres…
│   ├── migraciones.php    Actualización automática del esquema (versionada)
│   ├── respaldo.php       Generación, validación y restauración de respaldos
│   └── contenido_ayuda.php Textos del manual, preguntas frecuentes y políticas
│
├── partials/              sidebar.php, acceso_denegado.php
├── assets/
│   ├── css/main.css       Estilos globales (tema oscuro/claro, tooltips, validación)
│   └── js/
│       ├── theme-engine.js  Personalización de apariencia
│       └── validaciones.js  Máscaras de entrada, validación y tooltips
├── respaldos/             Respaldos generados desde el sistema (bloqueado al acceso web)
│
├── database/              (bloqueado al acceso web con .htaccess)
│   ├── lavanderia_superior.sql  Copia completa: estructura + datos de demostración
│   └── schema.sql               Solo estructura + catálogos iniciales (instalación limpia)
│
├── docs/
│   ├── Manual_Usuario_Lavanderia_Superior.pdf
│   ├── Politicas_Lavanderia_Superior.pdf
│   └── diagrama_er_lavanderia.puml   Diagrama entidad-relación (PlantUML)
│
├── vendor/                Dependencias de Composer (mPDF)
├── composer.json · composer.lock
├── README.md · CHANGELOG.md
```

---

## 🗃️ Base de datos

| Tabla | Descripción |
|-------|-------------|
| `clientes` | Clientes; `cedula_rif` con formato `V-12345678`, `E-…`, `P-…`, `J-12345678-9`, `G-…` |
| `servicios` | Catálogo de servicios (precio USD, unidad, tiempo estimado) |
| `ordenes` | Órdenes con estado, fechas, totales USD/Bs. y método de pago |
| `detalle_orden` | Servicios de cada orden (`subtotal` es columna generada) |
| `tipos_prenda` | **Nuevo.** Catálogo de tipos de prenda |
| `prendas_orden` | **Nuevo.** Prendas recibidas por orden (tipo, cantidad, observación) |
| `tasas_cambio` | Historial de tasas BCV |
| `movimientos_caja` | Ingresos y egresos |
| `inventario_insumos` / `consumo_insumos` | Stock y trazabilidad del consumo por orden |
| `usuarios` | Usuarios con rol `admin` o `vendedor` |
| `sistema_config` | **Nuevo.** Parámetros internos (versión del esquema) |

```
clientes (1)──(N) ordenes (1)──(N) detalle_orden (N)──(1) servicios
                     ├──(N) prendas_orden (N)──(1) tipos_prenda
                     ├──(N) movimientos_caja
                     └──(N) consumo_insumos (N)──(1) inventario_insumos
```

### Migraciones automáticas
No hace falta ejecutar SQL al actualizar. `config/migraciones.php` crea las tablas nuevas y el catálogo inicial de prendas la primera vez que alguien usa el sistema, y registra la versión en `sistema_config`. Todas las sentencias son idempotentes.

---

## 🚀 Instalación (XAMPP)

1. Copiar la carpeta del proyecto en `C:\xampp\htdocs\lavanderia\` (el nombre de la carpeta define la URL).
2. Iniciar **Apache** y **MySQL** desde el panel de XAMPP.
3. Importar la base de datos en phpMyAdmin (pestaña **Importar**), eligiendo **una** de estas opciones:
   - `database/lavanderia_superior.sql` → sistema **con datos de demostración** (clientes, órdenes, usuarios). Crea la base `lavanderia_superior` automáticamente.
   - `database/schema.sql` → instalación **limpia**, sin datos.
4. Ajustar credenciales en `config/database.php` si MySQL no usa `root` sin contraseña.
5. Si falta la carpeta `vendor/` (por ejemplo, al clonar desde GitHub), ejecutar `composer install`.
6. Instalación limpia: abrir `http://localhost/lavanderia/setup.php` para crear el primer administrador.
7. Ingresar por `http://localhost/lavanderia/login.php`.

Por línea de comandos:
```bash
C:\xampp\mysql\bin\mysql.exe -u root < database\lavanderia_superior.sql
```

**Requisitos:** PHP 8.0+ (`pdo_mysql`, `mbstring`, `curl`, `fileinfo`), MySQL 8 o MariaDB 10.4+.

> ⚠️ En producción: cambiar la contraseña de la base de datos y la constante `CLAVE_MAESTRA` de `config/auth.php`.

### Pasar el sistema a otro equipo
1. Instalar XAMPP (PHP 8.0 o superior) e iniciar **Apache** y **MySQL**.
2. Descomprimir el archivo `.zip` dentro de `C:\xampp\htdocs\` para que quede `C:\xampp\htdocs\lavanderia\`.
3. Importar `database/lavanderia_superior.sql` (con todos los datos) desde phpMyAdmin → **Importar**.
   Si el equipo tiene un MySQL/MariaDB antiguo y da error con la columna `subtotal`, usar `database/lavanderia_superior_compatible.sql`.
4. Entrar a `http://localhost/lavanderia/login.php` con los mismos usuarios y contraseñas del equipo original.

No hace falta ejecutar `composer install`: la carpeta `vendor/` (generación de PDF) va incluida en el comprimido.

### Seguridad
- Token **CSRF** en todas las peticiones POST, límite de **5 intentos** de inicio de sesión (bloqueo de 5 minutos), cookie de sesión `HttpOnly`.
- Validación en el navegador **y** en el servidor con las mismas reglas; los errores de PHP se registran en el log y no se muestran.

---

## 💡 Funcionalidades

### Clientes y validación de datos
- **Nombres y apellidos en campos separados.** Con RIF J/G se pide la **razón social** y no hay apellidos.
- Documento de identidad con **tipo seleccionable**: V (venezolano), E (extranjero residente), P (pasaporte), J y G (RIF). Ya no se asume siempre "V".
- El número **no admite letras** (salvo pasaporte): se filtran mientras se escribe y se validan de nuevo en el servidor.
- Teléfonos venezolanos (04XX/02XX, 11 dígitos) o internacionales con `+`. Enlace de WhatsApp con código de país correcto.
- Detección de documentos duplicados, incluso contra registros antiguos guardados sin prefijo.
- Errores marcados en el campo exacto con mensaje explicativo.

| Tipo | Regla | Se guarda como |
|------|-------|----------------|
| V | 6–8 dígitos | `V-12345678` |
| E | 6–10 dígitos | `E-84123456` |
| P | 5–15 letras/números | `P-AB123456` |
| J / G | 9 dígitos | `J-12345678-9` |

### Órdenes
- Flujo guiado en 4 pasos: cliente → servicios → detalle → **prendas recibidas**.
- Prendas por tipo y cantidad (botones rápidos, +/−, observación de color/marca/daños); total visible en el resumen, en el ticket y en el tablero.
- **El total se recalcula en el servidor**; se validan cliente, servicios, cantidades, precios, prendas y método de pago.
- Los insumos se descuentan solo por los kilos de servicios medidos en Kg.

### Tablero Kanban
- Recibido → Lavando → Secando → Listo → Entregado, con alertas URGENTE/ATRASADO. Solo se avanza al estado siguiente (sin saltos ni retrocesos).
- **Aviso por WhatsApp (gratis):** al quedar «Listo» se abre el mensaje redactado en WhatsApp Web/Desktop; el empleado pulsa «Enviar». Un punto verde marca las órdenes sin avisar.
- Al entregar, confirma la cantidad de prendas.
- **Cancelación** de órdenes en estado Recibido: anula el cobro en caja y devuelve los insumos.

### Respaldo y restauración (Configuración → Respaldo de datos)
- Respaldo completo en `.sql` generado con PDO (no depende de `mysqldump`), descarga automática.
- Restauración desde un respaldo guardado o subiendo un archivo (máx. 50 MB), con confirmación por contraseña.
- Respaldo de seguridad automático antes de restaurar y reversión si ocurre un error.
- Carpeta `respaldos/` protegida contra acceso web; nombres de archivo validados (sin *path traversal*).

### Centro de ayuda
- Manual rápido por módulo, preguntas frecuentes con buscador, políticas resumidas y créditos institucionales.
- **Manual de usuario detallado** y **Políticas** descargables en PDF.
- Tooltips en botones e íconos `(?)` en todo el sistema.

---

## 🔐 Roles

| Rol | Módulos |
|-----|---------|
| Administrador | Todos |
| Vendedor | Dashboard, Órdenes, Tablero, Clientes, Tickets, Ayuda |

---

## 🧰 Tecnologías

PHP 8 · MySQL/MariaDB · HTML5 · CSS3 · JavaScript ES6 · Chart.js 4 · mPDF 8 · CallMeBot (WhatsApp, opcional)

---

## 📚 Documentación

| Documento | Ubicación |
|-----------|-----------|
| Manual de usuario (PDF) | `docs/Manual_Usuario_Lavanderia_Superior.pdf` · también en el sistema: **Ayuda → Manual completo** |
| Políticas del servicio y del sistema (PDF) | `docs/Politicas_Lavanderia_Superior.pdf` · **Ayuda → Políticas** |
| Diagrama entidad-relación | `docs/diagrama_er_lavanderia.puml` (abrir con [PlantUML](https://www.plantuml.com/plantuml) o la extensión de VS Code) |
| Historial de cambios | `CHANGELOG.md` |

---

*Proyecto universitario — Universidad Politécnica Territorial de los Llanos "Juana Ramírez" (UPTLL), Valle de la Pascua.*
