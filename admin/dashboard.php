<?php
require_once '../config/conexion.php';
session_start();

// Validación de sesión y rol
if (!isset($_SESSION['id_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

// ============================================
// ESTADÍSTICAS GENERALES
// ============================================

// Libros
$total_libros = $pdo->query("SELECT SUM(existencias_totales) FROM Libro")->fetchColumn() ?? 0;
$total_titulos = $pdo->query("SELECT COUNT(*) FROM Libro")->fetchColumn();
$libros_sin_stock = $pdo->query("SELECT COUNT(*) FROM Libro WHERE existencias_totales = 0")->fetchColumn();

// Usuarios
$total_usuarios = $pdo->query("SELECT COUNT(*) FROM Usuario")->fetchColumn();
$total_estudiantes = $pdo->query("SELECT COUNT(*) FROM Usuario WHERE tipo_usuario = 'Estudiante'")->fetchColumn();
$total_docentes = $pdo->query("SELECT COUNT(*) FROM Usuario WHERE tipo_usuario = 'Docente'")->fetchColumn();
$total_admins = $pdo->query("SELECT COUNT(*) FROM Usuario WHERE tipo_usuario = 'admin'")->fetchColumn();

// Préstamos
$total_prestamos_activos = $pdo->query("SELECT COUNT(*) FROM Prestamo WHERE estado_prestamo = 'Activo'")->fetchColumn();
$total_prestamos_vencidos = $pdo->query("SELECT COUNT(*) FROM Prestamo WHERE estado_prestamo = 'Vencido'")->fetchColumn();
$total_prestamos_devueltos = $pdo->query("SELECT COUNT(*) FROM Prestamo WHERE estado_prestamo = 'Devuelto'")->fetchColumn();
$total_prestamos_perdidos = $pdo->query("SELECT COUNT(*) FROM Prestamo WHERE estado_prestamo = 'Perdido'")->fetchColumn();

// Reservas
$total_reservas_pendientes = $pdo->query("SELECT COUNT(*) FROM Reserva WHERE estado = 'Pendiente'")->fetchColumn();
$total_reservas_disponibles = $pdo->query("SELECT COUNT(*) FROM Reserva WHERE estado = 'Disponible'")->fetchColumn();
$total_reservas_reclamadas = $pdo->query("SELECT COUNT(*) FROM Reserva WHERE estado = 'Reclamada'")->fetchColumn();

// Autores y Categorías
$total_autores = $pdo->query("SELECT COUNT(*) FROM Autores")->fetchColumn();
$total_categorias = $pdo->query("SELECT COUNT(*) FROM Categoria")->fetchColumn();
$total_carreras = $pdo->query("SELECT COUNT(*) FROM Carrera")->fetchColumn();
$total_generos = $pdo->query("SELECT COUNT(*) FROM Genero")->fetchColumn();

// ============================================
// ACTIVIDAD RECIENTE (últimos 5 préstamos)
// ============================================
$ultimos_prestamos = $pdo->query("
    SELECT p.*, l.titulo AS libro_titulo, l.codigo AS libro_codigo,
           u.nombre_completo AS usuario_nombre, u.carnet_codigo AS usuario_carnet
    FROM Prestamo p
    INNER JOIN Libro l ON p.id_libro = l.id_libro
    INNER JOIN Usuario u ON p.id_usuario = u.id_usuario
    ORDER BY p.fecha_prestamo DESC
    LIMIT 5
")->fetchAll();

// ============================================
// RESERVAS QUE REQUIEREN ATENCIÓN (Disponibles)
// ============================================
$reservas_atencion = $pdo->query("
    SELECT r.*, l.titulo AS libro_titulo, l.codigo AS libro_codigo,
           u.nombre_completo AS usuario_nombre, u.carnet_codigo AS usuario_carnet,
           DATEDIFF(r.fecha_expiracion, NOW()) AS dias_restantes
    FROM Reserva r
    INNER JOIN Libro l ON r.id_libro = l.id_libro
    INNER JOIN Usuario u ON r.id_usuario = u.id_usuario
    WHERE r.estado = 'Disponible'
    ORDER BY r.fecha_expiracion ASC
    LIMIT 5
")->fetchAll();

// ============================================
// LIBROS MÁS PRESTADOS (Top 5)
// ============================================
$libros_populares = $pdo->query("
    SELECT l.id_libro, l.titulo, l.codigo, l.imagen,
           COUNT(p.id_prestamo) AS total_prestamos,
           (SELECT COUNT(*) FROM Prestamo WHERE id_libro = l.id_libro AND estado_prestamo IN ('Activo', 'Vencido')) AS prestamos_activos
    FROM Libro l
    INNER JOIN Prestamo p ON p.id_libro = l.id_libro
    GROUP BY l.id_libro
    ORDER BY total_prestamos DESC
    LIMIT 5
")->fetchAll();

// ============================================
// ALERTAS IMPORTANTES
// ============================================
$alertas = [];

if ($total_prestamos_vencidos > 0) {
    $alertas[] = [
        'tipo' => 'danger',
        'icono' => 'fa-exclamation-triangle',
        'titulo' => 'Préstamos Vencidos',
        'mensaje' => "Hay $total_prestamos_vencidos préstamo(s) vencido(s) que requieren atención.",
        'enlace' => 'prestamos.php?estado_filtro=Vencido'
    ];
}

if ($total_reservas_disponibles > 0) {
    $alertas[] = [
        'tipo' => 'warning',
        'icono' => 'fa-bell',
        'titulo' => 'Reservas Disponibles',
        'mensaje' => "Hay $total_reservas_disponibles reserva(s) lista(s) para ser reclamada(s).",
        'enlace' => 'reservas.php?estado_filtro=Disponible'
    ];
}

if ($libros_sin_stock > 0) {
    $alertas[] = [
        'tipo' => 'info',
        'icono' => 'fa-box-open',
        'titulo' => 'Libros Sin Stock',
        'mensaje' => "Hay $libros_sin_stock libro(s) sin existencias disponibles.",
        'enlace' => 'libros.php'
    ];
}

if ($total_prestamos_perdidos > 0) {
    $alertas[] = [
        'tipo' => 'secondary',
        'icono' => 'fa-times-circle',
        'titulo' => 'Libros Perdidos',
        'mensaje' => "Hay $total_prestamos_perdidos préstamo(s) marcado(s) como perdido(s).",
        'enlace' => 'prestamos.php?estado_filtro=Perdido'
    ];
}

// Datos del admin actual
$stmtAdmin = $pdo->prepare("SELECT nombre_completo, correo, carnet_codigo FROM Usuario WHERE id_usuario = ?");
$stmtAdmin->execute([$_SESSION['id_usuario']]);
$adminInfo = $stmtAdmin->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Administrativo - Dashboard</title>
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
        
        /* Welcome Header */
        .welcome-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2.5rem 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        
        .welcome-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: 10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        
        .welcome-header h1 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }
        
        .welcome-header p {
            opacity: 0.9;
            margin: 0;
            position: relative;
            z-index: 2;
        }
        
        .welcome-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            border: 3px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }
        
        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 5px solid;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .stats-card.libros { border-left-color: #667eea; }
        .stats-card.usuarios { border-left-color: #28a745; }
        .stats-card.prestamos { border-left-color: #ffc107; }
        .stats-card.reservas { border-left-color: #17a2b8; }
        .stats-card.autores { border-left-color: #6c757d; }
        .stats-card.categorias { border-left-color: #fd7e14; }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            flex-shrink: 0;
        }
        
        .stats-icon.libros { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stats-icon.usuarios { background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); }
        .stats-icon.prestamos { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); }
        .stats-icon.reservas { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
        .stats-icon.autores { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); }
        .stats-icon.categorias { background: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%); }
        
        .stats-number {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0;
            color: #343a40;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin: 0;
        }
        
        .stats-sublabel {
            font-size: 0.75rem;
            color: #adb5bd;
            margin: 0;
        }
        
        /* Section Headers */
        .section-header {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            border-left: 4px solid #667eea;
        }
        
        .section-header h4 {
            margin: 0;
            font-weight: 700;
            color: #343a40;
        }
        
        /* Cards */
        .card-custom {
            background: white;
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            height: 100%;
        }
        
        .card-custom-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 15px 15px 0 0;
        }
        
        .card-custom-header h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .card-custom-body {
            padding: 1.5rem;
        }
        
        /* Quick Access */
        .quick-access-item {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .quick-access-item:hover {
            transform: translateY(-5px);
            border-color: #667eea;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.2);
            color: inherit;
        }
        
        .quick-access-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 1rem;
        }
        
        .quick-access-icon.libros { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .quick-access-icon.usuarios { background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); }
        .quick-access-icon.prestamos { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); }
        .quick-access-icon.reservas { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
        .quick-access-icon.autores { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); }
        .quick-access-icon.categorias { background: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%); }
        .quick-access-icon.carreras { background: linear-gradient(135deg, #e91e63 0%, #c2185b 100%); }
        .quick-access-icon.reportes { background: linear-gradient(135deg, #9c27b0 0%, #7b1fa2 100%); }
        
        .quick-access-title {
            font-weight: 600;
            margin: 0;
            color: #343a40;
        }
        
        .quick-access-desc {
            font-size: 0.8rem;
            color: #6c757d;
            margin: 0.25rem 0 0 0;
        }
        
        /* Activity Item */
        .activity-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f3f5;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: white;
            flex-shrink: 0;
            margin-right: 1rem;
        }
        
        .activity-icon.prestamo { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .activity-icon.reserva { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
        .activity-icon.vencido { background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%); }
        
        .activity-info {
            flex: 1;
            min-width: 0;
        }
        
        .activity-title {
            font-weight: 600;
            margin: 0;
            font-size: 0.9rem;
            color: #343a40;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .activity-subtitle {
            font-size: 0.8rem;
            color: #6c757d;
            margin: 0;
        }
        
        .activity-time {
            font-size: 0.75rem;
            color: #adb5bd;
            white-space: nowrap;
        }
        
        /* Alert Card */
        .alert-card {
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
            border: none;
        }
        
        .alert-card:hover {
            transform: translateX(5px);
        }
        
        .alert-card.danger {
            background: rgba(220, 53, 69, 0.1);
            border-left: 4px solid #dc3545;
        }
        
        .alert-card.warning {
            background: rgba(255, 193, 7, 0.1);
            border-left: 4px solid #ffc107;
        }
        
        .alert-card.info {
            background: rgba(23, 162, 184, 0.1);
            border-left: 4px solid #17a2b8;
        }
        
        .alert-card.secondary {
            background: rgba(108, 117, 125, 0.1);
            border-left: 4px solid #6c757d;
        }
        
        .alert-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .alert-card.danger .alert-icon { color: #dc3545; }
        .alert-card.warning .alert-icon { color: #ffc107; }
        .alert-card.info .alert-icon { color: #17a2b8; }
        .alert-card.secondary .alert-icon { color: #6c757d; }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-title {
            font-weight: 700;
            margin: 0;
            font-size: 0.95rem;
        }
        
        .alert-message {
            margin: 0;
            font-size: 0.85rem;
            color: #495057;
        }
        
        .alert-action {
            text-decoration: none;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .alert-card.danger .alert-action { background: #dc3545; color: white; }
        .alert-card.warning .alert-action { background: #ffc107; color: #000; }
        .alert-card.info .alert-action { background: #17a2b8; color: white; }
        .alert-card.secondary .alert-action { background: #6c757d; color: white; }
        
        /* Popular Book */
        .popular-book-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-radius: 10px;
            transition: background 0.2s ease;
            margin-bottom: 0.5rem;
        }
        
        .popular-book-item:hover {
            background: #f8f9fa;
        }
        
        .popular-rank {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .popular-rank.top-1 { background: linear-gradient(135deg, #ffd700 0%, #ffaa00 100%); }
        .popular-rank.top-2 { background: linear-gradient(135deg, #c0c0c0 0%, #a8a8a8 100%); }
        .popular-rank.top-3 { background: linear-gradient(135deg, #cd7f32 0%, #b87333 100%); }
        
        .popular-book-cover {
            width: 40px;
            height: 55px;
            border-radius: 6px;
            object-fit: cover;
            margin-right: 1rem;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .popular-book-placeholder {
            width: 40px;
            height: 55px;
            border-radius: 6px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .popular-info {
            flex: 1;
            min-width: 0;
        }
        
        .popular-title {
            font-weight: 600;
            margin: 0;
            font-size: 0.9rem;
            color: #343a40;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .popular-code {
            font-size: 0.75rem;
            color: #6c757d;
            margin: 0;
        }
        
        .popular-count {
            text-align: right;
            flex-shrink: 0;
            margin-left: 0.5rem;
        }
        
        .popular-count-number {
            font-weight: 700;
            color: #667eea;
            font-size: 1.1rem;
            display: block;
        }
        
        .popular-count-label {
            font-size: 0.7rem;
            color: #6c757d;
        }
        
        /* Empty State */
        .empty-state-mini {
            text-align: center;
            padding: 2rem 1rem;
            color: #6c757d;
        }
        
        .empty-state-mini i {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            opacity: 0.3;
        }
        
        /* Reserva urgente */
        .reserva-urgente {
            padding: 0.75rem;
            border-radius: 10px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            margin-bottom: 0.5rem;
        }
        
        .reserva-urgente .urgente-dias {
            color: #856404;
            font-weight: 700;
            font-size: 0.85rem;
        }
        
        .reserva-urgente .urgente-dias.critico {
            color: #dc3545;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .welcome-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.8rem;
            }
            
            .welcome-header h1 {
                font-size: 1.5rem;
            }
            
            .stats-number {
                font-size: 1.8rem;
            }
            
            .stats-icon {
                width: 50px;
                height: 50px;
                font-size: 1.4rem;
            }
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
        
        <!-- Welcome Header -->
        <div class="welcome-header">
            <div class="d-flex align-items-center gap-4 position-relative" style="z-index: 2;">
                <div class="welcome-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="flex-grow-1">
                    <h1>¡Bienvenido, <?php echo htmlspecialchars(explode(' ', $adminInfo['nombre_completo'])[0]); ?>!</h1>
                    <p class="mb-1">
                        <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($adminInfo['correo']); ?>
                        <span class="ms-3"><i class="fas fa-id-card me-2"></i><?php echo htmlspecialchars($adminInfo['carnet_codigo']); ?></span>
                    </p>
                    <p class="mb-0 small opacity-75">
                        <i class="fas fa-calendar-day me-1"></i>
                        <?php 
                            setlocale(LC_TIME, 'es_ES.UTF-8');
                            echo strftime('%A, %d de %B de %Y', time());
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Alertas Importantes -->
        <?php if (!empty($alertas)): ?>
            <div class="section-header d-flex align-items-center gap-2">
                <i class="fas fa-exclamation-circle text-danger"></i>
                <h4>Alertas que Requieren Atención</h4>
            </div>
            <?php foreach ($alertas as $alerta): ?>
                <div class="alert-card <?php echo $alerta['tipo']; ?>">
                    <i class="fas <?php echo $alerta['icono']; ?> alert-icon"></i>
                    <div class="alert-content">
                        <p class="alert-title"><?php echo $alerta['titulo']; ?></p>
                        <p class="alert-message"><?php echo $alerta['mensaje']; ?></p>
                    </div>
                    <a href="<?php echo $alerta['enlace']; ?>" class="alert-action">
                        <i class="fas fa-arrow-right me-1"></i> Ver
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Estadísticas Generales -->
        <div class="section-header d-flex align-items-center gap-2 mt-4">
            <i class="fas fa-chart-line text-primary"></i>
            <h4>Resumen General</h4>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stats-card libros">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo number_format($total_libros); ?></p>
                            <p class="stats-label">Ejemplares Totales</p>
                            <p class="stats-sublabel"><?php echo $total_titulos; ?> títulos únicos</p>
                        </div>
                        <div class="stats-icon libros">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card usuarios">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo number_format($total_usuarios); ?></p>
                            <p class="stats-label">Usuarios Registrados</p>
                            <p class="stats-sublabel">
                                <i class="fas fa-user-graduate me-1"></i><?php echo $total_estudiantes; ?>
                                <i class="fas fa-chalkboard-teacher ms-2 me-1"></i><?php echo $total_docentes; ?>
                                <i class="fas fa-user-shield ms-2 me-1"></i><?php echo $total_admins; ?>
                            </p>
                        </div>
                        <div class="stats-icon usuarios">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card prestamos">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $total_prestamos_activos; ?></p>
                            <p class="stats-label">Préstamos Activos</p>
                            <p class="stats-sublabel">
                                <i class="fas fa-exclamation-triangle text-danger me-1"></i><?php echo $total_prestamos_vencidos; ?> vencidos
                            </p>
                        </div>
                        <div class="stats-icon prestamos">
                            <i class="fas fa-hand-holding"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card reservas">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $total_reservas_pendientes + $total_reservas_disponibles; ?></p>
                            <p class="stats-label">Reservas Activas</p>
                            <p class="stats-sublabel">
                                <i class="fas fa-clock me-1"></i><?php echo $total_reservas_pendientes; ?>
                                <i class="fas fa-check-circle ms-2 me-1"></i><?php echo $total_reservas_disponibles; ?>
                            </p>
                        </div>
                        <div class="stats-icon reservas">
                            <i class="fas fa-bookmark"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card autores">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $total_autores; ?></p>
                            <p class="stats-label">Autores</p>
                            <p class="stats-sublabel">en el catálogo</p>
                        </div>
                        <div class="stats-icon autores">
                            <i class="fas fa-user-edit"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card categorias">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $total_categorias; ?></p>
                            <p class="stats-label">Categorías</p>
                            <p class="stats-sublabel"><?php echo $total_generos; ?> géneros • <?php echo $total_carreras; ?> carreras</p>
                        </div>
                        <div class="stats-icon categorias">
                            <i class="fas fa-tags"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accesos Rápidos -->
        <div class="section-header d-flex align-items-center gap-2">
            <i class="fas fa-bolt text-warning"></i>
            <h4>Accesos Rápidos</h4>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-sm-6">
                <a href="libros.php" class="quick-access-item">
                    <div class="quick-access-icon libros">
                        <i class="fas fa-book"></i>
                    </div>
                    <p class="quick-access-title">Libros</p>
                    <p class="quick-access-desc">Gestionar catálogo</p>
                </a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="prestamos.php" class="quick-access-item">
                    <div class="quick-access-icon prestamos">
                        <i class="fas fa-hand-holding"></i>
                    </div>
                    <p class="quick-access-title">Préstamos</p>
                    <p class="quick-access-desc">Administrar préstamos</p>
                </a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="reservas.php" class="quick-access-item">
                    <div class="quick-access-icon reservas">
                        <i class="fas fa-bookmark"></i>
                    </div>
                    <p class="quick-access-title">Reservas</p>
                    <p class="quick-access-desc">Ver reservas</p>
                </a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="usuarios.php" class="quick-access-item">
                    <div class="quick-access-icon usuarios">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <p class="quick-access-title">Usuarios</p>
                    <p class="quick-access-desc">Crear cuenta</p>
                </a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="autores.php" class="quick-access-item">
                    <div class="quick-access-icon autores">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <p class="quick-access-title">Autores</p>
                    <p class="quick-access-desc">Gestionar autores</p>
                </a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="categorias.php" class="quick-access-item">
                    <div class="quick-access-icon categorias">
                        <i class="fas fa-tags"></i>
                    </div>
                    <p class="quick-access-title">Categorías</p>
                    <p class="quick-access-desc">Categorías y géneros</p>
                </a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="carreras.php" class="quick-access-item">
                    <div class="quick-access-icon carreras">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <p class="quick-access-title">Carreras</p>
                    <p class="quick-access-desc">Carreras universitarias</p>
                </a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="libros.php" class="quick-access-item">
                    <div class="quick-access-icon reportes">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <p class="quick-access-title">Catálogo</p>
                    <p class="quick-access-desc">Ver inventario</p>
                </a>
            </div>
        </div>

        <!-- Actividad Reciente y Libros Populares -->
        <div class="row g-4">
            <!-- Actividad Reciente -->
            <div class="col-lg-6">
                <div class="card-custom">
                    <div class="card-custom-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-history me-2"></i>Actividad Reciente</h5>
                        <a href="prestamos.php" class="text-white text-decoration-none small">
                            Ver todo <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-custom-body">
                        <?php if (count($ultimos_prestamos) > 0): ?>
                            <?php foreach ($ultimos_prestamos as $p): ?>
                                <?php 
                                    $esVencido = strtoupper($p['estado_prestamo']) === 'VENCIDO';
                                    $iconClass = $esVencido ? 'vencido' : 'prestamo';
                                    $icon = $esVencido ? 'fa-exclamation-triangle' : 'fa-book';
                                ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?php echo $iconClass; ?>">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="activity-info">
                                        <p class="activity-title"><?php echo htmlspecialchars($p['libro_titulo']); ?></p>
                                        <p class="activity-subtitle">
                                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($p['usuario_nombre']); ?>
                                            <span class="ms-2">•</span>
                                            <span class="ms-2 badge <?php echo $esVencido ? 'bg-danger' : 'bg-success'; ?> small">
                                                <?php echo $p['estado_prestamo']; ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('d/m/Y', strtotime($p['fecha_prestamo'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state-mini">
                                <i class="fas fa-inbox"></i>
                                <p class="mb-0">No hay actividad reciente</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Libros Más Populares -->
            <div class="col-lg-6">
                <div class="card-custom">
                    <div class="card-custom-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-trophy me-2"></i>Libros Más Populares</h5>
                        <a href="libros.php" class="text-white text-decoration-none small">
                            Ver todos <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-custom-body">
                        <?php if (count($libros_populares) > 0): ?>
                            <?php foreach ($libros_populares as $idx => $libro): ?>
                                <?php 
                                    $rankClass = '';
                                    if ($idx === 0) $rankClass = 'top-1';
                                    elseif ($idx === 1) $rankClass = 'top-2';
                                    elseif ($idx === 2) $rankClass = 'top-3';
                                ?>
                                <div class="popular-book-item">
                                    <div class="popular-rank <?php echo $rankClass; ?>">
                                        <?php echo $idx + 1; ?>
                                    </div>
                                    <?php if (!empty($libro['imagen']) && file_exists('../' . $libro['imagen'])): ?>
                                        <img src="../<?php echo htmlspecialchars($libro['imagen']); ?>" alt="Portada" class="popular-book-cover">
                                    <?php else: ?>
                                        <div class="popular-book-placeholder">
                                            <i class="fas fa-book"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="popular-info">
                                        <p class="popular-title"><?php echo htmlspecialchars($libro['titulo']); ?></p>
                                        <p class="popular-code">
                                            <i class="fas fa-barcode me-1"></i>
                                            <?php echo htmlspecialchars($libro['codigo']); ?>
                                            <?php if ($libro['prestamos_activos'] > 0): ?>
                                                <span class="badge bg-warning text-dark ms-2">
                                                    <i class="fas fa-hand-holding me-1"></i><?php echo $libro['prestamos_activos']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="popular-count">
                                        <span class="popular-count-number"><?php echo $libro['total_prestamos']; ?></span>
                                        <span class="popular-count-label">préstamos</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state-mini">
                                <i class="fas fa-book"></i>
                                <p class="mb-0">No hay préstamos registrados</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reservas que requieren atención (si hay disponibles) -->
        <?php if (count($reservas_atencion) > 0): ?>
            <div class="row g-4 mt-3">
                <div class="col-12">
                    <div class="card-custom">
                        <div class="card-custom-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-bell me-2"></i>Reservas Disponibles para Reclamar</h5>
                            <a href="reservas.php?estado_filtro=Disponible" class="text-white text-decoration-none small">
                                Gestionar <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <div class="card-custom-body">
                            <div class="row g-2">
                                <?php foreach ($reservas_atencion as $r): ?>
                                    <div class="col-md-6">
                                        <div class="reserva-urgente">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <p class="mb-1 fw-bold small">
                                                        <i class="fas fa-book me-1"></i>
                                                        <?php echo htmlspecialchars($r['libro_titulo']); ?>
                                                    </p>
                                                    <p class="mb-1 small text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($r['usuario_nombre']); ?>
                                                    </p>
                                                    <p class="mb-0 small">
                                                        <span class="urgente-dias <?php echo $r['dias_restantes'] <= 2 ? 'critico' : ''; ?>">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo $r['dias_restantes']; ?> día(s) restante(s)
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center text-muted mt-5 py-3">
            <p class="mb-0">
                <i class="fas fa-code me-2"></i>
                Sistema de Gestión de Biblioteca Digital • 
                <?php echo date('Y'); ?>
            </p>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>