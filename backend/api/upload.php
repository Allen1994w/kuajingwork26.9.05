<?php
/**
 * 文件上传API
 * 路由：backend/api/upload.php?type=image|video
 * POST multipart/form-data, field: file
 */
require_once __DIR__ . '/../db.php';

$user = require_login();
$type = $_GET['type'] ?? 'image';

if (!isset($_FILES['file'])) {
    fail('没有上传文件');
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    fail('上传失败，错误码：' . $file['error']);
}

if ($file['size'] > MAX_UPLOAD_SIZE) {
    fail('文件大小超过限制（最大50MB）');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

// 确定上传目录和URL路径
if ($type === 'video') {
    if (!in_array($mimeType, ALLOWED_VIDEO_TYPES) && !in_array($file['type'], ALLOWED_VIDEO_TYPES)) {
        fail('不支持的视频格式，仅支持MP4/WebM/MOV');
    }
    $subDir = 'videos/';
    $ext = 'mp4';
} else {
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES) && !in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
        fail('不支持的图片格式，仅支持JPG/PNG/GIF/WebP');
    }
    $subDir = 'images/';
    $ext = 'jpg';
    if ($mimeType === 'image/png') $ext = 'png';
    elseif ($mimeType === 'image/gif') $ext = 'gif';
    elseif ($mimeType === 'image/webp') $ext = 'webp';
}

// 创建目录
$uploadPath = UPLOAD_DIR . $subDir;
if (!is_dir($uploadPath)) {
    mkdir($uploadPath, 0755, true);
}

// 生成文件名
$filename = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$destPath = $uploadPath . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    fail('文件保存失败');
}

// 返回URL
$url = UPLOAD_URL . $subDir . $filename;

ok([
    'url' => $url,
    'filename' => $filename,
    'size' => $file['size'],
    'type' => $type,
], '上传成功');
