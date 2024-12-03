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

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_casa'])) {
    $direccion = $_POST['direccion'];
    $descripcion = $_POST['descripcion'];
    $latitud = $_POST['latitud'];
    $longitud = $_POST['longitud'];

    // Manejar la imagen si se ha subido una nueva
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $imagen = $_FILES['imagen'];
        $nombre_temporal = $imagen['tmp_name'];
        $nombre_imagen = uniqid('casa_') . '.' . pathinfo($imagen['name'], PATHINFO_EXTENSION);
        $ruta_destino = '../uploads/' . $nombre_imagen;

        // Validar el tipo de archivo
        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
        $extension = strtolower(pathinfo($imagen['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $extensiones_permitidas)) {
            $error = "Formato de imagen no permitido.";
        } else {
            if (move_uploaded_file($nombre_temporal, $ruta_destino)) {
                // Eliminar la imagen anterior si existe
                if (!empty($casa['imagen']) && file_exists('../uploads/' . $casa['imagen'])) {
                    unlink('../uploads/' . $casa['imagen']);
                }
                $imagen_casa = $nombre_imagen;
            } else {
                $error = "Error al mover la imagen.";
            }
        }
    } else {
        $imagen_casa = $casa['imagen'];
    }

    if (!isset($error)) {
        // Actualizar la casa en la base de datos
        $sql_update = "UPDATE casas SET direccion = ?, descripcion = ?, latitud = ?, longitud = ?, imagen = ? WHERE id_casa = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ssddsi", $direccion, $descripcion, $latitud, $longitud, $imagen_casa, $id_casa);

        if ($stmt_update->execute()) {
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Error al actualizar la casa: " . $stmt_update->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Casa - Arrendador - AlquilaYA!</title>
    <!-- Enlaces a CSS de Bootstrap -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Leaflet CSS para mapas -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <!-- Navegación -->
    <!-- ... código de navegación ... -->

    <!-- Contenido Principal -->
    <div class="container mt-4">
        <h1>Editar Casa</h1>
        <?php if (isset($error)) { echo '<div class="alert alert-danger">'.$error.'</div>'; } ?>
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="direccion">Dirección:</label>
                <input type="text" name="direccion" class="form-control" value="<?php echo htmlspecialchars($casa['direccion']); ?>" required>
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción:</label>
                <textarea name="descripcion" class="form-control" rows="4"><?php echo htmlspecialchars($casa['descripcion']); ?></textarea>
            </div>
            <!-- Mapa para seleccionar ubicación -->
            <div class="form-group">
                <label for="ubicacion">Ubicación:</label>
                <div id="map" style="height: 400px;"></div>
                <input type="hidden" name="latitud" id="latitud" value="<?php echo $casa['latitud']; ?>">
                <input type="hidden" name="longitud" id="longitud" value="<?php echo $casa['longitud']; ?>">
            </div>
            <!-- Mostrar imagen actual -->
            <?php if (!empty($casa['imagen'])): ?>
                <div class="form-group">
                    <label>Imagen Actual:</label><br>
                    <img src="../uploads/<?php echo htmlspecialchars($casa['imagen']); ?>" alt="Imagen de la Casa" class="img-fluid mb-2" style="max-width: 200px;">
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="imagen">Cambiar Imagen (opcional):</label>
                <input type="file" name="imagen" class="form-control" accept="image/*">
            </div>
            <button type="submit" name="editar_casa" class="btn btn-primary">Guardar Cambios</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>

    <!-- Scripts de Bootstrap y jQuery -->
    <!-- ... scripts ... -->
    <!-- Leaflet JS para mapas -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <!-- Script para manejar el mapa -->
    <script>
        var latitud = <?php echo $casa['latitud']; ?>;
        var longitud = <?php echo $casa['longitud']; ?>;
        var map = L.map('map').setView([latitud, longitud], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var marker = L.marker([latitud, longitud], {draggable: true}).addTo(map);

        marker.on('dragend', function(e) {
            var coords = marker.getLatLng();
            $('#latitud').val(coords.lat);
            $('#longitud').val(coords.lng);
        });
    </script>
</body>
</html>
