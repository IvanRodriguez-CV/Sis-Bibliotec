<?php
session_start();
require_once "../clases/Libro.php";

$libroObj = new Libro();
$result = $libroObj->listar();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Catálogo de Libros</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
  <h1>📚 Catálogo de Libros</h1>
  <table class="table table-bordered">
    <thead class="table-dark">
      <tr><th>Código</th><th>Título</th><th>Categoría</th><th>Autor</th><th>Acciones</th></tr>
    </thead>
    <tbody>
      <?php while($row = $result->fetch_assoc()){ ?>
      <tr>
        <td><?= $row['codigo'] ?></td>
        <td><?= $row['titulo'] ?></td>
        <td><?= $row['categoria'] ?></td>
        <td><?= $row['autor'] ?></td>
        <td>
          <?php if(isset($_SESSION['tipo_usuario'])){ ?>
            <a href="solicitar_prestamo.php?id=<?= $row['id_libro'] ?>" class="btn btn-success btn-sm">📅 Solicitar Préstamo</a>
          <?php } else { ?>
            <span class="text-muted">🔒 Inicia sesión para pedir préstamo</span>
          <?php } ?>
        </td>
      </tr>
      <?php } ?>
    </tbody>
  </table>
</div>
</body>
</html>
