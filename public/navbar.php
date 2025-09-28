<?php
// Solo incluir init.php si no está ya incluido
if (!function_exists('user')) {
    require_once __DIR__ . '/../init.php';
}

// Obtener datos del usuario si no están definidos
if (!isset($rol)) {
    $rol = user()['rol'];
}

// Función para determinar si el enlace está activo
function isActive($currentPage, $targetPage) {
    return basename($_SERVER['PHP_SELF']) === $targetPage ? 'active' : '';
}

// Obtener la página actual para marcar el elemento activo
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Inventario</title>
    <link rel="stylesheet" href="../css/navbar.css">
    <style>
       
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-container">
            <!-- Brand -->
            <div class="nav-brand">
                <a href="/inventario_uni/" class="brand-link">Inventario</a>
                <span class="badge"><?= htmlspecialchars($rol) ?></span>
            </div>

            <!-- Botón hamburguesa -->
            <div class="nav-toggle" id="nav-toggle">
                <span></span>
                <span></span>
                <span></span>
            </div>

            <!-- Menú -->
            <ul class="nav-menu" id="nav-menu">
                <li class="nav-item">
                    <a href="/inventario_uni/public/equipos_index.php" 
                       class="nav-link <?= isActive($currentPage, 'equipos_index.php') ?>">
                       Equipos
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/inventario_uni/public/prestamos_index.php" 
                       class="nav-link <?= isActive($currentPage, 'prestamos_index.php') ?>">
                       Préstamos
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/inventario_uni/public/estudiantes_listar.php" 
                       class="nav-link <?= isActive($currentPage, 'estudiantes_listar.php') ?>">
                       Estudiantes
                    </a>
                </li>
            
                <?php if ($rol === 'admin'): ?>
                <li class="nav-item">
                    <a href="/inventario_uni/public/usuarios_index.php" 
                       class="nav-link <?= isActive($currentPage, 'usuarios_index.php') ?>">
                       Usuarios
                    </a>
                </li>
                <?php endif; ?>

                <!-- Usuario dentro del menú -->
                <li class="nav-item nav-user">
                    <span class="user-name"><?= htmlspecialchars(user()['nombre']) ?></span>
                    <a href="/inventario_uni/auth/logout.php" class="logout-btn">Salir</a>
                </li>
            </ul>
        </div>
    </nav>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.getElementById('nav-toggle');
        const menu = document.getElementById('nav-menu');

        toggle.addEventListener('click', () => {
            toggle.classList.toggle('active');
            menu.classList.toggle('active');
        });
    });
    </script>
</body>

</html>