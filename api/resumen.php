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

// ── Auto-seed si vacío ────────────────────────────────────────────────────────
$chk      = $conn->query("SELECT COUNT(*) FROM facturas");
$chk_row  = $chk->fetch_row();
$total_chk = $chk_row[0] ?? 0;
$chk->free();

if ($total_chk == 0) {
    $estados = ['PENDIENTE', 'PENDIENTE', 'EN_COBRO', 'PAGADA'];
    $seed = $conn->prepare(
        "INSERT IGNORE INTO facturas (id, cufe, fecha, valor, estado, responsable) VALUES (?, ?, ?, ?, ?, 'sistema')"
    );
    for ($i = 1; $i <= 20; $i++) {
        $id    = 'FESM519' . str_pad($i, 3, '0', STR_PAD_LEFT);
        $cufe  = md5($id . microtime());
        $f     = date('Y-m-d H:i:s', strtotime("-" . rand(0, 29) . " days"));
        $val   = rand(50000, 500000);
        $est   = $estados[array_rand($estados)];
        $seed->bind_param("sssds", $id, $cufe, $f, $val, $est);
        $seed->execute();
    }
    $seed->close();
}

// ── Totales generales ─────────────────────────────────────────────────────────
$s1 = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(valor), 0) FROM facturas");
$s1->execute();
$total_facturas = $total_recaudado = null;
$s1->bind_result($total_facturas, $total_recaudado);
$s1->fetch();
$s1->close();

if ($total_facturas == 0) {
    echo json_encode([
        "ok" => true, "total_facturas" => 0, "total_recaudado" => 0,
        "recaudo_dia" => 0, "por_estado" => [], "ultimas" => [],
        "consecutivos_faltantes" => [], "mensaje" => "Sin datos"
    ]);
    exit;
}

// ── Recaudo del día (optimizado con rango) ───────────────────────────────────
$fecha_inicio = $fecha . ' 00:00:00';
$fecha_fin    = $fecha . ' 23:59:59';
$s_dia = $conn->prepare(
    "SELECT COUNT(*), COALESCE(SUM(valor), 0) FROM facturas WHERE fecha BETWEEN ? AND ?"
);
$s_dia->bind_param("ss", $fecha_inicio, $fecha_fin);
$s_dia->execute();
$facturas_dia = $recaudo_dia = null;
$s_dia->bind_result($facturas_dia, $recaudo_dia);
$s_dia->fetch();
$s_dia->close();

// ── Por estado ────────────────────────────────────────────────────────────────
$s2 = $conn->prepare("SELECT estado, COUNT(*) FROM facturas GROUP BY estado");
$s2->execute();
$est_nombre = $est_cant = null;
$s2->bind_result($est_nombre, $est_cant);
$por_estado = [];
while ($s2->fetch()) {
    $por_estado[$est_nombre] = (int)$est_cant;
}
$s2->close();

// ── Consecutivos faltantes (Optimizado: solo últimos 500 registros) ────────
$sc = $conn->prepare(
    "SELECT id FROM facturas WHERE id LIKE 'FESM%'
     ORDER BY created_at DESC LIMIT 500"
);
$sc->execute();
$sc_id = null;
$sc->bind_result($sc_id);
$nums = [];
while ($sc->fetch()) {
    $n = intval(substr($sc_id, 4));
    if ($n > 0) $nums[] = $n;
}
$sc->close();

$faltantes = [];
for ($i = 1; $i < count($nums); $i++) {
    $gap = $nums[$i] - $nums[$i - 1];
    if ($gap > 1 && count($faltantes) < 20) { // máximo 20 alertas
        for ($m = $nums[$i-1] + 1; $m < $nums[$i]; $m++) {
            $faltantes[] = 'FESM' . str_pad($m, strlen((string)$nums[$i-1]), '0', STR_PAD_LEFT);
        }
    }
}

// ── Últimas 10 facturas con responsable (optimizado con rango) ───────────────
$s3 = $conn->prepare(
    "SELECT id, DATE_FORMAT(fecha,'%Y-%m-%d') as fecha, valor, estado,
            COALESCE(responsable,'—') as responsable
     FROM facturas WHERE fecha BETWEEN ? AND ?
     ORDER BY created_at DESC LIMIT 10"
);
$s3->bind_param("ss", $fecha_inicio, $fecha_fin);
$s3->execute();
$r_id = $r_fecha = $r_valor = $r_estado = $r_resp = null;
$s3->bind_result($r_id, $r_fecha, $r_valor, $r_estado, $r_resp);
$ultimas = [];
while ($s3->fetch()) {
    $ultimas[] = [
        "id"          => $r_id,
        "fecha"       => $r_fecha,
        "valor"       => (float)$r_valor,
        "estado"      => $r_estado,
        "responsable" => $r_resp
    ];
}
$s3->close();

// Si no hay resultados en la fecha, traer las últimas 10 globales
if (empty($ultimas)) {
    $s3b = $conn->prepare(
        "SELECT id, DATE_FORMAT(fecha,'%Y-%m-%d'), valor, estado,
                COALESCE(responsable,'—')
         FROM facturas ORDER BY created_at DESC LIMIT 10"
    );
    $s3b->execute();
    $s3b->bind_result($r_id, $r_fecha, $r_valor, $r_estado, $r_resp);
    while ($s3b->fetch()) {
        $ultimas[] = [
            "id" => $r_id, "fecha" => $r_fecha, "valor" => (float)$r_valor,
            "estado" => $r_estado, "responsable" => $r_resp
        ];
    }
    $s3b->close();
}

$conn->close();

echo json_encode([
    "ok"                      => true,
    "fecha_filtro"            => $fecha,
    "total_facturas"          => (int)$total_facturas,
    "total_recaudado"         => (float)$total_recaudado,
    "facturas_dia"            => (int)$facturas_dia,
    "recaudo_dia"             => (float)$recaudo_dia,
    "por_estado"              => $por_estado,
    "consecutivos_faltantes"  => $faltantes,
    "ultimas"                 => $ultimas
]);
