<?php
session_start();
require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

// Verificar autenticación
if (empty($_SESSION['est'])) {
  echo json_encode(['changed' => false, 'error' => 'No autenticado']);
  exit;
}

// Validar parámetros
$equipo_id = filter_input(INPUT_GET, 'equipo_id', FILTER_VALIDATE_INT);
$estudiante_id = filter_input(INPUT_GET, 'estudiante_id', FILTER_VALIDATE_INT);

if (!$equipo_id || !$estudiante_id) {
  echo json_encode(['changed' => false, 'error' => 'Parámetros inválidos']);
  exit;
}

// Buscar préstamo activo o pendiente
$stmt = $mysqli->prepare("
    SELECT p.id, p.estado, p.estudiante_id, p.docente_id,
           UNIX_TIMESTAMP(p.actualizado_en) AS last_update
    FROM prestamos p
    WHERE p.equipo_id = ? AND p.estado IN ('activo','pendiente')
    ORDER BY p.fecha_entrega DESC
    LIMIT 1
");
$stmt->bind_param("i", $equipo_id);
$stmt->execute();
$result = $stmt->get_result();
$prestamo = $result->fetch_assoc();

$response = [
  'changed' => false,
  'prestamo' => $prestamo,
  'estudiante_tiene' => false
];

$session_key = "prestamo_check_$equipo_id";
$last_check = $_SESSION[$session_key] ?? null;

if ($prestamo) {
  $response['estudiante_tiene'] = ($prestamo['estudiante_id'] == $estudiante_id);

  $current_state = json_encode([
    'id' => $prestamo['id'],
    'estado' => $prestamo['estado'],
    'last_update' => $prestamo['last_update']
  ]);

  if ($last_check !== $current_state) {
    $response['changed'] = true;
    $_SESSION[$session_key] = $current_state;
  }
} else {
  if ($last_check !== null) $response['changed'] = true;
  $_SESSION[$session_key] = null;
}

$stmt->close();
$mysqli->close();

echo json_encode($response);
