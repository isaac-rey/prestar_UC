<?php
require __DIR__ . '/docente_init.php';
require_doc_login();

header('Content-Type: application/json');

$term = trim($_GET['q'] ?? '');
if ($term === '') {
    echo json_encode([]);
    exit;
}

// Buscar docentes por nombre, apellido o CI
$stmt = $mysqli->prepare("
    SELECT id, nombre, apellido, ci
    FROM docentes
    WHERE nombre LIKE CONCAT('%', ?, '%')
       OR apellido LIKE CONCAT('%', ?, '%')
       OR ci LIKE CONCAT('%', ?, '%')
    ORDER BY nombre, apellido
    LIMIT 10
");
$stmt->bind_param("sss", $term, $term, $term);
$stmt->execute();
$result = $stmt->get_result();

$docentes = [];
while ($row = $result->fetch_assoc()) {
    $docentes[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre'],
        'apellido' => $row['apellido'],
        'ci' => $row['ci']
    ];
}

echo json_encode($docentes);
