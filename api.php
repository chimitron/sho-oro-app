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
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT recorded_at, price_eur, tokens_remaining
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
            'tokens_remaining' => $row['tokens_remaining'] !== null ? (int) $row['tokens_remaining'] : null,
        ];
    }
    $db->close();

    // Los tokens más recientes son los más relevantes
    $latestRow      = count($rows) > 0 ? end($rows) : null;
    $tokensRemaining = $latestRow ? $latestRow['tokens_remaining'] : null;

    echo json_encode([
        'history'          => $rows,
        'tokens_remaining' => $tokensRemaining,
        'count'            => count($rows),
        'days'             => $days,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos', 'detail' => $e->getMessage()]);
}
