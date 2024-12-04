<?php
// index.php
include 'includes/db_connect.php';
session_start();

// Número de casas por página
$casas_por_pagina = 5;

// Validar id_casa desde la URL (nos aseguramos de que sea un valor entero y seguro)
$id_primero_paginacion = isset($_GET['id_casa']) ? (int)$_GET['id_casa'] : null; // Obtener id_casa desde la URL

// Número de página actual
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) {
    $pagina_actual = 1;  // Asegurar que la página sea al menos 1
}

// Calcular el desplazamiento para la consulta SQL
$offset = ($pagina_actual - 1) * $casas_por_pagina;

// Contar el número total de casas disponibles
$sql_total_casas = "SELECT COUNT(*) as total FROM casas";
$result_total = $conn->query($sql_total_casas);

if ($result_total) {
    $total_casas = $result_total->fetch_assoc()['total'];
} else {
    die("Error al obtener el número total de casas: " . $conn->error);  // Manejo de errores
}

// Calcular el número total de páginas
$total_paginas = ceil($total_casas / $casas_por_pagina);

// Validamos que $id_primero_paginacion tenga un valor válido antes de ejecutar la consulta
if ($id_primero_paginacion === null) {
    // Si no hay id_casa en la URL, forzamos a que sea 0 o el valor que necesites
    $id_primero_paginacion = 0;
}

// Consulta para obtener casas con paginación y que la casa seleccionada aparezca primero
$sql_casas = "SELECT casas.id_casa, casas.direccion, detalles_casas.descripcion, 
                     AVG(valoracion_casas.calificacion) as calificacion_promedio,
                     casas.latitud, casas.longitud  
              FROM casas
              LEFT JOIN detalles_casas ON casas.id_casa = detalles_casas.id_casa
              LEFT JOIN valoracion_casas ON casas.id_casa = valoracion_casas.id_casa
              GROUP BY casas.id_casa
              ORDER BY 
                  CASE WHEN casas.id_casa = $id_primero_paginacion THEN 0 ELSE 1 END, 
                  casas.id_casa
              LIMIT $casas_por_pagina OFFSET $offset";

// Ejecutamos la consulta para obtener las casas con paginación
$result_casas = $conn->query($sql_casas);

// Verificar si la consulta fue exitosa
if (!$result_casas) {
    die('Error en la consulta de casas: ' . $conn->error);  // Manejo de errores si la consulta falla
}

// Nueva consulta para obtener todas las casas (sin paginación ni filtrado de precios)
$sql_casas_todas = "SELECT casas.id_casa, casas.direccion, casas.latitud, casas.longitud  
                     FROM casas
                     LEFT JOIN detalles_casas ON casas.id_casa = detalles_casas.id_casa
                     LEFT JOIN valoracion_casas ON casas.id_casa = valoracion_casas.id_casa
                     GROUP BY casas.id_casa";

// Ejecutamos la consulta para obtener todas las casas
$result_casas_todas = $conn->query($sql_casas_todas);

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
                'direccion' => $fila['direccion'],   // Almacena la dirección de la casa
                'id_casa' => $fila['id_casa'],
            ];
        }
    }
} else {
    // Manejo de errores si la consulta falla
    $casas_coordenadas = [];
}

// Ahora $casas_coordenadas contiene las coordenadas y direcciones de todas las casas
$casas_coordenadas_json = json_encode($casas_coordenadas);

