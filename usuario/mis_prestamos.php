<?php
// usuario/mis_prestamos.php
require_once '../config/conexion.php';
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../public/login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['devolver_id'])) {
    $prestamo_id = intval($_POST['devolver_id']);
    $stmtUpdate = $pdo->prepare("UPDATE Prestamo SET estado_prestamo = 'Devuelto' WHERE id_prestamo = ? AND id_usuario = ?");
    if ($stmtUpdate->execute([$prestamo_id, $id_usuario])) {
        $mensaje = $stmtUpdate->rowCount() > 0
            ? 'El préstamo se marcó como devuelto correctamente.'
            : 'No se encontró el préstamo o ya está devuelto.';
    } else {
        $mensaje = 'Ocurrió un error al actualizar el préstamo.';
    }
}

// Consulta uniendo los datos de los préstamos del usuario actual
$stmt = $pdo->prepare("SELECT p.*, l.titulo, l.codigo FROM Prestamo p 
                        INNER JOIN Libro l ON p.id_libro = l.id_libro 
                        WHERE p.id_usuario = ? 
                        ORDER BY p.fecha_prestamo DESC");
$stmt->execute([$id_usuario]);
$prestamos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Préstamos</title>
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
        <div class="mb-4">
            <h2 class="fw-bold text-secondary">Mi Historial de Préstamos</h2>
            <p class="text-muted">Controla el estado y las fechas límite de tus devoluciones.</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-info py-3 px-4 rounded shadow-sm">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>
        
        <?php if (count($prestamos) > 0): ?>
            <div class="card shadow-sm border-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th scope="col" class="ps-4">Código</th>
                                <th scope="col">Título del Libro</th>
                                <th scope="col">Fecha Solicitud</th>
                                <th scope="col" class="pe-4">Estado</th>
                                <th scope="col" class="text-end pe-4">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prestamos as $p): ?>
                                <tr>
                                    <td class="ps-4"><span class="badge bg-secondary"><?php echo $p['codigo']; ?></span></td>
                                    <td class="fw-semibold text-dark"><?php echo htmlspecialchars($p['titulo']); ?></td>
                                    
                                    <td><?php echo date('d/m/Y', strtotime($p['fecha_prestamo'])); ?></td>
                                    
                                    <td class="pe-4">
                                        <?php 
                                        $clase_badge = 'bg-secondary';
                                        if ($p['estado_prestamo'] === 'Activo') $clase_badge = 'bg-success';
                                        if ($p['estado_prestamo'] === 'Vencido') $clase_badge = 'bg-danger';
                                        if ($p['estado_prestamo'] === 'Devuelto') $clase_badge = 'bg-dark';
                                        ?>
                                        <span class="badge <?php echo $clase_badge; ?> px-3 py-2">
                                            <?php echo $p['estado_prestamo']; ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if ($p['estado_prestamo'] === 'Activo' || $p['estado_prestamo'] === 'Vencido'): ?>
                                            <form method="post" class="m-0">
                                                <input type="hidden" name="devolver_id" value="<?php echo $p['id_prestamo']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                    Marcar devuelto
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-secondary">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-5 bg-white rounded shadow-sm border">
                <p class="text-muted mb-0">Aún no registras ninguna solicitud de préstamo en el sistema.</p>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>