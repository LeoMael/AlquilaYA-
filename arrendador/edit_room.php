<?php
// arrendador/edit_room.php
include '../includes/db_connect.php';
session_start();

// Verificar si el usuario ha iniciado sesión y es un arrendador
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] != 'arrendador') {
    header("Location: ../login.php");
    exit();
}

$id_arrendador = $_SESSION['id_arrendador'];

// Obtener id_cuarto desde la URL
if (isset($_GET['id_cuarto'])) {
    $id_cuarto = (int)$_GET['id_cuarto'];
} else {
    header("Location: dashboard.php");
    exit();
}

// Verificar que el cuarto pertenece al arrendador
$sql_cuarto = "SELECT cuartos.*, 
                      detalles_cuartos.descripcion AS descripcion_cuarto, 
                      casas.id_arrendador,
                      estados.estado AS estado_actual
               FROM cuartos
               LEFT JOIN detalles_cuartos ON cuartos.id_cuarto = detalles_cuartos.id_cuarto
               LEFT JOIN casas ON cuartos.id_casa = casas.id_casa
               LEFT JOIN estados ON cuartos.id_estado = estados.id_estado
               WHERE cuartos.id_cuarto = ? AND casas.id_arrendador = ?";
$stmt_cuarto = $conn->prepare($sql_cuarto);
$stmt_cuarto->bind_param("ii", $id_cuarto, $id_arrendador);
$stmt_cuarto->execute();
$result_cuarto = $stmt_cuarto->get_result();

if ($result_cuarto->num_rows == 0) {
    echo "No tienes permiso para editar este cuarto.";
    exit();
} else {
    $cuarto = $result_cuarto->fetch_assoc();
}

// Obtener los estados disponibles
$sql_estados = "SELECT * FROM estados";
$result_estados = $conn->query($sql_estados);

// Obtener la descripción del cuarto
$descripcion_cuarto = isset($cuarto['descripcion_cuarto']) ? $cuarto['descripcion_cuarto'] : '';

