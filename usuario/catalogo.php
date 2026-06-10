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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_libro'])) {
    $requestTokenSubmitted = $_POST['request_token'] ?? '';
    if (!hash_equals($_SESSION['catalogo_request_token'] ?? '', $requestTokenSubmitted)) {
        $_SESSION['mensaje_catalogo'] = 'Solicitud duplicada o inválida. Intente de nuevo.';
        $redirectUrl = 'catalogo.php';
        if (!empty($_POST['buscar'])) {
            $redirectUrl .= '?buscar=' . urlencode(trim($_POST['buscar']));
        }
        header('Location: ' . $redirectUrl);
        exit;
    }
    unset($_SESSION['catalogo_request_token']);

    $id_libro_solicitado = filter_input(INPUT_POST, 'id_libro', FILTER_VALIDATE_INT);
    if ($id_libro_solicitado === false || $id_libro_solicitado === null) {
        $id_libro_solicitado = isset($_POST['id_libro']) ? intval($_POST['id_libro']) : 0;
    }
    $busqueda = isset($_POST['buscar']) ? trim($_POST['buscar']) : '';

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

$libroColumnas = $pdo->query("SHOW COLUMNS FROM Libro")->fetchAll(PDO::FETCH_COLUMN);
$idCategoriaCol = null;
$idAutorCol = null;
$descripcionCol = null;
$imagenCol = null;
foreach ($libroColumnas as $columna) {
    $columnaLower = strtolower($columna);
    if ($idCategoriaCol === null && str_contains($columnaLower, 'categoria')) {
        $idCategoriaCol = $columna;
    }
    if ($idAutorCol === null && str_contains($columnaLower, 'autor')) {
        $idAutorCol = $columna;
    }
    if ($descripcionCol === null && preg_match('/descripcion|resumen|sinopsis|detalle/i', $columnaLower)) {
        $descripcionCol = $columna;
    }
    if ($imagenCol === null && preg_match('/imagen|foto|portada|ruta_imagen|ruta_portada/i', $columnaLower)) {
        $imagenCol = $columna;
    }
}
if ($idCategoriaCol === null) {
    $idCategoriaCol = 'id_categoria';
}

$useJoinTableAutores = false;
$joinAutorSql = '';
if ($idAutorCol !== null) {
    $joinAutorSql = "LEFT JOIN Autores a ON l.{$idAutorCol} = a.id_autor";
} else {
    try {
        $pdo->query("SELECT 1 FROM Libro_Autor LIMIT 1");
        $useJoinTableAutores = true;
    } catch (Exception $e) {
        $useJoinTableAutores = false;
    }

    if ($useJoinTableAutores) {
        $joinAutorSql = "LEFT JOIN Libro_Autor la ON la.id_libro = l.id_libro
                         LEFT JOIN Autores a ON la.id_autor = a.id_autor";
    }
}

$selectedColumns = [
    "l.id_libro",
    "l.{$idCategoriaCol} AS id_categoria",
    "l.titulo",
    "l.codigo",
    "l.existencias_totales",
    "c.nombre AS nombre_categoria",
];

$groupByColumns = [
    "l.id_libro",
    "l.{$idCategoriaCol}",
    "l.titulo",
    "l.codigo",
    "l.existencias_totales",
    "c.nombre",
];

if ($joinAutorSql !== '') {
    $selectedColumns[] = "GROUP_CONCAT(DISTINCT CONCAT(a.nombre, ' ', a.apellido) SEPARATOR ', ') AS nombre_autor";
}

if ($descripcionCol !== null) {
    $selectedColumns[] = "l.{$descripcionCol} AS descripcion";
    $groupByColumns[] = "l.{$descripcionCol}";
}

if ($imagenCol !== null) {
    $selectedColumns[] = "l.{$imagenCol} AS imagen";
    $groupByColumns[] = "l.{$imagenCol}";
} else {
    $selectedColumns[] = "'' AS imagen";
}

