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

function obtenerIdsGenerosDesdeTexto($pdo, $texto) {
    $ids = [];
    $texto = trim($texto);
    if ($texto === '') {
        return $ids;
    }

    $generos = array_filter(array_map('trim', explode(',', $texto)));
    $stmtSelect = $pdo->prepare("SELECT id_genero FROM Genero WHERE LOWER(nombre) = LOWER(?)");
    $stmtInsert = $pdo->prepare("INSERT INTO Genero (nombre) VALUES (?)");

    foreach ($generos as $generoNuevo) {
        if ($generoNuevo === '') {
            continue;
        }
        $stmtSelect->execute([$generoNuevo]);
        $row = $stmtSelect->fetch();
        if ($row) {
            $ids[] = $row['id_genero'];
        } else {
            $stmtInsert->execute([$generoNuevo]);
            $ids[] = $pdo->lastInsertId();
        }
    }

    return $ids;
}

// Registrar nueva categoría
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $nombre = trim($_POST['nombre']);
    $generosSeleccionados = isset($_POST['generos']) ? array_map('intval', $_POST['generos']) : [];
    $nuevosGenerosTexto = trim($_POST['nuevos_generos'] ?? '');

    if ($nuevosGenerosTexto !== '') {
        $nuevosIds = obtenerIdsGenerosDesdeTexto($pdo, $nuevosGenerosTexto);
        $generosSeleccionados = array_unique(array_merge($generosSeleccionados, $nuevosIds), SORT_NUMERIC);
    }

    if (!empty($nombre)) {
        try {
            // Verificar si ya existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Categoria WHERE LOWER(nombre) = LOWER(?)");
            $stmt->execute([$nombre]);
            
            if ($stmt->fetchColumn() > 0) {
                $mensaje = 'Esta categoría ya está registrada.';
                $tipo_mensaje = 'warning';
            } else {
                $stmt = $pdo->prepare("INSERT INTO Categoria (nombre) VALUES (?)");
                $stmt->execute([$nombre]);
                $idCategoria = $pdo->lastInsertId();

                if (!empty($generosSeleccionados)) {
                    $stmtRel = $pdo->prepare("INSERT INTO Categoria_Genero (id_categoria, id_genero) VALUES (?, ?)");
                    foreach ($generosSeleccionados as $idGenero) {
                        $stmtRel->execute([$idCategoria, intval($idGenero)]);
                    }
                }

                $mensaje = 'Categoría registrada correctamente.';
            }
        } catch (PDOException $e) {
            $mensaje = 'Error al registrar la categoría.';
            $tipo_mensaje = 'danger';
        }
    }
}

// Modificar categoría existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id_categoria']);
    $nombre = trim($_POST['nombre']);
    $generosSeleccionados = isset($_POST['generos']) ? array_map('intval', $_POST['generos']) : [];
    $nuevosGenerosTexto = trim($_POST['nuevos_generos'] ?? '');

    if ($nuevosGenerosTexto !== '') {
        $nuevosIds = obtenerIdsGenerosDesdeTexto($pdo, $nuevosGenerosTexto);
        $generosSeleccionados = array_unique(array_merge($generosSeleccionados, $nuevosIds), SORT_NUMERIC);
    }

    if (!empty($nombre)) {
        try {
            $stmt = $pdo->prepare("UPDATE Categoria SET nombre = ? WHERE id_categoria = ?");
            $stmt->execute([$nombre, $id]);

            $stmtDelete = $pdo->prepare("DELETE FROM Categoria_Genero WHERE id_categoria = ?");
            $stmtDelete->execute([$id]);

            if (!empty($generosSeleccionados)) {
                $stmtRel = $pdo->prepare("INSERT INTO Categoria_Genero (id_categoria, id_genero) VALUES (?, ?)");
                $generosInsertados = [];
                foreach ($generosSeleccionados as $idGenero) {
                    $idGeneroInt = intval($idGenero);
                    if ($idGeneroInt > 0 && !in_array($idGeneroInt, $generosInsertados, true)) {
                        $stmtRel->execute([$id, $idGeneroInt]);
                        $generosInsertados[] = $idGeneroInt;
                    }
                }
            }

            $mensaje = 'Categoría actualizada correctamente.';
        } catch (PDOException $e) {
            $mensaje = 'Error al actualizar la categoría.';
            $tipo_mensaje = 'danger';
        }
    }
}

