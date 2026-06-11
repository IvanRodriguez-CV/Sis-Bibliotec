<?php
require_once '../config/conexion.php';
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../public/login.php');
    exit;
}

if (!isset($_SESSION['catalogo_request_token'])) {
    $_SESSION['catalogo_request_token'] = bin2hex(random_bytes(16));
}
$requestToken = $_SESSION['catalogo_request_token'];

$id_usuario = $_SESSION['id_usuario'];
$mensaje = '';

// ============================================
// PROCESAR SOLICITUD DE PRÉSTAMO
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_libro'])) {
    $requestTokenSubmitted = $_POST['request_token'] ?? '';
    if (!hash_equals($_SESSION['catalogo_request_token'] ?? '', $requestTokenSubmitted)) {
        $_SESSION['mensaje_catalogo'] = 'Solicitud duplicada o inválida. Intente de nuevo.';
        header('Location: catalogo.php');
        exit;
    }
    unset($_SESSION['catalogo_request_token']);

    $id_libro_solicitado = filter_input(INPUT_POST, 'id_libro', FILTER_VALIDATE_INT);
    if ($id_libro_solicitado === false || $id_libro_solicitado === null) {
        $id_libro_solicitado = isset($_POST['id_libro']) ? intval($_POST['id_libro']) : 0;
    }

    if ($id_libro_solicitado > 0) {
        if (isset($_SESSION['ultimo_prestamo_id_libro'], $_SESSION['ultimo_prestamo_timestamp']) &&
            $_SESSION['ultimo_prestamo_id_libro'] === $id_libro_solicitado &&
            (time() - $_SESSION['ultimo_prestamo_timestamp']) < 5) {
            $mensaje = 'Solicitud duplicada detectada. Intente de nuevo.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmtLibro = $pdo->prepare("SELECT existencias_totales FROM Libro WHERE id_libro = ? FOR UPDATE");
                $stmtLibro->execute([$id_libro_solicitado]);
                $libroDatos = $stmtLibro->fetch(PDO::FETCH_ASSOC);

                if (!$libroDatos || $libroDatos['existencias_totales'] <= 0) {
                    $pdo->rollBack();
                    $mensaje = 'No hay existencias disponibles para este libro.';
                } else {
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Prestamo WHERE id_libro = ? AND id_usuario = ? AND estado_prestamo IN ('Activo','Vencido')");
                    $stmtCheck->execute([$id_libro_solicitado, $id_usuario]);
                    $solicitudesActivas = (int) $stmtCheck->fetchColumn();

                    if ($solicitudesActivas > 0) {
                        $mensaje = 'Ya tienes un préstamo activo o vencido para este libro.';
                        $pdo->rollBack();
                    } else {
                        $stmtPrestamo = $pdo->prepare("INSERT INTO Prestamo (id_libro, id_usuario, fecha_prestamo, fecha_entrega, estado_prestamo) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'Activo')");
                        $stmtPrestamo->execute([$id_libro_solicitado, $id_usuario]);

                        if ($stmtPrestamo->rowCount() > 0) {
                            $pdo->commit();
                            $mensaje = 'Préstamo solicitado correctamente.';
                            $_SESSION['ultimo_prestamo_id_libro'] = $id_libro_solicitado;
                            $_SESSION['ultimo_prestamo_timestamp'] = time();
                        } else {
                            $pdo->rollBack();
                            $mensaje = 'No se pudo registrar el préstamo. Intente de nuevo.';
                        }
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Error al solicitar préstamo: ' . $e->getMessage());
                $mensaje = 'No se pudo procesar la solicitud. Intente de nuevo.';
            }
        }
    } else {
        $mensaje = 'Libro no válido.';
    }

    $_SESSION['mensaje_catalogo'] = $mensaje;
    header('Location: catalogo.php?' . http_build_query($_GET));
    exit;
}

// ============================================
// CAPTURAR FILTROS Y PAGINACIÓN
// ============================================
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$filtro_categoria = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$filtro_genero = isset($_GET['genero']) ? intval($_GET['genero']) : 0;
$filtro_autor = isset($_GET['autor']) ? intval($_GET['autor']) : 0;
$filtro_anio_desde = isset($_GET['anio_desde']) ? intval($_GET['anio_desde']) : 0;
$filtro_anio_hasta = isset($_GET['anio_hasta']) ? intval($_GET['anio_hasta']) : 0;
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'recientes';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$libros_por_pagina = 12;

$mensaje = $_SESSION['mensaje_catalogo'] ?? '';
unset($_SESSION['mensaje_catalogo']);

