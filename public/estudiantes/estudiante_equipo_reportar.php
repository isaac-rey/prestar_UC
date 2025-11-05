<?php


require __DIR__ . '/estudiante_init.php';
require_est_login();
$e = est();
$ok = false;
$error = '';



$serial = trim($_GET['serial'] ?? '');
if ($serial === '') die("Serial no especificado.");



// Buscamos el id del equipo a partir del serial recibido por GET
$id_equipo_get = 0;
if ($serial !== '') {
    $stmt = $mysqli->prepare("SELECT id FROM equipos WHERE serial_interno = ?");
    $stmt->bind_param("s", $serial);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $id_equipo_get = (int)$row['id'];
    }
    $stmt->close();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre_usuario_reportador = trim($_POST['nombre_usuario_reportador'] ?? '');
  $id_equipo    = intval($_POST['id_equipo'] ?? 0);
  $fecha_reporte   = $_POST['fecha_reporte'] ?? '';
  $tipo_fallo  = trim($_POST['tipo_fallo'] ?? '');
  $descripcion_fallo  = trim($_POST['descripcion_fallo'] ?? '');
  $reporte_verificar = 0;

  if ($nombre_usuario_reportador === '' || $id_equipo === 0 || $fecha_reporte === '' || $tipo_fallo === '' || $descripcion_fallo === '') {
    $error = 'Todos los campos son obligatorios.';
  } else {
    // Convierte de YYYY-MM-DD a DD-MM-YYYY
    $fecha_reporte_formateada = date("d-m-Y", strtotime($fecha_reporte));

    $stmt = $mysqli->prepare("
          INSERT INTO reporte_fallos(fecha, tipo_fallo, descripcion_fallo, id_equipo, nombre_usuario_reportante, reporte_verificar)
          VALUES (?,?,?,?,?,?)
        ");
    if ($stmt === false) {
      $error = "Error en prepare: " . $mysqli->error;
    } else {
      $stmt->bind_param("sssisi", $fecha_reporte_formateada, $tipo_fallo, $descripcion_fallo, $id_equipo, $nombre_usuario_reportador, $reporte_verificar);
      if ($stmt->execute()) {
        $mysqli->query("UPDATE equipos SET con_reporte = 1 WHERE id = $id_equipo");
        //----------------------------------insersion de la auditoria----------------------------
        // 2. RECUPERAR DATOS DEL EQUIPO PARA LA AUDITORÍA (¡PASO CLAVE!)
        $stmt_audit = $mysqli->prepare("SELECT tipo, marca, modelo FROM equipos WHERE id = ?");
        $stmt_audit->bind_param("i", $id_equipo);
        $stmt_audit->execute();
        $equipo_data = $stmt_audit->get_result()->fetch_assoc();

        if ($equipo_data) {
          // 3. Insertar Auditoría
          $equipo_desc = $equipo_data['tipo'] . ' ' . $equipo_data['marca'] . ' ' . $equipo_data['modelo'];
          $reporte_desc = "Fallo: {$tipo_fallo}.";

          // CLAVE: Añadir el tipo de acción 'reporte'
          auditar("El/la alumno/a reportó un fallo para el equipo ID {$id_equipo} ({$equipo_desc}). {$reporte_desc}", 'reporte');
        }

        $ok = true;
      } else {
        $error = "No se pudo registrar el reporte.";
      }
    }
  }
}
function auditar($accion, $tipo_accion = 'general', $override_user_id = null) // <-- 1. ACEPTAR NUEVO PARÁMETRO
{
 global $mysqli;
 
 $usuario_id = null;

 // Prioridad 1: Usar el ID pasado como override (ideal para forgot_password)
 if ($override_user_id !== null) {
 $usuario_id = intval($override_user_id);
 }
 // Prioridad 2: Usar el ID del usuario logueado
 else {
 $usuario = est();
 if ($usuario && isset($usuario['id'])) {
 $usuario_id = $usuario['id'];
 }
 }
 // 2. Manejo de usuario desconocido/no logueado
 if ($usuario_id === null) {
 // Asumiendo que 0 es un ID reservado para el sistema/invitado. 
 $usuario_id = 0; // ID para "Anónimo" o "Sistema"
 }
 /*********************************** */

 $ip = $_SERVER['REMOTE_ADDR'] ?? null;
 $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

 // 3. Modificar la consulta SQL para incluir tipo_accion
 $stmt = $mysqli->prepare("
        INSERT INTO auditoria 
        (usuario_id, accion, tipo_accion, ip_usuario, user_agent, fecha) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");


 if ($stmt === false) {
 error_log("Error al preparar la auditoría: " . $mysqli->error);
 return;
 }

  // 4. Modificar el bind_param
  // Original: "isss" (usuario_id, accion, ip, user_agent)
  // Nuevo: "issss" (usuario_id, accion, tipo_accion, ip, user_agent)
 $stmt->bind_param("issss", $usuario_id, $accion, $tipo_accion, $ip, $user_agent); // <-- CLAVE: 's' para tipo_accion

 if (!$stmt->execute()) {
 error_log("Error al ejecutar la auditoría: " . $stmt->error);
 }
 $stmt->close();
}

// Obtener info del equipo
$equipo_info = null;
if ($id_equipo_get) {
  $stmt = $mysqli->prepare("SELECT * FROM equipos WHERE id = ?");
  $stmt->bind_param("i", $id_equipo_get);
  $stmt->execute();
  $res = $stmt->get_result();
  $equipo_info = $res->fetch_assoc();
}
?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <title>Reporte de fallos de equipos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="estudiante_styles.css">
</head>

<body>
  <header>
    <a href="/prestar_UC/public/estudiantes/estudiante_panel.php">Inventario – Estudiante</a>
    <div>
        <button id="theme-toggle" class="btn-secondary btn-sm">🌙</button>
        <?= htmlspecialchars($e['nombre'].' '.$e['apellido']) ?> · 
        <a href="/prestar_UC/auth/logout_docente.php">Salir</a>
    </div>
</header>

  <div class="container">
    <div class="card">
      <h2>Registrar fallo de equipo</h2>

      <?php if ($ok): ?>
        <div class="ok">✔ Reporte registrado correctamente.</div>
        <div class="muted"><a href="/prestar_UC/public/estudiantes/estudiante_panel.php">Volver al inicio</a></div>
      <?php else: ?>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php if ($equipo_info): ?>
          <p class="muted">
            <strong>Equipo:</strong> <?= htmlspecialchars($equipo_info['tipo']) ?> —
            <?= htmlspecialchars($equipo_info['marca'] . " " . $equipo_info['modelo']) ?><br>
            <strong>Serial:</strong> <?= htmlspecialchars($equipo_info['serial_interno']) ?>
          </p>
        <?php else: ?>
          <p class="muted">Equipo no seleccionado.</p>
        <?php endif; ?>

        <form method="post" autocomplete="off">
          <input type="hidden" name="nombre_usuario_reportador" value="<?= htmlspecialchars($e['nombre'].' '.$e['apellido'])  ?>">
          <input type="hidden" name="id_equipo" value="<?= $id_equipo_get ?>">
          <br>

          <label for="fecha_reporte">Fecha del reporte *</label>
          <input id="fecha_reporte" type="date" name="fecha_reporte" required>

          <label for="tipo_fallo">Tipo de fallo *</label>
          <input id="tipo_fallo" type="text" name="tipo_fallo" placeholder="Ej.: Daño físico, software, etc." required>

          <label for="descripcion_fallo">Descripción del fallo *</label>
          <textarea id="descripcion_fallo" name="descripcion_fallo" required style="height:180px;" placeholder="Describa detalladamente el problema"></textarea>

          <button type="submit" class="primary">Registrar reporte</button>
          <button type="reset" class="secondary">Vaciar formulario</button>
        </form>

        <p class="muted" style="margin-top:10px">
          Todos los campos marcados con * son obligatorios.
        </p>
      <?php endif; ?>
    </div>
  </div>


  <script>

    document.addEventListener('DOMContentLoaded',()=>{
    const body=document.body,toggle=document.getElementById('theme-toggle');
    const stored=localStorage.getItem('theme');
    const prefersDark=window.matchMedia('(prefers-color-scheme: dark)').matches;
    let current=stored||(prefersDark?'dark':'light');
    const apply=t=>{
        if(t==='light'){body.classList.add('light-mode');toggle.innerHTML='🌙';}
        else{body.classList.remove('light-mode');toggle.innerHTML='☀️';}
        localStorage.setItem('theme',t);current=t;
    };
    apply(current);
    toggle.addEventListener('click',()=>apply(current==='dark'?'light':'dark'));
    actualizar();
    setInterval(actualizar,2000);
});
  </script>
</body>

</html>