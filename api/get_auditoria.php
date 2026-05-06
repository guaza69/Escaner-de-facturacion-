<?php
header('Content-Type: application/json');
include 'db.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    die(json_encode(["ok" => false, "error" => "No session"]));
}

$limit = 100;
$sql = "SELECT l.*, f.planilla_id 
        FROM logs l 
        LEFT JOIN facturas f ON l.factura_id = f.id
        WHERE l.accion IN ('INTENTO_DUPLICADO', 'RE-REGISTRO_FACTURA', 'ERROR_SISTEMA')
        ORDER BY l.fecha DESC LIMIT ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $limit);
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    "ok" => true,
    "data" => $data
]);
$conn->close();
?>
