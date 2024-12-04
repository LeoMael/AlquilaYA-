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
        $calificacion = (int)$_POST['calificacion'];
        $comentario = trim($_POST['comentario']);
        $id_estudiante = (int)$_SESSION['id_estudiante'];

        // Validar calificación
        if ($calificacion < 1 || $calificacion > 5) {
            $error_reseña = "La calificación debe estar entre 1 y 5.";
        } elseif (empty($comentario)) {
            $error_reseña = "El comentario es obligatorio.";
        } else {
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
        }
    } else {
        $error_reseña = "Debes iniciar sesión como estudiante para agregar una reseña.";
    }
}

// Obtener todas las casas para el mapa
$sql_todas_casas = "SELECT id_casa, direccion, latitud, longitud FROM casas WHERE latitud IS NOT NULL AND longitud IS NOT NULL";
$result_todas_casas = $conn->query($sql_todas_casas);

// Array para almacenar las coordenadas de todas las casas
$casas_coordenadas = [];

if ($result_todas_casas) {
    while ($fila = $result_todas_casas->fetch_assoc()) {
        if (is_numeric($fila['latitud']) && is_numeric($fila['longitud'])) {
            $casas_coordenadas[] = [
                'id_casa' => (int)$fila['id_casa'],
                'direccion' => htmlspecialchars($fila['direccion']),
                'latitud' => (float)$fila['latitud'],
                'longitud' => (float)$fila['longitud'],
            ];
        }
    }
} else {
    // Manejo de errores si la consulta falla
    $casas_coordenadas = [];
}

