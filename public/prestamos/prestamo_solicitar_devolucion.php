<?php
require __DIR__ . '/../../init.php';
require_login();

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
if(!$id) { echo json_encode(['error'=>'ID no válido']); exit; }

$docente_id = $_SESSION['user_id'] ?? 0;
if(!$docente_id) { echo json_encode(['error'=>'Docente no identificado']); exit; }

// Verificar préstamo activo del docente actual
$stmt = $mysqli->prepare("SELECT id, equipo_id FROM prestamos WHERE id=? AND usuario_actual_id=? AND estado='activo'");
$stmt->bind_param("ii",$id,$docente_id);
$stmt->execute();
$prestamo = $stmt->get_result()->fetch_assoc();
if(!$prestamo){ echo json_encode(['error'=>'No hay préstamo activo para este docente']); exit; }

// Cambiar estado a pendiente_devolucion
$stmt = $mysqli->prepare("UPDATE prestamos SET estado='pendiente_devolucion' WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();

echo json_encode(['ok'=>'Solicitud de devolución enviada al administrador']);
