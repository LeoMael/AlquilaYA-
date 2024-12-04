<?php
// arrendador/add_room.php
include '../includes/db_connect.php';
session_start();

// Verificar si el usuario ha iniciado sesión y es un arrendador
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] != 'arrendador') {
    header("Location: ../login.php");
    exit();
}

$id_arrendador = $_SESSION['id_arrendador'];

// Verificar que la casa pertenece al arrendador
if (isset($_GET['id_casa'])) {
    $id_casa = (int)$_GET['id_casa'];
} else {
    header("Location: dashboard.php");
    exit();
}

$sql_casa = "SELECT * FROM casas WHERE id_casa = ? AND id_arrendador = ?";
$stmt_casa = $conn->prepare($sql_casa);
$stmt_casa->bind_param("ii", $id_casa, $id_arrendador);
$stmt_casa->execute();
$result_casa = $stmt_casa->get_result();

if ($result_casa->num_rows == 0) {
    echo "No tienes permiso para agregar cuartos a esta casa.";
    exit();
}

$error = '';
$success = '';

// Procesar el formulario de agregar cuarto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_cuarto'])) {
    // Sanitizar y validar los datos del formulario
    $piso = trim($_POST['piso']);
    $precio = trim($_POST['precio']);
    $metroscuadrados = trim($_POST['metroscuadrados']);
    $descripcion = trim($_POST['descripcion']);

    // Validaciones básicas
    if (empty($piso) || empty($precio)) {
        $error = "Los campos piso y precio son obligatorios.";
    } elseif (!is_numeric($piso) || !is_numeric($precio) || ($metroscuadrados !== '' && !is_numeric($metroscuadrados))) {
        $error = "Por favor, ingresa valores numéricos válidos en los campos correspondientes.";
    } else {
        // Manejo de la imagen
        $nombre_imagen = null;
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
                    }
                }
            } else {
                $error = "Error al subir la imagen.";
            }
        }

        // Si no hay errores hasta ahora, insertar en la base de datos
        if (empty($error)) {
            if ($nombre_imagen) {
                // Insertar el cuarto con imagen
                $sql_insert = "INSERT INTO cuartos (id_casa, piso, precio, metroscuadrados, imagen) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("iidds", $id_casa, $piso, $precio, $metroscuadrados, $nombre_imagen);
            } else {
                // Insertar el cuarto sin imagen
                $sql_insert = "INSERT INTO cuartos (id_casa, piso, precio, metroscuadrados) VALUES (?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("iidi", $id_casa, $piso, $precio, $metroscuadrados);
            }

            if ($stmt_insert->execute()) {
                $id_cuarto = $stmt_insert->insert_id;

                // Insertar la descripción en la tabla detalles_cuartos si no está vacía
                if (!empty($descripcion)) {
                    $sql_detalle = "INSERT INTO detalles_cuartos (id_cuarto, descripcion) VALUES (?, ?)";
                    $stmt_detalle = $conn->prepare($sql_detalle);
                    $stmt_detalle->bind_param("is", $id_cuarto, $descripcion);
                    $stmt_detalle->execute();
                }

                $success = "Cuarto agregado exitosamente.";
                // Limpiar los campos del formulario
                $_POST = [];
            } else {
                $error = "Error al agregar el cuarto: " . htmlspecialchars($stmt_insert->error);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Cuarto - Arrendador - AlquilaYA!</title>
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
        .add-room-container {
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
    <div class="container add-room-container">
        <h2 class="mb-4 text-center"><i class="fas fa-plus-circle me-2 text-success"></i>Agregar Nuevo Cuarto</h2>

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

        <!-- Formulario de Agregar Cuarto -->
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="imagen" class="form-label"><i class="fas fa-image me-2 text-primary"></i>Imagen del Cuarto:</label>
                <input type="file" name="imagen" id="imagen" class="form-control" accept="image/*" <?php echo isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE ? 'required' : ''; ?>>
            </div>
            <div class="mb-3">
                <label for="piso" class="form-label"><i class="fas fa-layer-group me-2 text-primary"></i>Piso:</label>
                <input type="number" name="piso" id="piso" class="form-control" placeholder="Ingresa el piso" value="<?php echo isset($_POST['piso']) ? htmlspecialchars($_POST['piso']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="precio" class="form-label"><i class="fas fa-dollar-sign me-2 text-primary"></i>Precio (S/):</label>
                <input type="number" name="precio" id="precio" class="form-control" placeholder="Ingresa el precio" step="0.01" value="<?php echo isset($_POST['precio']) ? htmlspecialchars($_POST['precio']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="metroscuadrados" class="form-label"><i class="fas fa-ruler-combined me-2 text-primary"></i>Metros Cuadrados:</label>
                <input type="number" name="metroscuadrados" id="metroscuadrados" class="form-control" placeholder="Ingresa los metros cuadrados" value="<?php echo isset($_POST['metroscuadrados']) ? htmlspecialchars($_POST['metroscuadrados']) : ''; ?>">
            </div>
            <div class="mb-4">
                <label for="descripcion" class="form-label"><i class="fas fa-info-circle me-2 text-primary"></i>Descripción:</label>
                <textarea name="descripcion" id="descripcion" class="form-control" rows="4" placeholder="Ingresa una descripción del cuarto"><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
            </div>
            <button type="submit" name="agregar_cuarto" class="btn btn-primary w-100"><i class="fas fa-plus me-2"></i>Agregar Cuarto</button>
            <a href="dashboard.php" class="btn btn-secondary w-100 mt-2"><i class="fas fa-times me-2"></i>Cancelar</a>
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
