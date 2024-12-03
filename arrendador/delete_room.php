<?php
// arrendador/delete_room.php
include '../includes/db_connect.php';
session_start();

// Verificar si el usuario ha iniciado sesión y es un arrendador
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] != 'arrendador') {
    header("Location: ../login.php");
    exit();
}

$id_arrendador = $_SESSION['id_arrendador'];

if (isset($_GET['id_cuarto'])) {
    $id_cuarto = (int)$_GET['id_cuarto'];
} else {
    header("Location: dashboard.php");
    exit();
}

// Verificar que el cuarto pertenece al arrendador
$sql_cuarto = "SELECT cuartos.*, casas.id_arrendador FROM cuartos JOIN casas ON cuartos.id_casa = casas.id_casa WHERE cuartos.id_cuarto = ? AND casas.id_arrendador = ?";
$stmt_cuarto = $conn->prepare($sql_cuarto);
$stmt_cuarto->bind_param("ii", $id_cuarto, $id_arrendador);
$stmt_cuarto->execute();
$result_cuarto = $stmt_cuarto->get_result();

if ($result_cuarto->num_rows == 0) {
    echo "No tienes permiso para eliminar este cuarto.";
    exit();
} else {
    $cuarto = $result_cuarto->fetch_assoc();
}

// Eliminar la imagen del cuarto si existe
if (!empty($cuarto['imagen']) && file_exists('../uploads/' . $cuarto['imagen'])) {
    unlink('../uploads/' . $cuarto['imagen']);
}

// Eliminar registros de la base de datos
// Eliminar detalles del cuarto
$sql_delete_detalle = "DELETE FROM detalles_cuartos WHERE id_cuarto = ?";
$stmt_delete_detalle = $conn->prepare($sql_delete_detalle);
$stmt_delete_detalle->bind_param("i", $id_cuarto);
$stmt_delete_detalle->execute();

// Eliminar el cuarto
$sql_delete_cuarto = "DELETE FROM cuartos WHERE id_cuarto = ?";
$stmt_delete_cuarto = $conn->prepare($sql_delete_cuarto);
$stmt_delete_cuarto->bind_param("i", $id_cuarto);

if ($stmt_delete_cuarto->execute()) {
    header("Location: dashboard.php");
    exit();
} else {
    echo "Error al eliminar el cuarto.";
}
?>
