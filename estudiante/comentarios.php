<?php
// estudiante/comentarios.php
include '../includes/db_connect.php';
session_start();

$id_estudiante = $_SESSION['id_estudiante'];

// Obtener los comentarios realizados por el estudiante
$sql_comentarios = "SELECT valoracion_casas.id_valoracion_casa AS id_valoracion, 'Casa' AS tipo, valoracion_casas.comentario, valoracion_casas.fecha, casas.direccion, casas.id_casa
                    FROM valoracion_casas
                    JOIN casas ON valoracion_casas.id_casa = casas.id_casa
                    WHERE valoracion_casas.id_estudiante = ?
                    UNION ALL
                    SELECT valoracion_cuartos.id_valoracion_cuarto AS id_valoracion, 'Cuarto' AS tipo, valoracion_cuartos.comentario, valoracion_cuartos.fecha, casas.direccion, cuartos.id_cuarto
                    FROM valoracion_cuartos
                    JOIN cuartos ON valoracion_cuartos.id_cuarto = cuartos.id_cuarto
                    JOIN casas ON cuartos.id_casa = casas.id_casa
                    WHERE valoracion_cuartos.id_estudiante = ?
                    ORDER BY fecha DESC";
$stmt_comentarios = $conn->prepare($sql_comentarios);
$stmt_comentarios->bind_param("ii", $id_estudiante, $id_estudiante);
$stmt_comentarios->execute();
$result_comentarios = $stmt_comentarios->get_result();

if ($result_comentarios->num_rows > 0) {
    while ($comentario = $result_comentarios->fetch_assoc()) {
        echo '<div class="media mb-3">';
        echo '<div class="media-body">';
        echo '<h5 class="mt-0">' . htmlspecialchars($comentario['tipo']) . ' - ' . htmlspecialchars($comentario['direccion']) . '</h5>';
        echo '<p>' . htmlspecialchars($comentario['comentario']) . '</p>';
        echo '<small class="text-muted">Fecha: ' . date('d/m/Y', strtotime($comentario['fecha'])) . '</small>';
        // Enlaces para editar, eliminar y ver el lugar
        if ($comentario['tipo'] == 'Casa') {
            $id = $comentario['id_valoracion'];
            $tipo = 'casa';
            $id_lugar = $comentario['id_casa'];
            $link_lugar = '../house_details.php?id_casa=' . $id_lugar;
        } else {
            $id = $comentario['id_valoracion'];
            $tipo = 'cuarto';
            $id_lugar = $comentario['id_casa'];
            $link_lugar = '../room_details.php?id_cuarto=' . $id_lugar;
        }
        echo '<div class="mt-2">';
        echo '<a href="' . $link_lugar . '" class="btn btn-sm btn-info mr-2">Ver Publicación</a>';
        echo '<a href="edit_comment.php?tipo=' . $tipo . '&id=' . $id . '" class="btn btn-sm btn-primary mr-2">Editar</a>';
        echo '<a href="delete_comment.php?tipo=' . $tipo . '&id=' . $id . '" class="btn btn-sm btn-danger">Eliminar</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
} else {
    echo '<p>No has realizado comentarios aún.</p>';
}
?>
