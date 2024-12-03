<?php
// register.php
include 'includes/db_connect.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo_usuario = $_POST['tipo_usuario'];
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $dni = $_POST['dni'];
    $celular = $_POST['celular'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Verificar si el usuario ya está registrado
    if ($tipo_usuario == 'estudiante') {
        $stmt_check = $conn->prepare("SELECT * FROM estudiantes WHERE dni = ? OR email = ?");
    } else {
        $stmt_check = $conn->prepare("SELECT * FROM arrendadores WHERE dni = ? OR email = ?");
    }
    $stmt_check->bind_param("ss", $dni, $email);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $error = "El usuario ya está registrado con ese DNI o correo electrónico.";
    } else {
        if ($tipo_usuario == 'estudiante') {
            $stmt = $conn->prepare("INSERT INTO estudiantes (nombre, apellidos, dni, celular, email, password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $nombre, $apellido, $dni, $celular, $email, $password);
        } else {
            $stmt = $conn->prepare("INSERT INTO arrendadores (nombre, apellido, dni, celular, email, password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $nombre, $apellido, $dni, $celular, $email, $password);
        }

        if ($stmt->execute()) {
            $success = "Registro exitoso. Puedes iniciar sesión ahora.";
        } else {
            $error = "Error en el registro: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrarse - AlquilaYA!</title>
    <!-- Enlaces a CSS de Bootstrap -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <!-- Navegación -->
    <nav class="navbar navbar-light bg-light">
        <a class="navbar-brand" href="index.php">AlquilaYA!</a>
    </nav>

    <div class="container mt-5">
        <h2>Registrarse</h2>
        <?php
        if (isset($error)) {
            echo '<div class="alert alert-danger">'.$error.'</div>';
        }
        if (isset($success)) {
            echo '<div class="alert alert-success">'.$success.'</div>';
        }
        ?>
        <form action="" method="POST">
            <div class="form-group">
                <label for="tipo_usuario">Tipo de usuario:</label>
                <select name="tipo_usuario" class="form-control" required>
                    <option value="estudiante">Estudiante</option>
                    <option value="arrendador">Arrendador</option>
                </select>
            </div>
            <div class="form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" name="nombre" class="form-control" placeholder="Ingresa tu nombre" required>
            </div>
            <div class="form-group">
                <label for="apellido">Apellido:</label>
                <input type="text" name="apellido" class="form-control" placeholder="Ingresa tu apellido" required>
            </div>
            <div class="form-group">
                <label for="dni">DNI:</label>
                <input type="text" name="dni" class="form-control" placeholder="Ingresa tu DNI" required>
            </div>
            <div class="form-group">
                <label for="celular">Celular:</label>
                <input type="text" name="celular" class="form-control" placeholder="Ingresa tu número de celular" required>
            </div>
            <div class="form-group">
                <label for="email">Correo Electrónico:</label>
                <input type="email" name="email" class="form-control" placeholder="Ingresa tu email" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" name="password" class="form-control" placeholder="Ingresa una contraseña" required>
            </div>
            <button type="submit" class="btn btn-primary">Registrarse</button>
            <a href="login.php" class="btn btn-link">Iniciar Sesión</a>
        </form>
    </div>

    <!-- Scripts de Bootstrap -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.min.js"></script>
</body>
</html>
