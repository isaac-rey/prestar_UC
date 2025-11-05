<?php
require __DIR__ . '/docente_init.php';
require_doc_login();
$e = doc();
$serial = trim($_GET['serial'] ?? '');
// if ($serial === '') die("Serial no especificado.");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Elección del docente</title>
<link rel="stylesheet" href="docente_styles.css">
<style>
    .contenedorBotones{
        width: 100%;
        height: 60vh;
        display: flex;
        justify-content: space-evenly;
        align-items: center;
        flex-direction: column;
    }
    .botones{
        font-size: 40px !important;
    }
</style>
</head>
<body>
<header>
    <a href="/prestar_UC/public/docentes/docente_panel.php">Inventario – Docente</a>
    <div>
        <button id="theme-toggle" class="btn-secondary btn-sm">🌙</button>
        <?= htmlspecialchars($e['nombre'].' '.$e['apellido']) ?> · 
        <a href="/prestar_UC/auth/logout_docente.php">Salir</a>
    </div>
</header>

<div class="container">
    <div class="contenedorBotones">
        <a href="/prestar_UC/public/docentes/docente_equipo.php?serial=<?php echo $serial ?>"  class="btn btn-primary botones">Prestar equipo</a>
        <a href="/prestar_UC/public/docentes/docente_equipo_reportar.php?serial=<?php echo $serial ?>" class="btn btn-primary botones">Realizar un reporte</a>
    </div>
</div>
<script>
        document.addEventListener('DOMContentLoaded', () => {
            const body = document.body,
                toggle = document.getElementById('theme-toggle');
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            let current = stored || (prefersDark ? 'dark' : 'light');
            const apply = t => {
                if (t === 'light') {
                    body.classList.add('light-mode');
                    toggle.innerHTML = '🌙';
                } else {
                    body.classList.remove('light-mode');
                    toggle.innerHTML = '☀️';
                }
                localStorage.setItem('theme', t);
                current = t;
            };
            apply(current);
            toggle.addEventListener('click', () => apply(current === 'dark' ? 'light' : 'dark'));
            actualizar();
            setInterval(actualizar, 2000);
        });
    </script>
</body>
</html>
