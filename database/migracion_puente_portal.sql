-- Migración para quien YA tiene la base `sigsm` cargada: agrega lo necesario para el puente con el portal
-- (publicar encuestas y recibir respuestas pregunta por pregunta). NO borra datos.
-- Importar DESPUÉS de migracion_guias_generales, migracion_encuestas y migracion_respuestas.
-- Se puede importar más de una vez. Requiere MariaDB (el de XAMPP lo es).

USE `sigsm`;
SET NAMES utf8mb4;

-- Estado de la copia de cada encuesta en el portal.
ALTER TABLE `encuesta`
  ADD COLUMN IF NOT EXISTS `estado_portal` TINYINT(1) NOT NULL DEFAULT 0 AFTER `activa`;

-- Los campos del formulario fijo anterior pasan a ser opcionales (solo los usan las respuestas viejas).
ALTER TABLE `respuesta_encuesta`
  MODIFY `clasificacion` VARCHAR(20) NULL,
  MODIFY `lugar` VARCHAR(50) NULL,
  MODIFY `profesional` VARCHAR(50) NULL;

-- Respuestas pregunta por pregunta.
CREATE TABLE IF NOT EXISTS `respuesta_detalle` (
  `id_detalle` INT AUTO_INCREMENT PRIMARY KEY,
  `id_respuesta` INT NOT NULL,
  `id_pregunta` INT NULL,
  `texto_pregunta` VARCHAR(255) NOT NULL,
  `tipo` VARCHAR(10) NOT NULL,
  `valor` VARCHAR(500) NOT NULL,
  INDEX `idx_detalle_respuesta` (`id_respuesta`),
  CONSTRAINT `fk_detalle_id_respuesta` FOREIGN KEY (`id_respuesta`) REFERENCES `respuesta_encuesta` (`id_respuesta`) ON DELETE CASCADE,
  CONSTRAINT `fk_detalle_id_pregunta` FOREIGN KEY (`id_pregunta`) REFERENCES `pregunta` (`id_pregunta`) ON DELETE SET NULL
) ENGINE=InnoDB;
