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
    document.addEventListener("DOMContentLoaded", () => {
      const readerElem = document.getElementById("reader");
      const statusDiv = document.getElementById("status");

      // Detectar iPhone / Safari
      const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
      const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
      const isAndroid = /Android/i.test(navigator.userAgent);

      // Mostrar botón manual si es iPhone o Safari
      if (isIOS || isSafari) {
        const btn = document.createElement("button");
        btn.textContent = "📷 Iniciar escáner";
        btn.className = "btn";
        btn.style.margin = "12px auto";
        btn.onclick = initScanner;
        readerElem.before(btn);
      } else {
        initScanner();
      }

      function initScanner() {
        if (!window.Html5Qrcode) {
          alert("El lector QR no está disponible.");
          return;
        }

        Html5Qrcode.getCameras()
          .then(devices => {
            if (!devices || !devices.length) {
              alert("No se detectaron cámaras en este dispositivo.");
              return;
            }

            // Buscar cámara trasera por label o última disponible
            let backCamera =
              devices.find(d => /back|environment/i.test(d.label)) ||
              devices[devices.length - 1];

            const html5QrCode = new Html5Qrcode("reader");

            // Configuración adaptativa por sistema
            const config = {
              fps: 10,
              qrbox: {
                width: 250,
                height: 250
              },
              experimentalFeatures: {
                useBarCodeDetectorIfSupported: true
              },
              videoConstraints: isIOS || isSafari ?
                {
                  facingMode: {
                    exact: "environment"
                  }
                } // Safari requiere exact
                :
                {
                  facingMode: "environment"
                } // Android Chrome más flexible
            };

            html5QrCode
              .start(backCamera?.id || {
                  facingMode: "environment"
                }, config,
                async (decodedText) => {
                    let serial = "";
                    try {
                      const u = new URL(decodedText, window.location.origin);
                      serial = u.searchParams.get("serial") || "";
                    } catch (_) {
                      serial = decodedText;
                    }

                    serial = (serial || "").trim();
                    if (!serial) {
                      alert("❌ No se detectó un serial válido en el QR.");
                      return;
                    }

                    statusDiv.style.display = "block";
                    await html5QrCode.stop();
                    setTimeout(() => {
                      window.location.assign(`/prestar_UC/public/estudiantes/estudiante_equipo.php?serial=${encodeURIComponent(serial)}`);
                    }, 200);
                  },
                  (errorMsg) => {
                    if (!/NotFoundException/.test(errorMsg)) console.warn("Error escaneo:", errorMsg);
                  }
              )
              .catch(err => {
                console.warn("Error de inicio de cámara:", err);

                // Fallback: intentar sin facingMode si Android falla
                if (isAndroid) {
                  html5QrCode.start(
                    backCamera.id, {
                      fps: 10,
                      qrbox: {
                        width: 250,
                        height: 250
                      },
                      experimentalFeatures: {
                        useBarCodeDetectorIfSupported: true
                      }
                    },
                    (decodedText) => {
                      let serial = decodedText.trim();
                      if (!serial) {
                        alert("❌ No se detectó un serial válido en el QR.");
                        return;
                      }
                      statusDiv.style.display = "block";
                      html5QrCode.stop();
                      setTimeout(() => {
                        window.location.assign(`/prestar_UC/public/estudiantes/estudiante_equipo.php?serial=${encodeURIComponent(serial)}`);
                      }, 200);
                    }
                  );
                } else {
                  alert("⚠️ No se pudo iniciar la cámara. Revisá los permisos.");
                }
              });
          })
          .catch(err => {
            console.error("Error al obtener cámaras:", err);
            alert("No se pudo acceder a la cámara.");
          });
      }
    });
  </script>



</body>

</html>