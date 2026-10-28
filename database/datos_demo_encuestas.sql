-- Encuestas de ejemplo (opcional). Importar DESPUÉS de sigsm.sql (o de migracion_encuestas.sql).
-- Se pueden importar más de una vez: no duplica las encuestas que ya existen.
-- Las crea a nombre del usuario `admin`.

USE `sigsm`;
SET NAMES utf8mb4;

-- ---------- Enfermería ----------
INSERT INTO `encuesta` (`titulo_encuesta`, `descripcion`, `id_sector`, `id_funcionario`, `activa`)
SELECT 'Satisfacción con la atención de enfermería',
       'Opinión del paciente sobre el trato y la atención recibida.',
       s.id_sector, f.id_funcionario, 1
  FROM `sector` s
  JOIN `funcionario` f ON f.nombre = 'admin'
 WHERE s.nombre_sector = 'Enfermería'
   AND NOT EXISTS (SELECT 1 FROM `encuesta` e
                    WHERE e.id_sector = s.id_sector
                      AND e.titulo_encuesta = 'Satisfacción con la atención de enfermería');

INSERT INTO `pregunta` (`id_encuesta`, `texto_pregunta`, `tipo`, `opciones`, `obligatoria`, `orden`)
SELECT e.id_encuesta, q.texto, q.tipo, q.opciones, q.obligatoria, q.orden
  FROM `encuesta` e
  JOIN (
        SELECT 1 AS orden, '¿Cómo calificaría la atención recibida hoy?' AS texto, 'escala' AS tipo,
               NULL AS opciones, 1 AS obligatoria
        UNION ALL
        SELECT 2, '¿En qué turno fue atendido?', 'opcion',
               CONCAT('Mañana', CHAR(10), 'Tarde', CHAR(10), 'Noche'), 1
        UNION ALL
        SELECT 3, '¿Algo que le gustaría que supiéramos?', 'texto', NULL, 0
       ) q
 WHERE e.titulo_encuesta = 'Satisfacción con la atención de enfermería'
   AND NOT EXISTS (SELECT 1 FROM `pregunta` p WHERE p.id_encuesta = e.id_encuesta);

-- ---------- Servicio web ----------
INSERT INTO `encuesta` (`titulo_encuesta`, `descripcion`, `id_sector`, `id_funcionario`, `activa`)
SELECT 'Valoración del portal informativo',
       'Qué tan útil y fácil de usar resulta el portal para pacientes y visitantes.',
       s.id_sector, f.id_funcionario, 1
  FROM `sector` s
  JOIN `funcionario` f ON f.nombre = 'admin'
 WHERE s.nombre_sector = 'Servicio web'
   AND NOT EXISTS (SELECT 1 FROM `encuesta` e
                    WHERE e.id_sector = s.id_sector
                      AND e.titulo_encuesta = 'Valoración del portal informativo');

INSERT INTO `pregunta` (`id_encuesta`, `texto_pregunta`, `tipo`, `opciones`, `obligatoria`, `orden`)
SELECT e.id_encuesta, q.texto, q.tipo, q.opciones, q.obligatoria, q.orden
  FROM `encuesta` e
  JOIN (
        SELECT 1 AS orden, '¿Qué tan fácil le resultó encontrar la información que buscaba?' AS texto, 'escala' AS tipo,
               NULL AS opciones, 1 AS obligatoria
        UNION ALL
        SELECT 2, '¿Qué mejoraría del portal?', 'texto', NULL, 0
       ) q
 WHERE e.titulo_encuesta = 'Valoración del portal informativo'
   AND NOT EXISTS (SELECT 1 FROM `pregunta` p WHERE p.id_encuesta = e.id_encuesta);

-- ---------- Admisiones ----------
INSERT INTO `encuesta` (`titulo_encuesta`, `descripcion`, `id_sector`, `id_funcionario`, `activa`)
SELECT 'Atención en admisiones',
       'Trato recibido y tiempo de espera al ingresar al hospital.',
       s.id_sector, f.id_funcionario, 1
  FROM `sector` s
  JOIN `funcionario` f ON f.nombre = 'admin'
 WHERE s.nombre_sector = 'Admisiones'
   AND NOT EXISTS (SELECT 1 FROM `encuesta` e
                    WHERE e.id_sector = s.id_sector
                      AND e.titulo_encuesta = 'Atención en admisiones');

