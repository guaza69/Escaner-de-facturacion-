<?php
header('Content-Type: application/json');
include 'db.php';

$result = $conn->query("SELECT id FROM facturas ORDER BY id ASC");

$anteriores = null;
$errores = [];

while ($row = $result->fetch_assoc()) {
    // Asume formato FESM####
    $num = intval(substr($row['id'], 4));

    if ($anteriores !== null && $num != $anteriores + 1) {
        $errores[] = "Falta: FESM" . ($anteriores + 1);
    }

    $anteriores = $num;
}

echo json_encode($anteriores ? $errores : ["info" => "No hay facturas registradas"]);
$conn->close();