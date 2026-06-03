<?php
session_start();
if(!isset($_SESSION['tipo_usuario']) || ($_SESSION['tipo_usuario'] != 'estudiante' && $_SESSION['tipo_usuario'] != 'docente')){
    header("Location: ../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel Usuario</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
  <h1 class="mb-4">👤 Bienvenido <?= $_SESSION['usuario'] ?></h1>
  <div class="list-group">
    <a href="buscar.php" class="list-group-item list-group-item-action">📚 Catálogo de Libros</a>
    <a href="prestamos.php" class="list-group-item list-group-item-action">📅 Mis Préstamos</a>
    <a href="perfil.php" class="list-group-item list-group-item-action">⚙️ Mi Perfil</a>
    <a href="../logout.php" class="list-group-item list-group-item-action text-danger">🚪 Cerrar Sesión</a>
  </div>
</div>
</body>
</html>
