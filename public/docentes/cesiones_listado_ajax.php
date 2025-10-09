<?php
require __DIR__ . '/docente_init.php';
require_doc_login();
$e = doc();

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
");
$stmt->bind_param("i",$e['id']);
$stmt->execute();
$cesiones = $stmt->get_result();

if($cesiones->num_rows===0){
    echo '<p class="muted">No hay solicitudes pendientes.</p>';
}else{
    echo '<ul>';
    while($c=$cesiones->fetch_assoc()){
        echo '<li><strong>'.htmlspecialchars($c['cedente_nombre'].' '.$c['cedente_apellido']).'</strong> quiere cederte <em>'.htmlspecialchars($c['equipo_nombre']).'</em> (Serial: '.htmlspecialchars($c['equipo_serial']).') ';
        echo '<button onclick="responderCesion('.$c['id'].',\'aceptar\')">Aceptar</button> ';
        echo '<button onclick="responderCesion('.$c['id'].',\'rechazar\')">Rechazar</button></li>';
    }
    echo '</ul>';
}
