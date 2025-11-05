<?php
// formulario_nueva_clave.php
require_once 'db.php';

$token = $_GET['token'] ?? '';
$isValid = false;
$errorMsg = '';

if (!empty($token)) {
    $stmt = $mysqli->prepare("
        SELECT pr.id, pr.user_id, pr.table_name
        FROM password_resets pr
        WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $isValid = true;
    } else {
        $errorMsg = "El enlace de restablecimiento es inválido, ya fue usado o ha expirado.";
    }
    $stmt->close();
} else {
    $errorMsg = "Token de restablecimiento no proporcionado.";
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Restablecer Contraseña</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://kit.fontawesome.com/64d58efce2.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="../css/form_login.css">
</head>

<body>
    <div class="container">
        <div class="signin-signup">
            <form action="actualizar_clave_general.php" method="POST" class="sign-in-form">
                <h2 class="title">Establecer Nueva Contraseña</h2>

                <?php if (!$isValid): ?>
                    <div class="error">
                        <?= htmlspecialchars($errorMsg) ?>
                    </div>
                    <a href="solicitar_reset_ci.php" class="btn transparent">Solicitar nuevo enlace</a>
                <?php else: ?>
                    <p style="color: rgba(255,255,255,0.9); margin-bottom: 10px; text-align:center;">
                        Introduce tu nueva contraseña (mínimo 8 caracteres).
                    </p>

                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="input-field">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" minlength="8" placeholder="Nueva Contraseña" required>
                    </div>

                    <div class="input-field">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" minlength="8" placeholder="Confirmar Contraseña" required>
                    </div>

                    <button type="submit" class="btn solid">Restablecer</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>

</html>
<?php $mysqli->close(); ?>
