<?php
// house_details.php
include 'includes/db_connect.php';
session_start();

// Obtener el id_casa de la URL
if (isset($_GET['id_casa'])) {
    $id_casa = (int)$_GET['id_casa'];
} else {
    // Redirigir al index si no se proporciona id_casa
    header("Location: index.php");
    exit();
}

// Obtener información de la casa
$sql_casa = "SELECT casas.*, detalles_casas.descripcion, casas.latitud, casas.longitud,
             AVG(valoracion_casas.calificacion) as calificacion_promedio,
             COUNT(valoracion_casas.calificacion) as total_calificaciones
             FROM casas
             LEFT JOIN detalles_casas ON casas.id_casa = detalles_casas.id_casa
             LEFT JOIN valoracion_casas ON casas.id_casa = valoracion_casas.id_casa
             WHERE casas.id_casa = ?
             GROUP BY casas.id_casa";
$stmt_casa = $conn->prepare($sql_casa);
$stmt_casa->bind_param("i", $id_casa);
$stmt_casa->execute();
$result_casa = $stmt_casa->get_result();

if ($result_casa->num_rows > 0) {
    $casa = $result_casa->fetch_assoc();
} else {
    echo "Casa no encontrada.";
    exit();
}

// Obtener cuartos disponibles en la casa
$sql_cuartos = "SELECT cuartos.*, 
                detalles_cuartos.descripcion AS descripcion_cuarto, 
                estados.estado
                FROM cuartos
                LEFT JOIN detalles_cuartos ON cuartos.id_cuarto = detalles_cuartos.id_cuarto
                LEFT JOIN estados ON cuartos.id_estado = estados.id_estado
                WHERE cuartos.id_casa = ?";
$stmt_cuartos = $conn->prepare($sql_cuartos);
$stmt_cuartos->bind_param("i", $id_casa);
$stmt_cuartos->execute();
$result_cuartos = $stmt_cuartos->get_result();

// Obtener reseñas y calificaciones de la casa
$sql_reseñas = "SELECT valoracion_casas.*, estudiantes.nombre
                FROM valoracion_casas
                JOIN estudiantes ON valoracion_casas.id_estudiante = estudiantes.id_estudiante
                WHERE valoracion_casas.id_casa = ?
                ORDER BY valoracion_casas.fecha DESC";
$stmt_reseñas = $conn->prepare($sql_reseñas);
$stmt_reseñas->bind_param("i", $id_casa);
$stmt_reseñas->execute();
$result_reseñas = $stmt_reseñas->get_result();

// Calcular porcentajes de calificaciones
$sql_calificaciones = "SELECT calificacion, COUNT(*) as cantidad
                       FROM valoracion_casas
                       WHERE id_casa = ?
                       GROUP BY calificacion";
$stmt_calificaciones = $conn->prepare($sql_calificaciones);
$stmt_calificaciones->bind_param("i", $id_casa);
$stmt_calificaciones->execute();
$result_calificaciones = $stmt_calificaciones->get_result();

$calificaciones = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$total_calificaciones = $casa['total_calificaciones'];

if ($total_calificaciones > 0) {
    while ($row = $result_calificaciones->fetch_assoc()) {
        $calificaciones[$row['calificacion']] = $row['cantidad'];
    }
}

