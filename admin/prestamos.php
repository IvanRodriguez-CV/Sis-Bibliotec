<?php
require_once '../config/conexion.php';
session_start();

// Validación de sesión y rol
if (!isset($_SESSION['id_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

$error = isset($_GET['error']) ? trim($_GET['error']) : '';
$mensaje = isset($_GET['mensaje']) ? trim($_GET['mensaje']) : '';

// ============================================
// PROCESAR CREACIÓN DE PRÉSTAMO
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_prestamo'])) {
    $id_usuario = intval($_POST['id_usuario']);
    $id_libro = intval($_POST['id_libro']);
    $fecha_entrega = $_POST['fecha_entrega'];
    $fecha_prestamo = !empty($_POST['fecha_prestamo']) ? $_POST['fecha_prestamo'] : date('Y-m-d');
    
    try {
        // Verificar que el libro exista y tenga existencias
        $stmtLibro = $pdo->prepare("SELECT id_libro, titulo, existencias_totales FROM Libro WHERE id_libro = ?");
        $stmtLibro->execute([$id_libro]);
        $libro = $stmtLibro->fetch();
        
        if (!$libro) {
            throw new Exception("El libro no existe.");
        }
        
        if ($libro['existencias_totales'] <= 0) {
            throw new Exception("No hay existencias disponibles de este libro.");
        }
        
        // Verificar que el usuario exista
        $stmtUser = $pdo->prepare("SELECT id_usuario FROM Usuario WHERE id_usuario = ?");
        $stmtUser->execute([$id_usuario]);
        if (!$stmtUser->fetch()) {
            throw new Exception("El usuario no existe.");
        }
        
        // Verificar que el usuario no tenga préstamos vencidos
        $stmtVencidos = $pdo->prepare("SELECT COUNT(*) FROM Prestamo WHERE id_usuario = ? AND estado_prestamo = 'Vencido'");
        $stmtVencidos->execute([$id_usuario]);
        if ($stmtVencidos->fetchColumn() > 0) {
            throw new Exception("El usuario tiene préstamos vencidos. No se puede crear nuevo préstamo.");
        }
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Crear préstamo
        $stmt = $pdo->prepare("INSERT INTO Prestamo (id_usuario, id_libro, fecha_prestamo, fecha_entrega, estado_prestamo) VALUES (?, ?, ?, ?, 'Activo')");
        $stmt->execute([$id_usuario, $id_libro, $fecha_prestamo, $fecha_entrega]);
        
        // Descontar existencia
        $stmtDesc = $pdo->prepare("UPDATE Libro SET existencias_totales = existencias_totales - 1 WHERE id_libro = ? AND existencias_totales > 0");
        $stmtDesc->execute([$id_libro]);
        
        // Si existencias llegan a 0, cambiar estado del libro
        $stmtEstado = $pdo->prepare("UPDATE Libro SET estado = 'Prestado' WHERE id_libro = ? AND existencias_totales = 0");
        $stmtEstado->execute([$id_libro]);
        
        $pdo->commit();
        $mensaje = 'Préstamo creado exitosamente.';
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
    
    $redirectUrl = 'prestamos.php';
    if (!empty($mensaje)) $redirectUrl .= '?mensaje=' . urlencode($mensaje);
    if (!empty($error)) $redirectUrl .= '?error=' . urlencode($error);
    header("Location: $redirectUrl");
    exit;
}

// Procesar cambio de estado de préstamo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
    $id = intval($_POST['id_prestamo']);
    $nuevo_estado = $_POST['estado'];
    $estados_validos = ['Activo', 'Devuelto', 'Vencido', 'Perdido'];
    
    if (in_array($nuevo_estado, $estados_validos)) {
        if ($nuevo_estado === 'Devuelto') {
            $stmtUpdate = $pdo->prepare("UPDATE Prestamo SET estado_prestamo = 'Devuelto', fecha_devolucion = CURDATE() WHERE id_prestamo = ?");
            if ($stmtUpdate->execute([$id])) {
                $stmtLibro = $pdo->prepare("SELECT id_libro FROM Prestamo WHERE id_prestamo = ?");
                $stmtLibro->execute([$id]);
                $id_libro = $stmtLibro->fetchColumn();
                
                if ($id_libro) {
                    $stmtNext = $pdo->prepare("
                        UPDATE Reserva 
                        SET estado = 'Disponible', fecha_disponibilidad = NOW(), fecha_expiracion = DATE_ADD(NOW(), INTERVAL 7 DAY)
                        WHERE id_libro = ? AND estado = 'Pendiente'
                        ORDER BY posicion_cola ASC
                        LIMIT 1
                    ");
                    $stmtNext->execute([$id_libro]);
                }
                $mensaje = 'Préstamo marcado como devuelto correctamente.';
            }
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE Prestamo SET estado_prestamo = ? WHERE id_prestamo = ?");
            if ($stmtUpdate->execute([$nuevo_estado, $id])) {
                $mensaje = 'Estado del préstamo actualizado correctamente.';
            }
        }
    }
    
    $redirectUrl = 'prestamos.php';
    if (!empty($mensaje)) $redirectUrl .= '?mensaje=' . urlencode($mensaje);
    if (!empty($error)) $redirectUrl .= '?error=' . urlencode($error);
    header("Location: $redirectUrl");
    exit;
}

// Eliminar préstamo (solo si está devuelto)
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    try {
        $stmt = $pdo->prepare("SELECT estado_prestamo FROM Prestamo WHERE id_prestamo = ?");
        $stmt->execute([$id]);
        $estado = $stmt->fetchColumn();
        
        if ($estado === 'Devuelto') {
            $stmt = $pdo->prepare("DELETE FROM Prestamo WHERE id_prestamo = ?");
            $stmt->execute([$id]);
            $mensaje = 'Préstamo eliminado correctamente.';
        } else {
            $error = 'Solo se pueden eliminar préstamos devueltos.';
        }
    } catch (PDOException $e) {
        $error = 'No se puede eliminar el préstamo.';
    }
    $redirectUrl = 'prestamos.php';
    if (!empty($mensaje)) $redirectUrl .= '?mensaje=' . urlencode($mensaje);
    if (!empty($error)) $redirectUrl .= '?error=' . urlencode($error);
    header("Location: $redirectUrl");
    exit;
}

// Filtros
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$estado_filtro = isset($_GET['estado_filtro']) ? trim($_GET['estado_filtro']) : '';

// Consulta base
$query = "SELECT 
            p.*,
            l.titulo AS libro_titulo,
            l.codigo AS libro_codigo,
            u.nombre_completo AS usuario_nombre,
            u.carnet_codigo AS usuario_carnet
          FROM Prestamo p
          INNER JOIN Libro l ON p.id_libro = l.id_libro
          INNER JOIN Usuario u ON p.id_usuario = u.id_usuario";

if (!empty($busqueda) || !empty($estado_filtro)) {
    $condiciones = [];
    $parametros = [];
    
    if (!empty($busqueda)) {
        $condiciones[] = "(l.titulo LIKE ? OR l.codigo LIKE ? OR u.nombre_completo LIKE ? OR u.carnet_codigo LIKE ?)";
        $busquedaWildcard = '%' . $busqueda . '%';
        $parametros[] = $busquedaWildcard;
        $parametros[] = $busquedaWildcard;
        $parametros[] = $busquedaWildcard;
        $parametros[] = $busquedaWildcard;
    }
    
    if (!empty($estado_filtro)) {
        $condiciones[] = "p.estado_prestamo = ?";
        $parametros[] = $estado_filtro;
    }
    
    $query .= " WHERE " . implode(" AND ", $condiciones);
    $stmt = $pdo->prepare($query . " ORDER BY p.fecha_prestamo DESC");
    $stmt->execute($parametros);
    $prestamos = $stmt->fetchAll();
} else {
    $prestamos = $pdo->query($query . " ORDER BY p.fecha_prestamo DESC")->fetchAll();
}

// Estadísticas
$stats = [
    'total' => count($prestamos),
    'activos' => 0,
    'vencidos' => 0,
    'devueltos' => 0,
    'perdidos' => 0
];

foreach ($prestamos as $p) {
    $st = strtoupper($p['estado_prestamo']);
    if ($st === 'ACTIVO') $stats['activos']++;
    elseif ($st === 'VENCIDO') $stats['vencidos']++;
    elseif ($st === 'DEVUELTO') $stats['devueltos']++;
    elseif ($st === 'PERDIDO') $stats['perdidos']++;
}

// Cargar usuarios y libros para el formulario de crear préstamo
$usuarios = $pdo->query("SELECT id_usuario, nombre_completo, carnet_codigo FROM Usuario ORDER BY nombre_completo")->fetchAll();
$libros = $pdo->query("SELECT id_libro, titulo, codigo, existencias_totales FROM Libro WHERE existencias_totales > 0 ORDER BY titulo")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Préstamos - Panel Admin</title>
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
        .stats-card.activos { border-left-color: #28a745; }
        .stats-card.vencidos { border-left-color: #dc3545; }
        .stats-card.devueltos { border-left-color: #6c757d; }
        .stats-card.perdidos { border-left-color: #ffc107; }
        
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
        
        .btn-info {
            background: #17a2b8;
            border: none;
            color: white;
            font-weight: 600;
        }
        
        .btn-success {
            background: #28a745;
            border: none;
            font-weight: 600;
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
        
        .prestamo-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-right: 0.75rem;
            color: white;
        }
        
        .prestamo-icon.activo { background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); }
        .prestamo-icon.vencido { background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%); }
        .prestamo-icon.devuelto { background: linear-gradient(135deg, #6c757d 0%, #545b62 100%); }
        .prestamo-icon.perdido { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); }
        
        .libro-info {
            font-weight: 600;
            color: #495057;
            margin: 0;
        }
        
        .libro-codigo {
            font-size: 0.85rem;
            color: #6c757d;
            margin: 0;
        }
        
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
        
        .badge-estado {
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
        
        .badge-estado.activo {
            background: rgba(40, 167, 69, 0.15);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.4);
        }
        
        .badge-estado.vencido {
            background: rgba(220, 53, 69, 0.15);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.4);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(220, 53, 69, 0); }
        }
        
        .badge-estado.devuelto {
            background: rgba(108, 117, 125, 0.15);
            color: #383d41;
            border: 1px solid rgba(108, 117, 125, 0.4);
        }
        
        .badge-estado.perdido {
            background: rgba(255, 193, 7, 0.15);
            color: #856404;
            border: 1px solid rgba(255, 193, 7, 0.4);
        }
        
        .fecha-prestamo {
            font-size: 0.9rem;
            color: #495057;
            white-space: nowrap;
        }
        
        .fecha-prestamo .dia {
            color: #667eea;
            font-weight: 700;
            font-size: 1.1rem;
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
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .info-box-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #6c757d;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        
        .info-box-value {
            font-weight: 600;
            color: #495057;
            font-size: 1rem;
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
                    <h2><i class="fas fa-hand-holding me-2"></i>Gestión de Préstamos</h2>
                    <p class="mb-0 mt-2 opacity-75">Administra los préstamos de libros de la biblioteca</p>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-light text-dark fs-6 px-3 py-2">
                        <i class="fas fa-info-circle me-2"></i>
                        Total: <?php echo $stats['total']; ?> préstamos
                    </span>
                    <!-- BOTÓN NUEVO PRÉSTAMO -->
                    <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#modalCrearPrestamo">
                        <i class="fas fa-plus me-2"></i>Nuevo Préstamo
                    </button>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md">
                <div class="stats-card total">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $stats['total']; ?></p>
                            <p class="stats-label mb-0">Total</p>
                        </div>
                        <i class="fas fa-hand-holding fa-2x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md">
                <div class="stats-card activos">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $stats['activos']; ?></p>
                            <p class="stats-label mb-0">Activos</p>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md">
                <div class="stats-card vencidos">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $stats['vencidos']; ?></p>
                            <p class="stats-label mb-0">Vencidos</p>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md">
                <div class="stats-card devueltos">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $stats['devueltos']; ?></p>
                            <p class="stats-label mb-0">Devueltos</p>
                        </div>
                        <i class="fas fa-undo fa-2x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md">
                <div class="stats-card perdidos">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $stats['perdidos']; ?></p>
                            <p class="stats-label mb-0">Perdidos</p>
                        </div>
                        <i class="fas fa-times-circle fa-2x opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Búsqueda y Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-2"></i>Buscar Préstamo
                        </label>
                        <input type="text" name="buscar" class="form-control form-control-lg" 
                               placeholder="Buscar por libro, usuario o código..." 
                               value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-filter me-2"></i>Estado
                        </label>
                        <select name="estado_filtro" class="form-select form-select-lg">
                            <option value="">-- Todos los estados --</option>
                            <option value="Activo" <?php echo $estado_filtro === 'Activo' ? 'selected' : ''; ?>>✓ Activo</option>
                            <option value="Vencido" <?php echo $estado_filtro === 'Vencido' ? 'selected' : ''; ?>>⚠️ Vencido</option>
                            <option value="Devuelto" <?php echo $estado_filtro === 'Devuelto' ? 'selected' : ''; ?>>↩️ Devuelto</option>
                            <option value="Perdido" <?php echo $estado_filtro === 'Perdido' ? 'selected' : ''; ?>>✕ Perdido</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-2"></i>Filtrar
                        </button>
                        <?php if (!empty($busqueda) || !empty($estado_filtro)): ?>
                            <a href="prestamos.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Préstamos -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Préstamos Registrados
                    <span class="badge bg-light text-dark ms-2"><?php echo count($prestamos); ?></span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($prestamos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Libro</th>
                                    <th>Usuario</th>
                                    <th>Fechas</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prestamos as $p): ?>
                                    <?php 
                                        $estadoLower = strtolower($p['estado_prestamo']);
                                        $fechaPrestamo = strtotime($p['fecha_prestamo']);
                                        $diaP = date('d', $fechaPrestamo);
                                        $mesP = date('M', $fechaPrestamo);
                                        $anioP = date('Y', $fechaPrestamo);
                                        
                                        $fechaEntrega = strtotime($p['fecha_entrega']);
                                        $diaE = date('d', $fechaEntrega);
                                        $mesE = date('M', $fechaEntrega);
                                        
                                        $iconoEstado = '';
                                        switch (strtoupper($p['estado_prestamo'])) {
                                            case 'ACTIVO': $iconoEstado = 'fa-check-circle'; break;
                                            case 'VENCIDO': $iconoEstado = 'fa-exclamation-triangle'; break;
                                            case 'DEVUELTO': $iconoEstado = 'fa-undo'; break;
                                            case 'PERDIDO': $iconoEstado = 'fa-times-circle'; break;
                                        }
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="badge bg-dark fs-6">#<?php echo $p['id_prestamo']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="prestamo-icon <?php echo $estadoLower; ?>">
                                                    <i class="fas fa-book"></i>
                                                </div>
                                                <div>
                                                    <p class="libro-info"><?php echo htmlspecialchars($p['libro_titulo']); ?></p>
                                                    <p class="libro-codigo">
                                                        <i class="fas fa-barcode me-1"></i>
                                                        <?php echo htmlspecialchars($p['libro_codigo']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <p class="usuario-info">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($p['usuario_nombre']); ?>
                                            </p>
                                            <p class="usuario-carnet">
                                                <i class="fas fa-id-card me-1"></i>
                                                <?php echo htmlspecialchars($p['usuario_carnet']); ?>
                                            </p>
                                        </td>
                                        <td>
                                            <div class="fecha-prestamo">
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-calendar-plus me-1"></i>Préstamo:
                                                </small>
                                                <span class="dia"><?php echo $diaP; ?></span> <?php echo $mesP; ?> <?php echo $anioP; ?>
                                                <br>
                                                <small class="text-muted d-block mt-2">
                                                    <i class="fas fa-calendar-check me-1"></i>Entrega:
                                                </small>
                                                <span class="dia"><?php echo $diaE; ?></span> <?php echo $mesE; ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-estado <?php echo $estadoLower; ?>">
                                                <i class="fas <?php echo $iconoEstado; ?>"></i>
                                                <?php echo $p['estado_prestamo']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-warning me-1" 
                                                    onclick='editarPrestamo(<?php echo json_encode($p); ?>)'
                                                    title="Editar estado">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <?php if (strtoupper($p['estado_prestamo']) === 'DEVUELTO'): ?>
                                                <a href="prestamos.php?eliminar=<?php echo $p['id_prestamo']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('¿Eliminar este préstamo definitivamente?')"
                                                   title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-hand-holding"></i>
                        <h4>No se encontraron préstamos</h4>
                        <p>Intenta con otros criterios de búsqueda</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- ============================================ -->
    <!-- MODAL PARA CREAR NUEVO PRÉSTAMO -->
    <!-- ============================================ -->
    <div class="modal fade" id="modalCrearPrestamo" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>Crear Nuevo Préstamo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="prestamos.php" method="POST">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-user me-2"></i>Usuario
                                </label>
                                <select name="id_usuario" class="form-select form-select-lg" required>
                                    <option value="">-- Seleccionar usuario --</option>
                                    <?php foreach ($usuarios as $u): ?>
                                        <option value="<?php echo $u['id_usuario']; ?>">
                                            <?php echo htmlspecialchars($u['nombre_completo']); ?> (<?php echo htmlspecialchars($u['carnet_codigo']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-book me-2"></i>Libro
                                </label>
                                <select name="id_libro" class="form-select form-select-lg" required>
                                    <option value="">-- Seleccionar libro --</option>
                                    <?php foreach ($libros as $l): ?>
                                        <option value="<?php echo $l['id_libro']; ?>">
                                            <?php echo htmlspecialchars($l['titulo']); ?> (<?php echo htmlspecialchars($l['codigo']); ?>) - Disp: <?php echo $l['existencias_totales']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Solo se muestran libros con existencias disponibles.
                                </small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-calendar-plus me-2"></i>Fecha de Préstamo
                                </label>
                                <input type="date" name="fecha_prestamo" class="form-control form-control-lg" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-calendar-check me-2"></i>Fecha de Entrega
                                </label>
                                <input type="date" name="fecha_entrega" class="form-control form-control-lg" 
                                       value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" required>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Al crear el préstamo, se descontará automáticamente 1 unidad de las existencias del libro.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear_prestamo" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Crear Préstamo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para editar -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Préstamo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="prestamos.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id_prestamo" id="edit_id_prestamo">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="info-box">
                                    <div class="info-box-label">
                                        <i class="fas fa-book me-1"></i>Libro
                                    </div>
                                    <div class="info-box-value" id="edit_libro"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <div class="info-box-label">
                                        <i class="fas fa-user me-1"></i>Usuario
                                    </div>
                                    <div class="info-box-value" id="edit_usuario"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <div class="info-box-label">
                                        <i class="fas fa-calendar me-1"></i>Fechas
                                    </div>
                                    <div class="info-box-value" id="edit_fechas"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-exchange-alt me-2"></i>Nuevo Estado:
                                </label>
                                <select id="estado" name="estado" class="form-select form-select-lg" required>
                                    <option value="Activo">✓ Activo - Préstamo en curso</option>
                                    <option value="Vencido">⚠️ Vencido - Fecha de entrega superada</option>
                                    <option value="Devuelto">↩️ Devuelto - Libro devuelto (notifica reservas)</option>
                                    <option value="Perdido">✕ Perdido - Libro no devuelto</option>
                                </select>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Si marcas como "Devuelto", se notificará automáticamente al siguiente usuario en la lista de reservas.
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="cambiar_estado" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editarPrestamo(prestamo) {
            document.getElementById('edit_id_prestamo').value = prestamo.id_prestamo;
            document.getElementById('edit_libro').textContent = prestamo.libro_codigo + ' - ' + prestamo.libro_titulo;
            document.getElementById('edit_usuario').textContent = prestamo.usuario_nombre + ' (' + prestamo.usuario_carnet + ')';
            
            const fechaPrestamo = new Date(prestamo.fecha_prestamo);
            const fechaEntrega = new Date(prestamo.fecha_entrega);
            
            const fechaPrestamoFormateada = fechaPrestamo.toLocaleDateString('es-ES', { 
                day: '2-digit', 
                month: 'short', 
                year: 'numeric'
            });
            
            const fechaEntregaFormateada = fechaEntrega.toLocaleDateString('es-ES', { 
                day: '2-digit', 
                month: 'short', 
                year: 'numeric'
            });
            
            document.getElementById('edit_fechas').textContent = 'Préstamo: ' + fechaPrestamoFormateada + ' | Entrega: ' + fechaEntregaFormateada;
            
            document.getElementById('estado').value = prestamo.estado_prestamo;
            
            var modal = new bootstrap.Modal(document.getElementById('modalEditar'));
            modal.show();
        }
    </script>
</body>
</html>