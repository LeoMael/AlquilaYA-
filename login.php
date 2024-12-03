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
    <!-- Enlaces a CSS de Bootstrap -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <!-- Navegación -->
    <nav class="navbar navbar-light bg-light">
        <a class="navbar-brand" href="index.php">AlquilaYA!</a>
    </nav>

    <div class="container mt-5">
        <h2>Iniciar Sesión</h2>
        <?php if (isset($error)) { echo '<div class="alert alert-danger">'.$error.'</div>'; } ?>
        <form action="" method="POST">
            <div class="form-group">
                <label for="tipo_usuario">Tipo de usuario:</label>
                <select name="tipo_usuario" class="form-control" required>
                    <option value="estudiante">Estudiante</option>
                    <option value="arrendador">Arrendador</option>
                </select>
            </div>
            <div class="form-group">
                <label for="email">Correo Electrónico:</label>
                <input type="email" name="email" class="form-control" placeholder="Ingresa tu email" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" name="password" class="form-control" placeholder="Ingresa tu contraseña" required>
            </div>
            <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
            <a href="register.php" class="btn btn-link">Registrarse</a>
        </form>
    </div>

    <!-- Scripts de Bootstrap -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.min.js"></script>
</body>
</html>
