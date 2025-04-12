let editor = null;
let postId = null;

// 等待 DOM 加载完成
document.addEventListener('DOMContentLoaded', function() {
    // 显示编辑器
    window.showEditor = function(mode, id = null, title = '', content = '', labels = '') {
        postId = id;
        const modal = document.getElementById('editorModal');
        if (!modal) {
            alert('模态框未找到！请检查 HTML 结构。');
            console.error('模态框 #editorModal 缺失');
            return;
        }
        modal.style.display = 'flex';
        document.getElementById('editorTitle').innerText = mode === 'new' ? '新建文章' : '编辑文章';
        document.getElementById('postTitle').value = title;
        document.getElementById('postLabels').value = labels;

        const editorContainer = document.getElementById('editor');
        if (!editorContainer) {
            alert('编辑器容器未找到！请检查 HTML 结构。');
            console.error('编辑器容器 #editor 缺失');
            return;
        }

        // 初始化编辑器 (v4.x)
        if (!editor) {
            try {
                if (typeof window.wangEditor === 'undefined') {
                    alert('wangEditor 未加载！请确保 assets/wangeditor/wangeditor.min.js 存在。');
                    console.error('wangEditor 未定义，可能文件缺失或路径错误');
                    return;
                }
                editor = new window.wangEditor(editorContainer);
                editor.config.uploadImgServer = 'post.php';
                editor.config.uploadImgParams = { action: 'upload_image' };
                editor.config.uploadFileName = 'image';
                editor.config.height = 400;
                editor.create();
            } catch (e) {
                alert('编辑器初始化失败：' + e.message);
                console.error('编辑器初始化错误:', e);
                return;
            }
        }

        // 设置内容
        editor.txt.html(content);
    };

    // 关闭编辑器
    window.closeEditor = function() {
        const modal = document.getElementById('editorModal');
        modal.style.display = 'none';
        if (editor) {
            editor.txt.clear();
        }
        document.getElementById('postTitle').value = '';
        document.getElementById('postLabels').value = '';
        postId = null;
    };

    // 保存文章
    window.savePost = function() {
        if (!editor) {
            alert('编辑器未初始化，无法保存！');
            return;
        }
        const title = document.getElementById('postTitle').value;
        const content = editor.txt.html();
        const labels = document.getElementById('postLabels').value;
        const action = postId ? 'update' : 'publish';
        const data = new FormData();
        data.append('action', action);
        data.append('title', title);
        data.append('content', content);
        data.append('labels', labels);
        if (postId) data.append('post_id', postId);

        fetch('post.php', {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.status === 'success') {
                location.reload();
            }
        })
        .catch(error => {
            console.error('保存文章失败:', error);
            alert('保存文章失败，请检查网络！');
        });
    };

    // 编辑文章
    window.editPost = function(button) {
        const id = button.getAttribute('data-post-id');
        const title = button.getAttribute('data-title');
        const content = button.getAttribute('data-content');
        const labels = button.getAttribute('data-labels');
        showEditor('edit', id, title, content, labels);
    };

    // 删除文章
    window.deletePost = function(id) {
        if (confirm('确定删除这篇文章？')) {
            const data = new FormData();
            data.append('action', 'delete');
            data.append('post_id', id);
            fetch('post.php', {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(result => {
                alert(result.message);
                if (result.status === 'success') {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('删除文章失败:', error);
                alert('删除文章失败，请检查网络！');
            });
        }
    };
});
