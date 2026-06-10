<?php
require_once '../config/conexion.php';
session_start();

// Validación de sesión y rol
if (!isset($_SESSION['id_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

// Asegurar que exista la tabla de relación entre libros y géneros
try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute(['Libro_Genero']);
    if (!$stmt->fetchColumn()) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS Libro_Genero (
                id_libro INT NOT NULL,
                id_genero INT NOT NULL,
                PRIMARY KEY (id_libro, id_genero),
                INDEX idx_libro (id_libro),
                INDEX idx_genero (id_genero)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
} catch (PDOException $e) {
    // Si no se puede crear la tabla, continuar sin bloquear la carga inicial.
}

// Crear libro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $id_categoria_arr = isset($_POST['id_categoria']) ? (array)$_POST['id_categoria'] : [];
    $id_categoria = isset($id_categoria_arr[0]) ? intval($id_categoria_arr[0]) : 0;
    $autor_ids = isset($_POST['id_autor']) ? (array)$_POST['id_autor'] : [];
    $autor_ids = array_filter(array_map('intval', $autor_ids));
    $genero_ids = isset($_POST['id_genero']) ? (array)$_POST['id_genero'] : [];
    $genero_ids = array_filter(array_map('intval', $genero_ids));
    $codigo = trim($_POST['codigo']);
    $titulo = trim($_POST['titulo']);
    $editorial = trim($_POST['editorial']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $anio = intval($_POST['anio_publicacion']);
    $existencias = intval($_POST['existencias_totales']);
    $imagen = '';

    // Procesar imagen
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $originalName = $_FILES['imagen']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $upload_dir = '../uploads/libros/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = 'libro_' . time() . '.' . $ext;
            $imagen = 'uploads/libros/' . $filename;
            move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_dir . $filename);
        }
    }

    if (!empty($codigo) && !empty($titulo) && $id_categoria > 0) {
        // Ensure codigo is unique to avoid integrity constraint violation
        $baseCodigo = $codigo;
        $suffix = 0;
        $codigoUnique = $baseCodigo;
        $check = $pdo->prepare("SELECT COUNT(*) FROM Libro WHERE codigo = ?");
        while (true) {
            $check->execute([$codigoUnique]);
            if ($check->fetchColumn() == 0) break;
            $suffix++;
            $codigoUnique = $baseCodigo . '_' . $suffix;
        }

        // Insert into Libro; genres stored in Libro_Genero and authors in Libro_Autor
        $stmt = $pdo->prepare("INSERT INTO Libro (id_categoria, codigo, titulo, editorial, imagen, anio_publicacion, existencias_totales) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_categoria, $codigoUnique, $titulo, $editorial, $imagen, $anio, $existencias]);
        $newId = $pdo->lastInsertId();
        if (!empty($autor_ids)) {
            $stmt2 = $pdo->prepare("INSERT INTO Libro_Autor (id_libro, id_autor) VALUES (?, ?)");
            foreach ($autor_ids as $sel_autor) {
                $stmt2->execute([$newId, $sel_autor]);
            }
        }
        if (!empty($genero_ids)) {
            $stmt3 = $pdo->prepare("INSERT INTO Libro_Genero (id_libro, id_genero) VALUES (?, ?)");
            foreach ($genero_ids as $sel_genero) {
                $stmt3->execute([$newId, $sel_genero]);
            }
        }
    }
}

// Editar libro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id_libro']);
    $id_categoria_arr = isset($_POST['id_categoria']) ? (array)$_POST['id_categoria'] : [];
    $id_categoria = isset($id_categoria_arr[0]) ? intval($id_categoria_arr[0]) : 0;
    $autor_ids = isset($_POST['id_autor']) ? (array)$_POST['id_autor'] : [];
    $autor_ids = array_filter(array_map('intval', $autor_ids));
    $genero_ids = isset($_POST['id_genero']) ? (array)$_POST['id_genero'] : [];
    $genero_ids = array_filter(array_map('intval', $genero_ids));
    $codigo = trim($_POST['codigo']);
    $titulo = trim($_POST['titulo']);
    $editorial = trim($_POST['editorial']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $anio = intval($_POST['anio_publicacion']);
    $existencias = intval($_POST['existencias_totales']);
    $imagen = trim($_POST['imagen_actual'] ?? '');

    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $originalName = $_FILES['imagen']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $upload_dir = '../uploads/libros/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = 'libro_' . time() . '.' . $ext;
            $imagen = 'uploads/libros/' . $filename;
            move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_dir . $filename);
        }
    }

    // Update Libro and refresh relations
    if (!empty($codigo) && !empty($titulo) && $id_categoria > 0) {
        $stmt = $pdo->prepare("UPDATE Libro SET id_categoria = ?, codigo = ?, titulo = ?, editorial = ?, imagen = ?, anio_publicacion = ?, existencias_totales = ? WHERE id_libro = ?");
        $stmt->execute([$id_categoria, $codigo, $titulo, $editorial, $imagen, $anio, $existencias, $id]);

        $del = $pdo->prepare("DELETE FROM Libro_Autor WHERE id_libro = ?");
        $del->execute([$id]);
        if (!empty($autor_ids)) {
            $ins = $pdo->prepare("INSERT INTO Libro_Autor (id_libro, id_autor) VALUES (?, ?)");
            foreach ($autor_ids as $sel_autor) {
                $ins->execute([$id, $sel_autor]);
            }
        }

        $delGenero = $pdo->prepare("DELETE FROM Libro_Genero WHERE id_libro = ?");
        $delGenero->execute([$id]);
        if (!empty($genero_ids)) {
            $insGenero = $pdo->prepare("INSERT INTO Libro_Genero (id_libro, id_genero) VALUES (?, ?)");
            foreach ($genero_ids as $sel_genero) {
                $insGenero->execute([$id, $sel_genero]);
            }
        }
    }
}

