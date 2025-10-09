<?php
require __DIR__ . '/docente_init.php';
require_doc_login();
$e = doc();

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

// Historial devueltos
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
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <title>Panel del docente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    /* --- estilos simplificados --- */
    body {
      font-family: system-ui, Arial;
      background: #0f172a;
      color: #e2e8f0;
      margin: 0;
    }

    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px;
      background: #111827;
    }

    a {
      color: #93c5fd;
      text-decoration: none;
    }

    .container {
      padding: 24px;
      max-width: 1100px;
      margin: auto;
    }

    .grid {
      display: grid;
      gap: 16px;
      grid-template-columns: 1fr;
    }

    .card {
      background: #111827;
      border: 1px solid #1f2937;
      border-radius: 12px;
      padding: 16px;
    }

    .muted {
      color: #9ca3af;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      padding: 10px;
      border-bottom: 1px solid #1f2937;
      text-align: left;
    }

    th {
      color: #93c5fd;
      background: #0b1220;
    }

    .btn {
      display: inline-block;
      padding: 8px 12px;
      border-radius: 8px;
      background: #2563eb;
      color: #fff;
      cursor: pointer;
    }

    .search-form {
      display: flex;
      gap: 8px;
      align-items: center;
      margin-top: 12px;
    }

    .search-input {
      padding: 8px;
      border-radius: 6px;
      border: 1px solid #374151;
      background: #1f2937;
      color: #fff;
    }

    button {
      padding: 6px 10px;
      border: 0;
      border-radius: 6px;
      background: #2563eb;
      color: #fff;
      cursor: pointer;
      margin-left: 4px;
    }
  </style>
</head>

