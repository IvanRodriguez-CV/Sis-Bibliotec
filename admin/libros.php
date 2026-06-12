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

// Asegurar que exista la tabla de relación entre libros y géneros
try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute(['Libro_Genero']);
    if (!$stmt->fetchColumn()) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS Libro_Genero (
                id_libro INT NOT NULL,
                id_genero INT NOT NULL,
                PRIMARY KEY (id_libro, id_genero),
                INDEX idx_libro (id_libro),
                INDEX idx_genero (id_genero)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
} catch (PDOException $e) {
    // Si no se puede crear la tabla, continuar sin bloquear la carga inicial.
}

// Crear libro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $id_categoria_arr = isset($_POST['id_categoria']) ? (array)$_POST['id_categoria'] : [];
    $id_categoria = isset($id_categoria_arr[0]) ? intval($id_categoria_arr[0]) : 0;
    $autor_ids = isset($_POST['id_autor']) ? (array)$_POST['id_autor'] : [];
    $autor_ids = array_filter(array_map('intval', $autor_ids));
    $genero_ids = isset($_POST['id_genero']) ? (array)$_POST['id_genero'] : [];
    $genero_ids = array_filter(array_map('intval', $genero_ids));
    $codigo = trim($_POST['codigo']);
    $titulo = trim($_POST['titulo']);
    $editorial = trim($_POST['editorial']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $anio = intval($_POST['anio_publicacion']);
    $existencias = intval($_POST['existencias_totales']);
    $imagen = '';

    // Procesar imagen
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $originalName = $_FILES['imagen']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $upload_dir = '../uploads/libros/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = 'libro_' . time() . '.' . $ext;
            $imagen = 'uploads/libros/' . $filename;
            move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_dir . $filename);
        }
    }

    if (!empty($codigo) && !empty($titulo) && $id_categoria > 0) {
        // Ensure codigo is unique
        $baseCodigo = $codigo;
        $suffix = 0;
        $codigoUnique = $baseCodigo;
        $check = $pdo->prepare("SELECT COUNT(*) FROM Libro WHERE codigo = ?");
        while (true) {
            $check->execute([$codigoUnique]);
            if ($check->fetchColumn() == 0) break;
            $suffix++;
            $codigoUnique = $baseCodigo . '_' . $suffix;
        }

        // Insert into Libro con descripción
        $stmt = $pdo->prepare("INSERT INTO Libro (id_categoria, codigo, titulo, editorial, descripcion, imagen, anio_publicacion, existencias_totales) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_categoria, $codigoUnique, $titulo, $editorial, $descripcion, $imagen, $anio, $existencias]);
        $newId = $pdo->lastInsertId();
        
        if (!empty($autor_ids)) {
            $stmt2 = $pdo->prepare("INSERT INTO Libro_Autor (id_libro, id_autor) VALUES (?, ?)");
            foreach ($autor_ids as $sel_autor) {
                $stmt2->execute([$newId, $sel_autor]);
            }
        }
        if (!empty($genero_ids)) {
            $stmt3 = $pdo->prepare("INSERT INTO Libro_Genero (id_libro, id_genero) VALUES (?, ?)");
            foreach ($genero_ids as $sel_genero) {
                $stmt3->execute([$newId, $sel_genero]);
            }
        }
        
        $mensaje = 'Libro registrado correctamente.';
    } else {
        $mensaje = 'Error: Complete todos los campos obligatorios.';
        $tipo_mensaje = 'danger';
    }
}

