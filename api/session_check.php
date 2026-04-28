<?php
header('Content-Type: application/json');
session_start();

// Si hay sesión activa, responder OK
if (!empty($_SESSION['autenticado'])) {
    echo json_encode(["ok" => true, "nombre" => $_SESSION['nombre']]);
    exit;
}

// Intentar restaurar desde cookie de "recordarme"
if (!empty($_COOKIE['factura_usuario']) && !empty($_COOKIE['factura_nombre'])) {
    $_SESSION['autenticado'] = true;
    $_SESSION['usuario']     = $_COOKIE['factura_usuario'];
    $_SESSION['nombre']      = $_COOKIE['factura_nombre'];
    echo json_encode(["ok" => true, "nombre" => $_SESSION['nombre']]);
    exit;
}

http_response_code(401);
echo json_encode(["ok" => false]);
