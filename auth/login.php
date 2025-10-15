<?php
session_start();
require __DIR__ . '/../config/db.php';

$error = '';
$error_estudiante = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $rol = $_POST['rol'] ?? '';
  $ci = trim($_POST['ci'] ?? '');
  $pass = $_POST['password'] ?? '';

  if ($ci === '' || $pass === '') {
    $error_msg = 'CI y contraseña son obligatorios.';
    if ($rol === 'docente') {
      $error = $error_msg;
    } else {
      $error_estudiante = $error_msg;
    }
  } else {
    if ($rol === 'docente') {
      $stmt = $mysqli->prepare("SELECT id, ci, nombre, apellido, password_hash FROM docentes WHERE ci=? LIMIT 1");
    } else {
      $stmt = $mysqli->prepare("SELECT id, ci, nombre, apellido, password_hash FROM estudiantes WHERE ci=? LIMIT 1");
    }
    $stmt->bind_param('s', $ci);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row && password_verify($pass, $row['password_hash'])) {
      if ($rol === 'docente') {
        $_SESSION['doc'] = [
          'id' => $row['id'],
          'ci' => $row['ci'],
          'nombre' => $row['nombre'],
          'apellido' => $row['apellido']
        ];
        header('Location: /prestar_uc/public/docentes/docente_panel.php');
      } else {
        $_SESSION['est'] = [
          'id' => $row['id'],
          'ci' => $row['ci'],
          'nombre' => $row['nombre'],
          'apellido' => $row['apellido']
        ];
        header('Location: /prestar_uc/public/estudiantes/estudiante_panel.php');
      }
      exit;
    } else {
      $error_msg = 'CI o contraseña incorrectos.';
      if ($rol === 'docente') {
        $error = $error_msg;
      } else {
        $error_estudiante = $error_msg;
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ingreso — Inventario</title>
  <script src="https://kit.fontawesome.com/64d58efce2.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="../css/login.css" />
</head>
<body>
  <div class="container">
    <div class="forms-container">
      <div class="signin-signup">

        <!-- ================= LOGIN DOCENTE ================= -->
        <form method="post" class="sign-in-form">
          <h1 class="title">Docente</h1>
          <?php if ($error): ?>
            <div class="error" style="
              background:#ffe5e5;
              color:#b91c1c;
              border:1px solid #fca5a5;
              border-radius:10px;
              padding:8px 12px;
              margin-bottom:10px;
              font-weight:500;
              text-align:center;">
              <?= htmlspecialchars($error) ?>
            </div>
          <?php endif; ?>

          <input type="hidden" name="rol" value="docente" />
          
          <div class="input-field">
            <i class="fas fa-id-card"></i>
            <input type="text" name="ci" placeholder="CI" required />
          </div>
          <div class="input-field">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" placeholder="Contraseña" required />
          </div>

          <div style="margin-top: 10px; text-align:center;">
            <a href="../config/password_estudiantes.php" style="color:#4481eb; text-decoration:none; font-size:0.9rem;">
              ¿Olvidaste tu contraseña?
            </a>
          </div>

          <input type="submit" value="Entrar" class="btn solid" />
        </form>

        <!-- ================= LOGIN ESTUDIANTE ================= -->
        <form method="post" class="sign-up-form">
          <h2 class="title">Estudiante</h2>
          <?php if ($error_estudiante): ?>
            <div class="error" style="
              background:#ffe5e5;
              color:#b91c1c;
              border:1px solid #fca5a5;
              border-radius:10px;
              padding:8px 12px;
              margin-bottom:10px;
              font-weight:500;
              text-align:center;">
              <?= htmlspecialchars($error_estudiante) ?>
            </div>
          <?php endif; ?>

          <input type="hidden" name="rol" value="estudiante" />
          
          <div class="input-field">
            <i class="fas fa-id-card"></i>
            <input type="text" name="ci" placeholder="CI" required />
          </div>
          <div class="input-field">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" placeholder="Contraseña" required />
          </div>

          <div style="margin-top: 10px; text-align:center;">
            <a href="../config/password_estudiantes.php" style="color:#4481eb; text-decoration:none; font-size:0.9rem;">
              ¿Olvidaste tu contraseña?
            </a>
          </div>

          <input type="submit" value="Entrar" class="btn" />
        </form>
      </div>
    </div>

    <!-- ================= PANEL LATERAL ================= -->
    <div class="panels-container">
      <div class="panel left-panel">
        <div class="content">
          <h3>¿Eres estudiante?</h3>
          <p>Accede a tu panel de estudiante para gestionar tus préstamos y consultar el inventario.</p>
          <button class="btn transparent" id="sign-up-btn">Ingresar como estudiante</button>
        </div>
      </div>
      <div class="panel right-panel">
        <div class="content">
          <h3>¿Eres docente?</h3>
          <p>Accede a tu panel de docente para gestionar préstamos y el inventario completo.</p>
          <button class="btn transparent" id="sign-in-btn">Ingresar como docente</button>
        </div>
      </div>
    </div>
  </div>

  <script>
const sign_in_btn = document.querySelector("#sign-in-btn");
const sign_up_btn = document.querySelector("#sign-up-btn");
const container = document.querySelector(".container");

sign_up_btn.addEventListener("click", () => {
  container.classList.add("sign-up-mode");
});

sign_in_btn.addEventListener("click", () => {
  container.classList.remove("sign-up-mode");
});
  </script>
</body>
</html>