<?php
/**
 * FactuFlow Automated Backup
 * Genera un volcado SQL de la base de datos.
 */
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="backup_'.date('Y-m-d_H-i-s').'.sql"');

include 'db.php';

$tables = ['usuarios', 'planillas', 'facturas', 'logs'];
$output = "-- FactuFlow SQL Dump\n";
$output .= "-- Generado: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($tables as $table) {
    // Estructura
    $res = $conn->query("SHOW CREATE TABLE $table");
    $row = $res->fetch_row();
    $output .= "\n\n" . $row[1] . ";\n\n";
    
    // Datos
    $res = $conn->query("SELECT * FROM $table");
    while ($row = $res->fetch_assoc()) {
        $keys = array_keys($row);
        $values = array_map(function($v) use ($conn) {
            return is_null($v) ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
        }, array_values($row));
        
        $output .= "INSERT INTO $table (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ");\n";
    }
}

echo $output;
$conn->close();
?>
