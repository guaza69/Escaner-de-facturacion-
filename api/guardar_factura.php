<?php
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);
session_start();
include 'db.php';
include 'logger.php';

$data = json_decode(file_get_contents("php://input"), true);

$id          = $data['id']    ?? null;
$cufe        = $data['cufe']  ?? null;
$fecha       = $data['fecha'] ?? null;
$valor       = $data['valor'] ?? null;
$responsable = $_SESSION['usuario'] ?? 'escaner';
$planilla_id = $data['planilla_id'] ?? null;
$novedad     = $data['novedad']     ?? null;
$force       = $data['force']       ?? false; // Nueva bandera para decisión operativa

if (!$id || !$valor || !$planilla_id) {
    sendResponse(false, ["error" => "DATOS_INCOMPLETOS", "msg" => "Falta ID, Valor o Planilla Activa"]);
}

// Limpiar valor de posibles caracteres no numéricos
$valor = preg_replace('/[^0-9.]/', '', $valor);
if (!is_numeric($valor)) {
    sendResponse(false, ["error" => "VALOR_INVALIDO", "msg" => "El valor de la factura no es válido"]);
}
$valor = (float)$valor;


try {
    // 1. Verificar si la planilla ya está cerrada
    $check = $conn->prepare("SELECT numero, closed_at FROM planillas WHERE id = ?");
    $check->bind_param("i", $planilla_id);
    $check->execute();
    $p_res = $check->get_result()->fetch_assoc();
    
    if (!$p_res) {
        sendResponse(false, "PLANILLA_INEXISTENTE");
    }
    
    if ($p_res['closed_at']) {
        sendResponse(false, ["error" => "PLANILLA_CERRADA", "msg" => "La planilla {$p_res['numero']} ya está cerrada."]);
    }
    $check->close();

    // 2. Lógica de Inserción o Actualización (Decisión Operativa)
    if ($force) {
        // Mover factura a la nueva planilla o actualizar datos
        $stmt = $conn->prepare(
            "UPDATE facturas 
             SET planilla_id = ?, novedad = ?, responsable = ?, valor = ?, fecha = ?, estado = 'REGISTRADA' 
             WHERE id = ?"
        );
        $stmt->bind_param("issdss", $planilla_id, $novedad, $responsable, $valor, $fecha, $id);
        
        if ($stmt->execute()) {
            logAction($conn, "RE-REGISTRO_FACTURA", $id, $planilla_id, ["info" => "Forzado por usuario"]);
            sendResponse(true, ["msg" => "Factura re-asignada exitosamente"]);
            exit;
        }
    }

    // Intento normal
    $stmt = $conn->prepare(
        "INSERT INTO facturas (id, cufe, fecha, valor, estado, responsable, planilla_id, novedad)
         VALUES (?, ?, ?, ?, 'REGISTRADA', ?, ?, ?)"
    );
    $stmt->bind_param("sssdsis", $id, $cufe, $fecha, $valor, $responsable, $planilla_id, $novedad);

    if ($stmt->execute()) {
        logAction($conn, "REGISTRO_FACTURA", $id, $planilla_id);
        sendResponse(true, ["msg" => "Factura registrada exitosamente"]);
    } else {
        // 3. Manejo inteligente de duplicados (Error 1062)
        if ($conn->errno == 1062) {
            $dup = $conn->prepare("SELECT p.numero FROM facturas f JOIN planillas p ON f.planilla_id = p.id WHERE f.id = ?");
            $dup->bind_param("s", $id);
            $dup->execute();
            $dup_res = $dup->get_result()->fetch_assoc();
            $planilla_dup = $dup_res['numero'] ?? 'Desconocida';
            
            logAction($conn, "INTENTO_DUPLICADO", $id, $planilla_id, ["en_planilla" => $planilla_dup]);
            sendResponse(false, [
                "error" => "DUPLICADO", 
                "status" => "duplicado",
                "planilla" => $planilla_dup,
                "mensaje" => "Esta factura ya fue registrada en la planilla: " . $planilla_dup
            ]);
        } else {
            throw new Exception($conn->error);
        }
    }
} catch (Throwable $e) {
    logAction($conn, "ERROR_SISTEMA", $id, $planilla_id, $e->getMessage());
    sendResponse(false, "Error interno del servidor: " . $e->getMessage());
} finally {
    if (isset($stmt)) $stmt->close();
    $conn->close();
}
?>