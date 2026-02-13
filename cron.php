<?php
declare(strict_types=1);

/**
 * cron.php — Consulta la API de oro cada hora y guarda el precio en SQLite.
 *
 * Crontab recomendado (ejecutar como el usuario del servidor web):
 *   0 * * * * php /ruta/completa/a/cron.php >> /var/log/oro-cron.log 2>&1
 */

// Bloqueamos acceso desde navegador web (litespeed, apache, nginx, fpm)
// Pero permitimos CLI y CGI (que es lo que usan algunos cron en hosting compartido)
$sapi = php_sapi_name();
$sapisWeb = ['litespeed', 'apache2handler', 'fpm-fcgi', 'phpdbg'];
if (in_array($sapi, $sapisWeb, true)) {
    http_response_code(403);
    exit('Acceso denegado.');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// Construimos la URL completa combinando base + credenciales
define('API_URL', API_BASE_URL . '?key=' . API_KEY . '&action=' . API_ACTION);

function fetchFromAPI(): ?string {
    $ch = curl_init(API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    // curl_close() obsoleto desde PHP 8.0 — no se llama

    if ($err) {
        log_line("ERROR curl: $err");
        return null;
    }
    return $res ?: null;
}

// Guardamos log en data/cron.log para poder consultarlo remotamente
function log_line(string $msg): void {
    $linea = date('Y-m-d H:i:s') . ' | ' . $msg . PHP_EOL;
    echo $linea;
    // Escribimos también en fichero (misma carpeta que la BD)
    $logFile = __DIR__ . '/data/cron.log';
    file_put_contents($logFile, $linea, FILE_APPEND | LOCK_EX);
}

// --- Main ---

$raw = fetchFromAPI();
if (!$raw) {
    log_line('ERROR: respuesta vacía de la API');
    exit(1);
}

$data = json_decode($raw, true);
if (!$data) {
    log_line('ERROR: JSON inválido — ' . substr($raw, 0, 120));
    exit(1);
}

$price  = extractBid($data);
$silver = extractSilverBid($data);  // Extraemos también el precio de la plata
$tokens = extractTokens($data);

if ($price === null) {
    log_line('ERROR: no se encontró el precio BID del oro en la respuesta');
    log_line('RAW: ' . substr($raw, 0, 200));
    exit(1);
}

// Guardamos oro + plata + tokens en la base de datos
savePrice($price, $silver, $tokens);

$silverStr = $silver !== null ? "Plata: {$silver}€/gr" : 'Plata: no disponible';
$tokensStr = $tokens !== null ? "Tokens: $tokens" : 'Tokens: desconocidos';
log_line("OK | Oro: {$price}€/gr | $silverStr | $tokensStr");
exit(0);
