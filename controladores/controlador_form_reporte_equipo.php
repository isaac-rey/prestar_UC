<?php
include_once "../config/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_usuario_reportador = $_POST['nombre_usuario_reportador'];
    $id_equipo    = intval($_POST['id_equipo']); // mejor asegurar que sea INT
    $fecha_reporte   = $_POST['fecha_reporte'];
    $tipo_fallo  = trim($_POST['tipo_fallo']);
    $descripcion_fallo  = $_POST['descripcion_fallo'];

    // Convierte de YYYY-MM-DD a DD-MM-YYYY
    $fecha_reporte_formateada = date("d-m-Y", strtotime($fecha_reporte));



    $stmt = $mysqli->prepare("INSERT INTO reporte_fallos(fecha, tipo_fallo, descripcion_fallo, id_equipo, nombre_usuario_reportante) VALUES (?,?,?,?,?)");

    if ($stmt === false) {
        die("Error en prepare: " . $mysqli->error);
    }

    // string, string, string, int, string
    $stmt->bind_param("sssis", $fecha_reporte_formateada, $tipo_fallo, $descripcion_fallo, $id_equipo, $nombre_usuario_reportador);

    if ($stmt->execute()) {
        session_start();
        $_SESSION['mensaje'] = "Reporte registrado correctamente";
        $_SESSION['icono'] = "success";
        header("Location: /inventario_uni/public/form_reporte_equipo.php");
    } else {
        session_start();
        $_SESSION['mensaje'] = "No se pudo registrar el reporte";
        $_SESSION['icono'] = "error";
        header("Location: /inventario_uni/public/form_reporte_equipo.php");
    }
}
