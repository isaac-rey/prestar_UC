<?php
require __DIR__ . '/docente_init.php';
require_doc_login();
$e = doc();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Escanear QR – Docente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <link rel="stylesheet" href="docente_styles.css">
  <script src="https://unpkg.com/html5-qrcode"></script>
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
      if (document.getElementById('reader') && window.Html5QrcodeScanner) {
        const statusDiv = document.getElementById('status');

        const scanner = new Html5QrcodeScanner("reader", {
          fps: 10,
          qrbox: { width: 250, height: 250 },
          rememberLastUsedCamera: true,
          formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
          supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
        });

        const gotoEquipo = async (serial) => {
          statusDiv.style.display = 'block';
          try { await scanner.clear(); } catch (_) {}
          setTimeout(() => {
            window.location.assign(`/prestar_UC-main/public/docentes/docente_equipo.php?serial=${encodeURIComponent(serial)}`);
          }, 100);
        };

        const onScanSuccess = async (decodedText) => {
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
          await gotoEquipo(serial);
        };

        const onScanError = (error) => {
          if (error.includes('NotFoundException')) return;
          console.warn('Error de escaneo:', error);
        };

        scanner.render(onScanSuccess, onScanError);
      }
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
    });
  </script>

</body>
</html>