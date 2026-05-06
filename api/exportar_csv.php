<?php
include 'db.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    die("Acceso denegado");
}

$planilla_id = $_GET['planilla_id'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

$sql = "SELECT f.id as 'ID Factura', f.cufe as 'CUFE', f.fecha as 'Fecha Emision', 
               f.valor as 'Valor', f.estado as 'Estado', 
               p.numero as 'Planilla', f.novedad as 'Novedad', 
               f.responsable as 'Responsable', f.created_at as 'Fecha Registro'
        FROM facturas f
        LEFT JOIN planillas p ON f.planilla_id = p.id
        WHERE 1=1";
$params = [];
$types = "";

if ($planilla_id) {
    $sql .= " AND p.numero = ?";
    $params[] = $planilla_id;
    $types .= "s";
}
if ($fecha_inicio) {
    $sql .= " AND f.fecha >= ?";
    $params[] = $fecha_inicio . " 00:00:00";
    $types .= "s";
}
if ($fecha_fin) {
    $sql .= " AND f.fecha <= ?";
    $params[] = $fecha_fin . " 23:59:59";
    $types .= "s";
}

$sql .= " ORDER BY f.fecha DESC";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$filename = "facturas_" . date('Ymd') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility in Windows
echo "\xEF\xBB\xBF";

// Headers
fputcsv($output, ['FACTURA', 'CUFE', 'FECHA', 'VALOR', 'ESTADO', 'PLANILLA', 'NOVEDAD', 'USUARIO', 'CREACION'], ';');

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row, ';');
}


fclose($output);
$stmt->close();
$conn->close();
exit;
?>
