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

// Procesar el formulario de agregar casa
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_casa'])) {
    $direccion = $_POST['direccion'];
    $descripcion = $_POST['descripcion'];
    $latitud = $_POST['latitud'];
    $longitud = $_POST['longitud'];
    $imagen = $_FILES['imagen'];
    if ($imagen['error'] === UPLOAD_ERR_OK) {
        $nombre_temporal = $imagen['tmp_name'];
        $nombre_imagen = uniqid('casa_') . '.' . pathinfo($imagen['name'], PATHINFO_EXTENSION);
        $ruta_destino = '../uploads/' . $nombre_imagen;

        // Validar el tipo de archivo (opcional pero recomendable)
        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
        $extension = strtolower(pathinfo($imagen['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $extensiones_permitidas)) {
            $error = "Formato de imagen no permitido.";
        } else {
            // Mover el archivo a la carpeta 'uploads'
            if (move_uploaded_file($nombre_temporal, $ruta_destino)) {
                // Insertar la casa en la base de datos con la imagen
                $sql_insert = "INSERT INTO casas (id_arrendador, direccion, descripcion, latitud, longitud, imagen) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("issdds", $id_arrendador, $direccion, $descripcion, $latitud, $longitud, $nombre_imagen);

                if ($stmt_insert->execute()) {
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Error al agregar la casa: " . $stmt_insert->error;
                }
            } else {
                $error = "Error al mover la imagen.";
            }
        }
    } else {
        $error = "Error al subir la imagen.";
    }

    // Validar los datos (puedes agregar más validaciones)
    if (empty($direccion)) {
        $error = "La dirección es obligatoria.";
    } else {
        // Insertar la casa en la base de datos
        $sql_insert = "INSERT INTO casas (id_arrendador, direccion, descripcion, latitud, longitud) VALUES (?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("issdd", $id_arrendador, $direccion, $descripcion, $latitud, $longitud);

        if ($stmt_insert->execute()) {
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Error al agregar la casa.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Casa - Arrendador - AlquilaYA!</title>
    <!-- Enlaces a CSS de Bootstrap -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Leaflet CSS para mapas -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="../index.php">AlquilaYA!</a>
        <div class="navbar-nav">
            <a class="nav-item nav-link" href="dashboard.php">Panel de Control</a>
            <a class="nav-item nav-link" href="profile.php">Mi Perfil</a>
            <a class="nav-item nav-link" href="../logout.php">Cerrar Sesión</a>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container mt-4">
        <h1>Agregar Nueva Casa</h1>
        <?php if (isset($error)) { echo '<div class="alert alert-danger">'.$error.'</div>'; } ?>
        <form action="" method="POST">
            <div class="form-group">
                <label for="imagen">Imagen de la Casa:</label>
                <input type="file" name="imagen" class="form-control" accept="image/*" required>
            </div>
            <div class="form-group">
                <label for="direccion">Dirección:</label>
                <input type="text" name="direccion" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción:</label>
                <textarea name="descripcion" class="form-control" rows="4"></textarea>
            </div>
            <!-- Mapa para seleccionar ubicación -->
            <div class="form-group">
                <label for="ubicacion">Ubicación:</label>
                <div id="map" style="height: 400px;"></div>
                <input type="hidden" name="latitud" id="latitud">
                <input type="hidden" name="longitud" id="longitud">
            </div>
            <button type="submit" name="agregar_casa" class="btn btn-success">Agregar Casa</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>

    <!-- Scripts de Bootstrap y jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Popper.js necesario para Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Leaflet JS para mapas -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <!-- Script para manejar el mapa -->
    <script>
        var map = L.map('map').setView([-15.840221, -70.021881], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var marker;

        map.on('click', function(e) {
            var lat = e.latlng.lat;
            var lng = e.latlng.lng;

            if (marker) {
                map.removeLayer(marker);
            }

            marker = L.marker([lat, lng]).addTo(map);
            $('#latitud').val(lat);
            $('#longitud').val(lng);
        });
    </script>
</body>
</html>
