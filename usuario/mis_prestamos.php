<?php
require_once '../config/conexion.php';
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../public/login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$mensaje = '';
$tipo_mensaje = 'success';

// Capturar pestaña actual
$tab = $_POST['current_tab'] ?? $_GET['tab'] ?? 'prestamos';

// ==========================================
// PROCESAR DEVOLUCIÓN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['devolver_id'])) {
    $prestamo_id = intval($_POST['devolver_id']);
    $stmtUpdate = $pdo->prepare("UPDATE Prestamo SET estado_prestamo = 'Devuelto', fecha_devolucion = CURDATE() WHERE id_prestamo = ? AND id_usuario = ?");
    if ($stmtUpdate->execute([$prestamo_id, $id_usuario])) {
        // Verificar si hay reservas pendientes para notificar al siguiente en cola
        $stmtLibro = $pdo->prepare("SELECT id_libro FROM Prestamo WHERE id_prestamo = ?");
        $stmtLibro->execute([$prestamo_id]);
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
        
        $mensaje = 'El préstamo se marcó como devuelto correctamente.';
        $tab = 'prestamos';
    } else {
        $mensaje = 'Ocurrió un error al actualizar el préstamo.';
        $tipo_mensaje = 'danger';
    }
}

// ==========================================
// PROCESAR RENOVACIÓN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['renovar_id'])) {
    $prestamo_id = intval($_POST['renovar_id']);
    
    // Obtener datos del préstamo
    $stmt = $pdo->prepare("SELECT p.*, l.titulo, l.id_libro FROM Prestamo p INNER JOIN Libro l ON p.id_libro = l.id_libro WHERE p.id_prestamo = ? AND p.id_usuario = ?");
    $stmt->execute([$prestamo_id, $id_usuario]);
    $prestamo = $stmt->fetch();
    
    if ($prestamo) {
        $estado_upper = strtoupper($prestamo['estado_prestamo']);
        $renovaciones = intval($prestamo['renovaciones'] ?? 0);
        
        // Validación 1: Máximo 2 renovaciones
        if ($renovaciones >= 2) {
            $mensaje = 'Este préstamo ya alcanzó el máximo de 2 renovaciones permitidas.';
            $tipo_mensaje = 'warning';
        }
        // Validación 2: Solo se renuevan préstamos Activos o Vencidos
        elseif ($estado_upper !== 'ACTIVO' && $estado_upper !== 'VENCIDO') {
            $mensaje = 'Solo se pueden renovar préstamos activos o vencidos.';
            $tipo_mensaje = 'warning';
        }
        // Validación 3: Verificar reservas pendientes del mismo libro
        else {
            $stmtReservas = $pdo->prepare("SELECT COUNT(*) FROM Reserva WHERE id_libro = ? AND estado IN ('Pendiente', 'Disponible')");
            $stmtReservas->execute([$prestamo['id_libro']]);
            $reservas_pendientes = $stmtReservas->fetchColumn();
            
            if ($reservas_pendientes > 0) {
                $mensaje = 'No se puede renovar porque hay ' . $reservas_pendientes . ' persona(s) esperando este libro.';
                $tipo_mensaje = 'warning';
            } else {
                // Realizar la renovación
                $stmtRenovar = $pdo->prepare("
                    UPDATE Prestamo 
                    SET fecha_entrega = DATE_ADD(fecha_entrega, INTERVAL 7 DAY),
                        renovaciones = renovaciones + 1,
                        fecha_renovacion = CURDATE(),
                        estado_prestamo = 'Activo'
                    WHERE id_prestamo = ? AND id_usuario = ?
                ");
                
                if ($stmtRenovar->execute([$prestamo_id, $id_usuario])) {
                    $nueva_fecha = date('d/m/Y', strtotime($prestamo['fecha_entrega'] . ' +7 days'));
                    $mensaje = '¡Préstamo renovado! Nueva fecha: ' . $nueva_fecha;
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al renovar el préstamo.';
                    $tipo_mensaje = 'danger';
                }
            }
        }
    } else {
        $mensaje = 'Préstamo no encontrado.';
        $tipo_mensaje = 'danger';
    }
    $tab = 'prestamos';
}

