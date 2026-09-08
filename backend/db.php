<?php
/**
 * 数据库连接与通用工具
 */
require_once __DIR__ . '/config.php';

class DB {
    private static $pdo = null;

    public static function conn() {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => '数据库连接失败：' . $e->getMessage()]);
                exit;
            }
        }
        return self::$pdo;
    }

    public static function query($sql, $params = []) {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch($sql, $params = []) {
        return self::query($sql, $params)->fetch();
    }

    public static function fetchAll($sql, $params = []) {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = array_map(function($f) { return ':' . $f; }, $fields);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
        self::query($sql, $data);
        return self::conn()->lastInsertId();
    }

    public static function update($table, $data, $where, $whereParams = []) {
        $sets = [];
        foreach (array_keys($data) as $f) {
            $sets[] = $f . '=:' . $f;
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(',', $sets) . ' WHERE ' . $where;
        self::query($sql, array_merge($data, $whereParams));
    }

    public static function delete($table, $where, $params = []) {
        $sql = 'DELETE FROM ' . $table . ' WHERE ' . $where;
        self::query($sql, $params);
    }
}

// ============ 通用工具函数 ============

/**
 * 输出JSON响应
 */
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 成功响应
 */
function ok($data = null, $msg = 'success') {
    json_response(['ok' => true, 'msg' => $msg, 'data' => $data]);
}

/**
 * 失败响应
 */
function fail($msg = 'error', $code = 400) {
    json_response(['ok' => false, 'error' => $msg], $code);
}

/**
 * 获取POST JSON数据
 */
function get_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if ($data === null) {
        $data = $_POST;
    }
    return $data ?: [];
}

/**
 * 获取登录用户（从token）
 */
function get_current_user() {
    $token = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    } elseif (isset($_GET['token'])) {
        $token = $_GET['token'];
    }
    if (!$token) return null;

    $row = DB::fetch('SELECT u.* FROM tokens t JOIN users u ON t.user_id = u.id WHERE t.token = ? AND t.expire_at > NOW()', [$token]);
    return $row ?: null;
}

/**
 * 要求登录
 */
function require_login() {
    $user = get_current_user();
    if (!$user) {
        fail('请先登录', 401);
    }
    return $user;
}

/**
 * 要求管理员
 */
function require_admin() {
    $user = require_login();
    if ($user['role'] !== 'admin') {
        fail('需要管理员权限', 403);
    }
    return $user;
}

/**
 * 生成唯一ID
 */
function gen_id($prefix = '') {
    return $prefix . uniqid() . bin2hex(random_bytes(4));
}

/**
 * 生成登录token
 */
function gen_token($userId) {
    $token = bin2hex(random_bytes(32));
    $expire = date('Y-m-d H:i:s', time() + TOKEN_EXPIRE);
    DB::insert('tokens', [
        'user_id' => $userId,
        'token' => $token,
        'expire_at' => $expire,
    ]);
    return $token;
}

/**
 * 清理用户的过期token
 */
function clean_expired_tokens($userId) {
    DB::query('DELETE FROM tokens WHERE user_id = ? AND expire_at < NOW()', [$userId]);
}

/**
 * 获取/设置系统配置
 */
function get_setting($key, $default = null) {
    $row = DB::fetch('SELECT svalue FROM settings WHERE skey = ?', [$key]);
    if ($row) {
        $val = json_decode($row['svalue'], true);
        return $val !== null ? $val : $row['svalue'];
    }
    return $default;
}

function set_setting($key, $value) {
    $json = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    DB::query('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = ?', [$key, $json, $json]);
}

/**
 * 记录积分变动
 */
function add_order($userId, $type, $points, $amount = 0, $title = '', $detail = null) {
    DB::insert('orders', [
        'user_id' => $userId,
        'type' => $type,
        'points' => $points,
        'amount' => $amount,
        'title' => $title,
        'detail' => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
    ]);
    // 更新用户积分
    if ($points != 0) {
        DB::query('UPDATE users SET points = points + ? WHERE id = ?', [$points, $userId]);
    }
}

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit;
}
