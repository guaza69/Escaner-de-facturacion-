<?php
/**
 * Script de prueba de carga para FactuFlow
 * Inserta 1000 facturas simuladas y mide el rendimiento.
 */
include '../api/db.php';

$planilla_id = 1; // Asegúrate de que existe
$total = 1000;
$start = microtime(true);

echo "Iniciando inserción de $total facturas...\n";

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO facturas (id, cufe, fecha, valor, estado, responsable, planilla_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    for ($i = 1; $i <= $total; $i++) {
        $id = "TEST-" . str_pad($i, 6, '0', STR_PAD_LEFT) . "-" . time();
        $cufe = md5($id);
        $fecha = date('Y-m-d');
        $valor = rand(1000, 100000);
        $estado = 'REGISTRADA';
        $resp = 'test_runner';
        
        $stmt->bind_param("sssdssi", $id, $cufe, $fecha, $valor, $estado, $resp, $planilla_id);
        $stmt->execute();
    }
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}

$end = microtime(true);
$time = $end - $start;

echo "Finalizado en " . round($time, 4) . " segundos.\n";
echo "Promedio: " . round(($time / $total) * 1000, 2) . " ms por factura.\n";

$conn->close();
?>
