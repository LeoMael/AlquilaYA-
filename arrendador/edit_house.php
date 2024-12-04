<?php
// arrendador/edit_house.php
include '../includes/db_connect.php';
session_start();

// Verificar si el usuario ha iniciado sesión y es un arrendador
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] != 'arrendador') {
    header("Location: ../login.php");
    exit();
}

$id_arrendador = $_SESSION['id_arrendador'];

// Obtener id_casa desde la URL
if (isset($_GET['id_casa'])) {
    $id_casa = (int)$_GET['id_casa'];
} else {
    header("Location: dashboard.php");
    exit();
}

// Verificar que la casa pertenece al arrendador
$sql_casa = "SELECT * FROM casas WHERE id_casa = ? AND id_arrendador = ?";
$stmt_casa = $conn->prepare($sql_casa);
$stmt_casa->bind_param("ii", $id_casa, $id_arrendador);
$stmt_casa->execute();
$result_casa = $stmt_casa->get_result();

if ($result_casa->num_rows == 0) {
    echo "No tienes permiso para editar esta casa.";
    exit();
} else {
    $casa = $result_casa->fetch_assoc();
}

// Inicializar variables de error y éxito
$error = '';
$success = '';

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_casa'])) {
    // Sanitizar y validar los datos del formulario
    $direccion = trim($_POST['direccion']);
    $descripcion = trim($_POST['descripcion']);
    $latitud = trim($_POST['latitud']);
    $longitud = trim($_POST['longitud']);

    // Validaciones básicas
    if (empty($direccion)) {
        $error = "La dirección es obligatoria.";
    } elseif (!is_numeric($latitud) || !is_numeric($longitud)) {
        $error = "Las coordenadas de ubicación son inválidas.";
    } else {
        // Manejo de la imagen
        $nombre_imagen = $casa['imagen']; // Mantener la imagen actual por defecto
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $imagen = $_FILES['imagen'];
                $nombre_temporal = $imagen['tmp_name'];
                $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
                $extension = strtolower(pathinfo($imagen['name'], PATHINFO_EXTENSION));

                if (!in_array($extension, $extensiones_permitidas)) {
                    $error = "Formato de imagen no permitido. Permisos: jpg, jpeg, png, gif.";
                } else {
                    $nombre_imagen = uniqid('casa_') . '.' . $extension;
                    $ruta_destino = '../uploads/' . $nombre_imagen;

                    if (!move_uploaded_file($nombre_temporal, $ruta_destino)) {
                        $error = "Error al subir la imagen.";
                    } else {
                        // Eliminar la imagen anterior si existe
                        if (!empty($casa['imagen']) && file_exists('../uploads/' . $casa['imagen'])) {
                            unlink('../uploads/' . $casa['imagen']);
                        }
                    }
                }
            } else {
                $error = "Error al subir la imagen.";
            }
        }

        // Si no hay errores hasta ahora, actualizar en la base de datos
        if (empty($error)) {
            $sql_update = "UPDATE casas SET direccion = ?, descripcion = ?, latitud = ?, longitud = ?, imagen = ? WHERE id_casa = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ssddsi", $direccion, $descripcion, $latitud, $longitud, $nombre_imagen, $id_casa);

            if ($stmt_update->execute()) {
                $success = "Casa actualizada exitosamente.";
                // Actualizar los datos de $casa para reflejar los cambios
                $casa['direccion'] = $direccion;
                $casa['descripcion'] = $descripcion;
                $casa['latitud'] = $latitud;
                $casa['longitud'] = $longitud;
                $casa['imagen'] = $nombre_imagen;
            } else {
                $error = "Error al actualizar la casa: " . htmlspecialchars($stmt_update->error);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Casa - Arrendador - AlquilaYA!</title>
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
        .edit-house-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            max-width: 800px;
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
    <div class="container edit-house-container">
        <h2 class="mb-4 text-center"><i class="fas fa-edit me-2 text-success"></i>Editar Casa</h2>

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

        <!-- Formulario de Edición de Casa -->
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="imagen" class="form-label"><i class="fas fa-image me-2 text-primary"></i>Imagen de la Casa:</label>
                <?php if (!empty($casa['imagen'])): ?>
                    <div class="mb-2">
                        <img src="../uploads/<?php echo htmlspecialchars($casa['imagen']); ?>" alt="Imagen de la Casa" class="img-fluid current-image rounded">
                    </div>
                <?php endif; ?>
                <input type="file" name="imagen" id="imagen" class="form-control" accept="image/*">
                <small class="form-text text-muted">Sube una nueva imagen para reemplazar la actual (opcional).</small>
            </div>
            <div class="mb-3">
                <label for="direccion" class="form-label"><i class="fas fa-map-marker-alt me-2 text-primary"></i>Dirección:</label>
                <input type="text" name="direccion" id="direccion" class="form-control" placeholder="Ingresa la dirección de la casa" value="<?php echo htmlspecialchars($casa['direccion']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="descripcion" class="form-label"><i class="fas fa-info-circle me-2 text-primary"></i>Descripción:</label>
                <textarea name="descripcion" id="descripcion" class="form-control" rows="4" placeholder="Ingresa una descripción de la casa"><?php echo htmlspecialchars($casa['descripcion']); ?></textarea>
            </div>
            <div class="mb-4">
                <label for="ubicacion" class="form-label"><i class="fas fa-map me-2 text-primary"></i>Ubicación:</label>
                <div id="map" class="map-container mb-3"></div>
                <input type="hidden" name="latitud" id="latitud" value="<?php echo htmlspecialchars($casa['latitud']); ?>">
                <input type="hidden" name="longitud" id="longitud" value="<?php echo htmlspecialchars($casa['longitud']); ?>">
                <small class="text-muted">Haz clic en el mapa para seleccionar la ubicación de la casa.</small>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" name="editar_casa" class="btn btn-primary"><i class="fas fa-save me-2"></i>Guardar Cambios</button>
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
        // Función para centrar el mapa en coordenadas específicas
        function centrarMapa(lat, lon) {
            map.setView([lat, lon], 15); // Ajusta el zoom según lo necesites
        }

        var latitud = <?php echo htmlspecialchars($casa['latitud']); ?>;
        var longitud = <?php echo htmlspecialchars($casa['longitud']); ?>;
        var map = L.map('map').setView([latitud, longitud], 13);

        // Añadir capa de mapa
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var marker = L.marker([latitud, longitud], { draggable: true }).addTo(map)
            .bindPopup('<b>Ubicación Seleccionada</b><br>Latitud: ' + latitud.toFixed(6) + '<br>Longitud: ' + longitud.toFixed(6))
            .openPopup();

        // Función para actualizar los campos de latitud y longitud
        function actualizarCoordenadas(lat, lng) {
            document.getElementById('latitud').value = lat;
            document.getElementById('longitud').value = lng;
        }

        // Evento de arrastrar el marcador
        marker.on('dragend', function(e) {
            var coords = marker.getLatLng();
            actualizarCoordenadas(coords.lat, coords.lng);
            marker.setPopupContent('<b>Ubicación Seleccionada</b><br>Latitud: ' + coords.lat.toFixed(6) + '<br>Longitud: ' + coords.lng.toFixed(6));
            marker.openPopup();
        });

        // Evento de clic en el mapa para mover el marcador
        map.on('click', function(e) {
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
    </script>
</body>
</html>
