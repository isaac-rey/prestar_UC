<?php
require __DIR__ . '/estudiante_init.php';
require_est_login();
$e = est();

$serial = trim($_GET['serial'] ?? '');
if ($serial === '') die("Serial no especificado.");

// Buscar equipo
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

// ✅ CORRECCIÓN: Buscar préstamo actual con TODOS los estados relevantes
$stmt = $mysqli->prepare("
    SELECT p.*, 
           d.id AS d_id, d.nombre AS d_nombre, d.apellido AS d_apellido,
           est.id AS e_id, est.nombre AS e_nombre, est.apellido AS e_apellido
    FROM prestamos p
    LEFT JOIN docentes d ON d.id=p.docente_id
    LEFT JOIN estudiantes est ON est.id=p.estudiante_id
    WHERE p.equipo_id=? AND p.estado IN ('activo','pendiente','pendiente_devolucion')
    ORDER BY p.fecha_entrega DESC LIMIT 1
");
$stmt->bind_param("i", $equipo_id);
$stmt->execute();
$prestamo_act = $stmt->get_result()->fetch_assoc();
$yo_lo_tengo = $prestamo_act && intval($prestamo_act['e_id']) === intval($e['id']);

// === LÓGICA DE NOTIFICACIÓN DE RECHAZO ===
$rechazo_motivo = null;
if ($yo_lo_tengo && $prestamo_act) {
    $stmt_r = $mysqli->prepare("
        SELECT observacion, creada_en FROM devoluciones 
        WHERE prestamo_id=? AND estado='rechazada' 
        ORDER BY creada_en DESC LIMIT 1
    ");
    $stmt_r->bind_param("i", $prestamo_act['id']);
    $stmt_r->execute();
    $rechazo_data = $stmt_r->get_result()->fetch_assoc();
    
    if ($rechazo_data && strtotime($rechazo_data['creada_en']) > time() - 600) {
        $rechazo_motivo = $rechazo_data['observacion'] ?? 'No se especificó un motivo.';
    }
    $stmt_r->close();
}

// Componentes del equipo
$stmt = $mysqli->prepare("SELECT * FROM componentes WHERE equipo_id=? ORDER BY creado_en DESC");
$stmt->bind_param("i", $equipo_id);
$stmt->execute();
$componentes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $obs = trim($_POST['observacion'] ?? '');

    // === Solicitar préstamo ===
    if ($accion === 'solicitar') {
        if ($prestamo_act) {
            $error = "Ya existe una solicitud o préstamo activo para este equipo.";
        } else {
            $stmt = $mysqli->prepare("INSERT INTO prestamos (equipo_id, estudiante_id, observacion, estado)
                                     VALUES (?,?,?, 'pendiente')");
            $stmt->bind_param("iis", $equipo_id, $e['id'], $obs);
            $stmt->execute();
        }
    }

    // ✅ CORRECCIÓN: Solicitar devolución - IGUAL QUE DOCENTES
    elseif ($accion === 'devolver') {
        if (!$prestamo_act || $prestamo_act['estado'] !== 'activo') {
            $error = "No hay préstamo activo para este equipo.";
        } elseif (!$yo_lo_tengo) {
            $error = "Solo quien tiene el préstamo puede solicitar la devolución.";
        } else {
            // ✅ CAMBIAR EL ESTADO DEL PRÉSTAMO A 'pendiente_devolucion'
            $stmt = $mysqli->prepare("UPDATE prestamos SET estado='pendiente_devolucion' WHERE id=? LIMIT 1");
            $stmt->bind_param("i", $prestamo_act['id']);
            $stmt->execute();
            
            if ($mysqli->affected_rows > 0) {
                $ok = "✅ Solicitud de devolución enviada. Esperando confirmación del administrador.";
            } else {
                $error = "Error al procesar la solicitud de devolución.";
            }
        }
    }

    // === Cancelar Solicitud Pendiente ===
    elseif ($accion === 'cancelar_solicitud') {
        if (!$prestamo_act || $prestamo_act['estado'] !== 'pendiente') {
            $error = "No existe una solicitud pendiente que puedas cancelar.";
        } elseif (!$yo_lo_tengo) {
            $error = "Solo el solicitante puede cancelar esta solicitud.";
        } else {
            $stmt = $mysqli->prepare("UPDATE prestamos SET estado='cancelado' WHERE id=? AND estudiante_id=? AND estado='pendiente'");
            $stmt->bind_param("ii", $prestamo_act['id'], $e['id']);
            $stmt->execute();

            if ($mysqli->affected_rows > 0) {
                $ok = "❌ Solicitud de préstamo cancelada con éxito.";
                $prestamo_act = null;
                $yo_lo_tengo = false;
            } else {
                $error = "Error al intentar cancelar la solicitud. Es posible que ya haya sido procesada.";
            }
        }
    }
}

// ✅ CORRECCIÓN: Vuelve a buscar el préstamo después del POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $mysqli->prepare("
        SELECT p.*, 
               d.id AS d_id, d.nombre AS d_nombre, d.apellido AS d_apellido,
               est.id AS e_id, est.nombre AS e_nombre, est.apellido AS e_apellido
        FROM prestamos p
        LEFT JOIN docentes d ON d.id=p.docente_id
        LEFT JOIN estudiantes est ON est.id=p.estudiante_id
        WHERE p.equipo_id=? AND p.estado IN ('activo','pendiente','pendiente_devolucion')
        ORDER BY p.fecha_entrega DESC LIMIT 1
    ");
    $stmt->bind_param("i", $equipo_id);
    $stmt->execute();
    $prestamo_act = $stmt->get_result()->fetch_assoc();
    $yo_lo_tengo = $prestamo_act && intval($prestamo_act['e_id']) === intval($e['id']);
}

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Equipo – Estudiante</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estudiante_styles.css">
    <style>
        .pulse { animation: pulse 2s ease-in-out infinite; }
        @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.5;} }
        .updating { opacity:0.6; pointer-events:none; }
    </style>
