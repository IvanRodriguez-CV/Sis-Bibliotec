<?php
require_once '../config/conexion.php';
session_start();

// Validación de sesión y rol
if (!isset($_SESSION['id_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

// Registrar nueva carrera
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $nombre = trim($_POST['nombre_carrera']);
    if (!empty($nombre)) {
        $stmt = $pdo->prepare("INSERT INTO Carrera (nombre_carrera) VALUES (?)");
        $stmt->execute([$nombre]);
    }
}

// Modificar carrera existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id_carrera']);
    $nombre = trim($_POST['nombre_carrera']);
    if (!empty($nombre)) {
        $stmt = $pdo->prepare("UPDATE Carrera SET nombre_carrera = ? WHERE id_carrera = ?");
        $stmt->execute([$nombre, $id]);
    }
}

// Eliminar carrera
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $stmt = $pdo->prepare("DELETE FROM Carrera WHERE id_carrera = ?");
    $stmt->execute([$id]);
    header("Location: carreras.php");
    exit;
}

$carreras = $pdo->query("SELECT * FROM Carrera ORDER BY id_carrera DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mantenimiento de Carreras</title>
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
        <h2 class="fw-bold mb-4 text-center">Gestión de Carreras</h2>

        <!-- Formulario -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Crear / Modificar Carrera</h5>
                <form action="carreras.php" method="POST" class="row g-3">
                    <input type="hidden" name="id_carrera" id="id_carrera">
                    <div class="col-12">
                        <label for="nombre_carrera" class="form-label">Nombre de la Carrera</label>
                        <input type="text" id="nombre_carrera" name="nombre_carrera" class="form-control" placeholder="Ej. Ingeniería en Sistemas" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="crear" id="btn-submit" class="btn btn-primary w-100">Agregar Carrera</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Carreras Registradas</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Carrera</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($carreras as $c): ?>
                                <tr>
                                    <td><?php echo $c['id_carrera']; ?></td>
                                    <td><?php echo htmlspecialchars($c['nombre_carrera']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="cargarDatos(<?php echo $c['id_carrera']; ?>, '<?php echo addslashes($c['nombre_carrera']); ?>')">Editar</button>
                                        <a href="carreras.php?eliminar=<?php echo $c['id_carrera']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar esta carrera?')">Eliminar</a>
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
        function cargarDatos(id, nombre) {
            document.getElementById('id_carrera').value = id;
            document.getElementById('nombre_carrera').value = nombre;
            document.getElementById('btn-submit').name = 'editar';
            document.getElementById('btn-submit').textContent = 'Guardar Cambios';
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
