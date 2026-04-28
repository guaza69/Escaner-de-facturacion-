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

$mes = $_GET['mes'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    echo json_encode(["ok" => false, "error" => "Formato inválido. Usar YYYY-MM"]);
    exit;
}

$anio      = (int)substr($mes, 0, 4);
$numMes    = (int)substr($mes, 5, 2);
$diasEnMes = cal_days_in_month(CAL_GREGORIAN, $numMes, $anio);

// ── Resumen mensual general ────────────────────────────────────────────────
$s1 = $conn->prepare(
    "SELECT
        COUNT(*),
        COALESCE(SUM(valor), 0),
        COUNT(DISTINCT DATE(fecha)),
        MAX(valor),
        MIN(valor)
     FROM facturas WHERE DATE_FORMAT(fecha, '%Y-%m') = ?"
);
$s1->bind_param("s", $mes);
$s1->execute();
$total_f = $total_r = $dias_con = $max_val = $min_val = null;
$s1->bind_result($total_f, $total_r, $dias_con, $max_val, $min_val);
$s1->fetch();
$s1->close();

$dias_sin    = $diasEnMes - (int)$dias_con;
$promedio_r  = $dias_con > 0 ? round($total_r / $dias_con, 2) : 0;
$promedio_f  = $dias_con > 0 ? round($total_f / $dias_con, 1) : 0;

// ── Por estado (mes) ───────────────────────────────────────────────────────
$s2 = $conn->prepare(
    "SELECT estado, COUNT(*) FROM facturas
     WHERE DATE_FORMAT(fecha, '%Y-%m') = ? GROUP BY estado"
);
$s2->bind_param("s", $mes);
$s2->execute();
$e_nom = $e_cnt = null;
$s2->bind_result($e_nom, $e_cnt);
$por_estado = [];
while ($s2->fetch()) {
    $por_estado[$e_nom] = (int)$e_cnt;
}
$s2->close();

// ── Desglose diario (para gráfico) ────────────────────────────────────────
$s3 = $conn->prepare(
    "SELECT
        DAY(fecha)             as dia,
        COUNT(*)               as facturas,
        COALESCE(SUM(valor),0) as recaudado
     FROM facturas
     WHERE DATE_FORMAT(fecha, '%Y-%m') = ?
     GROUP BY DAY(fecha)
     ORDER BY dia ASC"
);
$s3->bind_param("s", $mes);
$s3->execute();
$d_dia = $d_fac = $d_rec = null;
$s3->bind_result($d_dia, $d_fac, $d_rec);
$desglose_raw = [];
while ($s3->fetch()) {
    $desglose_raw[(int)$d_dia] = ["facturas" => (int)$d_fac, "recaudado" => (float)$d_rec];
}
$s3->close();

// Completar todos los días del mes (días sin data = 0)
$desglose = [];
for ($d = 1; $d <= $diasEnMes; $d++) {
    $desglose[] = [
        "dia"       => $d,
        "facturas"  => $desglose_raw[$d]["facturas"]  ?? 0,
        "recaudado" => $desglose_raw[$d]["recaudado"] ?? 0
    ];
}

$conn->close();

echo json_encode([
    "ok"                   => true,
    "mes"                  => $mes,
    "dias_en_mes"          => $diasEnMes,
    "total_facturas_mes"   => (int)$total_f,
    "total_recaudado_mes"  => (float)$total_r,
    "promedio_diario_cop"  => (float)$promedio_r,
    "promedio_diario_fac"  => (float)$promedio_f,
    "dias_con_facturacion" => (int)$dias_con,
    "dias_sin_facturacion" => (int)$dias_sin,
    "valor_max"            => (float)($max_val ?? 0),
    "por_estado"           => $por_estado,
    "desglose"             => $desglose
]);
