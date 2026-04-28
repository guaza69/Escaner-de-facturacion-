<?php
header('Content-Type: application/json');
include 'db.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    die(json_encode(["ok" => false, "error" => "No session"]));
}

$id = $_GET['id'] ?? '';

if (!$id) {
    die(json_encode(["ok" => false, "error" => "ID missing"]));
}

// Obtener planilla
$stmt = $conn->prepare("SELECT p.*, COUNT(f.id) as total_registrado, (p.total_esperado - COUNT(f.id)) as diferencia 
                        FROM planillas p 
                        LEFT JOIN facturas f ON p.id = f.planilla_id 
                        WHERE p.id = ? GROUP BY p.id");
$stmt->bind_param("i", $id);
$stmt->execute();
$planilla = $stmt->get_result()->fetch_assoc();

if (!$planilla) {
    die(json_encode(["ok" => false, "error" => "Planilla not found"]));
}

// Obtener facturas asociadas
$stmt2 = $conn->prepare("SELECT * FROM facturas WHERE planilla_id = ? ORDER BY created_at ASC");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$facturas = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    "ok" => true,
    "planilla" => $planilla,
    "facturas" => $facturas
]);

$stmt->close();
$stmt2->close();
$conn->close();
?>
