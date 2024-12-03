<?php
// estudiante/datos.php
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

?>
<div class="card mb-4">
    <div class="card-body">
        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($estudiante['nombre']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($estudiante['email']); ?></p>
        <p><strong>Celular:</strong> <?php echo htmlspecialchars($estudiante['celular']); ?></p>
        <p><strong>DNI:</strong> <?php echo htmlspecialchars($estudiante['dni']); ?></p>
        <a href="edit_profile.php" class="btn btn-primary">Editar Datos Personales</a>
    </div>
</div>
