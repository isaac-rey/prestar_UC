<?php
require __DIR__ . '/estudiante_init.php';
require_est_login();
$e = est();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$cesion_id = intval($_POST['cesion_id'] ?? 0);
$accion    = $_POST['accion'] ?? '';

if (!$cesion_id || !in_array($accion, ['aceptar', 'rechazar'])) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

// Verificar cesión pendiente para el estudiante
$stmt = $mysqli->prepare("SELECT * FROM cesiones WHERE id=? AND a_estudiante_id=? AND estado='pendiente' LIMIT 1");
$stmt->bind_param("ii", $cesion_id, $e['id']);
$stmt->execute();
$cesion = $stmt->get_result()->fetch_assoc();

if (!$cesion) {
    echo json_encode(['success' => false, 'message' => 'Cesión no encontrada o ya procesada']);
    exit;
}

// Actualizar estado
$nuevo_estado = ($accion === 'aceptar') ? 'aceptada' : 'rechazada';
$fecha_confirmacion = date('Y-m-d H:i:s');

$stmt = $mysqli->prepare("UPDATE cesiones SET estado=?, fecha_confirmacion=? WHERE id=?");
$stmt->bind_param("ssi", $nuevo_estado, $fecha_confirmacion, $cesion_id);
$stmt->execute();

// Si se aceptó, transferir préstamo
if ($accion === 'aceptar') {
    $stmt = $mysqli->prepare("UPDATE prestamos SET estudiante_id=? WHERE id=?");
    $stmt->bind_param("ii", $e['id'], $cesion['prestamo_id']);
    $stmt->execute();
}

echo json_encode(['success' => true, 'message' => ($accion === 'aceptar') ? 'Cesión aceptada' : 'Cesión rechazada']);
