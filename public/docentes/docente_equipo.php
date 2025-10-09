<?php
require __DIR__ . '/docente_init.php';
require_doc_login();
$e = doc(); // docente actual

$serial = trim($_GET['serial'] ?? '');
if ($serial === '') die("Serial no especificado.");

// Traer equipo por serial + área y sala
$stmt = $mysqli->prepare("
    SELECT e.*, a.nombre AS area, s.nombre AS sala
    FROM equipos e
    JOIN areas a ON a.id = e.area_id
    LEFT JOIN salas s ON s.id = e.sala_id
    WHERE e.serial_interno=? LIMIT 1
");
$stmt->bind_param("s", $serial);
$stmt->execute();
$equipo = $stmt->get_result()->fetch_assoc();
if (!$equipo) die("Equipo no encontrado.");
$equipo_id = intval($equipo['id']);

// Buscar préstamo activo o pendiente (docente o estudiante)
$stmt = $mysqli->prepare("
    SELECT p.*,
           d.id AS d_id, est.id AS e_id, p.usuario_actual_id
    FROM prestamos p
    LEFT JOIN docentes d ON d.id = p.docente_id
    LEFT JOIN estudiantes est ON est.id = p.estudiante_id
    WHERE p.equipo_id=? AND p.estado IN ('activo','pendiente','pendiente_devolucion')
    ORDER BY p.fecha_entrega DESC
    LIMIT 1
");
$stmt->bind_param("i", $equipo_id);
$stmt->execute();
$prestamo_act = $stmt->get_result()->fetch_assoc();

$yo_lo_tengo = $prestamo_act && intval($prestamo_act['usuario_actual_id']) === intval($e['id']);

// Componentes del equipo
$stmt = $mysqli->prepare("SELECT * FROM componentes WHERE equipo_id=? ORDER BY creado_en DESC");
$stmt->bind_param("i", $equipo_id);
$stmt->execute();
$componentes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Lista de otros docentes para cesión
$otros_docentes = [];
if ($yo_lo_tengo && $prestamo_act['estado'] === 'activo') {
    $res = $mysqli->query("SELECT id, nombre, apellido, ci FROM docentes WHERE id<>".$e['id']." ORDER BY nombre, apellido");
    $otros_docentes = $res->fetch_all(MYSQLI_ASSOC);
}

// Cesiones recibidas
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

$error = '';
$ok = '';

// Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'solicitar') {
        $obs = trim($_POST['observacion'] ?? '');
        if ($prestamo_act) {
            $error = "Ya existe una solicitud o préstamo activo para este equipo.";
        } else {
            $stmt = $mysqli->prepare("
                INSERT INTO prestamos (equipo_id, docente_id, observacion, estado)
                VALUES (?,?,?, 'pendiente')
            ");
            $stmt->bind_param("iis", $equipo_id, $e['id'], $obs);
            $stmt->execute();
            $ok = "Solicitud enviada. Esperando aprobación.";
        }

    } elseif ($accion === 'devolver') {
        if (!$prestamo_act || $prestamo_act['estado'] !== 'activo') {
            $error = "No hay préstamo activo para este equipo.";
        } elseif (!$yo_lo_tengo) {
            $error = "Solo quien tiene el préstamo puede solicitar la devolución.";
        } else {
            $stmt = $mysqli->prepare("UPDATE prestamos SET estado='pendiente_devolucion' WHERE id=? LIMIT 1");
            $stmt->bind_param("i", $prestamo_act['id']);
            $stmt->execute();
            $ok = "Solicitud de devolución enviada. Esperando aprobación.";
        }

    } elseif ($accion === 'ceder') {
        $a_docente_id = intval($_POST['a_docente_id'] ?? 0);
        if ($a_docente_id && $yo_lo_tengo && $prestamo_act['estado'] === 'activo') {
            $stmt = $mysqli->prepare("
                INSERT INTO cesiones (prestamo_id, cedente_id, a_docente_id, estado, fecha_solicitud)
                VALUES (?,?,?, 'pendiente', NOW())
            ");
            $stmt->bind_param("iii", $prestamo_act['id'], $e['id'], $a_docente_id);
            $stmt->execute();
            $ok = "Solicitud de cesión enviada al docente seleccionado.";

            // Refrescar cesiones
            $stmt = $mysqli->prepare("
                SELECT c.*, d.nombre AS cedente_nombre, d.apellido AS cedente_apellido,
                       e.tipo AS equipo_tipo, e.marca AS equipo_marca, e.modelo AS equipo_modelo, e.serial_interno AS equipo_serial
                FROM cesiones c
                JOIN docentes d ON d.id=c.cedente_id
                JOIN prestamos p ON p.id=c.prestamo_id
                JOIN equipos e ON e.id=p.equipo_id
                WHERE c.a_docente_id=? AND c.estado='pendiente'
                ORDER BY c.fecha_solicitud DESC
            ");
            $stmt->bind_param("i", $e['id']);
            $stmt->execute();
            $cesiones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $error = "Datos inválidos o no tienes un préstamo activo para ceder.";
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Equipo — Docente</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../css/tabla_prestamo_index.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#0f172a;color:#e2e8f0;margin:0}
header{display:flex;justify-content:space-between;align-items:center;padding:16px;background:#111827}
a{color:#93c5fd;text-decoration:none}
.container{padding:24px;max-width:1000px;margin:auto}
.grid{display:grid;gap:24px;grid-template-columns:1.2fr .8fr}
.card{background:#111827;border:1px solid #1f2937;border-radius:12px;padding:16px}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #1f2937;text-align:left}
th{color:#93c5fd;background:#0b1220}
.badge{padding:4px 8px;border-radius:9999px;font-size:12px}
.ok{background:#052e16;color:#bbf7d0}
.warn{background:#1f2937;color:#fef08a}
.bad{background:#450a0a;color:#fecaca}
input,textarea,select{width:100%;padding:10px;border-radius:8px;border:1px solid #374151;background:#0b1220;color:#e5e7eb;margin-top:4px;margin-bottom:8px}
button{padding:10px 14px;border:0;border-radius:8px;background:#2563eb;color:#fff;cursor:pointer;font-weight:600}
.muted{color:#9ca3af}
.error{background:#7f1d1d;color:#fecaca;padding:10px;border-radius:8px;margin-bottom:12px}
.okmsg{background:#052e16;color:#bbf7d0;padding:10px;border-radius:8px;margin-bottom:12px}
</style>
</head>
<body>

<div class="container">
<div class="grid">

<div class="card">
    <h2><?= htmlspecialchars($equipo['tipo'].' · '.$equipo['marca'].' '.$equipo['modelo']) ?></h2>
    <p class="muted">Área: <?= htmlspecialchars($equipo['area']) ?><?= $equipo['sala'] ? ' / '.htmlspecialchars($equipo['sala']) : '' ?></p>
    <p class="muted">Serial: <?= htmlspecialchars($equipo['serial_interno']) ?></p>

    <p>
        Estado:
        <?php
        $cls = 'ok';
        if($equipo['estado']==='dañado'||$equipo['estado']==='fuera_servicio') $cls='bad';
        elseif($equipo['estado']==='en_uso') $cls='warn';
        ?>
        <span class="badge <?= $cls ?>"><?= htmlspecialchars($equipo['estado']) ?></span>
        · Prestado: <?= $equipo['prestado'] ? 'Sí' : 'No' ?>
    </p>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="okmsg"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

    <?php if ($prestamo_act && !$yo_lo_tengo): ?>
        <?php if ($prestamo_act['estado']=='pendiente'): ?>
            <p class="muted">Este equipo ha sido solicitado por otro usuario y aún no fue aprobado.</p>
        <?php elseif ($prestamo_act['estado']=='pendiente_devolucion'): ?>
            <p class="muted">Este equipo tiene una solicitud de devolución pendiente de aprobación.</p>
        <?php else: ?>
            <p class="muted">Este equipo está actualmente prestado por otro usuario.</p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$prestamo_act): ?>
        <form method="post" onsubmit="return confirm('¿Confirmar solicitud de este equipo?');">
            <input type="hidden" name="accion" value="solicitar">
            <label>Destino / Observación (opcional)</label>
            <textarea name="observacion" placeholder="Ej.: Sala 203, uso de clase, etc."></textarea>
            <button type="submit">Solicitar préstamo</button>
        </form>
    <?php elseif ($yo_lo_tengo && $prestamo_act['estado']=='activo'): ?>
        <form method="post" onsubmit="return confirm('¿Enviar solicitud de devolución?');" style="margin-top:10px">
            <input type="hidden" name="accion" value="devolver">
            <button type="submit">Solicitar devolución</button>
        </form>

        <?php if ($otros_docentes): ?>
            <div style="margin-top:12px">
                <h4>Ceder préstamo a otro docente</h4>
                <form method="post" onsubmit="return confirm('¿Confirmar cesión del préstamo?');">
                    <input type="hidden" name="accion" value="ceder">
                    <input type="text" id="buscar_docente" placeholder="Buscar por nombre o CI..." autocomplete="off">
                    <input type="hidden" name="a_docente_id" id="a_docente_id" required>
                    <div id="resultados_busqueda" style="background:#1e293b;border-radius:8px;margin-top:4px;"></div>
                    <button type="submit" style="margin-top:8px">Enviar cesión</button>
                </form>
            </div>
        <?php endif; ?>
    <?php elseif ($yo_lo_tengo && $prestamo_act['estado']=='pendiente'): ?>
        <p class="muted">Tu solicitud está pendiente de aprobación.</p>
    <?php elseif ($yo_lo_tengo && $prestamo_act['estado']=='pendiente_devolucion'): ?>
        <p class="muted">Tu solicitud de devolución está pendiente de aprobación.</p>
    <?php endif; ?>
</div>

<!-- Componentes -->
<div class="card">
    <h3>“Lo que trae”</h3>
    <table>
        <thead>
            <tr><th>Tipo</th><th>Marca</th><th>Modelo</th><th>Estado</th></tr>
        </thead>
        <tbody>
        <?php if (!$componentes): ?>
            <tr><td colspan="4" class="muted">Sin componentes.</td></tr>
        <?php else: foreach($componentes as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['tipo']) ?></td>
                <td><?= htmlspecialchars($c['marca']) ?></td>
                <td><?= htmlspecialchars($c['modelo']) ?></td>
                <td><?= htmlspecialchars($c['estado']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Cesiones -->
<?php if ($cesiones): ?>
<div class="card">
    <h3>Cesiones pendientes hacia ti</h3>
    <table>
        <thead>
            <tr><th>Equipo</th><th>Cedente</th><th>Serial</th><th>Acción</th></tr>
        </thead>
        <tbody>
        <?php foreach($cesiones as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['equipo_tipo'].' '.$c['equipo_marca'].' '.$c['equipo_modelo']) ?></td>
                <td><?= htmlspecialchars($c['cedente_nombre'].' '.$c['cedente_apellido']) ?></td>
                <td><?= htmlspecialchars($c['equipo_serial']) ?></td>
                <td>
                    <button class="cesion-btn" data-id="<?= $c['id'] ?>" data-accion="aceptar">Aceptar</button>
                    <button class="cesion-btn" data-id="<?= $c['id'] ?>" data-accion="rechazar">Rechazar</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

</div>
</div>

<script>
document.querySelectorAll('.cesion-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
        const id = btn.dataset.id;
        const accion = btn.dataset.accion;
        fetch('cesion_responder_ajax.php',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'cesion_id='+id+'&accion='+accion
        })
        .then(res=>res.json())
        .then(data=>{
            alert(data.message);
            if(data.success) location.reload();
        })
        .catch(()=>alert('Error al procesar la cesión'));
    });
});

const input = document.getElementById('buscar_docente');
const resultados = document.getElementById('resultados_busqueda');
const hiddenInput = document.getElementById('a_docente_id');
let timeout = null;

input.addEventListener('input', () => {
    clearTimeout(timeout);
    const term = input.value.trim();
    resultados.innerHTML = '';
    hiddenInput.value = '';

    if (!term) return;

    timeout = setTimeout(() => {
        fetch('buscar_docente_ajax.php?q=' + encodeURIComponent(term))
            .then(res => res.json())
            .then(data => {
                if (!data.length) {
                    resultados.innerHTML = '<div style="padding:8px;color:#9ca3af">Sin resultados</div>';
                    return;
                }

                data.forEach(d => {
                    if (d.id === <?= $e['id'] ?>) return; // 🔹 excluir al docente actual
                    const div = document.createElement('div');
                    div.style.padding = '8px';
                    div.style.cursor = 'pointer';
                    div.textContent = d.nombre + ' ' + d.apellido + ' (CI: ' + d.ci + ')';
                    div.addEventListener('click', () => {
                        input.value = d.nombre + ' ' + d.apellido + ' (CI: ' + d.ci + ')';
                        hiddenInput.value = d.id;
                        resultados.innerHTML = '';
                    });
                    resultados.appendChild(div);
                });
            });
    }, 250);
});

document.addEventListener('click', (e) => {
    if (!resultados.contains(e.target) && e.target !== input) {
        resultados.innerHTML = '';
    }
});
</script>
</body>
</html>
