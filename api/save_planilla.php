<?php
header('Content-Type: application/json');
include 'db.php';
include 'logger.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    sendResponse(false, "No session");
}

// Leer datos (soporta FormData y JSON)
$inputJSON = json_decode(file_get_contents('php://input'), true);
$numero = $_POST['numero'] ?? $inputJSON['numero'] ?? '';
$fecha = $_POST['fecha'] ?? $inputJSON['fecha'] ?? '';
$total_esperado = intval($_POST['total_esperado'] ?? $inputJSON['total_esperado'] ?? 0);

// Validar fecha (si viene vacía, usar hoy)
if (empty($fecha)) {
    $fecha = date('Y-m-d');
}
$imagen_url = '';

if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
    $target_dir = "../uploads/planillas/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    
    $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
    $filename = "planilla_" . time() . "." . $ext;
    $target_file = $target_dir . $filename;
    
    if (move_uploaded_file($_FILES['imagen']['tmp_name'], $target_file)) {
        $imagen_url = "uploads/planillas/" . $filename;
    }
}

try {
    $stmt = $conn->prepare("INSERT INTO planillas (numero, fecha, total_esperado, imagen_url) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $numero, $fecha, $total_esperado, $imagen_url);

    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        logAction($conn, "CREAR_PLANILLA", null, $new_id, ["numero" => $numero]);
        sendResponse(true, ["id" => $new_id]);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    logAction($conn, "ERROR_PLANILLA", null, null, $e->getMessage());
    sendResponse(false, "Error al crear planilla: " . $e->getMessage());
} finally {
    if (isset($stmt)) $stmt->close();
    $conn->close();
}
?>
