<?php
/**
 * 用户认证API
 * 路由：backend/api/auth.php?action=xxx
 * action: login / logout / register / me / update_password
 */
require_once __DIR__ . '/../db.php';

$action = $_GET['action'] ?? '';
$input = get_input();

switch ($action) {

    // 登录
    case 'login':
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        if (!$email || !$password) fail('邮箱和密码不能为空');

        $user = DB::fetch('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$user) fail('该邮箱未注册，请联系管理员开通账号');

        if (!password_verify($password, $user['password'])) {
            fail('密码错误，请重试');
        }

        // 检查账号有效期
        if ($user['expire_at'] && strtotime($user['expire_at']) < time()) {
            fail('账号已过期，请联系管理员续费');
        }

        clean_expired_tokens($user['id']);
        $token = gen_token($user['id']);

        ok([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role'],
                'points' => (int)$user['points'],
                'expire_at' => $user['expire_at'],
                'permissions' => $user['permissions'] ? json_decode($user['permissions'], true) : [],
            ]
        ], '登录成功');
        break;

    // 退出登录
    case 'logout':
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if ($token) {
            DB::query('DELETE FROM tokens WHERE token = ?', [$token]);
        }
        ok(null, '已退出登录');
        break;

    // 获取当前用户信息
    case 'me':
        $user = require_login();
        ok([
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
            'points' => (int)$user['points'],
            'expire_at' => $user['expire_at'],
            'permissions' => $user['permissions'] ? json_decode($user['permissions'], true) : [],
        ]);
        break;

    // 修改密码
    case 'update_password':
        $user = require_login();
        $oldPwd = $input['old_password'] ?? '';
        $newPwd = $input['new_password'] ?? '';
        if (!$oldPwd || !$newPwd) fail('旧密码和新密码不能为空');
        if (strlen($newPwd) < 6) fail('新密码至少6位');
        if (!password_verify($oldPwd, $user['password'])) fail('旧密码错误');

        $hash = password_hash($newPwd, PASSWORD_DEFAULT);
        DB::update('users', ['password' => $hash], 'id = :id', ['id' => $user['id']]);
        ok(null, '密码修改成功');
        break;

    default:
        fail('未知操作：' . $action);
}
