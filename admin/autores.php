<?php
require_once '../config/conexion.php';
session_start();

// Validación de sesión y rol
if (!isset($_SESSION['id_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

// Crear autor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $anio = intval($_POST['anio_nacimiento']);
    $genero = trim($_POST['genero']);
    if (!empty($nombre) && !empty($apellido)) {
        $stmt = $pdo->prepare("INSERT INTO Autores (nombre, apellido, anio_nacimiento, genero) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nombre, $apellido, $anio, $genero]);
    }
}

// Editar autor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id_autor']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $anio = intval($_POST['anio_nacimiento']);
    $genero = trim($_POST['genero']);
    $stmt = $pdo->prepare("UPDATE Autores SET nombre = ?, apellido = ?, anio_nacimiento = ?, genero = ? WHERE id_autor = ?");
    $stmt->execute([$nombre, $apellido, $anio, $genero, $id]);
}

// Eliminar autor
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $stmt = $pdo->prepare("DELETE FROM Autores WHERE id_autor = ?");
    $stmt->execute([$id]);
    header("Location: autores.php");
    exit;
}

$autores = $pdo->query("SELECT * FROM Autores ORDER BY id_autor DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mantenimiento de Autores</title>
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
                    <li class="nav-item"><a class="nav-link active fw-semibold" href="autores.php">Autores</a></li>
                    <li class="nav-item"><a class="nav-link" href="categorias.php">Categorías</a></li>
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
        <h2 class="fw-bold mb-4 text-center">Gestión de Autores</h2>

        <!-- Formulario -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Ingresar / Editar Autor</h5>
                <form action="autores.php" method="POST" class="row g-3">
                    <input type="hidden" name="id_autor" id="id_autor">
                    <div class="col-md-6">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="apellido" class="form-label">Apellido</label>
                        <input type="text" id="apellido" name="apellido" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="anio_nacimiento" class="form-label">Año Nacimiento</label>
                        <input type="number" id="anio_nacimiento" name="anio_nacimiento" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label for="genero" class="form-label">Género Literario Frecuente</label>
                        <input type="text" id="genero" name="genero" class="form-control">
                    </div>
                    <div class="col-12">
                        <button type="submit" name="crear" id="btn-submit" class="btn btn-primary w-100">Registrar Autor</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Autores Registrados</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nombre Completo</th>
                                <th>Año Nacimiento</th>
                                <th>Género</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($autores as $a): ?>
                                <tr>
                                    <td><?php echo $a['id_autor']; ?></td>
                                    <td><?php echo htmlspecialchars($a['nombre'] . ' ' . $a['apellido']); ?></td>
                                    <td><?php echo $a['anio_nacimiento'] ?? 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($a['genero'] ?? 'N/A'); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="cargarDatos(<?php echo $a['id_autor']; ?>, '<?php echo addslashes($a['nombre']); ?>', '<?php echo addslashes($a['apellido']); ?>', '<?php echo $a['anio_nacimiento']; ?>', '<?php echo addslashes($a['genero']); ?>')">Editar</button>
                                        <a href="autores.php?eliminar=<?php echo $a['id_autor']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este autor?')">Eliminar</a>
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
        function cargarDatos(id, nombre, apellido, anio, genero) {
            document.getElementById('id_autor').value = id;
            document.getElementById('nombre').value = nombre;
            document.getElementById('apellido').value = apellido;
            document.getElementById('anio_nacimiento').value = anio;
            document.getElementById('genero').value = genero;
            document.getElementById('btn-submit').name = 'editar';
            document.getElementById('btn-submit').textContent = 'Guardar Cambios';
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
