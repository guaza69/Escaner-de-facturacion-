<?php
header('Content-Type: application/json');
include 'db.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    die(json_encode(["ok" => false, "error" => "No session"]));
}

$id = $_GET['id'] ?? '';
$cufe = $_GET['cufe'] ?? '';
$planilla_id = $_GET['planilla_id'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$estado = $_GET['estado'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;


$sql = "SELECT f.*, p.numero as planilla_numero FROM facturas f LEFT JOIN planillas p ON f.planilla_id = p.id WHERE 1=1";
$params = [];
$types = "";

if ($id) { $sql .= " AND f.id LIKE ?"; $params[] = "%$id%"; $types .= "s"; }
if ($cufe) { $sql .= " AND f.cufe LIKE ?"; $params[] = "%$cufe%"; $types .= "s"; }
if ($planilla_id) { $sql .= " AND p.numero LIKE ?"; $params[] = "%$planilla_id%"; $types .= "s"; }
if ($fecha_inicio) { $sql .= " AND f.fecha >= ?"; $params[] = $fecha_inicio . " 00:00:00"; $types .= "s"; }
if ($fecha_fin) { $sql .= " AND f.fecha <= ?"; $params[] = $fecha_fin . " 23:59:59"; $types .= "s"; }
if ($estado) { $sql .= " AND f.estado = ?"; $params[] = $estado; $types .= "s"; }

// Count total for pagination
$countSql = str_replace("f.*, p.numero as planilla_numero", "COUNT(*) as total", $sql);
$stmtCount = $conn->prepare($countSql);
if ($types) { $stmtCount->bind_param($types, ...$params); }
$stmtCount->execute();
$totalRows = $stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();

$sql .= " ORDER BY f.fecha DESC, f.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if ($types) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    "ok" => true,
    "data" => $data,
    "total" => $totalRows,
    "page" => $page,
    "pages" => ceil($totalRows / $limit)
]);
$conn->close();
?>
