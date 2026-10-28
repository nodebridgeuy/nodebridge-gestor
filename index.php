<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

// Punto de entrada: con sesión va al panel, sin sesión va al login.
redirigir(usuario_actual() ? 'panel.php' : 'login.php');
