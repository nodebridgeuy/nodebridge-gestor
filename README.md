# S.I.G.S.M. – Gestor de documentos (intranet)

Gestor interno del Hospital de Clínicas. Es **independiente** del portal informativo público (ese va a internet; este queda solo en la intranet del hospital).
Guarda documentos (PDF) por categoría, administra las **guías generales** que se publican en el portal, recibe las **encuestas de satisfacción** del portal y administra a los funcionarios. El módulo de ambulancias se agrega después.

## Instalar en XAMPP

1. Copiar esta carpeta a `C:\xampp\htdocs\` (queda `C:\xampp\htdocs\nodebridge-gestor`).
2. Iniciar **Apache** y **MySQL** en el panel de XAMPP.
3. phpMyAdmin → **Importar**:
   - **Instalación nueva:** `database/sigsm.sql` (crea la base `sigsm`; **borra y recrea** las tablas, no usarlo si ya hay datos).
   - **Ya tenías la base cargada:** `database/migracion_guias_generales.sql`; para las encuestas y los sectores, `database/migracion_encuestas.sql`; y, para vincular las respuestas con sus encuestas, `database/migracion_respuestas.sql` para el estado de ambulancias del Inicio, `database/migracion_ambulancias.sql` y, para publicar las encuestas en el portal y recibir sus respuestas, `database/migracion_puente_portal.sql`; para el captcha y la recuperación de contraseña, `database/migracion_recuperacion.sql` (en ese orden; agregan lo nuevo sin borrar nada y se pueden repetir).
4. Opcional, datos de ejemplo: importar también `database/datos_demo.sql`, `database/datos_demo_encuestas.sql` y `database/datos_demo_ambulancias.sql`.
5. Conexión con el portal (ver «Puente con el portal informativo»): copiar `.env.example` como `.env` y completar `PORTAL_API_KEY` con la **misma clave** que tiene el portal en `portal-backend/.env` (`API_KEY`). Si el portal no está en la misma máquina, cambiar también `PORTAL_URL`.
6. Abrir **http://localhost/nodebridge-gestor/**

| Usuario | Contraseña | Rol |
|---|---|---|
| `admin` | `Admin2026!` | Administrador |
| `funcionario` | `Func2026!` (solo con `datos_demo.sql`) | Funcionario |

**Cambiar la contraseña de `admin` apenas se entre** (Funcionarios → Editar).
Requisitos de PHP (vienen activados en XAMPP): `curl`, `mbstring`, `pdo_mysql`.

## Qué puede hacer cada rol

| | Administrador | Funcionario |
|---|---|---|
| Ver y descargar documentos y guías generales, buscar | ✔ | ✔ |
| Subir documentos | ✔ | ✔ |
| Editar y eliminar documentos | ✔ | ✘ |
| Subir guías generales (quedan pendientes si las sube un funcionario) | ✔ | ✔ |
| Editar, eliminar y publicar guías generales | ✔ | ✘ |
| Crear, ver, editar y eliminar encuestas (plantillas con sus preguntas) | ✔ | ✘ |
| Registrar, editar, dar de baja y reactivar funcionarios | ✔ | ✘ |
| Entrar al módulo de ambulancias | ✔ | ✔ |

Los permisos se controlan en el servidor (`requerir_login()` / `requerir_admin()`), no solo ocultando botones.

## Inicio de sesión: captcha y contraseña olvidada

- **Captcha propio:** el login pide escribir las 5 letras de una imagen que genera el propio gestor (`captcha.php`, con la extensión GD de PHP si está activa; si no, la dibuja en SVG, así que siempre funciona). No usa servicios de internet, así que funciona en la intranet. Cada código sirve una sola vez.
- **Bloqueo por intentos:** 5 contraseñas incorrectas en 10 minutos (por usuario o por IP) bloquean el acceso durante ese tiempo.
- **«¿Olvidó su contraseña?»:** no envía correos (es una intranet). La persona escribe su usuario o email y deja una solicitud. El administrador la ve en **Funcionarios** (aviso arriba de la lista y número en el menú) y elige **Restablecer contraseña**: se crea una contraseña temporal que se muestra una sola vez y se la entrega en persona. Al entrar, la persona tiene que elegir una nueva. También puede **Descartar** la solicitud.
- Cualquier funcionario puede cambiar su contraseña desde **Cambiar mi contraseña** (menú lateral).
- Si el único administrador olvida su contraseña no hay quien la restablezca: hay que cambiar el hash en la tabla `funcionario` desde phpMyAdmin (o importar `sigsm.sql`, que reinicia todo). Conviene tener dos administradores.

## Cédula de identidad

Cada funcionario tiene una **cédula obligatoria y única** (se valida el dígito verificador, por ejemplo `1.234.567-2`). **No se usa para iniciar sesión** (se entra con el usuario): la cédula no es secreta y, si fuera el acceso, cualquiera que la sepa conocería el usuario de otra persona. Sirve para identificar al funcionario en la lista y para pedir la recuperación de contraseña (con usuario, email **o cédula**). El administrador de ejemplo viene con la cédula `1.111.111-1`: cambiarla por la real. Los funcionarios ya existentes aparecen con «Falta» hasta que se edite su ficha.

## Documentos y guías generales

- **Documentos:** internos, solo existen en el gestor.
- **Guías generales:** misma pantalla y mismos campos (título, categoría, PDF), pero **se publican en el portal informativo**, en la página de su categoría. Al guardar, se envían solas.
- Cada guía muestra su estado (solo administradores): **Publicada**, **Pendiente** (nunca llegó al portal) o **Cambios sin publicar**. Si el portal no responde, la guía se guarda igual; luego se usa **Publicar** (una) o **Publicar pendientes**.
- **Republicar todas** vuelve a enviar todo (útil si el portal perdió sus archivos).
- No se puede eliminar una guía ya publicada si no se puede quitar del portal: así no queda una copia pública sin dueño.

## Encuestas (plantillas)

Sección **Encuestas** del menú (solo administradores): lista de las encuestas creadas, con buscador por nombre y filtro por sector.

- Cada encuesta tiene nombre, sector, descripción (opcional), estado (**Activa** / **Inactiva**) y sus preguntas. Se crean **inactivas** y se activan (o desactivan) con el botón de la lista, junto a Editar y Eliminar.
- Tipos de pregunta: **escala de 1 a 5**, **opción única** (una opción por línea) y **texto libre**; cada una puede ser obligatoria o no, y se pueden reordenar.
- No puede haber dos encuestas con el mismo nombre en el mismo sector.
- Sectores iniciales: Enfermería, Servicio web, Admisiones y Mantenimiento (tabla `sector`; por ahora se agregan nuevos con un `INSERT`).
- Al eliminar una encuesta se eliminan también sus preguntas (sus respuestas se conservan).
- **Activa = visible para los pacientes en el portal.** Al activarla se publica sola; al desactivarla o eliminarla se retira. La lista muestra su estado en el portal (**En el portal**, **Pendiente de publicar**, **Cambios sin publicar**, **Pendiente de retirar**, **No visible**) y tiene los botones **Publicar** (una) y **Actualizar el portal** (las pendientes).

## Inicio (dashboard)

- **Indicadores:** ambulancias disponibles (estado "Disponible"), encuestas disponibles (las **activas** que ya están **publicadas en el portal**, o sea, las que los pacientes pueden responder), documentos en el sistema (incluye las guías generales) y documentos subidos en los últimos 7 días.
- **Estado de las ambulancias:** tabla con nombre, conductor del turno, estado actual (Disponible, En camino, Llegó a destino, Volviendo, Fuera de servicio) y desde cuándo. Lee la tabla `ambulancia` (`conductor` y `estado`); cuando exista el módulo de turnos y seguimiento solo hay que actualizar esos datos y esta vista los muestra. Por ahora se cargan con un `INSERT`/`UPDATE` (ver `datos_demo_ambulancias.sql`).

## Respuestas a encuestas

Opción **Respuestas** del menú lateral, justo debajo de **Encuestas** (solo administradores). Lista todas las respuestas enviadas por los pacientes, con:

- **Buscador por nombre de la encuesta** y **selector de sector** (Enfermería, Servicio web, Admisiones, Mantenimiento, etc.). Los filtros se conservan al paginar o eliminar.
- Cada fila tiene **Ver respuestas**: muestra lo que contestó el paciente **pregunta por pregunta** (la escala como "4 / 5"). El texto de cada pregunta se copia al recibir la respuesta, así que se entiende aunque la encuesta se edite o se elimine.
- Cada respuesta se vincula con su encuesta (`respuesta_encuesta.id_encuesta`) y, por la encuesta, con su sector. El portal informa de qué encuesta es cada respuesta y el gestor guarda el vínculo; las respuestas anteriores al puente (formulario fijo) se siguen mostrando y quedan como **"Sin asignar"** (se ven en la lista completa, pero no aparecen al filtrar por sector o nombre).
- Si se elimina una encuesta, sus respuestas se conservan y pasan a "Sin asignar".
- Se quitó el filtro por comentarios libres (y los de calificación y lugar).

El gestor trae las respuestas del portal (nunca al revés):
- **Automático:** al entrar a Inicio o a Encuestas (solo administradores), como máximo **cada 5 minutos**.
- **Botón "Actualizar ahora":** con un enfriamiento de **2 minutos**. Ambos comparten el mismo reloj, para no saturar el portal.
- Si el portal está caído, las encuestas esperan allí y llegan cuando vuelva. No se duplican ni se pierden.

## Puente con el portal informativo

Dos programas separados (el gestor en la intranet, el portal en internet) que se hablan por **HTTP con una clave compartida**. La regla: **siempre llama el gestor; el portal nunca entra a la intranet**, así que no hay que abrir puertos y funciona igual en `localhost` que en la nube.

| Qué | Sentido | Cómo |
|---|---|---|
| Encuestas activas | gestor → portal | Al guardar, activar, desactivar o eliminar, el gestor envía (o retira) la encuesta con sus preguntas y sus ids |
| Guías generales | gestor → portal | Igual que las encuestas, al guardarlas |
| Respuestas de pacientes | portal → gestor | El gestor las **pide** al entrar a Inicio/Respuestas (máx. cada 5 min) o con "Actualizar ahora" (enfriamiento de 2 min), las guarda y las **confirma** |

**Configuración (3 pasos):**
1. En `portal-backend`, copiar `.env.example` como `.env` y completar `API_KEY=` con una clave larga (PowerShell: `-join ((48..57)+(65..90)+(97..122) | Get-Random -Count 40 | ForEach-Object {[char]$_})`).
2. En el gestor, copiar `.env.example` como `.env` y poner **la misma clave** en `PORTAL_API_KEY=`. `PORTAL_URL` ya viene bien para XAMPP (`http://localhost/portal-backend`).
3. Comprobar que los archivos se llamen `.env` y no `.env.txt` (Windows oculta las extensiones). Verificación: Encuestas → "Actualizar el portal" debe decir que las encuestas se publicaron, y deben verse en `surveys.html` del portal.

