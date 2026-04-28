<?php
/**
 * FactuFlow Production Logger
 * Permite registrar acciones de usuario para auditoría y debug.
 */
function logAction($conn, $accion, $factura_id = null, $planilla_id = null, $detalles = null) {
    if (!isset($_SESSION)) session_start();
    
    $usuario = $_SESSION['usuario'] ?? 'sistema';
    $detalles_str = is_array($detalles) ? json_encode($detalles) : $detalles;

    $stmt = $conn->prepare("INSERT INTO logs (usuario, accion, factura_id, planilla_id, detalles) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $usuario, $accion, $factura_id, $planilla_id, $detalles_str);
    $stmt->execute();
    $stmt->close();
}

function sendResponse($ok, $data = []) {
    if (!$ok) {
        // Log error if needed or handle generic error format
        $data = is_string($data) ? ["error" => $data] : $data;
        $data["ok"] = false;
    } else {
        $data["ok"] = true;
    }
    echo json_encode($data);
    exit;
}
?>
