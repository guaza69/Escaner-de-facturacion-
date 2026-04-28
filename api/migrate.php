<?php
/**
 * MIGRACIÓN DE OPTIMIZACIÓN
 * Ejecutar una vez para aplicar índices y crear tablas de histórico.
 */
include 'db.php';

echo "<h2>Iniciando optimización de base de datos...</h2>";

$queries = [
    // 1. Crear tabla de histórico (réplica exacta de facturas)
    "CREATE TABLE IF NOT EXISTS facturas_historico LIKE facturas",
    
    // 2. Agregar índices a la tabla principal
    "ALTER TABLE facturas ADD INDEX IF NOT EXISTS idx_fecha (fecha)",
    "ALTER TABLE facturas ADD INDEX IF NOT EXISTS idx_estado (estado)",
    
    // 3. Agregar índices a la tabla histórico
    "ALTER TABLE facturas_historico ADD INDEX IF NOT EXISTS idx_fecha_hist (fecha)",
    "ALTER TABLE facturas_historico ADD INDEX IF NOT EXISTS idx_estado_hist (estado)",
    
    // 4. Tabla de logs histórico
    "CREATE TABLE IF NOT EXISTS logs_historico LIKE logs",
    "ALTER TABLE logs_historico ADD INDEX IF NOT EXISTS idx_factura_hist (factura_id)"
];

foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "<p style='color:green'>✅ Éxito: " . substr($sql, 0, 50) . "...</p>";
    } else {
        echo "<p style='color:red'>❌ Error: " . $conn->error . "</p>";
    }
}

// 5. Crear Evento de MySQL para purga automática (si los permisos lo permiten)
$event_sql = "
CREATE EVENT IF NOT EXISTS evt_archivar_facturacion
ON SCHEDULE EVERY 1 DAY
STARTS (TIMESTAMP(CURRENT_DATE) + INTERVAL 2 HOUR)
DO
  BEGIN
    -- Mover facturas de más de 30 días
    INSERT INTO facturas_historico SELECT * FROM facturas WHERE fecha < (CURRENT_DATE - INTERVAL 30 DAY);
    DELETE FROM facturas WHERE fecha < (CURRENT_DATE - INTERVAL 30 DAY);
    
    -- Mover logs asociados
    INSERT INTO logs_historico SELECT l.* FROM logs l 
    LEFT JOIN facturas f ON l.factura_id = f.id 
    WHERE f.id IS NULL;
    
    DELETE l FROM logs l 
    LEFT JOIN facturas f ON l.factura_id = f.id 
    WHERE f.id IS NULL;
  END;
";

if ($conn->query($event_sql)) {
    echo "<p style='color:blue'>📅 Evento de automatización creado (se ejecuta diariamente a las 02:00 AM).</p>";
} else {
    // Si falla por permisos, no es crítico ya que tendremos el script PHP
    echo "<p style='color:orange'>⚠️ Nota: No se pudo crear el Evento MySQL (posible falta de permisos). Se recomienda usar el script PHP 'archive.php' con un Cron job.</p>";
}

$conn->close();
echo "<h3>Optimización completada.</h3>";
?>
