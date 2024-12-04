<?php
// arrendador/delete_house.php
include '../includes/db_connect.php';
session_start();

// Verificar si el usuario ha iniciado sesión y es un arrendador
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] != 'arrendador') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id_casa'])) {
    $id_casa = (int)$_GET['id_casa'];
} else {
    header("Location: dashboard.php");
    exit();
}

// Iniciar una transacción
$conn->begin_transaction();

try {
    // 1. Obtener todos los cuartos asociados a la casa
    $sql_get_cuartos = "SELECT id_cuarto FROM cuartos WHERE id_casa = ?";
    $stmt_get_cuartos = $conn->prepare($sql_get_cuartos);
    $stmt_get_cuartos->bind_param("i", $id_casa);
    $stmt_get_cuartos->execute();
    $result_cuartos = $stmt_get_cuartos->get_result();

    while ($cuarto = $result_cuartos->fetch_assoc()) {
        $id_cuarto = $cuarto['id_cuarto'];

        // 2. Eliminar las valoraciones relacionadas con el cuarto
        $sql_delete_valoraciones = "DELETE FROM valoracion_cuartos WHERE id_cuarto = ?";
        $stmt_valoraciones = $conn->prepare($sql_delete_valoraciones);
        $stmt_valoraciones->bind_param("i", $id_cuarto);
        $stmt_valoraciones->execute();

        // 3. Eliminar los detalles del cuarto
        $sql_delete_detalles = "DELETE FROM detalles_cuartos WHERE id_cuarto = ?";
        $stmt_detalles = $conn->prepare($sql_delete_detalles);
        $stmt_detalles->bind_param("i", $id_cuarto);
        $stmt_detalles->execute();

        // 4. Eliminar el cuarto
        $sql_delete_cuarto = "DELETE FROM cuartos WHERE id_cuarto = ?";
        $stmt_cuarto = $conn->prepare($sql_delete_cuarto);
        $stmt_cuarto->bind_param("i", $id_cuarto);
        $stmt_cuarto->execute();
    }

    // 5. Eliminar las valoraciones relacionadas con la casa (si existen)
    $sql_delete_valoraciones_casa = "DELETE FROM valoracion_casas WHERE id_casa = ?";
    $stmt_valoraciones_casa = $conn->prepare($sql_delete_valoraciones_casa);
    $stmt_valoraciones_casa->bind_param("i", $id_casa);
    $stmt_valoraciones_casa->execute();

    // 6. Eliminar la casa
    $sql_delete_casa = "DELETE FROM casas WHERE id_casa = ?";
    $stmt_casa = $conn->prepare($sql_delete_casa);
    $stmt_casa->bind_param("i", $id_casa);
    $stmt_casa->execute();

    // Confirmar la transacción
    $conn->commit();

    header("Location: dashboard.php?message=Casa eliminada exitosamente");
    exit();
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    $conn->rollback();
    echo "Error al eliminar la casa: " . $e->getMessage();
}
?>
