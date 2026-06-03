<?php
session_start();
require_once "../clases/Prestamo.php";
if(!isset($_SESSION['id_usuario'])){ header("Location: ../index.php"); exit; }

$prestamoObj = new Prestamo();
$result = $prestamoObj->listar();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Mis Préstamos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
  <h1>📅 Mis Préstamos</h1>
  <table class="table table-bordered">
    <thead class="table-dark">
      <tr><th>Libro</th><th>Fecha Préstamo</th><th>Fecha Límite</th><th>Estado</th></tr>
    </thead>
    <tbody>
      <?php while($row = $result->fetch_assoc()){ ?>
      <?php if($row['id_usuario'] == $_SESSION['id_usuario']){ ?>
      <tr>
        <td><?= $row['titulo'] ?></td>
        <td><?= $row['fecha_prestamo'] ?></td>
        <td><?= $row['fecha_devolucion'] ?></td>
        <td>
          <?php 
            if($row['estado_prestamo'] == "Activo"){
              echo "<span class='badge bg-success'>Activo</span>";
            } elseif($row['estado_prestamo'] == "Devuelto"){
              echo "<span class='badge bg-primary'>Devuelto</span>";
            } else {
              echo "<span class='badge bg-danger'>Vencido</span>";
            }
          ?>
        </td>
      </tr>
      <?php } ?>
      <?php } ?>
    </tbody>
  </table>
</div>
</body>
</html>
