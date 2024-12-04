<?php
// login.php
include 'includes/db_connect.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo_usuario = $_POST['tipo_usuario'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Dependiendo del tipo de usuario, verificamos en la tabla correspondiente
    if ($tipo_usuario == 'estudiante') {
        $stmt = $conn->prepare("SELECT id_estudiante, nombre, password FROM estudiantes WHERE email = ?");
    } else {
        $stmt = $conn->prepare("SELECT id_arrendador, nombre, password FROM arrendadores WHERE email = ?");
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();

    if ($usuario && password_verify($password, $usuario['password'])) {
        // Inicio de sesión exitoso
        $_SESSION['tipo_usuario'] = $tipo_usuario;
        $_SESSION['nombre'] = $usuario['nombre'];
        if ($tipo_usuario == 'estudiante') {
            $_SESSION['id_estudiante'] = $usuario['id_estudiante'];
            header("Location: estudiante/profile.php");
        } else {
            $_SESSION['id_arrendador'] = $usuario['id_arrendador'];
            header("Location: arrendador/dashboard.php");
        }
        exit();
    } else {
        $error = "Correo electrónico o contraseña incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión - AlquilaYA!</title>
    <!-- Enlaces a CSS de Bootstrap 5 para estilos modernos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Enlace a la fuente Kaushan Script -->
    <link href="https://fonts.googleapis.com/css2?family=Kaushan+Script&display=swap" rel="stylesheet">
    
    <style>
        /* Estilos personalizados */
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 25%, #e9ecef 100%);
            height: 100vh;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
        }
        .btn-primary {
            background-color: #dc3545;
            border: none;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #c82333;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #dc3545;
        }
        .form-label {
            font-weight: 500;
        }
        .footer-link {
            color: #dc3545;
            text-decoration: none;
        }
        .footer-link:hover {
            text-decoration: underline;
        }
        .alert-custom {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
            border-radius: 5px;
            padding: 10px 20px;
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
        .titulo-puno {
            font-size: 1.4rem;
            font-family: 'Josefin Slab', serif;
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

    <div class="container login-container mt-5">
        <h3 class="text-center mb-4"><i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión</h3>
        <?php if (isset($error)) { echo '<div class="alert alert-danger">'.$error.'</div>'; } ?>
        <form action="" method="POST">
            <div class="form-group mb-3">
                <label for="tipo_usuario" class="form-label"><i class="fas fa-user-tag me-2"></i>Tipo de usuario:</label>
                <select name="tipo_usuario" id="tipo_usuario" class="form-select" required>
                    <option value="" disabled selected>Selecciona tu tipo de usuario</option>
                    <option value="estudiante">Estudiante</option>
                    <option value="arrendador">Arrendador</option>
                </select>
            </div>
            <div class="form-group mb-3">
                <label for="email" class="form-label"><i class="fas fa-envelope me-2"></i>Correo Electrónico:</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="Ingresa tu email" required>
            </div>
            <div class="form-group mb-4">
            <label for="password" class="form-label"><i class="fas fa-lock me-2"></i>Contraseña:</label>
            <input type="password" name="password" id="password" class="form-control" placeholder="Ingresa tu contraseña" required>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-circle me-2"></i>Iniciar Sesión</button>
            </div>
        </form>
        <p class="text-center mt-3">¿No tienes una cuenta? <a href="register.php" class="footer-link"><i class="fas fa-user-plus me-1"></i>Regístrate aquí</a>.</p>
    </div>
    
    <!-- Scripts de Bootstrap -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.min.js"></script>
</body>
</html>
