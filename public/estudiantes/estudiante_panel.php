<?php
require __DIR__ . '/estudiante_init.php';
require_est_login();
$e = est();

// Préstamos activos
$stmt = $mysqli->prepare("
    SELECT p.id, p.equipo_id, p.fecha_entrega, p.observacion,
           e.tipo, e.marca, e.modelo, e.serial_interno
    FROM prestamos p
    JOIN equipos e ON e.id = p.equipo_id
    WHERE p.estudiante_id=? AND p.estado='activo'
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
    WHERE p.estudiante_id=? AND p.estado='devuelto'
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
  <title>Panel del estudiante</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <link rel="stylesheet" href="estudiante_styles.css">
  </head>
<body>

  <header>
    <a href="/prestar_UC-main/public/estudiantes/estudiante_panel.php">Inventario — Estudiante</a>
    <div style="display: flex; align-items: center; gap: 10px;">
      <button id="theme-toggle" class="btn-secondary btn-sm" style="width: auto; padding: 6px 12px; margin: 0;">
      </button>
      <?= htmlspecialchars($e['nombre'] . ' ' . $e['apellido']) ?> · <a href="/prestar_UC-main/auth/logout_estudiante.php">Salir</a>
    </div>
  </header>

  <div class="container">
    
    <div class="card">
      <h3>¡Hola, <?= htmlspecialchars($e['nombre']) ?>!</h3>
      <p class="muted">Podés escanear el QR de un equipo para pedir préstamo o devolverlo, o buscarlo por número de serie.</p>

      <div class="flex mt-2">
        <a class="btn" href="/prestar_UC-main/public/estudiantes/estudiante_scan.php">📷 Escanear QR</a>
      </div>

      <form class="search-form mt-2" method="get" action="/prestar_UC-main/public/estudiantes/estudiante_equipo.php">
        <input class="search-input" type="text" name="serial" placeholder="Ingresar N° de serie" required>
        <button class="btn" type="submit">🔍 Buscar</button>
      </form>
    </div>

    <div class="card mt-2">
      <h2>Mis préstamos activos (<?= count($activos) ?>)</h2>
      
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
                <a class="btn btn-sm" href="/prestar_UC-main/public/estudiantes/estudiante_equipo.php?serial=<?= urlencode($p['serial_interno']) ?>">
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
    
    <div id="cesionesContainer"></div>

  </div>

  <script>
    // Función AJAX para aceptar/rechazar cesión (Mantengo las funciones si son utilizadas en otro script que alimenta el #cesionesContainer)
    function responderCesion(id, accion) {
      fetch('cesion_responder_ajax.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: `cesion_id=${id}&accion=${accion}`
        })
        .then(res => res.json())
        .then(data => {
          alert(data.message);
          cargarCesiones();
        });
    }

    // Cargar listado de cesiones vía AJAX
    function cargarCesiones() {
      fetch('cesiones_listado_ajax.php')
        .then(res => res.text())
        .then(html => {
          const container = document.getElementById('cesionesContainer');
          if (container) container.innerHTML = html;
        });
    }

    // === LÓGICA DE TEMA CLARO/OSCURO y Carga Inicial de Cesiones ===
    document.addEventListener('DOMContentLoaded', () => {
        // 1. Elementos
        const body = document.body;
        const toggleButton = document.getElementById('theme-toggle');

        // 2. Obtener la preferencia guardada o del sistema
        const storedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        // Determinar el tema inicial
        let currentTheme = storedTheme || (systemPrefersDark ? 'dark' : 'light');

        // 3. Función para aplicar el tema
        function applyTheme(theme) {
            if (theme === 'light') {
                body.classList.add('light-mode');
                toggleButton.innerHTML = '🌙'; // Icono de luna para cambiar a oscuro
                toggleButton.title = 'Cambiar a Tema Oscuro';
            } else {
                body.classList.remove('light-mode');
                toggleButton.innerHTML = '☀️'; // Icono de sol para cambiar a claro
                toggleButton.title = 'Cambiar a Tema Claro';
            }
            currentTheme = theme;
            localStorage.setItem('theme', theme);
        }

        // 4. Aplicar el tema inicial
        applyTheme(currentTheme);

        // 5. Listener para el botón de alternancia
        toggleButton.addEventListener('click', () => {
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            applyTheme(newTheme);
        });

        // 6. Cargar cesiones (si existe el contenedor)
        if (document.getElementById('cesionesContainer')) {
            cargarCesiones();
            setInterval(cargarCesiones, 10000); // refresca cada 10s
        }
    });
  </script>

</body>
</html>