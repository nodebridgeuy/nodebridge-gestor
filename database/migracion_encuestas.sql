-- Migración para quien YA tiene la base `sigsm` cargada: agrega los sectores y las encuestas
-- (plantillas con sus preguntas) SIN borrar datos. (Para una instalación desde cero alcanza con
-- importar sigsm.sql, que ya incluye todo esto.)
-- Se puede importar más de una vez. Requiere MariaDB (el de XAMPP lo es).
-- No toca la tabla `respuesta_encuesta` (las respuestas de los pacientes).

USE `sigsm`;
SET NAMES utf8mb4;

-- 1) Si la base todavía tiene las tablas `encuesta` / `pregunta` de la versión anterior del proyecto
--    (otra estructura), se apartan con otro nombre para no perder datos: encuesta_antigua / pregunta_antigua.
--    Si no existen, estas sentencias no hacen nada.
SET @encuesta_vieja := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'encuesta' AND COLUMN_NAME = 'fecha_publi_encuesta'
);
SET @pregunta_vieja := (
  SELECT COUNT(*) FROM information_schema.TABLES t
   WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME = 'pregunta'
     AND NOT EXISTS (
       SELECT 1 FROM information_schema.COLUMNS c
        WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = 'pregunta' AND c.COLUMN_NAME = 'texto_pregunta'
     )
);

SET @sql := IF(@encuesta_vieja > 0, 'RENAME TABLE `encuesta` TO `encuesta_antigua`', 'DO 0');
PREPARE paso FROM @sql;
EXECUTE paso;
DEALLOCATE PREPARE paso;

SET @sql := IF(@pregunta_vieja > 0, 'RENAME TABLE `pregunta` TO `pregunta_antigua`', 'DO 0');
PREPARE paso FROM @sql;
EXECUTE paso;
DEALLOCATE PREPARE paso;

-- 2) Sectores (si ya existía la tabla `sector` de antes, se reutiliza y se conservan sus filas)
CREATE TABLE IF NOT EXISTS `sector` (
  `id_sector` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_sector` VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Cada sector se agrega solo si todavía no existe (se puede repetir sin duplicar).
INSERT INTO `sector` (`nombre_sector`)
SELECT 'Enfermería' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `sector` WHERE `nombre_sector` = 'Enfermería');

INSERT INTO `sector` (`nombre_sector`)
SELECT 'Servicio web' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `sector` WHERE `nombre_sector` = 'Servicio web');

INSERT INTO `sector` (`nombre_sector`)
SELECT 'Admisiones' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `sector` WHERE `nombre_sector` = 'Admisiones');

INSERT INTO `sector` (`nombre_sector`)
SELECT 'Mantenimiento' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `sector` WHERE `nombre_sector` = 'Mantenimiento');

-- 3) Encuestas (plantillas)
CREATE TABLE IF NOT EXISTS `encuesta` (
  `id_encuesta` INT AUTO_INCREMENT PRIMARY KEY,
  `titulo_encuesta` VARCHAR(150) NOT NULL,
  `descripcion` VARCHAR(500) NULL,
  `id_sector` INT NOT NULL,
  `id_funcionario` INT NOT NULL,                 -- quién la creó
  `activa` TINYINT(1) NOT NULL DEFAULT 0,        -- 1 = se muestra a los pacientes, 0 = inactiva (se crea inactiva)
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_encuesta_sector_titulo` (`id_sector`, `titulo_encuesta`),
  INDEX `idx_encuesta_titulo` (`titulo_encuesta`),
  CONSTRAINT `fk_encuesta_id_sector` FOREIGN KEY (`id_sector`) REFERENCES `sector` (`id_sector`),
  CONSTRAINT `fk_encuesta_id_funcionario` FOREIGN KEY (`id_funcionario`) REFERENCES `funcionario` (`id_funcionario`)
) ENGINE=InnoDB;

-- 4) Preguntas de cada encuesta (al borrar la encuesta se borran sus preguntas)
CREATE TABLE IF NOT EXISTS `pregunta` (
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
