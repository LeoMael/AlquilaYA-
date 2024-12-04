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
    <!-- Enlaces a CSS de Bootstrap 5 para estilos modernos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Estilos personalizados */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f2f5;
        }
        .navbar-brand {
            font-weight: bold;
            color: #dc3545 !important;
        }
        .navbar-nav .nav-link {
            font-weight: 500;
            color: #495057;
            transition: color 0.3s;
        }
        .navbar-nav .nav-link:hover {
            color: #dc3545 !important;
        }
        .dashboard-container {
            padding-top: 20px;
            padding-bottom: 40px;
        }
        .card-header {
            background-color: #dc3545;
            color: white;
            font-weight: bold;
        }
        .btn-primary {
            background-color: #0d6efd;
            border: none;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
        }
        .btn-success {
            background-color: #198754;
            border: none;
        }
        .btn-success:hover {
            background-color: #157347;
        }
        .btn-danger {
            background-color: #dc3545;
            border: none;
        }
        .btn-danger:hover {
            background-color: #bb2d3b;
        }
        .btn-info {
            background-color: #0dcaf0;
            border: none;
        }
        .btn-info:hover {
            background-color: #31d2f2;
        }
        .media-body {
            flex: 1 1;
        }
        .footer {
            background-color: #dc3545;
            color: white;
            padding: 10px 0;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
        .card-img-top {
            height: 200px;
            object-fit: cover;
            border-top-left-radius: calc(.25rem - 1px);
            border-top-right-radius: calc(.25rem - 1px);
        }
        @media (max-width: 767.98px) {
            .card-img-top {
                height: 150px;
            }
        }
    </style>
</head>
<body>
    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="../index.php"><i class="fas fa-home me-2"></i>AlquilaYA!</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Panel de Control</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Mi Perfil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container dashboard-container">
        <h1 class="mb-4">Bienvenido, <?php echo htmlspecialchars($arrendador['nombre']); ?>!</h1>
        <p>Este es tu panel de control donde puedes gestionar tus propiedades.</p>

        <!-- Botón para agregar nueva casa -->
        <a href="add_house.php" class="btn btn-success mb-4"><i class="fas fa-plus-circle me-2"></i>Agregar Nueva Casa</a>

        <!-- Lista de Casas -->
        <h2 class="mb-3"><i class="fas fa-home me-2"></i>Mis Casas</h2>
        <div class="row">
            <?php
            if ($result_casas->num_rows > 0) {
                while ($casa = $result_casas->fetch_assoc()) {
                    // Obtener cuartos de la casa
                    $sql_cuartos = "SELECT * FROM cuartos WHERE id_casa = ?";
                    $stmt_cuartos = $conn->prepare($sql_cuartos);
                    $stmt_cuartos->bind_param("i", $casa['id_casa']);
                    $stmt_cuartos->execute();
                    $result_cuartos = $stmt_cuartos->get_result();
                    
                    // Contar cuartos
                    $total_cuartos = $result_cuartos->num_rows;

                    ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo htmlspecialchars($casa['direccion']); ?></h5>
                                <div>
                                    <a href="edit_house.php?id_casa=<?php echo $casa['id_casa']; ?>" class="btn btn-sm btn-primary me-2"><i class="fas fa-edit me-1"></i>Editar</a>
                                    <a href="delete_house.php?id_casa=<?php echo $casa['id_casa']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que deseas eliminar esta casa?');"><i class="fas fa-trash-alt me-1"></i>Eliminar</a>
                                    <a href="../house_details.php?id_casa=<?php echo $casa['id_casa']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye me-1"></i>Ver Publicación</a>
                                </div>
                            </div>
                            <?php if (!empty($casa['imagen']) && file_exists("../uploads/" . $casa['imagen'])): ?>
                                <img src="../uploads/<?php echo htmlspecialchars($casa['imagen']); ?>" class="card-img-top" alt="Imagen de la Casa">
                            <?php else: ?>
                                <img src="../images/cuarto_default.jpg" class="card-img-top" alt="Imagen de la Casa">
                            <?php endif; ?>
                            <div class="card-body d-flex flex-column">
                                <p class="card-text mb-2"><strong>Descripción:</strong> <?php echo htmlspecialchars($casa['descripcion']); ?></p>
                                <p class="card-text mb-2"><strong>Cuartos:</strong> <?php echo $total_cuartos; ?></p>
                                <a href="add_room.php?id_casa=<?php echo $casa['id_casa']; ?>" class="btn btn-success mt-auto"><i class="fas fa-plus-circle me-2"></i>Agregar Nuevo Cuarto</a>
                                <?php if ($total_cuartos > 0): ?>
                                    <h6 class="mt-3"><i class="fas fa-door-open me-2"></i>Cuartos:</h6>
                                    <ul class="list-group">
                                        <?php
                                        $stmt_cuartos->data_seek(0); // Reiniciar el puntero del resultado
                                        while ($cuarto = $result_cuartos->fetch_assoc()) {
                                            ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span>Cuarto en piso <?php echo htmlspecialchars($cuarto['piso']); ?> - S/.<?php echo number_format($cuarto['precio'], 2); ?></span>
                                                <div>
                                                    <a href="edit_room.php?id_cuarto=<?php echo $cuarto['id_cuarto']; ?>" class="btn btn-sm btn-primary me-2"><i class="fas fa-edit me-1"></i>Editar</a>
                                                    <a href="delete_room.php?id_cuarto=<?php echo $cuarto['id_cuarto']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que deseas eliminar este cuarto?');"><i class="fas fa-trash-alt me-1"></i>Eliminar</a>
                                                </div>
                                            </li>
                                            <?php
                                        }
                                        ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="mt-2">No hay cuartos agregados en esta casa.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<p>No has agregado ninguna casa aún. <a href="add_house.php" class="btn btn-success"><i class="fas fa-plus-circle me-2"></i>Agregar Casa</a></p>';
            }
            ?>
        </div>

        <!-- Comentarios de Casas -->
        <h2 class="mb-3"><i class="fas fa-comments me-2"></i>Comentarios de Casas</h2>
        <?php
        if ($result_comentarios_casas->num_rows > 0) {
            echo '<div class="row">';
            while ($comentario = $result_comentarios_casas->fetch_assoc()) {
                ?>
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($comentario['nombre_estudiante']); ?></h5>
                            <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($comentario['direccion']); ?></h6>
                            <p class="card-text"><strong>Calificación:</strong> 
                                <?php
                                for ($i = 0; $i < $comentario['calificacion']; $i++) {
                                    echo '<i class="fas fa-star text-warning"></i>';
                                }
                                for ($i = $comentario['calificacion']; $i < 5; $i++) {
                                    echo '<i class="far fa-star text-warning"></i>';
                                }
                                ?>
                            </p>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($comentario['comentario'])); ?></p>
                            <p class="card-text"><small class="text-muted">Fecha: <?php echo date('d/m/Y', strtotime($comentario['fecha'])); ?></small></p>
                            <a href="../house_details.php?id_casa=<?php echo $comentario['id_casa']; ?>" class="btn btn-info btn-sm"><i class="fas fa-eye me-1"></i>Ver Publicación</a>
                        </div>
                    </div>
                </div>
                <?php
            }
            echo '</div>';
        } else {
            echo '<p>No hay comentarios para tus casas.</p>';
        }
        ?>

        <!-- Comentarios de Cuartos -->
        <h2 class="mb-3"><i class="fas fa-comments me-2"></i>Comentarios de Cuartos</h2>
        <?php
        if ($result_comentarios_cuartos->num_rows > 0) {
            echo '<div class="row">';
            while ($comentario = $result_comentarios_cuartos->fetch_assoc()) {
                ?>
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($comentario['nombre_estudiante']); ?></h5>
                            <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($comentario['direccion']); ?> - Piso <?php echo htmlspecialchars($comentario['piso']); ?></h6>
                            <p class="card-text"><strong>Calificación:</strong> 
                                <?php
                                for ($i = 0; $i < $comentario['calificacion']; $i++) {
                                    echo '<i class="fas fa-star text-warning"></i>';
                                }
                                for ($i = $comentario['calificacion']; $i < 5; $i++) {
                                    echo '<i class="far fa-star text-warning"></i>';
                                }
                                ?>
                            </p>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($comentario['comentario'])); ?></p>
                            <p class="card-text"><small class="text-muted">Fecha: <?php echo date('d/m/Y', strtotime($comentario['fecha'])); ?></small></p>
                            <a href="../room_details.php?id_cuarto=<?php echo $comentario['id_cuarto']; ?>" class="btn btn-info btn-sm"><i class="fas fa-eye me-1"></i>Ver Publicación</a>
                        </div>
                    </div>
                </div>
                <?php
            }
            echo '</div>';
        } else {
            echo '<p>No hay comentarios para tus cuartos.</p>';
        }
        ?>
    </div>

    <!-- Footer -->
    <footer class="footer text-center">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> AlquilaYA! - Todos los derechos reservados.</p>
        </div>
    </footer>

    <!-- Scripts de Bootstrap 5 y dependencias -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome para íconos -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>
