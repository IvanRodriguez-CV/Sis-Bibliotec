<?php
require_once '../config/conexion.php';
session_start();

// Validación de sesión y rol
if (!isset($_SESSION['id_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

// Procesar eliminación o actualización de préstamo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_prestamo'])) {
    $id_p = intval($_POST['id_prestamo']);

    $pdo->beginTransaction();
    $stmtDel = $pdo->prepare("DELETE FROM Prestamo WHERE id_prestamo = ?");
    $stmtDel->execute([$id_p]);

    if ($stmtDel->rowCount() > 0) {
        $pdo->commit();
    } else {
        $pdo->rollBack();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_estado'])) {
    $id_p = intval($_POST['id_prestamo']);
    $nuevo_estado = $_POST['estado_prestamo'];

    $campos_fecha = ['fecha_solicitud', 'fecha_prestamo', 'fecha_entrega', 'fecha_limite', 'fecha_devolucion'];
    $valores_fecha = [];

    foreach ($campos_fecha as $campo) {
        if (isset($_POST[$campo])) {
            $valor = trim($_POST[$campo]);
            $valores_fecha[$campo] = $valor === '' ? null : $valor;
        }
    }

    $pdo->beginTransaction();

    $stmtActual = $pdo->prepare("SELECT estado_prestamo, id_libro FROM Prestamo WHERE id_prestamo = ?");
    $stmtActual->execute([$id_p]);
    $prestamoActual = $stmtActual->fetch(PDO::FETCH_ASSOC);

    if ($prestamoActual) {
        $estadoAnterior = $prestamoActual['estado_prestamo'];
        $id_libro = $prestamoActual['id_libro'];

        if ($nuevo_estado === 'Devuelto' && empty($valores_fecha['fecha_devolucion'])) {
            $valores_fecha['fecha_devolucion'] = date('Y-m-d');
        }

        $setPartes = ['estado_prestamo = ?'];
        $parametros = [$nuevo_estado];

        foreach ($valores_fecha as $campo => $valor) {
            $setPartes[] = "$campo = ?";
            $parametros[] = $valor;
        }

        $parametros[] = $id_p;
        $updPrestamo = $pdo->prepare("UPDATE Prestamo SET " . implode(', ', $setPartes) . " WHERE id_prestamo = ?");
        $updPrestamo->execute($parametros);

        if ($nuevo_estado === 'Devuelto' && $estadoAnterior !== 'Devuelto') {
            $updStock = $pdo->prepare("UPDATE Libro SET existencias_totales = existencias_totales + 1 WHERE id_libro = ?");
            $updStock->execute([$id_libro]);
        }

        $pdo->commit();
    } else {
        $pdo->rollBack();
    }
}

$prestamos = $pdo->query("SELECT p.*, l.titulo as libro_titulo, u.nombre_completo as usuario_nombre, u.carnet_codigo 
                          FROM Prestamo p 
                          JOIN Libro l ON p.id_libro = l.id_libro 
                          JOIN Usuario u ON p.id_usuario = u.id_usuario 
                          ORDER BY p.id_prestamo DESC")->fetchAll();

$fechaCampos = [
    'fecha_solicitud' => 'F. Solicitud',
    'fecha_prestamo' => 'F. Préstamo',
    'fecha_entrega' => 'F. Salida',
    'fecha_limite' => 'F. Límite',
    'fecha_devolucion' => 'F. Retorno',
];

$camposDisponibles = [];
if (!empty($prestamos)) {
    foreach ($fechaCampos as $campo => $etiqueta) {
        if (array_key_exists($campo, $prestamos[0])) {
            $camposDisponibles[$campo] = $etiqueta;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administración de Préstamos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Estilos para una vista más compacta y ordenada */
        table.table td, table.table th { vertical-align: middle; }
        .fecha-pequena { font-size: 0.85rem; color: #444; }
        details[open] summary::after { content: "▲"; float: right; }
        details summary::after { content: "▼"; float: right; }
        details summary { list-style: none; cursor: pointer; }
        .editar-form { background: #f8f9fa; border: 1px solid #e9ecef; padding: .75rem; border-radius: .375rem; }
        .min-input { min-width: 140px; }
    </style>
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
        <h2 class="fw-bold mb-4 text-center">Gestión de Préstamos Activos e Historial</h2>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Lector (Código)</th>
                                <th>Libro Solicitado</th>
                                <?php foreach ($camposDisponibles as $etiqueta): ?>
                                    <th><?php echo htmlspecialchars($etiqueta); ?></th>
                                <?php endforeach; ?>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prestamos as $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['usuario_nombre']); ?> (<?php echo $p['carnet_codigo']; ?>)</td>
                                    <td><?php echo htmlspecialchars($p['libro_titulo']); ?></td>
                                    <?php foreach ($camposDisponibles as $campo => $etiqueta): ?>
                                        <td><?php echo $p[$campo] ?? '---'; ?></td>
                                    <?php endforeach; ?>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo ($p['estado_prestamo']=='Activo') ? 'primary' : 
                                                 (($p['estado_prestamo']=='Devuelto') ? 'success' : 
                                                 (($p['estado_prestamo']=='Vencido') ? 'warning' : 'danger')); ?>">
                                            <?php echo htmlspecialchars($p['estado_prestamo']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <details>
                                            <summary class="small text-muted">Editar</summary>
                                            <form action="prestamos.php" method="POST" class="editar-form mt-2">
                                                <input type="hidden" name="id_prestamo" value="<?php echo $p['id_prestamo']; ?>">
                                                <div class="d-flex flex-wrap gap-2 mb-2">
                                                    <?php foreach ($camposDisponibles as $campo => $etiqueta): ?>
                                                        <div class="d-flex flex-column min-input">
                                                            <label class="form-label mb-1 small text-muted"><?php echo htmlspecialchars($etiqueta); ?></label>
                                                            <input type="date" name="<?php echo $campo; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p[$campo] ?? ''); ?>">
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <select name="estado_prestamo" class="form-select form-select-sm" style="width:auto;">
                                                        <option value="Activo" <?php if($p['estado_prestamo']=='Activo') echo 'selected'; ?>>Activo</option>
                                                        <option value="Devuelto" <?php if($p['estado_prestamo']=='Devuelto') echo 'selected'; ?>>Devuelto</option>
                                                        <option value="Vencido" <?php if($p['estado_prestamo']=='Vencido') echo 'selected'; ?>>Vencido</option>
                                                        <option value="Perdido" <?php if($p['estado_prestamo']=='Perdido') echo 'selected'; ?>>Perdido</option>
                                                    </select>
                                                    <button type="submit" name="actualizar_estado" class="btn btn-sm btn-primary">Guardar</button>
                                                    <button type="submit" name="eliminar_prestamo" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este préstamo?');">Eliminar</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="this.closest('details').removeAttribute('open')">Cerrar</button>
                                                </div>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
