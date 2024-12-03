<?php
// includes/db_connect.php

$servername = "localhost";
$username = "root";
$password = ""; // Contraseña vacía
$dbname = "alquilaya";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

$conn->set_charset("utf8");
?>