// Eliminar categoría
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    
    try {
        // Verificar si tiene libros asociados
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Libro WHERE id_categoria = ?");
        $stmt->execute([$id]);
        $tiene_libros = $stmt->fetchColumn();
        
        if ($tiene_libros > 0) {
            $mensaje = 'No se puede eliminar la categoría porque tiene ' . $tiene_libros . ' libro(s) asociado(s).';
            $tipo_mensaje = 'warning';
        } else {
            // Eliminar relaciones primero
            $stmt = $pdo->prepare("DELETE FROM Categoria_Genero WHERE id_categoria = ?");
            $stmt->execute([$id]);
            
            $stmt = $pdo->prepare("DELETE FROM Categoria WHERE id_categoria = ?");
            $stmt->execute([$id]);
            $mensaje = 'Categoría eliminada correctamente.';
        }
    } catch (PDOException $e) {
        $mensaje = 'Error al eliminar la categoría.';
        $tipo_mensaje = 'danger';
    }
}

// Búsqueda
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

$query = "SELECT c.id_categoria,
            c.nombre,
            GROUP_CONCAT(g.id_genero ORDER BY g.nombre) AS generos_ids,
            GROUP_CONCAT(g.nombre ORDER BY g.nombre SEPARATOR ', ') AS generos,
            (SELECT COUNT(*) FROM Libro l WHERE l.id_categoria = c.id_categoria) as total_libros
     FROM Categoria c
     LEFT JOIN Categoria_Genero cg ON c.id_categoria = cg.id_categoria
     LEFT JOIN Genero g ON cg.id_genero = g.id_genero";

if (!empty($busqueda)) {
    $query .= " WHERE c.nombre LIKE ?";
    $stmt = $pdo->prepare($query . " GROUP BY c.id_categoria ORDER BY c.nombre ASC");
    $stmt->execute(["%$busqueda%"]);
} else {
    $stmt = $pdo->prepare($query . " GROUP BY c.id_categoria ORDER BY c.nombre ASC");
    $stmt->execute();
}

$categorias = $stmt->fetchAll();

// Obtener todos los géneros
$generos = $pdo->query("SELECT * FROM Genero ORDER BY nombre")->fetchAll();

