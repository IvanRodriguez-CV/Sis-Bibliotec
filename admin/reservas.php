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

// Cambiar estado de reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
    $id = intval($_POST['id_reserva']);
    $nuevo_estado = $_POST['estado'];
    $estados_validos = ['Pendiente', 'Disponible', 'Reclamada', 'Cancelada', 'Expirada'];
    
    if (in_array($nuevo_estado, $estados_validos)) {
        $stmt = $pdo->prepare("UPDATE Reserva SET estado = ? WHERE id_reserva = ?");
        if ($stmt->execute([$nuevo_estado, $id])) {
            $mensaje = 'Estado de reserva actualizado correctamente.';
        } else {
            $error = 'Error al actualizar el estado.';
        }
    }
    
    $redirectUrl = 'reservas.php';
    if (!empty($mensaje)) $redirectUrl .= '?mensaje=' . urlencode($mensaje);
    if (!empty($error)) $redirectUrl .= '?error=' . urlencode($error);
    header("Location: $redirectUrl");
    exit;
}

// Eliminar reserva
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    try {
        $stmt = $pdo->prepare("DELETE FROM Reserva WHERE id_reserva = ?");
        $stmt->execute([$id]);
        $mensaje = 'Reserva eliminada correctamente.';
    } catch (PDOException $e) {
        $error = 'No se puede eliminar la reserva.';
    }
    $redirectUrl = 'reservas.php';
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
            r.*,
            l.titulo AS libro_titulo,
            l.codigo AS libro_codigo,
            u.nombre_completo AS usuario_nombre,
            u.carnet_codigo AS usuario_carnet,
            u.correo AS usuario_correo,
            (SELECT COUNT(*) FROM Reserva WHERE id_libro = r.id_libro AND estado IN ('Pendiente', 'Disponible') AND posicion_cola < r.posicion_cola) + 1 AS posicion_actual
          FROM Reserva r
          INNER JOIN Libro l ON r.id_libro = l.id_libro
          INNER JOIN Usuario u ON r.id_usuario = u.id_usuario";

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
        $condiciones[] = "r.estado = ?";
        $parametros[] = $estado_filtro;
    }
    
    $query .= " WHERE " . implode(" AND ", $condiciones);
    $stmt = $pdo->prepare($query . " ORDER BY r.fecha_reserva DESC");
    $stmt->execute($parametros);
    $reservas = $stmt->fetchAll();
} else {
    $reservas = $pdo->query($query . " ORDER BY r.fecha_reserva DESC")->fetchAll();
}

// Estadísticas
$stats = [
    'total' => count($reservas),
    'pendientes' => 0,
    'disponibles' => 0,
    'reclamadas' => 0,
    'canceladas' => 0,
    'expiradas' => 0
];

