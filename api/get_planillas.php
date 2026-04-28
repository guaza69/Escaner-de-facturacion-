<?php
header('Content-Type: application/json');
include 'db.php';
include 'logger.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    sendResponse(false, "No session");
}

$fecha = $_GET['fecha'] ?? '';
$numero = $_GET['numero'] ?? '';

try {
    $sql = "SELECT p.*, COUNT(f.id) as total_registrado, 
                   (p.total_esperado - COUNT(f.id)) as diferencia 
            FROM planillas p 
            LEFT JOIN facturas f ON p.id = f.planilla_id 
            WHERE 1=1";
    $params = [];
    $types = "";

    if ($fecha) {
        $sql .= " AND p.fecha = ?";
        $params[] = $fecha;
        $types .= "s";
    }
    if ($numero) {
        $sql .= " AND p.numero LIKE ?";
        $params[] = "%$numero%";
        $types .= "s";
    }

    $sql .= " GROUP BY p.id ORDER BY p.fecha DESC, p.id DESC LIMIT 100";

    $stmt = $conn->prepare($sql);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);

    sendResponse(true, ["data" => $data]);

} catch (Exception $e) {
    logAction($conn, "ERROR_LISTADO_PLANILLAS", null, null, $e->getMessage());
    sendResponse(false, "Error al obtener planillas");
} finally {
    if (isset($stmt)) $stmt->close();
    $conn->close();
}
?>
