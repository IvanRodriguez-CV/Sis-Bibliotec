<?php
require_once '../config/conexion.php';
session_start();
$error = isset($_GET['error']) ? trim($_GET['error']) : '';

// Validación de sesión y rol
if (!isset($_SESSION['id_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

// Crear usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $correo = trim($_POST['correo']);
    $nombre = trim($_POST['nombre_completo']);
    $tipo = $_POST['tipo_usuario'];
    $id_carrera = !empty($_POST['id_carrera']) ? intval($_POST['id_carrera']) : null;
    $contrasenia = isset($_POST['contrasenia']) ? trim($_POST['contrasenia']) : '';
    $carnet = ($tipo === 'admin' ? 'ADM-' : ($tipo === 'Docente' ? 'DOC-' : 'EST-')) . rand(10000, 99999);

    if (!empty($correo) && !empty($nombre) && !empty($contrasenia)) {
        $hash = password_hash($contrasenia, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO Usuario (id_carrera, carnet_codigo, nombre_completo, correo, contrasenia, tipo_usuario) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_carrera, $carnet, $nombre, $correo, $hash, $tipo]);
    }
}

// Editar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id_usuario']);
    $correo = trim($_POST['correo']);
    $nombre = trim($_POST['nombre_completo']);
    $tipo = $_POST['tipo_usuario'];
    $id_carrera = !empty($_POST['id_carrera']) ? intval($_POST['id_carrera']) : null;

    $stmt = $pdo->prepare("UPDATE Usuario SET id_carrera = ?, nombre_completo = ?, correo = ?, tipo_usuario = ? WHERE id_usuario = ?");
    $stmt->execute([$id_carrera, $nombre, $correo, $tipo, $id]);
}

// Eliminar usuario
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    if ($id !== intval($_SESSION['id_usuario'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM Usuario WHERE id_usuario = ?");
            $stmt->execute([$id]);
        } catch (PDOException $e) {
            $error = 'No se puede eliminar el usuario porque tiene registros asociados.';
        }
    }
    $redirectUrl = 'usuarios.php';
    if (!empty($error)) {
        $redirectUrl .= '?error=' . urlencode($error);
    }
    header("Location: $redirectUrl");
    exit;
}

// Buscar usuarios
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$tipo_filtro = isset($_GET['tipo_filtro']) ? trim($_GET['tipo_filtro']) : '';
$query = "SELECT u.*, c.nombre_carrera FROM Usuario u LEFT JOIN Carrera c ON u.id_carrera = c.id_carrera";

if (!empty($busqueda) || !empty($tipo_filtro)) {
    $condiciones = [];
    $parametros = [];
    
    if (!empty($busqueda)) {
        $condiciones[] = "(u.nombre_completo LIKE ? OR u.correo LIKE ? OR u.carnet_codigo LIKE ?)";
        $busquedaWildcard = '%' . $busqueda . '%';
        $parametros[] = $busquedaWildcard;
        $parametros[] = $busquedaWildcard;
        $parametros[] = $busquedaWildcard;
    }
    
    if (!empty($tipo_filtro)) {
        $condiciones[] = "u.tipo_usuario = ?";
        $parametros[] = $tipo_filtro;
    }
    
    $query .= " WHERE " . implode(" AND ", $condiciones);
    $stmt = $pdo->prepare($query . " ORDER BY u.id_usuario DESC");
    $stmt->execute($parametros);
    $usuarios = $stmt->fetchAll();
} else {
    $usuarios = $pdo->query($query . " ORDER BY u.id_usuario DESC")->fetchAll();
}