**Si algo falla, el sistema no se rompe:** la encuesta o guía se guarda igual y queda **pendiente** (con su botón "Publicar"); las respuestas esperan en el portal hasta que el gestor las traiga, sin duplicarse ni perderse. No se puede eliminar algo que sigue en el portal si no se logra retirarlo.

**Al salir de localhost** solo cambian valores de configuración, no código: `PORTAL_URL` en el `.env` del gestor (la URL pública del backend), `BACKEND_URL` en `portal-informativo/js/config.js`, y las variables del portal en el servidor (`API_KEY`, base de datos con `DB_SSL=1` si hace falta, `UPLOADS_DIR` apuntando al disco persistente).

## Estructura

```
panel.php             Inicio: 4 indicadores, estado de las ambulancias y últimos documentos (con Ver y Descargar)
login.php / logout.php  Login en CSS puro (assets/css/login_sistema.css), con captcha.php
recuperar.php / cambiar_clave.php  Solicitud de contraseña olvidada / cambio de contraseña
documentos/           index (documentos y guías: ?tipo=guias), formulario, ver, eliminar, publicar
encuestas/            index (lista), formulario (crear/editar), estado (activar/desactivar), eliminar, publicar (puente)   [solo Administrador]
                      respuestas (respuestas de pacientes, con su opción en el menú), respuestas_eliminar, sincronizar
funcionarios/         index (con solicitudes de contraseña), formulario, estado, restablecer                        [solo Administrador]
ambulancias/          placeholder del módulo 2
includes/             conexion, entorno (.env), auth, layout, helpers, archivos (PDF), portal (puente con el portal), encuestas (sectores, preguntas y datos que se publican)
assets/css/           estilos.css (Bootstrap con la paleta teal) y login_sistema.css
assets/img/           login-fondo.svg (por defecto) y, opcional, logo.png
database/             sigsm.sql, migracion_guias_generales.sql, migracion_encuestas.sql, migracion_respuestas.sql,
                      migracion_ambulancias.sql, migracion_puente_portal.sql, datos_demo_ambulancias.sql,
                      datos_demo.sql, datos_demo_encuestas.sql
uploads/documentos/   PDF subidos (no se abren por URL directa)
```

## Personalizar el login

- **Imagen de fondo:** poner `assets/img/login-fondo.jpg` (o `.png` / `.webp`); si existe, se usa sola. Conviene que pese menos de ~400 KB.
- **Logo:** poner `assets/img/logo.png` (o `.svg` / `.webp`); aparece arriba del formulario.
- **Textos:** título, subtítulo y descripción están en `login.php`.

## Bootstrap sin internet

Por defecto Bootstrap se carga desde un CDN. En la intranet puede no haber internet: descargar `bootstrap.min.css` y `bootstrap.bundle.min.js` (5.3.3) y ponerlos en `assets/vendor/bootstrap/`; si existen, se usan solos.

## Configuración (archivo `.env`)

`PORTAL_URL`, `PORTAL_API_KEY` y, opcionales: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `MAX_UPLOAD_MB` (10), `UPLOADS_DIR`. El `.env` no se sube al repositorio ni se puede abrir desde el navegador.

## Pendiente

- Módulo de ambulancias.
- Las guías que ya están en el portal (las 120 iniciales) no existen en el gestor; hay que decidir cómo cargarlas.
