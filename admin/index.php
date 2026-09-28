<?php
session_start();
// 管理员凭据从 config.php 读取（不入库）；首次部署复制 config.example.php 为 config.php 并填入真实值
require_once __DIR__ . '/config.php';

$error = '';
$success = '';

// Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === $admin_user && password_verify($_POST['password'], $pass_hash)) {
        $_SESSION['admin'] = true;
    } else {
        $error = '用户名或密码错误';
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Add / Edit poem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_poem']) && !empty($_SESSION['admin'])) {
    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $tag = trim($_POST['tag'] ?? '现代诗');
    $content = trim($_POST['content'] ?? '');
    $background = trim($_POST['background'] ?? '');
    $editId = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

    if ($title && $date && $content) {
        $sortKey = '';
        if (preg_match('/(\d{4})[.\-\/](\d{1,2})[.\-\/](\d{1,2})/', $date, $m)) {
            $sortKey = $m[1] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[3], 2, '0', STR_PAD_LEFT);
        } elseif (preg_match('/(\d{4})[.\-\/](\d{1,2})/', $date, $m)) {
            $sortKey = $m[1] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
        } else {
            $sortKey = date('Y-m-d');
        }

        $monthLabel = trim($_POST['monthLabel'] ?? '');
        if ($monthLabel === '') $monthLabel = null;

        $jsonPath = __DIR__ . '/../poems_extra.json';
        $extra = json_decode(file_get_contents($jsonPath), true) ?? [];

        if ($editId) {
            // 更新已有作品
            $updated = false;
            foreach ($extra as $k => $p) {
                if (($p['id'] ?? 0) === $editId) {
                    $extra[$k] = [
                        'id' => $editId,
                        'title' => $title,
                        'date' => $date,
                        'sortKey' => $sortKey,
                        'monthLabel' => $monthLabel,
                        'tag' => $tag,
                        'content' => $content,
                        'background' => $background,
                    ];
                    $updated = true;
                    break;
                }
            }
            if (!$updated) {
                $error = '未找到要修改的作品';
            } else {
                $written = file_put_contents($jsonPath, json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $success = $written !== false ? '作品已更新' : '写入失败，请检查文件权限';
            }
        } else {
            $maxId = 24;
            foreach ($extra as $p) {
                if (($p['id'] ?? 0) > $maxId) $maxId = $p['id'];
            }
            $extra[] = [
                'id' => $maxId + 1,
                'title' => $title,
                'date' => $date,
                'sortKey' => $sortKey,
                'monthLabel' => $monthLabel,
                'tag' => $tag,
                'content' => $content,
                'background' => $background,
            ];
            $written = file_put_contents($jsonPath, json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $success = $written !== false ? '作品已添加' : '写入失败，请检查文件权限';
        }
    } else {
        $error = '标题、日期和正文为必填项';
    }
}

// Delete poem
if (isset($_GET['delete']) && !empty($_SESSION['admin'])) {
    $jsonPath = __DIR__ . '/../poems_extra.json';
    $extra = json_decode(file_get_contents($jsonPath), true) ?? [];
    $extra = array_values(array_filter($extra, function($p) {
        return ($p['id'] ?? 0) != $_GET['delete'];
    }));
    file_put_contents($jsonPath, json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header('Location: index.php');
    exit;
}

// Save about content
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_about']) && !empty($_SESSION['admin'])) {
    $aboutPath = __DIR__ . '/../about_content.json';
    $text = trim($_POST['about_text'] ?? '');
    file_put_contents($aboutPath, json_encode(['text' => $text], JSON_UNESCAPED_UNICODE));
    $success = '关于页面已更新';
}

// Save access rules
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_access']) && !empty($_SESSION['admin'])) {
    $rulesPath = __DIR__ . '/../access_rules.json';
    $rules = [
        'enabled' => !empty($_POST['access_enabled']),
        'allowed_provinces' => $_POST['allowed_provinces'] ?? [],
        'allowed_devices' => $_POST['allowed_devices'] ?? [],
        'block_message' => trim($_POST['block_message'] ?? ''),
    ];
    file_put_contents($rulesPath, json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $success = '访问规则已更新';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理 · 陌生的你</title>
<link rel="stylesheet" href="../style.css">
<style>
.admin-wrap { max-width:720px; margin:0 auto; padding:48px 24px; }
.admin-wrap h2 { margin-bottom:24px; font-weight:600; }
.admin-wrap form { display:flex; flex-direction:column; gap:12px; margin-bottom:32px; }
.admin-wrap input,.admin-wrap textarea,.admin-wrap select {
  padding:12px 16px; border:1px solid var(--border); border-radius:8px;
  font-family:var(--font-serif); font-size:0.92rem; background:var(--bg-card);
  color:var(--text); outline:none; resize:vertical;
}
.admin-wrap input:focus,.admin-wrap textarea:focus { border-color:var(--accent); }
.admin-wrap button {
  align-self:flex-end; padding:10px 28px; background:var(--accent); color:#fff;
  border:none; border-radius:8px; font-family:var(--font-serif); font-size:0.92rem; cursor:pointer;
}
.admin-wrap button:hover { background:var(--accent-hover); }
.msg { padding:12px 16px; border-radius:8px; margin-bottom:16px; }
.msg.error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
.msg.success { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.poem-list { display:flex; flex-direction:column; gap:12px; }
.poem-item { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); padding:16px 20px; display:flex; justify-content:space-between; align-items:center; }
.poem-item .p-title { font-weight:600; font-size:0.92rem; }
.poem-item .p-meta { color:var(--text-tertiary); font-size:0.78rem; margin-top:2px; }
.poem-item a { color:var(--accent); text-decoration:none; font-size:0.82rem; }
.login-form { max-width:320px; margin:120px auto; }
.login-form h2 { text-align:center; }
/* Stats */
.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:24px; }
.stat-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); padding:20px; text-align:center; }
.stat-num { font-size:1.8rem; font-weight:700; color:var(--accent); }
.stat-label { font-size:0.78rem; color:var(--text-tertiary); margin-top:4px; }
.hourly-chart { display:flex; align-items:flex-end; gap:4px; height:100px; margin-bottom:24px; padding:0 4px; }
.hourly-bar { flex:1; background:var(--accent); border-radius:3px 3px 0 0; min-height:2px; position:relative; transition:height 0.3s; }
.hourly-bar:hover { opacity:0.8; }
.hourly-label { font-size:0.6rem; color:var(--text-tertiary); text-align:center; margin-top:4px; }
.stats-row { display:flex; gap:24px; margin-bottom:24px; }
.stats-col { flex:1; }
.stats-col h4 { font-size:0.85rem; color:var(--text-secondary); font-weight:400; margin-bottom:8px; letter-spacing:0.04em; }
.stat-bar-wrap { display:flex; align-items:center; gap:8px; margin-bottom:4px; font-size:0.82rem; }
.stat-bar-bg { flex:1; height:16px; background:#f0eeeb; border-radius:8px; overflow:hidden; }
.stat-bar { height:100%; background:var(--accent); border-radius:8px; min-width:2px; }
.stat-bar-label { width:50px; color:var(--text-secondary); }
.stat-bar-pct { width:36px; text-align:right; color:var(--text-tertiary); font-size:0.75rem; }
.visit-table { width:100%; border-collapse:collapse; font-size:0.82rem; }
.visit-table th { text-align:left; color:var(--text-tertiary); font-weight:400; padding:6px 8px; border-bottom:1px solid var(--border); }
.visit-table td { padding:6px 8px; border-bottom:1px solid var(--border); color:var(--text-secondary); }
/* Preview modal */
.preview-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.4); backdrop-filter:blur(4px); z-index:1000; display:none; justify-content:center; align-items:flex-start; padding:60px 24px; overflow-y:auto; }
.preview-modal { background:var(--bg-card); border-radius:var(--radius); max-width:640px; width:100%; padding:48px 40px; position:relative; box-shadow:0 8px 40px rgba(0,0,0,0.12); animation:modalIn 0.3s ease; margin:auto; }
.preview-close { position:absolute; top:16px; right:20px; background:none; border:none; font-size:1.6rem; color:var(--text-tertiary); cursor:pointer; padding:4px 8px; line-height:1; }
.preview-close:hover { color:var(--text); }
.form-actions { display:flex; gap:12px; justify-content:flex-end; }
.form-actions .btn-preview { background:var(--bg-card); color:var(--text-secondary); border:1px solid var(--border); }
.form-actions .btn-preview:hover { background:var(--bg); color:var(--text); }
/* About editor */
.about-editor { margin-top:32px; }
.about-editor h2 { margin-bottom:16px; }
.about-editor textarea { min-height:200px; }
.about-editor .about-hint { font-size:0.82rem; color:var(--text-tertiary); margin-bottom:8px; }
</style>
</head>
<body>
<div class="admin-wrap">

