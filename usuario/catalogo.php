<?php
require_once '../config/conexion.php';
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../public/login.php');
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_libro'])) {
    $id_libro_solicitado = filter_input(INPUT_POST, 'id_libro', FILTER_VALIDATE_INT);
    if ($id_libro_solicitado === false || $id_libro_solicitado === null) {
        $id_libro_solicitado = isset($_POST['id_libro']) ? intval($_POST['id_libro']) : 0;
    }
    $busqueda = isset($_POST['buscar']) ? trim($_POST['buscar']) : '';

    if ($id_libro_solicitado > 0) {
        try {
            $pdo->beginTransaction();

            $stmtLibro = $pdo->prepare("SELECT existencias_totales FROM Libro WHERE id_libro = ? FOR UPDATE");
            $stmtLibro->execute([$id_libro_solicitado]);
            $libroInfo = $stmtLibro->fetch();

            if ($libroInfo && isset($libroInfo['existencias_totales']) && $libroInfo['existencias_totales'] > 0) {
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Prestamo WHERE id_libro = ? AND id_usuario = ? AND estado_prestamo IN ('Activo','Vencido')");
                $stmtCheck->execute([$id_libro_solicitado, $id_usuario]);
                $solicitudesActivas = (int) $stmtCheck->fetchColumn();

                if ($solicitudesActivas > 0) {
                    $pdo->rollBack();
                    $mensaje = 'Ya tienes un préstamo activo o vencido para este libro.';
                } else {
                    $stmtPrestamo = $pdo->prepare("INSERT INTO Prestamo (id_libro, id_usuario, fecha_prestamo, fecha_entrega, estado_prestamo) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'Activo')");
                    $stmtPrestamo->execute([$id_libro_solicitado, $id_usuario]);

                    $stmtUpdate = $pdo->prepare("UPDATE Libro SET existencias_totales = existencias_totales - 1 WHERE id_libro = ?");
                    $stmtUpdate->execute([$id_libro_solicitado]);

                    $pdo->commit();
                    $mensaje = 'Préstamo solicitado correctamente.';
                }
            } else {
                $pdo->rollBack();
                $mensaje = 'No hay existencias disponibles para este libro.';
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje = 'No se pudo procesar la solicitud. Intente de nuevo.';
        }
    } else {
        $mensaje = 'Libro no válido.';
    }

    $_SESSION['mensaje_catalogo'] = $mensaje;
    $redirectUrl = 'catalogo.php';
    if ($busqueda !== '') {
        $redirectUrl .= '?buscar=' . urlencode($busqueda);
    }
    header('Location: ' . $redirectUrl);
    exit;
}

$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$mensaje = $_SESSION['mensaje_catalogo'] ?? '';
unset($_SESSION['mensaje_catalogo']);

$sql = "SELECT l.*, c.nombre AS nombre_categoria, CONCAT(a.nombre, ' ', a.apellido) AS nombre_autor 
        FROM Libro l
        INNER JOIN Categoria c ON l.id_categoria = c.id_categoria
        INNER JOIN Autores a ON l.id_autor = a.id_autor";

if (!empty($busqueda)) {
    $sql .= " WHERE l.titulo LIKE ? OR l.codigo LIKE ? OR CONCAT(a.nombre, ' ', a.apellido) LIKE ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$busqueda%", "%$busqueda%", "%$busqueda%"]);
} else {
    $stmt = $pdo->query($sql);
}
$libros = $stmt->fetchAll();

function obtenerCampoImagen(array $libro): ?string {
    $camposImagen = ['imagen', 'imagen_libro', 'ruta_imagen', 'ruta_portada', 'portada', 'foto'];
    foreach ($camposImagen as $campo) {
        if (!empty($libro[$campo])) {
            return $libro[$campo];
        }
    }

    foreach ($libro as $clave => $valor) {
        if (!empty($valor) && preg_match('/(imagen|portada|foto)/i', $clave)) {
            return $valor;
        }
    }

    return null;
}

function construirUrlImagen(string $imagenLibro): string {
    if (preg_match('#^(https?://|/)#i', $imagenLibro)) {
        return $imagenLibro;
    }

    $imagenLibroLimpia = ltrim($imagenLibro, '/\\');
    $rutasPosibles = [
        __DIR__ . '/' . $imagenLibro => $imagenLibro,
        __DIR__ . '/../' . $imagenLibroLimpia => '../' . $imagenLibroLimpia,
        __DIR__ . '/../uploads/libros/' . basename($imagenLibro) => '../uploads/libros/' . basename($imagenLibro),
        __DIR__ . '/../assets/images/' . basename($imagenLibro) => '../assets/images/' . basename($imagenLibro),
        __DIR__ . '/../assets/images/' . $imagenLibro => '../assets/images/' . $imagenLibro,
    ];

    foreach ($rutasPosibles as $rutaArchivo => $urlRelativa) {
        if (file_exists($rutaArchivo)) {
            return $urlRelativa;
        }
    }

    return '';
}

