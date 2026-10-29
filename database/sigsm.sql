-- S.I.G.S.M. - Base de datos del gestor (intranet)
-- Importar en phpMyAdmin (pestaña Importar). Se puede importar más de una vez:
-- ATENCIÓN: borra y vuelve a crear las tablas del gestor (se pierden los datos cargados).

CREATE DATABASE IF NOT EXISTS `sigsm`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sigsm`;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Tablas de la versión anterior del proyecto y las actuales
DROP TABLE IF EXISTS `respuesta_detalle`, `ambulancia`, `respuesta`, `pregunta`, `encuesta`, `sector`, `sincronizacion`,
                     `respuesta_encuesta`, `documento`, `categoria`, `funcionario`, `rol`;

SET FOREIGN_KEY_CHECKS = 1;

-- Roles: 1 = Administrador (todo), 2 = Funcionario (solo consulta documentos)
CREATE TABLE `rol` (
  `id_rol` INT NOT NULL PRIMARY KEY,
  `nombre_rol` VARCHAR(40) NOT NULL
) ENGINE=InnoDB;

INSERT INTO `rol` (`id_rol`, `nombre_rol`) VALUES
  (1, 'Administrador'),
  (2, 'Funcionario');

CREATE TABLE `funcionario` (
  `id_funcionario` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(50) NOT NULL UNIQUE,          -- se usa para iniciar sesión
  `contrasena` VARCHAR(255) NOT NULL,            -- hash (password_hash), nunca texto plano
  `id_rol` INT NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,        -- 0 = dado de baja (no puede entrar)
  `email` VARCHAR(70) NULL UNIQUE,
  CONSTRAINT `fk_funcionario_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`)
) ENGINE=InnoDB;

-- Mismas categorías (con los mismos nombres) que el portal informativo.
-- Los nombres deben coincidir exactamente: así el gestor podrá enviar documentos al portal.
CREATE TABLE `categoria` (
  `id_categoria` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO `categoria` (`nombre`) VALUES
  ('Unidad de Cuidados Paliativos'),
  ('Emergencia'),
  ('Neonatología'),
  ('Dpto. Clínico de medicina'),
  ('Cardiología'),
  ('Centro cardiovascular'),
  ('Clínica médica A'),
  ('Clínica médica B'),
  ('Clínica médica C'),
  ('Dermatología'),
  ('Endocrinología'),
  ('Gastroenterología'),
  ('Geriatría'),
  ('Hematología'),
  ('Infectología'),
  ('Medicina física, rehabilitación y medicina del deporte'),
  ('Nefrología'),
  ('Neurología'),
  ('Oncología'),
  ('Psiquiatría'),
  ('U.E. Autoinmunes sistémicas'),
  ('Unidad de tabaquismo'),
  ('Depto. Clínico de cirugía'),
  ('Anestesiología'),
  ('Cirugía plástica y quemados'),
  ('Cirugía vascular periférica'),
  ('Ginecotocológica B'),
  ('Neurocirugía'),
  ('Odontología'),
  ('Oftalmología'),
  ('Otorrinolaringología'),
  ('Quirúrgica A'),
  ('Quirúrgica B'),
  ('Quirúrgica F'),
  ('Urología'),
  ('Traumatología y ortopedia de adultos'),
  ('Radioterapia'),
  ('Medicina Nuclear'),
  ('Hemoterapia'),
  ('Imagenología');

CREATE TABLE `documento` (
  `id_documento` INT AUTO_INCREMENT PRIMARY KEY,
  `titulo_documento` VARCHAR(150) NOT NULL,
  `id_categoria` INT NOT NULL,
  `id_funcionario` INT NOT NULL,                 -- quién lo subió
  `ruta_archivo` VARCHAR(255) NOT NULL UNIQUE,   -- relativa a uploads/documentos/
  `nombre_original` VARCHAR(255) NOT NULL,       -- nombre con el que se descarga
  `tamano` INT NOT NULL DEFAULT 0,               -- bytes
  `es_guia_general` TINYINT(1) NOT NULL DEFAULT 0, -- 1 = guía general: se publica en el portal informativo
  `estado_portal` TINYINT(1) NOT NULL DEFAULT 0,   -- solo guías: 0 = pendiente, 1 = publicada, 2 = faltan cambios por publicar
  `fecha_subida` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_documento_guia` (`es_guia_general`),
  INDEX `idx_documento_categoria` (`id_categoria`),
  INDEX `idx_documento_fecha` (`fecha_subida`),
  CONSTRAINT `fk_documento_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categoria` (`id_categoria`),
  CONSTRAINT `fk_documento_funcionario` FOREIGN KEY (`id_funcionario`) REFERENCES `funcionario` (`id_funcionario`)
) ENGINE=InnoDB;

