<?php
// index.php
include 'includes/db_connect.php';
session_start();

// Número de casas por página
$casas_por_pagina = 5;

// Página actual (validar que sea un número válido)
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

// Obtener casas para la página actual
$sql_casas = "SELECT casas.id_casa, casas.direccion, detalles_casas.descripcion, 
                     AVG(valoracion_casas.calificacion) as calificacion_promedio,
                     casas.latitud, casas.logitud  
              FROM casas
              LEFT JOIN detalles_casas ON casas.id_casa = detalles_casas.id_casa
              LEFT JOIN valoracion_casas ON casas.id_casa = valoracion_casas.id_casa
              GROUP BY casas.id_casa
              LIMIT $casas_por_pagina OFFSET $offset";
$result_casas = $conn->query($sql_casas);
?>
<?php

// Almacenar los datos en un array
$casas_coordenadas = []; // Nuevo array para latitud y logitud
if ($result_casas) {
    while ($fila = $result_casas->fetch_assoc()) {
        // Verificar y agregar solo latitud y longitud si están definidas y son válidas
        if (is_numeric($fila['latitud']) && is_numeric($fila['logitud'])) {
            $casas_coordenadas[] = [
                'latitud' => (float)$fila['latitud'],
                'logitud' => (float)$fila['logitud'],
                'direccion' => $fila['direccion'],  // Agregar la dirección de la casa
            ];
        }
    }
} else {
    // Manejo de errores si la consulta falla
    $casas_coordenadas = [];
}

$casas_coordenadas_json = json_encode($casas_coordenadas);
$result_casas = $conn->query($sql_casas);

//echo '<pre>';
//print_r($casas_coordenadas);
//echo '</pre>';

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>AlquilaYA! - Inicio</title>
    <!-- Enlaces a CSS de Bootstrap para estilos rápidos -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Tu archivo de estilos personalizado -->
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.css" />
    <style>
        /* Hacer que el mapa sea fijo cuando se hace scroll */
        .sticky-map {
            position: fixed;
            top: 80px; /* Ajusta este valor según la altura de tu navbar */
            right: 0;
            width: 50%;
            height: calc(100% - 80px); /* Ajusta este valor según la altura de tu navbar */
            overflow: hidden;
        }

        .left-content {
            margin-right: 50%;
        }
    </style>
</head>
<body>
    <!-- Navegación -->
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
                <!-- Botón de Filtro -->
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
                            <form action="search.php" method="GET">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="filtroModalLabel">Opciones de Filtro</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <!-- Opciones de filtro adicionales -->
                                    <div class="form-group">
                                        <label for="precio_min">Precio mínimo:</label>
                                        <input type="number" class="form-control" name="precio_min" placeholder="Precio mínimo">
                                    </div>
                                    <div class="form-group">
                                        <label for="precio_max">Precio máximo:</label>
                                        <input type="number" class="form-control" name="precio_max" placeholder="Precio máximo">
                                    </div>
                                    <!-- Puedes agregar más opciones de filtro aquí -->
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
                if ($result_casas->num_rows > 0) {
                    while($casa = $result_casas->fetch_assoc()) {
                        // Obtener rango de precios y cuartos disponibles
                        $id_casa = $casa['id_casa'];
                        $sql_precios = "SELECT MIN(precio) as precio_min, MAX(precio) as precio_max, COUNT(*) as total_cuartos FROM cuartos WHERE id_casa = $id_casa";
                        $result_precios = $conn->query($sql_precios);
                        $precios = $result_precios->fetch_assoc();

                        // Obtener reseña o comentario más reciente
                        $sql_comentario = "SELECT comentario FROM valoracion_casas WHERE id_casa = $id_casa ORDER BY fecha DESC LIMIT 1";
                        $result_comentario = $conn->query($sql_comentario);
                        $comentario = $result_comentario->fetch_assoc();

                        echo '<div class="card mb-3">';
                        echo '<div class="row no-gutters">';
                        echo '<div class="col-md-4">';
                        // Aquí puedes agregar la foto de la casa
                        echo '<img src="images/casa_default.jpg" class="card-img" alt="Foto de la casa">';
                        echo '</div>';
                        echo '<div class="col-md-8">';
                        echo '<div class="card-body">';
                        echo '<h5 class="card-title">' . $casa['direccion'] . '</h5>';
                        echo '<p class="card-text">Calificación: ' . number_format($casa['calificacion_promedio'], 1) . ' / 5 estrellas</p>';
                        echo '<p class="card-text">' . substr($casa['descripcion'], 0, 100) . '...</p>';
                        echo '<p class="card-text">Rango de precios: S/.' . $precios['precio_min'] . ' - S/.' . $precios['precio_max'] . '</p>';
                        echo '<p class="card-text">Cuartos disponibles: ' . $precios['total_cuartos'] . '</p>';
                        if ($comentario) {
                            echo '<p class="card-text"><em>"' . substr($comentario['comentario'], 0, 50) . '..."</em></p>';
                        }
                        echo '<a href="house_details.php?id_casa=' . $id_casa . '" class="btn btn-primary">Ver detalles</a>';
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                    }

                    // Paginación
                    echo '<nav aria-label="Paginación">';
                    echo '<ul class="pagination">';
                    for ($i = 1; $i <= $total_paginas; $i++) {
                        $active = ($i == $pagina_actual) ? 'active' : '';
                        echo '<li class="page-item ' . $active . '"><a class="page-link" href="index.php?pagina=' . $i . '">' . $i . '</a></li>';
                    }
                    echo '</ul>';
                    echo '</nav>';
                } else {
                    echo '<p>No hay casas disponibles en este momento.</p>';
                }
                ?>
            </div>

            <!-- Lado Derecho -->
            <div class="col-md-6">
                <!-- Mapa de Ubicaciones (Placeholder) -->
                <div class="sticky-map">
                    <h3>Mapa de Ubicaciones</h3>
                    <div id="map" style="width:98%;height:90%;background-color:#e9ecef;text-align:center;line-height:500px;">
                        <!-- Aquí irá el mapa interactivo -->
                        <p>Mapa en construcción</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
         window.casasCoordenadas = <?php echo $casas_coordenadas_json; ?>;
    </script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.js"></script>
    <script src="js/mapp.js"></script>
    <!-- Scripts de Bootstrap y jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <!-- Popper.js necesario para los modales de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
