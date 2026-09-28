<?php
$aboutPath = __DIR__ . '/../about_content.json';
$text = '';
if (file_exists($aboutPath)) {
    $data = json_decode(file_get_contents($aboutPath), true);
    $text = $data['text'] ?? '';
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="关于艾苇 — 陌生的你">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📝</text></svg>">
<title>关于 · 陌生的你</title>
<link rel="stylesheet" href="../style.css">
<style>
.about-page { max-width:640px; margin:0 auto; padding:60px 24px 80px; }
.about-page h1 { font-size:1.6rem; font-weight:700; margin-bottom:32px; letter-spacing:0.06em; }
.about-text { font-size:0.95rem; line-height:2; color:var(--text-secondary); }
.about-text p { margin-bottom:1.2em; }
.back-link { display:inline-block; margin-top:40px; color:var(--accent); text-decoration:none; font-size:0.88rem; }
.back-link:hover { text-decoration:underline; }
</style>
</head>
<body>
<main class="about-page">
  <h1>关于</h1>
  <div class="about-text"><?=$text ?: '<p style="color:var(--text-tertiary)">暂无介绍</p>'?></div>
  <a href="../" class="back-link">&larr; 返回诗集</a>
</main>
</body>
</html>
