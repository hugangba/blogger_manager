<?php
session_start();

// Google API 配置
define('CLIENT_ID', 'xxxxxxxxxx.apps.googleusercontent.com'); // 从Google API Console获取
define('CLIENT_SECRET', 'xxxxxxxxx');
define('REDIRECT_URI', 'https://yourdomain/oauth-callback.php'); // 回调URL
define('API_KEY', 'YOUR_APIKEY');
define('BLOG_ID', 'YOUR_BLOG_ID'); // 在 Blogger 后台查看
define('ADMIN_PASSWORD', '123456'); // 请更改为强密码
define('LOG_FILE', __DIR__ . '/logs/oauth.log'); // 日志文件
define('TOKEN_FILE', __DIR__ . '/tokens.json');


// API 基础 URL
define('BLOGGER_API', 'https://www.googleapis.com/blogger/v3/blogs/');

// 错误处理
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 日志记录函数
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
}

// 获取访问令牌
function getAccessToken() {
    if (!file_exists(TOKEN_FILE)) {
        logMessage("令牌文件不存在: " . TOKEN_FILE);
        return null;
    }

    $lock = fopen(TOKEN_FILE, 'r');
    if (!flock($lock, LOCK_SH)) {
        logMessage("无法获取令牌文件锁");
        fclose($lock);
        return null;
    }

    $tokens = json_decode(file_get_contents(TOKEN_FILE), true);
    flock($lock, LOCK_UN);
    fclose($lock);

    if (!$tokens || !isset($tokens['access_token'])) {
        logMessage("令牌文件为空或无效: " . json_encode($tokens));
        return null;
    }

    // 检查令牌是否有效
    $ch = curl_init();
    $url = BLOGGER_API . BLOG_ID . '?access_token=' . urlencode($tokens['access_token']) . '&key=' . urlencode(API_KEY);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $responseData = json_decode($response, true);
    curl_close($ch);

    if ($httpCode == 200) {
        return $tokens['access_token'];
    } elseif ($httpCode == 403 || $httpCode == 401) {
        $errorMsg = isset($responseData['error']['message']) ? $responseData['error']['message'] : '权限不足';
        logMessage("令牌无效: $errorMsg (HTTP $httpCode)");
        if (isset($tokens['refresh_token'])) {
            // 尝试刷新令牌
            $newTokens = refreshAccessToken($tokens['refresh_token']);
            if (isset($newTokens['access_token'])) {
                return $newTokens['access_token'];
            } else {
                logMessage("刷新令牌失败: " . json_encode($newTokens));
                return null;
            }
        } else {
            logMessage("无刷新令牌");
            return null;
        }
    } else {
        logMessage("API 请求失败: HTTP $httpCode");
        return null;
    }
}

// 刷新访问令牌
function refreshAccessToken($refreshToken) {
    $url = 'https://oauth2.googleapis.com/token';
    $data = [
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 设置超时
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        logMessage("cURL 错误: $error");
        return ['error' => '网络错误', 'code' => 0];
    }

    $tokens = json_decode($response, true);
    if ($httpCode == 200 && isset($tokens['access_token'])) {
        saveTokens($tokens['access_token'], $refreshToken);
        logMessage("成功刷新令牌");
        return $tokens;
    } else {
        $errorMsg = isset($tokens['error_description']) ? $tokens['error_description'] : '未知错误';
        logMessage("刷新令牌失败: $errorMsg (HTTP $httpCode)");
        return ['error' => "刷新令牌失败: $errorMsg", 'code' => $httpCode];
    }
}

// 保存令牌
function saveTokens($accessToken, $refreshToken) {
    $tokens = [
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken
    ];

    $lock = fopen(TOKEN_FILE, 'c+');
    if (!flock($lock, LOCK_EX)) {
        logMessage("无法获取令牌文件写入锁");
        fclose($lock);
        return;
    }

    file_put_contents(TOKEN_FILE, json_encode($tokens, JSON_PRETTY_PRINT));
    flock($lock, LOCK_UN);
    fclose($lock);
    logMessage("令牌已保存到: " . TOKEN_FILE);
}

// 获取授权 URL
function getAuthUrl() {
    $params = [
        'client_id' => CLIENT_ID,
        'redirect_uri' => REDIRECT_URI,
        'response_type' => 'code',
        'scope' => SCOPES,
        'access_type' => 'offline',
        'prompt' => 'consent'
    ];
    return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
}
?>
