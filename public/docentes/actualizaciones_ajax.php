<?php
require __DIR__ . '/docente_init.php';
require_doc_login();
$e = doc();

header('Content-Type: application/json');

// Cesión pendiente actual
$stmt = $mysqli->prepare("
    SELECT c.id, e.nombre AS cedente_nombre, e.apellido AS cedente_apellido,
           CONCAT(eq.tipo,' ',IFNULL(eq.marca,''),' ',IFNULL(eq.modelo,'')) AS equipo_nombre,
           eq.serial_interno AS equipo_serial
    FROM cesiones c
    JOIN docentes e ON c.cedente_id=e.id
    JOIN prestamos p ON c.prestamo_id=p.id
    JOIN equipos eq ON p.equipo_id=eq.id
    WHERE c.a_docente_id=? AND c.estado='pendiente'
    ORDER BY c.fecha_solicitud DESC
    LIMIT 1
");
$stmt->bind_param("i", $e['id']);
$stmt->execute();
$cesion_actual = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Préstamos activos
$stmt = $mysqli->prepare("
    SELECT p.id, p.equipo_id, p.fecha_entrega, p.observacion,
           e.tipo, e.marca, e.modelo, e.serial_interno
    FROM prestamos p
    JOIN equipos e ON e.id = p.equipo_id
    WHERE p.docente_id=? AND p.estado='activo'
    ORDER BY p.fecha_entrega DESC
");
$stmt->bind_param("i", $e['id']);
$stmt->execute();
$activos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'cesiones' => $cesion_actual,
    'prestamos_activos' => $activos
]);