?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>AlquilaYA! - Inicio</title>
    <!-- Enlaces a CSS de Bootstrap para estilos rápidos -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.css" />
    <!-- Font Awesome para íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Enlace a la fuente Kaushan Script -->
    <link href="https://fonts.googleapis.com/css2?family=Kaushan+Script&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inria+Serif:ital,wght@0,300;0,400;0,700;1,300;1,400;1,700&display=swap" rel="stylesheet">
    <style>
        /* Hacer que el mapa sea fijo cuando se hace scroll */
        .sticky-map {
            position: fixed;
            top: 80px;
            right: 0;
            width: 50%;
            height: calc(100% - 80px);
            overflow: hidden;
        }
        .left-content {
            margin-right: 50%;
        }
        /* CSS para el marcador seleccionado */
        .selected-marker {
            filter: hue-rotate(140deg);
        }
        .card-body:hover {
        background-color: #dcdcdc; /* Fondo claro cuando el ratón pasa */
        cursor: pointer; /* Cambiar el cursor a puntero */
        }

        /* Efecto cuando la tarjeta es clickeada */
        .card-body.selected {
            background-color: #dcdcdc; /* Gris claro */
            cursor: pointer; /* Mantener el cursor como puntero para la interacción */
        }
        body {
            background-color: #ffffff;
            font-family: 'Arial', sans-serif;
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
        .navbar-light .navbar-nav .nav-link.text-danger {
            color: #ff7673 !important;
        }
        .navbar-light .navbar-nav .nav-link.text-success {
            color: #198754 !important;
        }
        .navbar-light .navbar-nav .nav-link.text-danger {
            color: #dc3545 !important;
        }
        .navbar-light .navbar-nav .nav-link:hover {
            color: #ff7673 !important;
        }
        /* Separador debajo de la navbar */
        .navbar {
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            background-color: #ffffff !important;
            position: sticky;
            top: 0;
            z-index: 10;
        }   
        /* Tarjetas de casas */
        .card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .card:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .card-img-top {
            height: 200px;
            object-fit: cover;
            border-top-left-radius: calc(.25rem - 1px);
            border-top-right-radius: calc(.25rem - 1px);
        }
        /* Filtros */
        .modal-header {
            background-color: #bd2130;
            color: white;
        }
        /* Paginación */
        .pagination .page-link {
            color: #ff7673;
        }
        .pagination .active .page-link {
            background-color: #be211d;
            border-color: #be211d;
            color: white;
        }
        /* Botón de contacto */
        .btn-contact {
            background-color: #25D366;
            color: white;
        }
        .btn-contact:hover {
            background-color: #128C7E;
            color: white;
        }
        /* Mejoras en la tarjeta */
        .card-body {
            display: flex;
            flex-direction: column;
            font-family: 'Inria Sans', sans-serif; /* Aplicar Inria Sans */
        }
        .card-body:hover {
            background-color: #f1f1f1;
        }
        /* Responsividad del mapa */
        @media (max-width: 767.98px) {
            .sticky-map {
                position: static;
                height: 300px;
                margin-top: 20px;
            }
            .left-content {
                margin-right: 0;
            }
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
            color: #ff7673;
        }
        .navbar-light .navbar-nav .nav-link {
            color: #495057;
            font-weight: 600;
            font-family: 'Inria Sans', sans-serif; /* Aplicar Inria Sans */
        }
        .titulo-puno {
            font-size: 1.4rem;
            font-family: 'Josefin Slab', serif;
        }
        .InriaText {
            font-family: 'Inria Sans';
        }
        .separator {
            border-top: 2px solid #de6772;
            margin: 20px 0;
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
    <div class="container-fluid mt-3 pl-5 pr-5">
        <div class="row">
            <!-- Lado Izquierdo -->
            <div class="col-md-6 left-content">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="titulo-puno"><?php echo $total_casas; ?> Lugares en Puno</h3>
                    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#filtroModal">
                    <i class="fas fa-filter me-2"></i> Filtro
                    </button>
                </div>
                <!-- Modal de Filtro -->
                <div class="modal fade" id="filtroModal" tabindex="-1" aria-labelledby="filtroModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="index.php" method="GET">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="filtroModalLabel">Opciones de Filtro</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label for="precio_min">Precio mínimo:</label>
                                        <input type="number" class="form-control" name="precio_min" placeholder="Precio mínimo">
                                    </div>
                                    <div class="form-group">
                                        <label for="precio_max">Precio máximo:</label>
                                        <input type="number" class="form-control" name="precio_max" placeholder="Precio máximo">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-danger">Aplicar Filtros</button>
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Sección de Casas -->
                <?php
                    $precio_min = isset($_GET['precio_min']) ? $_GET['precio_min'] : NULL;
                    $precio_max = isset($_GET['precio_max']) ? $_GET['precio_max'] : NULL;
                    $conta = 0;

                    if ($result_casas->num_rows > 0) {
                        while ($casa = $result_casas->fetch_assoc()) {
                            $id_casa = $casa['id_casa'];
                            $sql_precios = "SELECT MIN(precio) as precio_min, MAX(precio) as precio_max, COUNT(*) as total_cuartos FROM cuartos WHERE id_casa = $id_casa";
                            $result_precios = $conn->query($sql_precios);
                            $precios = $result_precios->fetch_assoc();
                            // Obtener reseña o comentario más reciente
                            $sql_comentario = "SELECT comentario FROM valoracion_casas WHERE id_casa = $id_casa ORDER BY fecha DESC LIMIT 1";
                            $result_comentario = $conn->query($sql_comentario);
                            $comentario = $result_comentario->fetch_assoc();

                            // Obtener imagen de la casa o una imagen por defecto
                            $imagen_casa = !empty($casa['imagen']) && file_exists('uploads/' . $casa['imagen']) ? 'uploads/' . $casa['imagen'] : 'assets/default_house.jpg';

                            // Calificación promedio
                            $calificacion_promedio = isset($casa['calificacion_promedio']) ? number_format($casa['calificacion_promedio'], 1) : '0.0';
                            
                            // Descripción corta
                            $descripcion_casa = isset($casa['descripcion']) ? htmlspecialchars(substr($casa['descripcion'], 0, 100)) . '...' : 'No hay descripción disponible.';
                            
                            if (($precio_min == NULL || $precios['precio_min'] >= $precio_min) && ($precio_max == NULL || $precios['precio_max'] <= $precio_max)) {
                                // Mostrar la casa solo si pasa el filtro

                                echo '<div class="mb-3">';
                                echo '  <div class="row g-0">';
                                echo '      <div class="col-md-4">';
                                echo '          <img src="' . $imagen_casa . '" class="img-fluid rounded-start" alt="Foto de la casa">';
                                echo '      </div>';
                                echo '      <div class="col-md-8">';
                                echo "          <div class='card-body' data-id-casa='{$id_casa}'>";
                                echo '              <h5 class="card-title">' . $casa['direccion'] . '</h5>';
                                echo '              <p class="card-text">';
                                echo '                  <i class="fas fa-star text-warning"></i> ' . $calificacion_promedio . ' / 5 estrellas';
                                echo '              </p>';
                                echo '              <p class="card-text">' . $descripcion_casa . '</p>';
                                echo '              <p class="card-text"><strong>Rango de precios:</strong> S/.' . $precios['precio_min'] . ' - S/.' . $precios['precio_max'] . '</p>';
                                echo '              <p class="card-text"><strong>Cuartos:</strong> ' . $precios['total_cuartos'] . '</p>';
                                if ($comentario) {
                                    echo '              <p class="card-text"><em>"' . substr($comentario['comentario'], 0, 50) . '..."</em></p>';
                                }
                                echo '              <a href="house_details.php?id_casa=' . $id_casa . '" class="btn btn-danger">Ver detalles</a>';
                                echo '          </div>';
                                echo '      </div>';
                                echo '  </div>';
                                echo '</div>';
                                echo '<div class="separator"></div>';
                                $conta++;

                            }
                        }   
                    } else {
                        echo "<p>No se encontraron casas.</p>";
                    }
                ?>

                <nav aria-label="Paginación">
                    <ul class="pagination justify-content-center">
                        <?php
                        // Comprobar si 'pagina' está definida en la URL, si no, establecerla en 1 por defecto
                        $pagina_actual = isset($_GET['pagina']) ? $_GET['pagina'] : 1;

                        // Mostrar los números de página
                        for ($i = 1; $i <= $total_paginas; $i++) {
                            // Comprobar si la página actual es la misma que la página en el ciclo
                            $active_class = ($i == $pagina_actual) ? 'active' : ''; // Agregar la clase 'active' a la página actual
                            echo '<li class="page-item ' . $active_class . '">
                                    <a class="page-link" href="index.php?pagina=' . $i . '&precio_min=' . $precio_min . '&precio_max=' . $precio_max . '">' . $i . '</a>
                                </li>';
                        }
                        ?>
                    </ul>
                </nav>
                <div class="separator"></div>
            </div>

            <!-- Mapa (Lado Derecho) -->
            <div class="col-md-6 sticky-map card p-1">
                <div id="mapid" style="height: 100%;"></div>
            </div>
        </div>
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

    var idCasaSeleccionada = localStorage.getItem('idCasaSeleccionada');
    var marcadorSeleccionado = null; // Declaración fuera para que sea accesible globalmente

    // Inicializar mapa
    var map = L.map('mapid').setView([<?php echo $casas_coordenadas[0]['latitud']; ?>, <?php echo $casas_coordenadas[0]['longitud']; ?>], 15);

    // Añadir capa de mapa
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // Array de casas (se asume que es el mismo array que viene del PHP)
    var casas = <?php echo $casas_coordenadas_json; ?>;

    // Escuchar el evento DOMContentLoaded para asegurar que el HTML esté listo
    document.addEventListener('DOMContentLoaded', function() {
        // Escuchar los clics en el cuerpo de la página
        document.body.addEventListener('click', function(event) {
            var cardBody = event.target.closest('.card-body');
            
            if (cardBody) {
                // Obtener el id_casa de la tarjeta
                var idCasaSeleccionada = cardBody.getAttribute('data-id-casa');

                // Remover la clase 'selected' de todas las tarjetas
                var allCardBodies = document.querySelectorAll('.card-body');
                allCardBodies.forEach(function(card) {
                    card.classList.remove('selected');
                });

                // Añadir la clase 'selected' al card-body actual
                cardBody.classList.add('selected');

                // Buscar la casa correspondiente por id_casa
                casas.forEach(function(casa) {
                    if (casa.id_casa == idCasaSeleccionada) {
                        var lat = casa.latitud;
                        var lon = casa.longitud;

                        // Si ya hay un marcador seleccionado, no restablecerlo
                        if (marcadorSeleccionado) {
                            // Si el marcador ya está en el mapa, no eliminamos su capa
                            if (map.hasLayer(marcadorSeleccionado)) {
                                marcadorSeleccionado.setIcon(L.icon({
                                    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png', // Icono estándar
                                    iconSize: [25, 41],
                                    iconAnchor: [12, 41],
                                    popupAnchor: [1, -34],
                                    shadowSize: [41, 41]
                                }));
                            }
                        }

                        // Crear el nuevo marcador
                        var marker = L.marker([lat, lon]).addTo(map)
                            .bindPopup('<strong>' + casa.direccion + '</strong>');

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
                    }
                });
            }
        });
    });

    // Agregar los marcadores al mapa
    casas.forEach(function(casa) {
        var lat = casa.latitud;
        var lon = casa.longitud;
        var idCasa = casa.id_casa;
        var direccion = casa.direccion;

        // Crear un marcador para cada casa
        var marker = L.marker([lat, lon]).addTo(map)
            .bindPopup('<strong>' + direccion + '</strong>')
            .on('click', function () {
                // Si el marcador ya está seleccionado, evitar actualizaciones innecesarias
                if (marcadorSeleccionado === marker) {
                    return;
                }

                // Guardar el ID de la casa seleccionada en localStorage
                localStorage.setItem('idCasaSeleccionada', idCasa);

                // Si hay un marcador previamente seleccionado, restaurar su icono
                if (marcadorSeleccionado) {
                    marcadorSeleccionado.setIcon(L.icon({
                        iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png', // Icono estándar
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    }));
                }

                // Actualizar el marcador seleccionado
                marcadorSeleccionado = marker;

                // Cambiar el ícono del marcador seleccionado
                marker.setIcon(L.icon({
                    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png', // Icono seleccionado
                    iconSize: [30, 45],  
                    iconAnchor: [15, 45],
                    popupAnchor: [1, -36],
                    shadowSize: [41, 41],
                    className: 'selected-marker'  // Clase CSS para el marcador seleccionado
                }));

                // Redirigir a la URL con el id_casa al hacer clic
                window.location.href = window.location.pathname + '?id_casa=' + idCasa;
            });

        // Si el marcador corresponde al ID de la casa seleccionada, lo marca como seleccionado
        if (idCasa == idCasaSeleccionada) {
            marker.setIcon(L.icon({
                iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
                iconSize: [30, 45],
                iconAnchor: [15, 45],
                popupAnchor: [1, -36],
                shadowSize: [41, 41],
                className: 'selected-marker' // Aseguramos que el ícono se marque como seleccionado
            }));
            marcadorSeleccionado = marker;
        }
    });
</script>



</body>
</html>
