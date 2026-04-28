<?php
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);
session_start();

if (empty($_SESSION['autenticado']) && empty($_COOKIE['factura_usuario'])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "No autorizado"]);
    exit;
}

include 'db.php';

if ($fecha) {
    $fecha_inicio = $fecha . ' 00:00:00';
    $fecha_fin    = $fecha . ' 23:59:59';
    $stmt = $conn->prepare(
        "SELECT id, cufe, DATE_FORMAT(fecha,'%Y-%m-%d') as fecha, valor, estado, created_at
         FROM facturas WHERE fecha BETWEEN ? AND ? ORDER BY fecha DESC"
    );
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);

} elseif ($fecha_desde && $fecha_hasta) {
    $desde = $fecha_desde . ' 00:00:00';
    $hasta = $fecha_hasta . ' 23:59:59';
    $stmt = $conn->prepare(
        "SELECT id, cufe, DATE_FORMAT(fecha,'%Y-%m-%d') as fecha, valor, estado, created_at
         FROM facturas WHERE fecha BETWEEN ? AND ? ORDER BY fecha DESC LIMIT 500"
    );
    $stmt->bind_param("ss", $desde, $hasta);

} else {
    $stmt = $conn->prepare(
        "SELECT id, cufe, DATE_FORMAT(fecha,'%Y-%m-%d') as fecha, valor, estado, created_at
         FROM facturas ORDER BY created_at DESC LIMIT 50"
    );
}

$stmt->execute();
$stmt->store_result();
$stmt->bind_result($r_id, $r_cufe, $r_fecha, $r_valor, $r_estado, $r_created);

$facturas = [];
while ($stmt->fetch()) {
    $facturas[] = [
        "id"         => $r_id,
        "cufe"       => $r_cufe,
        "fecha"      => $r_fecha,
        "valor"      => $r_valor,
        "estado"     => $r_estado,
        "created_at" => $r_created
    ];
}
$stmt->close();

echo json_encode(["ok" => true, "total" => count($facturas), "data" => $facturas]);
$conn->close();
