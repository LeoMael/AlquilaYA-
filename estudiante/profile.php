<?php
// estudiante/profile.php
include '../includes/db_connect.php';
session_start();

// Función para generar un token CSRF
function generarTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Función para verificar el token CSRF
function verificarTokenCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Verificar si el usuario ha iniciado sesión y es un estudiante
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] != 'estudiante') {
    header("Location: ../login.php");
    exit();
}

$id_estudiante = $_SESSION['id_estudiante'];

// Obtener información del estudiante
$sql_estudiante = "SELECT * FROM estudiantes WHERE id_estudiante = ?";
$stmt = $conn->prepare($sql_estudiante);
$stmt->bind_param("i", $id_estudiante);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $estudiante = $result->fetch_assoc();
} else {
    echo "Error al obtener los datos del estudiante.";
    exit();
}

// Generar token CSRF
generarTokenCSRF();

// Procesar el formulario de actualización de datos personales
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_datos'])) {
    // Verificar el token CSRF
    if (!isset($_POST['csrf_token']) || !verificarTokenCSRF($_POST['csrf_token'])) {
        $error_datos = "Solicitud inválida. Por favor, intenta de nuevo.";
    } else {
        // Obtener y sanitizar los datos
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $telefono = trim($_POST['telefono']);

        // Validaciones
        if (empty($nombre) || empty($email)) {
            $error_datos = "Los campos Nombre y Email son obligatorios.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_datos = "Formato de email inválido.";
        } elseif (!empty($telefono) && !preg_match('/^\+?[0-9]{7,15}$/', $telefono)) {
            $error_datos = "Formato de teléfono inválido.";
        } else {
            // Actualizar la información en la base de datos
            $sql_update = "UPDATE estudiantes SET nombre = ?, email = ?, telefono = ? WHERE id_estudiante = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("sssi", $nombre, $email, $telefono, $id_estudiante);

            if ($stmt_update->execute()) {
                // Actualizar la información en la sesión si es necesario
                $_SESSION['nombre'] = $nombre;
                $_SESSION['email'] = $email;
                $_SESSION['telefono'] = $telefono;

                // Redirigir con mensaje de éxito
                $_SESSION['success_message'] = "Datos personales actualizados exitosamente.";
                header("Location: profile.php");
                exit();
            } else {
                $error_datos = "Error al actualizar los datos personales. Por favor, intenta de nuevo.";
            }
        }
    }
}

// Procesar el formulario de actualización de la cuenta (ejemplo: cambiar contraseña)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_cuenta'])) {
    // Verificar el token CSRF
    if (!isset($_POST['csrf_token']) || !verificarTokenCSRF($_POST['csrf_token'])) {
        $error_cuenta = "Solicitud inválida. Por favor, intenta de nuevo.";
    } else {
        // Obtener y sanitizar los datos
        $password_actual = $_POST['password_actual'];
        $password_nueva = $_POST['password_nueva'];
        $confirmar_password = $_POST['confirmar_password'];

        // Validaciones
        if (empty($password_actual) || empty($password_nueva) || empty($confirmar_password)) {
            $error_cuenta = "Todos los campos de contraseña son obligatorios.";
        } elseif ($password_nueva !== $confirmar_password) {
            $error_cuenta = "Las nuevas contraseñas no coinciden.";
        } elseif (strlen($password_nueva) < 6) {
            $error_cuenta = "La nueva contraseña debe tener al menos 6 caracteres.";
        } else {
            // Verificar la contraseña actual
            $sql_password = "SELECT password FROM estudiantes WHERE id_estudiante = ?";
            $stmt_password = $conn->prepare($sql_password);
            $stmt_password->bind_param("i", $id_estudiante);
            $stmt_password->execute();
            $result_password = $stmt_password->get_result();

            if ($result_password->num_rows > 0) {
                $user = $result_password->fetch_assoc();
                if (password_verify($password_actual, $user['password'])) {
                    // Actualizar la contraseña
                    $password_hash = password_hash($password_nueva, PASSWORD_BCRYPT);
                    $sql_update_password = "UPDATE estudiantes SET password = ? WHERE id_estudiante = ?";
                    $stmt_update_password = $conn->prepare($sql_update_password);
                    $stmt_update_password->bind_param("si", $password_hash, $id_estudiante);

                    if ($stmt_update_password->execute()) {
                        $_SESSION['success_message'] = "Contraseña actualizada exitosamente.";
                        header("Location: profile.php");
                        exit();
                    } else {
                        $error_cuenta = "Error al actualizar la contraseña. Por favor, intenta de nuevo.";
                    }
                } else {
                    $error_cuenta = "La contraseña actual es incorrecta.";
                }
            } else {
                $error_cuenta = "Error al verificar la contraseña actual.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil del Estudiante - AlquilaYA!</title>
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
            <h1><?php echo htmlspecialchars($estudiante['nombre']); ?></h1>
        </div>

        <!-- Mensajes de Éxito y Error -->
        <?php if (isset($error_datos)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_datos); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_cuenta)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_cuenta); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <!-- Navegación por Pestañas -->
        <ul class="nav nav-tabs" id="profileTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="historial-tab" data-bs-toggle="tab" data-bs-target="#historial" type="button" role="tab" aria-controls="historial" aria-selected="true">
                    <i class="fas fa-history me-2"></i>Historial de Cuartos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="comentarios-tab" data-bs-toggle="tab" data-bs-target="#comentarios" type="button" role="tab" aria-controls="comentarios" aria-selected="false">
                    <i class="fas fa-comments me-2"></i>Comentarios
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="datos-tab" data-bs-toggle="tab" data-bs-target="#datos" type="button" role="tab" aria-controls="datos" aria-selected="false">
                    <i class="fas fa-user me-2"></i>Datos Personales
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="cuenta-tab" data-bs-toggle="tab" data-bs-target="#cuenta" type="button" role="tab" aria-controls="cuenta" aria-selected="false">
                    <i class="fas fa-cogs me-2"></i>Cuenta
                </button>
            </li>
        </ul>

        <!-- Contenido de las Pestañas -->
        <div class="tab-content" id="profileTabContent">
            <!-- Historial de Cuartos -->
            <div class="tab-pane fade show active" id="historial" role="tabpanel" aria-labelledby="historial-tab">
                <div class="spinner-container" id="historial-loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
                <div id="historial-content" style="display: none;">
                    <?php include 'historial.php'; ?>
                </div>
            </div>
            <!-- Comentarios -->
            <div class="tab-pane fade" id="comentarios" role="tabpanel" aria-labelledby="comentarios-tab">
                <div class="spinner-container" id="comentarios-loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
                <div id="comentarios-content" style="display: none;">
                    <?php include 'comentarios.php'; ?>
                </div>
            </div>
            <!-- Datos Personales -->
            <div class="tab-pane fade" id="datos" role="tabpanel" aria-labelledby="datos-tab">
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
                const target = event.target.getAttribute('data-bs-target');
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
