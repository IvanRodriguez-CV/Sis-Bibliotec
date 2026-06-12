<?php
require_once '../config/conexion.php';
session_start();

// Validación de sesión y rol
if (!isset($_SESSION['id_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

$mensaje = '';
$tipo_mensaje = 'success';

// Crear autor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $codigo = trim($_POST['codigo_autor'] ?? '');
    $generos = trim($_POST['genero_frecuente'] ?? '');
    $anio = (isset($_POST['anio_nacimiento']) && $_POST['anio_nacimiento'] !== '') ? intval($_POST['anio_nacimiento']) : null;
    
    if (!empty($nombre) && !empty($apellido)) {
        // Validar año
        if ($anio !== null && ($anio < 1000 || $anio > date('Y'))) {
            $mensaje = 'El año de nacimiento no es válido.';
            $tipo_mensaje = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO Autores (nombre, apellido, anio_nacimiento, codigo_autor, genero_frecuente) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $apellido, $anio, $codigo, $generos]);
                $mensaje = 'Autor registrado correctamente.';
            } catch (PDOException $e) {
                $mensaje = 'Error al registrar el autor.';
                $tipo_mensaje = 'danger';
            }
        }
    }
}

// Editar autor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id_autor']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $codigo = trim($_POST['codigo_autor'] ?? '');
    $generos = trim($_POST['genero_frecuente'] ?? '');
    $anio = isset($_POST['anio_nacimiento']) && $_POST['anio_nacimiento'] !== '' ? intval($_POST['anio_nacimiento']) : null;
    
    if (!empty($nombre) && !empty($apellido)) {
        try {
            $stmt = $pdo->prepare("UPDATE Autores SET nombre = ?, apellido = ?, anio_nacimiento = ?, codigo_autor = ?, genero_frecuente = ? WHERE id_autor = ?");
            $stmt->execute([$nombre, $apellido, $anio, $codigo, $generos, $id]);
            $mensaje = 'Autor actualizado correctamente.';
        } catch (PDOException $e) {
            $mensaje = 'Error al actualizar el autor.';
            $tipo_mensaje = 'danger';
        }
    }
}

// Eliminar autor
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    try {
        // Verificar si el autor tiene libros asociados
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Libro_Autor WHERE id_autor = ?");
        $stmt->execute([$id]);
        $tiene_libros = $stmt->fetchColumn();
        
        if ($tiene_libros > 0) {
            $mensaje = 'No se puede eliminar el autor porque tiene ' . $tiene_libros . ' libro(s) asociado(s).';
            $tipo_mensaje = 'warning';
        } else {
            $stmt = $pdo->prepare("DELETE FROM Autores WHERE id_autor = ?");
            $stmt->execute([$id]);
            $mensaje = 'Autor eliminado correctamente.';
        }
    } catch (PDOException $e) {
        $mensaje = 'Error al eliminar el autor.';
        $tipo_mensaje = 'danger';
    }
}

// Búsqueda y filtros
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$filtro_letra = isset($_GET['letra']) ? trim($_GET['letra']) : '';

$query = "SELECT a.*, 
          (SELECT COUNT(*) FROM Libro_Autor la WHERE la.id_autor = a.id_autor) as total_libros
          FROM Autores a";

$condiciones = [];
$parametros = [];

if (!empty($busqueda)) {
    $condiciones[] = "(a.nombre LIKE ? OR a.apellido LIKE ? OR CONCAT(a.nombre, ' ', a.apellido) LIKE ? OR a.codigo_autor LIKE ?)";
    $parametros[] = "%$busqueda%";
    $parametros[] = "%$busqueda%";
    $parametros[] = "%$busqueda%";
    $parametros[] = "%$busqueda%";
}

if (!empty($filtro_letra)) {
    $condiciones[] = "a.nombre LIKE ?";
    $parametros[] = "$filtro_letra%";
}

if (!empty($condiciones)) {
    $query .= " WHERE " . implode(" AND ", $condiciones);
}

$query .= " ORDER BY a.apellido ASC, a.nombre ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($parametros);
$autores = $stmt->fetchAll();

// Estadísticas
$total_autores = count($autores);
$autores_con_libros = count(array_filter($autores, fn($a) => $a['total_libros'] > 0));
$autores_sin_libros = $total_autores - $autores_con_libros;

