<?php
// room_details.php
include 'includes/db_connect.php';
session_start();

// Obtener el id_cuarto de la URL
if (isset($_GET['id_cuarto'])) {
    $id_cuarto = (int)$_GET['id_cuarto'];
} else {
    // Redirigir al index si no se proporciona id_cuarto
    header("Location: index.php");
    exit();
}

// Obtener información del cuarto
$sql_cuarto = "SELECT cuartos.*, detalles_cuartos.descripcion, casas.direccion,
               arrendadores.nombre AS nombre_arrendador, arrendadores.celular, arrendadores.email,
               AVG(valoracion_cuartos.calificacion) AS calificacion_promedio,
               COUNT(valoracion_cuartos.calificacion) AS total_calificaciones
               FROM cuartos
               LEFT JOIN detalles_cuartos ON cuartos.id_cuarto = detalles_cuartos.id_cuarto
               LEFT JOIN casas ON cuartos.id_casa = casas.id_casa
               LEFT JOIN arrendadores ON casas.id_arrendador = arrendadores.id_arrendador
               LEFT JOIN valoracion_cuartos ON cuartos.id_cuarto = valoracion_cuartos.id_cuarto
               WHERE cuartos.id_cuarto = ?
               GROUP BY cuartos.id_cuarto";
$stmt_cuarto = $conn->prepare($sql_cuarto);
$stmt_cuarto->bind_param("i", $id_cuarto);
$stmt_cuarto->execute();
$result_cuarto = $stmt_cuarto->get_result();

if ($result_cuarto->num_rows > 0) {
    $cuarto = $result_cuarto->fetch_assoc();
} else {
    echo "Cuarto no encontrado.";
    exit();
}

// Obtener imágenes del cuarto
$sql_imagenes = "SELECT imagen FROM imagenes_cuartos WHERE id_cuarto = ?";
$stmt_imagenes = $conn->prepare($sql_imagenes);
$stmt_imagenes->bind_param("i", $id_cuarto);
$stmt_imagenes->execute();
$result_imagenes = $stmt_imagenes->get_result();

$imagenes = [];
while ($row = $result_imagenes->fetch_assoc()) {
    $imagenes[] = $row['imagen'];
}

// Obtener reseñas y calificaciones del cuarto
$sql_reseñas = "SELECT valoracion_cuartos.*, estudiantes.nombre
                FROM valoracion_cuartos
                JOIN estudiantes ON valoracion_cuartos.id_estudiante = estudiantes.id_estudiante
                WHERE valoracion_cuartos.id_cuarto = ?
                ORDER BY valoracion_cuartos.fecha DESC";
$stmt_reseñas = $conn->prepare($sql_reseñas);
$stmt_reseñas->bind_param("i", $id_cuarto);
$stmt_reseñas->execute();
$result_reseñas = $stmt_reseñas->get_result();

// Calcular porcentajes de calificaciones
$sql_calificaciones = "SELECT calificacion, COUNT(*) as cantidad
                       FROM valoracion_cuartos
                       WHERE id_cuarto = ?
                       GROUP BY calificacion";
$stmt_calificaciones = $conn->prepare($sql_calificaciones);
$stmt_calificaciones->bind_param("i", $id_cuarto);
$stmt_calificaciones->execute();
$result_calificaciones = $stmt_calificaciones->get_result();

$calificaciones = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$total_calificaciones = $cuarto['total_calificaciones'];

if ($total_calificaciones > 0) {
    while ($row = $result_calificaciones->fetch_assoc()) {
        $calificaciones[$row['calificacion']] = $row['cantidad'];
    }
}

