-- Migración para quien YA tiene la base `sigsm` cargada: agrega la tabla de ambulancias que usa el Inicio.
-- No borra datos y se puede importar más de una vez.

USE `sigsm`;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `ambulancia` (
  `id_ambulancia` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(50) NOT NULL UNIQUE,
  `conductor` VARCHAR(80) NULL,
  `estado` ENUM('disponible', 'en_camino', 'llego_destino', 'volviendo', 'fuera_servicio') NOT NULL DEFAULT 'disponible',
  `fecha_estado` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ambulancia_estado` (`estado`)
) ENGINE=InnoDB;
