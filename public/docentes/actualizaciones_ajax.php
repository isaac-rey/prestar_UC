<?php
require __DIR__ . '/docente_init.php';
require_doc_login();
$e = doc();

header('Content-Type: application/json');

// === CESIONES PENDIENTES ===
$stmt = $mysqli->prepare("
 SELECT 
 c.id,
 d.nombre AS cedente_nombre,
 d.apellido AS cedente_apellido,
 eq.tipo AS equipo_tipo, 
eq.marca AS equipo_marca, 
 eq.modelo AS equipo_modelo, 
 eq.serial_interno AS equipo_serial
FROM cesiones c
JOIN docentes d ON c.cedente_id = d.id
JOIN prestamos p ON c.prestamo_id = p.id
 JOIN equipos eq ON p.equipo_id = eq.id
WHERE c.a_docente_id = ? 
AND c.estado = 'pendiente'
AND p.estado = 'activo'
ORDER BY c.fecha_solicitud DESC
");
$stmt->bind_param("i", $e['id']);
$stmt->execute();
$cesiones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// === PRÉSTAMOS ACTIVOS ===
$stmt = $mysqli->prepare("
    SELECT 
        p.id,
        p.equipo_id,
        p.fecha_entrega,
        p.observacion,
        e.tipo,
        e.marca,
        e.modelo,
        e.serial_interno
    FROM prestamos p
    JOIN equipos e ON e.id = p.equipo_id
    WHERE p.usuario_actual_id = ? 
      AND p.estado = 'activo'
    ORDER BY p.fecha_entrega DESC
");
$stmt->bind_param("i", $e['id']);
$stmt->execute();
$activos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// === SALIDA JSON ===
echo json_encode([
    'cesiones' => $cesiones,
    'prestamos_activos' => $activos
]);
