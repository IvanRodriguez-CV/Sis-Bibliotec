<?php
require_once '../config/conexion.php';
session_start();

$msg = '';
$error = '';
$registro_exitoso = false;

$nombre = '';
$apellido = '';
$correo = '';
$id_carrera = '';
$tipo = 'Estudiante';

try {
    $carreras = $pdo->query('SELECT * FROM Carrera')->fetchAll();
} catch (PDOException $e) {
    $carreras = [];
    $error = '<div class="alert alert-danger text-center border-0 shadow-sm">No se pudo cargar la lista de carreras.</div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registro'])) {
    $correo = trim($_POST['correo'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $id_carrera = !empty($_POST['id_carrera']) ? intval($_POST['id_carrera']) : null;
    $tipo = in_array($_POST['tipo_usuario'] ?? '', ['Estudiante', 'Docente', 'admin'], true) ? $_POST['tipo_usuario'] : 'Estudiante';
    $contrasena = trim($_POST['contrasena'] ?? '');
    $nombre_completo = $nombre . ' ' . $apellido;

    if ($correo === '' || $nombre === '' || $apellido === '' || $contrasena === '') {
        $error = '<div class="alert alert-danger text-center border-0 shadow-sm">Por favor complete los campos obligatorios.</div>';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = '<div class="alert alert-danger text-center border-0 shadow-sm">Ingrese un correo electrónico válido.</div>';
    } else {
        try {
            // Verificar si el correo ya existe
            $checkEmailStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM Usuario WHERE correo = ?');
            $checkEmailStmt->execute([$correo]);
            $emailCount = $checkEmailStmt->fetch(PDO::FETCH_ASSOC)['total'];

            if ($emailCount > 0) {
                $error = '<div class="alert alert-danger text-center border-0 shadow-sm">El correo electrónico ya se encuentra en uso.</div>';
            } else {
                // Encriptar contraseña
                $hash = password_hash($contrasena, PASSWORD_BCRYPT);

                // Generar carnet único
                $prefijo = $tipo === 'admin' ? 'ADMIN' : ($tipo === 'Estudiante' ? 'EST-' : 'DOC-');
                $checkCarnetStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM Usuario WHERE carnet_codigo = ?');

                do {
                    $carnet = $prefijo . rand(10000, 99999);
                    $checkCarnetStmt->execute([$carnet]);
                    $carnetCount = $checkCarnetStmt->fetch(PDO::FETCH_ASSOC)['total'];
                } while ($carnetCount > 0);

                // Insertar usuario
                $stmt = $pdo->prepare('INSERT INTO Usuario (id_carrera, carnet_codigo, nombre_completo, correo, contrasenia, tipo_usuario) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$id_carrera, $carnet, $nombre_completo, $correo, $hash, $tipo]);

                $_SESSION['tipo_usuario'] = $tipo;
                $_SESSION['nombre_completo'] = $nombre_completo;

                $registro_exitoso = true;
                $msg = '<div class="alert alert-success p-4 border-0 shadow-sm mb-4">'
                    . '<h4 class="alert-heading fw-bold">🎉 ¡Inscripción Completada!</h4>'
                    . '<p class="mb-3">Tu cuenta ha sido creada exitosamente.</p>'
                    . '<hr>'
                    . '<p class="mb-0 fs-5">Tu código de acceso es: <strong class="badge bg-dark fs-5">' . htmlspecialchars($carnet, ENT_QUOTES, 'UTF-8') . '</strong></p>'
                    . '</div>';
            }
        } catch (PDOException $e) {
            $error = '<div class="alert alert-danger text-center border-0 shadow-sm">Ocurrió un error inesperado al procesar el registro.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Biblioteca - Registro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow border-0">
                    <div class="card-header bg-primary text-white text-center py-4">
                        <h2 class="fw-bold mb-0">Formulario de Inscripción</h2>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <?php if (!empty($error)) echo $error; ?>
                        <?php if (!empty($msg)) echo $msg; ?>
                        <?php if ($registro_exitoso): ?>
                             <div class="text-center mt-4">
                                 <a href="<?php echo ($_SESSION['tipo_usuario'] === 'admin') ? '../admin/dashboard.php' : '../usuario/catalogo.php'; ?>" class="btn btn-success btn-lg w-100 py-3 fw-bold shadow">
                                      Incio Completado 😎
                                 </a>
                             </div>
                        <?php else: ?>
                            <form action="registro.php" method="POST">
                                <input type="hidden" name="registro" value="1">
                                <div class="mb-3">
                                    <label for="nombre" class="form-label fw-semibold">Nombre</label>
                                    <input type="text" id="nombre" name="nombre" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label for="apellido" class="form-label fw-semibold">Apellido</label>
                                    <input type="text" id="apellido" name="apellido" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label for="correo" class="form-label fw-semibold">Correo Electrónico</label>
                                    <input type="email" id="correo" name="correo" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label for="id_carrera" class="form-label fw-semibold">Carrera Académica</label>
                                    <select id="id_carrera" name="id_carrera" class="form-select">
                                        <option value="">-- No aplica / Dejar en blanco --</option>
                                        <?php foreach ($carreras as $c): ?>
                                            <option value="<?php echo $c['id_carrera']; ?>"><?php echo htmlspecialchars($c['nombre_carrera']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="tipo_usuario" class="form-label fw-semibold">Nivel / Perfil</label>
                                    <select id="tipo_usuario" name="tipo_usuario" class="form-select" required>
                                        <option value="Estudiante">Estudiante</option>
                                        <option value="Docente">Docente</option>
                                      
                                    
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="contrasena" class="form-label fw-semibold">Contraseña de Acceso</label>
                                    <input type="password" id="contrasena" name="contrasena" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">Registrar Cuenta</button>
                            </form>
                            <div class="text-center mt-4">
                                <p class="mb-0 text-muted">¿Ya tienes cuenta? <a href="login.php" class="fw-semibold">Iniciar Sesión</a></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
