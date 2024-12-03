<?php
// arrendador/dashboard.php
include '../includes/db_connect.php';
session_start();

// Verificar si el usuario ha iniciado sesión y es un arrendador
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] != 'arrendador') {
    header("Location: ../login.php");
    exit();
}

$id_arrendador = $_SESSION['id_arrendador'];

// Obtener información del arrendador
$sql_arrendador = "SELECT * FROM arrendadores WHERE id_arrendador = ?";
$stmt = $conn->prepare($sql_arrendador);
$stmt->bind_param("i", $id_arrendador);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $arrendador = $result->fetch_assoc();
} else {
    echo "Error al obtener los datos del arrendador.";
    exit();
}

// Obtener las casas del arrendador
$sql_casas = "SELECT * FROM casas WHERE id_arrendador = ?";
$stmt_casas = $conn->prepare($sql_casas);
$stmt_casas->bind_param("i", $id_arrendador);
$stmt_casas->execute();
$result_casas = $stmt_casas->get_result();

// Obtener comentarios de casas
$sql_comentarios_casas = "SELECT valoracion_casas.*, estudiantes.nombre AS nombre_estudiante, casas.direccion
                          FROM valoracion_casas
                          JOIN estudiantes ON valoracion_casas.id_estudiante = estudiantes.id_estudiante
                          JOIN casas ON valoracion_casas.id_casa = casas.id_casa
                          WHERE casas.id_arrendador = ?
                          ORDER BY valoracion_casas.fecha DESC";
$stmt_comentarios_casas = $conn->prepare($sql_comentarios_casas);
$stmt_comentarios_casas->bind_param("i", $id_arrendador);
$stmt_comentarios_casas->execute();
$result_comentarios_casas = $stmt_comentarios_casas->get_result();

// Obtener comentarios de cuartos
$sql_comentarios_cuartos = "SELECT valoracion_cuartos.*, estudiantes.nombre AS nombre_estudiante, cuartos.piso, casas.direccion
                            FROM valoracion_cuartos
                            JOIN estudiantes ON valoracion_cuartos.id_estudiante = estudiantes.id_estudiante
                            JOIN cuartos ON valoracion_cuartos.id_cuarto = cuartos.id_cuarto
                            JOIN casas ON cuartos.id_casa = casas.id_casa
                            WHERE casas.id_arrendador = ?
                            ORDER BY valoracion_cuartos.fecha DESC";
