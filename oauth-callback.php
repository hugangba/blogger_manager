<?php
require_once 'config.php';

// 处理授权回调
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $url = 'https://oauth2.googleapis.com/token';
    $data = [
        'code' => $code,
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'redirect_uri' => REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        logMessage("cURL 错误: $error");
        die('授权失败：网络错误');
    }

    $tokens = json_decode($response, true);
    if ($httpCode == 200 && isset($tokens['access_token']) && isset($tokens['refresh_token'])) {
        saveTokens($tokens['access_token'], $tokens['refresh_token']);
        logMessage("授权成功，保存令牌");
        header('Location: index.php');
        exit;
    } else {
        $errorMsg = isset($tokens['error_description']) ? $tokens['error_description'] : '未知错误';
        logMessage("授权失败: $errorMsg (HTTP $httpCode)");
        die('授权失败：' . $errorMsg);
    }
} else {
    // 触发授权
    logMessage("触发 OAuth 授权");
    header('Location: ' . getAuthUrl());
    exit;
}
?>