<body>

  <header>
    <div>Inventario — Docente</div>
    <div><?= htmlspecialchars($e['nombre'] . ' ' . $e['apellido']) ?> · <a href="/prestar_uc/auth/logout_docente.php">Salir</a></div>
  </header>

  <div class="container">
    <div class="grid">

      <!-- Panel principal -->
      <div class="card">
        <h3>¡Hola, <?= htmlspecialchars($e['nombre']) ?>!</h3>
        <p class="muted">Podés escanear el QR de un equipo para pedir préstamo o devolverlo, o buscarlo por número de serie.</p>

        <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;margin-top:12px">
          <a class="btn" href="/prestar_uc/public/docentes/docente_scan.php">📷 Escanear QR de un equipo</a>
          <form class="search-form" method="get" action="/prestar_uc/public/docentes/docente_equipo.php">
            <input class="search-input" type="text" name="serial" placeholder="Ingresar N° de serie" required>
            <button class="btn" type="submit">🔍 Buscar</button>
          </form>
        </div>
      </div>

      <!-- Préstamos activos -->
      <div class="card">
        <h2>Mis préstamos activos (<?= count($activos) ?>)</h2>
        <table>
          <thead>
            <tr>
              <th>Equipo</th>
              <th>Serial</th>
              <th>Entregado</th>
              <th>Obs</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$activos): ?>
              <tr>
                <td colspan="5" class="muted">No tenés préstamos activos.</td>
              </tr>
              <?php else: foreach ($activos as $p): ?>
                <tr>
                  <td><?= htmlspecialchars($p['tipo'] . ' ' . $p['marca'] . ' ' . $p['modelo']) ?></td>
                  <td><a href="/prestar_uc/public/docentes/docente_equipo.php?serial=<?= urlencode($p['serial_interno']) ?>"><?= htmlspecialchars($p['serial_interno']) ?></a></td>
                  <td><?= htmlspecialchars($p['fecha_entrega']) ?></td>
                  <td><?= htmlspecialchars($p['observacion'] ?? '') ?></td>
                  <td><a class="btn" href="/prestar_uc/public/docentes/docente_equipo.php?serial=<?= urlencode($p['serial_interno']) ?>">Ver / Devolver / Ceder</a></td>
                </tr>
            <?php endforeach;
            endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Cesiones pendientes -->
      <div class="card">
        <h2>Solicitudes de cesión pendientes</h2>
        <div id="cesionesContainer">
          <!-- Cargado vía AJAX -->
        </div>
      </div>

      <!-- Historial -->
      <div class="card">
        <h2>Historial reciente</h2>
        <table>
          <thead>
            <tr>
              <th>Equipo</th>
              <th>Serial</th>
              <th>Entregado</th>
              <th>Devuelto</th>
              <th>Obs</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$historial): ?>
              <tr>
                <td colspan="5" class="muted">Todavía no hay devoluciones registradas.</td>
              </tr>
              <?php else: foreach ($historial as $p): ?>
                <tr>
                  <td><?= htmlspecialchars($p['tipo'] . ' ' . $p['marca'] . ' ' . $p['modelo']) ?></td>
                  <td><?= htmlspecialchars($p['serial_interno']) ?></td>
                  <td><?= htmlspecialchars($p['fecha_entrega']) ?></td>
                  <td><?= htmlspecialchars($p['fecha_devolucion']) ?></td>
                  <td><?= htmlspecialchars($p['observacion'] ?? '') ?></td>
                </tr>
            <?php endforeach;
            endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>

  <script>
    let ultimo_check = Math.floor(Date.now() / 1000);

    function actualizarPrestamosActivos(prestamos) {
  const tbody = document.querySelector('div.card table tbody');
  const contador = document.querySelector('div.card h2'); // el H2 que muestra la cantidad
  if (!tbody || !contador) return;

  tbody.innerHTML = '';

  if (!prestamos || prestamos.length === 0) {
    tbody.innerHTML = `<tr><td colspan="5" class="muted">No tenés préstamos activos.</td></tr>`;
    contador.textContent = 'Mis préstamos activos (0)';
    return;
  }

  contador.textContent = `Mis préstamos activos (${prestamos.length})`;

  prestamos.forEach(p => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>${p.tipo} ${p.marca} ${p.modelo}</td>
        <td><a href="/prestar_uc/public/docentes/docente_equipo.php?serial=${encodeURIComponent(p.serial_interno)}">${p.serial_interno}</a></td>
        <td>${p.fecha_entrega}</td>
        <td>${p.observacion ?? ''}</td>
        <td><a class="btn" href="/prestar_uc/public/docentes/docente_equipo.php?serial=${encodeURIComponent(p.serial_interno)}">Ver / Devolver / Ceder</a></td>
    `;
    tbody.appendChild(tr);
  });
}


    function actualizarCesiones(cesiones) {
      const cont = document.getElementById('cesionesContainer');
      if (!cont) return;

      if (!cesiones || cesiones.length === 0) {
        cont.innerHTML = '<p class="muted">No hay solicitudes pendientes.</p>';
        return;
      }

      let html = '<ul>';
      cesiones.forEach(c => {
        html += `<li><strong>${c.cedente_nombre} ${c.cedente_apellido}</strong> quiere cederte <em>${c.equipo_nombre}</em> (Serial: ${c.equipo_serial}) 
            <button onclick="responderCesion(${c.id},'aceptar')">Aceptar</button> 
            <button onclick="responderCesion(${c.id},'rechazar')">Rechazar</button></li>`;
      });
      html += '</ul>';
      cont.innerHTML = html;
    }

    function responderCesion(id, accion) {
    fetch('/prestar_uc/public/docentes/cesion_responder_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `cesion_id=${id}&accion=${accion}`
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        actualizar(); // refresca la UI inmediatamente
    })
    .catch(err => console.error(err));
}


    function actualizar() {
    fetch('/prestar_uc/public/docentes/actualizaciones_ajax.php')
        .then(res => res.json())
        .then(data => {
            actualizarPrestamosActivos(data.prestamos_activos);
            actualizarCesiones(data.cesiones);
        })
        .catch(console.error);
}

// Ejecutar cada segundo
setInterval(actualizar, 1000);
document.addEventListener('DOMContentLoaded', actualizar);

  </script>

</body>

</html>