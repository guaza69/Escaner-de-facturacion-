<?php
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);
session_start();

// Validar auth
if (empty($_SESSION['autenticado']) && empty($_COOKIE['factura_usuario'])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "No autorizado"]);
    exit;
}

include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? null;
$nuevo_estado = $data['estado'] ?? null; // PENDIENTE, EN_COBRO, PAGADA
$usuario = $_SESSION['usuario'] ?? $_COOKIE['factura_usuario'] ?? 'sistema';

if (!$id || !$nuevo_estado) {
    echo json_encode(["ok" => false, "error" => "ID o estado faltante"]);
    exit;
}

// 1. Obtener estado actual
$stmt = $conn->prepare("SELECT estado FROM facturas WHERE id = ?");
$stmt->bind_param("s", $id);
$stmt->execute();
$stmt->bind_result($estado_actual);
if (!$stmt->fetch()) {
    echo json_encode(["ok" => false, "error" => "Factura no encontrada"]);
    exit;
}
$stmt->close();

// 2. Validaciones de flujo
if ($estado_actual === 'PAGADA') {
    echo json_encode(["ok" => false, "error" => "Esta factura ya ha sido pagada y no puede ser modificada"]);
    exit;
}

if ($estado_actual === $nuevo_estado) {
    echo json_encode(["ok" => false, "error" => "La factura ya se encuentra en este estado"]);
    exit;
}

// 3. Preparar actualización
$query = "UPDATE facturas SET estado = ?, responsable = ?";
$params = [$nuevo_estado, $usuario];
$types = "ss";

if ($nuevo_estado === 'PAGADA') {
    $query .= ", pagado_at = NOW()";
}

$query .= " WHERE id = ?";
$params[] = $id;
$types .= "s";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    // 4. Logear la acción
    $stmt_log = $conn->prepare("INSERT INTO logs (factura_id, usuario, accion) VALUES (?, ?, ?)");
    $accion = "Cambio de estado: $estado_actual -> $nuevo_estado";
    $stmt_log->bind_param("sss", $id, $usuario, $accion);
    $stmt_log->execute();
    $stmt_log->close();

    echo json_encode(["ok" => true, "nuevo_estado" => $nuevo_estado]);
} else {
    echo json_encode(["ok" => false, "error" => "Error al actualizar: " . $conn->error]);
}

$stmt->close();
$conn->close();