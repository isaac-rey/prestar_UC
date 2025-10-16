<?php
require __DIR__ . '/docente_init.php';
require_doc_login();
$e = doc();

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

// Buscar préstamo activo o pendiente
$stmt = $mysqli->prepare("
    SELECT p.*, d.id AS d_id, est.id AS e_id, p.usuario_actual_id
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
                INSERT INTO prestamos (equipo_id, docente_id, usuario_actual_id, observacion, estado)
                VALUES (?,?,?,?, 'pendiente')
            ");
            $stmt->bind_param("iiis", $equipo_id, $e['id'], $e['id'], $obs);
            $stmt->execute();
            $ok = "✅ Solicitud enviada. Esperando aprobación.";
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
            $ok = "✅ Solicitud de devolución enviada. Esperando aprobación.";
        }

    } elseif ($accion === 'ceder') {
        // --- Lógica de Cesión con flexibilidad (CI o ID) ---
        $a_docente_id = intval($_POST['a_docente_id'] ?? 0); // Viene del campo oculto (por AJAX)
        $receptor_input = trim($_POST['receptor_ci_nombre'] ?? ''); // Viene del campo de texto
        
        // Si el campo oculto (del AJAX) está vacío, buscamos el ID por la CI ingresada.
        if (!$a_docente_id && $receptor_input) { 
            // Buscamos docente estrictamente por CI
            $stmt = $mysqli->prepare("SELECT id FROM docentes WHERE ci=? LIMIT 1");
            $stmt->bind_param("s", $receptor_input);
            $stmt->execute();
            $receptor = $stmt->get_result()->fetch_assoc();
            
            if ($receptor) {
                $a_docente_id = intval($receptor['id']);
            } else {
                $error = "No se encontró el docente receptor con la CI proporcionada o no fue seleccionado.";
            }
        }

        // Si después de la búsqueda no tenemos ID, o hay error, detenemos el proceso.
        if (!$a_docente_id) {
            if (!$error) $error = "Debe seleccionar un docente o ingresar una CI válida.";
        }
        
        // 2. Procesar la cesión si es válida
        if (!$error && $a_docente_id && $yo_lo_tengo && $prestamo_act['estado'] === 'activo') {
            
            if ($a_docente_id === intval($e['id'])) {
                 $error = "No puedes ceder un préstamo a ti mismo.";
            } else {
                // Verificar si ya existe cesión pendiente
                $stmt = $mysqli->prepare("SELECT id FROM cesiones WHERE prestamo_id=? AND estado='pendiente' LIMIT 1");
                $stmt->bind_param("i", $prestamo_act['id']);
                $stmt->execute();
                $existe = $stmt->get_result()->fetch_assoc();
                
                if ($existe) {
                    $error = "Ya existe una solicitud de cesión pendiente para este préstamo.";
                } else {
                    // Insertar solicitud de cesión
                    $stmt = $mysqli->prepare("
                        INSERT INTO cesiones (prestamo_id, cedente_id, a_docente_id, estado, fecha_solicitud)
                        VALUES (?,?,?, 'pendiente', NOW())
                    ");
                    $stmt->bind_param("iii", $prestamo_act['id'], $e['id'], $a_docente_id);
                    $stmt->execute();
                    $ok = "✅ Solicitud de cesión enviada al docente.";
                }
            }
            
        } else if (!$error) {
            $error = "Datos inválidos o no tienes un préstamo activo para ceder.";
        }
        // --- FIN Lógica de Cesión ---

    } elseif ($accion === 'cancelar_solicitud') {
        if (!$prestamo_act || $prestamo_act['estado'] !== 'pendiente') {
            $error = "No existe una solicitud pendiente que puedas cancelar.";
        } elseif (!$yo_lo_tengo) {
            $error = "Solo el solicitante puede cancelar esta solicitud.";
        } else {
            $stmt = $mysqli->prepare("UPDATE prestamos SET estado='cancelado' WHERE id=? AND docente_id=? AND estado='pendiente'");
            $stmt->bind_param("ii", $prestamo_act['id'], $e['id']);
            $stmt->execute();

            if ($mysqli->affected_rows > 0) {
                $ok = "❌ Solicitud de préstamo cancelada con éxito.";
                $prestamo_act = null;
                $yo_lo_tengo = false;
            } else {
                $error = "Error al intentar cancelar la solicitud.";
            }
        }
    }
}