foreach ($reservas as $r) {
    switch (strtoupper($r['estado'])) {
        case 'PENDIENTE': $stats['pendientes']++; break;
        case 'DISPONIBLE': $stats['disponibles']++; break;
        case 'RECLAMADA': $stats['reclamadas']++; break;
        case 'CANCELADA': $stats['canceladas']++; break;
        case 'EXPIRADA': $stats['expiradas']++; break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Reservas - Panel Admin</title>
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
        .stats-card.pendientes { border-left-color: #ffc107; }
        .stats-card.disponibles { border-left-color: #28a745; }
        .stats-card.reclamadas { border-left-color: #17a2b8; }
        .stats-card.canceladas { border-left-color: #6c757d; }
        .stats-card.expiradas { border-left-color: #dc3545; }
        
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
        
        .reserva-icon {
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
        
        .reserva-icon.pendiente { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); }
        .reserva-icon.disponible { background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); }
        .reserva-icon.reclamada { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
        .reserva-icon.cancelada { background: linear-gradient(135deg, #6c757d 0%, #545b62 100%); }
        .reserva-icon.expirada { background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%); }
        
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
        
        .badge-posicion {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.85rem;
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
        
        .badge-estado.pendiente {
            background: rgba(255, 193, 7, 0.15);
            color: #856404;
            border: 1px solid rgba(255, 193, 7, 0.4);
        }
        
        .badge-estado.disponible {
            background: rgba(40, 167, 69, 0.15);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.4);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(40, 167, 69, 0); }
        }
        
        .badge-estado.reclamada {
            background: rgba(23, 162, 184, 0.15);
            color: #0c5460;
            border: 1px solid rgba(23, 162, 184, 0.4);
        }
        
        .badge-estado.cancelada {
            background: rgba(108, 117, 125, 0.15);
            color: #383d41;
            border: 1px solid rgba(108, 117, 125, 0.4);
        }
        
        .badge-estado.expirada {
            background: rgba(220, 53, 69, 0.15);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.4);
        }
        
        .fecha-reserva {
            font-size: 0.9rem;
            color: #495057;
            white-space: nowrap;
        }
        
        .fecha-reserva .dia {
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
                    <h2><i class="fas fa-bookmark me-2"></i>Gestión de Reservas</h2>
                    <p class="mb-0 mt-2 opacity-75">Administra las reservas de libros de la biblioteca</p>
                </div>
                <div>
                    <span class="badge bg-light text-dark fs-6 px-3 py-2">
                        <i class="fas fa-info-circle me-2"></i>
                        Total: <?php echo $stats['total']; ?> reservas
                    </span>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <div class="stats-card total">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $stats['total']; ?></p>
                            <p class="stats-label mb-0">Total</p>
                        </div>
                        <i class="fas fa-bookmark fa-2x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card pendientes">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $stats['pendientes']; ?></p>
                            <p class="stats-label mb-0">Pendientes</p>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card disponibles">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $stats['disponibles']; ?></p>
                            <p class="stats-label mb-0">Disponibles</p>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card reclamadas">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $stats['reclamadas']; ?></p>
                            <p class="stats-label mb-0">Reclamadas</p>
                        </div>
                        <i class="fas fa-hand-holding fa-2x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card canceladas">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $stats['canceladas']; ?></p>
                            <p class="stats-label mb-0">Canceladas</p>
                        </div>
                        <i class="fas fa-times-circle fa-2x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card expiradas">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $stats['expiradas']; ?></p>
                            <p class="stats-label mb-0">Expiradas</p>
                        </div>
                        <i class="fas fa-hourglass-end fa-2x opacity-25"></i>
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
                            <i class="fas fa-search me-2"></i>Buscar Reserva
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
                            <option value="Pendiente" <?php echo $estado_filtro === 'Pendiente' ? 'selected' : ''; ?>>⏳ Pendiente</option>
                            <option value="Disponible" <?php echo $estado_filtro === 'Disponible' ? 'selected' : ''; ?>>✓ Disponible</option>
                            <option value="Reclamada" <?php echo $estado_filtro === 'Reclamada' ? 'selected' : ''; ?>>🤝 Reclamada</option>
                            <option value="Cancelada" <?php echo $estado_filtro === 'Cancelada' ? 'selected' : ''; ?>>✕ Cancelada</option>
                            <option value="Expirada" <?php echo $estado_filtro === 'Expirada' ? 'selected' : ''; ?>>⏰ Expirada</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-2"></i>Filtrar
                        </button>
                        <?php if (!empty($busqueda) || !empty($estado_filtro)): ?>
                            <a href="reservas.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Reservas -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Reservas Registradas
                    <span class="badge bg-light text-dark ms-2"><?php echo count($reservas); ?></span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($reservas) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Libro</th>
                                    <th>Usuario</th>
                                    <th class="text-center">Posición</th>
                                    <th>Fecha Reserva</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservas as $r): ?>
                                    <?php 
                                        $estadoLower = strtolower($r['estado']);
                                        $fecha = strtotime($r['fecha_reserva']);
                                        $dia = date('d', $fecha);
                                        $mes = date('M', $fecha);
                                        $anio = date('Y', $fecha);
                                        $hora = date('H:i', $fecha);
                                        
                                        $iconoEstado = '';
                                        switch (strtoupper($r['estado'])) {
                                            case 'PENDIENTE': $iconoEstado = 'fa-clock'; break;
                                            case 'DISPONIBLE': $iconoEstado = 'fa-check-circle'; break;
                                            case 'RECLAMADA': $iconoEstado = 'fa-hand-holding'; break;
                                            case 'CANCELADA': $iconoEstado = 'fa-times-circle'; break;
                                            case 'EXPIRADA': $iconoEstado = 'fa-hourglass-end'; break;
                                        }
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="badge bg-dark fs-6">#<?php echo $r['id_reserva']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="reserva-icon <?php echo $estadoLower; ?>">
                                                    <i class="fas fa-book"></i>
                                                </div>
                                                <div>
                                                    <p class="libro-info"><?php echo htmlspecialchars($r['libro_titulo']); ?></p>
                                                    <p class="libro-codigo">
                                                        <i class="fas fa-barcode me-1"></i>
                                                        <?php echo htmlspecialchars($r['libro_codigo']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <p class="usuario-info">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($r['usuario_nombre']); ?>
                                            </p>
                                            <p class="usuario-carnet">
                                                <i class="fas fa-id-card me-1"></i>
                                                <?php echo htmlspecialchars($r['usuario_carnet']); ?>
                                            </p>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-posicion">
                                                <i class="fas fa-layer-group me-1"></i>
                                                #<?php echo $r['posicion_actual']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fecha-reserva">
                                                <span class="dia"><?php echo $dia; ?></span> <?php echo $mes; ?> <?php echo $anio; ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i><?php echo $hora; ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-estado <?php echo $estadoLower; ?>">
                                                <i class="fas <?php echo $iconoEstado; ?>"></i>
                                                <?php echo $r['estado']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-warning me-1" 
                                                    onclick='editarReserva(<?php echo json_encode($r); ?>)'
                                                    title="Editar estado">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="reservas.php?eliminar=<?php echo $r['id_reserva']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('¿Eliminar esta reserva definitivamente?')"
                                               title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bookmark"></i>
                        <h4>No se encontraron reservas</h4>
                        <p>Intenta con otros criterios de búsqueda</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal para editar -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Estado de Reserva
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="reservas.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id_reserva" id="edit_id_reserva">
                        
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
                                        <i class="fas fa-calendar me-1"></i>Fecha de Reserva
                                    </div>
                                    <div class="info-box-value" id="edit_fecha"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <div class="info-box-label">
                                        <i class="fas fa-layer-group me-1"></i>Posición en Cola
                                    </div>
                                    <div class="info-box-value" id="edit_posicion"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-box">
                                    <div class="info-box-label">
                                        <i class="fas fa-info-circle me-1"></i>Estado Actual
                                    </div>
                                    <div class="info-box-value" id="edit_estado_actual"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-exchange-alt me-2"></i>Nuevo Estado:
                                </label>
                                <select id="estado" name="estado" class="form-select form-select-lg" required>
                                    <option value="Pendiente">⏳ Pendiente - En espera</option>
                                    <option value="Disponible">✓ Disponible - Listo para reclamar</option>
                                    <option value="Reclamada">🤝 Reclamada - Usuario recogió el libro</option>
                                    <option value="Cancelada">✕ Cancelada - Reserva cancelada</option>
                                    <option value="Expirada">⏰ Expirada - Tiempo de reclamo vencido</option>
                                </select>
                                <small class="text-muted">Selecciona el nuevo estado para esta reserva</small>
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
        function editarReserva(reserva) {
            document.getElementById('edit_id_reserva').value = reserva.id_reserva;
            document.getElementById('edit_libro').textContent = reserva.libro_codigo + ' - ' + reserva.libro_titulo;
            document.getElementById('edit_usuario').textContent = reserva.usuario_nombre + ' (' + reserva.usuario_carnet + ')';
            
            const fecha = new Date(reserva.fecha_reserva);
            const fechaFormateada = fecha.toLocaleDateString('es-ES', { 
                day: '2-digit', 
                month: 'short', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('edit_fecha').textContent = fechaFormateada;
            
            document.getElementById('edit_posicion').textContent = '#' + reserva.posicion_actual + ' en la cola';
            document.getElementById('edit_estado_actual').textContent = reserva.estado;
            document.getElementById('estado').value = reserva.estado;
            
            var modal = new bootstrap.Modal(document.getElementById('modalEditar'));
            modal.show();
        }
    </script>
</body>
</html>