INSERT INTO `pregunta` (`id_encuesta`, `texto_pregunta`, `tipo`, `opciones`, `obligatoria`, `orden`)
SELECT e.id_encuesta, q.texto, q.tipo, q.opciones, q.obligatoria, q.orden
  FROM `encuesta` e
  JOIN (
        SELECT 1 AS orden, '¿Cómo calificaría el trato recibido en admisiones?' AS texto, 'escala' AS tipo,
               NULL AS opciones, 1 AS obligatoria
        UNION ALL
        SELECT 2, '¿Cuánto tiempo esperó para ser atendido?', 'opcion',
               CONCAT('Menos de 10 minutos', CHAR(10), 'Entre 10 y 30 minutos', CHAR(10), 'Más de 30 minutos'), 1
        UNION ALL
        SELECT 3, '¿Desea dejar algún comentario?', 'texto', NULL, 0
       ) q
 WHERE e.titulo_encuesta = 'Atención en admisiones'
   AND NOT EXISTS (SELECT 1 FROM `pregunta` p WHERE p.id_encuesta = e.id_encuesta);

-- ---------- Mantenimiento (queda inactiva, para ver cómo se muestra ese estado) ----------
INSERT INTO `encuesta` (`titulo_encuesta`, `descripcion`, `id_sector`, `id_funcionario`, `activa`)
SELECT 'Estado y limpieza de las instalaciones',
       'Opinión sobre baños, salas de espera, ascensores y pasillos.',
       s.id_sector, f.id_funcionario, 0
  FROM `sector` s
  JOIN `funcionario` f ON f.nombre = 'admin'
 WHERE s.nombre_sector = 'Mantenimiento'
   AND NOT EXISTS (SELECT 1 FROM `encuesta` e
                    WHERE e.id_sector = s.id_sector
                      AND e.titulo_encuesta = 'Estado y limpieza de las instalaciones');

INSERT INTO `pregunta` (`id_encuesta`, `texto_pregunta`, `tipo`, `opciones`, `obligatoria`, `orden`)
SELECT e.id_encuesta, q.texto, q.tipo, q.opciones, q.obligatoria, q.orden
  FROM `encuesta` e
  JOIN (
        SELECT 1 AS orden, '¿Cómo calificaría el estado de las instalaciones?' AS texto, 'escala' AS tipo,
               NULL AS opciones, 1 AS obligatoria
        UNION ALL
        SELECT 2, 'Si encontró algún problema, cuéntenos cuál.', 'texto', NULL, 0
       ) q
 WHERE e.titulo_encuesta = 'Estado y limpieza de las instalaciones'
   AND NOT EXISTS (SELECT 1 FROM `pregunta` p WHERE p.id_encuesta = e.id_encuesta);

-- ---------------------------------------------------------------------------
-- Respuestas de ejemplo vinculadas a las encuestas de arriba (para probar la sección
-- "Respuestas a encuestas"). Requiere haber importado antes sigsm.sql (o migracion_respuestas.sql).
-- No duplica: cada una tiene un uuid fijo.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `respuesta_encuesta` (`uuid`, `clasificacion`, `lugar`, `profesional`, `comentario`, `fecha_portal`, `id_encuesta`)
SELECT r.uuid, r.clasificacion, r.lugar, r.profesional, r.comentario, DATE_SUB(NOW(), INTERVAL r.dias DAY),
       (SELECT e.id_encuesta FROM `encuesta` e WHERE e.titulo_encuesta = r.titulo LIMIT 1)
  FROM (
        SELECT '22222222-2222-4222-8222-222222222201' AS uuid, 'excelente' AS clasificacion, 'sala1' AS lugar, 'profesional1' AS profesional, 'Me atendieron con mucha amabilidad.' AS comentario, 1 AS dias, 'Satisfacción con la atención de enfermería' AS titulo
        UNION ALL SELECT '22222222-2222-4222-8222-222222222202', 'buena',     'sala2', 'profesional2', NULL, 2, 'Satisfacción con la atención de enfermería'
        UNION ALL SELECT '22222222-2222-4222-8222-222222222203', 'regular',   'sala1', 'profesional3', 'Tardaron en venir al llamado.', 3, 'Satisfacción con la atención de enfermería'
        UNION ALL SELECT '22222222-2222-4222-8222-222222222204', 'excelente', 'web',   'profesional4', 'El portal es claro y rápido.', 2, 'Valoración del portal informativo'
        UNION ALL SELECT '22222222-2222-4222-8222-222222222205', 'mala',      'web',   'profesional4', 'No encontré los horarios de consulta.', 4, 'Valoración del portal informativo'
        UNION ALL SELECT '22222222-2222-4222-8222-222222222206', 'buena',     'adm1',  'profesional5', NULL, 5, 'Atención en admisiones'
        UNION ALL SELECT '22222222-2222-4222-8222-222222222207', 'pesima',    'adm1',  'profesional5', 'Más de una hora de espera.', 6, 'Atención en admisiones'
        UNION ALL SELECT '22222222-2222-4222-8222-222222222208', 'regular',   'hall',  'profesional6', 'Los baños necesitan más limpieza.', 7, 'Estado y limpieza de las instalaciones'
       ) r
 WHERE EXISTS (SELECT 1 FROM `encuesta` e WHERE e.titulo_encuesta = r.titulo);