// Editar libro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id_libro']);
    $id_categoria_arr = isset($_POST['id_categoria']) ? (array)$_POST['id_categoria'] : [];
    $id_categoria = isset($id_categoria_arr[0]) ? intval($id_categoria_arr[0]) : 0;
    $autor_ids = isset($_POST['id_autor']) ? (array)$_POST['id_autor'] : [];
    $autor_ids = array_filter(array_map('intval', $autor_ids));
    $genero_ids = isset($_POST['id_genero']) ? (array)$_POST['id_genero'] : [];
    $genero_ids = array_filter(array_map('intval', $genero_ids));
    $codigo = trim($_POST['codigo']);
    $titulo = trim($_POST['titulo']);
    $editorial = trim($_POST['editorial']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $anio = intval($_POST['anio_publicacion']);
    $existencias = intval($_POST['existencias_totales']);
    $imagen = trim($_POST['imagen_actual'] ?? '');

    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $originalName = $_FILES['imagen']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $upload_dir = '../uploads/libros/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = 'libro_' . time() . '.' . $ext;
            $imagen = 'uploads/libros/' . $filename;
            move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_dir . $filename);
        }
    }

    // Update Libro con descripción
    if (!empty($codigo) && !empty($titulo) && $id_categoria > 0) {
        $stmt = $pdo->prepare("UPDATE Libro SET id_categoria = ?, codigo = ?, titulo = ?, editorial = ?, descripcion = ?, imagen = ?, anio_publicacion = ?, existencias_totales = ? WHERE id_libro = ?");
        $stmt->execute([$id_categoria, $codigo, $titulo, $editorial, $descripcion, $imagen, $anio, $existencias, $id]);

        $del = $pdo->prepare("DELETE FROM Libro_Autor WHERE id_libro = ?");
        $del->execute([$id]);
        if (!empty($autor_ids)) {
            $ins = $pdo->prepare("INSERT INTO Libro_Autor (id_libro, id_autor) VALUES (?, ?)");
            foreach ($autor_ids as $sel_autor) {
                $ins->execute([$id, $sel_autor]);
            }
        }

        $delGenero = $pdo->prepare("DELETE FROM Libro_Genero WHERE id_libro = ?");
        $delGenero->execute([$id]);
        if (!empty($genero_ids)) {
            $insGenero = $pdo->prepare("INSERT INTO Libro_Genero (id_libro, id_genero) VALUES (?, ?)");
            foreach ($genero_ids as $sel_genero) {
                $insGenero->execute([$id, $sel_genero]);
            }
        }
        
        $mensaje = 'Libro actualizado correctamente.';
    } else {
        $mensaje = 'Error al actualizar el libro.';
        $tipo_mensaje = 'danger';
    }
}

// Eliminar libro
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    try {
        // Verificar si tiene préstamos asociados
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Prestamo WHERE id_libro = ? AND estado_prestamo IN ('Activo', 'Vencido')");
        $stmt->execute([$id]);
        $tiene_prestamos = $stmt->fetchColumn();
        
        if ($tiene_prestamos > 0) {
            $mensaje = 'No se puede eliminar el libro porque tiene préstamos activos.';
            $tipo_mensaje = 'warning';
        } else {
            // delete relations first
            $delRel = $pdo->prepare("DELETE FROM Libro_Autor WHERE id_libro = ?");
            $delRel->execute([$id]);
            $delGen = $pdo->prepare("DELETE FROM Libro_Genero WHERE id_libro = ?");
            $delGen->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM Libro WHERE id_libro = ?");
            $stmt->execute([$id]);
            $mensaje = 'Libro eliminado correctamente.';
        }
    } catch (PDOException $e) {
        $mensaje = 'Error al eliminar el libro.';
        $tipo_mensaje = 'danger';
    }
}

// Búsqueda
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$filtro_categoria = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;

// Consulta modificada para incluir los nombres de los géneros y categoría
$query = "SELECT L.*, 
    c.nombre AS nombre_categoria,
    GROUP_CONCAT(DISTINCT CONCAT(A.nombre,' ',A.apellido) SEPARATOR ', ') AS autores, 
    GROUP_CONCAT(DISTINCT A.id_autor) AS autores_ids,
    GROUP_CONCAT(DISTINCT LG.id_genero) AS generos_ids,
    GROUP_CONCAT(DISTINCT G.nombre SEPARATOR ', ') AS generos_nombres,
    (SELECT COUNT(*) FROM Prestamo p WHERE p.id_libro = L.id_libro AND p.estado_prestamo IN ('Activo', 'Vencido')) as prestamos_activos,
    (SELECT COUNT(*) FROM Reserva r WHERE r.id_libro = L.id_libro AND r.estado IN ('Pendiente', 'Disponible')) as reservas_activas
    FROM Libro L
    LEFT JOIN Categoria c ON L.id_categoria = c.id_categoria
    LEFT JOIN Libro_Autor LA ON LA.id_libro = L.id_libro
    LEFT JOIN Autores A ON A.id_autor = LA.id_autor
    LEFT JOIN Libro_Genero LG ON LG.id_libro = L.id_libro
    LEFT JOIN Genero G ON G.id_genero = LG.id_genero";

