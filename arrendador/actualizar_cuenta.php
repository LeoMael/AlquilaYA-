<?php
// arrendador/actualizar_cuenta.php
include '../includes/db_connect.php';
session_start();

// Verificar si el usuario ha iniciado sesión y es un arrendador
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] != 'arrendador') {
    header("Location: ../login.php");
    exit();
}

$id_arrendador = $_SESSION['id_arrendador'];

// Procesar el formulario de actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obtener y sanitizar los datos
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    // Agrega más campos según tu necesidad

    // Validaciones básicas
    if (empty($email) || empty($telefono)) {
        $_SESSION['error_message'] = "Todos los campos son obligatorios.";
        header("Location: profile.php#cuenta");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Formato de email inválido.";
        header("Location: profile.php#cuenta");
        exit();
    }

    // Actualizar la información en la base de datos
    $sql_update = "UPDATE arrendadores SET email = ?, telefono = ? WHERE id_arrendador = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ssi", $email, $telefono, $id_arrendador);

    if ($stmt_update->execute()) {
        $_SESSION['success_message'] = "Datos de la cuenta actualizados exitosamente.";
        header("Location: profile.php#cuenta");
        exit();
    } else {
        $_SESSION['error_message'] = "Error al actualizar los datos de la cuenta.";
        header("Location: profile.php#cuenta");
        exit();
    }
} else {
    header("Location: profile.php");
    exit();
}
?>
