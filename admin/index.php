<?php
session_start();
if(!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] != 'admin'){
    header("Location: ../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel Administrador</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
  <h1 class="mb-4">📊 Panel de Administración</h1>
  <div class="list-group">
    <a href="usuarios.php" class="list-group-item list-group-item-action">👤 Gestión de Usuarios</a>
    <a href="libros.php" class="list-group-item list-group-item-action">📚 Gestión de Libros</a>
    <a href="categorias.php" class="list-group-item list-group-item-action">🏷️ Gestión de Categorías</a>
    <a href="autores.php" class="list-group-item list-group-item-action">✍️ Gestión de Autores</a>
    <a href="prestamos.php" class="list-group-item list-group-item-action">📅 Gestión de Préstamos</a>
  </div>
</div>
</body>
</html>