// Inicializar variables de error y éxito
$error = '';
$success = '';

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_cuarto'])) {
    // Sanitizar y validar los datos del formulario
    $piso = trim($_POST['piso']);
    $precio = trim($_POST['precio']);
    $metroscuadrados = trim($_POST['metroscuadrados']);
    $descripcion = trim($_POST['descripcion']);
    $estado = isset($_POST['estado']) ? (int)$_POST['estado'] : null;
    $id_estudiante = isset($_POST['id_estudiante']) ? (int)$_POST['id_estudiante'] : null;

    // Validaciones básicas
    if (empty($piso) || empty($precio) || empty($estado)) {
        $error = "Los campos piso, precio y estado son obligatorios.";
    } elseif (!is_numeric($piso) || !is_numeric($precio) || (!empty($metroscuadrados) && !is_numeric($metroscuadrados))) {
        $error = "Por favor, ingresa valores numéricos válidos en los campos correspondientes.";
    } else {
        // Manejo de la imagen
        $nombre_imagen = $cuarto['imagen']; // Mantener la imagen actual por defecto
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $imagen = $_FILES['imagen'];
                $nombre_temporal = $imagen['tmp_name'];
                $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
                $extension = strtolower(pathinfo($imagen['name'], PATHINFO_EXTENSION));

                if (!in_array($extension, $extensiones_permitidas)) {
                    $error = "Formato de imagen no permitido. Permisos: jpg, jpeg, png, gif.";
                } else {
                    $nombre_imagen = uniqid('cuarto_') . '.' . $extension;
                    $ruta_destino = '../uploads/' . $nombre_imagen;

                    if (!move_uploaded_file($nombre_temporal, $ruta_destino)) {
                        $error = "Error al subir la imagen.";
                    } else {
                        // Eliminar la imagen anterior si existe
                        if (!empty($cuarto['imagen']) && file_exists('../uploads/' . $cuarto['imagen'])) {
                            unlink('../uploads/' . $cuarto['imagen']);
                        }
                    }
                }
            } else {
                $error = "Error al subir la imagen.";
            }
        }

        // Si no hay errores hasta ahora, proceder con la actualización
        if (empty($error)) {
            // Iniciar una transacción para asegurar la integridad de los datos
            $conn->begin_transaction();

            try {
                // Actualizar el cuarto en la base de datos
                $sql_update_cuarto = "UPDATE cuartos SET piso = ?, precio = ?, metroscuadrados = ?, imagen = ?, id_estado = ? WHERE id_cuarto = ?";
                $stmt_update_cuarto = $conn->prepare($sql_update_cuarto);
                $stmt_update_cuarto->bind_param("iidisi", $piso, $precio, $metroscuadrados, $nombre_imagen, $estado, $id_cuarto);

                if (!$stmt_update_cuarto->execute()) {
                    throw new Exception("Error al actualizar el cuarto: " . $stmt_update_cuarto->error);
                }

                // Actualizar la descripción en la tabla detalles_cuartos
                $sql_update_detalle = "UPDATE detalles_cuartos SET descripcion = ? WHERE id_cuarto = ?";
                $stmt_update_detalle = $conn->prepare($sql_update_detalle);
                $stmt_update_detalle->bind_param("si", $descripcion, $id_cuarto);

                if (!$stmt_update_detalle->execute()) {
                    throw new Exception("Error al actualizar la descripción del cuarto: " . $stmt_update_detalle->error);
                }

                // Manejar la asignación de estudiante si el estado es 'No Disponible'
                if ($estado === 2) { // Suponiendo que '2' es el ID para 'No Disponible'
                    if ($id_estudiante) {
                        // Asignar el estudiante al cuarto
                        $sql_asignar = "UPDATE cuartos SET id_estudiante = ? WHERE id_cuarto = ?";
                        $stmt_asignar = $conn->prepare($sql_asignar);
                        $stmt_asignar->bind_param("ii", $id_estudiante, $id_cuarto);
                        if (!$stmt_asignar->execute()) {
                            throw new Exception("Error al asignar el estudiante: " . $stmt_asignar->error);
                        }
                    } else {
                        throw new Exception("Debe asignar un estudiante cuando el estado es 'No Disponible'.");
                    }
                } else {
                    // Si el estado no es 'No Disponible', eliminar cualquier asignación previa
                    $sql_desasignar = "UPDATE cuartos SET id_estudiante = NULL WHERE id_cuarto = ?";
                    $stmt_desasignar = $conn->prepare($sql_desasignar);
                    $stmt_desasignar->bind_param("i", $id_cuarto);
                    if (!$stmt_desasignar->execute()) {
                        throw new Exception("Error al desasignar el estudiante: " . $stmt_desasignar->error);
                    }
                }

                // Commit de la transacción
                $conn->commit();

                // Redirigir con mensaje de éxito
                $_SESSION['success_message'] = "El cuarto ha sido actualizado exitosamente.";
                header("Location: dashboard.php");
                exit();

            } catch (Exception $e) {
                // Revertir la transacción en caso de error
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Cuarto - Arrendador - AlquilaYA!</title>
    <!-- Enlaces a CSS de Bootstrap 5 para estilos modernos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Leaflet CSS para mapas -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <!-- Font Awesome para íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Estilos personalizados */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-family: 'Kaushan Script', cursive;
            font-size: 1.8rem;
            color: #dc3545 !important;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .navbar-nav .nav-link {
            font-weight: 500;
            color: #495057;
            transition: color 0.3s;
        }
        .navbar-nav .nav-link:hover {
            color: #dc3545 !important;
        }
        .edit-room-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            max-width: 900px;
            margin: 50px auto;
        }
        .map-container {
            height: 400px;
            border: 1px solid #ced4da;
            border-radius: 10px;
            overflow: hidden;
        }
        .btn-primary {
            background-color: #dc3545;
            border: none;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #c82333;
        }
        .btn-secondary {
            background-color: #6c757d;
            border: none;
            transition: background-color 0.3s;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .alert-custom {
            border-radius: 10px;
        }
        .current-image {
            max-width: 200px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- Barra de Navegación -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="../index.php"><i class="fas fa-home me-2"></i>AlquilaYA!</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Panel de Control</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Mi Perfil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenedor Principal -->
    <div class="container edit-room-container">
        <h2 class="mb-4 text-center"><i class="fas fa-edit me-2 text-success"></i>Editar Cuarto</h2>

        <!-- Mensajes de Error y Éxito -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-custom mb-4" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-custom mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario de Edición de Cuarto -->
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="piso" class="form-label"><i class="fas fa-layer-group me-2 text-primary"></i>Piso:</label>
                <input type="number" name="piso" id="piso" class="form-control" placeholder="Ingresa el piso" value="<?php echo htmlspecialchars($cuarto['piso']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="precio" class="form-label"><i class="fas fa-dollar-sign me-2 text-primary"></i>Precio (S/):</label>
                <input type="number" name="precio" id="precio" class="form-control" placeholder="Ingresa el precio" step="0.01" value="<?php echo htmlspecialchars($cuarto['precio']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="metroscuadrados" class="form-label"><i class="fas fa-ruler-combined me-2 text-primary"></i>Metros Cuadrados:</label>
                <input type="number" name="metroscuadrados" id="metroscuadrados" class="form-control" placeholder="Ingresa los metros cuadrados" value="<?php echo htmlspecialchars($cuarto['metroscuadrados']); ?>">
            </div>
            <div class="mb-4">
                <label for="descripcion" class="form-label"><i class="fas fa-info-circle me-2 text-primary"></i>Descripción:</label>
                <textarea name="descripcion" id="descripcion" class="form-control" rows="4" placeholder="Ingresa una descripción del cuarto"><?php echo htmlspecialchars($descripcion_cuarto); ?></textarea>
            </div>
            <div class="mb-4">
                <label for="estado" class="form-label"><i class="fas fa-clipboard-list me-2 text-primary"></i>Estado del Cuarto:</label>
                <select name="estado" id="estado-select" class="form-select" required>
                    <option value="">-- Seleccionar Estado --</option>
                    <?php
                    // Resetear el puntero del resultado de estados
                    $result_estados->data_seek(0);
                    while ($estado_option = $result_estados->fetch_assoc()) {
                        $selected = ($cuarto['id_estado'] == $estado_option['id_estado']) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($estado_option['id_estado']) . '" ' . $selected . '>' . htmlspecialchars($estado_option['estado']) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- Campo para asignar estudiante, solo si el estado es 'No Disponible' -->
            <div class="mb-4" id="estudiante-select-group" style="display: none;">
                <label for="id_estudiante" class="form-label"><i class="fas fa-user-plus me-2 text-primary"></i>Asignar Estudiante:</label>
                <select name="id_estudiante" id="id_estudiante" class="form-select">
                    <option value="">-- Seleccionar Estudiante --</option>
                    <?php
                    // Obtener la lista de estudiantes
                    $sql_estudiantes = "SELECT id_estudiante, nombre FROM estudiantes";
                    $result_estudiantes = $conn->query($sql_estudiantes);
                    while ($estudiante = $result_estudiantes->fetch_assoc()) {
                        // Verificar si el estudiante ya está asignado a otro cuarto
                        $sql_verificar = "SELECT * FROM cuartos WHERE id_estudiante = ?";
                        $stmt_verificar = $conn->prepare($sql_verificar);
                        $stmt_verificar->bind_param("i", $estudiante['id_estudiante']);
                        $stmt_verificar->execute();
                        $result_verificar = $stmt_verificar->get_result();
                        if ($result_verificar->num_rows == 0 || $result_verificar->fetch_assoc()['id_cuarto'] == $id_cuarto) {
                            echo '<option value="' . htmlspecialchars($estudiante['id_estudiante']) . '">' . htmlspecialchars($estudiante['nombre']) . '</option>';
                        }
                        $stmt_verificar->close();
                    }
                    ?>
                </select>
                <small class="form-text text-muted">Asignar un estudiante al cuarto (solo si está 'No Disponible').</small>
            </div>

            <!-- Mostrar imagen actual -->
            <?php if (!empty($cuarto['imagen']) && file_exists('../uploads/' . $cuarto['imagen'])): ?>
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-image me-2 text-primary"></i>Imagen Actual:</label><br>
                    <img src="../uploads/<?php echo htmlspecialchars($cuarto['imagen']); ?>" alt="Imagen del Cuarto" class="img-fluid mb-2 rounded current-image">
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="imagen" class="form-label"><i class="fas fa-images me-2 text-primary"></i>Cambiar Imagen (opcional):</label>
                <input type="file" name="imagen" id="imagen" class="form-control" accept="image/*">
                <small class="form-text text-muted">Sube una nueva imagen para reemplazar la actual (opcional).</small>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" name="editar_cuarto" class="btn btn-primary"><i class="fas fa-save me-2"></i>Guardar Cambios</button>
                <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Cancelar</a>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">&copy; <?php echo date("Y"); ?> AlquilaYA! - Todos los derechos reservados.</span>
        </div>
    </footer>

    <!-- Scripts de Bootstrap 5 y Leaflet -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Manejar la visibilidad del campo de estudiante basado en el estado seleccionado
            const estadoSelect = document.getElementById('estado-select');
            const estudianteGroup = document.getElementById('estudiante-select-group');

            function toggleEstudianteSelect() {
                const estadoSeleccionado = estadoSelect.value;
                // Suponiendo que el estado "No Disponible" tiene id_estado = 2
                if (estadoSeleccionado == 2) {
                    estudianteGroup.style.display = 'block';
                } else {
                    estudianteGroup.style.display = 'none';
                }
            }

            estadoSelect.addEventListener('change', toggleEstudianteSelect);
            toggleEstudianteSelect(); // Activar al cargar la página

            // Inicializar el mapa
            var latitud = <?php echo htmlspecialchars($cuarto['latitud']); ?>;
            var longitud = <?php echo htmlspecialchars($cuarto['longitud']); ?>;
            var map = L.map('map').setView([latitud, longitud], 13);

            // Añadir capa de mapa
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            // Añadir marcador
            var marker = L.marker([latitud, longitud], { draggable: true }).addTo(map)
                .bindPopup('<b>Ubicación Seleccionada</b><br>Latitud: ' + latitud.toFixed(6) + '<br>Longitud: ' + longitud.toFixed(6))
                .openPopup();

            // Función para actualizar los campos de latitud y longitud
            function actualizarCoordenadas(lat, lng) {
                document.getElementById('latitud').value = lat;
                document.getElementById('longitud').value = lng;
            }

            // Evento de arrastrar el marcador
            marker.on('dragend', function (e) {
                var coords = marker.getLatLng();
                actualizarCoordenadas(coords.lat, coords.lng);
                marker.setPopupContent('<b>Ubicación Seleccionada</b><br>Latitud: ' + coords.lat.toFixed(6) + '<br>Longitud: ' + coords.lng.toFixed(6));
                marker.openPopup();
            });

            // Evento de clic en el mapa para mover el marcador
            map.on('click', function (e) {
                var lat = e.latlng.lat;
                var lng = e.latlng.lng;

                // Mover el marcador a la nueva ubicación
                marker.setLatLng([lat, lng]).update();
                actualizarCoordenadas(lat, lng);
                marker.setPopupContent('<b>Ubicación Seleccionada</b><br>Latitud: ' + lat.toFixed(6) + '<br>Longitud: ' + lng.toFixed(6));
                marker.openPopup();
            });

            // Si hay coordenadas previamente seleccionadas (por ejemplo, en caso de errores de validación), centrar el mapa y añadir el marcador
            <?php if (isset($_POST['latitud']) && isset($_POST['longitud']) && is_numeric($_POST['latitud']) && is_numeric($_POST['longitud'])): ?>
                var latPrev = <?php echo htmlspecialchars($_POST['latitud']); ?>;
                var lngPrev = <?php echo htmlspecialchars($_POST['longitud']); ?>;
                map.setView([latPrev, lngPrev], 15);
                marker.setLatLng([latPrev, lngPrev]).update();
                actualizarCoordenadas(latPrev, lngPrev);
                marker.setPopupContent('<b>Ubicación Seleccionada</b><br>Latitud: ' + latPrev.toFixed(6) + '<br>Longitud: ' + lngPrev.toFixed(6));
                marker.openPopup();
            <?php endif; ?>
        });
    </script>
</body>
</html>
