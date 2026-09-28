<?php
$file = __DIR__ . '/guestbook.json';

// 确保文件存在
if (!file_exists($file)) file_put_contents($file, '[]');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $messages = json_decode(file_get_contents($file), true) ?? [];

    if ($action === 'add') {
        $name = trim($input['name'] ?? '');
        $msg = trim($input['message'] ?? '');
        if ($name && $msg) {
            $messages[] = [
                'id' => bin2hex(random_bytes(8)),
                'name' => $name,
                'message' => $msg,
                'time' => date('Y-m-d H:i')
            ];
            file_put_contents($file, json_encode($messages, JSON_UNESCAPED_UNICODE));
        }
    } elseif ($action === 'delete') {
        $id = $input['id'] ?? '';
        $messages = array_values(array_filter($messages, fn($m) => ($m['id'] ?? '') !== $id));
        file_put_contents($file, json_encode($messages, JSON_UNESCAPED_UNICODE));
    }

    header('Content-Type: application/json');
    echo json_encode(array_reverse($messages), JSON_UNESCAPED_UNICODE);
    exit;
}

// GET
$messages = json_decode(file_get_contents($file), true) ?? [];
header('Content-Type: application/json');
echo json_encode(array_reverse($messages), JSON_UNESCAPED_UNICODE);
