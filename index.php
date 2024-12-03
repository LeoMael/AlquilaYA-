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
        if (is_numeric($fila['latitud']) && is_numeric($fila['logitud'])) {
            $casas_coordenadas[] = [
                'latitud' => (float)$fila['latitud'], // Almacena latitud
                'logitud' => (float)$fila['logitud'], // Almacena longitud
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

    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="#">AlquilaYA!</a>
        <div class="collapse navbar-collapse justify-content-center">
            <span class="navbar-text">
                Encuentra tu alojamiento ideal en Puno
            </span>
        </div>
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
    <div class="container-fluid mt-3">
        <div class="row">
            <!-- Lado Izquierdo -->
            <div class="col-md-6 left-content">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Casas Disponibles (<?php echo $total_casas; ?>)</h3>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#filtroModal">
                        Filtro
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
                                    <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
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

                            if (($precio_min === NULL || $precios['precio_min'] >= $precio_min) && ($precio_max === NULL || $precios['precio_max'] <= $precio_max)) {
                                // Mostrar la casa solo si pasa el filtro
                                echo "<div class='card mb-3' id='card-{$id_casa}'>";
                                echo "<div class='card-body' data-id-casa='{$id_casa}'>";
                                echo "<h5 class='card-title'>{$casa['direccion']}</h5>";
                                echo "<p class='card-text'>{$casa['descripcion']}</p>";
                                echo "<p class='card-text'>Precio: S/. {$precios['precio_min']} - S/. {$precios['precio_max']}</p>";
                                echo "</div>";
                                echo "</div>";
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

            </div>

            <!-- Mapa (Lado Derecho) -->
            <div class="col-md-6 sticky-map">
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
    var map = L.map('mapid').setView([<?php echo $casas_coordenadas[0]['latitud']; ?>, <?php echo $casas_coordenadas[0]['logitud']; ?>], 15);

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
                        var lon = casa.logitud;

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
        var lon = casa.logitud;
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
