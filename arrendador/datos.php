<?php
// arrendador/datos.php
include '../includes/db_connect.php';
session_start();

$id_arrendador = $_SESSION['id_arrendador'];

// Obtener información del arrendador
$sql_arrendador = "SELECT * FROM arrendadores WHERE id_arrendador = ?";
$stmt = $conn->prepare($sql_arrendador);
$stmt->bind_param("i", $id_arrendador);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $arrendador = $result->fetch_assoc();
} else {
    echo "Error al obtener los datos del arrendador.";
    exit();
}
?>

<div class="card mb-4">
    <div class="mb-3">
        <h4><i class="fas fa-user-circle me-2 text-primary"></i>Información Personal</h4>
        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($arrendador['nombre']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($arrendador['email']); ?></p>
        <p><strong>Celular:</strong> <?php echo htmlspecialchars($arrendador['celular']); ?></p>
        <!-- Agrega más campos según tu base de datos -->
        <a href="edit_profile.php" class="btn btn-primary">Editar Datos Personales</a>
    </div>
</div>
