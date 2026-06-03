<?php
require_once '../config/conexion.php';
session_start();

// Validación de sesión y rol
if (!isset($_SESSION['id_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

// Registrar nueva categoría
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $nombre = trim($_POST['nombre']);
    $generos = trim($_POST['generos']);
    if (!empty($nombre) && !empty($generos)) {
        $stmt = $pdo->prepare("INSERT INTO Categoria (nombre, generos) VALUES (?, ?)");
        $stmt->execute([$nombre, $generos]);
    }
}

// Modificar categoría existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id_categoria']);
    $nombre = trim($_POST['nombre']);
    $generos = trim($_POST['generos']);
    $stmt = $pdo->prepare("UPDATE Categoria SET nombre = ?, generos = ? WHERE id_categoria = ?");
    $stmt->execute([$nombre, $generos, $id]);
}

// Eliminar categoría
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $stmt = $pdo->prepare("DELETE FROM Categoria WHERE id_categoria = ?");
    $stmt->execute([$id]);
    header("Location: categorias.php");
    exit;
}

$categorias = $pdo->query("SELECT * FROM Categoria ORDER BY id_categoria DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mantenimiento de Categorías</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Navbar igual que dashboard -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
                📚 <span class="ms-2">Panel Admin</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center gap-2">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="libros.php">Libros</a></li>
                    <li class="nav-item"><a class="nav-link" href="autores.php">Autores</a></li>
                    <li class="nav-item"><a class="nav-link active fw-semibold" href="categorias.php">Categorías</a></li>
                    <li class="nav-item"><a class="nav-link" href="prestamos.php">Préstamos</a></li>
                    <li class="nav-item"><a class="nav-link" href="usuarios.php">Usuarios</a></li>
                    <li class="nav-item">
                        <a class="btn btn-outline-light btn-sm ms-2 px-3 fw-bold" href="../public/login.php">Salir</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <main class="container my-5">
        <h2 class="fw-bold mb-4 text-center">Gestión de Categorías y Géneros</h2>

        <!-- Formulario -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Crear / Modificar Registro</h5>
                <form action="categorias.php" method="POST" class="row g-3">
                    <input type="hidden" name="id_categoria" id="id_categoria">
                    <div class="col-12">
                        <label for="nombre" class="form-label">Nombre de la Categoría</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label for="generos" class="form-label">Géneros Relacionados</label>
                        <input type="text" id="generos" name="generos" class="form-control" placeholder="Separados por comas (Ej. Terror, Suspenso)" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="crear" id="btn-submit" class="btn btn-primary w-100">Agregar Categoría</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Categorías Registradas</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Categoría</th>
                                <th>Géneros</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categorias as $c): ?>
                                <tr>
                                    <td><?php echo $c['id_categoria']; ?></td>
                                    <td><?php echo htmlspecialchars($c['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($c['generos']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="cargarDatos(<?php echo $c['id_categoria']; ?>, '<?php echo addslashes($c['nombre']); ?>', '<?php echo addslashes($c['generos']); ?>')">Editar</button>
                                        <a href="categorias.php?eliminar=<?php echo $c['id_categoria']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar esta categoría?')">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        function cargarDatos(id, nombre, generos) {
            document.getElementById('id_categoria').value = id;
            document.getElementById('nombre').value = nombre;
            document.getElementById('generos').value = generos;
            document.getElementById('btn-submit').name = 'editar';
            document.getElementById('btn-submit').textContent = 'Guardar Cambios';
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
