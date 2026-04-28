<?php
/**
 * SETUP ÚNICO - Crear usuario administrador
 * Acceder UNA SOLA VEZ: http://localhost:8000/api/setup.php
 * Luego eliminar o proteger este archivo.
 */
include 'db.php';

$usuario  = 'admin';
$nombre   = 'Administrador';
$password = 'Admin123!';
$hash     = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT IGNORE INTO usuarios (usuario, nombre, password_hash) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $usuario, $nombre, $hash);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo "<h2>✅ Usuario creado:</h2>";
    echo "<p><b>Usuario:</b> $usuario</p>";
    echo "<p><b>Contraseña:</b> $password</p>";
    echo "<p style='color:red'><b>Elimina este archivo después de usarlo.</b></p>";
} else {
    echo "<h2>ℹ️ El usuario 'admin' ya existe o no se pudo crear.</h2>";
}
$conn->close();
