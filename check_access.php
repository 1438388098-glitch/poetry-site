<?php
/**
 * 访问限制检查
 * 在其他 PHP 入口文件顶部 include 此文件
 */

// ========== 设备检测 ==========
function accessDetectDevice($ua) {
    if (!$ua) return '未知';
    if (strpos($ua, 'iPad') !== false || strpos($ua, 'Tablet') !== false) return '平板';
    if (strpos($ua, 'Mobile') !== false) return '手机';
    return '桌面';
}

// ========== IP 地域查询（复用缓存） ==========
function accessGetProvince($ip) {
    if (empty($ip) || $ip === '未知') return '未知';
    $cacheFile = __DIR__ . '/ip_cache.json';
    $ipCache = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];
    if (isset($ipCache[$ip])) return $ipCache[$ip];

    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $url = "http://ip-api.com/json/{$ip}?fields=status,country,regionName&lang=zh-CN";
    $response = @file_get_contents($url, false, $ctx);
    if ($response) {
        $data = json_decode($response, true);
        if (!empty($data['status']) && $data['status'] === 'success' && !empty($data['regionName'])) {
            $ipCache[$ip] = $data['regionName'];
            file_put_contents($cacheFile, json_encode($ipCache, JSON_UNESCAPED_UNICODE));
            return $data['regionName'];
        }
    }
    $ipCache[$ip] = '未知';
    file_put_contents($cacheFile, json_encode($ipCache, JSON_UNESCAPED_UNICODE));
    return '未知';
}

// ========== 主检查函数 ==========
function checkAccess() {
    $rulesFile = __DIR__ . '/access_rules.json';
    if (!file_exists($rulesFile)) return;
    $rules = json_decode(file_get_contents($rulesFile), true);
    if (empty($rules['enabled'])) return;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // 地区检查
    if (!empty($rules['allowed_provinces'])) {
        $province = accessGetProvince($ip);
        if (!in_array($province, $rules['allowed_provinces'])) {
            http_response_code(403);
            $msg = !empty($rules['block_message']) ? $rules['block_message'] : '抱歉，您所在的地区暂无法访问。';
            die('<html><head><meta charset="utf-8"><title>访问受限</title><style>body{font-family:"Noto Serif SC",serif;background:#FAF8F5;color:#191919;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:24px;text-align:center;}.box{max-width:420px;}.code{font-size:3rem;color:#C4553A;font-weight:700;margin-bottom:8px;}.msg{font-size:1rem;color:#6B6B6B;line-height:1.8;}</style></head><body><div class="box"><div class="code">403</div><div class="msg">' . htmlspecialchars($msg) . '</div></div></body></html>');
            exit;
        }
    }

    // 设备检查
    if (!empty($rules['allowed_devices'])) {
        // 排除管理员
        if (empty($_SESSION['admin'])) {
            $device = accessDetectDevice($ua);
            if (!in_array($device, $rules['allowed_devices'])) {
                http_response_code(403);
                $msg = !empty($rules['block_message']) ? $rules['block_message'] : '抱歉，您使用的设备暂无法访问。';
                die('<html><head><meta charset="utf-8"><title>访问受限</title><style>body{font-family:"Noto Serif SC",serif;background:#FAF8F5;color:#191919;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:24px;text-align:center;}.box{max-width:420px;}.code{font-size:3rem;color:#C4553A;font-weight:700;margin-bottom:8px;}.msg{font-size:1rem;color:#6B6B6B;line-height:1.8;}</style></head><body><div class="box"><div class="code">403</div><div class="msg">' . htmlspecialchars($msg) . '</div></div></body></html>');
                exit;
            }
        }
    }
}
