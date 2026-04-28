<?php
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);
session_start();
include 'db.php';

$data     = json_decode(file_get_contents("php://input"), true);
$usuario  = trim($data['usuario'] ?? '');
$password = $data['password'] ?? '';
$recordar = $data['recordar'] ?? false;

if (!$usuario || !$password) {
    echo json_encode(["ok" => false, "error" => "Credenciales requeridas"]);
    exit;
}

$stmt = $conn->prepare("SELECT id, nombre, usuario, password_hash FROM usuarios WHERE usuario = ? LIMIT 1");

if (!$stmt) {
    echo json_encode(["ok" => false, "error" => "Tabla de usuarios no encontrada. Ejecuta /api/setup.php primero."]);
    exit;
}

$stmt->bind_param("s", $usuario);
$stmt->execute();

// Usar bind_result() en lugar de get_result() para compatibilidad con todos los drivers MySQL
$id_db = $nombre_db = $usuario_db = $hash_db = null;
$stmt->bind_result($id_db, $nombre_db, $usuario_db, $hash_db);
$found = $stmt->fetch();
$stmt->close();

if (!$found || !$hash_db) {
    echo json_encode(["ok" => false, "error" => "Usuario o contraseña incorrectos"]);
    exit;
}

if (!password_verify($password, $hash_db)) {
    echo json_encode(["ok" => false, "error" => "Usuario o contraseña incorrectos"]);
    exit;
}

// Crear sesión
$_SESSION['autenticado'] = true;
$_SESSION['nombre']      = $nombre_db;
$_SESSION['usuario']     = $usuario_db;

// Opción "recordarme" - cookie de 30 días
if ($recordar) {
    $expire = time() + (30 * 24 * 60 * 60);
    setcookie('factura_usuario', $usuario_db, $expire, '/', '', false, true);
    setcookie('factura_nombre',  $nombre_db,  $expire, '/', '', false, true);
}

echo json_encode(["ok" => true, "nombre" => $nombre_db]);
$conn->close();
