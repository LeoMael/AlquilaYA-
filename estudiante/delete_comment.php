<?php
// estudiante/delete_comment.php
include '../includes/db_connect.php';
session_start();

// Verificar si el usuario ha iniciado sesión y es un estudiante
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] != 'estudiante') {
    header("Location: ../login.php");
    exit();
}

$id_estudiante = $_SESSION['id_estudiante'];

if (isset($_GET['tipo'], $_GET['id'])) {
    $tipo = $_GET['tipo'];
    $id_valoracion = (int)$_GET['id'];
} else {
    header("Location: profile.php");
    exit();
}

// Eliminar el comentario
if ($tipo == 'casa') {
    $sql_delete = "DELETE FROM valoracion_casas WHERE id_valoracion_casa = ? AND id_estudiante = ?";
} else {
    $sql_delete = "DELETE FROM valoracion_cuartos WHERE id_valoracion_cuarto = ? AND id_estudiante = ?";
}
$stmt_delete = $conn->prepare($sql_delete);
$stmt_delete->bind_param("ii", $id_valoracion, $id_estudiante);

if ($stmt_delete->execute()) {
    header("Location: profile.php");
    exit();
} else {
    echo "Error al eliminar el comentario.";
}
?>
