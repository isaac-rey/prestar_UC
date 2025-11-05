<?php
session_start();
require __DIR__ . '/../config/db.php'; // Conexión a la BD
require __DIR__ . '/../config/mail.php'; // PHPMailer centralizado



use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ci = trim($_POST['ci'] ?? '');

  if ($ci === '') {
    $error = 'Debe ingresar su CI.';
  } else {
    // Buscar usuario por CI
    $stmt = $mysqli->prepare("SELECT id, nombre, email FROM estudiantes WHERE ci = ? LIMIT 1");
    $stmt->bind_param('s', $ci);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if (!$user) {
      $error = 'No existe un usuario con ese CI.';
    } else {
      // Generar token y vencimiento
      $token = bin2hex(random_bytes(32));
      $expira_en = date('Y-m-d H:i:s', strtotime('+30 minutes'));

      // Guardar token en DB
      $stmt = $mysqli->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
      $stmt->bind_param('iss', $user['id'], $token, $expira_en);
      $stmt->execute();
      $stmt->close();

      // Enlace de recuperación
      $reset_link = "http://10.85.200.10/inventario_uni/config/reset_estudiantes.php?token=" . urlencode($token);

      // --- Enviar correo con PHPMailer ---
      $mail = getMailer();

      try {
        $mail->addAddress($user['email'], $user['nombre']);
        $mail->Subject = "Restablecer contraseña";
        $mail->Body    = "Hola <b>{$user['nombre']}</b>,<br><br>
                                  Haz clic en el siguiente enlace para restablecer tu contraseña:<br>
                                  <a href='$reset_link'>$reset_link</a><br><br>
                                  Este enlace expira en 30 minutos.";

        $mail->send();
        $success = "Se ha enviado un enlace de recuperación a tu correo.";
      } catch (Exception $e) {
        $error = "Error al enviar el correo: {$mail->ErrorInfo}";
      }
    }
  }
}
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Recuperar Contraseña</title>
  <script src="https://kit.fontawesome.com/64d58efce2.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="../css/form_login.css" />
</head>

<body>
  <div class="container">
    <div class="forms-container">
      <div class="signin-signup">
        <!--login recuperar contraseña-->
        <form method="post" class="sign-in-form">
          <h1 class="title">Recuperar Contraseña</h1>
          <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <?php if ($success): ?>
            <div class="success" ><?= htmlspecialchars($success) ?></div>
          <?php endif; ?>

          <input type="hidden" name="rol" value="estudiante" />
          <div class="input-field">
            <i class="fas fa-id-card"></i>
            <input type="text" name="ci" placeholder="CI" required />
          </div>
          <input type="submit" value="Enviar" class="btn solid" />

          <!--form method="post"

          <label>Ingrese su CI</label>
          <input name="ci" required>
          <button type="submit">Enviar enlace</button>-->
          <!-- 🔹 Botón para volver al login -->
          <div class="forgot">
            <a href="../auth/login.php">Volver al login</a>
          </div>



        </form>
        
      

      <script>
        const sign_in_btn = document.querySelector("#sign-in-btn");
        const sign_up_btn = document.querySelector("#sign-up-btn");
        const container = document.querySelector(".container");
/*
        if (sign_in_btn && sign_up_btn) {
          sign_up_btn.addEventListener("click", () => container.classList.add("sign-up-mode"));
          sign_in_btn.addEventListener("click", () => container.classList.remove("sign-up-mode"));
        }*/
        sign_up_btn.addEventListener("click", () => {
          container.classList.add("sign-up-mode");
        });

        sign_in_btn.addEventListener("click", () => {
          container.classList.remove("sign-up-mode");
        });


        // Animaciones de paneles
      if (sign_up_btn && sign_in_btn) {
        sign_up_btn.addEventListener("click", () => container.classList.add("sign-up-mode"));
        sign_in_btn.addEventListener("click", () => container.classList.remove("sign-up-mode"));
      }
      </script>
</body>

</html>