<?php
// usuario/mis_prestamos.php
require_once '../config/conexion.php';
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../public/login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['devolver_id'])) {
    $prestamo_id = intval($_POST['devolver_id']);
    // El stock se gestiona automáticamente por triggers en la base de datos.
    // Aquí solo actualizamos el estado del préstamo a Devuelto.
    $stmtUpdate = $pdo->prepare("UPDATE Prestamo SET estado_prestamo = 'Devuelto' WHERE id_prestamo = ? AND id_usuario = ?");
    if ($stmtUpdate->execute([$prestamo_id, $id_usuario])) {
        $mensaje = $stmtUpdate->rowCount() > 0
            ? 'El préstamo se marcó como devuelto correctamente.'
            : 'No se encontró el préstamo o ya está devuelto.';
    } else {
        $mensaje = 'Ocurrió un error al actualizar el préstamo.';
    }
}

// Consulta uniendo los datos de los préstamos del usuario actual
$stmt = $pdo->prepare("SELECT p.*, l.titulo, l.codigo FROM Prestamo p 
                        INNER JOIN Libro l ON p.id_libro = l.id_libro 
                        WHERE p.id_usuario = ? 
                        ORDER BY p.fecha_prestamo DESC");
$stmt->execute([$id_usuario]);
$prestamos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Préstamos - Biblioteca Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #0B1C2B 0%, #1a2f42 50%, #0B1C2B 100%);
            color: #d4c5a0;
            font-family: 'Lora', serif;
            min-height: 100vh;
            padding-left: 260px;
        }

        /* Sidebar estilo El Libro Total */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(180deg, #0a1620 0%, #132738 50%, #0a1620 100%);
            border-right: 2px solid #c9a84c;
            padding: 0;
            z-index: 1000;
            box-shadow: 4px 0 20px rgba(0,0,0,0.5);
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(201, 168, 76, 0.3);
            background: rgba(0,0,0,0.3);
        }

        .sidebar-header h3 {
            font-family: 'Playfair Display', serif;
            color: #c9a84c;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            margin: 0;
        }

        .sidebar-header .subtitle {
            color: #8b7d5e;
            font-size: 0.75rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 0.5rem;
        }

        .sidebar-nav {
            padding: 1.5rem 0;
        }

        .sidebar-nav .nav-item {
            margin: 0.25rem 1rem;
        }

        .sidebar-nav .nav-link {
            color: #a89878;
            padding: 0.85rem 1.25rem;
            border-radius: 4px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-nav .nav-link:hover {
            color: #c9a84c;
            background: rgba(201, 168, 76, 0.1);
            border-left-color: #c9a84c;
            transform: translateX(4px);
        }

        .sidebar-nav .nav-link.active {
            color: #c9a84c;
            background: rgba(201, 168, 76, 0.15);
            border-left-color: #c9a84c;
            font-weight: 600;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1.5rem;
            border-top: 1px solid rgba(201, 168, 76, 0.3);
            background: rgba(0,0,0,0.3);
        }

        .sidebar-footer .btn-salir {
            width: 100%;
            background: transparent;
            border: 1px solid #c9a84c;
            color: #c9a84c;
            padding: 0.6rem;
            border-radius: 4px;
            font-family: 'Lora', serif;
            font-size: 0.9rem;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .sidebar-footer .btn-salir:hover {
            background: #c9a84c;
            color: #0B1C2B;
        }

        /* Contenido principal */
        .main-content {
            padding: 2rem 3rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header de la página */
        .page-header {
            text-align: center;
            padding: 2rem 0 3rem;
            border-bottom: 1px solid rgba(201, 168, 76, 0.2);
            margin-bottom: 3rem;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            color: #c9a84c;
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
            margin-bottom: 0.5rem;
        }

        .page-header .ornament {
            color: #8b7d5e;
            font-size: 1.5rem;
            letter-spacing: 8px;
        }

        .page-header p {
            color: #8b9da8;
            font-size: 1rem;
            margin-top: 1rem;
            font-style: italic;
        }

        /* Mensaje de alerta */
        .alert-custom {
            background: rgba(201, 168, 76, 0.15);
            border: 1px solid rgba(201, 168, 76, 0.4);
            color: #c9a84c;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            font-family: 'Lora', serif;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-custom .btn-close {
            filter: invert(0.7) sepia(1) saturate(3) hue-rotate(10deg);
        }

        /* Contenedor de la tabla */
        .tabla-container {
            background: linear-gradient(135deg, rgba(15, 34, 52, 0.9) 0%, rgba(26, 52, 80, 0.9) 100%);
            border: 2px solid rgba(201, 168, 76, 0.3);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Tabla personalizada */
        .tabla-prestamos {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Lora', serif;
        }

        .tabla-prestamos thead {
            background: linear-gradient(135deg, rgba(201, 168, 76, 0.2) 0%, rgba(139, 125, 94, 0.2) 100%);
            border-bottom: 2px solid rgba(201, 168, 76, 0.4);
        }

        .tabla-prestamos thead th {
            padding: 1.25rem 1.5rem;
            color: #c9a84c;
            font-family: 'Playfair Display', serif;
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-align: left;
            white-space: nowrap;
        }

        .tabla-prestamos tbody tr {
            border-bottom: 1px solid rgba(201, 168, 76, 0.1);
            transition: all 0.3s ease;
        }

        .tabla-prestamos tbody tr:hover {
            background: rgba(201, 168, 76, 0.05);
            transform: scale(1.005);
        }

        .tabla-prestamos tbody tr:last-child {
            border-bottom: none;
        }

        .tabla-prestamos tbody td {
            padding: 1.25rem 1.5rem;
            color: #d4c5a0;
            font-size: 0.95rem;
            vertical-align: middle;
        }

        /* Código del libro */
        .codigo-badge {
            display: inline-block;
            background: rgba(201, 168, 76, 0.15);
            color: #c9a84c;
            padding: 0.35rem 0.85rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid rgba(201, 168, 76, 0.3);
            letter-spacing: 0.5px;
        }

        /* Título del libro */
        .titulo-libro {
            color: #e8d9b4;
            font-weight: 600;
            font-size: 1rem;
        }

        /* Fecha */
        .fecha-prestamo {
            color: #8b9da8;
            font-size: 0.9rem;
        }

        .fecha-prestamo .dia {
            color: #c9a84c;
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* Badges de estado */
        .estado-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .estado-activo {
            background: rgba(40, 167, 69, 0.2);
            color: #5cb85c;
            border: 1px solid rgba(40, 167, 69, 0.4);
        }

        .estado-vencido {
            background: rgba(220, 53, 69, 0.2);
            color: #e74c3c;
            border: 1px solid rgba(220, 53, 69, 0.4);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
            50% { box-shadow: 0 0 0 6px rgba(220, 53, 69, 0); }
        }

        .estado-devuelto {
            background: rgba(108, 117, 125, 0.2);
            color: #8b9da8;
            border: 1px solid rgba(108, 117, 125, 0.4);
        }

        /* Botón devolver */
        .btn-devolver {
            background: transparent;
            border: 1px solid #5cb85c;
            color: #5cb85c;
            padding: 0.5rem 1.25rem;
            border-radius: 6px;
            font-family: 'Lora', serif;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }

        .btn-devolver:hover {
            background: #5cb85c;
            color: #0B1C2B;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(92, 184, 92, 0.3);
        }

        .texto-devuelto {
            color: #6b7d8a;
            font-style: italic;
            font-size: 0.85rem;
        }

        /* Estado vacío */
        .estado-vacio {
            text-align: center;
            padding: 5rem 2rem;
            background: linear-gradient(135deg, rgba(15, 34, 52, 0.9) 0%, rgba(26, 52, 80, 0.9) 100%);
            border: 2px solid rgba(201, 168, 76, 0.3);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
            animation: fadeInUp 0.6s ease;
        }

        .estado-vacio .icono {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0.6;
        }

        .estado-vacio h3 {
            font-family: 'Playfair Display', serif;
            color: #c9a84c;
            font-size: 1.8rem;
            margin-bottom: 0.75rem;
        }

        .estado-vacio p {
            color: #8b9da8;
            font-size: 1rem;
            font-style: italic;
            margin-bottom: 2rem;
        }

        .estado-vacio .btn-explorar {
            display: inline-block;
            background: linear-gradient(135deg, #c9a84c, #a8893c);
            color: #0B1C2B;
            padding: 0.75rem 2rem;
            border-radius: 6px;
            text-decoration: none;
            font-family: 'Lora', serif;
            font-weight: 600;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .estado-vacio .btn-explorar:hover {
            background: linear-gradient(135deg, #d4b85c, #b8994c);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(201, 168, 76, 0.3);
        }

        /* Estadísticas rápidas */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(15, 34, 52, 0.9) 0%, rgba(26, 52, 80, 0.9) 100%);
            border: 1px solid rgba(201, 168, 76, 0.3);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: rgba(201, 168, 76, 0.6);
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }

        .stat-card .stat-numero {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: #c9a84c;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-card .stat-etiqueta {
            color: #8b9da8;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card.activos .stat-numero { color: #5cb85c; }
        .stat-card.vencidos .stat-numero { color: #e74c3c; }
        .stat-card.devueltos .stat-numero { color: #8b9da8; }

        /* Scrollbar personalizado */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #0B1C2B;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #c9a84c, #8b7d5e);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #d4b85c, #a8893c);
        }

        /* Responsive */
        @media (max-width: 991.98px) {
            body {
                padding-left: 0;
                padding-top: 70px;
            }

            .sidebar {
                width: 100%;
                height: 70px;
                display: flex;
                align-items: center;
                padding: 0 1rem;
                border-right: none;
                border-bottom: 2px solid #c9a84c;
            }

            .sidebar-header {
                padding: 0 1rem 0 0;
                border-bottom: none;
                border-right: 1px solid rgba(201, 168, 76, 0.3);
                text-align: left;
            }

            .sidebar-header h3 {
                font-size: 1.1rem;
            }

            .sidebar-header .subtitle {
                display: none;
            }

            .sidebar-nav {
                display: flex;
                padding: 0;
                margin: 0 1rem;
                gap: 0.5rem;
                overflow-x: auto;
            }

            .sidebar-nav .nav-item {
                margin: 0;
            }

            .sidebar-nav .nav-link {
                padding: 0.5rem 1rem;
                white-space: nowrap;
                border-left: none;
                border-bottom: 3px solid transparent;
                font-size: 0.85rem;
            }

            .sidebar-nav .nav-link:hover,
            .sidebar-nav .nav-link.active {
                border-left: none;
                border-bottom-color: #c9a84c;
                transform: none;
            }

            .sidebar-footer {
                position: static;
                padding: 0;
                border: none;
                background: none;
                margin-left: auto;
            }

            .sidebar-footer .btn-salir {
                padding: 0.4rem 1rem;
                font-size: 0.8rem;
                width: auto;
            }

            .main-content {
                padding: 1.5rem 1rem;
            }

            .page-header h1 {
                font-size: 1.8rem;
            }

            .tabla-prestamos thead th,
            .tabla-prestamos tbody td {
                padding: 1rem;
                font-size: 0.85rem;
            }

            .stats-container {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.75rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-card .stat-numero {
                font-size: 1.8rem;
            }

            .stat-card .stat-etiqueta {
                font-size: 0.7rem;
            }
        }

        @media (max-width: 767.98px) {
            /* En móvil, convertir tabla en cards */
            .tabla-prestamos thead {
                display: none;
            }

            .tabla-prestamos, 
            .tabla-prestamos tbody, 
            .tabla-prestamos tr, 
            .tabla-prestamos td {
                display: block;
                width: 100%;
            }

            .tabla-prestamos tbody tr {
                background: rgba(15, 34, 52, 0.6);
                border: 1px solid rgba(201, 168, 76, 0.2);
                border-radius: 10px;
                margin-bottom: 1rem;
                padding: 1rem;
            }

            .tabla-prestamos tbody tr:hover {
                transform: none;
                background: rgba(201, 168, 76, 0.05);
            }

            .tabla-prestamos tbody td {
                padding: 0.5rem 0;
                text-align: left;
                border: none;
                position: relative;
                padding-left: 45%;
            }

            .tabla-prestamos tbody td::before {
                content: attr(data-label);
                position: absolute;
                left: 0;
                width: 40%;
                color: #c9a84c;
                font-weight: 600;
                font-size: 0.8rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .tabla-container {
                background: transparent;
                border: none;
                box-shadow: none;
            }
        }

        @media (max-width: 575.98px) {
            .page-header h1 {
                font-size: 1.4rem;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h3>📚 Biblioteca</h3>
            <div class="subtitle">Digital</div>
        </div>
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'catalogo.php' ? 'active' : ''; ?>" href="./catalogo.php">
                        <span>📖</span> Catálogo
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'mis_prestamos.php' ? 'active' : ''; ?>" href="./mis_prestamos.php">
                        <span>🔄</span> Mis Préstamos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'perfil.php' ? 'active' : ''; ?>" href="./perfil.php">
                        <span>👤</span> Mi Perfil
                    </a>
                </li>
            </ul>
        </div>
        <div class="sidebar-footer">
            <a class="btn-salir" href="../public/logout.php">⬅ Salir</a>
        </div>
    </nav>

    <!-- Contenido principal -->
    <main class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div class="ornament">✦ ✦ ✦</div>
            <h1>Mis Préstamos</h1>
            <p>Historial y control de tus devoluciones</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert-custom">
                <span>✓ <?php echo htmlspecialchars($mensaje); ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (count($prestamos) > 0): ?>
            <!-- Estadísticas rápidas -->
            <?php
                $totalActivos = 0;
                $totalVencidos = 0;
                $totalDevueltos = 0;
                foreach ($prestamos as $p) {
                    if ($p['estado_prestamo'] === 'Activo') $totalActivos++;
                    elseif ($p['estado_prestamo'] === 'Vencido') $totalVencidos++;
                    elseif ($p['estado_prestamo'] === 'Devuelto') $totalDevueltos++;
                }
            ?>
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-numero"><?php echo count($prestamos); ?></div>
                    <div class="stat-etiqueta">Total Préstamos</div>
                </div>
                <div class="stat-card activos">
                    <div class="stat-numero"><?php echo $totalActivos; ?></div>
                    <div class="stat-etiqueta">Activos</div>
                </div>
                <div class="stat-card vencidos">
                    <div class="stat-numero"><?php echo $totalVencidos; ?></div>
                    <div class="stat-etiqueta">Vencidos</div>
                </div>
                <div class="stat-card devueltos">
                    <div class="stat-numero"><?php echo $totalDevueltos; ?></div>
                    <div class="stat-etiqueta">Devueltos</div>
                </div>
            </div>

            <!-- Tabla de préstamos -->
            <div class="tabla-container">
                <table class="tabla-prestamos">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Título del Libro</th>
                            <th>Fecha Préstamo</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prestamos as $p): ?>
                            <?php
                                $fecha = strtotime($p['fecha_prestamo']);
                                $dia = date('d', $fecha);
                                $mes = date('M', $fecha);
                                $anio = date('Y', $fecha);
                                
                                $claseEstado = '';
                                if ($p['estado_prestamo'] === 'Activo') $claseEstado = 'estado-activo';
                                elseif ($p['estado_prestamo'] === 'Vencido') $claseEstado = 'estado-vencido';
                                elseif ($p['estado_prestamo'] === 'Devuelto') $claseEstado = 'estado-devuelto';
                            ?>
                            <tr>
                                <td data-label="Código">
                                    <span class="codigo-badge"><?php echo htmlspecialchars($p['codigo']); ?></span>
                                </td>
                                <td data-label="Título">
                                    <span class="titulo-libro"><?php echo htmlspecialchars($p['titulo']); ?></span>
                                </td>
                                <td data-label="Fecha">
                                    <span class="fecha-prestamo">
                                        <span class="dia"><?php echo $dia; ?></span> <?php echo $mes; ?> <?php echo $anio; ?>
                                    </span>
                                </td>
                                <td data-label="Estado">
                                    <span class="estado-badge <?php echo $claseEstado; ?>">
                                        <?php echo $p['estado_prestamo']; ?>
                                    </span>
                                </td>
                                <td data-label="Acción">
                                    <?php if ($p['estado_prestamo'] === 'Activo' || $p['estado_prestamo'] === 'Vencido'): ?>
                                        <form method="post" class="m-0">
                                            <input type="hidden" name="devolver_id" value="<?php echo $p['id_prestamo']; ?>">
                                            <button type="submit" class="btn-devolver">
                                                ✓ Marcar devuelto
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="texto-devuelto">Completado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="estado-vacio">
                <div class="icono">📚</div>
                <h3>Sin préstamos registrados</h3>
                <p>Aún no has solicitado ningún libro en préstamo</p>
                <a href="./catalogo.php" class="btn-explorar">Explorar Catálogo</a>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>