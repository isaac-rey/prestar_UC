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
    WHERE p.usuario_actual_id=? AND p.estado='activo'
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
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <link rel="stylesheet" href="docente_styles.css">
</head>
<body>

  <header>
    <a href="/prestar_UC-main/public/docentes/docente_panel.php">Inventario – Docente</a>
    <div>
      <button id="theme-toggle" class="btn-secondary btn-sm">🌙</button>
      <?= htmlspecialchars($e['nombre'] . ' ' . $e['apellido']) ?> · 
      <a href="/prestar_UC-main/auth/logout_docente.php">Salir</a>
    </div>
  </header>

  <div class="container">

    <!-- Panel principal -->
    <div class="card">
      <h3>¡Hola, <?= htmlspecialchars($e['nombre']) ?>!</h3>
      <p class="muted">Podés escanear el QR de un equipo para pedir préstamo o devolverlo, o buscarlo por número de serie.</p>

      <div class="flex mt-2">
        <a class="btn" href="/prestar_UC-main/public/docentes/docente_scan.php">📷 Escanear QR</a>
      </div>

      <form class="search-form mt-2" method="get" action="/prestar_UC-main/public/docentes/docente_equipo.php">
        <input class="search-input" type="text" name="serial" placeholder="Ingresar N° de serie" required>
        <button class="btn" type="submit">🔍 Buscar</button>
      </form>
    </div>

    <!-- Préstamos activos -->
    <div class="card mt-2">
      <h2>Mis préstamos activos (<span id="count-activos"><?= count($activos) ?></span>)</h2>
      
      <div id="prestamos-activos-container">
        <?php if (!$activos): ?>
          <div class="empty-state">
            <div class="empty-state-icon">📦</div>
            <p>No tenés préstamos activos</p>
          </div>
        <?php else: ?>
          <div style="margin-top: 16px;">
            <?php foreach ($activos as $p): ?>
              <div class="equipo-item">
                <div class="equipo-header">
                  <div class="equipo-title">
                    <?= htmlspecialchars($p['tipo']) ?><br>
                    <span class="muted" style="font-size: 13px; font-weight: 400;">
                      <?= htmlspecialchars($p['marca'] . ' ' . $p['modelo']) ?>
                    </span>
                  </div>
                  <a class="btn btn-sm" href="/prestar_UC-main/public/docentes/docente_equipo.php?serial=<?= urlencode($p['serial_interno']) ?>">
                    Ver
                  </a>
                </div>
                
                <div class="equipo-details">
                  <div class="equipo-detail-row">
                    <span>🔢 Serial:</span>
                    <strong><?= htmlspecialchars($p['serial_interno']) ?></strong>
                  </div>
                  <div class="equipo-detail-row">
                    <span>📅 Desde:</span>
                    <span><?= htmlspecialchars(date('d/m/Y', strtotime($p['fecha_entrega']))) ?></span>
                  </div>
                  <?php if ($p['observacion']): ?>
                    <div class="equipo-detail-row">
                      <span>📝 Obs:</span>
                      <span><?= htmlspecialchars($p['observacion']) ?></span>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Cesiones pendientes -->
    <div class="card mt-2">
      <h2>Solicitudes de cesión pendientes</h2>
      <div id="cesionesContainer">
        <div class="empty-state">
          <div class="empty-state-icon">⏳</div>
          <p>Cargando...</p>
        </div>
      </div>
    </div>

    <!-- Historial -->
    <div class="card mt-2">
      <h2>Historial reciente</h2>
      
      <?php if (!$historial): ?>
        <div class="empty-state">
          <div class="empty-state-icon">📋</div>
          <p>Todavía no hay devoluciones registradas</p>
        </div>
      <?php else: ?>
        <div style="margin-top: 16px;">
          <?php foreach ($historial as $p): ?>
            <div class="equipo-item">
              <div class="equipo-title">
                <?= htmlspecialchars($p['tipo']) ?><br>
                <span class="muted" style="font-size: 13px; font-weight: 400;">
                  <?= htmlspecialchars($p['marca'] . ' ' . $p['modelo']) ?>
                </span>
              </div>
              
              <div class="equipo-details">
                <div class="equipo-detail-row">
                  <span>🔢 Serial:</span>
                  <span><?= htmlspecialchars($p['serial_interno']) ?></span>
                </div>
                <div class="equipo-detail-row">
                  <span>📅 Entregado:</span>
                  <span><?= htmlspecialchars(date('d/m/Y', strtotime($p['fecha_entrega']))) ?></span>
                </div>
                <div class="equipo-detail-row">
                  <span>✅ Devuelto:</span>
                  <span><?= htmlspecialchars(date('d/m/Y', strtotime($p['fecha_devolucion']))) ?></span>
                </div>
                <?php if ($p['observacion']): ?>
                  <div class="equipo-detail-row">
                    <span>📝 Obs:</span>
                    <span><?= htmlspecialchars($p['observacion']) ?></span>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <script>
    // === FUNCIONES AJAX ===
    function actualizarPrestamosActivos(prestamos) {
      const container = document.getElementById('prestamos-activos-container');
      const contador = document.getElementById('count-activos');
      if (!container || !contador) return;

      contador.textContent = prestamos.length;

      if (!prestamos || prestamos.length === 0) {
        container.innerHTML = `
          <div class="empty-state">
            <div class="empty-state-icon">📦</div>
            <p>No tenés préstamos activos</p>
          </div>
        `;
        return;
      }

      let html = '<div style="margin-top: 16px;">';
      prestamos.forEach(p => {
        html += `
          <div class="equipo-item">
            <div class="equipo-header">
              <div class="equipo-title">
                ${p.tipo}<br>
                <span class="muted" style="font-size: 13px; font-weight: 400;">
                  ${p.marca} ${p.modelo}
                </span>
              </div>
              <a class="btn btn-sm" href="/prestar_UC-main/public/docentes/docente_equipo.php?serial=${encodeURIComponent(p.serial_interno)}">
                Ver
              </a>
            </div>
            
            <div class="equipo-details">
              <div class="equipo-detail-row">
                <span>🔢 Serial:</span>
                <strong>${p.serial_interno}</strong>
              </div>
              <div class="equipo-detail-row">
                <span>📅 Desde:</span>
                <span>${new Date(p.fecha_entrega).toLocaleDateString('es-PY')}</span>
              </div>
              ${p.observacion ? `
              <div class="equipo-detail-row">
                <span>📝 Obs:</span>
                <span>${p.observacion}</span>
              </div>
              ` : ''}
            </div>
          </div>
        `;
      });
      html += '</div>';
      container.innerHTML = html;
    }

    function actualizarCesiones(cesiones) {
      const cont = document.getElementById('cesionesContainer');
      if (!cont) return;

      if (!cesiones || cesiones.length === 0) {
        cont.innerHTML = `
          <div class="empty-state">
            <div class="empty-state-icon">✅</div>
            <p>No hay solicitudes pendientes</p>
          </div>
        `;
        return;
      }

      let html = '<div style="margin-top: 16px;">';
      cesiones.forEach(c => {
        html += `
          <div class="cesion-item">
            <p><strong>${c.cedente_nombre} ${c.cedente_apellido}</strong> quiere cederte:</p>
            <p class="muted">📦 ${c.equipo_nombre} (Serial: ${c.equipo_serial})</p>
            <div class="flex mt-1">
              <button class="btn btn-sm" onclick="responderCesion(${c.id},'aceptar')">✅ Aceptar</button>
              <button class="btn-secondary btn-sm" onclick="responderCesion(${c.id},'rechazar')">❌ Rechazar</button>
            </div>
          </div>
        `;
      });
      html += '</div>';
      cont.innerHTML = html;
    }

    function responderCesion(id, accion) {
      fetch('/prestar_UC-main/public/docentes/cesion_responder_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `cesion_id=${id}&accion=${accion}`
      })
      .then(res => res.json())
      .then(data => {
        alert(data.message);
        actualizar();
      })
      .catch(err => console.error('Error:', err));
    }

    function actualizar() {
      fetch('/prestar_UC-main/public/docentes/actualizaciones_ajax.php')
        .then(res => res.json())
        .then(data => {
          actualizarPrestamosActivos(data.prestamos_activos);
          actualizarCesiones(data.cesiones);
        })
        .catch(err => console.error('Error:', err));
    }

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

      // Iniciar actualización automática
      actualizar();
      setInterval(actualizar, 2000);
    });
  </script>

</body>
</html>