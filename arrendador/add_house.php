<?php
// arrendador/add_house.php
include '../includes/db_connect.php';
session_start();

// Verificar si el usuario ha iniciado sesión y es un arrendador
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] != 'arrendador') {
    header("Location: ../login.php");
    exit();
}

$id_arrendador = $_SESSION['id_arrendador'];
$error = '';
$success = '';

// Procesar el formulario de agregar casa
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_casa'])) {
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
        $nombre_imagen = null;
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
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
                }
            }
        } elseif (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
            $error = "Error al subir la imagen.";
        }

        // Si no hay errores hasta ahora, insertar en la base de datos
        if (empty($error)) {
            if ($nombre_imagen) {
                $sql_insert = "INSERT INTO casas (id_arrendador, direccion, descripcion, latitud, longitud, imagen) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("issdds", $id_arrendador, $direccion, $descripcion, $latitud, $longitud, $nombre_imagen);
            } else {
                $sql_insert = "INSERT INTO casas (id_arrendador, direccion, descripcion, latitud, longitud) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("issdd", $id_arrendador, $direccion, $descripcion, $latitud, $longitud);
            }

            if ($stmt_insert->execute()) {
                $success = "Casa agregada exitosamente.";
                // Limpiar los campos del formulario
                $_POST = [];
            } else {
                $error = "Error al agregar la casa: " . htmlspecialchars($stmt_insert->error);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Casa - Arrendador - AlquilaYA!</title>
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
        .add-house-container {
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
    <div class="add-house-container">
        <h2 class="mb-4 text-center"><i class="fas fa-plus-circle me-2 text-success"></i>Agregar Nueva Casa</h2>

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

        <!-- Formulario de Agregar Casa -->
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="imagen" class="form-label"><i class="fas fa-image me-2 text-primary"></i>Imagen de la Casa:</label>
                <input type="file" name="imagen" id="imagen" class="form-control" accept="image/*" required>
            </div>
            <div class="mb-3">
                <label for="direccion" class="form-label"><i class="fas fa-map-marker-alt me-2 text-primary"></i>Dirección:</label>
                <input type="text" name="direccion" id="direccion" class="form-control" placeholder="Ingresa la dirección de la casa" value="<?php echo isset($_POST['direccion']) ? htmlspecialchars($_POST['direccion']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="descripcion" class="form-label"><i class="fas fa-info-circle me-2 text-primary"></i>Descripción:</label>
                <textarea name="descripcion" id="descripcion" class="form-control" rows="4" placeholder="Ingresa una descripción de la casa"><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
            </div>
            <div class="mb-4">
                <label for="ubicacion" class="form-label"><i class="fas fa-map me-2 text-primary"></i>Ubicación:</label>
                <div id="map" class="map-container mb-3"></div>
                <input type="hidden" name="latitud" id="latitud" value="<?php echo isset($_POST['latitud']) ? htmlspecialchars($_POST['latitud']) : ''; ?>">
                <input type="hidden" name="longitud" id="longitud" value="<?php echo isset($_POST['longitud']) ? htmlspecialchars($_POST['longitud']) : ''; ?>">
                <small class="text-muted">Haz clic en el mapa para seleccionar la ubicación de la casa.</small>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" name="agregar_casa" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Agregar Casa</button>
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
        // Inicializar el mapa
        var map = L.map('map').setView([-15.840221, -70.021881], 13); // Coordenadas por defecto (Puno)

        // Añadir capa de mapa
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var marker;

        // Función para actualizar los campos de latitud y longitud
        function actualizarCoordenadas(lat, lng) {
            document.getElementById('latitud').value = lat;
            document.getElementById('longitud').value = lng;
        }

        // Evento de clic en el mapa
        map.on('click', function(e) {
            var lat = e.latlng.lat;
            var lng = e.latlng.lng;

            // Si ya hay un marcador, eliminarlo
            if (marker) {
                map.removeLayer(marker);
            }

            // Añadir nuevo marcador
            marker = L.marker([lat, lng]).addTo(map)
                .bindPopup('<b>Ubicación Seleccionada</b><br>Latitud: ' + lat.toFixed(6) + '<br>Longitud: ' + lng.toFixed(6))
                .openPopup();

            // Actualizar los campos ocultos
            actualizarCoordenadas(lat, lng);
        });

        // Si hay coordenadas previamente seleccionadas (por ejemplo, en caso de errores de validación), centrar el mapa y añadir el marcador
        <?php if (isset($_POST['latitud']) && isset($_POST['longitud']) && is_numeric($_POST['latitud']) && is_numeric($_POST['longitud'])): ?>
            var latPrev = <?php echo htmlspecialchars($_POST['latitud']); ?>;
            var lngPrev = <?php echo htmlspecialchars($_POST['longitud']); ?>;
            map.setView([latPrev, lngPrev], 15);
            marker = L.marker([latPrev, lngPrev]).addTo(map)
                .bindPopup('<b>Ubicación Seleccionada</b><br>Latitud: ' + latPrev.toFixed(6) + '<br>Longitud: ' + lngPrev.toFixed(6))
                .openPopup();
        <?php endif; ?>
    </script>
</body>
</html>