$condiciones = [];
$parametros = [];

if (!empty($busqueda)) {
    $condiciones[] = "(L.titulo LIKE ? OR L.codigo LIKE ? OR L.editorial LIKE ? OR A.nombre LIKE ? OR A.apellido LIKE ?)";
    $parametros[] = "%$busqueda%";
    $parametros[] = "%$busqueda%";
    $parametros[] = "%$busqueda%";
    $parametros[] = "%$busqueda%";
    $parametros[] = "%$busqueda%";
}

if ($filtro_categoria > 0) {
    $condiciones[] = "L.id_categoria = ?";
    $parametros[] = $filtro_categoria;
}

if (!empty($condiciones)) {
    $query .= " WHERE " . implode(" AND ", $condiciones);
}

$query .= " GROUP BY L.id_libro ORDER BY L.id_libro DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($parametros);
$libros = $stmt->fetchAll();

$categorias = $pdo->query("SELECT * FROM Categoria ORDER BY nombre ASC")->fetchAll();
$autores = $pdo->query("SELECT * FROM Autores ORDER BY apellido ASC, nombre ASC")->fetchAll();
try {
    $generos = $pdo->query("SELECT G.*, GROUP_CONCAT(CG.id_categoria) AS categorias FROM Genero G LEFT JOIN Categoria_Genero CG ON CG.id_genero = G.id_genero GROUP BY G.id_genero ORDER BY G.nombre ASC")->fetchAll();
} catch (PDOException $e) {
    $generos = [];
}

