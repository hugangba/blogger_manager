<?php
require_once 'config.php';

// 密码验证
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === ADMIN_PASSWORD) {
            $_SESSION['authenticated'] = true;
        } else {
            $error = '密码错误';
        }
    }
}

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <title>登录</title>
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body>
        <div class="container">
            <h2>请输入密码</h2>
            <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
            <form method="post">
                <input type="password" name="password" required>
                <button type="submit">登录</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// API 请求
function apiRequest($url, $method = 'GET', $data = null, $queryParams = []) {
    global $accessToken;
    if (is_null($accessToken)) {
        logMessage("Access Token 为空，重定向到授权");
        header('Location: oauth-callback.php');
        exit;
    }
    $ch = curl_init();
    $queryParams['access_token'] = urlencode($accessToken);
    $queryParams['key'] = urlencode(API_KEY);
    $url .= '?' . http_build_query($queryParams);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $responseData = json_decode($response, true);
    curl_close($ch);
    if ($httpCode >= 400) {
        $errorMsg = isset($responseData['error']['message']) ? $responseData['error']['message'] : 'API 请求失败';
        logMessage("API 请求失败: $errorMsg (HTTP $httpCode)");
        return ['error' => $errorMsg, 'code' => $httpCode];
    }
    return $responseData;
}

// 获取文章列表
function getPosts($pageToken = '') {
    global $accessToken;
    $url = BLOGGER_API . BLOG_ID . '/posts';
    $queryParams = ['maxResults' => 10];
    if ($pageToken) {
        $queryParams['pageToken'] = $pageToken;
    }
    return apiRequest($url, 'GET', null, $queryParams);
}

$accessToken = getAccessToken();
if (is_null($accessToken)) {
    logMessage("获取 Access Token 失败，重定向到授权");
    header('Location: oauth-callback.php');
    exit;
}

$postsData = getPosts(isset($_GET['pageToken']) ? $_GET['pageToken'] : '');
$errorMessage = isset($postsData['error']) ? $postsData['error'] . ' (状态码: ' . ($postsData['code'] ?? '未知') . ')' : '';
$posts = isset($postsData['items']) ? $postsData['items'] : [];
$nextPageToken = isset($postsData['nextPageToken']) ? $postsData['nextPageToken'] : '';

?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>Blogger 管理</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/wangeditor/wangeditor.css">
</head>
<body>
    <div class="container">
        <h1>Blogger 管理</h1>
        <button onclick="showEditor('new')">新建文章</button>
        <h2>文章列表</h2>
        <?php if ($errorMessage): ?>
            <p style="color:red;">错误：<?php echo htmlspecialchars($errorMessage); ?> <a href="oauth-callback.php">重新授权</a></p>
        <?php endif; ?>
        <?php if (empty($posts) && !$errorMessage): ?>
            <p>暂无文章</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>标题</th>
                    <th>操作</th>
                </tr>
                <?php foreach ($posts as $post): ?>
                <tr>
                    <td><?php echo htmlspecialchars($post['title']); ?></td>
                    <td class="actions">
                        <button class="edit-btn" data-post-id="<?php echo htmlspecialchars($post['id']); ?>" 
                                data-title="<?php echo htmlspecialchars(addslashes($post['title'])); ?>" 
                                data-content="<?php echo htmlspecialchars(addslashes($post['content'])); ?>" 
                                data-labels="<?php echo htmlspecialchars(addslashes(implode(',', $post['labels'] ?? []))); ?>"
                                onclick="editPost(this)">编辑</button>
                        <button onclick="deletePost('<?php echo htmlspecialchars($post['id']); ?>')">删除</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
        <?php if ($nextPageToken): ?>
            <a href="?pageToken=<?php echo $nextPageToken; ?>">下一页</a>
        <?php endif; ?>
    </div>

    <!-- 编辑器模态框 -->
    <div id="editorModal" style="display:none;">
        <div class="modal-content">
            <h2 id="editorTitle">新建文章</h2>
            <input type="text" id="postTitle" placeholder="文章标题">
            <input type="text" id="postLabels" placeholder="标签（用逗号分隔，可选）">
            <div id="editor" style="min-height:300px;"></div>
            <button onclick="savePost()">保存</button>
            <button onclick="closeEditor()">取消</button>
        </div>
    </div>

    <script src="assets/wangeditor/wangeditor.min.js"></script>
    <script src="assets/js/editor.js"></script>
</body>
</html>