// ============================================
// OBTENER DATOS PARA FILTROS
// ============================================
$categorias = $pdo->query("SELECT * FROM Categoria ORDER BY nombre ASC")->fetchAll();
$generos = $pdo->query("SELECT * FROM Genero ORDER BY nombre ASC")->fetchAll();
$autores = $pdo->query("SELECT id_autor, CONCAT(nombre, ' ', apellido) AS nombre_completo FROM Autores ORDER BY nombre ASC")->fetchAll();

// Obtener géneros filtrados si hay categoría seleccionada
$generosMostrar = $generos;
if ($filtro_categoria > 0) {
    $stmtGen = $pdo->prepare("
        SELECT DISTINCT g.id_genero, g.nombre 
        FROM Genero g
        INNER JOIN Categoria_Genero cg ON g.id_genero = cg.id_genero
        WHERE cg.id_categoria = ?
        ORDER BY g.nombre ASC
    ");
    $stmtGen->execute([$filtro_categoria]);
    $generosMostrar = $stmtGen->fetchAll();
}

// ============================================
// CONSTRUIR CONSULTA CON FILTROS
// ============================================
$whereConditions = [];
$params = [];

// Búsqueda por texto
if (!empty($busqueda)) {
    $whereConditions[] = "(l.titulo LIKE ? OR l.codigo LIKE ? OR CONCAT(a.nombre, ' ', a.apellido) LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

// Filtro por categoría
if ($filtro_categoria > 0) {
    $whereConditions[] = "l.id_categoria = ?";
    $params[] = $filtro_categoria;
}

// Filtro por género
if ($filtro_genero > 0) {
    $whereConditions[] = "EXISTS (SELECT 1 FROM Libro_Genero lg WHERE lg.id_libro = l.id_libro AND lg.id_genero = ?)";
    $params[] = $filtro_genero;
}

// Filtro por autor
if ($filtro_autor > 0) {
    $whereConditions[] = "EXISTS (SELECT 1 FROM Libro_Autor la WHERE la.id_libro = l.id_libro AND la.id_autor = ?)";
    $params[] = $filtro_autor;
}

// Filtro por año desde
if ($filtro_anio_desde > 0) {
    $whereConditions[] = "l.anio_publicacion >= ?";
    $params[] = $filtro_anio_desde;
}

// Filtro por año hasta
if ($filtro_anio_hasta > 0) {
    $whereConditions[] = "l.anio_publicacion <= ?";
    $params[] = $filtro_anio_hasta;
}

// Construir WHERE
$whereSql = '';
if (!empty($whereConditions)) {
    $whereSql = ' WHERE ' . implode(' AND ', $whereConditions);
}

// ============================================
// ORDENAMIENTO
// ============================================
$orderBy = match($orden) {
    'titulo_asc' => 'l.titulo ASC',
    'titulo_desc' => 'l.titulo DESC',
    'anio_asc' => 'l.anio_publicacion ASC',
    'anio_desc' => 'l.anio_publicacion DESC',
    default => 'l.id_libro DESC'
};

// ============================================
// CONTAR TOTAL DE RESULTADOS
// ============================================
$sqlCount = "SELECT COUNT(DISTINCT l.id_libro) 
             FROM Libro l
             LEFT JOIN Libro_Autor la ON la.id_libro = l.id_libro
             LEFT JOIN Autores a ON la.id_autor = a.id_autor
             $whereSql";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$total_libros = $stmtCount->fetchColumn();

// Calcular paginación
$total_paginas = ceil($total_libros / $libros_por_pagina);
$offset = ($pagina - 1) * $libros_por_pagina;

// ============================================
// OBTENER LIBROS CON PAGINACIÓN
// ============================================
$sql = "SELECT 
            l.id_libro,
            l.codigo,
            l.titulo,
            l.editorial,
            l.descripcion,
            l.anio_publicacion,
            l.existencias_totales,
            l.imagen,
            l.id_categoria,
            c.nombre AS nombre_categoria,
            GROUP_CONCAT(DISTINCT CONCAT(a.nombre, ' ', a.apellido) SEPARATOR ', ') AS nombre_autor
        FROM Libro l
        INNER JOIN Categoria c ON l.id_categoria = c.id_categoria
        LEFT JOIN Libro_Autor la ON la.id_libro = l.id_libro
        LEFT JOIN Autores a ON la.id_autor = a.id_autor
        $whereSql
        GROUP BY l.id_libro
        ORDER BY $orderBy
        LIMIT $libros_por_pagina OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$libros = $stmt->fetchAll();

// ============================================
// FUNCIONES AUXILIARES
// ============================================
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

function construirQueryString($params) {
    return http_build_query(array_filter($params, function($v) { return $v !== '' && $v !== 0; }));
}

$queryParams = [
    'buscar' => $busqueda,
    'categoria' => $filtro_categoria,
    'genero' => $filtro_genero,
    'autor' => $filtro_autor,
    'anio_desde' => $filtro_anio_desde,
    'anio_hasta' => $filtro_anio_hasta,
    'orden' => $orden
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca Digital - Catálogo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/catalogo.css">
</head>
<body>

    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h3>Biblioteca</h3>
            <div class="subtitle">Digital</div>
        </div>
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'catalogo.php' ? 'active' : ''; ?> nav-link-toggle" 
                       href="#" 
                       onclick="toggleSubmenu(event, 'submenuCatalogo')">
                        <span></span> Catálogo
                        <i class="fas fa-chevron-down submenu-icon" id="iconoCatalogo"></i>
                    </a>
                    <div class="submenu" id="submenuCatalogo" style="<?php echo basename($_SERVER['PHP_SELF']) === 'catalogo.php' ? 'display: block;' : 'display: none;'; ?>">
                        <form action="catalogo.php" method="GET" class="filtros-sidebar-form">
                            <div class="filtro-sidebar-grupo">
                                <label class="filtro-sidebar-label">Categoría</label>
                                <select name="categoria" id="filtroCategoria" class="filtro-sidebar-select" onchange="actualizarGeneros()">
                                    <option value="0">Todas</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $filtro_categoria == $cat['id_categoria'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filtro-sidebar-grupo">
                                <label class="filtro-sidebar-label">Género</label>
                                <select name="genero" id="filtroGenero" class="filtro-sidebar-select" onchange="this.form.submit()">
                                    <option value="0">Todos</option>
                                    <?php foreach ($generosMostrar as $gen): ?>
                                        <option value="<?php echo $gen['id_genero']; ?>" <?php echo $filtro_genero == $gen['id_genero'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($gen['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
           
                            <div class="filtro-sidebar-grupo">
                                <label class="filtro-sidebar-label">Ordenar</label>
                                <select name="orden" class="filtro-sidebar-select" onchange="this.form.submit()">
                                    <option value="recientes" <?php echo $orden === 'recientes' ? 'selected' : ''; ?>>Recientes</option>
                                    <option value="titulo_asc" <?php echo $orden === 'titulo_asc' ? 'selected' : ''; ?>>Título A-Z</option>
                                    <option value="titulo_desc" <?php echo $orden === 'titulo_desc' ? 'selected' : ''; ?>>Título Z-A</option>
                                    <option value="anio_asc" <?php echo $orden === 'anio_asc' ? 'selected' : ''; ?>>Año ↑</option>
                                    <option value="anio_desc" <?php echo $orden === 'anio_desc' ? 'selected' : ''; ?>>Año ↓</option>
                                </select>
                            </div>

                            <?php if (!empty($busqueda)): ?>
                                <input type="hidden" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>">
                            <?php endif; ?>

                            <div class="filtros-sidebar-acciones">
                                <button type="submit" class="btn-aplicar-sidebar">
                                    <i class="fas fa-check"></i> Aplicar
                                </button>
                                <a href="catalogo.php" class="btn-limpiar-sidebar">
                                    <i class="fas fa-times"></i> Limpiar
                                </a>
                            </div>
                        </form>
                    </div>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'mis_prestamos.php' ? 'active' : ''; ?>" href="./mis_prestamos.php">
                        <span></span> Mis Préstamos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'perfil.php' ? 'active' : ''; ?>" href="./perfil.php">
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
        <div class="catalog-header">
            <div class="ornament">✦ ✦ </div>
            <h1>La Biblioteca Digital</h1>
            <p>Explora nuestra colección de obras clásicas y académicas</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert-custom">
                <span><?php echo htmlspecialchars($mensaje); ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="search-container">
            <form action="catalogo.php" method="GET" id="formBusqueda">
                <input type="text" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar por título, autor o código...">
                <button type="submit">BUSCAR</button>
            </form>
        </div>

        <div class="resultados-info">
            <span>
                <?php if ($total_libros > 0): ?>
                    Mostrando <?php echo $offset + 1; ?> - <?php echo min($offset + $libros_por_pagina, $total_libros); ?> de <?php echo $total_libros; ?> libros
                <?php else: ?>
                    No se encontraron libros
                <?php endif; ?>
            </span>
            <?php if ($total_paginas > 1): ?>
                <span class="pagina-info">Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?></span>
            <?php endif; ?>
        </div>

        <div class="libros-mosaico">
            <?php foreach ($libros as $l): ?>
                <?php
                $imagenLibro = !empty($l['imagen']) ? $l['imagen'] : obtenerCampoImagen($l);
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
                <div class="libro-item">
                    <div class="libro-portada">
                        <span class="libro-codigo"><?php echo htmlspecialchars($l['codigo']); ?></span>
                        <img src="<?php echo $imagenUrl; ?>" alt="<?php echo htmlspecialchars($l['titulo']); ?>">
                        <div class="libro-overlay">
                            <a href="book.php?id=<?php echo $l['id_libro']; ?>" class="btn-info">
                                ℹ Ver Información
                            </a>
                            <?php if ($l['existencias_totales'] > 0): ?>
                                <form action="catalogo.php" method="POST">
                                    <input type="hidden" name="id_libro" value="<?php echo $l['id_libro']; ?>">
                                    <input type="hidden" name="request_token" value="<?php echo htmlspecialchars($requestToken); ?>">
                                    <?php foreach ($queryParams as $key => $value): ?>
                                        <?php if ($value): ?>
                                            <input type="hidden" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>">
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <button type="submit" name="solicitar_libro" value="1" class="btn-prestamo">
                                        📖 Solicitar Préstamo
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn-no-disponible" disabled>No Disponible</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="libro-info">
                        <div class="titulo" title="<?php echo htmlspecialchars($l['titulo']); ?>">
                            <?php echo htmlspecialchars($l['titulo']); ?>
                        </div>
                        <div class="autor"><?php echo htmlspecialchars($l['nombre_autor'] ?? ''); ?></div>
                        <div class="existencias <?php echo ($l['existencias_totales'] > 0) ? 'disponible' : 'agotado'; ?>">
                            <?php echo ($l['existencias_totales'] > 0) ? $l['existencias_totales'] . ' disponibles' : 'Agotado'; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($libros)): ?>
            <div style="text-align: center; padding: 4rem 0;">
                <div style="font-size: 4rem; margin-bottom: 1rem;"></div>
                <h3 style="font-family: 'Playfair Display', serif; color: #c9a84c;">No se encontraron libros</h3>
                <p style="color: #8b9da8; font-style: italic;">Intenta con otros términos de búsqueda o ajusta los filtros</p>
                <a href="catalogo.php" class="btn-limpiar-filtros" style="display: inline-block; margin-top: 1rem;">
                    <i class="fas fa-times"></i> Limpiar Filtros
                </a>
            </div>
        <?php endif; ?>

        <?php if ($total_paginas > 1): ?>
            <div class="paginacion">
                <?php if ($pagina > 1): $queryParams['pagina'] = $pagina - 1; ?>
                    <a href="catalogo.php?<?php echo construirQueryString($queryParams); ?>" class="pagina-btn">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                <?php else: ?>
                    <span class="pagina-btn disabled"><i class="fas fa-chevron-left"></i> Anterior</span>
                <?php endif; ?>

                <?php
                $rango = 2;
                $inicio = max(1, $pagina - $rango);
                $fin = min($total_paginas, $pagina + $rango);

                if ($inicio > 1): $queryParams['pagina'] = 1; ?>
                    <a href="catalogo.php?<?php echo construirQueryString($queryParams); ?>" class="pagina-num">1</a>
                    <?php if ($inicio > 2): ?><span class="pagina-ellipsis">...</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $inicio; $i <= $fin; $i++): $queryParams['pagina'] = $i; ?>
                    <?php if ($i == $pagina): ?>
                        <span class="pagina-num active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="catalogo.php?<?php echo construirQueryString($queryParams); ?>" class="pagina-num"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($fin < $total_paginas): ?>
                    <?php if ($fin < $total_paginas - 1): ?><span class="pagina-ellipsis">...</span><?php endif; ?>
                    <?php $queryParams['pagina'] = $total_paginas; ?>
                    <a href="catalogo.php?<?php echo construirQueryString($queryParams); ?>" class="pagina-num"><?php echo $total_paginas; ?></a>
                <?php endif; ?>

                <?php if ($pagina < $total_paginas): $queryParams['pagina'] = $pagina + 1; ?>
                    <a href="catalogo.php?<?php echo construirQueryString($queryParams); ?>" class="pagina-btn">
                        Siguiente <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="pagina-btn disabled">Siguiente <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSubmenu(event, submenuId) {
            event.preventDefault();
            const submenu = document.getElementById(submenuId);
            const icono = document.getElementById('iconoCatalogo');
            
            if (submenu.style.display === 'none' || submenu.style.display === '') {
                submenu.style.display = 'block';
                icono.classList.add('rotated');
            } else {
                submenu.style.display = 'none';
                icono.classList.remove('rotated');
            }
        }

        function actualizarGeneros() {
            const categoriaSelect = document.getElementById('filtroCategoria');
            const generoSelect = document.getElementById('filtroGenero');
            const categoriaId = categoriaSelect.value;
            
            // Recargar la página con la nueva categoría
            // El backend se encargará de filtrar los géneros
            categoriaSelect.form.submit();
        }

        document.querySelectorAll('.filtro-sidebar-select').forEach(select => {
            if (select.id !== 'filtroCategoria') {
                select.addEventListener('change', function() {
                    this.form.submit();
                });
            }
        });
    </script>
</body>
</html>