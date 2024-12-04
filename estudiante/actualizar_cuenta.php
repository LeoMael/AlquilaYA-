<?php
// estudiante/actualizar_cuenta.php
include '../includes/db_connect.php';
session_start();

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

// Procesar el formulario de cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_cuenta'])) {
    // Verificar el token CSRF
    if (!isset($_POST['csrf_token']) || !verificarTokenCSRF($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Solicitud inválida. Por favor, intenta de nuevo.";
        header("Location: profile.php#cuenta");
        exit();
    }

    // Obtener y sanitizar los datos
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $confirmar_password = $_POST['confirmar_password'];

    // Validaciones
    if (empty($password_actual) || empty($password_nueva) || empty($confirmar_password)) {
        $_SESSION['error_message'] = "Todos los campos de contraseña son obligatorios.";
        header("Location: profile.php#cuenta");
        exit();
    }

    if ($password_nueva !== $confirmar_password) {
        $_SESSION['error_message'] = "Las nuevas contraseñas no coinciden.";
        header("Location: profile.php#cuenta");
        exit();
    }

    if (strlen($password_nueva) < 6) {
        $_SESSION['error_message'] = "La nueva contraseña debe tener al menos 6 caracteres.";
        header("Location: profile.php#cuenta");
        exit();
    }

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
                header("Location: profile.php#cuenta");
                exit();
            } else {
                $_SESSION['error_message'] = "Error al actualizar la contraseña. Por favor, intenta de nuevo.";
                header("Location: profile.php#cuenta");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "La contraseña actual es incorrecta.";
            header("Location: profile.php#cuenta");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Error al verificar la contraseña actual.";
        header("Location: profile.php#cuenta");
        exit();
    }
} else {
    header("Location: profile.php");
    exit();
}
?>