// Obtener letras del abecedario para filtro rápido
$letras = range('A', 'Z');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Autores - Panel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .page-header h2 {
            margin: 0;
            font-weight: 700;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            border-left: 4px solid;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-card.total { border-left-color: #667eea; }
        .stats-card.con-libros { border-left-color: #28a745; }
        .stats-card.sin-libros { border-left-color: #ffc107; }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 1rem 1.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-warning {
            background: #ffc107;
            border: none;
            color: #000;
            font-weight: 600;
        }
        
        .btn-danger {
            background: #dc3545;
            border: none;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead {
            background: #f8f9fa;
        }
        
        .table thead th {
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            color: #495057;
        }
        
        .badge-libros {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .autor-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            margin-right: 0.75rem;
        }
        
        .letras-filtro {
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
            margin-bottom: 1rem;
        }
        
        .letra-btn {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            background: white;
            border: 2px solid #e9ecef;
            color: #495057;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .letra-btn:hover, .letra-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
            transform: scale(1.1);
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
                <i class="fas fa-cogs me-2"></i> Panel Admin
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
        
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : ($tipo_mensaje === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?>"></i>
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-user-edit me-2"></i>Gestión de Autores</h2>
                    <p class="mb-0 mt-2 opacity-75">Administra los autores de la biblioteca</p>
                </div>
                <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#modalNuevoAutor">
                    <i class="fas fa-plus me-2"></i>Nuevo Autor
                </button>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stats-card total">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $total_autores; ?></p>
                            <p class="stats-label mb-0">Total Autores</p>
                        </div>
                        <i class="fas fa-users fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card con-libros">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $autores_con_libros; ?></p>
                            <p class="stats-label mb-0">Con Libros</p>
                        </div>
                        <i class="fas fa-book fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card sin-libros">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $autores_sin_libros; ?></p>
                            <p class="stats-label mb-0">Sin Libros</p>
                        </div>
                        <i class="fas fa-user-slash fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Búsqueda y Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-2"></i>Buscar Autor
                        </label>
                        <input type="text" name="buscar" class="form-control form-control-lg" 
                               placeholder="Buscar por nombre, apellido o código..." 
                               value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                        <?php if (!empty($busqueda) || !empty($filtro_letra)): ?>
                            <a href="autores.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <!-- Filtro por letra -->
                <div class="mt-3">
                    <label class="form-label fw-bold mb-2">
                        <i class="fas fa-filter me-2"></i>Filtrar por inicial:
                    </label>
                    <div class="letras-filtro">
                        <?php foreach ($letras as $letra): ?>
                            <a href="?letra=<?php echo $letra; ?>" 
                               class="letra-btn <?php echo $filtro_letra === $letra ? 'active' : ''; ?>">
                                <?php echo $letra; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Autores -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Autores Registrados
                    <span class="badge bg-light text-dark ms-2"><?php echo $total_autores; ?></span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($autores) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Autor</th>
                                    <th>Código</th>
                                    <th>Año Nacimiento</th>
                                    <th>Géneros</th>
                                    <th>Libros</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($autores as $a): ?>
                                    <?php 
                                        $iniciales = strtoupper(substr($a['nombre'], 0, 1) . substr($a['apellido'], 0, 1));
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <div class="autor-avatar">
                                                    <?php echo $iniciales; ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($a['nombre'] . ' ' . $a['apellido']); ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($a['codigo_autor'])): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($a['codigo_autor']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $a['anio_nacimiento'] ?? '<span class="text-muted">N/A</span>'; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($a['genero_frecuente'])): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($a['genero_frecuente']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge-libros">
                                                <i class="fas fa-book me-1"></i>
                                                <?php echo $a['total_libros']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-warning me-1" 
                                                    onclick='editarAutor(<?php echo json_encode($a); ?>)'
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($a['total_libros'] == 0): ?>
                                                <a href="autores.php?eliminar=<?php echo $a['id_autor']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('¿Estás seguro de eliminar este autor?')"
                                                   title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled 
                                                        title="No se puede eliminar: tiene libros asociados">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h4>No se encontraron autores</h4>
                        <p>Intenta con otros criterios de búsqueda</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal Nuevo Autor -->
    <div class="modal fade" id="modalNuevoAutor" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Nuevo Autor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="autores.php" method="POST">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Nombre <span class="text-danger">*</span></label>
                                <input type="text" name="nombre" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Apellido <span class="text-danger">*</span></label>
                                <input type="text" name="apellido" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Año Nacimiento</label>
                                <input type="number" name="anio_nacimiento" class="form-control" 
                                       min="1000" max="<?php echo date('Y'); ?>" 
                                       placeholder="Ej: 1950">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Código Autor</label>
                                <input type="text" name="codigo_autor" class="form-control" 
                                       placeholder="Ej: AUT001">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Géneros Frecuentes</label>
                                <input type="text" name="genero_frecuente" class="form-control" 
                                       placeholder="Ej: Ficción, Drama, Romance">
                                <small class="text-muted">Separa los géneros con comas</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Registrar Autor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Autor -->
    <div class="modal fade" id="modalEditarAutor" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i>Editar Autor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="autores.php" method="POST">
                    <input type="hidden" name="id_autor" id="edit_id_autor">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Nombre <span class="text-danger">*</span></label>
                                <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Apellido <span class="text-danger">*</span></label>
                                <input type="text" name="apellido" id="edit_apellido" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Año Nacimiento</label>
                                <input type="number" name="anio_nacimiento" id="edit_anio" class="form-control" 
                                       min="1000" max="<?php echo date('Y'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Código Autor</label>
                                <input type="text" name="codigo_autor" id="edit_codigo" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Géneros Frecuentes</label>
                                <input type="text" name="genero_frecuente" id="edit_generos" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="editar" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editarAutor(autor) {
            document.getElementById('edit_id_autor').value = autor.id_autor;
            document.getElementById('edit_nombre').value = autor.nombre;
            document.getElementById('edit_apellido').value = autor.apellido;
            document.getElementById('edit_anio').value = autor.anio_nacimiento || '';
            document.getElementById('edit_codigo').value = autor.codigo_autor || '';
            document.getElementById('edit_generos').value = autor.genero_frecuente || '';
            
            var modal = new bootstrap.Modal(document.getElementById('modalEditarAutor'));
            modal.show();
        }
    </script>
</body>
</html>