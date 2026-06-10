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
    $codigo = trim($_POST['codigo_autor'] ?? '');
    $generos = trim($_POST['genero_frecuente'] ?? '');
    // Asegurar que año sea entero o NULL para columnas YEAR/INT
    $anio = (isset($_POST['anio_nacimiento']) && $_POST['anio_nacimiento'] !== '') ? intval($_POST['anio_nacimiento']) : null;
    if (!empty($nombre) && !empty($apellido)) {
        $stmt = $pdo->prepare("INSERT INTO Autores (nombre, apellido, anio_nacimiento, codigo_autor, genero_frecuente) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $apellido, $anio, $codigo, $generos]);
    }
}

// Editar autor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id_autor']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $codigo = trim($_POST['codigo_autor'] ?? '');
    $generos = trim($_POST['genero_frecuente'] ?? '');
    $anio = isset($_POST['anio_nacimiento']) && $_POST['anio_nacimiento'] !== '' ? $_POST['anio_nacimiento'] : null;
    $stmt = $pdo->prepare("UPDATE Autores SET nombre = ?, apellido = ?, anio_nacimiento = ?, codigo_autor = ?, genero_frecuente = ? WHERE id_autor = ?");
    $stmt->execute([$nombre, $apellido, $anio, $codigo, $generos, $id]);
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
                ⚙️ <span class="ms-2">Panel Admin</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center gap-2">
                    <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='dashboard.php'?'active fw-semibold':'text-white-50'; ?>" href="dashboard.php">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='carreras.php'?'active fw-semibold':'text-white-50'; ?>" href="carreras.php">Carreras</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='libros.php'?'active fw-semibold':'text-white-50'; ?>" href="libros.php">Libros</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='autores.php'?'active fw-semibold':'text-white-50'; ?>" href="autores.php">Autores</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='categorias.php'?'active fw-semibold':'text-white-50'; ?>" href="categorias.php">Categorías</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='prestamos.php'?'active fw-semibold':'text-white-50'; ?>" href="prestamos.php">Préstamos</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='usuarios.php'?'active fw-semibold':'text-white-50'; ?>" href="usuarios.php">Usuarios</a></li>
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
                        <label for="codigo_autor" class="form-label">Código Autor</label>
                        <input type="text" id="codigo_autor" name="codigo_autor" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label for="genero_frecuente" class="form-label">Géneros Frecuentes</label>
                        <input type="text" id="genero_frecuente" name="genero_frecuente" class="form-control" placeholder="Ej: Ficción, Drama">
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
                                <th>Código</th>
                                <th>Nombre Completo</th>
                                <th>Año Nacimiento</th>
                                <th>Géneros</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($autores as $a): ?>
                                <tr>
                                    <td><?php echo $a['id_autor']; ?></td>
                                    <td><?php echo htmlspecialchars($a['codigo_autor'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($a['nombre'] . ' ' . $a['apellido']); ?></td>
                                    <td><?php echo $a['anio_nacimiento'] ?? 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($a['genero_frecuente'] ?? 'N/A'); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick='cargarDatos(<?php echo $a['id_autor']; ?>, <?php echo json_encode($a['nombre']); ?>, <?php echo json_encode($a['apellido']); ?>, <?php echo json_encode($a['anio_nacimiento'] ?? ''); ?>, <?php echo json_encode($a['codigo_autor'] ?? ''); ?>, <?php echo json_encode($a['genero_frecuente'] ?? ''); ?>);'>Editar</button>
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
        function cargarDatos(id, nombre, apellido, anio, codigo, generos) {
            document.getElementById('id_autor').value = id;
            document.getElementById('nombre').value = nombre;
            document.getElementById('apellido').value = apellido;
            document.getElementById('anio_nacimiento').value = anio;
            document.getElementById('codigo_autor').value = codigo;
            document.getElementById('genero_frecuente').value = generos;
            document.getElementById('btn-submit').name = 'editar';
            document.getElementById('btn-submit').textContent = 'Guardar Cambios';
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
