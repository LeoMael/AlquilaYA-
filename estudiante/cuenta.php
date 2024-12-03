<?php
// estudiante/cuenta.php
include '../includes/db_connect.php';
session_start();

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

// Procesar el cambio de contraseña y actualización de email
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_cuenta'])) {
    $email = $_POST['email'];
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $password_confirmar = $_POST['password_confirmar'];

    // Validar el email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El email ingresado no es válido.";
    } else {
        // Verificar que la contraseña actual sea correcta
        if (password_verify($password_actual, $estudiante['password'])) {
            if ($password_nueva != $password_confirmar) {
                $error = "Las nuevas contraseñas no coinciden.";
            } else {
                // Encriptar la nueva contraseña
                $password_nueva_hash = password_hash($password_nueva, PASSWORD_DEFAULT);

                // Actualizar el email y la contraseña en la base de datos
                $sql_update = "UPDATE estudiantes SET email = ?, password = ? WHERE id_estudiante = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("ssi", $email, $password_nueva_hash, $id_estudiante);

                if ($stmt_update->execute()) {
                    $success = "Cuenta actualizada correctamente.";
                } else {
                    $error = "Error al actualizar la cuenta.";
                }
            }
        } else {
            $error = "La contraseña actual es incorrecta.";
        }
    }
}
?>
<?php if (isset($error)) { echo '<div class="alert alert-danger">'.$error.'</div>'; } ?>
<?php if (isset($success)) { echo '<div class="alert alert-success">'.$success.'</div>'; } ?>
<form action="" method="POST">
    <div class="form-group">
        <label for="email">Email Actual:</label>
        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($estudiante['email']); ?>" required>
    </div>
    <div class="form-group">
        <label for="password_actual">Contraseña Actual:</label>
        <input type="password" name="password_actual" class="form-control" required>
    </div>
    <div class="form-group">
        <label for="password_nueva">Nueva Contraseña:</label>
        <input type="password" name="password_nueva" class="form-control" required>
    </div>
    <div class="form-group">
        <label for="password_confirmar">Confirmar Nueva Contraseña:</label>
        <input type="password" name="password_confirmar" class="form-control" required>
    </div>
    <button type="submit" name="actualizar_cuenta" class="btn btn-primary">Actualizar Cuenta</button>
</form>
