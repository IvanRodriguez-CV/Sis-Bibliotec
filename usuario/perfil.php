<?php
require_once '../config/conexion.php';
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../public/login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$msg = '';
$error = '';

// Obtener datos actuales
$stmt = $pdo->prepare("SELECT u.*, c.nombre_carrera 
                        FROM Usuario u 
                        LEFT JOIN Carrera c ON u.id_carrera = c.id_carrera 
                        WHERE u.id_usuario = ?");
$stmt->execute([$id_usuario]);
$perfil = $stmt->fetch();

if (!$perfil) {
    header("Location: ../public/login.php");
    exit;
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar'])) {
    $nombre = trim($_POST['nombre_completo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $nueva_contrasenia = $_POST['nueva_contrasenia'] ?? '';
    $confirmar_contrasenia = $_POST['confirmar_contrasenia'] ?? '';

    // Validaciones
    if (empty($nombre)) {
        $error = '<div class="alert-custom alert-danger-custom">✗ El nombre es obligatorio</div>';
    } elseif (empty($correo)) {
        $error = '<div class="alert-custom alert-danger-custom">✗ El correo es obligatorio</div>';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = '<div class="alert-custom alert-danger-custom">✗ Correo electrónico inválido</div>';
    } elseif (!empty($nueva_contrasenia) && strlen($nueva_contrasenia) < 6) {
        $error = '<div class="alert-custom alert-danger-custom">✗ La contraseña debe tener al menos 6 caracteres</div>';
    } elseif (!empty($nueva_contrasenia) && $nueva_contrasenia !== $confirmar_contrasenia) {
        $error = '<div class="alert-custom alert-danger-custom">✗ Las contraseñas no coinciden</div>';
    } else {
        try {
            // Verificar si el correo ya existe (si cambió)
            if ($correo !== $perfil['correo']) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM Usuario WHERE correo = ? AND id_usuario != ?");
                $stmt->execute([$correo, $id_usuario]);
                if ($stmt->fetchColumn() > 0) {
                    $error = '<div class="alert-custom alert-danger-custom">✗ Ese correo ya está registrado</div>';
                }
            }

            if (empty($error)) {
                // Preparar actualización
                if (!empty($nueva_contrasenia)) {
                    $hash = password_hash($nueva_contrasenia, PASSWORD_DEFAULT);
                    $update = $pdo->prepare("UPDATE Usuario SET nombre_completo = ?, telefono = ?, correo = ?, contrasenia = ? WHERE id_usuario = ?");
                    $update->execute([$nombre, $telefono ?: null, $correo, $hash, $id_usuario]);
                } else {
                    $update = $pdo->prepare("UPDATE Usuario SET nombre_completo = ?, telefono = ?, correo = ? WHERE id_usuario = ?");
                    $update->execute([$nombre, $telefono ?: null, $correo, $id_usuario]);
                }

                $msg = '<div class="alert-custom alert-success-custom">✓ Perfil actualizado correctamente</div>';
                
                // Refrescar datos
                $stmt = $pdo->prepare("SELECT u.*, c.nombre_carrera FROM Usuario u LEFT JOIN Carrera c ON u.id_carrera = c.id_carrera WHERE u.id_usuario = ?");
                $stmt->execute([$id_usuario]);
                $perfil = $stmt->fetch();
            }
        } catch (PDOException $e) {
            $error = '<div class="alert-custom alert-danger-custom">✗ Error al actualizar: ' . $e->getMessage() . '</div>';
        }
    }
}

// Generar iniciales
$nombreCompleto = $perfil['nombre_completo'] ?? '';
$palabras = explode(' ', trim($nombreCompleto));
$iniciales = '';
if (count($palabras) >= 2) {
    $iniciales = strtoupper(mb_substr($palabras[0], 0, 1) . mb_substr($palabras[count($palabras)-1], 0, 1));
} elseif (count($palabras) === 1 && !empty($palabras[0])) {
    $iniciales = strtoupper(mb_substr($palabras[0], 0, 2));
} else {
    $iniciales = '??';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Biblioteca Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/perfil.css">
</head>
<body>

    <nav class="sidebar">
        <div class="sidebar-header">
            <h3> Biblioteca</h3>
            <div class="subtitle">Digital</div>
        </div>
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'catalogo.php' ? 'active' : ''; ?>" href="./catalogo.php">
                        <span></span> Catálogo
                    </a>
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
        <div class="page-header">
            <div class="ornament">✦ ✦ ✦</div>
            <h1>Mi Perfil</h1>
            <p>Gestiona tu información personal</p>
        </div>

        <?php if (!empty($msg)) echo $msg; ?>
        <?php if (!empty($error)) echo $error; ?>

        <div class="perfil-card">
            <div class="perfil-header">
                <div class="avatar-circle">
                    <?php echo $iniciales; ?>
                </div>
                <h2 class="perfil-nombre"><?php echo htmlspecialchars($perfil['nombre_completo']); ?></h2>
                <span class="perfil-tipo"><?php echo htmlspecialchars($perfil['tipo_usuario']); ?></span>
            </div>

            <div class="perfil-body">
                <h3 class="seccion-titulo">Información de la Cuenta</h3>
                <ul class="info-list">
                    <li>
                        <div class="info-icon">🎫</div>
                        <div class="info-content">
                            <span class="info-label">Carnet</span>
                            <span class="info-value"><strong><?php echo htmlspecialchars($perfil['carnet_codigo']); ?></strong></span>
                        </div>
                    </li>
                    <li>
                        <div class="info-icon">🎓</div>
                        <div class="info-content">
                            <span class="info-label">Carrera</span>
                            <span class="info-value"><?php echo htmlspecialchars($perfil['nombre_carrera']); ?></span>
                        </div>
                    </li>
                </ul>

                <div class="form-section">
                    <h3 class="seccion-titulo">Editar Información</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="nombre_completo" class="form-label-custom">Nombre Completo</label>
                            <input 
                                type="text" 
                                id="nombre_completo" 
                                name="nombre_completo" 
                                class="input-custom" 
                                value="<?php echo htmlspecialchars($perfil['nombre_completo']); ?>" 
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="correo" class="form-label-custom">Correo Electrónico</label>
                            <input 
                                type="email" 
                                id="correo" 
                                name="correo" 
                                class="input-custom" 
                                value="<?php echo htmlspecialchars($perfil['correo']); ?>" 
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="telefono" class="form-label-custom">Teléfono</label>
                            <p class="form-hint">Este campo es opcional</p>
                            <input 
                                type="text" 
                                id="telefono" 
                                name="telefono" 
                                class="input-custom" 
                                value="<?php echo htmlspecialchars($perfil['telefono'] ?? ''); ?>" 
                                placeholder="Ej: 7000-0000"
                            >
                        </div>

                        <div class="form-group">
                            <label for="nueva_contrasenia" class="form-label-custom">Nueva Contraseña</label>
                            <p class="form-hint">Déjalo vacío si no deseas cambiarla</p>
                            <input 
                                type="password" 
                                id="nueva_contrasenia" 
                                name="nueva_contrasenia" 
                                class="input-custom" 
                                placeholder="Mínimo 6 caracteres"
                            >
                        </div>

                        <div class="form-group">
                            <label for="confirmar_contrasenia" class="form-label-custom">Confirmar Contraseña</label>
                            <input 
                                type="password" 
                                id="confirmar_contrasenia" 
                                name="confirmar_contrasenia" 
                                class="input-custom" 
                                placeholder="Repite la contraseña"
                            >
                        </div>

                        <button type="submit" name="actualizar" class="btn-guardar">
                            Guardar Cambios
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>