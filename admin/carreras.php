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

// Registrar nueva carrera
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $nombre = trim($_POST['nombre_carrera']);
    if (!empty($nombre)) {
        try {
            // Verificar si ya existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Carrera WHERE nombre_carrera = ?");
            $stmt->execute([$nombre]);
            
            if ($stmt->fetchColumn() > 0) {
                $mensaje = 'Esta carrera ya está registrada.';
                $tipo_mensaje = 'warning';
            } else {
                $stmt = $pdo->prepare("INSERT INTO Carrera (nombre_carrera) VALUES (?)");
                $stmt->execute([$nombre]);
                $mensaje = 'Carrera registrada correctamente.';
            }
        } catch (PDOException $e) {
            $mensaje = 'Error al registrar la carrera.';
            $tipo_mensaje = 'danger';
        }
    }
}

// Modificar carrera existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id_carrera']);
    $nombre = trim($_POST['nombre_carrera']);
    
    if (!empty($nombre)) {
        try {
            $stmt = $pdo->prepare("UPDATE Carrera SET nombre_carrera = ? WHERE id_carrera = ?");
            $stmt->execute([$nombre, $id]);
            $mensaje = 'Carrera actualizada correctamente.';
        } catch (PDOException $e) {
            $mensaje = 'Error al actualizar la carrera.';
            $tipo_mensaje = 'danger';
        }
    }
}

// Eliminar carrera
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    
    try {
        // Verificar si hay usuarios asociados
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Usuario WHERE id_carrera = ?");
        $stmt->execute([$id]);
        $tiene_usuarios = $stmt->fetchColumn();
        
        if ($tiene_usuarios > 0) {
            $mensaje = 'No se puede eliminar la carrera porque tiene ' . $tiene_usuarios . ' usuario(s) asociado(s).';
            $tipo_mensaje = 'warning';
        } else {
            $stmt = $pdo->prepare("DELETE FROM Carrera WHERE id_carrera = ?");
            $stmt->execute([$id]);
            $mensaje = 'Carrera eliminada correctamente.';
        }
    } catch (PDOException $e) {
        $mensaje = 'Error al eliminar la carrera.';
        $tipo_mensaje = 'danger';
    }
}

// Búsqueda
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM Usuario u WHERE u.id_carrera = c.id_carrera) as total_usuarios
          FROM Carrera c";

if (!empty($busqueda)) {
    $query .= " WHERE c.nombre_carrera LIKE ?";
    $stmt = $pdo->prepare($query . " ORDER BY c.nombre_carrera ASC");
    $stmt->execute(["%$busqueda%"]);
} else {
    $stmt = $pdo->prepare($query . " ORDER BY c.nombre_carrera ASC");
    $stmt->execute();
}

$carreras = $stmt->fetchAll();

// Estadísticas
$total_carreras = count($carreras);
$carreras_con_usuarios = count(array_filter($carreras, fn($c) => $c['total_usuarios'] > 0));
$carreras_sin_usuarios = $total_carreras - $carreras_con_usuarios;
$total_usuarios = array_sum(array_column($carreras, 'total_usuarios'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Carreras - Panel Admin</title>
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
        .stats-card.con-usuarios { border-left-color: #28a745; }
        .stats-card.sin-usuarios { border-left-color: #ffc107; }
        .stats-card.usuarios { border-left-color: #17a2b8; }
        
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
        
        .badge-usuarios {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .carrera-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-right: 0.75rem;
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
        
        .carrera-nombre {
            font-weight: 600;
            color: #495057;
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
                    <h2><i class="fas fa-graduation-cap me-2"></i>Gestión de Carreras</h2>
                    <p class="mb-0 mt-2 opacity-75">Administra las carreras universitarias</p>
                </div>
                <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#modalNuevaCarrera">
                    <i class="fas fa-plus me-2"></i>Nueva Carrera
                </button>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stats-card total">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $total_carreras; ?></p>
                            <p class="stats-label mb-0">Total Carreras</p>
                        </div>
                        <i class="fas fa-graduation-cap fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card con-usuarios">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $carreras_con_usuarios; ?></p>
                            <p class="stats-label mb-0">Con Usuarios</p>
                        </div>
                        <i class="fas fa-user-check fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card sin-usuarios">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $carreras_sin_usuarios; ?></p>
                            <p class="stats-label mb-0">Sin Usuarios</p>
                        </div>
                        <i class="fas fa-user-slash fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card usuarios">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $total_usuarios; ?></p>
                            <p class="stats-label mb-0">Total Usuarios</p>
                        </div>
                        <i class="fas fa-users fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Búsqueda -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-2"></i>Buscar Carrera
                        </label>
                        <input type="text" name="buscar" class="form-control form-control-lg" 
                               placeholder="Buscar por nombre de carrera..." 
                               value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                        <?php if (!empty($busqueda)): ?>
                            <a href="carreras.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Carreras -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Carreras Registradas
                    <span class="badge bg-light text-dark ms-2"><?php echo $total_carreras; ?></span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($carreras) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Carrera</th>
                                    <th>Usuarios</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($carreras as $c): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="badge bg-secondary">#<?php echo $c['id_carrera']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="carrera-icon">
                                                    <i class="fas fa-graduation-cap"></i>
                                                </div>
                                                <span class="carrera-nombre"><?php echo htmlspecialchars($c['nombre_carrera']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($c['total_usuarios'] > 0): ?>
                                                <span class="badge-usuarios">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo $c['total_usuarios']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-user-slash me-1"></i> Sin usuarios
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-warning me-1" 
                                                    onclick='editarCarrera(<?php echo json_encode($c); ?>)'
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($c['total_usuarios'] == 0): ?>
                                                <a href="carreras.php?eliminar=<?php echo $c['id_carrera']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('¿Estás seguro de eliminar esta carrera?')"
                                                   title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled 
                                                        title="No se puede eliminar: tiene usuarios asociados">
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
                        <i class="fas fa-graduation-cap"></i>
                        <h4>No se encontraron carreras</h4>
                        <p>Intenta con otros criterios de búsqueda o registra una nueva carrera</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal Nueva Carrera -->
    <div class="modal fade" id="modalNuevaCarrera" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>Nueva Carrera
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="carreras.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre de la Carrera <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_carrera" class="form-control form-control-lg" 
                                   placeholder="Ej: Ingeniería en Sistemas" required>
                            <small class="text-muted">Ingresa el nombre completo de la carrera</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Registrar Carrera
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Carrera -->
    <div class="modal fade" id="modalEditarCarrera" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Carrera
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="carreras.php" method="POST">
                    <input type="hidden" name="id_carrera" id="edit_id_carrera">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre de la Carrera <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_carrera" id="edit_nombre_carrera" class="form-control form-control-lg" required>
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
        function editarCarrera(carrera) {
            document.getElementById('edit_id_carrera').value = carrera.id_carrera;
            document.getElementById('edit_nombre_carrera').value = carrera.nombre_carrera;
            
            var modal = new bootstrap.Modal(document.getElementById('modalEditarCarrera'));
            modal.show();
        }
    </script>
</body>
</html>