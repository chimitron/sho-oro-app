<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? 'history';
$days   = max(1, min((int) ($_GET['days'] ?? 7), 30)); // entre 1 y 30 días

if ($action !== 'history') {
    http_response_code(400);
    echo json_encode(['error' => 'Acción no reconocida']);
    exit;
}

try {
    $db = getDB();

    // 1. Historial de precios (para la gráfica)
    $stmt = $db->prepare("
        SELECT recorded_at, price_eur, silver_eur, tokens_remaining
        FROM   prices
        WHERE  recorded_at >= datetime('now', '-' || :days || ' days')
        ORDER  BY recorded_at ASC
    ");
    $stmt->bindValue(':days', $days, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = [
            'recorded_at'      => $row['recorded_at'],
            'price_eur'        => (float) $row['price_eur'],
            'silver_eur'       => $row['silver_eur'] !== null ? (float) $row['silver_eur'] : null,
            'tokens_remaining' => $row['tokens_remaining'] !== null ? (int) $row['tokens_remaining'] : null,
        ];
    }

    // 2. Último registro (el más reciente de toda la tabla, no solo del rango)
    //    Esto nos da el precio actual y la hora del último cron
    $latestStmt = $db->prepare("
        SELECT recorded_at, price_eur, silver_eur, tokens_remaining
        FROM   prices
        ORDER  BY recorded_at DESC
        LIMIT  1
    ");
    $latestResult = $latestStmt->execute();
    $latestRow = $latestResult->fetchArray(SQLITE3_ASSOC);

    $db->close();

    echo json_encode([
        'history'          => $rows,
        // Datos del último registro (fuente única de verdad para el frontend)
        'latest' => $latestRow ? [
            'price_eur'        => (float) $latestRow['price_eur'],
            'silver_eur'       => $latestRow['silver_eur'] !== null ? (float) $latestRow['silver_eur'] : null,
            'recorded_at'      => $latestRow['recorded_at'],
            'tokens_remaining' => $latestRow['tokens_remaining'] !== null ? (int) $latestRow['tokens_remaining'] : null,
        ] : null,
        'last_updated'     => $latestRow ? $latestRow['recorded_at'] : null,
        'count'            => count($rows),
        'days'             => $days,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos', 'detail' => $e->getMessage()]);
}