$sql = "SELECT " . implode(', ', $selectedColumns) . " FROM Libro l
        INNER JOIN Categoria c ON l.{$idCategoriaCol} = c.id_categoria";
if ($joinAutorSql !== '') {
    $sql .= "\n        " . $joinAutorSql;
}

$groupBySql = " GROUP BY " . implode(', ', $groupByColumns);

if (!empty($busqueda)) {
    $whereConditions = [
        "l.titulo LIKE ?",
        "l.codigo LIKE ?",
    ];
    $params = ["%$busqueda%", "%$busqueda%"];
    if ($joinAutorSql !== '') {
        $whereConditions[] = "CONCAT(a.nombre, ' ', a.apellido) LIKE ?";
        $params[] = "%$busqueda%";
    }
    $sql .= " WHERE " . implode(' OR ', $whereConditions);
    $stmt = $pdo->prepare($sql . $groupBySql);
    $stmt->execute($params);
} else {
    $stmt = $pdo->query($sql . $groupBySql);
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
    <title>Biblioteca Digital - Catálogo</title>
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

        .sidebar-nav .nav-link i {
            width: 20px;
            text-align: center;
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
        }

        .sidebar-footer .btn-salir:hover {
            background: #c9a84c;
            color: #0B1C2B;
        }

        /* Contenido principal */
        .main-content {
            padding: 2rem 3rem;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Header del catálogo */
        .catalog-header {
            text-align: center;
            padding: 2rem 0 3rem;
            border-bottom: 1px solid rgba(201, 168, 76, 0.2);
            margin-bottom: 3rem;
        }

        .catalog-header h1 {
            font-family: 'Playfair Display', serif;
            color: #c9a84c;
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
            margin-bottom: 0.5rem;
        }

        .catalog-header .ornament {
            color: #8b7d5e;
            font-size: 1.5rem;
            letter-spacing: 8px;
        }

        .catalog-header p {
            color: #8b9da8;
            font-size: 1rem;
            margin-top: 1rem;
            font-style: italic;
        }

        /* Barra de búsqueda */
        .search-container {
            max-width: 600px;
            margin: 0 auto 3rem;
            position: relative;
        }

        .search-container form {
            display: flex;
            gap: 0;
            border: 2px solid rgba(201, 168, 76, 0.4);
            border-radius: 50px;
            overflow: hidden;
            background: rgba(10, 22, 32, 0.8);
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }

        .search-container form:focus-within {
            border-color: #c9a84c;
            box-shadow: 0 4px 30px rgba(201, 168, 76, 0.2);
        }

        .search-container input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 1rem 1.5rem;
            color: #d4c5a0;
            font-family: 'Lora', serif;
            font-size: 1rem;
            outline: none;
        }

        .search-container input::placeholder {
            color: #6b7d8a;
            font-style: italic;
        }

        .search-container button {
            background: linear-gradient(135deg, #c9a84c, #a8893c);
            border: none;
            padding: 1rem 2rem;
            color: #0B1C2B;
            font-family: 'Lora', serif;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 1px;
        }

        .search-container button:hover {
            background: linear-gradient(135deg, #d4b85c, #b8994c);
        }

        /* Mensajes de alerta */
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
        }

        .alert-custom .btn-close {
            filter: invert(0.7) sepia(1) saturate(3) hue-rotate(10deg);
        }

        /* Grid de libros - Mosaico estilo El Libro Total */
        .libros-mosaico {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 2.5rem;
            padding: 1rem 0;
        }

        /* Cada libro */
        .libro-item {
            position: relative;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
        }

        .libro-item:hover {
            transform: translateY(-10px) scale(1.02);
        }

        .libro-portada {
            position: relative;
            width: 100%;
            aspect-ratio: 2/3;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 
                0 10px 30px rgba(0,0,0,0.5),
                0 0 0 1px rgba(201, 168, 76, 0.2);
            transition: all 0.4s ease;
        }

        .libro-item:hover .libro-portada {
            box-shadow: 
                0 20px 50px rgba(0,0,0,0.7),
                0 0 0 2px rgba(201, 168, 76, 0.5),
                0 0 30px rgba(201, 168, 76, 0.1);
        }

        .libro-portada img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.4s ease;
        }

        .libro-item:hover .libro-portada img {
            transform: scale(1.05);
        }

        /* Overlay sobre la portada */
        .libro-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(11, 28, 43, 0.95));
            padding: 3rem 1rem 1rem;
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .libro-item:hover .libro-overlay {
            opacity: 1;
        }

        .libro-overlay .btn-info {
            background: rgba(201, 168, 76, 0.9);
            color: #0B1C2B;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-family: 'Lora', serif;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }

        .libro-overlay .btn-info:hover {
            background: #c9a84c;
        }

        .libro-overlay .btn-prestamo {
            background: rgba(40, 167, 69, 0.9);
            color: #fff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-family: 'Lora', serif;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .libro-overlay .btn-prestamo:hover {
            background: rgba(40, 167, 69, 1);
        }

        .libro-overlay .btn-no-disponible {
            background: rgba(108, 117, 125, 0.8);
            color: #ccc;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-family: 'Lora', serif;
            font-size: 0.8rem;
            cursor: not-allowed;
        }

        /* Info debajo de la portada */
        .libro-info {
            padding: 1rem 0.5rem 0;
        }

        .libro-info .titulo {
            font-family: 'Playfair Display', serif;
            color: #c9a84c;
            font-size: 0.95rem;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 0.3rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 2.5em;
        }

        .libro-info .autor {
            color: #8b9da8;
            font-size: 0.8rem;
            font-style: italic;
            margin-bottom: 0.3rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .libro-info .existencias {
            font-size: 0.75rem;
            color: #6b7d8a;
        }

        .libro-info .existencias.disponible {
            color: #5cb85c;
        }

        .libro-info .existencias.agotado {
            color: #d9534f;
        }

        /* Badge de código */
        .libro-codigo {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(11, 28, 43, 0.9);
            color: #c9a84c;
            padding: 0.25rem 0.6rem;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: 600;
            border: 1px solid rgba(201, 168, 76, 0.3);
            z-index: 2;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .libro-item:hover .libro-codigo {
            opacity: 1;
        }

        /* Modal personalizado */
        .modal-custom .modal-content {
            background: linear-gradient(135deg, #0f2234 0%, #1a3450 100%);
            border: 2px solid rgba(201, 168, 76, 0.4);
            border-radius: 12px;
            color: #d4c5a0;
            box-shadow: 0 20px 60px rgba(0,0,0,0.7);
        }

        .modal-custom .modal-header {
            border-bottom: 1px solid rgba(201, 168, 76, 0.3);
            padding: 1.5rem 2rem;
        }

        .modal-custom .modal-title {
            font-family: 'Playfair Display', serif;
            color: #c9a84c;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .modal-custom .modal-body {
            padding: 2rem;
        }

        .modal-custom .modal-body p {
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }

        .modal-custom .modal-body strong {
            color: #c9a84c;
        }

        .modal-custom .modal-footer {
            border-top: 1px solid rgba(201, 168, 76, 0.3);
            padding: 1.5rem 2rem;
        }

        .modal-custom .btn-close {
            filter: invert(0.7) sepia(1) saturate(3) hue-rotate(10deg);
        }

        .modal-custom .btn-primary-custom {
            background: linear-gradient(135deg, #c9a84c, #a8893c);
            border: none;
            color: #0B1C2B;
            padding: 0.6rem 1.5rem;
            border-radius: 6px;
            font-family: 'Lora', serif;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .modal-custom .btn-primary-custom:hover {
            background: linear-gradient(135deg, #d4b85c, #b8994c);
            transform: translateY(-2px);
        }

        .modal-custom .btn-secondary-custom {
            background: transparent;
            border: 1px solid #6b7d8a;
            color: #8b9da8;
            padding: 0.6rem 1.5rem;
            border-radius: 6px;
            font-family: 'Lora', serif;
            transition: all 0.3s ease;
        }

        .modal-custom .btn-secondary-custom:hover {
            border-color: #c9a84c;
            color: #c9a84c;
        }

        /* Scrollbar personalizado */
        ::-webkit-scrollbar {
            width: 10px;
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

            .catalog-header h1 {
                font-size: 1.8rem;
            }

            .libros-mosaico {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 1.5rem;
            }
        }

        @media (max-width: 575.98px) {
            .libros-mosaico {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .catalog-header h1 {
                font-size: 1.4rem;
            }
        }

        /* Animación de entrada */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .libro-item {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }

        .libro-item:nth-child(1) { animation-delay: 0.05s; }
        .libro-item:nth-child(2) { animation-delay: 0.1s; }
        .libro-item:nth-child(3) { animation-delay: 0.15s; }
        .libro-item:nth-child(4) { animation-delay: 0.2s; }
        .libro-item:nth-child(5) { animation-delay: 0.25s; }
        .libro-item:nth-child(6) { animation-delay: 0.3s; }
        .libro-item:nth-child(7) { animation-delay: 0.35s; }
        .libro-item:nth-child(8) { animation-delay: 0.4s; }
        .libro-item:nth-child(n+9) { animation-delay: 0.45s; }
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
        <div class="catalog-header">
            <div class="ornament">✦ ✦ ✦</div>
            <h1>La Biblioteca Digital</h1>
            <p>Explora nuestra colección de obras clásicas y académicas</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert-custom">
                <span><?php echo htmlspecialchars($mensaje); ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Búsqueda -->
        <div class="search-container">
            <form action="catalogo.php" method="GET">
                <input type="text" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar por título, autor o código...">
                <button type="submit">BUSCAR</button>
            </form>
        </div>

        <!-- Mosaico de libros -->
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
                            <button type="button" class="btn-info" data-bs-toggle="modal" data-bs-target="#infoModal-<?php echo $l['id_libro']; ?>">
                                ℹ Ver Información
                            </button>
                            <?php if ($l['existencias_totales'] > 0): ?>
                                <form action="catalogo.php" method="POST">
                                    <input type="hidden" name="id_libro" value="<?php echo $l['id_libro']; ?>">
                                    <input type="hidden" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>">
                                    <input type="hidden" name="request_token" value="<?php echo htmlspecialchars($requestToken); ?>">
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
                        <div class="autor"><?php echo htmlspecialchars($l['nombre_autor']); ?></div>
                        <div class="existencias <?php echo ($l['existencias_totales'] > 0) ? 'disponible' : 'agotado'; ?>">
                            <?php echo ($l['existencias_totales'] > 0) ? $l['existencias_totales'] . ' disponibles' : 'Agotado'; ?>
                        </div>
                    </div>
                </div>

                <!-- Modal de información -->
                <div class="modal fade modal-custom" id="infoModal-<?php echo $l['id_libro']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><?php echo htmlspecialchars($l['titulo']); ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <p><strong>Código:</strong> <?php echo htmlspecialchars($l['codigo']); ?></p>
                                <p><strong>Autor:</strong> <?php echo htmlspecialchars($l['nombre_autor']); ?></p>
                                <p><strong>Categoría:</strong> <?php echo htmlspecialchars($l['nombre_categoria']); ?></p>
                                <p><strong>Disponibles:</strong> 
                                    <span class="<?php echo ($l['existencias_totales'] > 0) ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $l['existencias_totales']; ?> unidades
                                    </span>
                                </p>
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
                                        <input type="hidden" name="request_token" value="<?php echo htmlspecialchars($requestToken); ?>">
                                        <button type="submit" name="solicitar_libro" value="1" class="btn btn-primary-custom">Solicitar Préstamo</button>
                                    </form>
                                <?php endif; ?>
                                <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($libros)): ?>
            <div style="text-align: center; padding: 4rem 0;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">📚</div>
                <h3 style="font-family: 'Playfair Display', serif; color: #c9a84c;">No se encontraron libros</h3>
                <p style="color: #8b9da8; font-style: italic;">Intenta con otros términos de búsqueda</p>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>