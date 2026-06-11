<?php
require_once '../config/conexion.php';
session_start();

// Validación de sesión y rol
if (!isset($_SESSION['id_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

function obtenerIdsGenerosDesdeTexto($pdo, $texto) {
    $ids = [];
    $texto = trim($texto);
    if ($texto === '') {
        return $ids;
    }

    $generos = array_filter(array_map('trim', explode(',', $texto)));
    $stmtSelect = $pdo->prepare("SELECT id_genero FROM Genero WHERE LOWER(nombre) = LOWER(?)");
    $stmtInsert = $pdo->prepare("INSERT INTO Genero (nombre) VALUES (?)");

    foreach ($generos as $generoNuevo) {
        if ($generoNuevo === '') {
            continue;
        }
        $stmtSelect->execute([$generoNuevo]);
        $row = $stmtSelect->fetch();
        if ($row) {
            $ids[] = $row['id_genero'];
        } else {
            $stmtInsert->execute([$generoNuevo]);
            $ids[] = $pdo->lastInsertId();
        }
    }

    return $ids;
}

// Registrar nueva categoría
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $nombre = trim($_POST['nombre']);
    $generosSeleccionados = isset($_POST['generos']) ? array_map('intval', $_POST['generos']) : [];
    $nuevosGenerosTexto = trim($_POST['nuevos_generos'] ?? '');

    if ($nuevosGenerosTexto !== '') {
        $nuevosIds = obtenerIdsGenerosDesdeTexto($pdo, $nuevosGenerosTexto);
        $generosSeleccionados = array_unique(array_merge($generosSeleccionados, $nuevosIds), SORT_NUMERIC);
    }

    if (!empty($nombre)) {
        $stmt = $pdo->prepare("INSERT INTO Categoria (nombre) VALUES (?)");
        $stmt->execute([$nombre]);
        $idCategoria = $pdo->lastInsertId();

        if (!empty($generosSeleccionados)) {
            $stmtRel = $pdo->prepare("INSERT INTO Categoria_Genero (id_categoria, id_genero) VALUES (?, ?)");
            foreach ($generosSeleccionados as $idGenero) {
                $stmtRel->execute([$idCategoria, intval($idGenero)]);
            }
        }

        header("Location: categorias.php");
        exit;
    }
}

// Modificar categoría existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id_categoria']);
    $nombre = trim($_POST['nombre']);
    $generosSeleccionados = isset($_POST['generos']) ? array_map('intval', $_POST['generos']) : [];
    $nuevosGenerosTexto = trim($_POST['nuevos_generos'] ?? '');

    if ($nuevosGenerosTexto !== '') {
        $nuevosIds = obtenerIdsGenerosDesdeTexto($pdo, $nuevosGenerosTexto);
        $generosSeleccionados = array_unique(array_merge($generosSeleccionados, $nuevosIds), SORT_NUMERIC);
    }

    if (!empty($nombre)) {
        $stmt = $pdo->prepare("UPDATE Categoria SET nombre = ? WHERE id_categoria = ?");
        $stmt->execute([$nombre, $id]);

        $stmtDelete = $pdo->prepare("DELETE FROM Categoria_Genero WHERE id_categoria = ?");
        $stmtDelete->execute([$id]);

        if (!empty($generosSeleccionados)) {
            $stmtRel = $pdo->prepare("INSERT INTO Categoria_Genero (id_categoria, id_genero) VALUES (?, ?)");
            $generosInsertados = [];
            foreach ($generosSeleccionados as $idGenero) {
                $idGeneroInt = intval($idGenero);
                if ($idGeneroInt > 0 && !in_array($idGeneroInt, $generosInsertados, true)) {
                    $stmtRel->execute([$id, $idGeneroInt]);
                    $generosInsertados[] = $idGeneroInt;
                }
            }
        }

        header("Location: categorias.php");
        exit;
    }
}

// Eliminar categoría
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $stmt = $pdo->prepare("DELETE FROM Categoria WHERE id_categoria = ?");
    $stmt->execute([$id]);
    header("Location: categorias.php");
    exit;
}

$generos = $pdo->query("SELECT * FROM Genero ORDER BY nombre")->fetchAll();
$categorias = $pdo->query(
    "SELECT c.id_categoria,
            c.nombre,
            GROUP_CONCAT(g.id_genero ORDER BY g.nombre) AS generos_ids,
            GROUP_CONCAT(g.nombre ORDER BY g.nombre SEPARATOR ', ') AS generos
     FROM Categoria c
     LEFT JOIN Categoria_Genero cg ON c.id_categoria = cg.id_categoria
     LEFT JOIN Genero g ON cg.id_genero = g.id_genero
     GROUP BY c.id_categoria
     ORDER BY c.id_categoria DESC"
)->fetchAll();
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
                 <span class="ms-2">Panel Admin</span>
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
                    <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='reservas.php'?'active fw-semibold':'text-white-50'; ?>" href="reservas.php">Reservas</a></li>
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
                        <label for="nuevos_generos" class="form-label">Agregar nuevos géneros (separados por comas)</label>
                        <input type="text" id="nuevos_generos" name="nuevos_generos" class="form-control" placeholder="Ej: Terror, Ciencia ficción">
                    </div>
                    <!-- La selección de géneros relacionados se gestiona desde libros.php al asignar una categoría a un libro. -->
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
                                        <button class="btn btn-sm btn-warning" onclick="cargarDatos(<?php echo $c['id_categoria']; ?>, '<?php echo addslashes($c['nombre']); ?>', '<?php echo addslashes($c['generos_ids']); ?>')">Editar</button>
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
        function cargarDatos(id, nombre, generosIds) {
            document.getElementById('id_categoria').value = id;
            document.getElementById('nombre').value = nombre;

            const checkboxes = document.querySelectorAll('input[name="generos[]"]');
            const ids = generosIds ? generosIds.split(',').map(item => item.trim()) : [];

            checkboxes.forEach(checkbox => {
                checkbox.checked = ids.includes(checkbox.value);
            });

            document.getElementById('btn-submit').name = 'editar';
            document.getElementById('btn-submit').textContent = 'Guardar Cambios';
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