<?php if (empty($_SESSION['admin'])): ?>
  <form class="login-form" method="post">
    <h2>管理员登录</h2>
    <?php if ($error): ?><div class="msg error"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <input type="text" name="username" placeholder="用户名" required>
    <input type="password" name="password" placeholder="密码" required>
    <button type="submit" name="login">登录</button>
  </form>

<?php else: ?>
  <h2>添加新诗作</h2>
  <?php if ($error): ?><div class="msg error"><?=htmlspecialchars($error)?></div><?php endif; ?>
  <?php if ($success): ?><div class="msg success"><?=htmlspecialchars($success)?></div><?php endif; ?>

  <form method="post">
    <input type="text" name="title" placeholder="标题 *" required>
    <input type="text" name="date" placeholder="日期 *（如 2025.6.15）" required>
    <div style="display:flex;gap:12px;">
      <select name="tag" style="flex:1;">
        <option value="现代诗">现代诗</option>
        <option value="仿写诗">仿写诗</option>
      </select>
      <input type="text" name="monthLabel" placeholder="季节标签（如 2025 年夏，可不填）" style="flex:2;">
    </div>
    <textarea name="content" rows="10" placeholder="正文 *（每行一句）" required></textarea>
    <textarea name="background" rows="3" placeholder="创作手记（可不填）"></textarea>
    <div class="form-actions">
      <button type="button" class="btn-preview" onclick="showPreview()">预览</button>
      <button type="button" id="editCancel" style="display:none;" onclick="cancelEdit()">取消</button>
      <button type="submit" name="add_poem">发布</button>
    </div>
  </form>

  <hr style="border:none;border-top:1px solid var(--border);margin:32px 0;">

  <h2>已添加的作品</h2>
  <?php
  $jsonPath = __DIR__ . '/../poems_extra.json';
  $extra = json_decode(file_get_contents($jsonPath), true) ?? [];
  $extraList = array_reverse($extra);
  ?>
  <script>const extraPoems = <?=json_encode($extra, JSON_UNESCAPED_UNICODE)?>;</script>
  <div class="poem-list">
  <?php foreach ($extraList as $p): ?>
    <div class="poem-item">
      <div>
        <div class="p-title"><?=htmlspecialchars($p['title'] ?? '')?></div>
        <div class="p-meta"><?=htmlspecialchars($p['date'] ?? '')?> · <?=htmlspecialchars($p['tag'] ?? '')?></div>
      </div>
      <div style="display:flex;gap:12px;">
        <a href="#" onclick="editPoem(<?=($p['id'] ?? 0)?>); return false;">编辑</a>
        <a href="?delete=<?=($p['id'] ?? 0)?>" onclick="return confirm('确定删除？')">删除</a>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (empty($extra)): ?><p style="color:var(--text-tertiary);font-size:0.9rem;">暂无额外作品</p><?php endif; ?>
  </div>

  <hr style="border:none;border-top:1px solid var(--border);margin:32px 0;">

  <h2>访客统计</h2>
  <?php
  $logFile = __DIR__ . '/../visitor_log.json';
  $visitors = [];
  if (file_exists($logFile)) {
      $content = file_get_contents($logFile);
      if ($content) $visitors = json_decode($content, true) ?? [];
  }

  $total = count($visitors);
  $uniqueIps = count(array_unique(array_filter(array_column($visitors, 'ip'))));
  $now = time();
  $today24h = array_filter($visitors, function($e) use ($now) {
      return ($e['t'] ?? 0) > $now - 86400;
  });
  $todayCount = count($today24h);
  $todayUnique = count(array_unique(array_filter(array_column($today24h, 'ip'))));

  // Hourly breakdown (last 24h)
  $hourly = [];
  for ($i = 23; $i >= 0; $i--) {
      $hs = $now - ($i + 1) * 3600;
      $he = $now - $i * 3600;
      $c = count(array_filter($visitors, function($e) use ($hs, $he) {
          return ($e['t'] ?? 0) >= $hs && ($e['t'] ?? 0) < $he;
      }));
      $hourly[date('H:00', $he)] = $c;
  }
  $maxHourly = max($hourly) ?: 1;

  // OS detection
  function detectOS($ua) {
      if (!$ua) return '未知';
      if (strpos($ua, 'Windows') !== false) return 'Windows';
      if (strpos($ua, 'Mac OS') !== false && strpos($ua, 'iPhone') === false && strpos($ua, 'iPad') === false) return 'macOS';
      if (strpos($ua, 'iPhone') !== false || strpos($ua, 'iOS') !== false) return 'iOS';
      if (strpos($ua, 'iPad') !== false) return 'iPadOS';
      if (strpos($ua, 'Android') !== false) return 'Android';
      if (strpos($ua, 'Linux') !== false) return 'Linux';
      return '其他';
  }
  $osStats = [];
  foreach ($visitors as $e) {
      $os = detectOS($e['ua'] ?? '');
      $osStats[$os] = ($osStats[$os] ?? 0) + 1;
  }
  arsort($osStats);

  // Device type
  function detectDevice($ua) {
      if (!$ua) return '未知';
      if (strpos($ua, 'iPad') !== false || strpos($ua, 'Tablet') !== false) return '平板';
      if (strpos($ua, 'Mobile') !== false || strpos($ua, 'Android') !== false) return '手机';
      return '桌面';
  }
  $deviceStats = [];
  foreach ($visitors as $e) {
      $d = detectDevice($e['ua'] ?? '');
      $deviceStats[$d] = ($deviceStats[$d] ?? 0) + 1;
  }
  arsort($deviceStats);

  // Recent visits
  $recent = array_slice(array_reverse($visitors), 0, 20);

  // IP province cache
  $ipCacheFile = __DIR__ . '/../ip_cache.json';
  $ipCache = file_exists($ipCacheFile) ? json_decode(file_get_contents($ipCacheFile), true) : [];
  $ipCacheDirty = false;

  function getIpProvince($ip) {
      global $ipCache, $ipCacheDirty;
      if (empty($ip) || $ip === '未知') return '未知';
      if (isset($ipCache[$ip])) return $ipCache[$ip];

      $ctx = stream_context_create(['http' => ['timeout' => 3]]);
      $url = "http://ip-api.com/json/{$ip}?fields=status,country,regionName&lang=zh-CN";
      $response = @file_get_contents($url, false, $ctx);
      if ($response) {
          $data = json_decode($response, true);
          if (!empty($data['status']) && $data['status'] === 'success' && !empty($data['regionName'])) {
              $ipCache[$ip] = $data['regionName'];
              $ipCacheDirty = true;
              return $data['regionName'];
          }
      }
      $ipCache[$ip] = '未知';
      $ipCacheDirty = true;
      return '未知';
  }

  // Province stats
  $provinceStats = [];
  foreach ($visitors as $e) {
      $prov = getIpProvince($e['ip'] ?? '');
      $provinceStats[$prov] = ($provinceStats[$prov] ?? 0) + 1;
  }
  arsort($provinceStats);

  // Total likes
  $totalLikes = 0;
  $likesFile = __DIR__ . '/../likes.json';
  if (file_exists($likesFile)) {
      $likes = json_decode(file_get_contents($likesFile), true);
      foreach ($likes as $data) {
          $totalLikes += $data['c'] ?? 0;
      }
  }

  // Save IP cache if dirty
  if ($ipCacheDirty) {
      file_put_contents($ipCacheFile, json_encode($ipCache, JSON_UNESCAPED_UNICODE));
  }

  // About content
  $aboutText = '';
  $aboutPath = __DIR__ . '/../about_content.json';
  if (file_exists($aboutPath)) {
      $aboutData = json_decode(file_get_contents($aboutPath), true);
      $aboutText = $aboutData['text'] ?? '';
  }
  ?>
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-num"><?=$total?></div><div class="stat-label">总访问</div></div>
    <div class="stat-card"><div class="stat-num"><?=$uniqueIps?></div><div class="stat-label">独立访客</div></div>
    <div class="stat-card"><div class="stat-num"><?=$todayCount?></div><div class="stat-label">近24小时</div></div>
    <div class="stat-card"><div class="stat-num"><?=$totalLikes?></div><div class="stat-label">总点赞</div></div>
  </div>

  <h4 style="font-size:0.85rem;color:var(--text-secondary);font-weight:400;margin-bottom:8px;">近24小时时段分布</h4>
  <div class="hourly-chart">
    <?php foreach ($hourly as $label => $count): ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end;">
        <div class="hourly-bar" style="height:<?=max(round($count / $maxHourly * 80), 2)?>%;width:100%;" title="<?=$label?> — <?=$count?>次"></div>
        <div class="hourly-label"><?=$label?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="stats-row">
    <div class="stats-col">
      <h4>操作系统</h4>
      <?php foreach ($osStats as $os => $count):
        $pct = round($count / max($total, 1) * 100);
      ?>
      <div class="stat-bar-wrap">
        <span class="stat-bar-label"><?=htmlspecialchars($os)?></span>
        <div class="stat-bar-bg"><div class="stat-bar" style="width:<?=$pct?>%"></div></div>
        <span class="stat-bar-pct"><?=$pct?>%</span>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="stats-col">
      <h4>设备类型</h4>
      <?php foreach ($deviceStats as $d => $count):
        $pct = round($count / max($total, 1) * 100);
      ?>
      <div class="stat-bar-wrap">
        <span class="stat-bar-label"><?=htmlspecialchars($d)?></span>
        <div class="stat-bar-bg"><div class="stat-bar" style="width:<?=$pct?>%"></div></div>
        <span class="stat-bar-pct"><?=$pct?>%</span>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="stats-col">
      <h4>地域分布</h4>
      <?php foreach ($provinceStats as $prov => $count):
        $pct = round($count / max($total, 1) * 100);
      ?>
      <div class="stat-bar-wrap">
        <span class="stat-bar-label"><?=$prov?></span>
        <div class="stat-bar-bg"><div class="stat-bar" style="width:<?=$pct?>%"></div></div>
        <span class="stat-bar-pct"><?=$pct?>%</span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <h4 style="font-size:0.85rem;color:var(--text-secondary);font-weight:400;margin-bottom:8px;">最近访问</h4>
  <table class="visit-table">
    <tr><th>时间</th><th>IP</th><th>地域</th><th>设备</th><th>屏幕</th></tr>
    <?php foreach ($recent as $v): ?>
    <tr>
      <td><?=date('m-d H:i', $v['t'] ?? 0)?></td>
      <td><?=htmlspecialchars($v['ip'] ?? '')?></td>
      <td><?=getIpProvince($v['ip'] ?? '')?></td>
      <td><?=htmlspecialchars(detectDevice($v['ua'] ?? ''))?></td>
      <td><?=htmlspecialchars($v['s'] ?? '')?></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <hr style="border:none;border-top:1px solid var(--border);margin:32px 0;">

  <?php
  // Search keyword stats
  $searchLogFile = __DIR__ . '/../search_log.json';
  $searchLogs = [];
  if (file_exists($searchLogFile)) {
      $searchLogs = json_decode(file_get_contents($searchLogFile), true) ?? [];
  }
  $keywordStats = [];
  foreach ($searchLogs as $entry) {
      $kw = $entry['keyword'] ?? '';
      if ($kw) $keywordStats[$kw] = ($keywordStats[$kw] ?? 0) + 1;
  }
  arsort($keywordStats);
  $totalSearches = array_sum($keywordStats);
  ?>
  <h4 style="font-size:0.85rem;color:var(--text-secondary);font-weight:400;margin-bottom:8px;">搜索关键词（近 <?=count($searchLogs)?> 次）</h4>
  <?php if (empty($keywordStats)): ?>
    <p style="font-size:0.82rem;color:var(--text-tertiary);">暂无搜索记录</p>
  <?php else: ?>
  <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:24px;">
    <?php foreach ($keywordStats as $kw => $count):
      $pct = round($count / max($totalSearches, 1) * 100);
    ?>
    <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;background:var(--bg-card);border:1px solid var(--border);border-radius:20px;font-size:0.82rem;">
      <span style="color:var(--text);"><?=htmlspecialchars($kw)?></span>
      <span style="color:var(--accent);font-weight:600;"><?=$count?></span>
      <span style="color:var(--text-tertiary);font-size:0.7rem;"><?=$pct?>%</span>
    </span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <hr style="border:none;border-top:1px solid var(--border);margin:32px 0;">

  <div class="about-editor">
    <h2>关于页面</h2>
    <p class="about-hint">支持 HTML 标签，如 &lt;br&gt; 换行、&lt;em&gt;强调&lt;/em&gt; 等</p>
    <?php if ($success): ?><div class="msg success"><?=htmlspecialchars($success)?></div><?php endif; ?>
    <form method="post">
      <textarea name="about_text"><?=htmlspecialchars($aboutText)?></textarea>
      <button type="submit" name="save_about">保存</button>
    </form>
  </div>

  <?php
  // Load access rules
  $accessRulesPath = __DIR__ . '/../access_rules.json';
  $accessRules = file_exists($accessRulesPath) ? json_decode(file_get_contents($accessRulesPath), true) : ['enabled' => false, 'allowed_provinces' => [], 'allowed_devices' => [], 'block_message' => ''];
  // Collect all known provinces from ip_cache and visitors
  $allProvinces = [];
  foreach ($visitors as $v) {
      $prov = getIpProvince($v['ip'] ?? '');
      if ($prov && $prov !== '未知') $allProvinces[$prov] = true;
  }
  $allProvinces = array_keys($allProvinces);
  sort($allProvinces);
  ?>
  <hr style="border:none;border-top:1px solid var(--border);margin:32px 0;">

  <div class="about-editor">
    <h2>访问限制</h2>
    <?php if ($success): ?><div class="msg success"><?=htmlspecialchars($success)?></div><?php endif; ?>
    <form method="post">
      <label style="display:flex;align-items:center;gap:8px;margin-bottom:16px;cursor:pointer;font-size:0.92rem;">
        <input type="checkbox" name="access_enabled" value="1" <?=$accessRules['enabled'] ? 'checked' : ''?> onchange="document.getElementById('accessFields').style.display=this.checked?'block':'none'">
        启用访问限制
      </label>

      <div id="accessFields" style="display:<?=$accessRules['enabled'] ? 'block' : 'none'?>;margin-bottom:16px;">
        <p style="font-size:0.82rem;color:var(--text-tertiary);margin-bottom:8px;">至少选择一项限制条件。留空表示不限制。</p>

        <div style="margin-bottom:12px;">
          <label style="font-size:0.85rem;color:var(--text-secondary);display:block;margin-bottom:4px;">允许访问的地区</label>
          <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <?php if (empty($allProvinces)): ?>
              <span style="font-size:0.82rem;color:var(--text-tertiary);">暂无地区数据，等有访客后再来设置</span>
            <?php else: ?>
              <label style="font-size:0.78rem;display:flex;align-items:center;gap:3px;cursor:pointer;padding:4px 8px;border:1px solid var(--border);border-radius:6px;">
                <input type="checkbox" onchange="document.querySelectorAll('.prov-cb').forEach(c=>c.checked=this.checked)"> 全选
              </label>
              <?php foreach ($allProvinces as $prov): ?>
              <label style="font-size:0.78rem;display:flex;align-items:center;gap:3px;cursor:pointer;padding:4px 8px;border:1px solid var(--border);border-radius:6px;">
                <input type="checkbox" class="prov-cb" name="allowed_provinces[]" value="<?=htmlspecialchars($prov)?>" <?=in_array($prov, $accessRules['allowed_provinces'] ?? []) ? 'checked' : ''?>>
                <?=htmlspecialchars($prov)?>
              </label>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div style="margin-bottom:12px;">
          <label style="font-size:0.85rem;color:var(--text-secondary);display:block;margin-bottom:4px;">允许访问的设备</label>
          <div style="display:flex;gap:6px;">
            <?php foreach (['手机', '桌面', '平板'] as $d): ?>
            <label style="font-size:0.78rem;display:flex;align-items:center;gap:3px;cursor:pointer;padding:4px 8px;border:1px solid var(--border);border-radius:6px;">
              <input type="checkbox" name="allowed_devices[]" value="<?=$d?>" <?=in_array($d, $accessRules['allowed_devices'] ?? []) ? 'checked' : ''?>>
              <?=$d?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div>
          <label style="font-size:0.85rem;color:var(--text-secondary);display:block;margin-bottom:4px;">拦截提示信息</label>
          <input type="text" name="block_message" value="<?=htmlspecialchars($accessRules['block_message'] ?? '')?>" placeholder="抱歉，您所在的地区暂无法访问。" style="width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-family:var(--font-serif);font-size:0.85rem;background:var(--bg-card);color:var(--text);outline:none;">
        </div>
      </div>

      <button type="submit" name="save_access">保存规则</button>
    </form>
  </div>

  <p style="margin-top:32px;text-align:center;"><a href="?logout" style="color:var(--text-tertiary);font-size:0.82rem;">退出登录</a></p>