$carreras = $pdo->query("SELECT * FROM Carrera")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Control de Cuentas de Usuario</title>
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
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger text-center" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <h2 class="fw-bold mb-4 text-center">Mantenimiento de Usuarios del Sistema</h2>

        <!-- Formulario -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Formulario de Control (Alta / Edición)</h5>
                <form action="usuarios.php" method="POST" class="row g-3">
                    <input type="hidden" name="id_usuario" id="id_usuario">
                    <div class="col-md-6">
                        <label for="nombre_completo" class="form-label">Nombre Completo</label>
                        <input type="text" id="nombre_completo" name="nombre_completo" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="correo" class="form-label">Correo Electrónico</label>
                        <input type="email" id="correo" name="correo" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="tipo_usuario" class="form-label">Rol / Permisos</label>
                        <select id="tipo_usuario" name="tipo_usuario" class="form-select" required>
                            <option value="Estudiante">Estudiante</option>
                            <option value="Docente">Docente</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="id_carrera" class="form-label">Carrera Perteneciente</label>
                        <select id="id_carrera" name="id_carrera" class="form-select">
                            <option value="">-- No aplica / Administrativo --</option>
                            <?php foreach ($carreras as $car): ?>
                                <option value="<?php echo $car['id_carrera']; ?>"><?php echo htmlspecialchars($car['nombre_carrera']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12" id="pass-field">
                        <label for="contrasenia" class="form-label">Contraseña de acceso inicial</label>
                        <input type="password" id="contrasenia" name="contrasenia" class="form-control">
                    </div>
                    <div class="col-12">
                        <button type="submit" name="crear" id="btn-submit" class="btn btn-primary w-100">Guardar Usuario</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Barra de búsqueda -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Filtrar Usuarios</h5>
                <form action="usuarios.php" method="GET" class="row g-3">
                    <div class="col-md-6">
                        <input type="text" name="buscar" class="form-control" placeholder="Buscar por nombre, correo o carnet..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    <div class="col-md-4">
                        <select name="tipo_filtro" class="form-select">
                            <option value="">-- Todos los roles --</option>
                            <option value="Estudiante" <?php echo $tipo_filtro === 'Estudiante' ? 'selected' : ''; ?>>Estudiante</option>
                            <option value="Docente" <?php echo $tipo_filtro === 'Docente' ? 'selected' : ''; ?>>Docente</option>
                            <option value="admin" <?php echo $tipo_filtro === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-info w-100">🔍 Filtrar</button>
                        <?php if (!empty($busqueda) || !empty($tipo_filtro)): ?>
                            <a href="usuarios.php" class="btn btn-secondary">Limpiar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Usuarios Registrados</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Código/Carnet</th>
                                <th>Nombre</th>
                                <th>Correo</th>
                                <th>Tipo</th>
                                <th>Carrera</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td><strong><?php echo $u['carnet_codigo']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($u['nombre_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($u['correo']); ?></td>
                                    <td><?php echo $u['tipo_usuario']; ?></td>
                                    <td><?php echo htmlspecialchars($u['nombre_carrera'] ?? 'N/A'); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="cargarDatos(<?php echo $u['id_usuario']; ?>, '<?php echo addslashes($u['nombre_completo']); ?>', '<?php echo addslashes($u['correo']); ?>', '<?php echo $u['tipo_usuario']; ?>', '<?php echo $u['id_carrera']; ?>')">Editar</button>
                                        <?php if ($u['id_usuario'] !== intval($_SESSION['id_usuario'])): ?>
                                                                                      <a href="usuarios.php?eliminar=<?php echo $u['id_usuario']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('¿Baja definitiva de la cuenta?')">Eliminar</a>
                                        <?php endif; ?>
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
        function cargarDatos(id, nombre, correo, tipo, carrera) {
            document.getElementById('id_usuario').value = id;
            document.getElementById('nombre_completo').value = nombre;
            document.getElementById('correo').value = correo;
            document.getElementById('tipo_usuario').value = tipo;
            document.getElementById('id_carrera').value = carrera;
            document.getElementById('pass-field').style.display = 'none';
            document.getElementById('btn-submit').name = 'editar';
            document.getElementById('btn-submit').textContent = 'Actualizar Usuario';
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
