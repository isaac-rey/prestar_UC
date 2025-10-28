<?php
// public/docentes_login.php
session_start();
require __DIR__ . '/../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ci = trim($_POST['ci'] ?? '');
  $pass = $_POST['password'] ?? '';

  if ($ci === '' || $pass === '') {
    $error = 'CI y contraseña son obligatorios.';
  } else {
    $stmt = $mysqli->prepare("SELECT id, ci, nombre, apellido, password_hash FROM docentes WHERE ci=? LIMIT 1");
    $stmt->bind_param('s', $ci);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row && password_verify($pass, $row['password_hash'])) {
      $_SESSION['doc'] = [
        'id' => $row['id'],
        'ci' => $row['ci'],
        'nombre' => $row['nombre'],
        'apellido' => $row['apellido'],
      ];
      header('Location: /prestar_uc/public/docentes/docente_panel.php');
      exit;
    } else {
      $error = 'CI o contraseña incorrectos.';
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Login docente — Inventario</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
  <div class="card">
    <h1>Ingreso de docente</h1>
    <?php if ($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <label>CI</label>
      <input name="ci" required>
      <label>Contraseña</label>
      <input name="password" type="password" required>
      <button type="submit">Entrar</button>
    </form>
    <div class="muted" style="margin-top:12px;">
       <p><a href="../config/password_estudiantes.php">¿Olvidaste tu contraseña?</a></p>
    </div>
  </div>
</body>
</html>
