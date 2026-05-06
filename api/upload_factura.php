<?php
header('Content-Type: application/json');
include 'db.php';
include 'logger.php';
include 'drive_service.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    sendResponse(false, "No session");
}

$factura_id = $_POST['factura_id'] ?? null;
if (!$factura_id || !isset($_FILES['archivo'])) {
    sendResponse(false, "Datos incompletos (Falta ID o Archivo)");
}

try {
    // 1. Obtener datos de la factura y planilla
    $stmt = $conn->prepare("
        SELECT f.id, f.fecha, p.numero as planilla_numero 
        FROM facturas f 
        JOIN planillas p ON f.planilla_id = p.id 
        WHERE f.id = ?
    ");
    $stmt->bind_param("s", $factura_id);
    $stmt->execute();
    $factura = $stmt->get_result()->fetch_assoc();
    
    if (!$factura) {
        sendResponse(false, "La factura no existe o no tiene una planilla asociada");
    }
    $stmt->close();

    // 2. Configuración de Archivo Local
    $file = $_FILES['archivo'];
    $maxSize = 10 * 1024 * 1024; // 10MB (Aumentado para mayor flexibilidad)
    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file['size'] > $maxSize) {
        sendResponse(false, "El archivo supera el límite de 10MB");
    }
    
    if (!in_array($ext, $allowedExts)) {
        sendResponse(false, "Tipo de archivo no permitido (Solo JPG, PNG, PDF)");
    }

    // 3. Guardar Localmente (Fallback)
    $monthFolder = date('Y-m', strtotime($factura['fecha']));
    $targetDir = "../uploads/facturas/" . $monthFolder . "/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $safeId = preg_replace("/[^a-zA-Z0-9_-]/", "", $factura_id);
    $prefix = "FESM";
    $fileName = (strpos($safeId, $prefix) === 0 ? $safeId : $prefix . $safeId) . "." . $ext;
    $localPath = $targetDir . $fileName;
    $relativeUrl = "uploads/facturas/" . $monthFolder . "/" . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $localPath)) {
        throw new Exception("No se pudo guardar el archivo localmente");
    }

    // 4. Subir a Google Drive
    $driveUrl = null;
    $uploadSuccess = false;

    try {
        $config = include 'google_config.php';
        $rootId = $config['root_folder_id'] ?: null;

        // Crear Carpeta del Mes
        $monthDriveId = createFolderIfNotExists($monthFolder, $rootId);
        
        // Crear Carpeta de Planilla
        $planillaName = "PLANILLA_" . $factura['planilla_numero'];
        $planillaDriveId = createFolderIfNotExists($planillaName, $monthDriveId);

        // Subir Archivo
        $driveFile = uploadFileToDrive($localPath, $fileName, $planillaDriveId);
        $driveUrl = $driveFile['url'];
        $uploadSuccess = true;

    } catch (Exception $driveEx) {
        logAction($conn, "DRIVE_ERROR", $factura_id, null, "Error Drive: " . $driveEx->getMessage());
    }

    // 5. Actualizar Base de Datos
    $finalUrl = $uploadSuccess ? $driveUrl : $relativeUrl;
    $update = $conn->prepare("UPDATE facturas SET soporte_url = ? WHERE id = ?");
    $update->bind_param("ss", $finalUrl, $factura_id);
    
    if ($update->execute()) {
        logAction($conn, "UPLOAD_SOPORTE", $factura_id, null, [
            "url" => $finalUrl, 
            "method" => $uploadSuccess ? "DRIVE" : "LOCAL"
        ]);
        
        sendResponse(true, [
            "status" => "ok",
            "url" => $finalUrl,
            "method" => $uploadSuccess ? "google_drive" : "local_fallback"
        ]);
    } else {
        throw new Exception("Error al actualizar la base de datos");
    }
    $update->close();


} catch (Throwable $e) {
    logAction($conn, "ERROR_UPLOAD", $factura_id ?? 'N/A', null, $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
} finally {
    $conn->close();
}
?>

