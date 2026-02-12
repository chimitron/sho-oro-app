<?php
declare(strict_types=1);

function getDB(): SQLite3 {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $db = new SQLite3($dir . '/prices.db');
    $db->enableExceptions(true);
    $db->exec("
        CREATE TABLE IF NOT EXISTS prices (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            recorded_at     TEXT    NOT NULL DEFAULT (datetime('now')),
            price_eur       REAL    NOT NULL,
            tokens_remaining INTEGER
        )
    ");
    return $db;
}

function savePrice(float $price, ?int $tokens): void {
    try {
        $db   = getDB();
        $stmt = $db->prepare("
            INSERT INTO prices (price_eur, tokens_remaining)
            VALUES (:price, :tokens)
        ");
        $stmt->bindValue(':price',  $price,  SQLITE3_FLOAT);
        $stmt->bindValue(':tokens', $tokens, $tokens === null ? SQLITE3_NULL : SQLITE3_INTEGER);
        $stmt->execute();
        $db->close();
    } catch (Exception $e) {
        // Fallo silencioso — no interrumpir la respuesta HTTP por un error de BD
        error_log('[oro-app] savePrice error: ' . $e->getMessage());
    }
}

/**
 * Busca recursivamente el precio BID del oro en EUR dentro del JSON de la API.
 */
function extractBid(array $data): ?float {
    if (isset($data['EUR']['bid']))  return (float) $data['EUR']['bid'];
    if (isset($data['eur']['bid']))  return (float) $data['eur']['bid'];
    foreach ($data as $value) {
        if (is_array($value)) {
            $found = extractBid($value);
            if ($found !== null) return $found;
        }
    }
    return null;
}

/**
 * Busca los tokens restantes en el JSON de respuesta de la API.
 * Comprueba los nombres de campo más habituales en APIs de precio de commodities.
 */
function extractTokens(array $data): ?int {
    $candidates = ['tokens_remaining', 'remaining_tokens', 'tokens', 'quota', 'credits', 'remaining'];
    foreach ($candidates as $key) {
        if (isset($data[$key]) && is_numeric($data[$key])) {
            return (int) $data[$key];
        }
    }
    // Buscar también en el primer nivel de subobjetos
    foreach ($data as $value) {
        if (is_array($value)) {
            foreach ($candidates as $key) {
                if (isset($value[$key]) && is_numeric($value[$key])) {
                    return (int) $value[$key];
                }
            }
        }
    }
    return null;
}