// Eliminar libro
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    // delete relations first
    $delRel = $pdo->prepare("DELETE FROM Libro_Autor WHERE id_libro = ?");
    $delRel->execute([$id]);
    $stmt = $pdo->prepare("DELETE FROM Libro WHERE id_libro = ?");
    $stmt->execute([$id]);
    header("Location: libros.php");
    exit;
}
$libros = $pdo->query("SELECT L.*, 
    GROUP_CONCAT(DISTINCT CONCAT(A.nombre,' ',A.apellido) SEPARATOR ', ') AS autores, 
    GROUP_CONCAT(DISTINCT A.id_autor) AS autores_ids,
    GROUP_CONCAT(DISTINCT LG.id_genero) AS generos_ids
    FROM Libro L
    LEFT JOIN Libro_Autor LA ON LA.id_libro = L.id_libro
    LEFT JOIN Autores A ON A.id_autor = LA.id_autor
    LEFT JOIN Libro_Genero LG ON LG.id_libro = L.id_libro
    GROUP BY L.id_libro
    ORDER BY L.id_libro DESC")->fetchAll();
$categorias = $pdo->query("SELECT * FROM Categoria ORDER BY nombre ASC")->fetchAll();
$autores = $pdo->query("SELECT * FROM Autores")->fetchAll();
try {
    $generos = $pdo->query("SELECT G.*, GROUP_CONCAT(CG.id_categoria) AS categorias FROM Genero G LEFT JOIN Categoria_Genero CG ON CG.id_genero = G.id_genero GROUP BY G.id_genero ORDER BY G.nombre ASC")->fetchAll();
} catch (PDOException $e) {
    $generos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mantenimiento de Libros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Navbar igual que dashboard -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
                ⚙️ <span class="ms-2">Panel Admin</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center gap-2">
                    <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='dashboard.php'?'active fw-semibold':'text-white-50'; ?>" href="dashboard.php">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='carreras.php'?'active fw-semibold':'text-white-50'; ?>" href="carreras.php">Carreras</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='libros.php'?'active fw-semibold':'text-white-50'; ?>" href="libros.php">Libros</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='autores.php'?'active fw-semibold':'text-white-50'; ?>" href="autores.php">Autores</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='categorias.php'?'active fw-semibold':'text-white-50'; ?>" href="categorias.php">Categorías</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='prestamos.php'?'active fw-semibold':'text-white-50'; ?>" href="prestamos.php">Préstamos</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='usuarios.php'?'active fw-semibold':'text-white-50'; ?>" href="usuarios.php">Usuarios</a></li>
                    <li class="nav-item">
                        <a class="btn btn-outline-light btn-sm ms-2 px-3 fw-bold" href="../public/login.php">Salir</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <main class="container my-5">
        <h2 class="fw-bold mb-4 text-center">Gestión Integral de Libros</h2>

        <!-- Formulario -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Registrar / Editar Libro</h5>
                <form action="libros.php" method="POST" class="row g-3" enctype="multipart/form-data">
                    <input type="hidden" name="id_libro" id="id_libro">
                    <input type="hidden" name="imagen_actual" id="imagen_actual">
                    <div class="col-md-6">
                        <label for="codigo" class="form-label">Código Único</label>
                        <input type="text" id="codigo" name="codigo" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="titulo" class="form-label">Título de la Obra</label>
                        <input type="text" id="titulo" name="titulo" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Categorías</label>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="categoriaDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                Seleccionar categorías
                            </button>
                            <div class="dropdown-menu w-100 p-3" style="max-height:250px; overflow-y:auto;" aria-labelledby="categoriaDropdown">
                                <?php foreach ($categorias as $cat): ?>
                                    <div class="form-check">
                                        <input class="form-check-input categoria-check" type="checkbox" name="id_categoria[]" value="<?php echo $cat['id_categoria']; ?>" id="cat_<?php echo $cat['id_categoria']; ?>">
                                        <label class="form-check-label" for="cat_<?php echo $cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <small class="text-muted">Selecciona una o varias categorías.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Géneros</label>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="generoDropdown" data-bs-toggle="dropdown" aria-expanded="false" disabled>
                                Seleccionar géneros
                            </button>
                            <div class="dropdown-menu w-100 p-3" style="max-height:250px; overflow-y:auto;" aria-labelledby="generoDropdown" id="generoMenu">
                                <?php foreach ($generos as $gen): ?>
                                    <div class="form-check">
                                        <input class="form-check-input genero-check" type="checkbox" name="id_genero[]" value="<?php echo $gen['id_genero']; ?>" id="gen_<?php echo $gen['id_genero']; ?>" data-categorias="<?php echo $gen['categorias']; ?>">
                                        <label class="form-check-label" for="gen_<?php echo $gen['id_genero']; ?>"><?php echo htmlspecialchars($gen['nombre']); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <small class="text-muted">Selecciona uno o varios géneros relacionados a las categorías elegidas.</small>
                    </div>
                    <div class="col-md-6">
                        <label for="id_autor" class="form-label">Autores</label>
                        <select id="id_autor" name="id_autor[]" class="form-select" multiple size="5" required>
                            <?php foreach ($autores as $aut): ?>
                                <option value="<?php echo $aut['id_autor']; ?>"><?php echo htmlspecialchars($aut['nombre'] . ' ' . $aut['apellido']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Selecciona uno o varios autores (Ctrl/Cmd + clic para múltiples).</small>
                    </div>
                    <div class="col-md-6">
                        <label for="editorial" class="form-label">Editorial</label>
                        <input type="text" id="editorial" name="editorial" class="form-control">
                    </div>
                    <div class="col-12">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea id="descripcion" name="descripcion" class="form-control" rows="4" placeholder="Escribe una breve descripción del libro"></textarea>
                    </div>
                    <div class="col-md-3">
                        <label for="anio_publicacion" class="form-label">Año Publicación</label>
                        <input type="number" id="anio_publicacion" name="anio_publicacion" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="existencias_totales" class="form-label">Existencias</label>
                        <input type="number" id="existencias_totales" name="existencias_totales" class="form-control" min="0" required>
                    </div>
                    <div class="col-md-6">
                        <label for="imagen" class="form-label">Imagen del Libro</label>
                        <input type="file" id="imagen" name="imagen" class="form-control" accept="image/*,.webp">
                        <small class="text-muted">JPG, PNG, GIF, WebP (máx 5MB)</small>
                    </div>
                    <div class="col-md-6" id="preview-container" style="display:none;">
                        <img id="imagen-preview" src="" alt="Vista previa" class="img-thumbnail" style="max-width:150px; max-height:200px;">
                    </div>
                    <div class="col-12">
                        <button type="submit" name="crear" id="btn-submit" class="btn btn-primary w-100">Guardar Libro</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Libros Registrados</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Imagen</th>
                                <th>Código</th>
                                <th>Título</th>
                                <th>Autor</th>
                                <th>Categoría</th>
                                <th>Existencias</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($libros as $l): ?>
                                <tr>
                                    <td>
                                        <?php $libroImagen = !empty($l['imagen'] ?? '') ? $l['imagen'] : ''; ?>
                                    <?php if ($libroImagen && file_exists('../' . $libroImagen)): ?>
                                            <img src="../<?php echo htmlspecialchars($libroImagen); ?>" alt="Portada" class="img-thumbnail" style="max-width:50px; max-height:70px;">
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Sin imagen</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($l['codigo']); ?></td>
                                    <td><?php echo htmlspecialchars($l['titulo']); ?></td>
                                    <td><?php echo htmlspecialchars($l['autores'] ?? ''); ?></td>
                                    <td><?php 
                                        // Categorías (puede ser lista separada por comas en DB)
                                        $cat_names = [];
                                        $ids = array_filter(array_map('trim', explode(',', $l['id_categoria'])));
                                        foreach ($categorias as $c) if (in_array($c['id_categoria'], $ids)) $cat_names[] = $c['nombre'];
                                        echo htmlspecialchars(implode(', ', $cat_names));
                                    ?></td>
                                    <td><?php echo $l['existencias_totales']; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning" 
                                            onclick='cargarDatos(
                                                <?php echo $l['id_libro']; ?>, 
                                                <?php echo json_encode($l['id_categoria']); ?>, 
                                                <?php echo json_encode($l['autores_ids'] ?? ''); ?>, 
                                                <?php echo json_encode($l['codigo']); ?>, 
                                                <?php echo json_encode($l['titulo']); ?>, 
                                                <?php echo json_encode($l['editorial']); ?>, 
                                                <?php echo json_encode($l['descripcion'] ?? ''); ?>,
                                                <?php echo $l['anio_publicacion']; ?>, 
                                                <?php echo $l['existencias_totales']; ?>,
                                                <?php echo json_encode($l['imagen'] ?? ''); ?>,
                                                <?php echo json_encode($l['generos_ids'] ?? ''); ?>
                                            )'>Editar</button>
                                        <a href="libros.php?eliminar=<?php echo $l['id_libro']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('¿Eliminar libro?')">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        function updateCategoriaButtonText() {
            const selected = Array.from(document.querySelectorAll('.categoria-check:checked')).map(input => {
                const label = input.nextElementSibling;
                return label ? label.textContent.trim() : '';
            }).filter(Boolean);
            const button = document.getElementById('categoriaDropdown');
            if (!button) return;
            if (selected.length === 0) {
                button.textContent = 'Seleccionar categorías';
            } else if (selected.length === 1) {
                button.textContent = selected[0];
            } else {
                button.textContent = 'Seleccionadas: ' + selected.length;
            }
        }

        const generosData = <?php echo json_encode($generos); ?>;

        function updateGeneroButtonText() {
            const selected = Array.from(document.querySelectorAll('.genero-check:checked')).map(input => {
                const label = input.nextElementSibling;
                return label ? label.textContent.trim() : '';
            }).filter(Boolean);
            const button = document.getElementById('generoDropdown');
            if (!button) return;
            if (selected.length === 0) {
                button.textContent = 'Seleccionar géneros';
            } else if (selected.length === 1) {
                button.textContent = selected[0];
            } else {
                button.textContent = 'Seleccionados: ' + selected.length;
            }
        }

        function updateGeneroOptions(selectedGenero = []) {
            const selectedCatIds = Array.from(document.querySelectorAll('.categoria-check:checked')).map(input => input.value);
            const generoChecks = document.querySelectorAll('.genero-check');
            const generoDropdown = document.getElementById('generoDropdown');
            const selectedGeneroIds = (Array.isArray(selectedGenero) ? selectedGenero : selectedGenero.toString().split(',').map(item => item.trim())).filter(Boolean);

            if (selectedCatIds.length === 0) {
                generoChecks.forEach(input => {
                    input.checked = false;
                    input.disabled = true;
                    input.closest('.form-check').style.display = 'none';
                });
                generoDropdown.disabled = true;
                generoDropdown.textContent = 'Seleccionar géneros';
                return;
            }

            generoDropdown.disabled = false;
            let anyVisible = false;
            generoChecks.forEach(input => {
                const categorias = input.dataset.categorias ? input.dataset.categorias.toString().split(',').map(item => item.trim()) : [];
                const match = categorias.some(catId => selectedCatIds.includes(catId));
                input.closest('.form-check').style.display = match ? 'block' : 'none';
                input.disabled = !match;
                if (!match) {
                    input.checked = false;
                }
                if (match && selectedGeneroIds.includes(input.value)) {
                    input.checked = true;
                }
                if (match) {
                    anyVisible = true;
                }
            });

            if (!anyVisible) {
                generoDropdown.textContent = 'No hay géneros para la categoría seleccionada';
            } else {
                updateGeneroButtonText();
            }
        }

        document.querySelectorAll('.categoria-check').forEach(input => {
            input.addEventListener('change', () => {
                updateCategoriaButtonText();
                updateGeneroOptions();
            });
        });

        document.querySelectorAll('.genero-check').forEach(input => {
            input.addEventListener('change', updateGeneroButtonText);
        });

        function cargarDatos(id, cat, aut, cod, tit, edit, descripcion, anio, stock, img, gen) {
            document.getElementById('id_libro').value = id;
            const categoryInputs = document.querySelectorAll('input[name="id_categoria[]"]');
            categoryInputs.forEach(input => input.checked = false);
            const selectedCats = cat ? cat.toString().split(',').map(item => item.trim()) : [];
            categoryInputs.forEach(input => {
                if (selectedCats.includes(input.value)) {
                    input.checked = true;
                }
            });
            updateCategoriaButtonText();
            updateGeneroOptions(gen);
            const autorSelect = document.getElementById('id_autor');
            const selectedAuths = aut ? aut.toString().split(',').map(item => item.trim()) : [];
            Array.from(autorSelect.options).forEach(option => {
                option.selected = selectedAuths.includes(option.value);
            });
            document.getElementById('codigo').value = cod;
            document.getElementById('titulo').value = tit;
            document.getElementById('editorial').value = edit;
            document.getElementById('descripcion').value = descripcion;
            document.getElementById('anio_publicacion').value = anio;
            document.getElementById('existencias_totales').value = stock;
            document.getElementById('imagen_actual').value = img;
            document.getElementById('btn-submit').name = 'editar';
            document.getElementById('btn-submit').textContent = 'Actualizar Libro';
            
            // Mostrar vista previa si existe imagen
            if (img) {
                document.getElementById('imagen-preview').src = '../' + img;
                document.getElementById('preview-container').style.display = 'block';
            } else {
                document.getElementById('preview-container').style.display = 'none';
            }
        }
        
        // Preview de imagen seleccionada
        document.getElementById('imagen')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('imagen-preview').src = event.target.result;
                    document.getElementById('preview-container').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        // Inicializa el texto del botón de categorías
        updateCategoriaButtonText();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
