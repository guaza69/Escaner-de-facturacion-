<?php
header('Content-Type: application/json');
include 'db.php';
include 'logger.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    sendResponse(false, "No session");
}

$factura_id = $_POST['factura_id'] ?? null;
if (!$factura_id || !isset($_FILES['archivo'])) {
    sendResponse(false, "Datos incompletos (Falta ID o Archivo)");
}

try {
    // 1. Validar que la factura exista
    $stmt = $conn->prepare("SELECT id FROM facturas WHERE id = ?");
    $stmt->bind_param("s", $factura_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        sendResponse(false, "La factura no existe en el sistema");
    }
    $stmt->close();

    // 2. Configuración de Archivo
    $file = $_FILES['archivo'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file['size'] > $maxSize) {
        sendResponse(false, "El archivo supera el límite de 5MB");
    }
    
    if (!in_array($ext, $allowedExts)) {
        sendResponse(false, "Tipo de archivo no permitido (Solo JPG, PNG, PDF)");
    }

    // 3. Crear Estructura de Carpetas (YYYY-MM)
    $monthDir = date('Y-m');
    $targetDir = "../uploads/facturas/" . $monthDir . "/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    // 4. Nombre de Archivo Seguro (ID de Factura)
    // Limpiar ID de caracteres raros por seguridad
    $safeName = preg_replace("/[^a-zA-Z0-9_-]/", "", $factura_id);
    $fileName = $safeName . "." . $ext;
    $targetFile = $targetDir . $fileName;
    $relativeUrl = "uploads/facturas/" . $monthDir . "/" . $fileName;

    // 5. Mover Archivo y Actualizar BD
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        $update = $conn->prepare("UPDATE facturas SET soporte_url = ? WHERE id = ?");
        $update->bind_param("ss", $relativeUrl, $factura_id);
        
        if ($update->execute()) {
            logAction($conn, "UPLOAD_SOPORTE", $factura_id, null, ["url" => $relativeUrl]);
            sendResponse(true, ["url" => $relativeUrl, "msg" => "Soporte subido correctamente"]);
        } else {
            throw new Exception("Error al actualizar la base de datos");
        }
        $update->close();
    } else {
        throw new Exception("No se pudo mover el archivo al servidor");
    }

} catch (Exception $e) {
    logAction($conn, "ERROR_UPLOAD", $factura_id, null, $e->getMessage());
    sendResponse(false, "Error: " . $e->getMessage());
} finally {
    $conn->close();
}
?>
