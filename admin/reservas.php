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
    switch ($r['estado']) {
        case 'Pendiente': $stats['pendientes']++; break;
        case 'Disponible': $stats['disponibles']++; break;
        case 'Reclamada': $stats['reclamadas']++; break;
        case 'Cancelada': $stats['canceladas']++; break;
        case 'Expirada': $stats['expiradas']++; break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Reservas - Panel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Navbar -->
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
            <div class="alert alert-danger text-center" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-success text-center" role="alert">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <h2 class="fw-bold mb-4 text-center">Gestión de Reservas</h2>

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <div class="card bg-primary text-white shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?php echo $stats['total']; ?></h3>
                        <small>Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-warning text-dark shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?php echo $stats['pendientes']; ?></h3>
                        <small>Pendientes</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-success text-white shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?php echo $stats['disponibles']; ?></h3>
                        <small>Disponibles</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-info text-white shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?php echo $stats['reclamadas']; ?></h3>
                        <small>Reclamadas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-secondary text-white shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?php echo $stats['canceladas']; ?></h3>
                        <small>Canceladas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-danger text-white shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?php echo $stats['expiradas']; ?></h3>
                        <small>Expiradas</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barra de búsqueda -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Filtrar Reservas</h5>
                <form action="reservas.php" method="GET" class="row g-3">
                    <div class="col-md-5">
                        <input type="text" name="buscar" class="form-control" placeholder="Buscar por libro, usuario o código..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    <div class="col-md-4">
                        <select name="estado_filtro" class="form-select">
                            <option value="">-- Todos los estados --</option>
                            <option value="Pendiente" <?php echo $estado_filtro === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="Disponible" <?php echo $estado_filtro === 'Disponible' ? 'selected' : ''; ?>>Disponible</option>
                            <option value="Reclamada" <?php echo $estado_filtro === 'Reclamada' ? 'selected' : ''; ?>>Reclamada</option>
                            <option value="Cancelada" <?php echo $estado_filtro === 'Cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                            <option value="Expirada" <?php echo $estado_filtro === 'Expirada' ? 'selected' : ''; ?>>Expirada</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-info w-100">🔍 Filtrar</button>
                        <?php if (!empty($busqueda) || !empty($estado_filtro)): ?>
                            <a href="reservas.php" class="btn btn-secondary">Limpiar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Reservas Registradas</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Libro</th>
                                <th>Usuario</th>
                                <th>Posición</th>
                                <th>Fecha Reserva</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($reservas) > 0): ?>
                                <?php foreach ($reservas as $r): ?>
                                    <?php
                                        $claseBadge = '';
                                        switch ($r['estado']) {
                                            case 'Pendiente': $claseBadge = 'bg-warning text-dark'; break;
                                            case 'Disponible': $claseBadge = 'bg-success'; break;
                                            case 'Reclamada': $claseBadge = 'bg-info'; break;
                                            case 'Cancelada': $claseBadge = 'bg-secondary'; break;
                                            case 'Expirada': $claseBadge = 'bg-danger'; break;
                                        }
                                    ?>
                                    <tr>
                                        <td><strong>#<?php echo $r['id_reserva']; ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($r['libro_codigo']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($r['libro_titulo']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($r['usuario_nombre']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($r['usuario_carnet']); ?></small>
                                        </td>
                                        <td><span class="badge bg-dark">#<?php echo $r['posicion_actual']; ?></span></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($r['fecha_reserva'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $claseBadge; ?>">
                                                <?php echo $r['estado']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" 
                                                onclick='cargarDatos(<?php echo json_encode($r); ?>)'>
                                                Editar
                                            </button>
                                            <a href="reservas.php?eliminar=<?php echo $r['id_reserva']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('¿Eliminar esta reserva definitivamente?')">
                                                Eliminar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox"></i> No se encontraron reservas
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal para editar -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title fw-bold">Editar Estado de Reserva</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="reservas.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id_reserva" id="edit_id_reserva">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Libro:</label>
                            <p id="edit_libro" class="form-control-plaintext"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Usuario:</label>
                            <p id="edit_usuario" class="form-control-plaintext"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Estado Actual:</label>
                            <p id="edit_estado_actual" class="form-control-plaintext"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="estado" class="form-label fw-bold">Nuevo Estado:</label>
                            <select id="estado" name="estado" class="form-select" required>
                                <option value="Pendiente">Pendiente</option>
                                <option value="Disponible">Disponible</option>
                                <option value="Reclamada">Reclamada</option>
                                <option value="Cancelada">Cancelada</option>
                                <option value="Expirada">Expirada</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="cambiar_estado" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function cargarDatos(reserva) {
            document.getElementById('edit_id_reserva').value = reserva.id_reserva;
            document.getElementById('edit_libro').textContent = reserva.libro_codigo + ' - ' + reserva.libro_titulo;
            document.getElementById('edit_usuario').textContent = reserva.usuario_nombre + ' (' + reserva.usuario_carnet + ')';
            document.getElementById('edit_estado_actual').textContent = reserva.estado;
            document.getElementById('estado').value = reserva.estado;
            
            var modal = new bootstrap.Modal(document.getElementById('modalEditar'));
            modal.show();
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>