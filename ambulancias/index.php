<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

// Módulo 2 (trazabilidad de ambulancias): lo pueden abrir Administrador y Funcionario.
requerir_login();

encabezado('Ambulancias', 'ambulancias');
?>
<div class="card" style="max-width: 640px;">
    <div class="card-body">
        <h2 class="h6">Módulo de trazabilidad de ambulancias</h2>
        <p class="text-secondary mb-0">Este módulo está en desarrollo. Cuando esté listo se va a abrir desde acá, con el mismo menú y los mismos permisos.</p>
    </div>
</div>
<?php pie(); ?>
