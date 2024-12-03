<?php
// arrendador/profile.php
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil del Arrendador - AlquilaYA!</title>
    <!-- Enlaces a CSS de Bootstrap para estilos rápidos -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Tu archivo de estilos personalizado -->
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        /* Estilos personalizados */
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-header h1 {
            font-size: 2.5em;
        }
        .nav-tabs .nav-link.active {
            background-color: #f8f9fa;
            border-color: #dee2e6 #dee2e6 #fff;
        }
    </style>
</head>
<body>
    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="../index.php">AlquilaYA!</a>
        <div class="navbar-nav">
            <a class="nav-item nav-link" href="dashboard.php">Panel de Control</a>
            <a class="nav-item nav-link active" href="profile.php">Mi Perfil</a>
            <a class="nav-item nav-link" href="../logout.php">Cerrar Sesión</a>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container mt-4">
        <!-- Encabezado del Perfil -->
        <div class="profile-header">
            <h1><?php echo htmlspecialchars($arrendador['nombre']); ?></h1>
        </div>

        <!-- Navegación por Pestañas -->
        <ul class="nav nav-tabs" id="profileTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="datos-tab" data-toggle="tab" href="#datos" role="tab" aria-controls="datos" aria-selected="true">Datos Personales</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="cuenta-tab" data-toggle="tab" href="#cuenta" role="tab" aria-controls="cuenta" aria-selected="false">Cuenta</a>
            </li>
            <!-- Puedes agregar más pestañas si lo deseas -->
        </ul>

        <!-- Contenido de las Pestañas -->
        <div class="tab-content" id="profileTabContent">
            <!-- Datos Personales -->
            <div class="tab-pane fade show active" id="datos" role="tabpanel" aria-labelledby="datos-tab">
                <?php include 'datos.php'; ?>
            </div>
            <!-- Cuenta -->
            <div class="tab-pane fade" id="cuenta" role="tabpanel" aria-labelledby="cuenta-tab">
                <?php include 'cuenta.php'; ?>
            </div>
        </div>
    </div>

    <!-- Scripts de Bootstrap y jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Popper.js necesario para los componentes de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Script para cargar contenido dinámicamente -->
    <script>
        $(document).ready(function(){
            // Cargar contenido de la pestaña activa al cargar la página
            loadTabContent($('.nav-tabs .active'));

            // Manejar el evento de cambiar de pestaña
            $('.nav-tabs a').on('shown.bs.tab', function (e) {
                loadTabContent($(e.target));
            });

            function loadTabContent(tab) {
                var tabId = tab.attr('href').substring(1);
                $('#' + tabId).html('<div class="text-center my-5"><div class="spinner-border" role="status"><span class="sr-only">Cargando...</span></div></div>');
                $('#' + tabId).load(tabId + '.php');
            }
        });
    </script>
</body>
</html>
