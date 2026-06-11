<?php
require_once '../config/conexion.php';
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../public/login.php');
    exit;
}

$id_libro = isset($_GET['id']) ? intval($_GET['id']) : 0;
$id_usuario = $_SESSION['id_usuario'];

if ($id_libro <= 0) {
    header('Location: catalogo.php');
    exit;
}

// Consultar información completa del libro
try {
    $stmt = $pdo->prepare("
        SELECT 
            l.*,
            c.nombre AS nombre_categoria,
            GROUP_CONCAT(DISTINCT CONCAT(a.nombre, ' ', a.apellido) SEPARATOR ', ') AS autores,
            GROUP_CONCAT(DISTINCT g.nombre SEPARATOR ', ') AS generos
        FROM Libro l
        INNER JOIN Categoria c ON l.id_categoria = c.id_categoria
        LEFT JOIN Libro_Autor la ON la.id_libro = l.id_libro
        LEFT JOIN Autores a ON a.id_autor = la.id_autor
        LEFT JOIN Libro_Genero lg ON lg.id_libro = l.id_libro
        LEFT JOIN Genero g ON g.id_genero = lg.id_genero
        WHERE l.id_libro = ?
        GROUP BY l.id_libro
    ");
    $stmt->execute([$id_libro]);
    $libro = $stmt->fetch();

    if (!$libro) {
        header('Location: catalogo.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Error al obtener libro: ' . $e->getMessage());
    header('Location: catalogo.php');
    exit;
}

// Verificar si el usuario ya tiene una reserva activa de este libro
$stmt = $pdo->prepare("SELECT id_reserva, estado, posicion_cola FROM Reserva WHERE id_libro = ? AND id_usuario = ? AND estado IN ('Pendiente', 'Disponible')");
$stmt->execute([$id_libro, $id_usuario]);
$reserva_usuario = $stmt->fetch();

// Contar reservas pendientes de este libro
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Reserva WHERE id_libro = ? AND estado IN ('Pendiente', 'Disponible')");
$stmt->execute([$id_libro]);
$total_reservas = $stmt->fetchColumn();

$mensaje = '';

// Procesar solicitud de préstamo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_prestamo'])) {
    try {
        $stmt = $pdo->prepare("SELECT existencias_totales FROM Libro WHERE id_libro = ?");
        $stmt->execute([$id_libro]);
        $datos = $stmt->fetch();
        
        if ($datos && $datos['existencias_totales'] > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Prestamo WHERE id_libro = ? AND id_usuario = ? AND estado_prestamo IN ('Activo', 'Vencido')");
            $stmt->execute([$id_libro, $id_usuario]);
            
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("INSERT INTO Prestamo (id_libro, id_usuario, fecha_prestamo, fecha_entrega, estado_prestamo) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'Activo')");
                $stmt->execute([$id_libro, $id_usuario]);
                
                $mensaje = 'Préstamo solicitado correctamente.';
                
                $stmt = $pdo->prepare("SELECT existencias_totales FROM Libro WHERE id_libro = ?");
                $stmt->execute([$id_libro]);
                $libro['existencias_totales'] = $stmt->fetchColumn();
            } else {
                $mensaje = 'Ya tienes un préstamo activo de este libro.';
            }
        } else {
            $mensaje = 'No hay existencias disponibles.';
        }
    } catch (PDOException $e) {
        $mensaje = 'Error al procesar la solicitud.';
        error_log('Error préstamo: ' . $e->getMessage());
    }
}

// Procesar solicitud de reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_reserva'])) {
    try {
        // Verificar si ya tiene reserva activa
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Reserva WHERE id_libro = ? AND id_usuario = ? AND estado IN ('Pendiente', 'Disponible')");
        $stmt->execute([$id_libro, $id_usuario]);
        
        if ($stmt->fetchColumn() > 0) {
            $mensaje = 'Ya tienes una reserva activa para este libro.';
        } else {
            // Verificar si ya tiene préstamo activo
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Prestamo WHERE id_libro = ? AND id_usuario = ? AND estado_prestamo IN ('Activo', 'Vencido')");
            $stmt->execute([$id_libro, $id_usuario]);
            
            if ($stmt->fetchColumn() > 0) {
                $mensaje = 'Ya tienes un préstamo activo de este libro.';
            } else {
                // Obtener siguiente posición en cola
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(posicion_cola), 0) + 1 FROM Reserva WHERE id_libro = ? AND estado IN ('Pendiente', 'Disponible')");
                $stmt->execute([$id_libro]);
                $nueva_posicion = $stmt->fetchColumn();
                
                // Insertar reserva
                $stmt = $pdo->prepare("INSERT INTO Reserva (id_libro, id_usuario, posicion_cola) VALUES (?, ?, ?)");
                $stmt->execute([$id_libro, $id_usuario, $nueva_posicion]);
                
                $mensaje = "Reserva registrada correctamente. Posición en cola: #$nueva_posicion";
                
                // Actualizar contador
                $total_reservas++;
                $reserva_usuario = [
                    'id_reserva' => $pdo->lastInsertId(),
                    'estado' => 'Pendiente',
                    'posicion_cola' => $nueva_posicion
                ];
            }
        }
    } catch (PDOException $e) {
        $mensaje = 'Error al registrar la reserva.';
        error_log('Error reserva: ' . $e->getMessage());
    }
}

// Procesar cancelación de reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_reserva'])) {
    try {
        $stmt = $pdo->prepare("UPDATE Reserva SET estado = 'Cancelada' WHERE id_libro = ? AND id_usuario = ? AND estado IN ('Pendiente', 'Disponible')");
        $stmt->execute([$id_libro, $id_usuario]);
        
        if ($stmt->rowCount() > 0) {
            $mensaje = 'Reserva cancelada correctamente.';
            $reserva_usuario = null;
            $total_reservas--;
        }
    } catch (PDOException $e) {
        $mensaje = 'Error al cancelar la reserva.';
        error_log('Error cancelar reserva: ' . $e->getMessage());
    }
}

