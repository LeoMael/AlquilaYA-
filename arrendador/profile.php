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
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-family: 'Kaushan Script', cursive;
            font-size: 1.8rem;
            color: #dc3545 !important;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .navbar-nav .nav-link {
            font-weight: 500;
            color: #495057;
            transition: color 0.3s;
        }
        .navbar-nav .nav-link:hover {
            color: #dc3545 !important;
        }
        .profile-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            max-width: 900px;
            margin: 50px auto;
        }
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
        .spinner-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
        }
    </style>
</head>
<body>
    <!-- Barra de Navegación -->
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
                        <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Panel de Control</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php"><i class="fas fa-user me-2"></i>Mi Perfil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenedor Principal -->
    <div class="container profile-container">
        <!-- Encabezado del Perfil -->
        <div class="profile-header">
            <h1><?php echo htmlspecialchars($arrendador['nombre']); ?></h1>
        </div>

        <!-- Navegación por Pestañas -->
        <ul class="nav nav-tabs" id="profileTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="datos-tab" data-bs-toggle="tab" data-bs-target="#datos" type="button" role="tab" aria-controls="datos" aria-selected="true">
                    <i class="fas fa-info-circle me-2"></i>Datos Personales
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="cuenta-tab" data-bs-toggle="tab" data-bs-target="#cuenta" type="button" role="tab" aria-controls="cuenta" aria-selected="false">
                    <i class="fas fa-cogs me-2"></i>Cuenta
                </button>
            </li>
            <!-- Puedes agregar más pestañas si lo deseas -->
        </ul>

        <!-- Contenido de las Pestañas -->
        <div class="tab-content" id="profileTabContent">
            <!-- Datos Personales -->
            <div class="tab-pane fade show active" id="datos" role="tabpanel" aria-labelledby="datos-tab">
                <div class="spinner-container" id="datos-loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
                <div id="datos-content" style="display: none;">
                    <?php include 'datos.php'; ?>
                </div>
            </div>
            <!-- Cuenta -->
            <div class="tab-pane fade" id="cuenta" role="tabpanel" aria-labelledby="cuenta-tab">
                <div class="spinner-container" id="cuenta-loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
                <div id="cuenta-content" style="display: none;">
                    <?php include 'cuenta.php'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">&copy; <?php echo date("Y"); ?> AlquilaYA! - Todos los derechos reservados.</span>
        </div>
    </footer>

    <!-- Scripts de Bootstrap 5 y JavaScript Moderno -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const profileTab = document.getElementById('profileTab');

            profileTab.addEventListener('shown.bs.tab', function (event) {
                const target = event.target.getAttribute('data-bs-target'); // Pestaña activa
                const loadingDiv = document.getElementById(`${target.substring(1)}-loading`);
                const contentDiv = document.getElementById(`${target.substring(1)}-content`);

                // Mostrar el spinner de carga
                loadingDiv.style.display = 'flex';
                contentDiv.style.display = 'none';

                // Cargar contenido dinámicamente usando Fetch API
                fetch(`${target.substring(1)}.php`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error al cargar el contenido.');
                        }
                        return response.text();
                    })
                    .then(data => {
                        contentDiv.innerHTML = data;
                        loadingDiv.style.display = 'none';
                        contentDiv.style.display = 'block';
                    })
                    .catch(error => {
                        loadingDiv.innerHTML = `<div class="alert alert-danger" role="alert">
                            ${error.message}
                        </div>`;
                    });
            });

            // Cargar el contenido de la pestaña activa al cargar la página
            const activeTab = profileTab.querySelector('.nav-link.active');
            if (activeTab) {
                const target = activeTab.getAttribute('data-bs-target');
                const loadingDiv = document.getElementById(`${target.substring(1)}-loading`);
                const contentDiv = document.getElementById(`${target.substring(1)}-content`);

                // Mostrar el spinner de carga
                loadingDiv.style.display = 'flex';
                contentDiv.style.display = 'none';

                // Cargar contenido dinámicamente usando Fetch API
                fetch(`${target.substring(1)}.php`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error al cargar el contenido.');
                        }
                        return response.text();
                    })
                    .then(data => {
                        contentDiv.innerHTML = data;
                        loadingDiv.style.display = 'none';
                        contentDiv.style.display = 'block';
                    })
                    .catch(error => {
                        loadingDiv.innerHTML = `<div class="alert alert-danger" role="alert">
                            ${error.message}
                        </div>`;
                    });
            }
        });
    </script>
</body>
</html>
