-- Migración para quien YA tiene la base `sigsm` cargada: agrega lo nuevo SIN borrar datos.
-- (Para una instalación desde cero alcanza con importar sigsm.sql.)
-- Se puede importar más de una vez. Requiere MariaDB (el de XAMPP lo es).

USE `sigsm`;
SET NAMES utf8mb4;

ALTER TABLE `documento`
  ADD COLUMN IF NOT EXISTS `es_guia_general` TINYINT(1) NOT NULL DEFAULT 0 AFTER `tamano`,
  ADD COLUMN IF NOT EXISTS `estado_portal` TINYINT(1) NOT NULL DEFAULT 0 AFTER `es_guia_general`;

ALTER TABLE `documento` ADD INDEX IF NOT EXISTS `idx_documento_guia` (`es_guia_general`);

CREATE TABLE IF NOT EXISTS `sincronizacion` (
  `clave` VARCHAR(40) NOT NULL PRIMARY KEY,
  `ultimo_intento` DATETIME NULL,
  `ultimo_exito` DATETIME NULL,
  `ultimo_error` VARCHAR(255) NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO `sincronizacion` (`clave`) VALUES ('encuestas');
