<?php

// 保存笔记的目录路径，推荐使用绝对路径，且路径应位于文档根目录之外。
$save_path = __DIR__ . '/_tmp';

// 如果路径无效则终止程序。
if (!is_dir($save_path)) {
    die('Invalid save path');
}

// 禁用缓存。
header('Cache-Control: no-store');

// 限制POST内容大小，防止大文件上传攻击
$MAX_NOTE_SIZE = 1024 * 100; // 100KB

// 检查是否为新建笔记的API请求
if (isset($_GET['new']) && ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['text']))) {
    // 生成一个随机的5个字符的笔记名称
    $random_note_name = substr(str_shuffle('234579abcdefghjkmnpqrstwxyz'), 0, 5);
    $path = $save_path . '/' . $random_note_name;

    // 获取文本内容
    $text = isset($_POST['text']) ? $_POST['text'] : (isset($_GET['text']) ? $_GET['text'] : file_get_contents("php://input"));

    // 如果POST内容超出大小限制，终止请求。
    if (strlen($text) > $MAX_NOTE_SIZE) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => 'Content too large']));
    }

    // 保存或更新文件内容
    if (file_put_contents($path, $text) === false) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => 'Failed to save the note']));
    }

    // 设置响应头为JSON格式
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'note' => $random_note_name]);
    exit;
}

// 获取笔记名称
$note_name = isset($_GET['note']) ? $_GET['note'] : null;

// 如果未提供笔记名称，或名称过长，或包含非法字符。
if (!$note_name || strlen($note_name) > 64 || !preg_match('/^[a-zA-Z0-9_-]+$/', $note_name)) {
    // 生成一个5个随机无歧义字符的名称，并重定向到该名称。
    $random_note_name = substr(str_shuffle('234579abcdefghjkmnpqrstwxyz'), 0, 5);
    header("Location: /" . $random_note_name); // 不带查询参数
    exit;
}

// 构建笔记文件的完整路径，并防止目录遍历攻击。
$path = $save_path . '/' . $note_name;

// 确保路径不会包含 "../" 防止目录遍历攻击。
if (strpos($note_name, '..') !== false || strpos($note_name, '/') !== false) {
    die('Invalid note path');
}

// 处理POST请求，用于保存或更新笔记内容。
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['text']) || file_get_contents("php://input"))) {
    $text = isset($_POST['text']) ? $_POST['text'] : file_get_contents("php://input");

    // 如果POST内容超出大小限制，终止请求。
    if (strlen($text) > $MAX_NOTE_SIZE) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => 'Content too large']));
    }

    // 更新文件内容，并处理文件系统异常
    if (file_put_contents($path, $text) === false) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => 'Failed to save the note']));
    }

    // 如果提供的内容为空，删除文件，并处理删除异常
    if (!strlen($text) && is_file($path) && !unlink($path)) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => 'Failed to delete the note']));
    }

    // 设置响应头为JSON格式
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'note' => $note_name]);
    exit;
}

// 如果请求了原始内容，或客户端是curl或wget，直接返回文件内容。
if (isset($_GET['raw']) || strpos($_SERVER['HTTP_USER_AGENT'], 'curl') === 0 || strpos($_SERVER['HTTP_USER_AGENT'], 'Wget') === 0) {
    if (is_file($path)) {
        header('Content-type: text/plain');
        readfile($path);
    } else {
        header('HTTP/1.0 404 Not Found');
    }
    exit;
}

// 判断是否是新生成的笔记名称
$is_new_note = !is_file($path);