$casas_coordenadas_json = json_encode($casas_coordenadas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalles de la Casa - AlquilaYA!</title>
    <!-- Enlaces a CSS de Bootstrap 5 para estilos modernos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Leaflet CSS para mapas -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <!-- Leaflet Marker Cluster CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />
    <!-- Font Awesome para íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kaushan+Script&display=swap" rel="stylesheet">
    
    <style>
        /* Estilos personalizados */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #ffffff;
        }
        .map-section {
            height: 400px;
            width: 100%;
            margin-bottom: 20px;
        }
        .room-carousel {
            display: flex;
            overflow-x: auto;
            gap: 15px;
        }
        .room-card {
            min-width: 250px;
        }
        .ratings-summary {
            background-color: #fff;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .current-image {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .review-section {
            background-color: #fff;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .review-card {
            margin-bottom: 15px;
        }
        .navbar-brand {
            font-family: 'Kaushan Script', cursive;
            font-size: 1.8rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            color: #dc3545 !important; /* text-danger */
        }
        .navbar-light .navbar-nav .nav-link {
            color: #495057;
            font-weight: 600;
        }
        .navbar-light .navbar-nav .nav-link.text-danger {
            color: #FF7673 !important;
        }
        .navbar-light .navbar-nav .nav-link.text-success {
            color: #198754 !important;
        }
        .navbar-light .navbar-nav .nav-link.text-danger {
            color: #dc3545 !important;
        }
        .navbar-light .navbar-nav .nav-link:hover {
            color: #FF7673 !important;
        }
        /* Separador debajo de la navbar */
        .navbar {
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            background-color: #ffffff !important; /* Fondo blanco */
        }
        /* Texto central en Inika */
        .navbar-text {
            font-family: 'Inika', serif;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
        }
        /* Icono al lado del texto central */
        .navbar-text .fa-map-marker-alt {
            margin-right: 8px;
            color: #FF7673;
        }
        .navbar-light .navbar-nav .nav-link {
            color: #495057;
            font-weight: 600;
            font-family: 'Inria Sans', sans-serif; /* Aplicar Inria Sans */
        }
        .separator {
            border-top: 2px solid #dc3545;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-light border-bottom">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand text-danger" href="index.php">
                AlquilaYA!
            </a>

            <!-- Botón de colapso para dispositivos pequeños -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Menú -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Texto centrado con ícono -->
                <div class="mx-auto d-none d-lg-flex align-items-center">
                    <i class="fas fa-map-marker-alt"></i>
                    <span class="navbar-text text-secondary fs-5 ms-2">
                        Encuentra tu alojamiento ideal en Puno
                    </span>
                </div>
                
                <!-- Opciones del menú -->
                <ul class="navbar-nav ms-auto InriaText">
                    <?php
                    if (isset($_SESSION['tipo_usuario'])) {
                        if ($_SESSION['tipo_usuario'] == 'estudiante') {
                            echo '<li class="nav-item"><a class="nav-link" href="estudiante/profile.php">Mi Perfil</a></li>';
                        } elseif ($_SESSION['tipo_usuario'] == 'arrendador') {
                            echo '<li class="nav-item"><a class="nav-link" href="arrendador/dashboard.php">Mi Panel</a></li>';
                        }
                        echo '<li class="nav-item"><a class="nav-link text-danger" href="logout.php">Cerrar Sesión</a></li>';
                    } else {
                        echo '<li class="nav-item"><a class="nav-link" href="login.php">Iniciar Sesión</a></li>';
                        echo '<li class="nav-item"><a class="nav-link" href="register.php">Registrarse</a></li>';
                    }
                    ?>
                </ul>
            </div>
        </div>
    </nav>
     
    <!-- Contenido Principal -->
    <div class="container mt-4">
        <!-- Sección Superior: Imagen y Información de la Casa -->
        <div class="row">
            <!-- Imagen de la Casa -->
            <div class="col-md-6">
                <?php if (!empty($casa['imagen']) && file_exists('uploads/' . $casa['imagen'])): ?>
                    <img src="uploads/<?php echo htmlspecialchars($casa['imagen']); ?>" alt="Imagen de la Casa" class="img-fluid current-image">
                <?php else: ?>
                    <img src="images/casa_default.jpg" alt="Imagen de la Casa" class="img-fluid current-image">
                <?php endif; ?>
            </div>
            <!-- Información de la Casa -->
            <div class="col-md-6">
                <h1><?php echo htmlspecialchars($casa['direccion']); ?></h1>
                
                <?php 
                $calificacion = isset($casa['calificacion_promedio']) ? number_format($casa['calificacion_promedio'], 1) : 'No disponible';
                $total_calificaciones = $casa['total_calificaciones'];
                ?>
                <p>
                    <strong>Calificación:</strong> 
                    <?php 
                    if ($total_calificaciones > 0) {
                        echo $calificacion . ' / 5 estrellas (' . $total_calificaciones . ' calificaciones)';
                    } else {
                        echo 'No hay calificaciones aún.';
                    }
                    ?>
                </p>

                <p><?php echo nl2br(htmlspecialchars($casa['descripcion'] ?? '')); ?></p>

            </div>
        </div>
        
        <!-- Mapa de Ubicación -->
        <div class="row card">
            <div class="col-12">
                <h2 class="mt-4"><i class="fas fa-map-marker-alt me-2 text-danger"></i>Ubicación</h2>
                <div id="mapid" class="map-section"></div>
            </div>
        </div>
        <div class="separator"></div>
        <!-- Sección de Cuartos Disponibles -->
        <div class="row">
            <div class="col-12">
                <h3 class="mt-4 mb-3"><i class="fas fa-door-open me-2 text-danger"></i>Cuartos Disponibles</h3>
                <div class="room-carousel m-3">
                    <?php
                    if ($result_cuartos->num_rows > 0) {
                        while ($cuarto = $result_cuartos->fetch_assoc()) {
                            $estado = $cuarto['estado'] ? htmlspecialchars($cuarto['estado']) : 'Disponible';
                            ?>
                            <div class="card room-card">
                                <?php if (!empty($cuarto['imagen']) && file_exists('uploads/' . $cuarto['imagen'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($cuarto['imagen']); ?>" class="card-img-top" alt="Foto del Cuarto">
                                <?php else: ?>
                                    <img src="images/cuarto_default.jpg" class="card-img-top" alt="Foto del Cuarto">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title">Piso <?php echo htmlspecialchars($cuarto['piso']); ?></h5>
                                    <p class="card-text">Precio: S/ <?php echo htmlspecialchars(number_format($cuarto['precio'], 2)); ?></p>
                                    <p class="card-text">Tamaño: <?php echo htmlspecialchars($cuarto['metroscuadrados']); ?> m²</p>
                                    <p class="card-text">Estado: <?php echo $estado; ?></p>
                                    <a href="room_details.php?id_cuarto=<?php echo htmlspecialchars($cuarto['id_cuarto']); ?>" class="btn btn-danger"><i class="fas fa-eye me-2"></i>Ver Detalles</a>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<p>No hay cuartos disponibles en esta casa.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <div class="separator"></div>
        <!-- Resumen de Calificaciones -->
        <div class="row">
            <div class="col-md-4">
                <div class="ratings-summary">
                    <h3><i class="fas fa-star me-2 text-warning"></i>Calificación General</h3>
                    <?php 
                    if ($total_calificaciones > 0) {
                        echo '<p><strong>' . $calificacion . '</strong> de 5 estrellas</p>';
                    } else {
                        echo '<p>No hay calificaciones aún.</p>';
                    }
                    ?>
                    <div>
                        <?php
                        for ($i = 5; $i >= 1; $i--) {
                            $porcentaje = ($total_calificaciones > 0) ? ($calificaciones[$i] / $total_calificaciones) * 100 : 0;
                            echo '<div class="d-flex align-items-center mb-2">';
                            echo '<span class="me-2">' . $i . ' estrellas</span>';
                            echo '<div class="progress flex-grow-1 me-2">';
                            echo '<div class="progress-bar" role="progressbar" style="width: ' . $porcentaje . '%;" aria-valuenow="' . $porcentaje . '" aria-valuemin="0" aria-valuemax="100"></div>';
                            echo '</div>';
                            echo '<span>' . $calificaciones[$i] . '</span>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <!-- Comentarios y Calificaciones -->
            <div class="col-md-8">
                <div class="review-section">
                    <h3><i class="fas fa-comments me-2 text-danger"></i>Comentarios y Calificaciones</h3>
                    <?php
                    if ($result_reseñas->num_rows > 0) {
                        while ($reseña = $result_reseñas->fetch_assoc()) {
                            ?>
                            <div class="card review-card">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($reseña['nombre']); ?> <small class="text-muted">- <?php echo htmlspecialchars($reseña['calificacion']); ?> estrellas</small></h5>
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($reseña['comentario'])); ?></p>
                                    <p class="card-text"><small class="text-muted">Fecha: <?php echo date('d/m/Y', strtotime($reseña['fecha'])); ?></small></p>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<p>Aún no hay reseñas para esta casa.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Formulario para Agregar Reseña -->
        <?php
        // Verificar si el usuario es estudiante y ha iniciado sesión
        if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] == 'estudiante') {
            ?>
            <div class="row">
                <div class="col-12">
                    <div class="review-section">
                        <h3><i class="fas fa-pencil-alt me-2 text-success"></i>Agregar Reseña</h3>
                        <?php 
                        if (isset($error_reseña)) { 
                            echo '<div class="alert alert-danger">' . htmlspecialchars($error_reseña) . '</div>'; 
                        }
                        ?>
                        <form action="" method="POST">
                            <div class="mb-3">
                                <label for="calificacion" class="form-label"><i class="fas fa-star me-2 text-warning"></i>Calificación:</label>
                                <select name="calificacion" id="calificacion" class="form-select" required>
                                    <option value="">-- Seleccionar Calificación --</option>
                                    <option value="5">5 estrellas</option>
                                    <option value="4">4 estrellas</option>
                                    <option value="3">3 estrellas</option>
                                    <option value="2">2 estrellas</option>
                                    <option value="1">1 estrella</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="comentario" class="form-label"><i class="fas fa-comment me-2 text-danger"></i>Comentario:</label>
                                <textarea name="comentario" id="comentario" class="form-control" rows="4" placeholder="Escribe tu comentario aquí..." required></textarea>
                            </div>
                            <button type="submit" name="agregar_reseña" class="btn btn-danger"><i class="fas fa-paper-plane me-2"></i>Enviar Reseña</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php
        } else {
            echo '<div class="alert alert-info mt-4"><i class="fas fa-info-circle me-2"></i>Inicia sesión como estudiante para agregar una reseña.</div>';
        }
        ?>
    </div>

    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">&copy; <?php echo date("Y"); ?> AlquilaYA! - Todos los derechos reservados.</span>
        </div>
    </footer>

    <!-- Scripts de Bootstrap 5, Leaflet y Leaflet Marker Cluster -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <!-- Leaflet Marker Cluster JS -->
    <script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Inicializar el mapa principal
            var latitudCasa = <?php echo htmlspecialchars($casa['latitud']); ?>;
            var longitudCasa = <?php echo htmlspecialchars($casa['longitud']); ?>;
            var map = L.map('mapid').setView([latitudCasa, longitudCasa], 15);

            // Añadir capa de mapa de OpenStreetMap
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            // Añadir marcador para la casa actual
            if (latitudCasa && longitudCasa) {
                var marker = L.marker([latitudCasa, longitudCasa]).addTo(map)
                    .bindPopup('<strong><?php echo htmlspecialchars($casa['direccion']); ?></strong>').openPopup();
            }

            // Añadir todos los marcadores de casas utilizando Marker Cluster
            var markers = L.markerClusterGroup();

            var casas = <?php echo $casas_coordenadas_json; ?>;

            casas.forEach(function(casaItem) {
                // Evitar añadir la casa actual nuevamente
                if (casaItem.id_casa != <?php echo htmlspecialchars($id_casa); ?>) {
                    var marker = L.marker([casaItem.latitud, casaItem.longitud])
                        .bindPopup('<strong>' + casaItem.direccion + '</strong><br><a href="house_details.php?id_casa=' + casaItem.id_casa + '">Ver Detalles</a>');
                    markers.addLayer(marker);
                }
            });

            map.addLayer(markers);
        });
    </script>
</body>
</html>
