<?php
require __DIR__ . '/../init.php';
require_login();

if (!isset($_GET["id"])) exit();
$id = $_GET["id"];

$rol = user()['rol'];
include_once "../config/db.php";
$sentencia = $mysqli->query("SELECT reporte_fallos.fecha, reporte_fallos.tipo_fallo, reporte_fallos.descripcion_fallo, reporte_fallos.nombre_usuario_reportante, equipos.marca FROM reporte_fallos JOIN equipos ON equipos.id = reporte_fallos.id_equipo WHERE id = $id;");
$reportes = $sentencia->fetch_all(MYSQLI_ASSOC);
?>
<!--Recordemos que podemos intercambiar HTML y PHP como queramos-->
<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<title>Historial del Equipo</title>
	<link rel="stylesheet" href="../CSS/listado.css">
   
    <style>
    body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#0f172a;color:#e2e8f0;margin:0}
    header{display:flex;justify-content:space-between;align-items:center;padding:16px;background:#111827}
    a{color:#93c5fd;text-decoration:none}
    .container{padding:24px}
    .badge{display:inline-block;padding:4px 8px;border-radius:9999px;background:#1f2937;color:#93c5fd;font-size:12px}
    .grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
    .card{background:#111827;border:1px solid #1f2937;border-radius:12px;padding:16px}
    .card a.block{display:block;color:#e2e8f0;text-decoration:none}
    .card a.block:hover h3{color:#93c5fd}
    h3{margin:0 0 8px}
    p{margin:0}
  </style>
</head>

<body>
	<header>
        <div><a href="/inventario_uni/public/equipos_index.php">← Volver</a></div>
        <div>Reportes — <span class="badge"><?= htmlspecialchars($rol) ?></span></div>
        <div><?= htmlspecialchars(user()['nombre']) ?> <a href="/inventario_uni/auth/logout.php">Salir</a></div>
    </header>
	<div class="contenedor">


		<table class="table" >
			<thead>
				<tr>
					<th class="titulo" colspan="8">
						<div class="centrarTitulo"> <span class="texto-lista">Historial del Equipo</span>
                        
                    </div>
					</th>
				</tr>
				<tr class="table-primary">
					<th scope="col">Nro</th>
					<th scope="col">Marca</th>
					<th scope="col">Tipo fallo</th>
					<th scope="col">Descripción fallo</th>
					<th scope="col">Fecha</th>
					<th scope="col">Usuario reportante</th>
				</tr>
			</thead>
			<tbody id="tbody">

			</tbody>
		</table>
	</div>

		



	<template id="templateReportes">
		<tr>
			<td class="numero"></td>
			<td class="marca"></td>
			<td class="tipo_fallo"></td>
			<td class="descripcion_fallo"></td>
			<td class="fecha"></td>
			<td class="usuario_reportante"></td>
		</tr>
	</template>
	<template id="templateVacio">
		<tr>
			<td class="vacio" colspan="7">Sin coicidencias</td>
		</tr>
	</template>
	<script>
		const reportes = <?php echo json_encode($reportes) ?>;
        //console.log(reportes);
		const templateReportes = document.getElementById("templateReportes").content;
		const templateVacio = document.getElementById("templateVacio").content;
		const tbody = document.getElementById("tbody");

		function mostrarReportes() {
			tbody.innerHTML = ""; // Limpiar la tabla antes de agregar reportes

			const fragmento = document.createDocumentFragment();
			let numero = 0;
			reportes.map((reporte) => {
				
					numero++;
					const copia = templateReportes.cloneNode(true);
					copia.querySelector('.numero').textContent = numero;
					copia.querySelector('.marca').textContent = reporte.marca;
					copia.querySelector('.tipo_fallo').textContent = reporte.tipo_fallo;
					copia.querySelector('.descripcion_fallo').textContent = reporte.descripcion_fallo;
					copia.querySelector('.fecha').textContent = reporte.fecha;
					copia.querySelector('.usuario_reportante').textContent = reporte.nombre_usuario_reportante;
		


					fragmento.appendChild(copia);

			});

			tbody.appendChild(fragmento);
			if(tbody.textContent == ""){
				const copia = templateVacio.cloneNode(true);
				copia.querySelector('.vacio').textContent ="No hay reportes";
				tbody.appendChild(copia)
			}
			
		}

		mostrarReportes();
	</script>
</body>

</html>