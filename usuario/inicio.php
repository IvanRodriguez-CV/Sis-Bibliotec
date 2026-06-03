<?php
require_once '../config/conexion.php';
session_start();

$msg = "";
$carreras = $pdo->query("SELECT * FROM Carrera")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registro'])) {
    $correo = trim($_POST['correo']);
    $nombre_completo = trim($_POST['nombre']) . ' ' . trim($_POST['apellido']);
    $id_carrera = !empty($_POST['id_carrera']) ? intval($_POST['id_carrera']) : null;
    $tipo = $_POST['tipo_usuario']; 
    $contrasena = trim($_POST['contrasena']);
    $codigo_admin = trim($_POST['codigo_admin_verificar']);
    $telefono = trim($_POST['telefono']);
    
    $permitir_registro = true;

    if ($tipo === 'admin') {
        if ($codigo_admin !== "mimi2022") {
            $msg = '<div class="alert alert-danger text-center" role="alert">Código de acceso administrativo incorrecto.</div>';
            $permitir_registro = false;
        }
    }

    if (empty($id_carrera) && count($carreras) > 0) {
        $id_carrera = $carreras[0]['id_carrera'];
    }

    if ($permitir_registro) {
        if (!empty($correo) && !empty($nombre_completo) && !empty($contrasena) && !empty($id_carrera)) {
            $hash = password_hash($contrasena, PASSWORD_BCRYPT);
            
            if ($tipo === 'admin') {
                $carnet = 'ADM-' . rand(10000, 99999);
            } elseif ($tipo === 'Docente') {
                $carnet = 'DOC-' . rand(10000, 99999);
            } else {
                $carnet = 'EST-' . rand(10000, 99999);
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO Usuario (carnet_codigo, nombre_completo, id_carrera, telefono, correo, contrasenia, tipo_usuario) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$carnet, $nombre_completo, $id_carrera, $telefono, $correo, $hash, $tipo]);
                
                $msg = '<div class="alert alert-success text-center" role="alert">Inscripción exitosa. Tu carnet es: <strong>' . $carnet . '</strong>. Ya puedes <a href="../boblioteca/login.php" class="alert-link">Iniciar Sesión</a>.</div>';
            } catch (PDOException $e) {
                $msg = '<div class="alert alert-danger text-center" role="alert">El correo o código ya se encuentra registrado.</div>';
            }
        } else {
            $msg = '<div class="alert alert-danger text-center" role="alert">Por favor complete todos los campos requeridos.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca - Registro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function verificarRol(select) {
            var campoAdmin = document.getElementById('campo_codigo_admin');
            var campoCarrera = document.getElementById('campo_carrera');
            if (select.value === 'admin') {
                campoAdmin.classList.remove('d-none');
                campoCarrera.classList.add('d-none');
            } else {
                campoAdmin.classList.add('d-none');
                campoCarrera.classList.remove('d-none');
            }
        }
    </script>
</head>
<body class="bg-light">
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="text-center fw-bold text-primary mb-4">Inscripción al Sistema</h2>
                        <?php echo $msg; ?>
                        
                        <form action="inicio.php" method="POST">
                            <input type="hidden" name="registro" value="1">
                            
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label for="nombre" class="form-label fw-semibold">Nombre</label>
                                    <input type="text" id="nombre" name="nombre" class="form-control" required>
                                </div>
                                <div class="col-sm-6">
                                    <label for="apellido" class="form-label fw-semibold">Apellido</label>
                                    <input type="text" id="apellido" name="apellido" class="form-control" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label for="correo" class="form-label fw-semibold">Correo Electrónico</label>
                                    <input type="email" id="correo" name="correo" class="form-control" required>
                                </div>
                                <div class="col-sm-6">
                                    <label for="telefono" class="form-label fw-semibold">Teléfono</label>
                                    <input type="text" id="telefono" name="telefono" class="form-control">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="tipo_usuario" class="form-label fw-semibold">Selecciona tu Perfil / Rol</label>
                                <select id="tipo_usuario" name="tipo_usuario" class="form-select" onchange="verificarRol(this)" required>
                                    <option value="Estudiante">Estudiante</option>
                                    <option value="Docente">Docente</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>

                            <div class="mb-3" id="campo_carrera">
                                <label for="id_carrera" class="form-label fw-semibold">Carrera Académica</label>
                                <select id="id_carrera" name="id_carrera" class="form-select">
                                    <option value="">-- Seleccionar carrera --</option>
                                    <?php foreach ($carreras as $c): ?>
                                        <option value="<?php echo $c['id_carrera']; ?>"><?php echo htmlspecialchars($c['nombre_carrera']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div id="campo_codigo_admin" class="mb-3 d-none bg-warning-subtle p-3 rounded border border-warning">
                                <label for="codigo_admin_verificar" class="form-label fw-semibold text-warning-emphasis">Código Especial de Administrador</label>
                                <input type="password" id="codigo_admin_verificar" name="codigo_admin_verificar" class="form-control" placeholder="mimi2022">
                            </div>

                            <div class="mb-4">
                                <label for="contrasena" class="form-label fw-semibold">Contraseña</label>
                                <input type="password" id="contrasena" name="contrasena" class="form-control" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">Registrar Cuenta</button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="mb-0 text-muted">¿Ya tienes cuenta? <a href="../boblioteca/login.php" class="text-decoration-none fw-semibold">Inicia Sesión</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>