// Procesar el formulario de agregar reseña
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_reseña'])) {
    if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] == 'estudiante') {
        $calificacion = $_POST['calificacion'];
        $comentario = $_POST['comentario'];
        $id_estudiante = $_SESSION['id_estudiante'];

        $sql_insert_reseña = "INSERT INTO valoracion_casas (id_casa, calificacion, comentario, fecha, id_estudiante) VALUES (?, ?, ?, NOW(), ?)";
        $stmt_insert = $conn->prepare($sql_insert_reseña);
        $stmt_insert->bind_param("iisi", $id_casa, $calificacion, $comentario, $id_estudiante);
        if ($stmt_insert->execute()) {
            // Recargar la página para mostrar la nueva reseña
            header("Location: house_details.php?id_casa=" . $id_casa);
            exit();
        } else {
            $error_reseña = "Error al agregar la reseña.";
        }
    } else {
        $error_reseña = "Debes iniciar sesión como estudiante para agregar una reseña.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalles de la Casa - AlquilaYA!</title>
    <!-- Enlaces a CSS de Bootstrap para estilos rápidos -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Tu archivo de estilos personalizado -->
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* Estilos personalizados */
        .image-section {
            max-width: 300px;
        }
        .info-section {
            flex-grow: 1;
            padding: 0 20px;
        }
        .map-section {
            max-width: 300px;
        }
        .room-carousel {
            overflow-x: auto;
            white-space: nowrap;
        }
        .room-card {
            display: inline-block;
            width: 250px;
            margin-right: 15px;
        }
        .ratings-summary {
            max-width: 300px;
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
        <!-- Sección Superior -->
        <div class="d-flex">
            <!-- Foto de la Casa -->
            <div class="image-section">
                <img src="images/casa_default.jpg" class="img-fluid" alt="Foto de la casa">
            </div>
            <!-- Información de la Casa -->
            <div class="info-section">
                <h1><?php echo $casa['direccion']; ?></h1>
                
                <?php $calificacion = isset($casa['calificacion_promedio']) ? number_format($casa['calificacion_promedio'], 1) : 'No disponible';?>
                <?php echo '<p>Calificación: ' . $calificacion . ' / 5 estrellas</p>'; ?>

                <p><?php echo $casa['descripcion']; ?></p>
            </div>
            <!-- Mapa -->
            <div class="map-section">
                <div id="map">
                    <!-- El mapa se cargará aquí -->
                </div>
            </div>
        </div>

        <!-- Sección de Cuartos -->
        <h2 class="mt-4">Cuartos Disponibles</h2>
        <div class="room-carousel">
            <?php
            if ($result_cuartos->num_rows > 0) {
                while ($cuarto = $result_cuartos->fetch_assoc()) {
                    $estado = $cuarto['estado'] ? $cuarto['estado'] : 'Disponible';
                    echo '<div class="card room-card">';
                    echo '<img src="images/cuarto_default.jpg" class="card-img-top" alt="Foto del cuarto">';
                    echo '<div class="card-body">';
                    echo '<h5 class="card-title">Piso ' . $cuarto['piso'] . '</h5>';
                    echo '<p class="card-text">Precio: S/.' . $cuarto['precio'] . '</p>';
                    echo '<p class="card-text">Tamaño: ' . $cuarto['metroscuadrados'] . ' m²</p>';
                    echo '<p class="card-text">Estado: ' . $estado . '</p>';
                    echo '<a href="room_details.php?id_cuarto=' . $cuarto['id_cuarto'] . '" class="btn btn-primary">Ver detalles</a>';
                    echo '</div>';
                    echo '</div>';
                }
            } else {
                echo '<p>No hay cuartos disponibles en esta casa.</p>';
            }
            ?>
        </div>

        <!-- Comentarios y Calificaciones -->
        <h2 class="mt-4">Comentarios y Calificaciones</h2>
        <div class="row">
            <!-- Resumen de Calificaciones -->
            <div class="col-md-4 ratings-summary">
                <h3>Calificación General</h3>
                <?php $calificacion = isset($casa['calificacion_promedio']) ? number_format($casa['calificacion_promedio'], 1) : 'No disponible';?>
                <?php echo '<p><strong>' . $calificacion . '</strong> de 5 estrellas</p>';?>

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
                    while ($reseña = $result_reseñas->fetch_assoc()) {
                        echo '<div class="media mb-3">';
                        echo '<div class="media-body">';
                        echo '<h5 class="mt-0">' . htmlspecialchars($reseña['nombre']) . ' - ' . $reseña['calificacion'] . ' estrellas</h5>';
                        echo '<p>' . htmlspecialchars($reseña['comentario']) . '</p>';
                        echo '<small class="text-muted">Fecha: ' . date('d/m/Y', strtotime($reseña['fecha'])) . '</small>';
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<p>Aún no hay reseñas para esta casa.</p>';
                }
                ?>
            </div>
        </div>

        <?php
        // Formulario para agregar reseña
        if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] == 'estudiante') {
            ?>
            <h3>Agregar Reseña</h3>
            <?php if (isset($error_reseña)) { echo '<div class="alert alert-danger">'.$error_reseña.'</div>'; } ?>
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
                <button type="submit" name="agregar_reseña" class="btn btn-primary">Enviar Reseña</button>
            </form>
            <?php
        } else {
            echo '<p>Inicia sesión como estudiante para agregar una reseña.</p>';
        }
        ?>
    </div>

    <!-- Scripts de Bootstrap y jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <!-- Popper.js necesario para los componentes de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Script para el mapa (puedes integrar tu mapp.js aquí) -->
    <script>
        // Verificar si las coordenadas están disponibles y son válidas
        <?php if (is_numeric($casa['latitud']) && is_numeric($casa['longitud'])): ?>
            const map = L.map('map').setView([<?php echo $casa['latitud']; ?>, <?php echo $casa['longitud']; ?>], 15);

            // Agregar el mapa de OpenStreetMap
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            // Agregar marcador para la casa
            L.marker([<?php echo $casa['latitud']; ?>, <?php echo $casa['longitud']; ?>]).addTo(map)
                .bindPopup(`Dirección: <?php echo $casa['direccion']; ?>`);
        <?php else: ?>
            // Si no hay coordenadas válidas, mostrar mensaje
            document.getElementById('map').innerHTML = '<p>Ubicación no disponible</p>';
        <?php endif; ?>
    </script>
</body>
</html>
