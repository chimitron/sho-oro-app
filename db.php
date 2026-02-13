<?php
declare(strict_types=1);

function getDB(): SQLite3 {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $db = new SQLite3($dir . '/prices.db');
    $db->enableExceptions(true);

    // Creamos la tabla si no existe (incluye columna plata)
    $db->exec("
        CREATE TABLE IF NOT EXISTS prices (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            recorded_at     TEXT    NOT NULL DEFAULT (datetime('now')),
            price_eur       REAL    NOT NULL,
            silver_eur      REAL,
            tokens_remaining INTEGER
        )
    ");

    // Migración: si la tabla ya existía SIN la columna silver_eur, la añadimos
    // SQLite lanza error si la columna ya existe — lo capturamos y seguimos
    try {
        $db->exec("ALTER TABLE prices ADD COLUMN silver_eur REAL");
    } catch (Exception $e) {
        // La columna ya existe — no pasa nada
    }

    return $db;
}

// Guarda el precio del oro Y la plata en la base de datos
// $silver puede ser null si la API no devuelve precio de plata
function savePrice(float $price, ?float $silver, ?int $tokens): void {
    try {
        $db   = getDB();
        $stmt = $db->prepare("
            INSERT INTO prices (price_eur, silver_eur, tokens_remaining)
            VALUES (:price, :silver, :tokens)
        ");
        $stmt->bindValue(':price',  $price,  SQLITE3_FLOAT);
        $stmt->bindValue(':silver', $silver, $silver === null ? SQLITE3_NULL : SQLITE3_FLOAT);
        $stmt->bindValue(':tokens', $tokens, $tokens === null ? SQLITE3_NULL : SQLITE3_INTEGER);
        $stmt->execute();
        $db->close();
    } catch (Exception $e) {
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
 * Busca el precio BID de la PLATA en EUR dentro del JSON de la API.
 * La estructura esperada es: { "Silver": { "EUR": { "bid": 1.05 } } }
 */
function extractSilverBid(array $data): ?float {
    // Buscamos directamente bajo la clave "Silver" o "silver" a este nivel
    if (isset($data['Silver']['EUR']['bid'])) return (float) $data['Silver']['EUR']['bid'];
    if (isset($data['silver']['EUR']['bid'])) return (float) $data['silver']['EUR']['bid'];
    if (isset($data['Silver']['eur']['bid'])) return (float) $data['Silver']['eur']['bid'];
    if (isset($data['silver']['eur']['bid'])) return (float) $data['silver']['eur']['bid'];

    // Búsqueda recursiva en TODOS los subnodos (no solo los que se llamen "silver")
    // Así encontramos GSJM → Silver → EUR → bid
    foreach ($data as $value) {
        if (is_array($value)) {
            $found = extractSilverBid($value);
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