<?php endif; ?>

</div>
<!-- 预览弹窗 -->
<div class="preview-overlay" id="previewOverlay" onclick="if(event.target===this)closePreview()">
  <div class="preview-modal">
    <button class="preview-close" onclick="closePreview()">&times;</button>
    <div id="previewBody"></div>
  </div>
</div>

<script>
function showPreview() {
  const title = document.querySelector('input[name="title"]').value.trim();
  const date = document.querySelector('input[name="date"]').value.trim();
  const tag = document.querySelector('select[name="tag"]').value;
  const content = document.querySelector('textarea[name="content"]').value.trim();
  const background = document.querySelector('textarea[name="background"]').value.trim();

  if (!title || !date || !content) {
    alert('标题、日期和正文为必填项');
    return;
  }

  const lines = content.split('\n').map(l =>
    `<span class="line">${escapeHtml(l)}</span>`
  ).join('');

  let bgHtml = '';
  if (background) {
    bgHtml = `<div class="poem-background"><h4>创作手记</h4><p>${escapeHtml(background)}</p></div>`;
  }

  document.getElementById('previewBody').innerHTML = `
    <div class="poem-detail-date">${escapeHtml(date)}</div>
    <h2 class="poem-detail-title">${escapeHtml(title)}</h2>
    <div style="margin-bottom:16px;"><span style="display:inline-block;padding:2px 10px;font-size:0.7rem;color:var(--accent);border:1px solid var(--accent);border-radius:20px;">${escapeHtml(tag)}</span></div>
    <div class="poem-detail-body">${lines}</div>
    ${bgHtml}
  `;

  document.getElementById('previewOverlay').style.display = 'flex';
}