// 读取笔记内容
$note_content = is_file($path) ? file_get_contents($path) : '';

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($note_name, ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<style>
body {
    margin: 0;
    background: #ebeef1;
}
.container {
    position: absolute;
    top: 20px;
    right: 20px;
    bottom: 100px; /* 为底部按钮留出更多空间 */
    left: 20px;
}
#content {
    margin: 0;
    padding: 20px;
    overflow-y: auto;
    resize: none;
    width: 100%;
    height: 100%;
    box-sizing: border-box;
    border: 1px solid #ddd;
    outline: none;
}
#readonly-content {
    margin: 0;
    padding: 20px;
    overflow-y: auto;
    resize: none;
    width: 100%;
    height: 100%;
    box-sizing: border-box;
    border: 1px solid #ddd; /* 添加边框样式 */
    outline: none;
    background: #f9f9f9; /* 可调整背景颜色 */
}
#printable {
    display: none;
}
.button-container {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px; /* 按钮之间的间距 */
}
.button-container button {
    padding: 10px 20px;
    font-size: 16px;
    cursor: pointer;
    background-color: #007bff;
    color: #fff;
    border: none;
    border-radius: 4px;
    transition: background-color 0.3s ease;
}
.button-container button:hover {
    background-color: #0056b3;
}
.copy-success {
    position: fixed;
    bottom: 120px;
    left: 50%;
    transform: translateX(-50%);
    background: #28a745;
    color: #fff;
    padding: 10px 20px;
    border-radius: 4px;
    display: none;
    z-index: 1000;
}
@media (prefers-color-scheme: dark) {
    body {
        background: #333b4d;
    }
    #content, #readonly-content {
        background: #24262b;
        color: #fff;
        border-color: #495265;
    }
}
@media print {
    .container {
        display: none;
    }
    #printable {
        display: block;
        white-space: pre-wrap;
        word-break: break-word;
    }
}
</style>
</head>
<body>
<div class="container">
    <textarea id="content" style="display: none;"><?php echo htmlspecialchars($note_content, ENT_QUOTES, 'UTF-8'); ?></textarea>
    <pre id="readonly-content" contenteditable="false" style="display: none;"><?php echo htmlspecialchars($note_content, ENT_QUOTES, 'UTF-8'); ?></pre>
</div>
<pre id="printable"></pre>
<div class="button-container">
    <button id="toggle-mode">切换到编辑模式</button>
    <button id="copy-button">复制内容</button>
    <button id="copy-link-button">复制链接</button>
</div>
<div id="copy-success" class="copy-success">内容已复制到剪贴板</div>
<div id="copy-link-success" class="copy-success">链接已复制到剪贴板</div>
<script>
var textarea = document.getElementById('content');
var readonlyContent = document.getElementById('readonly-content');
var printable = document.getElementById('printable');
var toggleButton = document.getElementById('toggle-mode');
var copyButton = document.getElementById('copy-button');
var copyLinkButton = document.getElementById('copy-link-button');
var copySuccess = document.getElementById('copy-success');
var copyLinkSuccess = document.getElementById('copy-link-success');
var isEditMode = false;

function uploadContent() {
    if (content !== textarea.value) {
        var temp = textarea.value;
        var request = new XMLHttpRequest();
        request.open('POST', window.location.href, true);
        request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        request.onload = function() {
            if (request.readyState === 4) {
                content = temp;
                setTimeout(uploadContent, 1000);
            }
        }
        request.onerror = function() {
            setTimeout(uploadContent, 1000);
        }
        request.send('text=' + encodeURIComponent(temp));

        // 更新可打印内容。
        printable.removeChild(printable.firstChild);
        printable.appendChild(document.createTextNode(temp));
    } else {
        setTimeout(uploadContent, 1000);
    }
}

var content = textarea.value;

// 初始化可打印内容为文本区域的初始值。
printable.appendChild(document.createTextNode(content));

// 将焦点设置到文本区域。
textarea.focus();
uploadContent();

// 切换模式按钮
toggleButton.addEventListener('click', function() {
    isEditMode = !isEditMode;
    if (isEditMode) {
        textarea.style.display = 'block';
        readonlyContent.style.display = 'none';
        toggleButton.textContent = '切换到只读模式';
        textarea.value = readonlyContent.innerText; // 确保内容同步
        textarea.focus();
    } else {
        textarea.style.display = 'none';
        readonlyContent.style.display = 'block';
        readonlyContent.innerText = textarea.value; // 确保内容同步
        toggleButton.textContent = '切换到编辑模式';
    }
});

// 初始模式判断
if (window.location.search.includes('mode=edit') || <?php echo $is_new_note ? 'true' : 'false'; ?>) {
    isEditMode = true;
    textarea.style.display = 'block';
    readonlyContent.style.display = 'none';
    toggleButton.textContent = '切换到只读模式';
} else {
    isEditMode = false;
    textarea.style.display = 'none';
    readonlyContent.style.display = 'block';
    toggleButton.textContent = '切换到编辑模式';
}

// 复制内容按钮
copyButton.addEventListener('click', function() {
    textarea.select();
    document.execCommand('copy');
    copySuccess.style.display = 'block';
    setTimeout(function() {
        copySuccess.style.display = 'none';
    }, 2000);
});

// 复制链接按钮
copyLinkButton.addEventListener('click', function() {
    var link = window.location.href;
    navigator.clipboard.writeText(link).then(function() {
        copyLinkSuccess.style.display = 'block';
        setTimeout(function() {
            copyLinkSuccess.style.display = 'none';
        }, 2000);
    }, function() {
        alert('复制链接失败，请手动复制：' + link);
    });
});
</script>
</body>
</html>
