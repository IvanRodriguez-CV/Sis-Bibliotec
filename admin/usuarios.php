<?php
require_once '../config/conexion.php';
session_start();
$error = isset($_GET['error']) ? trim($_GET['error']) : '';
$mensaje = isset($_GET['mensaje']) ? trim($_GET['mensaje']) : '';

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
    $telefono = trim($_POST['telefono'] ?? '');
    $carnet = ($tipo === 'admin' ? 'ADM-' : ($tipo === 'Docente' ? 'DOC-' : 'EST-')) . rand(10000, 99999);

    if (!empty($correo) && !empty($nombre) && !empty($contrasenia)) {
        try {
            // Verificar si el correo ya existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Usuario WHERE correo = ?");
            $stmt->execute([$correo]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'Este correo ya está registrado en el sistema.';
            } else {
                $hash = password_hash($contrasenia, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO Usuario (id_carrera, carnet_codigo, nombre_completo, correo, telefono, contrasenia, tipo_usuario) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id_carrera, $carnet, $nombre, $correo, $telefono, $hash, $tipo]);
                $mensaje = 'Usuario registrado correctamente. Carnet: ' . $carnet;
            }
        } catch (PDOException $e) {
            $error = 'Error al registrar el usuario: ' . $e->getMessage();
        }
    } else {
        $error = 'Complete todos los campos obligatorios.';
    }
    
    $redirectUrl = 'usuarios.php';
    if (!empty($mensaje)) $redirectUrl .= '?mensaje=' . urlencode($mensaje);
    if (!empty($error)) $redirectUrl .= '?error=' . urlencode($error);
    header("Location: $redirectUrl");
    exit;
}

// Editar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id_usuario']);
    $correo = trim($_POST['correo']);
    $nombre = trim($_POST['nombre_completo']);
    $tipo = $_POST['tipo_usuario'];
    $id_carrera = !empty($_POST['id_carrera']) ? intval($_POST['id_carrera']) : null;
    $telefono = trim($_POST['telefono'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE Usuario SET id_carrera = ?, nombre_completo = ?, correo = ?, telefono = ?, tipo_usuario = ? WHERE id_usuario = ?");
        $stmt->execute([$id_carrera, $nombre, $correo, $telefono, $tipo, $id]);
        $mensaje = 'Usuario actualizado correctamente.';
    } catch (PDOException $e) {
        $error = 'Error al actualizar el usuario.';
    }
    
    $redirectUrl = 'usuarios.php';
    if (!empty($mensaje)) $redirectUrl .= '?mensaje=' . urlencode($mensaje);
    if (!empty($error)) $redirectUrl .= '?error=' . urlencode($error);
    header("Location: $redirectUrl");
    exit;
}

// Eliminar usuario
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    if ($id !== intval($_SESSION['id_usuario'])) {
        try {
            // Verificar si tiene préstamos activos
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Prestamo WHERE id_usuario = ? AND estado_prestamo IN ('Activo', 'Vencido')");
            $stmt->execute([$id]);
            $tiene_prestamos = $stmt->fetchColumn();
            
            if ($tiene_prestamos > 0) {
                $error = 'No se puede eliminar el usuario porque tiene préstamos activos.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM Usuario WHERE id_usuario = ?");
                $stmt->execute([$id]);
                $mensaje = 'Usuario eliminado correctamente.';
            }
        } catch (PDOException $e) {
            $error = 'No se puede eliminar el usuario porque tiene registros asociados.';
        }
    } else {
        $error = 'No puedes eliminar tu propia cuenta.';
    }
    
    $redirectUrl = 'usuarios.php';
    if (!empty($mensaje)) $redirectUrl .= '?mensaje=' . urlencode($mensaje);
    if (!empty($error)) $redirectUrl .= '?error=' . urlencode($error);
    header("Location: $redirectUrl");
    exit;
}

// Buscar usuarios
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$tipo_filtro = isset($_GET['tipo_filtro']) ? trim($_GET['tipo_filtro']) : '';
$carrera_filtro = isset($_GET['carrera_filtro']) ? intval($_GET['carrera_filtro']) : 0;

$query = "SELECT u.*, c.nombre_carrera,
          (SELECT COUNT(*) FROM Prestamo p WHERE p.id_usuario = u.id_usuario AND p.estado_prestamo IN ('Activo', 'Vencido')) as prestamos_activos,
          (SELECT COUNT(*) FROM Reserva r WHERE r.id_usuario = u.id_usuario AND r.estado IN ('Pendiente', 'Disponible')) as reservas_activas
          FROM Usuario u 
          LEFT JOIN Carrera c ON u.id_carrera = c.id_carrera";