function closePreview() {
  document.getElementById('previewOverlay').style.display = 'none';
}

function escapeHtml(str) {
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}

// ========== 编辑作品 ==========
function editPoem(id) {
  const poem = extraPoems.find(p => p.id === id);
  if (!poem) return;
  document.querySelector('input[name="title"]').value = poem.title;
  document.querySelector('input[name="date"]').value = poem.date;
  document.querySelector('select[name="tag"]').value = poem.tag;
  document.querySelector('input[name="monthLabel"]').value = poem.monthLabel || '';
  document.querySelector('textarea[name="content"]').value = poem.content;
  document.querySelector('textarea[name="background"]').value = poem.background || '';

  let editInput = document.querySelector('input[name="edit_id"]');
  if (!editInput) {
    editInput = document.createElement('input');
    editInput.type = 'hidden';
    editInput.name = 'edit_id';
    document.querySelector('form[method="post"]').appendChild(editInput);
  }
  editInput.value = id;

  document.querySelector('button[name="add_poem"]').textContent = '保存修改';
  document.getElementById('editCancel').style.display = 'inline';
  document.documentElement.scrollTop = 0;
}

function cancelEdit() {
  const f = document.querySelector('form[method="post"]');
  f.querySelector('input[name="edit_id"]')?.remove();
  f.querySelector('input[name="title"]').value = '';
  f.querySelector('input[name="date"]').value = '';
  f.querySelector('select[name="tag"]').value = '现代诗';
  f.querySelector('input[name="monthLabel"]').value = '';
  f.querySelector('textarea[name="content"]').value = '';
  f.querySelector('textarea[name="background"]').value = '';
  document.querySelector('button[name="add_poem"]').textContent = '发布';
  document.getElementById('editCancel').style.display = 'none';
  localStorage.removeItem('admin_poem_draft');
}