// Estadísticas
$total_libros = count($libros);
$total_existencias = array_sum(array_column($libros, 'existencias_totales'));
$total_prestamos_activos = array_sum(array_column($libros, 'prestamos_activos'));
$total_reservas = array_sum(array_column($libros, 'reservas_activas'));
$libros_sin_stock = count(array_filter($libros, fn($l) => $l['existencias_totales'] == 0));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Libros - Panel Admin</title>
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
        .stats-card.existencias { border-left-color: #28a745; }
        .stats-card.prestamos { border-left-color: #ffc107; }
        .stats-card.reservas { border-left-color: #17a2b8; }
        .stats-card.sin-stock { border-left-color: #dc3545; }
        
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
        
        .libro-imagen {
            width: 50px;
            height: 70px;
            object-fit: cover;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .libro-imagen-placeholder {
            width: 50px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .badge-categoria {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .badge-genero {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            padding: 0.3rem 0.7rem;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.75rem;
            margin: 0.1rem;
            display: inline-block;
        }
        
        .badge-existencias {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .badge-existencias.disponible {
            background: #28a745;
            color: white;
        }
        
        .badge-existencias.bajo {
            background: #ffc107;
            color: #000;
        }
        
        .badge-existencias.agotado {
            background: #dc3545;
            color: white;
        }
        
        .badge-prestamos {
            background: rgba(255, 193, 7, 0.2);
            color: #856404;
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-reservas {
            background: rgba(23, 162, 184, 0.2);
            color: #0c5460;
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .libro-titulo {
            font-weight: 600;
            color: #495057;
            margin: 0;
        }
        
        .libro-autor {
            font-size: 0.85rem;
            color: #6c757d;
            margin: 0;
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
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .categoria-dropdown-btn, .genero-dropdown-btn {
            text-align: left;
            border: 2px solid #e9ecef;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .categoria-dropdown-btn:hover, .genero-dropdown-btn:hover {
            border-color: #667eea;
        }
        
        .preview-imagen {
            max-width: 150px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .autor-select {
            min-height: 120px;
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
                    <h2><i class="fas fa-book me-2"></i>Gestión de Libros</h2>
                    <p class="mb-0 mt-2 opacity-75">Administra el catálogo de libros de la biblioteca</p>
                </div>
                <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#modalNuevoLibro">
                    <i class="fas fa-plus me-2"></i>Nuevo Libro
                </button>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md">
                <div class="stats-card total">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $total_libros; ?></p>
                            <p class="stats-label mb-0">Total Libros</p>
                        </div>
                        <i class="fas fa-book fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md">
                <div class="stats-card existencias">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $total_existencias; ?></p>
                            <p class="stats-label mb-0">Existencias</p>
                        </div>
                        <i class="fas fa-boxes fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md">
                <div class="stats-card prestamos">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $total_prestamos_activos; ?></p>
                            <p class="stats-label mb-0">Préstamos</p>
                        </div>
                        <i class="fas fa-hand-holding fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md">
                <div class="stats-card reservas">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $total_reservas; ?></p>
                            <p class="stats-label mb-0">Reservas</p>
                        </div>
                        <i class="fas fa-bookmark fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md">
                <div class="stats-card sin-stock">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stats-number"><?php echo $libros_sin_stock; ?></p>
                            <p class="stats-label mb-0">Sin Stock</p>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Búsqueda y Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-2"></i>Buscar Libro
                        </label>
                        <input type="text" name="buscar" class="form-control form-control-lg" 
                               placeholder="Buscar por título, código, autor o editorial..." 
                               value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-filter me-2"></i>Categoría
                        </label>
                        <select name="categoria" class="form-select form-select-lg">
                            <option value="0">Todas las categorías</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $filtro_categoria == $cat['id_categoria'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                        <?php if (!empty($busqueda) || $filtro_categoria > 0): ?>
                            <a href="libros.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Libros -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Libros Registrados
                    <span class="badge bg-light text-dark ms-2"><?php echo $total_libros; ?></span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($libros) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Portada</th>
                                    <th>Información</th>
                                    <th>Categoría</th>
                                    <th>Géneros</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($libros as $l): ?>
                                    <?php 
                                        $libroImagen = !empty($l['imagen'] ?? '') ? $l['imagen'] : '';
                                        $tieneImagen = $libroImagen && file_exists('../' . $libroImagen);
                                        
                                        $claseExistencias = 'disponible';
                                        if ($l['existencias_totales'] == 0) {
                                            $claseExistencias = 'agotado';
                                        } elseif ($l['existencias_totales'] <= 2) {
                                            $claseExistencias = 'bajo';
                                        }
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <?php if ($tieneImagen): ?>
                                                <img src="../<?php echo htmlspecialchars($libroImagen); ?>" alt="Portada" class="libro-imagen">
                                            <?php else: ?>
                                                <div class="libro-imagen-placeholder">
                                                    <i class="fas fa-book"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <p class="libro-titulo"><?php echo htmlspecialchars($l['titulo']); ?></p>
                                            <p class="libro-autor">
                                                <i class="fas fa-user-edit me-1"></i>
                                                <?php echo htmlspecialchars($l['autores'] ?? 'Sin autor'); ?>
                                            </p>
                                            <small class="text-muted">
                                                <strong>Código:</strong> <?php echo htmlspecialchars($l['codigo']); ?>
                                                <?php if (!empty($l['editorial'])): ?>
                                                    | <strong>Editorial:</strong> <?php echo htmlspecialchars($l['editorial']); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($l['anio_publicacion'])): ?>
                                                    | <strong>Año:</strong> <?php echo $l['anio_publicacion']; ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge-categoria">
                                                <i class="fas fa-tag me-1"></i>
                                                <?php echo htmlspecialchars($l['nombre_categoria'] ?? 'Sin categoría'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($l['generos_nombres'])): ?>
                                                <?php 
                                                    $generosArray = explode(', ', $l['generos_nombres']);
                                                    foreach (array_slice($generosArray, 0, 3) as $genero): 
                                                ?>
                                                    <span class="badge-genero">
                                                        <?php echo htmlspecialchars($genero); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                                <?php if (count($generosArray) > 3): ?>
                                                    <span class="badge bg-secondary">+<?php echo count($generosArray) - 3; ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Sin géneros</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-existencias <?php echo $claseExistencias; ?>">
                                                <?php echo $l['existencias_totales']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($l['prestamos_activos'] > 0): ?>
                                                <span class="badge-prestamos">
                                                    <i class="fas fa-hand-holding me-1"></i><?php echo $l['prestamos_activos']; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($l['reservas_activas'] > 0): ?>
                                                <span class="badge-reservas">
                                                    <i class="fas fa-bookmark me-1"></i><?php echo $l['reservas_activas']; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($l['prestamos_activos'] == 0 && $l['reservas_activas'] == 0): ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-warning me-1" 
                                                    onclick='editarLibro(<?php echo json_encode($l); ?>)'
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($l['prestamos_activos'] == 0): ?>
                                                <a href="libros.php?eliminar=<?php echo $l['id_libro']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('¿Estás seguro de eliminar este libro?')"
                                                   title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled 
                                                        title="No se puede eliminar: tiene préstamos activos">
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
                        <i class="fas fa-book"></i>
                        <h4>No se encontraron libros</h4>
                        <p>Intenta con otros criterios de búsqueda o registra un nuevo libro</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal Nuevo Libro -->
    <div class="modal fade" id="modalNuevoLibro" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>Nuevo Libro
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="libros.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Código Único <span class="text-danger">*</span></label>
                                <input type="text" name="codigo" class="form-control" placeholder="Ej: LIB001" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Título <span class="text-danger">*</span></label>
                                <input type="text" name="titulo" class="form-control" placeholder="Título del libro" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Categoría <span class="text-danger">*</span></label>
                                <div class="dropdown w-100">
                                    <button class="btn btn-outline-secondary dropdown-toggle w-100 categoria-dropdown-btn" type="button" id="categoriaDropdownNuevo" data-bs-toggle="dropdown">
                                        Seleccionar categoría
                                    </button>
                                    <div class="dropdown-menu w-100 p-3" style="max-height:250px; overflow-y:auto;">
                                        <?php foreach ($categorias as $cat): ?>
                                            <div class="form-check">
                                                <input class="form-check-input categoria-check-nuevo" type="checkbox" name="id_categoria[]" value="<?php echo $cat['id_categoria']; ?>" id="cat_nuevo_<?php echo $cat['id_categoria']; ?>">
                                                <label class="form-check-label" for="cat_nuevo_<?php echo $cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Géneros</label>
                                <div class="dropdown w-100">
                                    <button class="btn btn-outline-secondary dropdown-toggle w-100 genero-dropdown-btn" type="button" id="generoDropdownNuevo" data-bs-toggle="dropdown" disabled>
                                        Seleccionar géneros
                                    </button>
                                    <div class="dropdown-menu w-100 p-3" style="max-height:250px; overflow-y:auto;">
                                        <?php foreach ($generos as $gen): ?>
                                            <div class="form-check genero-check-nuevo-container" style="display:none;">
                                                <input class="form-check-input genero-check-nuevo" type="checkbox" name="id_genero[]" value="<?php echo $gen['id_genero']; ?>" data-categorias="<?php echo $gen['categorias']; ?>">
                                                <label class="form-check-label"><?php echo htmlspecialchars($gen['nombre']); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Autores <span class="text-danger">*</span></label>
                                <select name="id_autor[]" class="form-select autor-select" multiple required>
                                    <?php foreach ($autores as $aut): ?>
                                        <option value="<?php echo $aut['id_autor']; ?>"><?php echo htmlspecialchars($aut['nombre'] . ' ' . $aut['apellido']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Ctrl/Cmd + clic para seleccionar múltiples autores</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Editorial</label>
                                <input type="text" name="editorial" class="form-control" placeholder="Nombre de la editorial">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Año Publicación</label>
                                <input type="number" name="anio_publicacion" class="form-control" min="1000" max="<?php echo date('Y'); ?>" placeholder="<?php echo date('Y'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Existencias <span class="text-danger">*</span></label>
                                <input type="number" name="existencias_totales" class="form-control" min="0" value="1" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Descripción</label>
                                <textarea name="descripcion" class="form-control" rows="3" placeholder="Breve descripción del libro"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Imagen del Libro</label>
                                <input type="file" name="imagen" class="form-control" accept="image/*,.webp" id="imagenNuevo">
                                <small class="text-muted">JPG, PNG, GIF, WebP (máx 5MB)</small>
                                <div id="preview-nuevo" style="display:none; margin-top: 1rem;">
                                    <img id="imagen-preview-nuevo" src="" alt="Vista previa" class="preview-imagen">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Registrar Libro
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Libro -->
    <div class="modal fade" id="modalEditarLibro" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Libro
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="libros.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id_libro" id="edit_id_libro">
                    <input type="hidden" name="imagen_actual" id="edit_imagen_actual">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Código Único <span class="text-danger">*</span></label>
                                <input type="text" name="codigo" id="edit_codigo" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Título <span class="text-danger">*</span></label>
                                <input type="text" name="titulo" id="edit_titulo" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Categoría <span class="text-danger">*</span></label>
                                <div class="dropdown w-100">
                                    <button class="btn btn-outline-secondary dropdown-toggle w-100 categoria-dropdown-btn" type="button" id="categoriaDropdownEdit" data-bs-toggle="dropdown">
                                        Seleccionar categoría
                                    </button>
                                    <div class="dropdown-menu w-100 p-3" style="max-height:250px; overflow-y:auto;">
                                        <?php foreach ($categorias as $cat): ?>
                                            <div class="form-check">
                                                <input class="form-check-input categoria-check-edit" type="checkbox" name="id_categoria[]" value="<?php echo $cat['id_categoria']; ?>" id="cat_edit_<?php echo $cat['id_categoria']; ?>">
                                                <label class="form-check-label" for="cat_edit_<?php echo $cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Géneros</label>
                                <div class="dropdown w-100">
                                    <button class="btn btn-outline-secondary dropdown-toggle w-100 genero-dropdown-btn" type="button" id="generoDropdownEdit" data-bs-toggle="dropdown">
                                        Seleccionar géneros
                                    </button>
                                    <div class="dropdown-menu w-100 p-3" style="max-height:250px; overflow-y:auto;">
                                        <?php foreach ($generos as $gen): ?>
                                            <div class="form-check genero-check-edit-container">
                                                <input class="form-check-input genero-check-edit" type="checkbox" name="id_genero[]" value="<?php echo $gen['id_genero']; ?>" data-categorias="<?php echo $gen['categorias']; ?>">
                                                <label class="form-check-label"><?php echo htmlspecialchars($gen['nombre']); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Autores <span class="text-danger">*</span></label>
                                <select name="id_autor[]" id="edit_autores" class="form-select autor-select" multiple required>
                                    <?php foreach ($autores as $aut): ?>
                                        <option value="<?php echo $aut['id_autor']; ?>"><?php echo htmlspecialchars($aut['nombre'] . ' ' . $aut['apellido']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Editorial</label>
                                <input type="text" name="editorial" id="edit_editorial" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Año Publicación</label>
                                <input type="number" name="anio_publicacion" id="edit_anio" class="form-control" min="1000" max="<?php echo date('Y'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Existencias <span class="text-danger">*</span></label>
                                <input type="number" name="existencias_totales" id="edit_existencias" class="form-control" min="0" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Descripción</label>
                                <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Imagen del Libro</label>
                                <input type="file" name="imagen" class="form-control" accept="image/*,.webp" id="imagenEdit">
                                <small class="text-muted">Deja vacío para mantener la imagen actual</small>
                                <div id="preview-edit" style="display:none; margin-top: 1rem;">
                                    <img id="imagen-preview-edit" src="" alt="Vista previa" class="preview-imagen">
                                </div>
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
        // Funciones para el modal nuevo
        function updateCategoriaButtonTextNuevo() {
            const selected = Array.from(document.querySelectorAll('.categoria-check-nuevo:checked')).map(input => {
                const label = input.nextElementSibling;
                return label ? label.textContent.trim() : '';
            }).filter(Boolean);
            const button = document.getElementById('categoriaDropdownNuevo');
            if (!button) return;
            if (selected.length === 0) {
                button.textContent = 'Seleccionar categoría';
            } else if (selected.length === 1) {
                button.textContent = selected[0];
            } else {
                button.textContent = 'Seleccionadas: ' + selected.length;
            }
            updateGeneroOptionsNuevo();
        }

        function updateGeneroButtonTextNuevo() {
            const selected = Array.from(document.querySelectorAll('.genero-check-nuevo:checked')).map(input => {
                const label = input.nextElementSibling;
                return label ? label.textContent.trim() : '';
            }).filter(Boolean);
            const button = document.getElementById('generoDropdownNuevo');
            if (!button) return;
            if (selected.length === 0) {
                button.textContent = 'Seleccionar géneros';
            } else if (selected.length === 1) {
                button.textContent = selected[0];
            } else {
                button.textContent = 'Seleccionados: ' + selected.length;
            }
        }

        function updateGeneroOptionsNuevo() {
            const selectedCatIds = Array.from(document.querySelectorAll('.categoria-check-nuevo:checked')).map(input => input.value);
            const generoChecks = document.querySelectorAll('.genero-check-nuevo');
            const generoDropdown = document.getElementById('generoDropdownNuevo');

            if (selectedCatIds.length === 0) {
                generoChecks.forEach(input => {
                    input.checked = false;
                    input.closest('.form-check').style.display = 'none';
                });
                generoDropdown.disabled = true;
                generoDropdown.textContent = 'Seleccionar géneros';
                return;
            }

            generoDropdown.disabled = false;
            generoChecks.forEach(input => {
                const categorias = input.dataset.categorias ? input.dataset.categorias.toString().split(',').map(item => item.trim()) : [];
                const match = categorias.some(catId => selectedCatIds.includes(catId));
                input.closest('.form-check').style.display = match ? 'block' : 'none';
                input.disabled = !match;
                if (!match) input.checked = false;
            });

            updateGeneroButtonTextNuevo();
        }

        document.querySelectorAll('.categoria-check-nuevo').forEach(input => {
            input.addEventListener('change', updateCategoriaButtonTextNuevo);
        });

        document.querySelectorAll('.genero-check-nuevo').forEach(input => {
            input.addEventListener('change', updateGeneroButtonTextNuevo);
        });

        // Preview de imagen nuevo
        document.getElementById('imagenNuevo')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('imagen-preview-nuevo').src = event.target.result;
                    document.getElementById('preview-nuevo').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        // Funciones para el modal editar
        function updateCategoriaButtonTextEdit() {
            const selected = Array.from(document.querySelectorAll('.categoria-check-edit:checked')).map(input => {
                const label = input.nextElementSibling;
                return label ? label.textContent.trim() : '';
            }).filter(Boolean);
            const button = document.getElementById('categoriaDropdownEdit');
            if (!button) return;
            if (selected.length === 0) {
                button.textContent = 'Seleccionar categoría';
            } else if (selected.length === 1) {
                button.textContent = selected[0];
            } else {
                button.textContent = 'Seleccionadas: ' + selected.length;
            }
            updateGeneroOptionsEdit();
        }

        function updateGeneroButtonTextEdit() {
            const selected = Array.from(document.querySelectorAll('.genero-check-edit:checked')).map(input => {
                const label = input.nextElementSibling;
                return label ? label.textContent.trim() : '';
            }).filter(Boolean);
            const button = document.getElementById('generoDropdownEdit');
            if (!button) return;
            if (selected.length === 0) {
                button.textContent = 'Seleccionar géneros';
            } else if (selected.length === 1) {
                button.textContent = selected[0];
            } else {
                button.textContent = 'Seleccionados: ' + selected.length;
            }
        }

        function updateGeneroOptionsEdit(selectedGenero = []) {
            const selectedCatIds = Array.from(document.querySelectorAll('.categoria-check-edit:checked')).map(input => input.value);
            const generoChecks = document.querySelectorAll('.genero-check-edit');
            const generoDropdown = document.getElementById('generoDropdownEdit');
            const selectedGeneroIds = (Array.isArray(selectedGenero) ? selectedGenero : selectedGenero.toString().split(',').map(item => item.trim())).filter(Boolean);

            if (selectedCatIds.length === 0) {
                generoChecks.forEach(input => {
                    input.checked = false;
                    input.closest('.form-check').style.display = 'none';
                });
                generoDropdown.disabled = true;
                generoDropdown.textContent = 'Seleccionar géneros';
                return;
            }

            generoDropdown.disabled = false;
            generoChecks.forEach(input => {
                const categorias = input.dataset.categorias ? input.dataset.categorias.toString().split(',').map(item => item.trim()) : [];
                const match = categorias.some(catId => selectedCatIds.includes(catId));
                input.closest('.form-check').style.display = match ? 'block' : 'none';
                input.disabled = !match;
                if (!match) {
                    input.checked = false;
                }
                if (match && selectedGeneroIds.includes(input.value)) {
                    input.checked = true;
                }
            });

            updateGeneroButtonTextEdit();
        }

        document.querySelectorAll('.categoria-check-edit').forEach(input => {
            input.addEventListener('change', updateCategoriaButtonTextEdit);
        });

        document.querySelectorAll('.genero-check-edit').forEach(input => {
            input.addEventListener('change', updateGeneroButtonTextEdit);
        });

        // Preview de imagen editar
        document.getElementById('imagenEdit')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('imagen-preview-edit').src = event.target.result;
                    document.getElementById('preview-edit').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        // Función para cargar datos en el modal de edición
        function editarLibro(libro) {
            document.getElementById('edit_id_libro').value = libro.id_libro;
            document.getElementById('edit_codigo').value = libro.codigo;
            document.getElementById('edit_titulo').value = libro.titulo;
            document.getElementById('edit_editorial').value = libro.editorial || '';
            document.getElementById('edit_descripcion').value = libro.descripcion || '';
            document.getElementById('edit_anio').value = libro.anio_publicacion || '';
            document.getElementById('edit_existencias').value = libro.existencias_totales;
            document.getElementById('edit_imagen_actual').value = libro.imagen || '';

            // Categorías
            document.querySelectorAll('.categoria-check-edit').forEach(input => input.checked = false);
            const selectedCats = libro.id_categoria ? libro.id_categoria.toString().split(',').map(item => item.trim()) : [];
            document.querySelectorAll('.categoria-check-edit').forEach(input => {
                if (selectedCats.includes(input.value)) {
                    input.checked = true;
                }
            });
            updateCategoriaButtonTextEdit();
            updateGeneroOptionsEdit(libro.generos_ids || '');

            // Autores
            const autorSelect = document.getElementById('edit_autores');
            const selectedAuths = libro.autores_ids ? libro.autores_ids.toString().split(',').map(item => item.trim()) : [];
            Array.from(autorSelect.options).forEach(option => {
                option.selected = selectedAuths.includes(option.value);
            });

            // Imagen preview
            if (libro.imagen) {
                document.getElementById('imagen-preview-edit').src = '../' + libro.imagen;
                document.getElementById('preview-edit').style.display = 'block';
            } else {
                document.getElementById('preview-edit').style.display = 'none';
            }

            var modal = new bootstrap.Modal(document.getElementById('modalEditarLibro'));
            modal.show();
        }
    </script>
</body>
</html>