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
$stmt = $pdo->prepare("SELECT u.carnet_codigo, u.nombre_completo, u.telefono, u.correo, u.tipo_usuario, c.nombre_carrera 
                        FROM Usuario u 
                        LEFT JOIN Carrera c ON u.id_carrera = c.id_carrera 
                        WHERE u.id_usuario = ?");
$stmt->execute([$id_usuario]);
$perfil = $stmt->fetch();

if (!$perfil) {
    header("Location: ../public/login.php");
    exit;
}

// Procesar actualización de teléfono (opcional)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar'])) {
    $telefono = trim($_POST['telefono'] ?? '');

    try {
        $update = $pdo->prepare("UPDATE Usuario SET telefono = ? WHERE id_usuario = ?");
        $update->execute([$telefono !== '' ? $telefono : null, $id_usuario]);
        $msg = '<div class="alert-custom alert-success-custom">✓ Teléfono actualizado correctamente</div>';
        $perfil['telefono'] = $telefono; // refrescar dato en pantalla
    } catch (PDOException $e) {
        $error = '<div class="alert-custom alert-danger-custom">✗ Error al actualizar el teléfono.</div>';
    }
}

// Generar iniciales para el avatar
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
            text-decoration: none;
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
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .sidebar-footer .btn-salir:hover {
            background: #c9a84c;
            color: #0B1C2B;
        }

        /* Contenido principal */
        .main-content {
            padding: 2rem 3rem;
            max-width: 1000px;
            margin: 0 auto;
        }

        /* Header de la página */
        .page-header {
            text-align: center;
            padding: 2rem 0 3rem;
            border-bottom: 1px solid rgba(201, 168, 76, 0.2);
            margin-bottom: 3rem;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            color: #c9a84c;
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
            margin-bottom: 0.5rem;
        }

        .page-header .ornament {
            color: #8b7d5e;
            font-size: 1.5rem;
            letter-spacing: 8px;
        }

        .page-header p {
            color: #8b9da8;
            font-size: 1rem;
            margin-top: 1rem;
            font-style: italic;
        }

        /* Alertas personalizadas */
        .alert-custom {
            border-radius: 8px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            font-family: 'Lora', serif;
            font-weight: 500;
            animation: fadeIn 0.5s ease;
            border: 1px solid;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success-custom {
            background: rgba(40, 167, 69, 0.15);
            border-color: rgba(40, 167, 69, 0.4);
            color: #5cb85c;
        }

        .alert-danger-custom {
            background: rgba(220, 53, 69, 0.15);
            border-color: rgba(220, 53, 69, 0.4);
            color: #e74c3c;
        }

        /* Card de perfil principal */
        .perfil-card {
            background: linear-gradient(135deg, rgba(15, 34, 52, 0.95) 0%, rgba(26, 52, 80, 0.95) 100%);
            border: 2px solid rgba(201, 168, 76, 0.3);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 15px 50px rgba(0,0,0,0.5);
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Header del perfil con avatar */
        .perfil-header {
            background: linear-gradient(135deg, rgba(10, 22, 32, 0.9) 0%, rgba(19, 39, 56, 0.9) 100%);
            padding: 3rem 2rem;
            text-align: center;
            border-bottom: 2px solid rgba(201, 168, 76, 0.3);
            position: relative;
        }

        .perfil-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, transparent, #c9a84c, transparent);
        }

        /* Avatar circular con iniciales */
        .avatar-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #c9a84c, #8b7d5e);
            color: #0B1C2B;
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            border: 4px solid rgba(201, 168, 76, 0.4);
            box-shadow: 
                0 10px 30px rgba(0,0,0,0.5),
                0 0 0 8px rgba(201, 168, 76, 0.1);
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            animation: pulseGlow 3s ease-in-out infinite;
        }

        @keyframes pulseGlow {
            0%, 100% {
                box-shadow: 
                    0 10px 30px rgba(0,0,0,0.5),
                    0 0 0 8px rgba(201, 168, 76, 0.1);
            }
            50% {
                box-shadow: 
                    0 10px 30px rgba(0,0,0,0.5),
                    0 0 0 12px rgba(201, 168, 76, 0.2);
            }
        }

        .perfil-nombre {
            font-family: 'Playfair Display', serif;
            color: #c9a84c;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
        }

        .perfil-tipo {
            display: inline-block;
            background: rgba(201, 168, 76, 0.15);
            color: #c9a84c;
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            border: 1px solid rgba(201, 168, 76, 0.4);
        }

        /* Cuerpo del perfil */
        .perfil-body {
            padding: 2.5rem;
        }

        .seccion-titulo {
            font-family: 'Playfair Display', serif;
            color: #c9a84c;
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(201, 168, 76, 0.3);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .seccion-titulo::before {
            content: '✦';
            color: #8b7d5e;
            font-size: 1rem;
        }

        /* Lista de información */
        .info-list {
            list-style: none;
            padding: 0;
            margin: 0 0 2.5rem 0;
        }

        .info-list li {
            display: flex;
            align-items: center;
            padding: 1.1rem 1.25rem;
            background: rgba(10, 22, 32, 0.5);
            border: 1px solid rgba(201, 168, 76, 0.15);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }

        .info-list li:hover {
            background: rgba(201, 168, 76, 0.08);
            border-color: rgba(201, 168, 76, 0.4);
            transform: translateX(6px);
        }

        .info-list li:last-child {
            margin-bottom: 0;
        }

        .info-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, rgba(201, 168, 76, 0.2), rgba(139, 125, 94, 0.2));
            border: 1px solid rgba(201, 168, 76, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-right: 1.25rem;
            flex-shrink: 0;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            display: block;
            color: #8b9da8;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.2rem;
        }

        .info-value {
            color: #e8d9b4;
            font-size: 1rem;
            font-weight: 500;
        }

        .info-value strong {
            color: #c9a84c;
            font-weight: 600;
        }

        .info-value em {
            color: #6b7d8a;
            font-style: italic;
        }

        /* Formulario */
        .form-section {
            background: rgba(10, 22, 32, 0.6);
            border: 1px solid rgba(201, 168, 76, 0.2);
            border-radius: 12px;
            padding: 2rem;
            margin-top: 1rem;
        }

        .form-section .seccion-titulo {
            margin-top: 0;
        }

        .form-label-custom {
            color: #c9a84c;
            font-family: 'Lora', serif;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-hint {
            color: #6b7d8a;
            font-size: 0.8rem;
            font-style: italic;
            margin-top: 0.25rem;
            margin-bottom: 1rem;
        }

        .input-custom {
            width: 100%;
            background: rgba(11, 28, 43, 0.8);
            border: 1px solid rgba(201, 168, 76, 0.3);
            border-radius: 8px;
            padding: 0.85rem 1.15rem;
            color: #e8d9b4;
            font-family: 'Lora', serif;
            font-size: 1rem;
            transition: all 0.3s ease;
            outline: none;
        }

        .input-custom::placeholder {
            color: #6b7d8a;
            font-style: italic;
        }

        .input-custom:focus {
            border-color: #c9a84c;
            background: rgba(11, 28, 43, 1);
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.15);
        }

        .btn-guardar {
            width: 100%;
            background: linear-gradient(135deg, #c9a84c, #a8893c);
            border: none;
            color: #0B1C2B;
            padding: 0.9rem 2rem;
            border-radius: 8px;
            font-family: 'Lora', serif;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            text-transform: uppercase;
        }

        .btn-guardar:hover {
            background: linear-gradient(135deg, #d4b85c, #b8994c);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(201, 168, 76, 0.3);
        }

        .btn-guardar:active {
            transform: translateY(0);
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

            .page-header h1 {
                font-size: 1.8rem;
            }

            .perfil-body {
                padding: 1.5rem;
            }

            .perfil-nombre {
                font-size: 1.5rem;
            }

            .avatar-circle {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }
        }

        @media (max-width: 575.98px) {
            .page-header h1 {
                font-size: 1.4rem;
            }

            .info-list li {
                padding: 0.9rem 1rem;
            }

            .info-icon {
                width: 36px;
                height: 36px;
                font-size: 0.95rem;
                margin-right: 1rem;
            }

            .info-value {
                font-size: 0.9rem;
            }

            .form-section {
                padding: 1.5rem;
            }
        }
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
        <div class="page-header">
            <div class="ornament">✦ ✦ ✦</div>
            <h1>Mi Perfil</h1>
            <p>Gestiona tu información personal</p>
        </div>

        <?php if (!empty($msg)) echo $msg; ?>
        <?php if (!empty($error)) echo $error; ?>

        <!-- Card de perfil -->
        <div class="perfil-card">
            <!-- Header con avatar -->
            <div class="perfil-header">
                <div class="avatar-circle">
                    <?php echo $iniciales; ?>
                </div>
                <h2 class="perfil-nombre"><?php echo htmlspecialchars($perfil['nombre_completo']); ?></h2>
                <span class="perfil-tipo"><?php echo htmlspecialchars($perfil['tipo_usuario']); ?></span>
            </div>

            <!-- Cuerpo -->
            <div class="perfil-body">
                <!-- Información de la cuenta -->
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
                        <div class="info-icon">✉</div>
                        <div class="info-content">
                            <span class="info-label">Correo electrónico</span>
                            <span class="info-value"><?php echo htmlspecialchars($perfil['correo']); ?></span>
                        </div>
                    </li>
                    <li>
                        <div class="info-icon">🎓</div>
                        <div class="info-content">
                            <span class="info-label">Carrera</span>
                            <span class="info-value"><?php echo htmlspecialchars($perfil['nombre_carrera']); ?></span>
                        </div>
                    </li>
                    <li>
                        <div class="info-icon">📞</div>
                        <div class="info-content">
                            <span class="info-label">Teléfono</span>
                            <span class="info-value">
                                <?php echo !empty($perfil['telefono']) ? '<strong>' . htmlspecialchars($perfil['telefono']) . '</strong>' : '<em>No registrado</em>'; ?>
                            </span>
                        </div>
                    </li>
                </ul>

                <!-- Formulario de actualización -->
                <div class="form-section">
                    <h3 class="seccion-titulo">Actualizar Teléfono</h3>
                    <form method="POST">
                        <label for="telefono" class="form-label-custom">Nuevo número de teléfono</label>
                        <p class="form-hint">Este campo es opcional. Déjalo vacío si no deseas modificarlo.</p>
                        <input 
                            type="text" 
                            id="telefono" 
                            name="telefono" 
                            class="input-custom" 
                            value="<?php echo htmlspecialchars($perfil['telefono'] ?? ''); ?>" 
                            placeholder="Ej: 7000-0000"
                        >
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