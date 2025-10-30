<?php
require __DIR__ . '/estudiante_init.php';
require_est_login();
$e = est();
?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <title>Escanear QR — Estudiante</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <link rel="stylesheet" href="estudiante_styles.css">
  <script src="https://unpkg.com/html5-qrcode"></script>
</head>

<body>

  <header>
    <a href="/prestar_UC/public/estudiantes/estudiante_panel.php">Inventario — Estudiante</a>
    <div style="display: flex; align-items: center; gap: 10px;">
      <button id="theme-toggle" class="btn-secondary btn-sm" style="width: auto; padding: 6px 12px; margin: 0;">
      </button>
      <?= htmlspecialchars($e['nombre'] . ' ' . $e['apellido']) ?> · <a href="/prestar_UC/auth/logout_estudiante.php">Salir</a>
    </div>
  </header>

  <div class="container">
    <div class="card">
      <h2>📷 Escanear QR de un equipo</h2>

      <div class="scan-instructions">
        <p>🎯 <strong>Apuntá la cámara al código QR del equipo</strong></p>
        <p>El sistema te redirigirá automáticamente a los detalles del equipo</p>
      </div>

      <div id="reader"></div>

      <div id="status" class="info mt-2" style="display: none;">
        <span class="loading"></span> Procesando...
      </div>
    </div>

  </div>

  <script>
    window.addEventListener('load', () => {
      // Solo iniciar el scanner si el elemento existe (para evitar errores si el script se reutiliza)
      if (document.getElementById('reader') && window.Html5QrcodeScanner) {

        const statusDiv = document.getElementById('status');

        // Intentar abrir directamente la cámara trasera
        Html5Qrcode.getCameras().then(devices => {
          if (devices && devices.length) {
            // Buscar cámara trasera (generalmente contiene 'back' o 'environment' en su label)
            let backCamera = devices.find(d =>
              /back|environment/i.test(d.label)
            ) || devices[0];

            const html5QrCode = new Html5Qrcode("reader");

            html5QrCode.start(
              backCamera.id, {
                fps: 10,
                qrbox: {
                  width: 250,
                  height: 250
                }
              },
              async (decodedText) => {
                  let serial = '';

                  try {
                    const u = new URL(decodedText, window.location.origin);
                    serial = u.searchParams.get('serial') || '';
                  } catch (_) {
                    serial = decodedText;
                  }

                  serial = (serial || '').trim();

                  if (!serial) {
                    alert("❌ No se detectó un serial válido en el QR.");
                    return;
                  }

                  document.getElementById('status').style.display = 'block';
                  await html5QrCode.stop();
                  setTimeout(() => {
                    window.location.assign(`/prestar_UC/public/estudiantes/estudiante_equipo.php?serial=${encodeURIComponent(serial)}`);
                  }, 200);
                },
                (error) => {
                  if (!/NotFoundException/.test(error)) console.warn('Error de escaneo:', error);
                }
            ).catch(err => {
              alert("No se pudo acceder a la cámara: " + err);
            });
          } else {
            alert("No se detectaron cámaras en este dispositivo.");
          }
        }).catch(err => {
          console.error("Error al obtener cámaras:", err);
        });


        const gotoEquipo = async (serial) => {
          statusDiv.style.display = 'block';
          try {
            await scanner.clear();
          } catch (_) {}

          setTimeout(() => {
            window.location.assign(`/prestar_UC/public/estudiantes/estudiante_equipo.php?serial=${encodeURIComponent(serial)}`);
          }, 100);
        };

        const onScanSuccess = async (decodedText) => {
          let serial = '';

          // Intentar extraer serial de URL o usar texto directo
          try {
            const u = new URL(decodedText, window.location.origin);
            serial = u.searchParams.get('serial') || '';
          } catch (_) {
            serial = decodedText;
          }

          serial = (serial || '').trim();

          if (!serial) {
            alert("❌ No se detectó un serial válido en el QR.");
            return;
          }

          await gotoEquipo(serial);
        };

        const onScanError = (error) => {
          // Silenciar errores de escaneo normales
          if (error.includes('NotFoundException')) return;
          console.warn('Error de escaneo:', error);
        };

        scanner.render(onScanSuccess, onScanError);
      }
    });

    // === LÓGICA AJAX y TEMA (Consolidada) ===
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

    function cargarCesiones() {
      fetch('cesiones_listado_ajax.php')
        .then(res => res.text())
        .then(html => {
          const container = document.getElementById('cesionesContainer');
          if (container) container.innerHTML = html;
        });
    }

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
        setInterval(cargarCesiones, 10000);
      }
    });
  </script>

</body>

</html>