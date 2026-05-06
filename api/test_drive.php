<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/drive_service.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Diagnóstico de Google Drive (Precisión Máxima)</h1>";

try {
    // 1. Validar Configuración
    echo "<h3>1. Validando Configuración...</h3>";
    $config = include __DIR__ . '/google_config.php';
    $creds = json_decode(file_get_contents($config['credentials_path']), true);
    $sa_email = $creds['client_email'] ?? 'No encontrado';
    
    echo "<b>Email de la Service Account:</b> <code style='background:#eee;padding:2px 5px;'>$sa_email</code><br>";
    echo "<i>(Copia este email y asegúrate de que la carpeta esté compartida EXACTAMENTE con él)</i><br><br>";

    $rootId = trim($config['root_folder_id']);
    echo "ID de carpeta configurado: <code>[$rootId]</code><br>";

    // 2. Validar Conexión
    echo "<h3>2. Probando Conexión...</h3>";
    $service = getDriveService();
    echo "✅ Conexión establecida.<br>";

    // 3. Ver qué carpetas son visibles
    echo "<h3>3. Carpetas compartidas visibles para esta cuenta:</h3>";
    $query = "mimeType = 'application/vnd.google-apps.folder' and trashed = false";
    $results = $service->files->listFiles(['q' => $query, 'fields' => 'files(id, name)']);
    
    if (count($results->getFiles()) == 0) {
        echo "❌ No se ve NINGUNA carpeta compartida. Esto confirma que el permiso no ha llegado o el email es incorrecto.<br>";
    } else {
        echo "✅ Carpetas visibles:<br>";
        foreach ($results->getFiles() as $file) {
            echo "- " . $file->getName() . " (ID: " . $file->getId() . ")<br>";
        }
    }

    // 4. Intentar acceder al ID directamente
    echo "<h3>4. Acceso directo al ID:</h3>";
    try {
        $root = $service->files->get($rootId, ['fields' => 'id, name']);
        echo "✅ ÉXITO: Carpeta encontrada: " . $root->getName() . "<br>";
    } catch (Exception $e) {
        echo "❌ FALLO: Sigue dando error 404 para el ID: <code>$rootId</code><br>";
        echo "<b>Respuesta de Google:</b> " . $e->getMessage() . "<br>";
    }

} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ ERROR GENERAL</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}