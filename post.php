<?php
require_once 'config.php';

// 检查密码
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    die('未授权访问');
}

// 获取访问令牌
function getAccessToken() {
    if (file_exists(TOKEN_FILE)) {
        $tokens = json_decode(file_get_contents(TOKEN_FILE), true);
        if ($tokens && isset($tokens['access_token'])) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, BLOGGER_API . BLOG_ID . '?access_token=' . $tokens['access_token']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 200) {
                return $tokens['access_token'];
            } elseif (isset($tokens['refresh_token'])) {
                $url = 'https://oauth2.googleapis.com/token';
                $data = [
                    'client_id' => CLIENT_ID,
                    'client_secret' => CLIENT_SECRET,
                    'refresh_token' => $tokens['refresh_token'],
                    'grant_type' => 'refresh_token'
                ];
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                curl_close($ch);
                $newTokens = json_decode($response, true);
                if (isset($newTokens['access_token'])) {
                    $tokens['access_token'] = $newTokens['access_token'];
                    file_put_contents(TOKEN_FILE, json_encode($tokens, JSON_PRETTY_PRINT));
                    return $newTokens['access_token'];
                }
            }
        }
    }
    header('Location: oauth-callback.php');
    exit;
}

$accessToken = getAccessToken();

// API 请求
function apiRequest($url, $method = 'GET', $data = null) {
    global $accessToken;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?access_token=' . $accessToken);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode >= 400) {
        return ['error' => 'API 请求失败，状态码: ' . $httpCode];
    }
    return json_decode($response, true);
}

// 发布文章
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish') {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $labels = isset($_POST['labels']) ? array_filter(array_map('trim', explode(',', $_POST['labels']))) : [];
    $data = [
        'title' => $title,
        'content' => $content
    ];
    if (!empty($labels)) {
        $data['labels'] = $labels;
    }
    $response = apiRequest(BLOGGER_API . BLOG_ID . '/posts', 'POST', $data);
    if (isset($response['id'])) {
        echo json_encode(['status' => 'success', 'message' => '文章已发布']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '发布失败: ' . ($response['error'] ?? '未知错误')]);
    }
    exit;
}

// 编辑文章
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $postId = $_POST['post_id'];
    $title = $_POST['title'];
    $content = $_POST['content'];
    $labels = isset($_POST['labels']) ? array_filter(array_map('trim', explode(',', $_POST['labels']))) : [];
    $data = [
        'title' => $title,
        'content' => $content
    ];
    if (!empty($labels)) {
        $data['labels'] = $labels;
    }
    $response = apiRequest(BLOGGER_API . BLOG_ID . '/posts/' . $postId, 'PUT', $data);
    if (isset($response['id'])) {
        echo json_encode(['status' => 'success', 'message' => '文章已更新']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '更新失败: ' . ($response['error'] ?? '未知错误')]);
    }
    exit;
}

// 删除文章
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $postId = $_POST['post_id'];
    $response = apiRequest(BLOGGER_API . BLOG_ID . '/posts/' . $postId, 'DELETE');
    if (!$response) {
        echo json_encode(['status' => 'success', 'message' => '文章已删除']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '删除失败: ' . ($response['error'] ?? '未知错误')]);
    }
    exit;
}

// 上传图片
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_image') {
    if (isset($_FILES['image'])) {
        $file = $_FILES['image'];
        $imageData = file_get_contents($file['tmp_name']);
        $url = 'https://www.googleapis.com/upload/blogger/v3/blogs/' . BLOG_ID . '/posts';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?access_token=' . $accessToken);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $imageData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . $file['type'],
            'Content-Length: ' . $file['size']
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result = json_decode($response, true);
        if ($httpCode == 200 && isset($result['url'])) {
            echo json_encode(['status' => 'success', 'url' => $result['url']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => '图片上传失败: ' . ($result['error'] ?? '未知错误')]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => '未上传图片']);
    }
    exit;
}