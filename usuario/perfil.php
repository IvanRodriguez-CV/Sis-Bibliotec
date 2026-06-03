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
        $msg = '<div class="alert alert-success text-center">Teléfono actualizado correctamente ✅</div>';
        $perfil['telefono'] = $telefono; // refrescar dato en pantalla
    } catch (PDOException $e) {
        $error = '<div class="alert alert-danger text-center">Error al actualizar el teléfono.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Perfil - Biblioteca</title>
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
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <?php if (!empty($msg)) echo $msg; ?>
                <?php if (!empty($error)) echo $error; ?>

                <div class="card shadow border-0">
                    <div class="card-header bg-primary text-white text-center py-4">
                        <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($perfil['nombre_completo']); ?></h3>
                        <span class="badge bg-white text-primary fw-bold px-3 rounded-pill">
                            <?php echo htmlspecialchars($perfil['tipo_usuario']); ?>
                        </span>
                    </div>

                    <div class="card-body p-4">
                        <h5 class="fw-bold text-secondary mb-4 border-bottom pb-2">Información de la Cuenta</h5>
                        <ul class="list-group list-group-flush mb-4">
                            <li class="list-group-item">Carnet: <strong><?php echo htmlspecialchars($perfil['carnet_codigo']); ?></strong></li>
                            <li class="list-group-item">Correo: <?php echo htmlspecialchars($perfil['correo']); ?></li>
                            <li class="list-group-item">Carrera: <?php echo htmlspecialchars($perfil['nombre_carrera']); ?></li>
                            <li class="list-group-item">Teléfono: 
                                <?php echo !empty($perfil['telefono']) ? htmlspecialchars($perfil['telefono']) : '<em>No registrado</em>'; ?>
                            </li>
                        </ul>

                        <!-- Formulario opcional para actualizar teléfono -->
                        <form method="POST" class="mt-3">
                            <div class="mb-3">
                                <label for="telefono" class="form-label fw-semibold">Actualizar Teléfono (opcional)</label>
                                <input type="text" id="telefono" name="telefono" class="form-control" 
                                       value="<?php echo htmlspecialchars($perfil['telefono']); ?>" placeholder="Ej: 7000-0000">
                            </div>
                            <button type="submit" name="actualizar" class="btn btn-primary w-100">Guardar Cambios</button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </main>

</body>
</html>
