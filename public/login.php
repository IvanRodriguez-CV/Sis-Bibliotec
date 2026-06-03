<?php
require_once '../config/conexion.php';
session_start();

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    $carnet_codigo = trim($_POST['carnet_codigo'] ?? '');

    if (!empty($correo) && !empty($contrasena)) {
        $stmt = $pdo->prepare("SELECT * FROM Usuario WHERE correo = ?");
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($contrasena, $usuario['contrasenia'])) {
            // Si es administrador, requiere carnet adicional
            if ($usuario['tipo_usuario'] === 'admin') {
                if (!empty($carnet_codigo) && $usuario['carnet_codigo'] === $carnet_codigo) {
                    $_SESSION['id_usuario'] = $usuario['id_usuario'];
                    $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];
                    $_SESSION['nombre_completo'] = $usuario['nombre_completo'];

                    // ✅ Carpeta admin está al mismo nivel que public
                    header("Location: ../admin/dashboard.php");
                    exit;
                } else {
                    $error = '<div class="alert alert-danger text-center border-0 shadow-sm">Código de Administración incorrecto o ausente.</div>';
                }
            } else {
                // Usuario normal (Estudiante o Docente)
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];
                $_SESSION['nombre_completo'] = $usuario['nombre_completo'];

                // ✅ Carpeta usuario está al mismo nivel que public
                header("Location: ../usuario/catalogo.php");
                exit;
            }
        } else {
            $error = '<div class="alert alert-danger text-center border-0 shadow-sm">Credenciales de acceso incorrectas.</div>';
        }
    } else {
        $error = '<div class="alert alert-danger text-center border-0 shadow-sm">Por favor complete los campos obligatorios.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Biblioteca - Iniciar Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="card shadow border-0">
                    <div class="card-header bg-dark text-white text-center py-4">
                        <h2 class="fw-bold mb-0">🔑 Control de Acceso</h2>
                        <small class="text-white-50">Ingresa al Sistema de la Biblioteca</small>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <?php if (!empty($error)) echo $error; ?>
                        <form action="login.php" method="POST">
                            <div class="mb-3">
                                <label for="correo" class="form-label fw-semibold">Correo Electrónico</label>
                                <input type="email" id="correo" name="correo" class="form-control" placeholder="ejemplo@correo.com" required>
                            </div>
                            <div class="mb-3">
                                <label for="contrasena" class="form-label fw-semibold">Contraseña</label>
                                <input type="password" id="contrasena" name="contrasena" class="form-control" placeholder="••••••••" required>
                            </div>
                            <div class="mb-4 bg-light p-3 rounded border">
                                <label for="carnet_codigo" class="form-label fw-semibold text-primary">Código de Administración</label>
                                <input type="text" id="carnet_codigo" name="carnet_codigo" class="form-control" placeholder="Solo si eres Administrador">
                                <small class="text-muted">Los lectores comunes pueden dejar este campo en blanco.</small>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">Iniciar Sesión</button>
                        </form>
                        <div class="text-center mt-4 pt-3 border-top">
                            <p class="mb-0 text-muted">
                                ¿No tienes una cuenta de lector? 
                                <a href="registro.php" class="fw-semibold link-secondary">Registrarse aquí</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
