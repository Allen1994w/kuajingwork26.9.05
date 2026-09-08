<?php
/**
 * 跨境电商工作台 - 配置文件
 * 部署到宝塔后，请修改以下数据库配置
 */

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'kuajing_work');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// 网站配置
define('SITE_URL', ''); // 留空自动获取，如 https://yourdomain.com
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'backend/uploads/'); // 相对index.html的路径

// 安全配置
define('TOKEN_EXPIRE', 86400 * 7); // token有效期7天
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 最大上传50MB

// 允许的图片格式
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
// 允许的视频格式
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/quicktime']);

// 错误显示（部署后改为false）
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 时区
date_default_timezone_set('Asia/Shanghai');
