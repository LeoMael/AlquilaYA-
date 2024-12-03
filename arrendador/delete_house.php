<?php
// arrendador/delete_house.php
include '../includes/db_connect.php';
session_start();

// Verificar si el usuario ha iniciado sesión y es un arrendador
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] != 'arrendador') {
    header("Location: ../login.php");
    exit();
}

$id_arrendador = $_SESSION['id_arrendador'];

if (isset($_GET['id_casa'])) {
    $id_casa = (int)$_GET['id_casa'];
} else {
    header("Location: dashboard.php");
    exit();
}

// Verificar que la casa pertenece al arrendador
$sql_casa = "SELECT * FROM casas WHERE id_casa = ? AND id_arrendador = ?";
$stmt_casa = $conn->prepare($sql_casa);
$stmt_casa->bind_param("ii", $id_casa, $id_arrendador);
$stmt_casa->execute();
$result_casa = $stmt_casa->get_result();

if ($result_casa->num_rows == 0) {
    echo "No tienes permiso para eliminar esta casa.";
    exit();
} else {
    $casa = $result_casa->fetch_assoc();
}

// Eliminar la casa y sus cuartos asociados
// Primero, eliminar las imágenes de los cuartos y la casa
// Eliminar imágenes de cuartos
$sql_cuartos = "SELECT * FROM cuartos WHERE id_casa = ?";
$stmt_cuartos = $conn->prepare($sql_cuartos);
$stmt_cuartos->bind_param("i", $id_casa);
$stmt_cuartos->execute();
$result_cuartos = $stmt_cuartos->get_result();

while ($cuarto = $result_cuartos->fetch_assoc()) {
    if (!empty($cuarto['imagen']) && file_exists('../uploads/' . $cuarto['imagen'])) {
        unlink('../uploads/' . $cuarto['imagen']);
    }
}

// Eliminar imágenes de la casa
if (!empty($casa['imagen']) && file_exists('../uploads/' . $casa['imagen'])) {
    unlink('../uploads/' . $casa['imagen']);
}

// Eliminar registros de la base de datos
// Eliminar cuartos
$sql_delete_cuartos = "DELETE FROM cuartos WHERE id_casa = ?";
$stmt_delete_cuartos = $conn->prepare($sql_delete_cuartos);
$stmt_delete_cuartos->bind_param("i", $id_casa);
$stmt_delete_cuartos->execute();

// Eliminar la casa
$sql_delete_casa = "DELETE FROM casas WHERE id_casa = ?";
$stmt_delete_casa = $conn->prepare($sql_delete_casa);
$stmt_delete_casa->bind_param("i", $id_casa);

if ($stmt_delete_casa->execute()) {
    header("Location: dashboard.php");
    exit();
} else {
    echo "Error al eliminar la casa.";
}
?>
