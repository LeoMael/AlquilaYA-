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

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_cuarto'])) {
    $piso = $_POST['piso'];
    $precio = $_POST['precio'];
    $metroscuadrados = $_POST['metroscuadrados'];
    $descripcion = $_POST['descripcion'];
    $estado = $_POST['estado']; // Nuevo campo para el estado

    // Manejar la imagen si se ha subido una nueva
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $imagen = $_FILES['imagen'];
        $nombre_temporal = $imagen['tmp_name'];
        $nombre_imagen = uniqid('cuarto_') . '.' . pathinfo($imagen['name'], PATHINFO_EXTENSION);
        $ruta_destino = '../uploads/' . $nombre_imagen;

        // Validar el tipo de archivo
        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
        $extension = strtolower(pathinfo($imagen['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $extensiones_permitidas)) {
            $error = "Formato de imagen no permitido.";
        } else {
            if (move_uploaded_file($nombre_temporal, $ruta_destino)) {
                // Eliminar la imagen anterior si existe
                if (!empty($cuarto['imagen']) && file_exists('../uploads/' . $cuarto['imagen'])) {
                    unlink('../uploads/' . $cuarto['imagen']);
                }
                $imagen_cuarto = $nombre_imagen;
            } else {
                $error = "Error al mover la imagen.";
            }
        }
    } else {
        $imagen_cuarto = $cuarto['imagen']; // Mantener la imagen existente
    }

    if (!isset($error)) {
        // Iniciar una transacción para asegurar la integridad de los datos
        $conn->begin_transaction();

        try {
            // Actualizar el cuarto en la base de datos
            $sql_update_cuarto = "UPDATE cuartos SET piso = ?, precio = ?, metroscuadrados = ?, imagen = ?, id_estado = ? WHERE id_cuarto = ?";
            $stmt_update_cuarto = $conn->prepare($sql_update_cuarto);
            $stmt_update_cuarto->bind_param("iidisi", $piso, $precio, $metroscuadrados, $imagen_cuarto, $estado, $id_cuarto);

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Cuarto - Arrendador - AlquilaYA!</title>
    <!-- Enlaces a CSS de Bootstrap -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Leaflet CSS para mapas (si es necesario) -->
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
        <h1>Editar Cuarto</h1>
        <?php 
        if (isset($error)) { 
            echo '<div class="alert alert-danger">'.$error.'</div>'; 
        } 
        if (isset($_SESSION['success_message'])) { 
            echo '<div class="alert alert-success">'.$_SESSION['success_message'].'</div>'; 
            unset($_SESSION['success_message']);
        } 
        ?>
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="piso">Piso:</label>
                <input type="number" name="piso" class="form-control" value="<?php echo htmlspecialchars($cuarto['piso']); ?>" required>
            </div>
            <div class="form-group">
                <label for="precio">Precio:</label>
                <input type="number" name="precio" class="form-control" step="0.01" value="<?php echo htmlspecialchars($cuarto['precio']); ?>" required>
            </div>
            <div class="form-group">
                <label for="metroscuadrados">Metros Cuadrados:</label>
                <input type="number" name="metroscuadrados" class="form-control" value="<?php echo htmlspecialchars($cuarto['metroscuadrados']); ?>">
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción:</label>
                <textarea name="descripcion" class="form-control" rows="4"><?php echo htmlspecialchars($descripcion_cuarto); ?></textarea>
            </div>
            <div class="form-group">
                <label for="estado">Estado del Cuarto:</label>
                <select name="estado" class="form-control" id="estado-select" required>
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
            <div class="form-group" id="estudiante-select-group" style="display: none;">
                <label for="id_estudiante">Asignar Estudiante:</label>
                <select name="id_estudiante" class="form-control">
                    <option value="">-- Seleccionar Estudiante --</option>
                    <?php
                    // Obtener la lista de estudiantes
                    $sql_estudiantes = "SELECT id_estudiante, nombre FROM estudiantes";
                    $result_estudiantes = $conn->query($sql_estudiantes);
                    while ($estudiante = $result_estudiantes->fetch_assoc()) {
                        echo '<option value="' . htmlspecialchars($estudiante['id_estudiante']) . '">' . htmlspecialchars($estudiante['nombre']) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- Mostrar imagen actual -->
            <?php if (!empty($cuarto['imagen']) && file_exists('../uploads/' . $cuarto['imagen'])): ?>
                <div class="form-group">
                    <label>Imagen Actual:</label><br>
                    <img src="../uploads/<?php echo htmlspecialchars($cuarto['imagen']); ?>" alt="Imagen del Cuarto" class="img-fluid mb-2" style="max-width: 200px;">
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="imagen">Cambiar Imagen (opcional):</label>
                <input type="file" name="imagen" class="form-control" accept="image/*">
            </div>
            <button type="submit" name="editar_cuarto" class="btn btn-primary">Guardar Cambios</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>

    <!-- Scripts de Bootstrap y jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Popper.js necesario para Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <!-- Script para mostrar u ocultar el campo de estudiante basado en el estado seleccionado -->
    <script>
        $(document).ready(function(){
            $('#estado-select').change(function(){
                var estadoSeleccionado = $(this).find('option:selected').text();
                if(estadoSeleccionado.toLowerCase() === 'no disponible') { // Ajusta según el texto exacto de tu estado
                    $('#estudiante-select-group').show();
                } else {
                    $('#estudiante-select-group').hide();
                }
            }).trigger('change'); // Activar el cambio al cargar la página
        });
    </script>
</body>
</html>
