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
    echo "No tienes permiso para agregar cuartos a esta casa.";
    exit();
}

// Procesar el formulario de agregar cuarto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_cuarto'])) {
    $piso = $_POST['piso'];
    $precio = $_POST['precio'];
    $metroscuadrados = $_POST['metroscuadrados'];
    $descripcion = $_POST['descripcion'];
    $imagen = $_FILES['imagen'];
    if ($imagen['error'] === UPLOAD_ERR_OK) {
        $nombre_temporal = $imagen['tmp_name'];
        $nombre_imagen = uniqid('cuarto_') . '.' . pathinfo($imagen['name'], PATHINFO_EXTENSION);
        $ruta_destino = '../uploads/' . $nombre_imagen;

        // Validar el tipo de archivo
        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
        $extension = strtolower(pathinfo($imagen['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $extensiones_permitidas)) {
            $error = "Formato de imagen no permitido.";
        } else {
            // Mover el archivo a la carpeta 'uploads'
            if (move_uploaded_file($nombre_temporal, $ruta_destino)) {
                // Insertar el cuarto en la base de datos con la imagen
                $sql_insert = "INSERT INTO cuartos (id_casa, piso, precio, metroscuadrados, imagen) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("iidds", $id_casa, $piso, $precio, $metroscuadrados, $nombre_imagen);

                if ($stmt_insert->execute()) {
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Error al agregar el cuarto.";
                }
            } else {
                $error = "Error al mover la imagen.";
            }
        }
    } else {
        $error = "Error al subir la imagen.";
    }

    // Validar los datos (puedes agregar más validaciones)
    if (empty($piso) || empty($precio)) {
        $error = "Los campos piso y precio son obligatorios.";
    } else {
        // Insertar el cuarto en la base de datos
        $sql_insert = "INSERT INTO cuartos (id_casa, piso, precio, metroscuadrados) VALUES (?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("iidi", $id_casa, $piso, $precio, $metroscuadrados);

        if ($stmt_insert->execute()) {
            $id_cuarto = $stmt_insert->insert_id;
            // Insertar la descripción en la tabla detalles_cuartos
            $sql_detalle = "INSERT INTO detalles_cuartos (id_cuarto, descripcion) VALUES (?, ?)";
            $stmt_detalle = $conn->prepare($sql_detalle);
            $stmt_detalle->bind_param("is", $id_cuarto, $descripcion);
            $stmt_detalle->execute();

            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Error al agregar el cuarto.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Cuarto - Arrendador - AlquilaYA!</title>
    <!-- Enlaces a CSS de Bootstrap -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
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
        <h1>Agregar Nuevo Cuarto</h1>
        <?php if (isset($error)) { echo '<div class="alert alert-danger">'.$error.'</div>'; } ?>
        <form action="" method="POST">
            <div class="form-group">
                <label for="imagen">Imagen del Cuarto:</label>
                <input type="file" name="imagen" class="form-control" accept="image/*" required>
            </div>
            <div class="form-group">
                <label for="piso">Piso:</label>
                <input type="number" name="piso" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="precio">Precio:</label>
                <input type="number" name="precio" class="form-control" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="metroscuadrados">Metros Cuadrados:</label>
                <input type="number" name="metroscuadrados" class="form-control">
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción:</label>
                <textarea name="descripcion" class="form-control" rows="4"></textarea>
            </div>
            <button type="submit" name="agregar_cuarto" class="btn btn-success">Agregar Cuarto</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>

    <!-- Scripts de Bootstrap y jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Popper.js necesario para Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
