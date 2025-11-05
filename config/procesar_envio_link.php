<?php
// procesar_envio_link.php

use PHPMailer\PHPMailer\Exception;
// ⚠️ CORRECCIÓN DE RUTA: Asumiendo que 'db.php' y 'mail.php' están en el mismo directorio.
require_once 'db.php';   
require_once 'mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Si no es POST, redirige al formulario de solicitud
    header('Location: solicitar_reset_ci.php');
    exit;
}

// ----------------------------------------------------
// 1. Obtener y sanitizar la CI
// ----------------------------------------------------
$ci = trim($_POST['ci'] ?? '');

if (empty($ci)) {
    header('Location: solicitar_reset_ci.php?error=vacio');
    exit;
}

// ----------------------------------------------------
// 2. Búsqueda UNIFICADA: Docente o Estudiante
// ----------------------------------------------------
$user = null;
$tableName = null;
$idColumn = 'id'; // Columna ID estandarizada

// 2.1. Intentar buscar como DOCENTE
// Columnas: id, ci, email, password_hash
$stmtDocente = $mysqli->prepare("SELECT id, ci, email FROM docentes WHERE ci = ?");
$stmtDocente->bind_param("s", $ci);
$stmtDocente->execute();
$resultDocente = $stmtDocente->get_result();

if ($resultDocente->num_rows === 1) {
    $user = $resultDocente->fetch_assoc();
    $tableName = 'docentes';
} else {
    // 2.2. Intentar buscar como ESTUDIANTE
    // Columnas: id, ci, email, password_hash
    $stmtEstudiante = $mysqli->prepare("SELECT id, ci, email FROM estudiantes WHERE ci = ?");
    $stmtEstudiante->bind_param("s", $ci);
    $stmtEstudiante->execute();
    $resultEstudiante = $stmtEstudiante->get_result();

    if ($resultEstudiante->num_rows === 1) {
        $user = $resultEstudiante->fetch_assoc();
        $tableName = 'estudiantes';
    }
}

if ($user === null) {
    // Usuario no encontrado en ninguna tabla
    header('Location: solicitar_reset_ci.php?error=noexiste');
    exit;
}

// ----------------------------------------------------
// 3. Generar Token y URL
// ----------------------------------------------------
$token = bin2hex(random_bytes(32));
$expiry = date("Y-m-d H:i:s", time() + 1800); // 30 minutos

// Generación de URL dinámica (más robusta)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
// Asegura que la ruta apunte al script de reseteo
$path = str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['PHP_SELF']);
$resetLink = $protocol . "://" . $host . $path . "formulario_nueva_clave.php?token=" . $token;

$userId = $user[$idColumn];
$userEmail = $user['email']; // Columna 'email' estandarizada

// ----------------------------------------------------
// 4. Guardar Token en password_resets
// ----------------------------------------------------
// Guardar 'table_name' para saber dónde actualizar la clave después
$stmtInsert = $mysqli->prepare("INSERT INTO password_resets (user_id, token, expires_at, table_name) VALUES (?, ?, ?, ?)");
$stmtInsert->bind_param("isss", $userId, $token, $expiry, $tableName);

if (!$stmtInsert->execute()) {
    error_log("Error al guardar token: " . $stmtInsert->error);
    header('Location: solicitar_reset_ci.php?error=dberror');
    exit;
}

// ----------------------------------------------------
// 5. Enviar Correo Electrónico (PHPMailer)
// ----------------------------------------------------
$mail = getMailer(); 
try {
    $mail->setFrom('tucorreo@ejemplo.com', 'Sistema Inventario UNI');
    $mail->addAddress($userEmail);
    $mail->Subject = 'Restablecer su Contraseña - Sistema UNI';
    $mail->Body    = "Hola, para restablecer tu contraseña, haz clic en el siguiente enlace: <a href='$resetLink'>$resetLink</a>. Este enlace expira en 30 minutos.";
    $mail->isHTML(true);

    $mail->send();
    header('Location: solicitar_reset_ci.php?success=enviado');

} catch (Exception $e) {
    error_log("Error al enviar correo: " . $mail->ErrorInfo);
    header('Location: solicitar_reset_ci.php?error=mailerror');
} finally {
    $mysqli->close();
    exit;
}

?>