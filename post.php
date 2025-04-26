<?php
require_once 'config.php';

// 检查密码
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    die('未授权访问');
}

$accessToken = getAccessToken();
if (is_null($accessToken)) {
    logMessage("获取 Access Token 失败，重定向到授权");
    header('Location: oauth-callback.php');
    exit;
}

// API 请求
function apiRequest($url, $method = 'GET', $data = null) {
    global $accessToken;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?access_token=' . urlencode($accessToken));
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
        logMessage("API 请求失败: HTTP $httpCode");
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
        logMessage("发布文章失败: " . json_encode($response));
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
        logMessage("更新文章失败: " . json_encode($response));
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
        logMessage("删除文章失败: " . json_encode($response));
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
        curl_setopt($ch, CURLOPT_URL, $url . '?access_token=' . urlencode($accessToken));
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
            logMessage("图片上传失败: " . json_encode($result));
            echo json_encode(['status' => 'error', 'message' => '图片上传失败: ' . ($result['error'] ?? '未知错误')]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => '未上传图片']);
    }
    exit;
}
?>
