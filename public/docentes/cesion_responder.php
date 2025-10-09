<?php
require __DIR__ . '/docente_init.php';
require_doc_login();
$e = doc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cesion_id = intval($_POST['cesion_id'] ?? 0);
    $accion    = $_POST['accion'] ?? '';

    if (!$cesion_id || !in_array($accion, ['aceptar', 'rechazar'])) {
        die("Solicitud inválida.");
    }

    // Verificar que esta cesión sea realmente para el docente actual
    $stmt = $mysqli->prepare("SELECT * FROM cesiones WHERE id=? AND a_docente_id=? AND estado='pendiente' LIMIT 1");
    $stmt->bind_param("ii", $cesion_id, $e['id']);
    $stmt->execute();
    $cesion = $stmt->get_result()->fetch_assoc();

    if (!$cesion) die("Cesión no encontrada o ya procesada.");

    // Actualizar estado
    $nuevo_estado = ($accion === 'aceptar') ? 'aceptada' : 'rechazada';
    $fecha_confirmacion = date('Y-m-d H:i:s');

    $stmt = $mysqli->prepare("UPDATE cesiones SET estado=?, fecha_confirmacion=? WHERE id=?");
    $stmt->bind_param("ssi", $nuevo_estado, $fecha_confirmacion, $cesion_id);
    $stmt->execute();

    // Si se aceptó, también se debe actualizar el préstamo
    if ($accion === 'aceptar') {
        $stmt = $mysqli->prepare("UPDATE prestamos SET docente_id=? WHERE id=?");
        $stmt->bind_param("ii", $e['id'], $cesion['prestamo_id']);
        $stmt->execute();
    }

    // Redirigir al panel
    header("Location: docente_panel.php");
    exit;
} else {
    die("Método inválido.");
}
