<?php
// estudiante/edit_comment.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/db_connect.php';
session_start();

// Verificar si el usuario ha iniciado sesión y es un estudiante
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] != 'estudiante') {
    header("Location: ../login.php");
    exit();
}

$id_estudiante = $_SESSION['id_estudiante'];

// Verificar que se hayan recibido los parámetros 'tipo' e 'id' en la URL
if (isset($_GET['tipo'], $_GET['id'])) {
    $tipo = $_GET['tipo'];
    $id_valoracion = (int)$_GET['id'];
} else {
    header("Location: profile.php");
    exit();
}

// Validar el valor de 'tipo'
if ($tipo != 'casa' && $tipo != 'cuarto') {
    echo "Tipo de comentario no válido.";
    exit();
}

// Obtener el comentario
if ($tipo == 'casa') {
    $sql_comentario = "SELECT * FROM valoracion_casas WHERE id_valoracion_casa = ? AND id_estudiante = ?";
} else {
    $sql_comentario = "SELECT * FROM valoracion_cuartos WHERE id_valoracion_cuarto = ? AND id_estudiante = ?";
}

$stmt = $conn->prepare($sql_comentario);
if (!$stmt) {
    die("Error en la preparación de la consulta: " . $conn->error);
}

$stmt->bind_param("ii", $id_valoracion, $id_estudiante);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $comentario = $result->fetch_assoc();
} else {
    echo "Comentario no encontrado o no tienes permiso para editarlo.";
    exit();
}

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_comentario'])) {
    $nueva_calificacion = $_POST['calificacion'];
    $nuevo_comentario = $_POST['comentario'];

    // Validar los datos
    if (!in_array($nueva_calificacion, [1, 2, 3, 4, 5])) {
        $error = "Calificación no válida.";
    } elseif (empty($nuevo_comentario)) {
        $error = "El comentario no puede estar vacío.";
    } else {
        // Actualizar el comentario en la base de datos
        if ($tipo == 'casa') {
            $sql_update = "UPDATE valoracion_casas SET calificacion = ?, comentario = ? WHERE id_valoracion_casa = ? AND id_estudiante = ?";
        } else {
            $sql_update = "UPDATE valoracion_cuartos SET calificacion = ?, comentario = ? WHERE id_valoracion_cuarto = ? AND id_estudiante = ?";
        }

        $stmt_update = $conn->prepare($sql_update);
        if (!$stmt_update) {
            die("Error en la preparación de la consulta: " . $conn->error);
        }

        $stmt_update->bind_param("isii", $nueva_calificacion, $nuevo_comentario, $id_valoracion, $id_estudiante);

        if ($stmt_update->execute()) {
            header("Location: profile.php");
            exit();
        } else {
            $error = "Error al actualizar el comentario.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Comentario - AlquilaYA!</title>
    <!-- Enlaces a CSS de Bootstrap -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Tu archivo de estilos personalizado -->
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="../index.php">AlquilaYA!</a>
        <div class="navbar-nav">
            <a class="nav-item nav-link" href="profile.php">Mi Perfil</a>
            <a class="nav-item nav-link" href="../logout.php">Cerrar Sesión</a>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container mt-4">
        <h1>Editar Comentario</h1>
        <?php if (isset($error)) { echo '<div class="alert alert-danger">'.$error.'</div>'; } ?>
        <form action="" method="POST">
            <div class="form-group">
                <label for="calificacion">Calificación:</label>
                <select name="calificacion" class="form-control" required>
                    <option value="5" <?php if ($comentario['calificacion'] == 5) echo 'selected'; ?>>5 estrellas</option>
                    <option value="4" <?php if ($comentario['calificacion'] == 4) echo 'selected'; ?>>4 estrellas</option>
                    <option value="3" <?php if ($comentario['calificacion'] == 3) echo 'selected'; ?>>3 estrellas</option>
                    <option value="2" <?php if ($comentario['calificacion'] == 2) echo 'selected'; ?>>2 estrellas</option>
                    <option value="1" <?php if ($comentario['calificacion'] == 1) echo 'selected'; ?>>1 estrella</option>
                </select>
            </div>
            <div class="form-group">
                <label for="comentario">Comentario:</label>
                <textarea name="comentario" class="form-control" rows="4" required><?php echo htmlspecialchars($comentario['comentario']); ?></textarea>
            </div>
            <button type="submit" name="editar_comentario" class="btn btn-primary">Guardar Cambios</button>
            <a href="profile.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>

    <!-- Scripts de Bootstrap y jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <!-- Popper.js necesario para Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