// ==========================================
// PROCESAR CANCELACIÓN DE RESERVA
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cancelar_reserva_id'])) {
    $reserva_id = intval($_POST['cancelar_reserva_id']);
    $stmtUpdate = $pdo->prepare("UPDATE Reserva SET estado = 'Cancelada' WHERE id_reserva = ? AND id_usuario = ? AND estado IN ('Pendiente', 'Disponible')");
    if ($stmtUpdate->execute([$reserva_id, $id_usuario])) {
        $mensaje = 'Reserva cancelada correctamente.';
        $tipo_mensaje = 'success';
    } else {
        $mensaje = 'Error al cancelar la reserva.';
        $tipo_mensaje = 'danger';
    }
    $tab = 'reservas';
}

// ==========================================
// PROCESAR RECLAMACIÓN DE RESERVA
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['reclamar_reserva_id'])) {
    $reserva_id = intval($_POST['reclamar_reserva_id']);
    $stmtUpdate = $pdo->prepare("UPDATE Reserva SET estado = 'Reclamada' WHERE id_reserva = ? AND id_usuario = ? AND estado = 'Disponible'");
    if ($stmtUpdate->execute([$reserva_id, $id_usuario])) {
        $mensaje = '¡Reserva reclamada! Pasa por la biblioteca a recoger el libro.';
        $tipo_mensaje = 'success';
    } else {
        $mensaje = 'Error al reclamar la reserva.';
        $tipo_mensaje = 'danger';
    }
    $tab = 'reservas';
}

// ==========================================
// OBTENER DATOS
// ==========================================

// Préstamos
$stmt = $pdo->prepare("SELECT p.*, l.titulo, l.codigo FROM Prestamo p INNER JOIN Libro l ON p.id_libro = l.id_libro WHERE p.id_usuario = ? ORDER BY p.fecha_prestamo DESC");
$stmt->execute([$id_usuario]);
$prestamos = $stmt->fetchAll();

