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

$fecha = $_GET['fecha'] ?? date('Y-m-d');

// ── Resumen del día ────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT COUNT(*) as total, COALESCE(SUM(valor), 0) as recaudado FROM facturas WHERE DATE(fecha) = ?"
);
$stmt->bind_param("s", $fecha);
$stmt->execute();
$total_dia = $recaudado_dia = null;
$stmt->bind_result($total_dia, $recaudado_dia);
$stmt->fetch();
$stmt->close();

// ── Pendientes del día ─────────────────────────────────────────
$stmt2 = $conn->prepare(
    "SELECT COUNT(*) FROM facturas WHERE DATE(fecha) = ? AND estado = 'PENDIENTE'"
);
$stmt2->bind_param("s", $fecha);
$stmt2->execute();
$pendientes = null;
$stmt2->bind_result($pendientes);
$stmt2->fetch();
$stmt2->close();

// ── Histórico últimos 7 días ───────────────────────────────────
$stmt3 = $conn->prepare(
    "SELECT DATE(fecha) as dia, COUNT(*) as total, COALESCE(SUM(valor),0) as recaudado
     FROM facturas
     WHERE DATE(fecha) >= DATE_SUB(?, INTERVAL 6 DAY)
     GROUP BY DATE(fecha) ORDER BY dia ASC"
);
$stmt3->bind_param("s", $fecha);
$stmt3->execute();
$h_dia = $h_total = $h_rec = null;
$stmt3->bind_result($h_dia, $h_total, $h_rec);
$historico = [];
while ($stmt3->fetch()) {
    $historico[] = ["dia" => $h_dia, "total" => $h_total, "recaudado" => $h_rec];
}
$stmt3->close();

echo json_encode([
    "ok"              => true,
    "fecha"           => $fecha,
    "total_facturas"  => (int)$total_dia,
    "total_recaudado" => (float)$recaudado_dia,
    "pendientes"      => (int)$pendientes,
    "historico"       => $historico
]);
$conn->close();