if (!empty($busqueda) || !empty($tipo_filtro) || $carrera_filtro > 0) {
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
    
    if ($carrera_filtro > 0) {
        $condiciones[] = "u.id_carrera = ?";
        $parametros[] = $carrera_filtro;
    }
    
    $query .= " WHERE " . implode(" AND ", $condiciones);
    $stmt = $pdo->prepare($query . " ORDER BY u.id_usuario DESC");
    $stmt->execute($parametros);
    $usuarios = $stmt->fetchAll();
} else {
    $usuarios = $pdo->query($query . " ORDER BY u.id_usuario DESC")->fetchAll();
}

$carreras = $pdo->query("SELECT * FROM Carrera ORDER BY nombre_carrera ASC")->fetchAll();

// Estadísticas
$stats = [
    'total' => count($usuarios),
    'estudiantes' => 0,
    'docentes' => 0,
    'admins' => 0,
    'bloqueados' => 0
];

foreach ($usuarios as $u) {
    switch ($u['tipo_usuario']) {
        case 'Estudiante': $stats['estudiantes']++; break;
        case 'Docente': $stats['docentes']++; break;
        case 'admin': $stats['admins']++; break;
    }
    if (!empty($u['bloqueado'])) $stats['bloqueados']++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios - Panel Admin</title>
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
        .stats-card.estudiantes { border-left-color: #28a745; }
        .stats-card.docentes { border-left-color: #17a2b8; }
        .stats-card.admins { border-left-color: #ffc107; }
        .stats-card.bloqueados { border-left-color: #dc3545; }
        
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
        
        .usuario-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }
        
        .usuario-avatar.estudiante { background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); }
        .usuario-avatar.docente { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
        .usuario-avatar.admin { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: #000; }
        
        .usuario-info {
            font-weight: 600;
            color: #495057;
            margin: 0;
        }
        
        .usuario-carnet {
            font-size: 0.85rem;
            color: #6c757d;
            margin: 0;
        }
        
        .usuario-correo {
            font-size: 0.9rem;
            color: #495057;
            margin: 0;
        }
        
        .badge-rol {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .badge-rol.estudiante {
            background: rgba(40, 167, 69, 0.15);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.4);
        }
        
        .badge-rol.docente {
            background: rgba(23, 162, 184, 0.15);
            color: #0c5460;
            border: 1px solid rgba(23, 162, 184, 0.4);
        }
        
        .badge-rol.admin {
            background: rgba(255, 193, 7, 0.15);
            color: #856404;
            border: 1px solid rgba(255, 193, 7, 0.4);
        }
        
        .badge-actividad {
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            margin: 0.1rem;
            display: inline-block;
        }
        
        .badge-prestamos {
            background: rgba(255, 193, 7, 0.15);
            color: #856404;
        }
        
        .badge-reservas {
            background: rgba(23, 162, 184, 0.15);
            color: #0c5460;
        }
        
        .badge-carrera {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.3rem 0.7rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
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
        
        .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .form-control:focus, .form-select:focus {
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
        
        .yo-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-left: 0.5rem;
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
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-times-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-users me-2"></i>Gestión de Usuarios</h2>
                    <p class="mb-0 mt-2 opacity-75">Administra las cuentas de usuarios del sistema</p>
                </div>
                <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
                    <i class="fas fa-user-plus me-2"></i>Nuevo Usuario
                </button>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md">
                <div class="stats-card total">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $stats['total']; ?></p>
                            <p class="stats-label mb-0">Total Usuarios</p>
                        </div>
                        <i class="fas fa-users fa-2x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md">
                <div class="stats-card estudiantes">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $stats['estudiantes']; ?></p>
                            <p class="stats-label mb-0">Estudiantes</p>
                        </div>
                        <i class="fas fa-user-graduate fa-2x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md">
                <div class="stats-card docentes">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $stats['docentes']; ?></p>
                            <p class="stats-label mb-0">Docentes</p>
                        </div>
                        <i class="fas fa-chalkboard-teacher fa-2x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md">
                <div class="stats-card admins">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $stats['admins']; ?></p>
                            <p class="stats-label mb-0">Administradores</p>
                        </div>
                        <i class="fas fa-user-shield fa-2x opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Búsqueda y Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-2"></i>Buscar Usuario
                        </label>
                        <input type="text" name="buscar" class="form-control form-control-lg" 
                               placeholder="Nombre, correo o carnet..." 
                               value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-user-tag me-2"></i>Rol
                        </label>
                        <select name="tipo_filtro" class="form-select form-select-lg">
                            <option value="">-- Todos los roles --</option>
                            <option value="Estudiante" <?php echo $tipo_filtro === 'Estudiante' ? 'selected' : ''; ?>>🎓 Estudiante</option>
                            <option value="Docente" <?php echo $tipo_filtro === 'Docente' ? 'selected' : ''; ?>>👨‍🏫 Docente</option>
                            <option value="admin" <?php echo $tipo_filtro === 'admin' ? 'selected' : ''; ?>>🛡️ Administrador</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-graduation-cap me-2"></i>Carrera
                        </label>
                        <select name="carrera_filtro" class="form-select form-select-lg">
                            <option value="0">-- Todas las carreras --</option>
                            <?php foreach ($carreras as $car): ?>
                                <option value="<?php echo $car['id_carrera']; ?>" <?php echo $carrera_filtro == $car['id_carrera'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($car['nombre_carrera']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-filter me-2"></i>Filtrar
                        </button>
                        <?php if (!empty($busqueda) || !empty($tipo_filtro) || $carrera_filtro > 0): ?>
                            <a href="usuarios.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Usuarios -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Usuarios Registrados
                    <span class="badge bg-light text-dark ms-2"><?php echo count($usuarios); ?></span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($usuarios) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Usuario</th>
                                    <th>Contacto</th>
                                    <th class="text-center">Rol</th>
                                    <th>Carrera</th>
                                    <th class="text-center">Actividad</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $u): ?>
                                    <?php 
                                        $tipoLower = strtolower($u['tipo_usuario']);
                                        $iniciales = '';
                                        $nombreParts = explode(' ', trim($u['nombre_completo']));
                                        if (count($nombreParts) >= 2) {
                                            $iniciales = strtoupper(substr($nombreParts[0], 0, 1) . substr(end($nombreParts), 0, 1));
                                        } else {
                                            $iniciales = strtoupper(substr($u['nombre_completo'], 0, 2));
                                        }
                                        
                                        $iconoRol = '';
                                        switch ($tipoLower) {
                                            case 'estudiante': $iconoRol = 'fa-user-graduate'; break;
                                            case 'docente': $iconoRol = 'fa-chalkboard-teacher'; break;
                                            case 'admin': $iconoRol = 'fa-user-shield'; break;
                                        }
                                        
                                        $esYo = ($u['id_usuario'] == $_SESSION['id_usuario']);
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <div class="usuario-avatar <?php echo $tipoLower; ?>">
                                                    <?php echo $iniciales; ?>
                                                </div>
                                                <div>
                                                    <p class="usuario-info">
                                                        <?php echo htmlspecialchars($u['nombre_completo']); ?>
                                                        <?php if ($esYo): ?>
                                                            <span class="yo-badge">TÚ</span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <p class="usuario-carnet">
                                                        <i class="fas fa-id-card me-1"></i>
                                                        <?php echo htmlspecialchars($u['carnet_codigo']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <p class="usuario-correo">
                                                <i class="fas fa-envelope me-1"></i>
                                                <?php echo htmlspecialchars($u['correo']); ?>
                                            </p>
                                            <?php if (!empty($u['telefono'])): ?>
                                                <p class="usuario-carnet">
                                                    <i class="fas fa-phone me-1"></i>
                                                    <?php echo htmlspecialchars($u['telefono']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-rol <?php echo $tipoLower; ?>">
                                                <i class="fas <?php echo $iconoRol; ?>"></i>
                                                <?php echo $u['tipo_usuario']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($u['nombre_carrera'])): ?>
                                                <span class="badge-carrera">
                                                    <i class="fas fa-graduation-cap me-1"></i>
                                                    <?php echo htmlspecialchars($u['nombre_carrera']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-briefcase me-1"></i> Administrativo
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($u['prestamos_activos'] > 0): ?>
                                                <span class="badge-actividad badge-prestamos">
                                                    <i class="fas fa-book me-1"></i><?php echo $u['prestamos_activos']; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($u['reservas_activas'] > 0): ?>
                                                <span class="badge-actividad badge-reservas">
                                                    <i class="fas fa-bookmark me-1"></i><?php echo $u['reservas_activas']; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($u['prestamos_activos'] == 0 && $u['reservas_activas'] == 0): ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-warning me-1" 
                                                    onclick='editarUsuario(<?php echo json_encode($u); ?>)'
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if (!$esYo): ?>
                                                <a href="usuarios.php?eliminar=<?php echo $u['id_usuario']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('¿Estás seguro de eliminar este usuario? Esta acción no se puede deshacer.')"
                                                   title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled title="No puedes eliminar tu propia cuenta">
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
                        <i class="fas fa-users"></i>
                        <h4>No se encontraron usuarios</h4>
                        <p>Intenta con otros criterios de búsqueda o registra un nuevo usuario</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal Nuevo Usuario -->
    <div class="modal fade" id="modalNuevoUsuario" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Nuevo Usuario
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="usuarios.php" method="POST">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Nombre Completo <span class="text-danger">*</span></label>
                                <input type="text" name="nombre_completo" class="form-control form-control-lg" 
                                       placeholder="Ej: Juan Pérez García" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Correo Electrónico <span class="text-danger">*</span></label>
                                <input type="email" name="correo" class="form-control" 
                                       placeholder="usuario@ejemplo.com" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Teléfono</label>
                                <input type="text" name="telefono" class="form-control" 
                                       placeholder="Ej: 7000-1234">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Rol / Permisos <span class="text-danger">*</span></label>
                                <select name="tipo_usuario" class="form-select" required>
                                    <option value="Estudiante">🎓 Estudiante</option>
                                    <option value="Docente">👨‍🏫 Docente</option>
                                    <option value="admin">🛡️ Administrador</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Carrera</label>
                                <select name="id_carrera" class="form-select">
                                    <option value="">-- No aplica / Administrativo --</option>
                                    <?php foreach ($carreras as $car): ?>
                                        <option value="<?php echo $car['id_carrera']; ?>">
                                            <?php echo htmlspecialchars($car['nombre_carrera']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Contraseña Inicial <span class="text-danger">*</span></label>
                                <input type="password" name="contrasenia" class="form-control" 
                                       placeholder="Mínimo 6 caracteres" required minlength="6">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Se generará automáticamente un carnet único para el usuario
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Registrar Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Usuario -->
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i>Editar Usuario
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="usuarios.php" method="POST">
                    <input type="hidden" name="id_usuario" id="edit_id_usuario">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <div class="info-box">
                                    <div class="info-box-label">
                                        <i class="fas fa-id-card me-1"></i>Carnet (No editable)
                                    </div>
                                    <div class="info-box-value" id="edit_carnet"></div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Nombre Completo <span class="text-danger">*</span></label>
                                <input type="text" name="nombre_completo" id="edit_nombre" class="form-control form-control-lg" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Correo Electrónico <span class="text-danger">*</span></label>
                                <input type="email" name="correo" id="edit_correo" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Teléfono</label>
                                <input type="text" name="telefono" id="edit_telefono" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Rol / Permisos <span class="text-danger">*</span></label>
                                <select name="tipo_usuario" id="edit_tipo" class="form-select" required>
                                    <option value="Estudiante">🎓 Estudiante</option>
                                    <option value="Docente">👨‍🏫 Docente</option>
                                    <option value="admin">🛡️ Administrador</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Carrera</label>
                                <select name="id_carrera" id="edit_carrera" class="form-select">
                                    <option value="">-- No aplica / Administrativo --</option>
                                    <?php foreach ($carreras as $car): ?>
                                        <option value="<?php echo $car['id_carrera']; ?>">
                                            <?php echo htmlspecialchars($car['nombre_carrera']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
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
        function editarUsuario(usuario) {
            document.getElementById('edit_id_usuario').value = usuario.id_usuario;
            document.getElementById('edit_carnet').textContent = usuario.carnet_codigo;
            document.getElementById('edit_nombre').value = usuario.nombre_completo;
            document.getElementById('edit_correo').value = usuario.correo;
            document.getElementById('edit_telefono').value = usuario.telefono || '';
            document.getElementById('edit_tipo').value = usuario.tipo_usuario;
            document.getElementById('edit_carrera').value = usuario.id_carrera || '';
            
            var modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
            modal.show();
        }
    </script>
</body>
</html>