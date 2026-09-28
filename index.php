<?php
session_start();
require_once __DIR__ . '/check_access.php';
checkAccess();
// 输出 index.html 内容
readfile(__DIR__ . '/index.html');
