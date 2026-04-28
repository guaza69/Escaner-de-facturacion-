<?php
header('Content-Type: application/json');
session_start();
session_destroy();

// Eliminar cookies de recordarme
setcookie('factura_usuario', '', time() - 3600, '/');
setcookie('factura_nombre',  '', time() - 3600, '/');

echo json_encode(["ok" => true]);
