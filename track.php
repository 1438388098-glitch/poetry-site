<?php
$logFile = __DIR__ . '/visitor_log.json';
$screen = $_GET['s'] ?? '';
$entry = [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
    'ref' => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 200),
    's' => substr($screen, 0, 20),
    't' => time(),
];

$log = [];
if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    if ($content) $log = json_decode($content, true) ?? [];
}

$log[] = $entry;

// 只保留最近 30 天
$cutoff = time() - 30 * 86400;
$log = array_values(array_filter($log, function($e) use ($cutoff) {
    return ($e['t'] ?? 0) > $cutoff;
}));

file_put_contents($logFile, json_encode($log, JSON_UNESCAPED_UNICODE));
http_response_code(204);
