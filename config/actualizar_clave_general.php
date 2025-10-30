<?php
// actualizar_clave_general.php

// ⚠️ CORRECCIÓN DE RUTA: Asumiendo que 'db.php' está en el mismo directorio.
require_once 'db.php';

// ... (Resto de la validación de POST y token) ...

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if ($password !== $confirm_password || strlen($password) < 8) {
    die('Error: Las contraseñas no coinciden o son demasiado cortas (mínimo 8 caracteres).');
}
// ... (Validación de token que obtiene $resetData) ...

// Buscar token válido
$stmt = $mysqli->prepare("
    SELECT pr.id, pr.user_id, pr.table_name
    FROM password_resets pr
    WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die('Error: Enlace de restablecimiento inválido o expirado.');
}

$resetData = $result->fetch_assoc();
$resetId = $resetData['id'];
$userId = $resetData['user_id'];
$tableName = $resetData['table_name']; // Contiene 'docentes' o 'estudiantes'

$stmt->close();

// ----------------------------------------------------
// 3. Hashear Contraseña
// ----------------------------------------------------
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// ----------------------------------------------------
// 4. Actualizar Contraseña en la tabla correcta (CORRECCIÓN FINAL)
// ----------------------------------------------------
$success = false;
$updateStmt = null;

// Columna de contraseña estandarizada: 'password_hash'
// Columna de ID estandarizada: 'id'
if ($tableName === 'docentes') {
    $updateStmt = $mysqli->prepare("UPDATE docentes SET password_hash = ? WHERE id = ?");
    $updateStmt->bind_param("si", $hashedPassword, $userId);
} elseif ($tableName === 'estudiantes') {
    $updateStmt = $mysqli->prepare("UPDATE estudiantes SET password_hash = ? WHERE id = ?");
    $updateStmt->bind_param("si", $hashedPassword, $userId);
} else {
    die('Error interno: Tipo de usuario no reconocido en el token.');
}

if ($updateStmt && $updateStmt->execute()) {
    $success = true;
    $updateStmt->close();
}

// ----------------------------------------------------
// 5. Marcar Token como Usado y Redirigir
// ----------------------------------------------------
if ($success) {
    $markUsedStmt = $mysqli->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
    $markUsedStmt->bind_param("i", $resetId);
    $markUsedStmt->execute();
    $markUsedStmt->close();

    // ⚠️ Ajusta esta ruta a tu login de Docentes/Estudiantes
    header('Location: ../auth/login.php?status=clave_actualizada'); 
    exit;
} else {
    die('Error: No se pudo actualizar la contraseña en la base de datos.');
}

$mysqli->close();
?>