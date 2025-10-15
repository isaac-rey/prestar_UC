<?php
// /prestar_uc/auth/logout_estudiante.php

// 1. Iniciar la sesión para poder acceder a sus datos
session_start();

// 2. Destruir todas las variables de sesión registradas
$_SESSION = [];

// 3. Si se usa el módulo de cookies, también se elimina la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Finalmente, destruir la sesión
session_destroy();

// 5. Redirigir al usuario a la página de login
header('Location: /prestar_uc/auth/login.php');
exit;
?>