function obtenerCamposAdicionales(array $libro): array {
    $excluir = [
        'id_libro',
        'id_categoria',
        'id_autor',
        'titulo',
        'codigo',
        'descripcion',
        'imagen',
        'imagen_libro',
        'ruta_imagen',
        'ruta_portada',
        'portada',
        'foto',
        'nombre_categoria',
        'nombre_autor',
        'fecha_solicitud',
        'estado',
    ];

    $campos = [];
    foreach ($libro as $clave => $valor) {
        if (in_array($clave, $excluir, true)) {
            continue;
        }

        if (is_null($valor) || trim((string) $valor) === '') {
            continue;
        }

        $etiqueta = ucfirst(str_replace('_', ' ', $clave));
        $campos[$etiqueta] = htmlspecialchars($valor);
    }

    return $campos;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Libros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
  <div class="container">
    <!-- Logo / título -->
    <a class="navbar-brand fw-bold d-flex align-items-center" href="catalogo.php">
      📚 <span class="ms-2">Biblioteca</span>
    </a>

    <!-- Botón responsive -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Links -->
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav align-items-center gap-2">
        <li class="nav-item">
          <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'catalogo.php' ? 'active fw-semibold' : 'text-white-50'; ?>" href="catalogo.php">Catálogo</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'mis_prestamos.php' ? 'active fw-semibold' : 'text-white-50'; ?>" href="mis_prestamos.php">Mis Préstamos</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'perfil.php' ? 'active fw-semibold' : 'text-white-50'; ?>" href="perfil.php">Mi Perfil</a>
        </li>
        <li class="nav-item">
          <a class="btn btn-outline-light btn-sm ms-2 px-3 fw-bold" href="../public/logout.php">Salir</a>
        </li>
      </ul>
    </div>
  </div>
</nav>


    <main class="container my-5">
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-4 align-items-center">
            <div class="col-md-6">
                <h2 class="fw-bold text-secondary">Libros Disponibles</h2>
                <p class="text-muted">Explora y solicita tus lecturas académicas de este ciclo.</p>
            </div>
            <div class="col-md-6">
                <form action="catalogo.php" method="GET" class="d-flex gap-2">
                    <input type="text" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>" class="form-control" placeholder="Buscar por título o código...">
                    <button type="submit" class="btn btn-primary px-4">Buscar</button>
                </form>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
            <?php foreach ($libros as $l): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-0">
                        <?php
                        $imagenLibro = obtenerCampoImagen($l);
                        $imagenUrl = $imagenLibro ? construirUrlImagen($imagenLibro) : '';

                        if ($imagenUrl === '') {
                            $codigoImagen = !empty($l['codigo']) ? strtolower($l['codigo']) . '.jpg' : 'default.jpg';
                            $rutaArchivo = __DIR__ . '/../assets/images/' . $codigoImagen;
                            if (file_exists($rutaArchivo)) {
                                $imagenUrl = '../assets/images/' . $codigoImagen;
                            } else {
                                $imagenUrl = 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?q=80&w=400&auto=format&fit=crop';
                            }
                        }

                        ?>
                        <img src="<?php echo $imagenUrl; ?>" class="card-img-top object-fit-cover" style="height: 240px; background-color: #eaeaea;" alt="Portada">
                        <div class="card-body d-flex flex-column justify-content-between">
                            <div>
                                <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($l['codigo']); ?></span>
                                <h5 class="card-title fw-bold text-dark text-truncate" title="<?php echo htmlspecialchars($l['titulo']); ?>">
                                    <?php echo htmlspecialchars($l['titulo']); ?>
                                </h5>
                                <p class="card-text text-muted small mb-1"><strong>Autor:</strong> <?php echo htmlspecialchars($l['nombre_autor']); ?></p>
                                <p class="card-text text-muted small mb-1"><strong>Categoría:</strong> <?php echo htmlspecialchars($l['nombre_categoria']); ?></p>
                            </div>
                            
                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="small text-muted">Disponibles:</span>
                                    <span class="fw-bold <?php echo ($l['existencias_totales'] > 0) ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $l['existencias_totales']; ?> uds
                                    </span>
                                </div>
                                <div class="d-flex gap-2 mb-3">
                                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#infoModal-<?php echo $l['id_libro']; ?>">
                                        Ver Información
                                    </button>
                                </div>

                                <?php if ($l['existencias_totales'] > 0): ?>
                                    <form action="catalogo.php" method="POST">
                                        <input type="hidden" name="id_libro" value="<?php echo $l['id_libro']; ?>">
                                        <input type="hidden" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>">
                                        <button type="submit" name="solicitar_libro" value="1" class="btn btn-outline-primary w-100 fw-semibold">Solicitar Préstamo</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-secondary w-100" disabled>No Disponible</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="infoModal-<?php echo $l['id_libro']; ?>" tabindex="-1" aria-labelledby="infoModalLabel-<?php echo $l['id_libro']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="infoModalLabel-<?php echo $l['id_libro']; ?>"><?php echo htmlspecialchars($l['titulo']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body">
                                    <p><strong>Código:</strong> <?php echo htmlspecialchars($l['codigo']); ?></p>
                                    <p><strong>Autor:</strong> <?php echo htmlspecialchars($l['nombre_autor']); ?></p>
                                    <p><strong>Categoría:</strong> <?php echo htmlspecialchars($l['nombre_categoria']); ?></p>
                                    <p><strong>Disponibles:</strong> <?php echo $l['existencias_totales']; ?> unidades</p>
                                    <?php
                                        $camposAdicionales = obtenerCamposAdicionales($l);
                                        foreach ($camposAdicionales as $etiqueta => $valor): ?>
                                            <p><strong><?php echo $etiqueta; ?>:</strong> <?php echo $valor; ?></p>
                                    <?php endforeach; ?>
                                </div>
                                <div class="modal-footer">
                                    <?php if ($l['existencias_totales'] > 0): ?>
                                        <form action="catalogo.php" method="POST" class="me-auto">
                                            <input type="hidden" name="id_libro" value="<?php echo $l['id_libro']; ?>">
                                            <input type="hidden" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>">
                                            <button type="submit" name="solicitar_libro" value="1" class="btn btn-primary">Solicitar Préstamo</button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>