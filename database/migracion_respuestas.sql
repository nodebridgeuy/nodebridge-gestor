-- Migración para quien YA tiene la base `sigsm` cargada: vincula cada respuesta de paciente con su
-- encuesta (y por la encuesta, con su sector) para poder filtrarlas. NO borra datos.
-- Importar DESPUÉS de migracion_encuestas.sql. Se puede importar más de una vez. Requiere MariaDB (XAMPP).
-- Las respuestas que ya estaban quedan "sin asignar" (id_encuesta = NULL) hasta que el portal informe la encuesta.

USE `sigsm`;
SET NAMES utf8mb4;

ALTER TABLE `respuesta_encuesta` ADD COLUMN IF NOT EXISTS `id_encuesta` INT NULL;
ALTER TABLE `respuesta_encuesta` ADD INDEX IF NOT EXISTS `idx_respuesta_encuesta` (`id_encuesta`);

SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
   WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'respuesta_encuesta'
     AND CONSTRAINT_NAME = 'fk_respuesta_id_encuesta'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE `respuesta_encuesta` ADD CONSTRAINT `fk_respuesta_id_encuesta` FOREIGN KEY (`id_encuesta`) REFERENCES `encuesta` (`id_encuesta`) ON DELETE SET NULL',
  'DO 0');
PREPARE paso FROM @sql;
EXECUTE paso;
DEALLOCATE PREPARE paso;