// Refrescar préstamo si hubo POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $mysqli->prepare("
        SELECT p.*, d.id AS d_id, est.id AS e_id, p.usuario_actual_id
        FROM prestamos p
        LEFT JOIN docentes d ON d.id = p.docente_id
        LEFT JOIN estudiantes est ON est.id = p.estudiante_id
        WHERE p.equipo_id=? AND p.estado IN ('activo','pendiente','pendiente_devolucion')
        ORDER BY p.fecha_entrega DESC LIMIT 1
    ");
    $stmt->bind_param("i", $equipo_id);
    $stmt->execute();
    $prestamo_act = $stmt->get_result()->fetch_assoc();
    $yo_lo_tengo = $prestamo_act && intval($prestamo_act['usuario_actual_id']) === intval($e['id']);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Equipo – Docente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#111827">
    <link rel="stylesheet" href="docente_styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<header>
    <a href="/prestar_UC-main/public/docentes/docente_panel.php">Inventario – Docente</a>
    <div>
        <button id="theme-toggle" class="btn-secondary btn-sm">🌙</button>
        <?= htmlspecialchars($e['nombre'].' '.$e['apellido']) ?> · 
        <a href="/prestar_UC-main/auth/logout_docente.php">Salir</a>
    </div>
</header>

<div class="container">
    <div class="grid">
        <div class="card">
            <h2><?= htmlspecialchars($equipo['tipo'].' · '.$equipo['marca'].' '.$equipo['modelo']) ?></h2>
            <p class="muted">📍 Área: <?= htmlspecialchars($equipo['area']) ?><?= $equipo['sala'] ? ' / '.htmlspecialchars($equipo['sala']) : '' ?></p>
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

            <div id="prestamo-status">
                <?php if ($prestamo_act && !$yo_lo_tengo): ?>
                    <div class="info mt-2">
                        <?= $prestamo_act['estado']=='pendiente' ? '⏳ Este equipo fue solicitado y espera aprobación.' : '🔒 Este equipo está prestado por otro usuario.' ?>
                    </div>
                <?php elseif (!$prestamo_act): ?>
                    <form method="post" class="mt-3">
                        <input type="hidden" name="accion" value="solicitar">
                        <label>Destino / Observación (opcional)</label>
                        <textarea name="observacion" id="obs-textarea" placeholder="Ej.: Sala 203, uso de clase, etc."></textarea>
                        <button type="submit" class="mt-2">📋 Solicitar préstamo</button>
                    </form>
                <?php elseif ($yo_lo_tengo && $prestamo_act['estado']=='pendiente'): ?>
                    <div class="warning mt-2 pulse">⏳ Tu solicitud está pendiente de aprobación.</div>
                    <form method="post" class="mt-2" onsubmit="return confirm('¿Estás seguro de cancelar tu solicitud?');">
                        <input type="hidden" name="accion" value="cancelar_solicitud">
                        <button type="submit" class="btn-secondary mt-2">❌ Cancelar Solicitud</button>
                    </form>
                <?php elseif ($yo_lo_tengo && $prestamo_act['estado']=='activo'): ?>
                    <div class="okmsg mt-2">✅ Tenés este equipo prestado</div>
                    <form method="post" class="mt-2">
                        <input type="hidden" name="accion" value="devolver">
                        <button type="submit" class="btn-danger mt-2">↩️ Solicitar devolución</button>
                    </form>

                    <div style="margin-top:12px">
                        <h4>Ceder préstamo a otro docente</h4>
                        <form method="post" onsubmit="return confirm('¿Confirmar cesión del préstamo?');">
                            <input type="hidden" name="accion" value="ceder">
                            <input type="text" name="receptor_ci_nombre" id="buscar_docente" placeholder="Buscar por CI o Nombre..." autocomplete="off" required>
                            <input type="hidden" name="a_docente_id" id="a_docente_id"> 
                            <div id="resultados_busqueda"></div>
                            <button type="submit" style="margin-top:8px">Enviar cesión</button>
                        </form>
                    </div>
                <?php elseif ($yo_lo_tengo && $prestamo_act['estado']=='pendiente_devolucion'): ?>
                    <div class="warning mt-2 pulse">⏳ Tu solicitud de devolución está pendiente de aprobación.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h3>📦 Componentes</h3>
            <?php if (!$componentes): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <p>Sin componentes registrados</p>
                </div>
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

        <?php if ($cesiones): ?>
        <div class="card">
            <h3>Cesiones pendientes hacia ti</h3>
            <div style="margin-top: 16px;">
                <?php foreach($cesiones as $c): ?>
                    <div class="cesion-item">
                        <p><strong><?= htmlspecialchars($c['cedente_nombre'].' '.$c['cedente_apellido']) ?></strong> quiere cederte:</p>
                        <p class="muted">📦 <?= htmlspecialchars($c['equipo_tipo'].' '.$c['equipo_marca'].' '.$c['equipo_modelo']) ?> (Serial: <?= htmlspecialchars($c['equipo_serial']) ?>)</p>
                        <div class="flex mt-1">
                            <button class="btn btn-sm cesion-btn" data-id="<?= $c['id'] ?>" data-accion="aceptar">✅ Aceptar</button>
                            <button class="btn-secondary btn-sm cesion-btn" data-id="<?= $c['id'] ?>" data-accion="rechazar">❌ Rechazar</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const serial = '<?= htmlspecialchars($serial) ?>';