// Estadísticas
$total_categorias = count($categorias);
$total_generos = count($generos);
$categorias_con_libros = count(array_filter($categorias, fn($c) => $c['total_libros'] > 0));
$total_libros_en_categorias = array_sum(array_column($categorias, 'total_libros'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Categorías - Panel Admin</title>
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
        .stats-card.generos { border-left-color: #17a2b8; }
        .stats-card.con-libros { border-left-color: #28a745; }
        .stats-card.libros { border-left-color: #ffc107; }
        
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
        
        .categoria-icon {
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
        
        .categoria-nombre {
            font-weight: 600;
            color: #495057;
            margin: 0;
        }
        
        .badge-genero {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            padding: 0.3rem 0.7rem;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.75rem;
            margin: 0.15rem;
            display: inline-block;
        }
        
        .badge-libros {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #000;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .badge-sin-generos {
            background: #e9ecef;
            color: #6c757d;
            padding: 0.3rem 0.7rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-style: italic;
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
        
        .generos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.5rem;
            max-height: 250px;
            overflow-y: auto;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            background: #f8f9fa;
        }
        
        .genero-check-item {
            background: white;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .genero-check-item:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .generos-seleccionados {
            margin-top: 1rem;
            padding: 0.75rem;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 8px;
            min-height: 50px;
        }
        
        .genero-tag {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.3rem 0.7rem;
            border-radius: 15px;
            font-size: 0.8rem;
            margin: 0.2rem;
            font-weight: 600;
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
                    <h2><i class="fas fa-tags me-2"></i>Gestión de Categorías y Géneros</h2>
                    <p class="mb-0 mt-2 opacity-75">Administra las categorías y géneros literarios</p>
                </div>
                <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#modalNuevaCategoria">
                    <i class="fas fa-plus me-2"></i>Nueva Categoría
                </button>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stats-card total">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $total_categorias; ?></p>
                            <p class="stats-label mb-0">Categorías</p>
                        </div>
                        <i class="fas fa-tags fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card generos">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $total_generos; ?></p>
                            <p class="stats-label mb-0">Géneros</p>
                        </div>
                        <i class="fas fa-bookmark fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card con-libros">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $categorias_con_libros; ?></p>
                            <p class="stats-label mb-0">Con Libros</p>
                        </div>
                        <i class="fas fa-book fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card libros">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $total_libros_en_categorias; ?></p>
                            <p class="stats-label mb-0">Libros Total</p>
                        </div>
                        <i class="fas fa-layer-group fa-3x opacity-25"></i>
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
                            <i class="fas fa-search me-2"></i>Buscar Categoría
                        </label>
                        <input type="text" name="buscar" class="form-control form-control-lg" 
                               placeholder="Buscar por nombre de categoría..." 
                               value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                        <?php if (!empty($busqueda)): ?>
                            <a href="categorias.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Categorías -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Categorías Registradas
                    <span class="badge bg-light text-dark ms-2"><?php echo $total_categorias; ?></span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($categorias) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Categoría</th>
                                    <th>Géneros Asociados</th>
                                    <th class="text-center">Libros</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categorias as $c): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <div class="categoria-icon">
                                                    <i class="fas fa-tag"></i>
                                                </div>
                                                <div>
                                                    <p class="categoria-nombre"><?php echo htmlspecialchars($c['nombre']); ?></p>
                                                    <small class="text-muted">ID: #<?php echo $c['id_categoria']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($c['generos'])): ?>
                                                <?php 
                                                    $generosArray = explode(', ', $c['generos']);
                                                    foreach ($generosArray as $genero): 
                                                ?>
                                                    <span class="badge-genero">
                                                        <i class="fas fa-bookmark me-1"></i>
                                                        <?php echo htmlspecialchars($genero); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="badge-sin-generos">
                                                    <i class="fas fa-info-circle me-1"></i> Sin géneros asociados
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($c['total_libros'] > 0): ?>
                                                <span class="badge-libros">
                                                    <i class="fas fa-book me-1"></i>
                                                    <?php echo $c['total_libros']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-book-open me-1"></i> 0
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-warning me-1" 
                                                    onclick='editarCategoria(<?php echo json_encode($c); ?>)'
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($c['total_libros'] == 0): ?>
                                                <a href="categorias.php?eliminar=<?php echo $c['id_categoria']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('¿Estás seguro de eliminar esta categoría?')"
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
                        <i class="fas fa-tags"></i>
                        <h4>No se encontraron categorías</h4>
                        <p>Intenta con otros criterios de búsqueda o registra una nueva categoría</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal Nueva Categoría -->
    <div class="modal fade" id="modalNuevaCategoria" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>Nueva Categoría
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="categorias.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre de la Categoría <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control form-control-lg" 
                                   placeholder="Ej: Novela, Ciencia Ficción, Historia" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-bookmark me-2"></i>Géneros Asociados
                            </label>
                            <div class="generos-grid">
                                <?php foreach ($generos as $g): ?>
                                    <div class="genero-check-item">
                                        <div class="form-check">
                                            <input class="form-check-input genero-check-nuevo" type="checkbox" 
                                                   name="generos[]" value="<?php echo $g['id_genero']; ?>" 
                                                   id="gen_nuevo_<?php echo $g['id_genero']; ?>"
                                                   onchange="actualizarGenerosSeleccionados('nuevo')">
                                            <label class="form-check-label" for="gen_nuevo_<?php echo $g['id_genero']; ?>">
                                                <?php echo htmlspecialchars($g['nombre']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div id="generos-seleccionados-nuevo" class="generos-seleccionados" style="display:none;">
                                <small class="text-muted d-block mb-2"><strong>Géneros seleccionados:</strong></small>
                                <div id="tags-nuevo"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-plus me-2"></i>O Agregar Nuevos Géneros
                            </label>
                            <input type="text" name="nuevos_generos" class="form-control" 
                                   placeholder="Ej: Terror, Misterio, Romance (separados por comas)">
                            <small class="text-muted">Los géneros nuevos se crearán automáticamente si no existen</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Registrar Categoría
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Categoría -->
    <div class="modal fade" id="modalEditarCategoria" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Categoría
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="categorias.php" method="POST">
                    <input type="hidden" name="id_categoria" id="edit_id_categoria">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre de la Categoría <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="edit_nombre" class="form-control form-control-lg" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-bookmark me-2"></i>Géneros Asociados
                            </label>
                            <div class="generos-grid">
                                <?php foreach ($generos as $g): ?>
                                    <div class="genero-check-item">
                                        <div class="form-check">
                                            <input class="form-check-input genero-check-edit" type="checkbox" 
                                                   name="generos[]" value="<?php echo $g['id_genero']; ?>" 
                                                   id="gen_edit_<?php echo $g['id_genero']; ?>"
                                                   onchange="actualizarGenerosSeleccionados('edit')">
                                            <label class="form-check-label" for="gen_edit_<?php echo $g['id_genero']; ?>">
                                                <?php echo htmlspecialchars($g['nombre']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div id="generos-seleccionados-edit" class="generos-seleccionados" style="display:none;">
                                <small class="text-muted d-block mb-2"><strong>Géneros seleccionados:</strong></small>
                                <div id="tags-edit"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-plus me-2"></i>O Agregar Nuevos Géneros
                            </label>
                            <input type="text" name="nuevos_generos" class="form-control" 
                                   placeholder="Ej: Terror, Misterio (separados por comas)">
                            <small class="text-muted">Los géneros nuevos se crearán automáticamente</small>
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
        // Datos de géneros para mostrar nombres
        const generosData = <?php echo json_encode($generos); ?>;
        
        function actualizarGenerosSeleccionados(tipo) {
            const checkboxes = document.querySelectorAll(`.genero-check-${tipo}:checked`);
            const container = document.getElementById(`generos-seleccionados-${tipo}`);
            const tagsContainer = document.getElementById(`tags-${tipo}`);
            
            if (checkboxes.length === 0) {
                container.style.display = 'none';
                return;
            }
            
            container.style.display = 'block';
            tagsContainer.innerHTML = '';
            
            checkboxes.forEach(cb => {
                const label = cb.nextElementSibling.textContent.trim();
                const tag = document.createElement('span');
                tag.className = 'genero-tag';
                tag.innerHTML = `<i class="fas fa-bookmark me-1"></i>${label}`;
                tagsContainer.appendChild(tag);
            });
        }
        
        function editarCategoria(categoria) {
            document.getElementById('edit_id_categoria').value = categoria.id_categoria;
            document.getElementById('edit_nombre').value = categoria.nombre;
            
            // Resetear checkboxes
            document.querySelectorAll('.genero-check-edit').forEach(cb => cb.checked = false);
            
            // Marcar géneros asociados
            if (categoria.generos_ids) {
                const ids = categoria.generos_ids.split(',').map(id => id.trim());
                document.querySelectorAll('.genero-check-edit').forEach(cb => {
                    if (ids.includes(cb.value)) {
                        cb.checked = true;
                    }
                });
            }
            
            actualizarGenerosSeleccionados('edit');
            
            var modal = new bootstrap.Modal(document.getElementById('modalEditarCategoria'));
            modal.show();
        }
        
        // Inicializar tags al cargar
        document.addEventListener('DOMContentLoaded', function() {
            actualizarGenerosSeleccionados('nuevo');
        });
    </script>
</body>
</html>