-- Migración para quien YA tiene la base `sigsm` cargada: agrega el captcha con bloqueo por intentos
-- y la recuperación de contraseña por solicitud. NO borra datos. Se puede importar más de una vez.
-- Requiere MariaDB (el de XAMPP lo es).

USE `sigsm`;
SET NAMES utf8mb4;

ALTER TABLE `funcionario`
  ADD COLUMN IF NOT EXISTS `debe_cambiar_clave` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `cedula` VARCHAR(8) NULL;
-- Los funcionarios que ya existían quedan con la cédula vacía ("Falta" en la lista): completarla con Editar.
CREATE UNIQUE INDEX IF NOT EXISTS `uq_funcionario_cedula` ON `funcionario` (`cedula`);

CREATE TABLE IF NOT EXISTS `solicitud_clave` (
  `id_solicitud` INT AUTO_INCREMENT PRIMARY KEY,
  `id_funcionario` INT NOT NULL,
  `fecha` DATETIME NOT NULL,
  `atendida` TINYINT(1) NOT NULL DEFAULT 0,
  INDEX `idx_solicitud_pendiente` (`atendida`, `id_funcionario`),
  CONSTRAINT `fk_solicitud_funcionario` FOREIGN KEY (`id_funcionario`) REFERENCES `funcionario` (`id_funcionario`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `intento_login` (
  `id_intento` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario` VARCHAR(50) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `fecha` DATETIME NOT NULL,
  INDEX `idx_intento_fecha` (`fecha`)
) ENGINE=InnoDB;
