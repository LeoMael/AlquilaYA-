<?php
// arrendador/delete_room.php
include '../includes/db_connect.php';
session_start();

// Verificar si el usuario ha iniciado sesión y es un arrendador
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] != 'arrendador') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id_cuarto'])) {
    $id_cuarto = (int)$_GET['id_cuarto'];
} else {
    header("Location: dashboard.php");
    exit();
}

// Iniciar una transacción
$conn->begin_transaction();

try {
    // 1. Eliminar las valoraciones relacionadas con el cuarto
    $sql_delete_valoraciones = "DELETE FROM valoracion_cuartos WHERE id_cuarto = ?";
    $stmt_valoraciones = $conn->prepare($sql_delete_valoraciones);
    $stmt_valoraciones->bind_param("i", $id_cuarto);
    $stmt_valoraciones->execute();

    // 2. Eliminar los detalles del cuarto
    $sql_delete_detalles = "DELETE FROM detalles_cuartos WHERE id_cuarto = ?";
    $stmt_detalles = $conn->prepare($sql_delete_detalles);
    $stmt_detalles->bind_param("i", $id_cuarto);
    $stmt_detalles->execute();

    // 3. Eliminar el cuarto
    $sql_delete_cuarto = "DELETE FROM cuartos WHERE id_cuarto = ?";
    $stmt_cuarto = $conn->prepare($sql_delete_cuarto);
    $stmt_cuarto->bind_param("i", $id_cuarto);
    $stmt_cuarto->execute();

    // Confirmar la transacción
    $conn->commit();

    header("Location: dashboard.php?message=Cuarto eliminado exitosamente");
    exit();
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    $conn->rollback();
    echo "Error al eliminar el cuarto: " . $e->getMessage();
}
?>
