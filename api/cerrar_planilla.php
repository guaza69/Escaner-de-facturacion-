<?php
header('Content-Type: application/json');
include 'db.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    die(json_encode(["ok" => false, "error" => "No session"]));
}

$id = $_POST['id'] ?? '';

if (!$id) {
    die(json_encode(["ok" => false, "error" => "ID missing"]));
}

// Validar que la planilla esté completa antes de cerrar
$stmt = $conn->prepare("SELECT p.total_esperado, COUNT(f.id) as total_registrado, p.closed_at 
                        FROM planillas p 
                        LEFT JOIN facturas f ON p.id = f.planilla_id 
                        WHERE p.id = ? GROUP BY p.id");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res) {
    die(json_encode(["ok" => false, "error" => "Planilla not found"]));
}

if ($res['closed_at']) {
    die(json_encode(["ok" => false, "error" => "La planilla ya está cerrada"]));
}

if ($res['total_registrado'] < $res['total_esperado']) {
    die(json_encode([
        "ok" => false, 
        "error" => "No se puede cerrar: faltan facturas por registrar (" . $res['total_registrado'] . " / " . $res['total_esperado'] . ")"
    ]));
}

// Cerrar la planilla
$now = date('Y-m-d H:i:s');
$stmt2 = $conn->prepare("UPDATE planillas SET closed_at = ? WHERE id = ?");
$stmt2->bind_param("si", $now, $id);

if ($stmt2->execute()) {
    echo json_encode(["ok" => true]);
} else {
    echo json_encode(["ok" => false, "error" => $conn->error]);
}

$stmt->close();
$stmt2->close();
$conn->close();
?>