-- Encuestas de satisfacción que llegan desde el portal informativo.
-- Tiene los mismos campos que la tabla `respuestas` del portal; `uuid` identifica
-- cada encuesta para no guardarla dos veces cuando se sincronice.
CREATE TABLE `respuesta_encuesta` (
  `id_respuesta` INT AUTO_INCREMENT PRIMARY KEY,
  `uuid` CHAR(36) NOT NULL UNIQUE,
  `clasificacion` VARCHAR(20) NULL,              -- formato anterior (formulario fijo): pesima, mala, regular, buena, excelente
  `lugar` VARCHAR(50) NULL,
  `profesional` VARCHAR(50) NULL,
  `comentario` VARCHAR(255) NULL,
  `fecha_portal` DATETIME NOT NULL,              -- cuándo la respondió el paciente
  `fecha_recibida` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_encuesta` INT NULL,                        -- a qué encuesta (plantilla) corresponde; NULL = sin asignar (el vínculo se agrega más abajo)
  INDEX `idx_respuesta_fecha` (`fecha_portal`),
  INDEX `idx_respuesta_encuesta` (`id_encuesta`)
) ENGINE=InnoDB;

-- Control de la sincronización con el portal (cuándo fue la última vez y si falló).
CREATE TABLE `sincronizacion` (
  `clave` VARCHAR(40) NOT NULL PRIMARY KEY,
  `ultimo_intento` DATETIME NULL,
  `ultimo_exito` DATETIME NULL,
  `ultimo_error` VARCHAR(255) NULL
) ENGINE=InnoDB;

INSERT INTO `sincronizacion` (`clave`) VALUES ('encuestas');

-- ---------------------------------------------------------------------------
-- Encuestas (plantillas): la estructura que administran los administradores y que,
-- más adelante, verán los pacientes. Las respuestas de los pacientes NO van acá:
-- siguen llegando a `respuesta_encuesta` (arriba).
-- ---------------------------------------------------------------------------

-- Sectores del hospital a los que pertenece cada encuesta.
CREATE TABLE `sector` (
  `id_sector` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_sector` VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO `sector` (`nombre_sector`) VALUES
  ('Enfermería'),
  ('Servicio web'),
  ('Admisiones'),
  ('Mantenimiento');

-- Una encuesta (plantilla) pertenece a un sector y tiene varias preguntas.
CREATE TABLE `encuesta` (
  `id_encuesta` INT AUTO_INCREMENT PRIMARY KEY,
  `titulo_encuesta` VARCHAR(150) NOT NULL,
  `descripcion` VARCHAR(500) NULL,
  `id_sector` INT NOT NULL,
  `id_funcionario` INT NOT NULL,                 -- quién la creó
  `activa` TINYINT(1) NOT NULL DEFAULT 0,        -- 1 = se muestra a los pacientes, 0 = inactiva (se crea inactiva)
  `estado_portal` TINYINT(1) NOT NULL DEFAULT 0, -- copia en el portal: 0 = no está, 1 = publicada y al día, 2 = falta enviar cambios (o retirarla)
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_encuesta_sector_titulo` (`id_sector`, `titulo_encuesta`),
  INDEX `idx_encuesta_titulo` (`titulo_encuesta`),
  CONSTRAINT `fk_encuesta_id_sector` FOREIGN KEY (`id_sector`) REFERENCES `sector` (`id_sector`),
  CONSTRAINT `fk_encuesta_id_funcionario` FOREIGN KEY (`id_funcionario`) REFERENCES `funcionario` (`id_funcionario`)
) ENGINE=InnoDB;

-- Preguntas de cada encuesta. Al borrar una encuesta se borran sus preguntas.
CREATE TABLE `pregunta` (
  `id_pregunta` INT AUTO_INCREMENT PRIMARY KEY,
  `id_encuesta` INT NOT NULL,
  `texto_pregunta` VARCHAR(255) NOT NULL,
  `tipo` ENUM('escala', 'opcion', 'texto') NOT NULL DEFAULT 'escala',  -- escala 1 a 5, opción única, texto libre
  `opciones` TEXT NULL,                          -- solo tipo 'opcion': una opción por línea
  `obligatoria` TINYINT(1) NOT NULL DEFAULT 1,
  `orden` SMALLINT NOT NULL DEFAULT 1,
  INDEX `idx_pregunta_encuesta` (`id_encuesta`, `orden`),
  CONSTRAINT `fk_pregunta_id_encuesta` FOREIGN KEY (`id_encuesta`) REFERENCES `encuesta` (`id_encuesta`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Vínculo de cada respuesta con su encuesta (y, por la encuesta, con su sector).
-- Si se elimina la encuesta, la respuesta se conserva y queda "sin asignar".
ALTER TABLE `respuesta_encuesta`
  ADD CONSTRAINT `fk_respuesta_id_encuesta` FOREIGN KEY (`id_encuesta`) REFERENCES `encuesta` (`id_encuesta`) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- Ambulancias (estructura para el Inicio; la gestión de turnos y el seguimiento en tiempo real
-- se agregan en el módulo de ambulancias). `conductor` es el conductor del turno actual (texto
-- libre por ahora; NULL = sin asignar).
-- ---------------------------------------------------------------------------
-- Respuestas de los pacientes, pregunta por pregunta (una fila por pregunta contestada).
-- El texto y el tipo de la pregunta se copian: si después se edita o se borra la pregunta, la respuesta se sigue entendiendo.
CREATE TABLE `respuesta_detalle` (
  `id_detalle` INT AUTO_INCREMENT PRIMARY KEY,
  `id_respuesta` INT NOT NULL,
  `id_pregunta` INT NULL,                        -- NULL si la pregunta ya no existe
  `texto_pregunta` VARCHAR(255) NOT NULL,
  `tipo` VARCHAR(10) NOT NULL,                   -- escala, opcion, texto
  `valor` VARCHAR(500) NOT NULL,                 -- escala: 1 a 5; opcion: la opción elegida; texto: lo escrito
  INDEX `idx_detalle_respuesta` (`id_respuesta`),
  CONSTRAINT `fk_detalle_id_respuesta` FOREIGN KEY (`id_respuesta`) REFERENCES `respuesta_encuesta` (`id_respuesta`) ON DELETE CASCADE,
  CONSTRAINT `fk_detalle_id_pregunta` FOREIGN KEY (`id_pregunta`) REFERENCES `pregunta` (`id_pregunta`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `ambulancia` (
  `id_ambulancia` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(50) NOT NULL UNIQUE,          -- ej. "Ambulancia 1"
  `conductor` VARCHAR(80) NULL,
  `estado` ENUM('disponible', 'en_camino', 'llego_destino', 'volviendo', 'fuera_servicio') NOT NULL DEFAULT 'disponible',
  `fecha_estado` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- desde cuándo está en ese estado
  INDEX `idx_ambulancia_estado` (`estado`)
) ENGINE=InnoDB;

-- Usuario inicial. Contraseña: Admin2026!  (cambiarla apenas se entre)
INSERT INTO `funcionario` (`nombre`, `contrasena`, `id_rol`, `activo`, `email`) VALUES
  ('admin', '$2y$10$6eo3h4S3ZkkpdWE5hCZ0Zu.42NZG7zJsDhk5vMfLsk1eimanMN8qG', 1, 1, NULL);
