<?php
// estudiante/historial.php
include '../includes/db_connect.php';
session_start();

$id_estudiante = $_SESSION['id_estudiante'];

// Obtener el historial de cuartos donde el estudiante ha estado
$sql_historial = "SELECT cuartos.*, casas.direccion, alquileres.fecha_inicio, alquileres.fecha_fin
                  FROM alquileres
                  JOIN cuartos ON alquileres.id_cuarto = cuartos.id_cuarto
                  JOIN casas ON cuartos.id_casa = casas.id_casa
                  WHERE alquileres.id_estudiante = ?
                  ORDER BY alquileres.fecha_inicio DESC";
$stmt_historial = $conn->prepare($sql_historial);
$stmt_historial->bind_param("i", $id_estudiante);
$stmt_historial->execute();
$result_historial = $stmt_historial->get_result();

if ($result_historial->num_rows > 0) {
    while ($cuarto = $result_historial->fetch_assoc()) {
        echo '<div class="card mb-3">';
        echo '<div class="card-body">';
        echo '<h5 class="card-title">Cuarto en piso ' . $cuarto['piso'] . ' - ' . htmlspecialchars($cuarto['direccion']) . '</h5>';
        echo '<p class="card-text"><strong>Fecha de inicio:</strong> ' . date('d/m/Y', strtotime($cuarto['fecha_inicio'])) . '</p>';
        echo '<p class="card-text"><strong>Fecha de fin:</strong> ' . ($cuarto['fecha_fin'] ? date('d/m/Y', strtotime($cuarto['fecha_fin'])) : 'Actualidad') . '</p>';
        echo '</div>';
        echo '</div>';
    }
} else {
    echo '<p>No tienes historial de cuartos.</p>';
}
?>
