<?php
require __DIR__ . '/docente_init.php';
require_doc_login();
$e = doc(); // docente actual (quien acepta)

// ⭐ INCLUSIÓN DE LA FUNCIÓN AUDITAR
require __DIR__ . '/../../../inventario_uni/init.php'; 

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

// ⭐ PASO 1: OBTENER DATOS DEL DOCENTE CEDENTE (QUIEN ENVIÓ)
$stmt_cedente = $mysqli->prepare("SELECT nombre, apellido, ci FROM docentes WHERE id=? LIMIT 1");
$stmt_cedente->bind_param("i", $cesion['cedente_id']);
$stmt_cedente->execute();
$cedente = $stmt_cedente->get_result()->fetch_assoc();
$nombre_cedente = $cedente ? htmlspecialchars("{$cedente['nombre']} {$cedente['apellido']} (CI: {$cedente['ci']})") : 'Docente Desconocido';


// ⭐ PASO 2: OBTENER DATOS DEL EQUIPO USANDO prestamo_id
$stmt_equipo = $mysqli->prepare("
    SELECT e.tipo, e.marca, e.modelo, e.serial_interno 
    FROM prestamos p
    JOIN equipos e ON e.id = p.equipo_id
    WHERE p.id=? LIMIT 1
");
$stmt_equipo->bind_param("i", $cesion['prestamo_id']);
$stmt_equipo->execute();
$equipo_data = $stmt_equipo->get_result()->fetch_assoc();

$equipo_desc = 'Equipo Desconocido';
if ($equipo_data) {
    $equipo_desc = htmlspecialchars(
        "{$equipo_data['tipo']} {$equipo_data['marca']} {$equipo_data['modelo']} (Serial: {$equipo_data['serial_interno']})"
    );
}


// Actualizar estado de la cesión
$nuevo_estado = ($accion === 'aceptar') ? 'aceptada' : 'rechazada';
$fecha_confirmacion = date('Y-m-d H:i:s');

$stmt = $mysqli->prepare("UPDATE cesiones SET estado=?, fecha_confirmacion=? WHERE id=?");
$stmt->bind_param("ssi", $nuevo_estado, $fecha_confirmacion, $cesion_id);
$stmt->execute();

// Si se aceptó, transferir préstamo y auditar
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

    // ⭐ AUDITORÍA: CESIÓN ACEPTADA (Mensaje detallado con cedente y equipo)
    $nombre_acepta = htmlspecialchars("{$e['nombre']} {$e['apellido']} (CI: {$e['ci']})");
    $prestamo_id = $cesion['prestamo_id'];

    $descripcion = "Cedió el equipo '{$equipo_desc}' al Docente {$nombre_acepta}";
    
    // ⭐⭐ CAMBIO CLAVE AQUÍ: Usar el ID del docente cedente (el que tenía el equipo) como actor
    auditar($descripcion, 'cesión_docentes', $cesion['cedente_id']); 

} 
// NOTA: No hay auditoría para el caso 'rechazar'.


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