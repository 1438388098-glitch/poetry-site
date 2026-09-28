<?php
$file = __DIR__ . '/likes.json';
if (!file_exists($file)) file_put_contents($file, '{}');

function loadLikes() {
    global $file;
    return json_decode(file_get_contents($file), true) ?? [];
}

function saveLikes($data) {
    global $file;
    file_put_contents($file, json_encode($data));
}

$ip = md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (($input['action'] ?? '') !== 'toggle') {
        http_response_code(400);
        exit;
    }

    $id = (string)($input['id'] ?? '');
    if (!$id) {
        http_response_code(400);
        exit;
    }

    $likes = loadLikes();
    if (!isset($likes[$id])) $likes[$id] = ['c' => 0, 'ips' => []];

    $idx = array_search($ip, $likes[$id]['ips']);
    if ($idx !== false) {
        array_splice($likes[$id]['ips'], $idx, 1);
        $likes[$id]['c']--;
        $liked = false;
    } else {
        $likes[$id]['ips'][] = $ip;
        $likes[$id]['c']++;
        $liked = true;
    }
    saveLikes($likes);

    header('Content-Type: application/json');
    echo json_encode(['liked' => $liked, 'count' => $likes[$id]['c']]);
    exit;
}

// GET — 返回当前访客的点赞状态
$likes = loadLikes();
$result = [];
foreach ($likes as $id => $data) {
    $result[$id] = [
        'c' => $data['c'],
        'liked' => in_array($ip, $data['ips']),
    ];
}

// 确保所有 24+ 首诗都有默认值
for ($i = 1; $i <= 30; $i++) {
    $sid = (string)$i;
    if (!isset($result[$sid])) {
        $result[$sid] = ['c' => 0, 'liked' => false];
    }
}

header('Content-Type: application/json');
echo json_encode($result);