// Reservas Activas
$stmt = $pdo->prepare("
    SELECT r.*, l.titulo, l.codigo, l.imagen,
           (SELECT COUNT(*) FROM Reserva WHERE id_libro = r.id_libro AND estado IN ('Pendiente', 'Disponible') AND posicion_cola < r.posicion_cola) + 1 AS posicion_actual
    FROM Reserva r
    INNER JOIN Libro l ON r.id_libro = l.id_libro
    WHERE r.id_usuario = ? AND r.estado IN ('Pendiente', 'Disponible')
    ORDER BY r.fecha_reserva DESC
");
$stmt->execute([$id_usuario]);
$reservas_activas = $stmt->fetchAll();

// Historial de Reservas
$stmt = $pdo->prepare("
    SELECT r.*, l.titulo, l.codigo
    FROM Reserva r
    INNER JOIN Libro l ON r.id_libro = l.id_libro
    WHERE r.id_usuario = ? AND r.estado IN ('Cancelada', 'Expirada', 'Reclamada')
    ORDER BY r.fecha_reserva DESC
");
$stmt->execute([$id_usuario]);
$reservas_historial = $stmt->fetchAll();

// Estadísticas
$totalActivos = 0;
$totalVencidos = 0;
$totalDevueltos = 0;
foreach ($prestamos as $p) {
    $st = strtoupper($p['estado_prestamo']);
    if ($st === 'ACTIVO') $totalActivos++;
    elseif ($st === 'VENCIDO') $totalVencidos++;
    elseif ($st === 'DEVUELTO') $totalDevueltos++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Préstamos y Reservas - Biblioteca Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/mis_prestamos.css">
</head>
<body>

    <nav class="sidebar">
        <div class="sidebar-header">
            <h3>Biblioteca</h3>
            <div class="subtitle">Digital</div>
        </div>
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="./catalogo.php">
                        <span></span> Catálogo
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="./mis_prestamos.php">
                        <span></span> Mis Préstamos
                        <?php if (count($reservas_activas) > 0): ?>
                            <span class="badge-reserva"><?php echo count($reservas_activas); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="./perfil.php">
                        <span></span> Mi Perfil
                    </a>
                </li>
            </ul>
        </div>
        <div class="sidebar-footer">
            <a class="btn-salir" href="../public/logout.php">⬅ Salir</a>
        </div>
    </nav>

    <main class="main-content">
        <div class="page-header">
            <div class="ornament">✦ ✦ ✦ </div>
            <h1>Mis Préstamos y Reservas</h1>
            <p>Control de tus préstamos y reservas de libros</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert-custom alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <span><?php echo htmlspecialchars($mensaje); ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Tabs de navegación -->
        <div class="tabs-container">
            <a href="?tab=prestamos" class="tab-item <?php echo $tab === 'prestamos' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i> 
                <span class="tab-label">Préstamos</span>
                <span class="tab-count"><?php echo count($prestamos); ?></span>
            </a>
            <a href="?tab=reservas" class="tab-item <?php echo $tab === 'reservas' ? 'active' : ''; ?>">
                <i class="fas fa-bookmark"></i> 
                <span class="tab-label">Mis Reservas</span>
                <?php if (count($reservas_activas) > 0): ?>
                    <span class="tab-count badge"><?php echo count($reservas_activas); ?></span>
                <?php else: ?>
                    <span class="tab-count"><?php echo count($reservas_activas); ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=historial" class="tab-item <?php echo $tab === 'historial' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> 
                <span class="tab-label">Historial</span>
                <span class="tab-count"><?php echo count($reservas_historial); ?></span>
            </a>
        </div>

        <!-- TAB: PRÉSTAMOS -->
        <?php if ($tab === 'prestamos'): ?>
            <?php if (count($prestamos) > 0): ?>
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

                <div class="tabla-container">
                    <table class="tabla-prestamos">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Título del Libro</th>
                                <th>Fecha Préstamo</th>
                                <th>Fecha Entrega</th>
                                <th>Renovaciones</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prestamos as $p): ?>
                                <?php
                                    // Normalización de fechas para mostrar
                                    $fecha = strtotime($p['fecha_prestamo']);
                                    $dia = date('d', $fecha);
                                    $mes = date('M', $fecha);
                                    $anio = date('Y', $fecha);
                                    
                                    $fechaEntrega = strtotime($p['fecha_entrega']);
                                    $diaEnt = date('d', $fechaEntrega);
                                    $mesEnt = date('M', $fechaEntrega);
                                    
                                    // Normalización de estado para lógica y estilo
                                    $estadoRaw = $p['estado_prestamo'];
                                    $estadoUpper = strtoupper($estadoRaw);
                                    
                                    $claseEstado = '';
                                    if ($estadoUpper === 'ACTIVO') $claseEstado = 'estado-activo';
                                    elseif ($estadoUpper === 'VENCIDO') $claseEstado = 'estado-vencido';
                                    elseif ($estadoUpper === 'DEVUELTO') $claseEstado = 'estado-devuelto';
                                    
                                    $renovaciones = intval($p['renovaciones'] ?? 0);
                                    $maxRenovaciones = 2;
                                    // Lógica de renovación corregida
                                    $puedeRenovar = ($estadoUpper === 'ACTIVO' || $estadoUpper === 'VENCIDO') && ($renovaciones < $maxRenovaciones);
                                ?>
                                <tr>
                                    <td data-label="Código">
                                        <span class="codigo-badge"><?php echo htmlspecialchars($p['codigo']); ?></span>
                                    </td>
                                    <td data-label="Título">
                                        <span class="titulo-libro"><?php echo htmlspecialchars($p['titulo']); ?></span>
                                    </td>
                                    <td data-label="Fecha Préstamo">
                                        <span class="fecha-prestamo">
                                            <span class="dia"><?php echo $dia; ?></span> <?php echo $mes; ?> <?php echo $anio; ?>
                                        </span>
                                    </td>
                                    <td data-label="Fecha Entrega">
                                        <span class="fecha-prestamo">
                                            <span class="dia"><?php echo $diaEnt; ?></span> <?php echo $mesEnt; ?>
                                        </span>
                                    </td>
                                    <td data-label="Renovaciones">
                                        <div class="renovaciones-indicador">
                                            <?php for ($i = 0; $i < $maxRenovaciones; $i++): ?>
                                                <span class="renovacion-dot <?php echo $i < $renovaciones ? 'used' : 'available'; ?>"></span>
                                            <?php endfor; ?>
                                            <small class="ms-2"><?php echo $renovaciones; ?>/<?php echo $maxRenovaciones; ?></small>
                                        </div>
                                    </td>
                                    <td data-label="Estado">
                                        <span class="estado-badge <?php echo $claseEstado; ?>">
                                            <?php echo ucfirst(strtolower($estadoRaw)); ?>
                                        </span>
                                    </td>
                                    <td data-label="Acción">
                                        <!-- Aquí está la corrección: ahora mostrará botones si es ACTIVO o VENCIDO -->
                                        <?php if ($estadoUpper === 'ACTIVO' || $estadoUpper === 'VENCIDO'): ?>
                                            <div class="acciones-container">
                                                <?php if ($puedeRenovar): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="current_tab" value="prestamos">
                                                        <input type="hidden" name="renovar_id" value="<?php echo $p['id_prestamo']; ?>">
                                                        <button type="submit" class="btn-renovar" onclick="return confirm('¿Renovar este préstamo por 7 días más?')">
                                                            <i class="fas fa-sync-alt"></i> Renovar
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="texto-renovacion-max" title="Máximo de renovaciones alcanzado">
                                                        <i class="fas fa-ban"></i> Máx. renovaciones
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="current_tab" value="prestamos">
                                                    <input type="hidden" name="devolver_id" value="<?php echo $p['id_prestamo']; ?>">
                                                    <button type="submit" class="btn-devolver" onclick="return confirm('¿Confirmar devolución de este libro?')">
                                                        <i class="fas fa-check"></i> Devolver
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="texto-devuelto">
                                                <i class="fas fa-check-circle"></i> Completado
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="estado-vacio">
                    <div class="icono"></div>
                    <h3>Sin préstamos registrados</h3>
                    <p>Aún no has solicitado ningún libro en préstamo</p>
                    <a href="./catalogo.php" class="btn-explorar">Explorar Catálogo</a>
                </div>
            <?php endif; ?>

        <!-- TAB: RESERVAS -->
        <?php elseif ($tab === 'reservas'): ?>
            <?php if (count($reservas_activas) > 0): ?>
                <div class="reservas-grid">
                    <?php foreach ($reservas_activas as $r): ?>
                        <?php
                            $esDisponible = strtoupper($r['estado']) === 'DISPONIBLE';
                            $diasRestantes = 0;
                            if ($esDisponible && !empty($r['fecha_expiracion'])) {
                                $diasRestantes = max(0, ceil((strtotime($r['fecha_expiracion']) - time()) / 86400));
                            }
                        ?>
                        <div class="reserva-card <?php echo $esDisponible ? 'disponible' : 'pendiente'; ?>">
                            <div class="reserva-header">
                                <div class="reserva-icono">
                                    <?php echo $esDisponible ? '' : '⏳'; ?>
                                </div>
                                <div class="reserva-info">
                                    <span class="codigo-badge"><?php echo htmlspecialchars($r['codigo']); ?></span>
                                    <h3><?php echo htmlspecialchars($r['titulo']); ?></h3>
                                </div>
                            </div>
                            
                            <div class="reserva-body">
                                <div class="reserva-dato">
                                    <span class="dato-label">Estado:</span>
                                    <span class="estado-badge <?php echo $esDisponible ? 'estado-disponible' : 'estado-pendiente'; ?>">
                                        <?php echo $esDisponible ? '¡Disponible!' : 'En espera'; ?>
                                    </span>
                                </div>
                                
                                <div class="reserva-dato">
                                    <span class="dato-label">Posición en cola:</span>
                                    <span class="dato-valor">#<?php echo $r['posicion_actual']; ?></span>
                                </div>
                                
                                <div class="reserva-dato">
                                    <span class="dato-label">Fecha de reserva:</span>
                                    <span class="dato-valor"><?php echo date('d/m/Y', strtotime($r['fecha_reserva'])); ?></span>
                                </div>
                                
                                <?php if ($esDisponible): ?>
                                    <div class="reserva-alerta">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <span>Tienes <strong><?php echo $diasRestantes; ?> día<?php echo $diasRestantes != 1 ? 's' : ''; ?></strong> para reclamar este libro</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="reserva-footer">
                                <?php if ($esDisponible): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="current_tab" value="reservas">
                                        <input type="hidden" name="reclamar_reserva_id" value="<?php echo $r['id_reserva']; ?>">
                                        <button type="submit" class="btn-reclamar" onclick="return confirm('¿Reclamar este libro? Tendrás 7 días para recogerlo.')">
                                            <i class="fas fa-check"></i> Reclamar Libro
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="current_tab" value="reservas">
                                    <input type="hidden" name="cancelar_reserva_id" value="<?php echo $r['id_reserva']; ?>">
                                    <button type="submit" class="btn-cancelar-reserva" onclick="return confirm('¿Cancelar esta reserva?')">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="estado-vacio">
                    <div class="icono">🔖</div>
                    <h3>Sin reservas activas</h3>
                    <p>No tienes reservas pendientes en este momento</p>
                    <a href="./catalogo.php" class="btn-explorar">Explorar Catálogo</a>
                </div>
            <?php endif; ?>

        <!-- TAB: HISTORIAL -->
        <?php elseif ($tab === 'historial'): ?>
            <?php if (count($reservas_historial) > 0): ?>
                <div class="tabla-container">
                    <table class="tabla-prestamos">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Título del Libro</th>
                                <th>Fecha Reserva</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservas_historial as $r): ?>
                                <?php
                                    $claseEstado = '';
                                    $iconoEstado = '';
                                    $st = strtoupper($r['estado']);
                                    
                                    if ($st === 'CANCELADA') { $claseEstado = 'estado-cancelada'; $iconoEstado = '<i class="fas fa-times-circle"></i> '; }
                                    elseif ($st === 'EXPIRADA') { $claseEstado = 'estado-expirada'; $iconoEstado = '<i class="fas fa-clock"></i> '; }
                                    elseif ($st === 'RECLAMADA') { $claseEstado = 'estado-reclamada'; $iconoEstado = '<i class="fas fa-check-circle"></i> '; }
                                ?>
                                <tr>
                                    <td data-label="Código">
                                        <span class="codigo-badge"><?php echo htmlspecialchars($r['codigo']); ?></span>
                                    </td>
                                    <td data-label="Título">
                                        <span class="titulo-libro"><?php echo htmlspecialchars($r['titulo']); ?></span>
                                    </td>
                                    <td data-label="Fecha">
                                        <span class="fecha-prestamo">
                                            <?php echo date('d M Y', strtotime($r['fecha_reserva'])); ?>
                                        </span>
                                    </td>
                                    <td data-label="Estado">
                                        <span class="estado-badge <?php echo $claseEstado; ?>">
                                            <?php echo $iconoEstado; ?>
                                            <?php echo ucfirst(strtolower($r['estado'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="estado-vacio">
                    <div class="icono">📜</div>
                    <h3>Sin historial</h3>
                    <p>Aún no tienes reservas en el historial</p>
                    <a href="./catalogo.php" class="btn-explorar">Explorar Catálogo</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const closeButton = alert.querySelector('.btn-close');
                    if (closeButton) {
                        closeButton.click();
                    }
                }, 5000);
            });
        });
    </script>
</body>
</html>