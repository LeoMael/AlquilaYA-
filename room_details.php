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
    <!-- Enlaces a CSS de Bootstrap 5 para estilos modernos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.css" />
    <!-- Font Awesome para íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <!-- Enlace a la fuente Kaushan Script -->
    <link href="https://fonts.googleapis.com/css2?family=Kaushan+Script&display=swap" rel="stylesheet">
    
    <style>
        
        /* Estilos personalizados */
        body {
            font-family: 'Roboto', sans-serif;
        }
        .navbar-brand {
            
            color: #dc3545 !important;
        }
        .carousel-item img {
            height: 500px;
            object-fit: cover;
        }
        .separator {
            border-top: 2px solid #dc3545;
            margin: 20px 0;
        }
        .anfitrion-section {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .ratings-summary {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .media-body {
            background-color: #ffffff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .progress {
            height: 20px;
        }
        .progress-bar {
            background-color: #dc3545;
        }
        .btn-contact {
            background-color: #28a745;
            color: white;
        }
        .btn-contact:hover {
            background-color: #218838;
            color: white;
        }
        .opinion-card {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .opinion-card h5 {
            color: #dc3545;
        }
        .form-opinion {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 30px;
        }
        /* Navegación */
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
        .navbar-light .navbar-nav .nav-link.text-primary {
            color: #0d6efd !important;
        }
        .navbar-light .navbar-nav .nav-link.text-success {
            color: #198754 !important;
        }
        .navbar-light .navbar-nav .nav-link.text-danger {
            color: #dc3545 !important;
        }
        .navbar-light .navbar-nav .nav-link:hover {
            color: #0d6efd !important;
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
            color: #0d6efd;
        }
        .navbar-light .navbar-nav .nav-link {
            color: #495057;
            font-weight: 600;
            font-family: 'Inria Sans', sans-serif; /* Aplicar Inria Sans */
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
    </style>
</head>
<body>
    <!-- Barra de Navegación -->
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
                <ul class="navbar-nav ms-auto">
                    <?php
                    if (isset($_SESSION['tipo_usuario'])) {
                        if ($_SESSION['tipo_usuario'] == 'estudiante') {
                            echo '<li class="nav-item"><a class="nav-link text-primary fw-semibold" href="estudiante/profile.php">Mi Perfil</a></li>';
                        } elseif ($_SESSION['tipo_usuario'] == 'arrendador') {
                            echo '<li class="nav-item"><a class="nav-link text-primary fw-semibold" href="arrendador/dashboard.php">Mi Panel</a></li>';
                        }
                        echo '<li class="nav-item"><a class="nav-link text-danger fw-semibold" href="logout.php">Cerrar Sesión</a></li>';
                    } else {
                        echo '<li class="nav-item"><a class="nav-link text-primary fw-semibold" href="login.php">Iniciar Sesión</a></li>';
                        echo '<li class="nav-item"><a class="nav-link text-success fw-semibold" href="register.php">Registrarse</a></li>';
                    }
                    ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container mt-5">
        <!-- Carrusel de Imágenes del Cuarto -->
        <div id="carouselImages" class="carousel slide mb-4" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <?php
                if (count($imagenes) > 0) {
                    for ($i = 0; $i < count($imagenes); $i++) {
                        echo '<button type="button" data-bs-target="#carouselImages" data-bs-slide-to="' . $i . '"' . ($i === 0 ? ' class="active" aria-current="true"' : '') . ' aria-label="Slide ' . ($i + 1) . '"></button>';
                    }
                } else {
                    echo '<button type="button" data-bs-target="#carouselImages" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>';
                }
                ?>
            </div>
            <div class="carousel-inner">
                <?php
                if (count($imagenes) > 0) {
                    $active = 'active';
                    foreach ($imagenes as $imagen) {
                        echo '<div class="carousel-item ' . $active . '">';
                        echo '<img src="uploads/' . htmlspecialchars($imagen) . '" class="d-block w-100" alt="Imagen del cuarto">';
                        echo '</div>';
                        $active = '';
                    }
                } else {
                    echo '<div class="carousel-item active">';
                    echo '<img src="images/cuarto_default.jpg" class="d-block w-100" alt="Imagen del cuarto">';
                    echo '</div>';
                }
                ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#carouselImages" data-bs-slide="prev">
                <span class="carousel-control-prev-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                <span class="visually-hidden">Anterior</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carouselImages" data-bs-slide="next">
                <span class="carousel-control-next-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                <span class="visually-hidden">Siguiente</span>
            </button>
        </div>

        <!-- Dirección del Cuarto -->
        <h2 class="mb-3 text-center text-md-start"><?php echo htmlspecialchars($cuarto['direccion']); ?></h2>

        <!-- Separador -->
        <div class="separator"></div>

        <!-- Nombre del Anfitrión y Botón "Contáctame" -->
        <div class="anfitrion-section">
            <div>
                <h4>Anfitrión: <?php echo htmlspecialchars($cuarto['nombre_arrendador']); ?></h4>
                <p class="mb-0"><i class="fas fa-phone-alt text-success me-2"></i><?php echo htmlspecialchars($cuarto['celular']); ?></p>
                <p class="mb-0"><i class="fas fa-envelope text-primary me-2"></i><?php echo htmlspecialchars($cuarto['email']); ?></p>
            </div>
            <?php
            // Formatear el número sin el '+' y con el formato internacional adecuado
            $telefono_arrendador = preg_replace('/\D/', '', $cuarto['celular']);
            $mensaje = "Hola, estoy interesado en el cuarto con ID: $id_cuarto.";
            $whatsapp_url = "https://wa.me/$telefono_arrendador?text=" . urlencode($mensaje);
            ?>
            <a href="<?php echo $whatsapp_url; ?>" class="btn btn-contact"><i class="fab fa-whatsapp me-2"></i>Contáctame</a>
        </div>

        <!-- Descripción del Cuarto -->
        <div class="mt-4">
            <h4>Descripción del Cuarto</h4>
            <p><?php echo nl2br(htmlspecialchars($cuarto['descripcion'])); ?></p>
            <p><strong>Estado:</strong> <?php echo htmlspecialchars($cuarto['estado_cuarto'] ?? 'Disponible'); ?></p>
            <p><strong>Precio:</strong> S/.<?php echo number_format($cuarto['precio'], 2); ?></p>
            <p><strong>Tamaño:</strong> <?php echo number_format($cuarto['metroscuadrados'], 2); ?> m²</p>
        </div>
        <div class="separator"></div>

        <!-- Calificaciones y Comentarios -->

            <div class="row">
            <!-- Resumen de Calificaciones -->
            <div class="col-md-4 ratings-summary">
                <h4 class="mb-4"><i class="fas fa-star me-2 text-warning"></i>Calificación General</h4>
                <p><strong><?php echo ($cuarto['calificacion_promedio'] !== null) ? number_format($cuarto['calificacion_promedio'], 1) : 'No disponible'; ?></strong> de 5 estrellas</p>
                <p><?php echo $total_calificaciones; ?> calificaciones en total</p>

                <?php
                // Mostrar barras de progreso de las calificaciones
                for ($i = 5; $i >= 1; $i--) {
                    $porcentaje = ($total_calificaciones > 0) ? ($calificaciones[$i] / $total_calificaciones) * 100 : 0;
                    echo '<div class="d-flex align-items-center mb-3">';
                    echo '<span class="me-3">' . $i . ' estrellas</span>';
                    echo '<div class="progress flex-grow-1 me-3" style="height: 10px;">';
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
                        echo '<div class="opinion-card mb-4 p-4 border rounded shadow-sm">';
                        echo '<div class="d-flex justify-content-between align-items-center mb-2">';
                        echo '<h5 class="mb-0">' . htmlspecialchars($reseña['nombre']) . '</h5>';
                        echo '<span>';
                        // Mostrar estrellas de calificación
                        for ($j = 0; $j < $reseña['calificacion']; $j++) {
                            echo '<i class="fas fa-star text-warning"></i>';
                        }
                        for ($j = $reseña['calificacion']; $j < 5; $j++) {
                            echo '<i class="far fa-star text-warning"></i>';
                        }
                        echo '</span>';
                        echo '</div>';
                        echo '<p>' . nl2br(htmlspecialchars($reseña['comentario'])) . '</p>';
                        echo '<small class="text-muted">Fecha: ' . date('d/m/Y', strtotime($reseña['fecha'])) . '</small>';
                        echo '</div>';
                    }
                } else {
                    echo '<p class="text-center text-muted">Aún no hay opiniones para este cuarto.</p>';
                }
                ?>
            </div>
        </div>


        <?php
        // Si el estudiante ha iniciado sesión, puede agregar una opinión
        if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] == 'estudiante') {
            ?>
            <div class="form-opinion">
                <h4>Agregar Opinión</h4>
                <?php if (isset($error_opinion)) { echo '<div class="alert alert-danger">' . htmlspecialchars($error_opinion) . '</div>'; } ?>
                <form action="" method="POST">
                    <div class="mb-3">
                        <label for="calificacion" class="form-label">Calificación:</label>
                        <select name="calificacion" id="calificacion" class="form-select" required>
                            <option value="" disabled selected>Selecciona una calificación</option>
                            <option value="5">5 estrellas</option>
                            <option value="4">4 estrellas</option>
                            <option value="3">3 estrellas</option>
                            <option value="2">2 estrellas</option>
                            <option value="1">1 estrella</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="comentario" class="form-label">Comentario:</label>
                        <textarea name="comentario" id="comentario" class="form-control" rows="4" required></textarea>
                    </div>
                    <button type="submit" name="agregar_opinion" class="btn btn-danger"><i class="fas fa-paper-plane me-2"></i>Enviar Opinión</button>
                </form>
            </div>
            <?php
        } else {
            echo '<div class="alert alert-info mt-4"><i class="fas fa-info-circle me-2"></i>Inicia sesión como estudiante para agregar una reseña.</div>';
        }
        ?>
    </div>

    <!-- Footer -->
    <footer class="bg-light text-center text-lg-start mt-5">
        <div class="text-center p-3" style="background-color: #dc3545; color: white;">
            © <?php echo date("Y"); ?> AlquilaYA! - Todos los derechos reservados.
        </div>
    </footer>

    <!-- Scripts de Bootstrap 5 y dependencias -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome para íconos -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>
