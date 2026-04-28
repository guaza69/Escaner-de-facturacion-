<?php
/**
 * ARCHIVADO MANUAL / CRON
 * Mueve facturas de más de 30 días a la tabla de histórico.
 * Uso recomendado: Ejecutar una vez al día mediante Cron.
 */
include 'db.php';

// Definir límite de 30 días
$limite = date('Y-m-d H:i:s', strtotime('-30 days'));

$conn->begin_transaction();

try {
    // 1. Obtener IDs de facturas a archivar (para logs)
    $stmt = $conn->prepare("SELECT id FROM facturas WHERE fecha < ?");
    $stmt->bind_param("s", $limite);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = $row['id'];
    }
    $stmt->close();

    if (empty($ids)) {
        header('Content-Type: application/json');
        echo json_encode(["ok" => true, "mensaje" => "No hay registros antiguos para archivar"]);
        exit;
    }

    // 2. Mover facturas a histórico
    $move_facturas = $conn->prepare("INSERT INTO facturas_historico SELECT * FROM facturas WHERE fecha < ?");
    $move_facturas->bind_param("s", $limite);
    $move_facturas->execute();
    $cantidad = $move_facturas->affected_rows;
    $move_facturas->close();

    // 3. Mover logs a histórico
    $ids_in = "'" . implode("','", $ids) . "'";
    $conn->query("INSERT INTO logs_historico SELECT * FROM logs WHERE factura_id IN ($ids_in)");
    $conn->query("DELETE FROM logs WHERE factura_id IN ($ids_in)");

    // 4. Eliminar de la tabla principal
    $del_facturas = $conn->prepare("DELETE FROM facturas WHERE fecha < ?");
    $del_facturas->bind_param("s", $limite);
    $del_facturas->execute();
    $del_facturas->close();

    $conn->commit();

    header('Content-Type: application/json');
    echo json_encode([
        "ok" => true,
        "archivos_movidos" => $cantidad,
        "logs_movidos" => count($ids),
        "mensaje" => "Archivo completado con éxito"
    ]);

} catch (Exception $e) {
    $conn->rollback();
    header('Content-Type: application/json', true, 500);
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>
