-- Ambulancias de ejemplo (opcional) para ver el Inicio con datos. Importar DESPUÉS de sigsm.sql
-- (o de migracion_ambulancias.sql). No duplica: cada nombre se agrega una sola vez.

USE `sigsm`;
SET NAMES utf8mb4;

INSERT IGNORE INTO `ambulancia` (`nombre`, `conductor`, `estado`, `fecha_estado`) VALUES
  ('Ambulancia 1', 'Carlos Pérez',   'disponible',     DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
  ('Ambulancia 2', 'Laura Gómez',    'en_camino',      DATE_SUB(NOW(), INTERVAL 8 MINUTE)),
  ('Ambulancia 3', 'Martín Rodríguez', 'llego_destino', DATE_SUB(NOW(), INTERVAL 3 MINUTE)),
  ('Ambulancia 4', 'Sofía Fernández', 'volviendo',     DATE_SUB(NOW(), INTERVAL 14 MINUTE)),
  ('Ambulancia 5', NULL,              'disponible',     DATE_SUB(NOW(), INTERVAL 2 HOUR));