// === PERSISTIR OBSERVACIÓN ===
function persistirObservacion() {
    const obsTextArea = document.getElementById('obs-textarea');
    if (obsTextArea) {
        obsTextArea.addEventListener('input', () => {
            localStorage.setItem('obs_equipo_' + serial, obsTextArea.value);
        });
        const storedObs = localStorage.getItem('obs_equipo_' + serial);
        if (storedObs) obsTextArea.value = storedObs;
    } else {
        localStorage.removeItem('obs_equipo_' + serial);
    }
}

// === AUTO-ACTUALIZACIÓN ===
function actualizarEstadoPrestamo() {
    const currentObsValue = document.getElementById('obs-textarea') ? 
                            document.getElementById('obs-textarea').value : null;

    fetch(`docente_equipo.php?serial=${serial}&ajax=true`)
        .then(response => response.text())
        .then(fullHtml => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(fullHtml, 'text/html');
            const newStatusHtml = doc.getElementById('prestamo-status').innerHTML;
            
            const prestamoStatusDiv = document.getElementById('prestamo-status');
            
            if (prestamoStatusDiv.innerHTML.trim() !== newStatusHtml.trim()) {
                prestamoStatusDiv.innerHTML = newStatusHtml;
                
                if (currentObsValue !== null) {
                    const newObsTextArea = document.getElementById('obs-textarea');
                    if (newObsTextArea) {
                        newObsTextArea.value = currentObsValue;
                        newObsTextArea.addEventListener('input', () => {
                            localStorage.setItem('obs_equipo_' + serial, newObsTextArea.value);
                        });
                    }
                }
                
                // Reactivar búsqueda de docentes
                inicializarBusquedaDocentes();
            }
        })
        .catch(error => console.error('Error:', error));
}

// === BÚSQUEDA DE DOCENTES CORREGIDA (SOLUCIÓN DEL BORRADO DEFINITIVA) ===
function inicializarBusquedaDocentes() {
    const input = document.getElementById('buscar_docente');
    const resultados = document.getElementById('resultados_busqueda');
    const hiddenInput = document.getElementById('a_docente_id');
    
    if (!input || !resultados || !hiddenInput) return;
    
    let timeout = null;

    input.addEventListener('input', () => {
        clearTimeout(timeout);
        const term = input.value; // No se usa trim() para evitar errores con espacios accidentales
        resultados.innerHTML = '';
        
        // SOLUCIÓN MEJORADA: Solo limpiamos el hidden ID. El texto visible se mantiene.
        hiddenInput.value = ''; 
        
        if (!term.trim()) return; // Solo buscamos si hay texto real

        timeout = setTimeout(() => {
            fetch('buscar_docente_ajax.php?q=' + encodeURIComponent(term))
                .then(res => res.json())
                .then(data => {
                    if (!data.length) {
                        resultados.innerHTML = '<div style="padding:8px;color:var(--text-muted)">Sin resultados</div>';
                        return;
                    }

                    data.forEach(d => {
                        if (d.id === <?= $e['id'] ?>) return;
                        const div = document.createElement('div');
                        div.style.padding = '8px';
                        div.style.cursor = 'pointer';
                        div.style.borderRadius = '4px';
                        // Mostrar CI, nombre y apellido
                        div.textContent = d.ci + ' (' + d.nombre + ' ' + d.apellido + ')';
                        
                        div.addEventListener('click', () => {
                            // Al hacer clic, se rellena el input con la CI + Nombre
                            input.value = d.ci + ' (' + d.nombre + ' ' + d.apellido + ')';
                            // Se asigna el ID al campo oculto (Esto es lo que el PHP usa primero)
                            hiddenInput.value = d.id; 
                            resultados.innerHTML = '';
                        });
                        resultados.appendChild(div);
                    });
                });
        }, 250);
    });

    // Esta parte permite que al hacer clic fuera, los resultados se oculten.
    document.addEventListener('click', (e) => {
        if (!resultados.contains(e.target) && e.target !== input) {
            resultados.innerHTML = '';
        }
    });
}

// === CESIONES ===
document.querySelectorAll('.cesion-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        const accion = btn.dataset.accion;
        fetch('cesion_responder_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'cesion_id=' + id + '&accion=' + accion
        })
        .then(res => res.json())
        .then(data => {
            Swal.fire({
                title: data.success ? 'Éxito' : 'Error',
                text: data.message,
                icon: data.success ? 'success' : 'error',
                confirmButtonText: 'Aceptar'
            }).then(() => {
                 if (data.success) location.reload();
            });
        })
        .catch(() => {
             Swal.fire({
                title: 'Error',
                text: 'Error al procesar la cesión. Intente de nuevo.',
                icon: 'error',
                confirmButtonText: 'Aceptar'
            });
        });
    });
});

// === TEMA CLARO/OSCURO ===
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

    // Inicializar funcionalidades
    persistirObservacion();
    inicializarBusquedaDocentes(); 
    
    // Auto-actualización cada 2 segundos
    setInterval(actualizarEstadoPrestamo, 2000);
});
</script>

</body>
</html> 