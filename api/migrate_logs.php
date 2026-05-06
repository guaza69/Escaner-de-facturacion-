<?php
include 'db.php';

echo "<h2>Migración de Tabla de Logs...</h2>";

function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

$queries = [];

if (!columnExists($conn, 'logs', 'planilla_id')) {
    $queries[] = "ALTER TABLE logs ADD COLUMN planilla_id INT AFTER factura_id";
}

if (!columnExists($conn, 'logs', 'detalles')) {
    $queries[] = "ALTER TABLE logs ADD COLUMN detalles TEXT AFTER accion";
}

$queries[] = "ALTER TABLE logs ADD INDEX IF NOT EXISTS idx_accion (accion)";
$queries[] = "ALTER TABLE logs ADD INDEX IF NOT EXISTS idx_planilla (planilla_id)";

foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "<p style='color:green'>✅ Éxito: " . substr($sql, 0, 80) . "...</p>";
    } else {
        echo "<p style='color:red'>❌ Error: " . $conn->error . "</p>";
    }
}

$conn->close();
echo "<h3>Migración de Logs completada.</h3>";
?>
