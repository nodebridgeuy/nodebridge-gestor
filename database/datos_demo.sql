-- Datos de prueba (opcional). Importar DESPUÉS de sigsm.sql.
USE `sigsm`;
SET NAMES utf8mb4;

-- Funcionario de prueba. Contraseña: Func2026!
INSERT INTO `funcionario` (`nombre`, `contrasena`, `id_rol`, `activo`, `email`, `cedula`) VALUES
  ('funcionario', '$2y$10$iKA9JarZHsVJC7pwLM6qGuwa5u91M0dgnSej0N2ybMi/bnSpstAEq', 2, 1, 'funcionario@ejemplo.local', '12345672');

-- Encuestas de ejemplo (como las que traerá el portal)
INSERT INTO `respuesta_encuesta` (`uuid`, `clasificacion`, `lugar`, `profesional`, `comentario`, `fecha_portal`) VALUES
  ('11111111-1111-4111-8111-111111111101', 'excelente', 'lab1', 'profesional1', 'Muy buena atención, todo rápido.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
  ('11111111-1111-4111-8111-111111111102', 'buena',     'lab2', 'profesional2', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
  ('11111111-1111-4111-8111-111111111103', 'regular',   'lab1', 'profesional3', 'Esperé mucho tiempo.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
  ('11111111-1111-4111-8111-111111111104', 'mala',      'lab3', 'profesional1', 'No me explicaron el procedimiento.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
  ('11111111-1111-4111-8111-111111111105', 'excelente', 'lab4', 'profesional4', NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
  ('11111111-1111-4111-8111-111111111106', 'pesima',    'lab5', 'profesional5', 'Muy mala experiencia.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
  ('11111111-1111-4111-8111-111111111107', 'buena',     'lab6', 'profesional6', 'Todo bien, gracias.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
  ('11111111-1111-4111-8111-111111111108', 'excelente', 'lab2', 'profesional2', 'Personal muy amable.', DATE_SUB(NOW(), INTERVAL 8 DAY));
