<?php
// arrendador/edit_profile.php
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

// Generar token CSRF
generarTokenCSRF();

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_perfil'])) {
    // Verificar el token CSRF
    if (!isset($_POST['csrf_token']) || !verificarTokenCSRF($_POST['csrf_token'])) {
        $error = "Solicitud inválida. Por favor, intenta de nuevo.";
    } else {
        // Obtener y sanitizar los datos
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $celular = trim($_POST['celular']);

        // Validaciones
        if (empty($nombre) || empty($email)) {
            $error = "Los campos Nombre y Email son obligatorios.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Formato de email inválido.";
        } elseif (!empty($celular) && !preg_match('/^\+?[0-9]{7,15}$/', $celular)) {
            $error = "Formato de celular inválido.";
        } else {
            // Actualizar la información en la base de datos
            $sql_update = "UPDATE arrendadores SET nombre = ?, email = ?, celular = ? WHERE id_arrendador = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("sssi", $nombre, $email, $celular, $id_arrendador);

            if ($stmt_update->execute()) {
                // Actualizar la información en la sesión si es necesario
                $_SESSION['nombre'] = $nombre;
                $_SESSION['email'] = $email;
                $_SESSION['celular'] = $celular;

                // Redirigir con mensaje de éxito
                $_SESSION['success_message'] = "Perfil actualizado exitosamente.";
                header("Location: profile.php");
                exit();
            } else {
                $error = "Error al actualizar el perfil. Por favor, intenta de nuevo.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Perfil - Arrendador - AlquilaYA!</title>
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
            max-width: 600px;
            margin: 50px auto;
        }
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-header h1 {
            font-size: 2.5em;
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
                        <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Mi Perfil</a>
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
            <h1>Editar Perfil</h1>
        </div>

        <!-- Mensajes de Éxito y Error -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
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

        <!-- Formulario de Edición de Perfil -->
        <form action="" method="POST">
            <!-- Token CSRF -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="mb-3">
                <label for="nombre" class="form-label"><i class="fas fa-user me-2 text-primary"></i>Nombre:</label>
                <input type="text" name="nombre" id="nombre" class="form-control" value="<?php echo htmlspecialchars($arrendador['nombre']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label"><i class="fas fa-envelope me-2 text-primary"></i>Email:</label>
                <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($arrendador['email']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="celular" class="form-label"><i class="fas fa-phone me-2 text-primary"></i>Celular:</label>
                <input type="tel" name="celular" id="celular" class="form-control" value="<?php echo htmlspecialchars($arrendador['celular']); ?>" pattern="^\+?[0-9]{7,15}$" placeholder="Ej: +51987654321">
                <div class="form-text">Ingresa un número válido con 7 a 15 dígitos. Opcional.</div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" name="editar_perfil" class="btn btn-primary"><i class="fas fa-save me-2"></i>Guardar Cambios</button>
                <a href="profile.php" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Cancelar</a>
            </div>
        </form>
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
        // Validación adicional del lado del cliente (opcional)
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form');
            form.addEventListener('submit', function (event) {
                const celular = document.getElementById('celular').value;
                const celularPattern = /^\+?[0-9]{7,15}$/;
                if (celular && !celularPattern.test(celular)) {
                    event.preventDefault();
                    event.stopPropagation();
                    alert('Por favor, ingresa un número de celular válido con 7 a 15 dígitos.');
                }
            });
        });
    </script>
</body>
</html>