// Función para obtener URL de imagen
function obtenerImagenUrl($libro) {
    if (!empty($libro['imagen'])) {
        $imagen = ltrim($libro['imagen'], '/');
        if (file_exists(__DIR__ . '/../' . $imagen)) {
            return '../' . $imagen;
        }
    }
    return 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?q=80&w=600&auto=format&fit=crop';
}

$imagenUrl = obtenerImagenUrl($libro);
$autoresArray = !empty($libro['autores']) ? explode(', ', $libro['autores']) : ['Autor desconocido'];
$generosArray = !empty($libro['generos']) ? explode(', ', $libro['generos']) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($libro['titulo']); ?> - Biblioteca Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/book.css">
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
                    <a class="nav-link" href="./catalogo.php">Catálogo</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="./mis_prestamos.php">Mis Préstamos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="./perfil.php">Mi Perfil</a>
                </li>
            </ul>
        </div>
        <div class="sidebar-footer">
            <a class="btn-salir" href="../public/logout.php">⬅ Salir</a>
        </div>
    </nav>

    <main class="main-content">
        <a href="catalogo.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver al catálogo
        </a>

        <?php if (!empty($mensaje)): ?>
            <div class="alert-custom <?php echo (strpos($mensaje, 'correctamente') !== false || strpos($mensaje, 'registrada') !== false) ? 'alert-success' : ''; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <div class="book-detail-card">
            <div class="book-grid">
                <div class="book-cover-container">
                    <div class="book-cover">
                        <img src="<?php echo $imagenUrl; ?>" alt="<?php echo htmlspecialchars($libro['titulo']); ?>">
                    </div>
                    
                    <div class="book-actions">
                        <?php if ($libro['existencias_totales'] > 0): ?>
                            <form method="POST">
                                <button type="submit" name="solicitar_prestamo" class="btn-action btn-primary-gold">
                                    <i class="fas fa-book-reader"></i> Solicitar Préstamo
                                </button>
                            </form>
                        <?php else: ?>
                            <?php if ($reserva_usuario): ?>
                                <?php if ($reserva_usuario['estado'] === 'Disponible'): ?>
                                    <div class="reserva-info-box disponible">
                                        <i class="fas fa-bell"></i>
                                        <div>
                                            <strong>¡Tu reserva está disponible!</strong>
                                            <small>Reclámala en la biblioteca</small>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="reserva-info-box">
                                        <i class="fas fa-clock"></i>
                                        <div>
                                            <strong>Reserva activa</strong>
                                            <small>Posición en cola: #<?php echo $reserva_usuario['posicion_cola']; ?></small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <form method="POST">
                                    <button type="submit" name="cancelar_reserva" class="btn-action btn-cancelar" onclick="return confirm('¿Cancelar esta reserva?')">
                                        <i class="fas fa-times"></i> Cancelar Reserva
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST">
                                    <button type="submit" name="solicitar_reserva" class="btn-action btn-reserva">
                                        <i class="fas fa-bookmark"></i> Reservar este libro
                                    </button>
                                </form>
                                <?php if ($total_reservas > 0): ?>
                                    <div class="reserva-contador">
                                        <i class="fas fa-users"></i> 
                                        <?php echo $total_reservas; ?> <?php echo $total_reservas == 1 ? 'persona esperando' : 'personas esperando'; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <a href="catalogo.php" class="btn-action btn-outline-gold">
                            <i class="fas fa-th"></i> Ver Catálogo
                        </a>
                    </div>
                </div>

                <div class="book-info">
                    <h1><?php echo htmlspecialchars($libro['titulo']); ?></h1>
                    
                    <div class="book-meta">
                        <div class="meta-item">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars(implode(', ', $autoresArray)); ?></span>
                        </div>
                        
                        <?php if (!empty($libro['anio_publicacion'])): ?>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo $libro['anio_publicacion']; ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="meta-item">
                            <i class="fas fa-cube"></i>
                            <span><?php echo $libro['existencias_totales']; ?> disponibles</span>
                        </div>
                        
                        <div class="meta-item">
                            <i class="fas fa-code"></i>
                            <span><?php echo htmlspecialchars($libro['codigo']); ?></span>
                        </div>
                    </div>

                    <?php if (!empty($libro['generos']) || !empty($libro['nombre_categoria'])): ?>
                        <div class="genre-tags">
                            <?php if (!empty($libro['nombre_categoria'])): ?>
                                <span class="genre-tag">
                                    <i class="fas fa-folder"></i> <?php echo htmlspecialchars($libro['nombre_categoria']); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php foreach ($generosArray as $genero): ?>
                                <span class="genre-tag">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($genero); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($libro['descripcion'])): ?>
                        <div class="description-section">
                            <h2 class="section-title">Descripción</h2>
                            <div class="description-text">
                                <?php echo nl2br(htmlspecialchars($libro['descripcion'])); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="description-section">
                            <h2 class="section-title">Descripción</h2>
                            <div class="description-text" style="font-style: italic; color: #6b7d8a;">
                                Este libro aún no tiene descripción disponible.
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($libro['editorial'])): ?>
                        <div class="description-section">
                            <h2 class="section-title">Detalles</h2>
                            <p><strong>Editorial:</strong> <?php echo htmlspecialchars($libro['editorial']); ?></p>
                            <p><strong>Año:</strong> <?php echo $libro['anio_publicacion'] ?? 'N/A'; ?></p>
                            <p><strong>Código:</strong> <?php echo htmlspecialchars($libro['codigo']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>