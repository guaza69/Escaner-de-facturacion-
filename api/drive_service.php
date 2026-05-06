<?php
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Obtiene el servicio de Google Drive usando la cuenta de servicio.
 */
function getDriveService() {
    $config = include __DIR__ . '/google_config.php';
    
    if (!file_exists($config['credentials_path'])) {
        throw new Exception("Archivo de credenciales no encontrado en: " . $config['credentials_path']);
    }

    $client = new Google\Client();
    $client->setAuthConfig($config['credentials_path']);
    $client->addScope(Google\Service\Drive::DRIVE_FILE);
    $client->setAccessType('offline');

    return new Google\Service\Drive($client);
}

/**
 * Crea una carpeta si no existe y retorna su ID.
 */
function createFolderIfNotExists($nombre, $parentId = null) {
    $service = getDriveService();
    
    // Buscar si ya existe
    $query = "name = '$nombre' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
    if ($parentId) {
        $query .= " and '$parentId' in parents";
    }

    $results = $service->files->listFiles([
        'q' => $query,
        'spaces' => 'drive',
        'fields' => 'files(id, name)',
    ]);

    if (count($results->getFiles()) > 0) {
        return $results->getFiles()[0]->getId();
    }

    // Crear si no existe
    $fileMetadata = new Google\Service\Drive\DriveFile([
        'name' => $nombre,
        'mimeType' => 'application/vnd.google-apps.folder',
    ]);
    
    if ($parentId) {
        $fileMetadata->setParents([$parentId]);
    }

    $folder = $service->files->create($fileMetadata, ['fields' => 'id']);
    return $folder->id;
}

function uploadFileToDrive($rutaLocal, $nombreArchivo, $folderId) {
    $config = include __DIR__ . '/google_config.php';
    
    if (empty($config['script_url'])) {
        throw new Exception("Script URL no configurada en google_config.php");
    }

    $content = file_get_contents($rutaLocal);
    $base64 = base64_encode($content);
    $mimeType = mime_content_type($rutaLocal);

    $payload = json_encode([
        "folderId" => $folderId,
        "fileName" => $nombreArchivo,
        "mimeType" => $mimeType,
        "base64"   => $base64
    ]);

    $ch = curl_init($config['script_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Respuesta RAW: " . $response . "\n"; // Habilitar para debug si es necesario

    if ($httpCode !== 200) {
        throw new Exception("Error en el puente Google Apps Script (HTTP $httpCode): " . $response);
    }

    $result = json_decode($response, true);
    if (!$result || !$result['ok']) {
        throw new Exception("El puente de Google reportó un error: " . ($result['error'] ?? 'Desconocido'));
    }

    return [
        'id'  => $result['id'],
        'url' => $result['url']
    ];
}

