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

 $aver = "SELECT latitud, longitud from casas where $id_casa = id_casa";
 $result_casas_todas = $conn->query( $aver);

// Verificar si la consulta fue exitosa
if (!$result_casas_todas) {
    die('Error en la consulta de casas completas: ' . $conn->error);  // Manejo de errores si la consulta falla
}

// Array para almacenar las coordenadas de todas las casas
$casas_coordenadas = []; // Nuevo array para latitud y longitud

// Verificar si hay resultados
if ($result_casas_todas) {
    while ($fila = $result_casas_todas->fetch_assoc()) {
        // Verificar y agregar solo latitud y longitud si están definidas y son válidas
        if (is_numeric($fila['latitud']) && is_numeric($fila['longitud'])) {
            $casas_coordenadas[] = [
                'latitud' => (float)$fila['latitud'], // Almacena latitud
                'longitud' => (float)$fila['longitud'], // Almacena longitud
            ];
        }
    }
} else {
    // Manejo de errores si la consulta falla
    $casas_coordenadas = [];
}
$casas_coordenadas_json = json_encode( $casas_coordenadas);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalles de la Casa - AlquilaYA!</title>
    <!-- Enlaces a CSS de Bootstrap para estilos rápidos -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.css" />
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kaushan+Script&display=swap" rel="stylesheet">

    <style>
        /* Estilos personalizados */
        .sticky-map {
            position: fixed;
            top: 60px;
            right: 0;
            width: 50%;
            height: calc(70% - 80px);
            overflow: hidden;
        }
        .selected-marker {
            filter: hue-rotate(140deg);
        }
        .left-content {
            margin-right: 50%;
        }
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
            <div class="col-md-6 sticky-map">
                <div id="mapid" style="height: 100%;"></div>
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

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.js"></script>

    <script>
        function centrarMapa(lat, lon) {
        map.setView([lat, lon], 15); // Ajusta el zoom según lo necesites
        }
        var map = L.map('mapid').setView([-15.840221, -70.021881], 20);
        // Agregar el mapa de OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var casas = <?php echo $casas_coordenadas_json; ?>;

        // Agregar los marcadores al mapa
        casas.forEach(function(casa) {
            var lat = casa.latitud;
            var lon = casa.longitud;
            var idCasa = casa.id_casa;
            var direccion = casa.direccion;

            // Crear un marcador para cada casa
            var marker = L.marker([lat, lon]).addTo(map).bindPopup('<strong>' + direccion + '</strong>');

            // Cambiar el icono del marcador seleccionado
            marker.setIcon(L.icon({
                iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png', // Icono seleccionado
                iconSize: [30, 45],  
                iconAnchor: [15, 45],
                popupAnchor: [1, -36],
                shadowSize: [41, 41],
                className: 'selected-marker'  // Clase CSS personalizada para cambiar el color
            }));

            // Actualizar la referencia del marcador seleccionado
            marcadorSeleccionado = marker;

            // Centrar el mapa en las coordenadas del marcador seleccionado
            centrarMapa(lat, lon);

            // Guardar el ID de la casa seleccionada en localStorage
            localStorage.setItem('idCasaSeleccionada', idCasaSeleccionada);
        });
    </script>

</body>
</html>