$stmt_comentarios_cuartos = $conn->prepare($sql_comentarios_cuartos);
$stmt_comentarios_cuartos->bind_param("i", $id_arrendador);
$stmt_comentarios_cuartos->execute();
$result_comentarios_cuartos = $stmt_comentarios_cuartos->get_result();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control - Arrendador - AlquilaYA!</title>
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
            <a class="nav-item nav-link active" href="dashboard.php">Panel de Control</a>
            <a class="nav-item nav-link" href="profile.php">Mi Perfil</a>
            <a class="nav-item nav-link" href="../logout.php">Cerrar Sesión</a>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container mt-4">
        <h1>Bienvenido, <?php echo htmlspecialchars($arrendador['nombre']); ?></h1>
        <p>Este es tu panel de control donde puedes gestionar tus propiedades.</p>

        <!-- Botón para agregar nueva casa -->
        <a href="add_house.php" class="btn btn-success mb-3">Agregar Nueva Casa</a>

        <!-- Lista de Casas -->
        <h2>Mis Casas</h2>
        <?php
        if ($result_casas->num_rows > 0) {
            while ($casa = $result_casas->fetch_assoc()) {
                // Obtener cuartos de la casa
                $sql_cuartos = "SELECT * FROM cuartos WHERE id_casa = ?";
                $stmt_cuartos = $conn->prepare($sql_cuartos);
                $stmt_cuartos->bind_param("i", $casa['id_casa']);
                $stmt_cuartos->execute();
                $result_cuartos = $stmt_cuartos->get_result();
                
                ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h5><?php echo htmlspecialchars($casa['direccion']); ?></h5>
                        <div class="float-right">
                            <a href="edit_house.php?id_casa=<?php echo $casa['id_casa']; ?>" class="btn btn-sm btn-primary">Editar</a>
                            <a href="delete_house.php?id_casa=<?php echo $casa['id_casa']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que deseas eliminar esta casa?');">Eliminar</a>
                            <?php echo '<a href="../house_details.php?id_casa=' . $casa['id_casa'] . '" class="btn btn-success btn-block">Ver Publicación</a>'?>
                        </div>
                    </div>
                    <div class="card-body">
                        
                        <?php if (!empty($casa['imagen'])) {
                            echo '<img src="../uploads/' . htmlspecialchars($casa['imagen']) . '" class="img-fluid mb-3" alt="Imagen de la Casa">';
                        } ?>
                        <p><strong>Descripción:</strong> <?php echo htmlspecialchars($casa['descripcion']); ?></p>
                        <!-- Botón para agregar nuevo cuarto -->
                        <a href="add_room.php?id_casa=<?php echo $casa['id_casa']; ?>" class="btn btn-success mb-2">Agregar Nuevo Cuarto</a>
                        <!-- Lista de Cuartos -->
                        <h6>Cuartos:</h6>
                        <?php
                        if ($result_cuartos->num_rows > 0) {
                            
                            echo '<ul class="list-group">';
                            while ($cuarto = $result_cuartos->fetch_assoc()) {
                                echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                                echo 'Cuarto en piso ' . $cuarto['piso'] . ' - S/.' . $cuarto['precio'];
                                echo '<div>';
                                echo '<a href="edit_room.php?id_cuarto=' . $cuarto['id_cuarto'] . '" class="btn btn-sm btn-primary mr-2">Editar</a>';
                                echo '<a href="delete_room.php?id_cuarto=' . $cuarto['id_cuarto'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'¿Estás seguro de que deseas eliminar este cuarto?\');">Eliminar</a>';
                                echo '</div>';
                                echo '</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<p>No hay cuartos agregados en esta casa.</p>';
                        }
                        ?>
                    </div>
                </div>
                <?php
            }
        } else {
            echo '<p>No has agregado ninguna casa aún.</p>';
        }
        ?>
        <h2>Comentarios de Casas</h2>
        <?php
        if ($result_comentarios_casas->num_rows > 0) {
            while ($comentario = $result_comentarios_casas->fetch_assoc()) {
                echo '<div class="media mb-3">';
                echo '<div class="media-body">';
                echo '<h5 class="mt-0">' . htmlspecialchars($comentario['nombre_estudiante']) . ' en ' . htmlspecialchars($comentario['direccion']) . '</h5>';
                echo '<p>Calificación: ' . $comentario['calificacion'] . ' / 5</p>';
                echo '<p>' . htmlspecialchars($comentario['comentario']) . '</p>';
                echo '<small class="text-muted">Fecha: ' . date('d/m/Y', strtotime($comentario['fecha'])) . '</small>';
                echo '</div>';
                echo '<a href="../house_details.php?id_casa=' . $comentario['id_casa'] . '" class="btn btn-sm btn-info">Ver Publicación</a>';
                echo '</div>';
            }
        } else {
            echo '<p>No hay comentarios para tus casas.</p>';
        }
        ?>

        <h2>Comentarios de Cuartos</h2>
        <?php
        if ($result_comentarios_cuartos->num_rows > 0) {
            while ($comentario = $result_comentarios_cuartos->fetch_assoc()) {
                echo '<div class="media mb-3">';
                echo '<div class="media-body">';
                echo '<h5 class="mt-0">' . htmlspecialchars($comentario['nombre_estudiante']) . ' en ' . htmlspecialchars($comentario['direccion']) . ' - Piso ' . $comentario['piso'] . '</h5>';
                echo '<p>Calificación: ' . $comentario['calificacion'] . ' / 5</p>';
                echo '<p>' . htmlspecialchars($comentario['comentario']) . '</p>';
                echo '<small class="text-muted">Fecha: ' . date('d/m/Y', strtotime($comentario['fecha'])) . '</small>';
                echo '</div>';
                echo '<a href="../room_details.php?id_cuarto=' . $comentario['id_cuarto'] . '" class="btn btn-sm btn-info">Ver Publicación</a>';
                echo '</div>';
            }
        } else {
            echo '<p>No hay comentarios para tus cuartos.</p>';
        }
        ?>

    </div>

    <!-- Scripts de Bootstrap y jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Popper.js necesario para Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
