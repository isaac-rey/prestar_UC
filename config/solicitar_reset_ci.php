<?php
// solicitar_reset_ci.php
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Recuperar Contraseña (Docente/Estudiante)</title>

    <script src="https://kit.fontawesome.com/64d58efce2.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="../css/form_login.css" />
</head>

<body>
    <div class="container">
        <div class="forms-container">
            <h2>Restablecer Contraseña</h2>
            <p>Introduce tu Cédula de Identidad (CI) para recibir un enlace de restablecimiento de contraseña.</p>

            <div class="forms-container">
                <div class="signin-signup">
                    <!--login recuperar contraseña-->
                    <?php
                    if (isset($_GET['error'])) {
                        $error = $_GET['error'];
                        if ($error == 'noexiste') echo '<p style="color:red;">Error: La CI proporcionada no se encontró en nuestros registros de Docentes o Estudiantes.</p>';
                        if ($error == 'vacio') echo '<p style="color:red;">Error: Debe ingresar una Cédula de Identidad.</p>';
                        if ($error == 'mailerror') echo '<p style="color:red;">Error: No se pudo enviar el correo de restablecimiento. Intente más tarde.</p>';
                    }
                    if (isset($_GET['success']) && $_GET['success'] == 'enviado') {
                        echo '<p style="color:green;">Si la CI está registrada, has recibido un correo electrónico con el enlace para restablecer tu contraseña. Revisa tu bandeja de entrada y spam.</p>';
                    }
                    ?>

                    <form action="procesar_envio_link.php" method="POST" class="sign-in-form">
                        <h1 class="title">Recuperar Contraseña</h1>
                        <div class="input-field">
                            <i class="fas fa-id-card"></i>
                            <input type="text" name="ci" placeholder="CI" required />
                        </div>
                        <input type="submit" value="Enviar" class="btn solid" />

                        <!-- 🔹 Botón para volver al login -->
                        <div class="forgot">
                            <a href="../auth/login.php">Volver al login</a>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>