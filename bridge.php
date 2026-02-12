<?php
declare(strict_types=1);

// Importante: los headers van ANTES que cualquier salida
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

// Construimos la URL con las credenciales del config
$url = API_BASE_URL . '?key=' . API_KEY . '&action=' . API_ACTION;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 15,
]);
$res = curl_exec($ch);
// No llamamos curl_close() — está obsoleto desde PHP 8.0 y genera
// un aviso que contamina la respuesta JSON rompiendo el frontend

// Bridge es solo un proxy CORS — no guarda en BD
// El guardado es responsabilidad exclusiva de cron.php
echo $res;
