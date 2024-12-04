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
<div class="mt-4">
    <h4><i class="fas fa-cogs me-2 text-primary"></i>Configuración de la Cuenta</h4>
    <form action="actualizar_cuenta.php" method="POST">
        <!-- Token CSRF -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        
        <div class="mb-3">
            <label for="password_actual" class="form-label"><i class="fas fa-key me-2 text-secondary"></i>Contraseña Actual:</label>
            <input type="password" name="password_actual" id="password_actual" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="password_nueva" class="form-label"><i class="fas fa-key me-2 text-secondary"></i>Contraseña Nueva:</label>
            <input type="password" name="password_nueva" id="password_nueva" class="form-control" required minlength="6">
            <div class="form-text">La contraseña debe tener al menos 6 caracteres.</div>
        </div>
        <div class="mb-3">
            <label for="confirmar_password" class="form-label"><i class="fas fa-key me-2 text-secondary"></i>Confirmar Contraseña:</label>
            <input type="password" name="confirmar_password" id="confirmar_password" class="form-control" required minlength="6">
        </div>
        <button type="submit" name="actualizar_cuenta" class="btn btn-primary"><i class="fas fa-save me-2"></i>Guardar Cambios</button>
    </form>
</div>