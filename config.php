<?php
session_start();

// Google API 配置
define('CLIENT_ID', 'xxxxxxxxxx.apps.googleusercontent.com'); // 从Google API Console获取
define('CLIENT_SECRET', 'xxxxxxxxx');
define('REDIRECT_URI', 'https://yourdomain/oauth-callback.php'); // 回调URL
define('API_KEY', 'YOUR_APIKEY');
define('BLOG_ID', 'YOUR_BLOG_ID'); // 在 Blogger 后台查看
define('ADMIN_PASSWORD', '123456'); // 请更改为强密码


define('SCOPES', 'https://www.googleapis.com/auth/blogger');
define('TOKEN_FILE', __DIR__ . '/tokens.json');


// API 基础 URL
define('BLOGGER_API', 'https://www.googleapis.com/blogger/v3/blogs/');

// 错误处理
ini_set('display_errors', 1);
error_reporting(E_ALL);