// Procesar el formulario de agregar opinión
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_opinion'])) {
    if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] == 'estudiante') {
        $calificacion = $_POST['calificacion'];
        $comentario = $_POST['comentario'];
        $id_estudiante = $_SESSION['id_estudiante'];

        $sql_insert_opinion = "INSERT INTO valoracion_cuartos (id_cuarto, calificacion, comentario, fecha, id_estudiante) VALUES (?, ?, ?, NOW(), ?)";
        $stmt_insert = $conn->prepare($sql_insert_opinion);
        $stmt_insert->bind_param("iisi", $id_cuarto, $calificacion, $comentario, $id_estudiante);
        if ($stmt_insert->execute()) {
            // Recargar la página para mostrar la nueva opinión
            header("Location: room_details.php?id_cuarto=" . $id_cuarto);
            exit();
        } else {
            $error_opinion = "Error al agregar la opinión.";
        }
    } else {
        $error_opinion = "Debes iniciar sesión como estudiante para agregar una opinión.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalles del Cuarto - AlquilaYA!</title>
    <!-- Enlaces a CSS de Bootstrap para estilos rápidos -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Tu archivo de estilos personalizado -->
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* Estilos personalizados */
        .separator {
            border-top: 1px solid #ccc;
            margin: 20px 0;
        }
        .anfitrion-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ratings-summary {
            max-width: 300px;
        }
        .carousel-item img {
            height: 400px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="index.php">AlquilaYA!</a>
        <div class="navbar-nav">
            <?php
            if (isset($_SESSION['tipo_usuario'])) {
                if ($_SESSION['tipo_usuario'] == 'estudiante') {
                    echo '<a class="nav-item nav-link" href="estudiante/profile.php">Mi Perfil</a>';
                } elseif ($_SESSION['tipo_usuario'] == 'arrendador') {
                    echo '<a class="nav-item nav-link" href="arrendador/dashboard.php">Mi Panel</a>';
                }
                echo '<a class="nav-item nav-link" href="logout.php">Cerrar Sesión</a>';
            } else {
                echo '<a class="nav-item nav-link" href="login.php">Iniciar Sesión</a>';
                echo '<a class="nav-item nav-link" href="register.php">Registrarse</a>';
            }
            ?>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container mt-4">
        <!-- Carrusel de Imágenes del Cuarto -->
        <div id="carouselImages" class="carousel slide" data-ride="carousel">
            <div class="carousel-inner">
                <?php
                if (count($imagenes) > 0) {
                    $active = 'active';
                    foreach ($imagenes as $imagen) {
                        echo '<div class="carousel-item ' . $active . '">';
                        echo '<img src="uploads/' . $imagen . '" class="d-block w-100" alt="Imagen del cuarto">';
                        echo '</div>';
                        $active = ''; // Después del primer elemento, no necesitamos la clase 'active'
                    }
                } else {
                    echo '<div class="carousel-item active">';
                    echo '<img src="images/cuarto_default.jpg" class="d-block w-100" alt="Imagen del cuarto">';
                    echo '</div>';
                }
                ?>
            </div>
            <a class="carousel-control-prev" href="#carouselImages" role="button" data-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="sr-only">Anterior</span>
            </a>
            <a class="carousel-control-next" href="#carouselImages" role="button" data-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="sr-only">Siguiente</span>
            </a>
        </div>

        <!-- Dirección del Cuarto -->
        <h2 class="mt-3"><?php echo htmlspecialchars($cuarto['direccion']); ?></h2>

        <!-- Separador -->
        <div class="separator"></div>

        <!-- Nombre del Anfitrión y Botón "Contáctame" -->
        <div class="anfitrion-section">
            <h3>Anfitrión: <?php echo htmlspecialchars($cuarto['nombre_arrendador']); ?></h3>
            <?php
            // Suponiendo que el número de celular del arrendador está almacenado en $arrendador['celular']
            // Formateamos el número sin el '+' y con el formato internacional adecuado
            $telefono_arrendador = $cuarto['celular'];  // Asegúrate de obtener el número del arrendador

            // Si deseas agregar un mensaje predefinido al inicio de la conversación
            $mensaje = "Hola, estoy interesado en el cuarto con ID: $id_cuarto.";

            $whatsapp_url = "https://wa.me/$telefono_arrendador?text=" . urlencode($mensaje);
            ?>

            <!-- Botón para redirigir al WhatsApp -->
            <a href="<?php echo $whatsapp_url; ?>" class="btn btn-primary" target="_blank">Contáctame</a>
        </div>

        <!-- Descripción del Cuarto -->
        <div class="mt-3">
            <h4>Descripción del Cuarto</h4>
            <p><?php echo htmlspecialchars($cuarto['descripcion']); ?></p>
            <?php 
            // Asegúrate de que $cuarto['estado_cuarto'] contiene el nombre del estado (no el ID).
            $estado = isset($cuarto['estado_cuarto']) ? $cuarto['estado_cuarto'] : 'Disponible';

            echo '<p><strong>Estado: </strong>' . $estado . '</p>';
            ?>
            <?php
            // Suponiendo que $cuarto es el array que contiene el resultado de la consulta
            $celular_arrendador = $cuarto['celular'];  // Accedemos directamente a 'celular' que es el alias de la columna 'arrendadores.celular'

            echo '<p><strong>Celular del arrendador: </strong>' . $celular_arrendador . '</p>';
            ?>
            <?php
            // Suponiendo que $cuarto es el array que contiene el resultado de la consulta
            $email_arrendador = $cuarto['email'];  // Accedemos a 'email' de los resultados

            echo '<p><strong>Email del arrendador: </strong>' . $email_arrendador . '</p>';
            ?>

            
            <p><strong>Precio:</strong> S/.<?php echo $cuarto['precio']; ?></p>
            <p><strong>Tamaño:</strong> <?php echo $cuarto['metroscuadrados']; ?> m²</p>
        </div>

        <!-- Calificaciones y Comentarios -->
        <h2 class="mt-4">Opiniones del Cuarto</h2>
        <div class="row">
            <!-- Resumen de Calificaciones -->
            <div class="col-md-4 ratings-summary">
                <h3>Calificación General</h3>
                <?php $calificacion = isset($cuarto['calificacion_promedio']) ? number_format($cuarto['calificacion_promedio'], 1) : 'No disponible';
                echo '<p><strong>' . $calificacion . '</strong> de 5 estrellas</p>';?>

                <p><?php echo $total_calificaciones; ?> calificaciones en total</p>
                <?php
                for ($i = 5; $i >= 1; $i--) {
                    $porcentaje = ($total_calificaciones > 0) ? ($calificaciones[$i] / $total_calificaciones) * 100 : 0;
                    echo '<div class="d-flex align-items-center">';
                    echo '<span>' . $i . ' estrellas</span>';
                    echo '<div class="progress flex-grow-1 mx-2">';
                    echo '<div class="progress-bar" role="progressbar" style="width: ' . $porcentaje . '%;" aria-valuenow="' . $porcentaje . '" aria-valuemin="0" aria-valuemax="100"></div>';
                    echo '</div>';
                    echo '<span>' . $calificaciones[$i] . '</span>';
                    echo '</div>';
                }
                ?>
            </div>
            <!-- Comentarios -->
            <div class="col-md-8">
                <?php
                if ($result_reseñas->num_rows > 0) {
                    while($reseña = $result_reseñas->fetch_assoc()) {
                        echo '<div class="media mb-3">';
                        echo '<div class="media-body">';
                        echo '<h5 class="mt-0">' . htmlspecialchars($reseña['nombre']) . ' - ' . $reseña['calificacion'] . ' estrellas</h5>';
                        echo '<p>' . htmlspecialchars($reseña['comentario']); '</p>';
                        echo '<small class="text-muted">Fecha: ' . date('d/m/Y', strtotime($reseña['fecha'])) . '</small>';
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<p>Aún no hay opiniones para este cuarto.</p>';
                }
                ?>
            </div>
        </div>

        <?php
        // Si el estudiante ha iniciado sesión, puede agregar una opinión
        if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] == 'estudiante') {
            ?>
            <h3>Agregar Opinión</h3>
            <?php if (isset($error_opinion)) { echo '<div class="alert alert-danger">'.$error_opinion.'</div>'; } ?>
            <form action="" method="POST">
                <div class="form-group">
                    <label for="calificacion">Calificación:</label>
                    <select name="calificacion" class="form-control" required>
                        <option value="5">5 estrellas</option>
                        <option value="4">4 estrellas</option>
                        <option value="3">3 estrellas</option>
                        <option value="2">2 estrellas</option>
                        <option value="1">1 estrella</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="comentario">Comentario:</label>
                    <textarea name="comentario" class="form-control" rows="4" required></textarea>
                </div>
                <button type="submit" name="agregar_opinion" class="btn btn-primary">Enviar Opinión</button>
            </form>
            <?php
        } else {
            echo '<p>Inicia sesión como estudiante para agregar una opinión.</p>';
        }
        ?>
    </div>

    <!-- Scripts de Bootstrap y jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <!-- Popper.js necesario para los componentes de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