// ========== 草稿自动保存 ==========
(function() {
  const form = document.querySelector('form[method="post"]');
  if (!form || !form.querySelector('input[name="add_poem"]')) return;
  const fields = {
    title: form.querySelector('input[name="title"]'),
    date: form.querySelector('input[name="date"]'),
    tag: form.querySelector('select[name="tag"]'),
    monthLabel: form.querySelector('input[name="monthLabel"]'),
    content: form.querySelector('textarea[name="content"]'),
    background: form.querySelector('textarea[name="background"]')
  };

  // Restore draft
  const saved = localStorage.getItem('admin_poem_draft');
  if (saved) {
    try {
      const data = JSON.parse(saved);
      if (data.title) fields.title.value = data.title;
      if (data.date) fields.date.value = data.date;
      if (data.tag) fields.tag.value = data.tag;
      if (data.monthLabel) fields.monthLabel.value = data.monthLabel;
      if (data.content) fields.content.value = data.content;
      if (data.background) fields.background.value = data.background;
    } catch(e) {}
  }

  // Auto-save on input
  let saveTimer;
  function saveDraft() {
    const data = {};
    for (const [key, el] of Object.entries(fields)) {
      data[key] = el ? el.value : '';
    }
    localStorage.setItem('admin_poem_draft', JSON.stringify(data));
  }
  for (const el of Object.values(fields)) {
    if (el) el.addEventListener('input', () => {
      clearTimeout(saveTimer);
      saveTimer = setTimeout(saveDraft, 500);
    });
  }

  // Clear draft on successful submit
  form.addEventListener('submit', () => {
    // Only clear after a moment, so if the submit fails we keep the draft
    setTimeout(() => localStorage.removeItem('admin_poem_draft'), 1000);
  });
})();
</script>
</body>
</html>
