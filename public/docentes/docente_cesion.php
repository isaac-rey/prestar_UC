<?php
require __DIR__ . '/docente_init.php';
require_doc_login();
$e = doc();

// ------------------------------------------------------------------
// NOTA: Se mantiene la inclusión del init.php, pero se ELIMINA la llamada a auditar.
// Se recomienda mantener el require, ya que podría ser necesario en el futuro.
// ------------------------------------------------------------------
require __DIR__ . '/../../../inventario_uni/init.php'; 

$prestamo_id = intval($_GET['prestamo_id'] ?? 0);
$serial = trim($_GET['serial'] ?? '');
if (!$prestamo_id) die("Préstamo no especificado.");

// Traer préstamo
$stmt = $mysqli->prepare("SELECT * FROM prestamos WHERE id=? LIMIT 1");
$stmt->bind_param("i", $prestamo_id);
$stmt->execute();
$prestamo = $stmt->get_result()->fetch_assoc();
if (!$prestamo) die("Préstamo no encontrado.");

// Validar que sea préstamo activo
if ($prestamo['estado'] !== 'activo') die("Este préstamo ya no está activo.");

// Solo quien tiene el préstamo puede ceder
if (intval($prestamo['docente_id']) !== intval($e['id'])) die("No puede ceder este préstamo.");

$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docente_receptor = trim($_POST['docente_receptor'] ?? '');

    if (!$docente_receptor) {
        $error = "Debe ingresar el CI o nombre del docente receptor.";
    } else {
        // Buscar docente receptor por CI o nombre completo
        $stmt = $mysqli->prepare("SELECT id FROM docentes WHERE ci=? OR CONCAT(nombre,' ',apellido)=? LIMIT 1");
        $stmt->bind_param("ss", $docente_receptor, $docente_receptor);
        $stmt->execute();
        $receptor = $stmt->get_result()->fetch_assoc();

        if (!$receptor) {
            $error = "No se encontró el docente receptor.";
        } else {
            $a_docente_id = intval($receptor['id']);
            $cedente_id = intval($e['id']); // El docente actual es el cedente

            // Verificar si ya existe cesión pendiente
            $stmt = $mysqli->prepare("SELECT id FROM cesiones WHERE prestamo_id=? AND estado='pendiente' LIMIT 1");
            $stmt->bind_param("i", $prestamo_id);
            $stmt->execute();
            $existe = $stmt->get_result()->fetch_assoc();

            if ($existe) {
                $error = "Ya existe una solicitud de cesión pendiente para este préstamo.";
            } else {
                // Insertar nueva solicitud usando cedente_id y a_docente_id
                $stmt = $mysqli->prepare("INSERT INTO cesiones (prestamo_id, cedente_id, a_docente_id, estado) VALUES (?,?,?, 'pendiente')");
                $stmt->bind_param("iii", $prestamo_id, $cedente_id, $a_docente_id);

                if ($stmt->execute()) {
                    $ok = "Solicitud de cesión enviada correctamente. El docente receptor debe aceptarla.";
                    
                    // ------------------------------------------------------------------
                    // ⭐ AUDITORÍA ELIMINADA: La auditoría ocurre solo al ACEPTAR en cesion_responder.php.
                    // ------------------------------------------------------------------
                    
                } else {
                    $error = "Error al registrar la cesión. Intente de nuevo.";
                }
            }
        }
    }
}
?>

<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ceder equipo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: system-ui, Segoe UI, Arial, sans-serif; background: #0f172a; color: #e2e8f0; margin:0 }
header { display:flex; justify-content:space-between; align-items:center; padding:16px; background:#111827 }
a { color:#93c5fd; text-decoration:none }
.container { padding:24px; max-width:600px; margin:auto }
.card { background:#111827; border:1px solid #1f2937; border-radius:12px; padding:16px; margin-top:20px }
input, textarea { width:100%; padding:10px; border-radius:8px; border:1px solid #374151; background:#0b1220; color:#e5e7eb; margin-top:8px }
button { padding:10px 14px; border:0; border-radius:8px; background:#2563eb; color:#fff; cursor:pointer; font-weight:600; margin-top:12px }
.muted { color:#9ca3af }
.error { background:#7f1d1d; color:#fecaca; padding:10px; border-radius:8px; margin-bottom:12px }
.okmsg { background:#052e16; color:#bbf7d0; padding:10px; border-radius:8px; margin-bottom:12px }
</style>
</head>
<body>

<header>
    <div><a href="docente_equipo.php?serial=<?= urlencode($serial) ?>">← Volver al equipo</a></div>
    <div><?= htmlspecialchars($e['nombre'].' '.$e['apellido']) ?> · <a href="/inventario_uni/auth/logout_docente.php">Salir</a></div>
</header>

<div class="container">
<div class="card">
<h2>Ceder equipo</h2>

<?php if($error): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if($ok): ?>
  <div class="okmsg"><?= htmlspecialchars($ok) ?></div>
<?php endif; ?>

<form method="post">
    <label>CI o nombre del docente receptor</label>
    <input type="text" name="docente_receptor" value="<?= htmlspecialchars($_POST['docente_receptor'] ?? '') ?>" placeholder="Ej.: 1234567 o Juan Pérez" required>
    <button type="submit">Enviar solicitud</button>
</form>
</div>
</div>

</body>
</html>