</head>
<body>
<header>
    <a href="/prestar_UC/public/estudiantes/estudiante_panel.php">Inventario – Estudiante</a>
    <div style="display:flex;gap:10px;align-items:center;">
        <button id="theme-toggle" class="btn-secondary btn-sm">🌙</button>
        <?= htmlspecialchars($e['nombre'] . ' ' . $e['apellido']) ?> · <a href="/prestar_UC/auth/logout_estudiante.php">Salir</a>
    </div>
</header>

<div class="container">
    <div class="grid">
        <div class="card">
            <h2><?= htmlspecialchars($equipo['tipo'].' · '.$equipo['marca'].' '.$equipo['modelo']) ?></h2>
            <p class="muted">📍 Área: <?= htmlspecialchars($equipo['area']) ?> <?= $equipo['sala'] ? '/ '.htmlspecialchars($equipo['sala']) : '' ?></p>
            <p class="muted">🔢 Serial: <strong><?= htmlspecialchars($equipo['serial_interno']) ?></strong></p>

            <div class="flex mt-2">
                <span>Estado:</span>
                <?php
                $cls = ($equipo['estado']==='dañado'||$equipo['estado']==='fuera_servicio')?'bad':(($equipo['estado']==='en_uso')?'warn':'ok');
                ?>
                <span class="badge <?= $cls ?>"><?= htmlspecialchars($equipo['estado']) ?></span>
                <span class="badge <?= $equipo['prestado'] ? 'warn' : 'ok' ?>">
                    <?= $equipo['prestado'] ? '🔒 Prestado' : '✅ Disponible' ?>
                </span>
            </div>

            <?php if ($error): ?><div class="error mt-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($ok): ?><div class="okmsg mt-2"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
            
            <div id="temp-message-container">
            <?php if ($rechazo_motivo): ?>
                <div class="error mt-2 temp-message" data-duration="5000">
                    ❌ Solicitud de Devolución **Rechazada**: <br>
                    <strong>Motivo:</strong> <?= htmlspecialchars($rechazo_motivo) ?>
                </div>
            <?php endif; ?>
            </div>

            <div id="prestamo-status">
                <?php if ($prestamo_act && !$yo_lo_tengo): ?>
                    <!-- ✅ CORRECCIÓN: Mostrar quién tiene el préstamo -->
                    <div class="info mt-2">
                        <?php if ($prestamo_act['estado']=='pendiente'): ?>
                            ⏳ Este equipo fue solicitado y espera aprobación.
                        <?php else: ?>
                            🔒 Este equipo está prestado por: 
                            <strong>
                                <?php 
                                if ($prestamo_act['e_id']) {
                                    echo htmlspecialchars($prestamo_act['e_nombre'] . ' ' . $prestamo_act['e_apellido']);
                                } elseif ($prestamo_act['d_id']) {
                                    echo htmlspecialchars($prestamo_act['d_nombre'] . ' ' . $prestamo_act['d_apellido']);
                                } else {
                                    echo 'otro usuario';
                                }
                                ?>
                            </strong>
                        <?php endif; ?>
                    </div>

                <?php elseif (!$prestamo_act): ?>
                    <form method="post" class="mt-3">
                        <input type="hidden" name="accion" value="solicitar">
                        <label>Destino / Observación (opcional)</label>
                        <textarea name="observacion" placeholder="Ej.: Sala 203, uso de clase, etc."></textarea>
                        <button type="submit" class="mt-2">📋 Solicitar préstamo</button>
                    </form>

                <?php elseif ($yo_lo_tengo && $prestamo_act['estado']=='pendiente'): ?>
                    <div class="warning mt-2 pulse">⏳ Tu solicitud está pendiente de aprobación.</div>
                    <form method="post" class="mt-2" onsubmit="return confirm('¿Estás seguro de que deseas cancelar tu solicitud de préstamo?');">
                        <input type="hidden" name="accion" value="cancelar_solicitud">
                        <button type="submit" class="btn-secondary mt-2">❌ Cancelar Solicitud</button>
                    </form>

                <?php elseif ($yo_lo_tengo && $prestamo_act['estado']=='activo'): ?>
                    <div class="okmsg mt-2">✅ Tenés este equipo prestado</div>
                    <form method="post" class="mt-2">
                        <input type="hidden" name="accion" value="devolver">
                        <label>Observación (opcional)</label>
                        <textarea name="observacion" placeholder="Ej.: Equipo en buen estado, etc."></textarea>
                        <button type="submit" class="btn-danger mt-2">↩️ Solicitar devolución</button>
                    </form>

                <?php elseif ($yo_lo_tengo && $prestamo_act['estado']=='pendiente_devolucion'): ?>
                    <!-- ✅ NUEVO: Mostrar cuando hay solicitud de devolución pendiente -->
                    <div class="warning mt-2 pulse">⏳ Tu solicitud de devolución está pendiente de aprobación.</div>
                    <p class="muted mt-1">El administrador revisará tu solicitud pronto.</p>

                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h3>📦 Componentes</h3>
            <?php if (!$componentes): ?>
                <p class="muted text-center mt-2">Sin componentes registrados.</p>
            <?php else: ?>
                <div class="componentes-mobile">
                    <?php foreach($componentes as $c): ?>
                        <?php
                          $cls_c = ($c['estado']==='dañado'||$c['estado']==='fuera_servicio')?'bad':(($c['estado']==='en_uso')?'warn':'ok');
                        ?>
                        <div class="equipo-item">
                            <div class="equipo-header">
                                <div class="equipo-title">
                                    <?= htmlspecialchars($c['tipo']) ?><br>
                                    <span class="muted" style="font-size:13px;"><?= htmlspecialchars($c['marca'].' '.$c['modelo']) ?></span>
                                </div>
                                <span class="badge <?= $cls_c ?>"><?= htmlspecialchars($c['estado']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    const serial = '<?= htmlspecialchars($equipo['serial_interno']) ?>';

    function persistirObservacion() {
        const obsTextArea = document.querySelector('form textarea[name="observacion"]');
        if (obsTextArea) {
            obsTextArea.addEventListener('input', () => {
                localStorage.setItem('obs_equipo_' + serial, obsTextArea.value);
            });
            
            const storedObs = localStorage.getItem('obs_equipo_' + serial);
            if (storedObs) {
                obsTextArea.value = storedObs;
            }
        } else {
             localStorage.removeItem('obs_equipo_' + serial);
        }
    }

    function actualizarEstadoPrestamo() {
        const currentObsValue = document.querySelector('form textarea[name="observacion"]') ? 
                                document.querySelector('form textarea[name="observacion"]').value : null;

        fetch(`estudiante_equipo.php?serial=${serial}&ajax=true`)
            .then(response => response.text())
            .then(fullHtml => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(fullHtml, 'text/html');
                const newStatusHtml = doc.getElementById('prestamo-status').innerHTML;
                
                const prestamoStatusDiv = document.getElementById('prestamo-status');
                
                if (prestamoStatusDiv.innerHTML.trim() !== newStatusHtml.trim()) {
                    prestamoStatusDiv.innerHTML = newStatusHtml;
                    
                    if (currentObsValue !== null) {
                         const newObsTextArea = document.querySelector('form textarea[name="observacion"]');
                         if (newObsTextArea) {
                            newObsTextArea.value = currentObsValue;
                            newObsTextArea.addEventListener('input', () => {
                                localStorage.setItem('obs_equipo_' + serial, newObsTextArea.value);
                            });
                         }
                    }
                }
            })
            .catch(error => console.error('Error al actualizar el estado del préstamo:', error));
    }

    document.addEventListener('DOMContentLoaded', () => {
        const body = document.body;
        const toggleButton = document.getElementById('theme-toggle');
        const storedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        let currentTheme = storedTheme || (systemPrefersDark ? 'dark' : 'light');

        function applyTheme(theme) {
            if (theme === 'light') {
                body.classList.add('light-mode');
                toggleButton.innerHTML = '🌙'; 
                toggleButton.title = 'Cambiar a Tema Oscuro';
            } else {
                body.classList.remove('light-mode');
                toggleButton.innerHTML = '☀️'; 
                toggleButton.title = 'Cambiar a Tema Claro';
            }
            currentTheme = theme;
            localStorage.setItem('theme', theme);
        }
        applyTheme(currentTheme);
        toggleButton.addEventListener('click', () => {
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            applyTheme(newTheme);
        });

        persistirObservacion();

        const tempMessages = document.querySelectorAll('.temp-message');
        tempMessages.forEach(msg => {
            const duration = parseInt(msg.dataset.duration) || 5000;
            setTimeout(() => {
                msg.style.transition = 'opacity 0.5s ease-out';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            }, duration);
        });

        setInterval(actualizarEstadoPrestamo, 2000); 
    });
</script>

</body>
</html>