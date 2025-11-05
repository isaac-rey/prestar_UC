<?php
require __DIR__ . '/docente_init.php';
require_doc_login();
$e = doc();

// === PRÉSTAMOS ACTIVOS ===
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

// === HISTORIAL DEVUELTOS ===
$stmt = $mysqli->prepare("
    SELECT p.id, p.equipo_id, p.fecha_entrega, p.fecha_devolucion, p.observacion,
           e.tipo, e.marca, e.modelo, e.serial_interno
    FROM prestamos p
    JOIN equipos e ON e.id = p.equipo_id
    WHERE p.docente_id=? AND p.estado='devuelto'
    ORDER BY p.fecha_devolucion DESC
    LIMIT 15
");
$stmt->bind_param("i", $e['id']);
$stmt->execute();
$historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Panel del docente</title>
<link rel="stylesheet" href="docente_styles.css">
</head>
<body>
<header>
    <a href="/prestar_UC/public/docentes/docente_panel.php">Inventario – Docente</a>
    <div>
        <button id="theme-toggle" class="btn-secondary btn-sm">🌙</button>
        <?= htmlspecialchars($e['nombre'].' '.$e['apellido']) ?> · 
        <a href="/prestar_UC/auth/logout_docente.php">Salir</a>
    </div>
</header>

<div class="container">

    <!-- ACCIÓN PRINCIPAL -->
    <div class="card main-card">
        <h2>¡Hola, <?= htmlspecialchars($e['nombre']) ?>!</h2>
        <p class="muted">Escanea el QR de un equipo o busca por número de serie.</p>
        <div class="flex main-actions">
            <a class="btn btn-primary btn-xl" href="/prestar_UC/public/docentes/docente_scan.php">📷 Escanear QR</a>
            <form class="search-form" method="get" action="/prestar_UC/public/docentes/docente_equipo.php">
                <input class="search-input" type="text" name="serial" placeholder="Ingresar N° de serie" required>
                <button class="btn btn-search" type="submit">🔍 Buscar</button>
            </form>
        </div>
    </div>

    <!-- PRÉSTAMOS ACTIVOS -->
    <div class="card">
        <h2>Mis préstamos activos (<span id="count-activos"><?= count($activos) ?></span>)</h2>
        <div class="scroll-x" id="prestamos-activos-container">
            <?php if(!$activos): ?>
                <div class="empty-state"><div class="empty-state-icon">📦</div><p>No tenés préstamos activos</p></div>
            <?php else: ?>
                <?php foreach($activos as $p): ?>
                <div class="equipo-item">
                    <div class="equipo-header">
                        <div class="equipo-title"><?= htmlspecialchars($p['tipo']) ?><br><span class="muted"><?= htmlspecialchars($p['marca'].' '.$p['modelo']) ?></span></div>
                        <a class="btn btn-sm" href="/prestar_UC/public/docentes/docente_equipo.php?serial=<?= urlencode($p['serial_interno']) ?>">Ver</a>
                    </div>
                    <div class="equipo-details">
                        <div><span>🔢 Serial:</span> <?= htmlspecialchars($p['serial_interno']) ?></div>
                        <div><span>📅 Desde:</span> <?= date('d/m/Y', strtotime($p['fecha_entrega'])) ?></div>
                        <?php if($p['observacion']): ?><div><span>📝 Obs:</span> <?= htmlspecialchars($p['observacion']) ?></div><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- CESIONES -->
    <div class="card">
        <h2>Solicitudes de cesión pendientes</h2>
        <div id="cesionesContainer">
            <div class="empty-state"><div class="empty-state-icon">⏳</div><p>Cargando...</p></div>
        </div>
    </div>

    <!-- HISTORIAL -->
    <div class="card">
        <h2>Historial reciente</h2>
        <?php if(!$historial): ?>
            <div class="empty-state"><div class="empty-state-icon">📋</div><p>Todavía no hay devoluciones registradas</p></div>
        <?php else: ?>
            <?php foreach($historial as $p): ?>
                <div class="equipo-item">
                    <div class="equipo-title"><?= htmlspecialchars($p['tipo']) ?><br><span class="muted"><?= htmlspecialchars($p['marca'].' '.$p['modelo']) ?></span></div>
                    <div class="equipo-details">
                        <div><span>🔢 Serial:</span> <?= htmlspecialchars($p['serial_interno']) ?></div>
                        <div><span>📅 Entregado:</span> <?= date('d/m/Y', strtotime($p['fecha_entrega'])) ?></div>
                        <div><span>✅ Devuelto:</span> <?= date('d/m/Y', strtotime($p['fecha_devolucion'])) ?></div>
                        <?php if($p['observacion']): ?><div><span>📝 Obs:</span> <?= htmlspecialchars($p['observacion']) ?></div><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script>
function actualizarPrestamosActivos(prestamos){
    const container=document.getElementById('prestamos-activos-container');
    const contador=document.getElementById('count-activos');
    if(!container)return;
    contador.textContent=prestamos.length;
    if(!prestamos.length){
        container.innerHTML=`<div class="empty-state"><div class="empty-state-icon">📦</div><p>No tenés préstamos activos</p></div>`;
        return;
    }
    container.innerHTML=prestamos.map(p=>`
    <div class="equipo-item">
        <div class="equipo-header">
            <div class="equipo-title">${p.tipo}<br><span class="muted">${p.marca} ${p.modelo}</span></div>
            <a class="btn btn-sm" href="/prestar_UC/public/docentes/docente_equipo.php?serial=${encodeURIComponent(p.serial_interno)}">Ver</a>
        </div>
        <div class="equipo-details">
            <div><span>🔢 Serial:</span> ${p.serial_interno}</div>
            <div><span>📅 Desde:</span> ${new Date(p.fecha_entrega).toLocaleDateString('es-PY')}</div>
            ${p.observacion?`<div><span>📝 Obs:</span> ${p.observacion}</div>`:''}
        </div>
    </div>`).join('');
}

function actualizarCesiones(cesiones){
 const cont=document.getElementById('cesionesContainer');
 if(!cont)return;
 if(!cesiones.length){
 cont.innerHTML=`<div class="empty-state"><div class="empty-state-icon">✅</div><p>No hay solicitudes pendientes</p></div>`;
return;
 }
 cont.innerHTML=cesiones.map(c=>`
 <div class="cesion-item">
 <p><strong>${c.cedente_nombre} ${c.cedente_apellido}</strong> quiere cederte:</p>
 <p class="muted">📦 ${c.equipo_tipo} ${c.equipo_marca} ${c.equipo_modelo} (Serial: ${c.equipo_serial})</p>
 <div class="flex mt-1">
 <button class="btn btn-sm" onclick="responderCesion(${c.id},'aceptar')">✅ Aceptar</button>
 <button class="btn-secondary btn-sm" onclick="responderCesion(${c.id},'rechazar')">❌ Rechazar</button>
</div>
 </div>`).join('');
}

function responderCesion(id,accion){
    fetch('/prestar_UC/public/docentes/cesion_responder_ajax.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`cesion_id=${id}&accion=${accion}`
    }).then(res=>res.json()).then(d=>{alert(d.message);actualizar();}).catch(e=>console.error(e));
}

function actualizar(){
    fetch('/prestar_UC/public/docentes/actualizaciones_ajax.php')
    .then(res=>res.json())
    .then(d=>{
        actualizarPrestamosActivos(d.prestamos_activos);
        actualizarCesiones(d.cesiones);
    }).catch(e=>console.error(e));
}

document.addEventListener('DOMContentLoaded',()=>{
    const body=document.body,toggle=document.getElementById('theme-toggle');
    const stored=localStorage.getItem('theme');
    const prefersDark=window.matchMedia('(prefers-color-scheme: dark)').matches;
    let current=stored||(prefersDark?'dark':'light');
    const apply=t=>{
        if(t==='light'){body.classList.add('light-mode');toggle.innerHTML='🌙';}
        else{body.classList.remove('light-mode');toggle.innerHTML='☀️';}
        localStorage.setItem('theme',t);current=t;
    };
    apply(current);
    toggle.addEventListener('click',()=>apply(current==='dark'?'light':'dark'));
    actualizar();
    setInterval(actualizar,2000);
});
</script>
</body>
</html>
