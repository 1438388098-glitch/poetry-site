<?php
$logFile = __DIR__ . '/search_log.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $keyword = trim($input['keyword'] ?? '');
    if ($keyword === '') {
        http_response_code(400);
        echo json_encode(['error' => 'empty keyword']);
        exit;
    }

    $logs = [];
    if (file_exists($logFile)) {
        $logs = json_decode(file_get_contents($logFile), true) ?? [];
    }

    $logs[] = [
        'keyword' => $keyword,
        'time' => time(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];

    // Keep last 5000 entries
    if (count($logs) > 5000) {
        $logs = array_slice($logs, -5000);
    }

    file_put_contents($logFile, json_encode($logs, JSON_UNESCAPED_UNICODE));
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'method not allowed']);
