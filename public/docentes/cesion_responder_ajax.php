<?php
require __DIR__ . '/docente_init.php';
require_doc_login();
$e = doc(); // docente actual

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

// Verificar cesión pendiente
$stmt = $mysqli->prepare("SELECT * FROM cesiones WHERE id=? AND a_docente_id=? AND estado='pendiente' LIMIT 1");
$stmt->bind_param("ii", $cesion_id, $e['id']);
$stmt->execute();
$cesion = $stmt->get_result()->fetch_assoc();

if (!$cesion) {
    echo json_encode(['success' => false, 'message' => 'Cesión no encontrada o ya procesada']);
    exit;
}

// Actualizar estado de la cesión
$nuevo_estado = ($accion === 'aceptar') ? 'aceptada' : 'rechazada';
$fecha_confirmacion = date('Y-m-d H:i:s');

$stmt = $mysqli->prepare("UPDATE cesiones SET estado=?, fecha_confirmacion=? WHERE id=?");
$stmt->bind_param("ssi", $nuevo_estado, $fecha_confirmacion, $cesion_id);
$stmt->execute();

// Si se aceptó, transferir préstamo al nuevo docente y registrar historial
if ($accion === 'aceptar') {
    // Registrar historial de cesión
    $stmt = $mysqli->prepare("
        INSERT INTO historial_cesiones (prestamo_id, de_docente_id, a_docente_id, observacion, fecha)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iiis", $cesion['prestamo_id'], $cesion['cedente_id'], $e['id'], $cesion['observacion']);
    $stmt->execute();

    // Actualizar docente_id y usuario_actual_id del préstamo al nuevo docente
    $stmt = $mysqli->prepare("UPDATE prestamos SET docente_id=?, usuario_actual_id=? WHERE id=?");
    $stmt->bind_param("iii", $e['id'], $e['id'], $cesion['prestamo_id']);
    $stmt->execute();
}

// Obtener préstamos activos actualizados (donde usuario_actual = docente)
$stmt = $mysqli->prepare("
    SELECT p.id, p.equipo_id, p.fecha_entrega, p.observacion,
           e.tipo, e.marca, e.modelo, e.serial_interno
    FROM prestamos p
    JOIN equipos e ON e.id = p.equipo_id
    WHERE p.usuario_actual_id=? AND p.estado='activo'
    ORDER BY p.fecha_entrega DESC
");
$stmt->bind_param("i", $e['id']);
$stmt->execute();
$activos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener cesiones pendientes actualizadas
$stmt = $mysqli->prepare("
    SELECT c.*, d.nombre AS cedente_nombre, d.apellido AS cedente_apellido,
           eq.tipo AS equipo_tipo, eq.marca AS equipo_marca, eq.modelo AS equipo_modelo, eq.serial_interno AS equipo_serial
    FROM cesiones c
    JOIN docentes d ON d.id=c.cedente_id
    JOIN prestamos p ON p.id=c.prestamo_id
    JOIN equipos eq ON eq.id=p.equipo_id
    WHERE c.a_docente_id=? AND c.estado='pendiente'
    ORDER BY c.fecha_solicitud DESC
");
$stmt->bind_param("i", $e['id']);
$stmt->execute();
$cesiones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'message' => ($accion === 'aceptar') ? 'Cesión aceptada' : 'Cesión rechazada',
    'prestamos_activos' => $activos,
    'cesiones' => $cesiones
]);
