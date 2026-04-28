<?php
$conn = new mysqli("localhost", "root", "", "facturacion_control");

if ($conn->connect_error) {
    header('Content-Type: application/json');
    die(json_encode(["ok" => false, "error" => "DB connection failed: " . $conn->connect_error]));
}
?>