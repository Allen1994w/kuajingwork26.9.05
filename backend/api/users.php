<?php
/**
 * 用户管理API（后台管理员用）
 * 路由：backend/api/users.php?action=xxx
 * action: list / create / update / delete / recharge / permissions / orders
 */
require_once __DIR__ . '/../db.php';

$action = $_GET['action'] ?? '';
$input = get_input();
$admin = require_admin();

switch ($action) {

    // 用户列表
    case 'list':
        $page = max(1, (int)($input['page'] ?? 1));
        $pageSize = min(200, max(10, (int)($input['pageSize'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        $keyword = $input['keyword'] ?? '';

        $where = '';
        $params = [];
        if ($keyword) {
            $where = 'WHERE email LIKE ? OR name LIKE ?';
            $params = ["%$keyword%", "%$keyword%"];
        }

        $total = DB::fetch("SELECT COUNT(*) as c FROM users $where", $params)['c'];
        $rows = DB::fetchAll("SELECT id,email,name,role,points,expire_at,permissions,created_at FROM users $where ORDER BY created_at DESC LIMIT $offset,$pageSize", $params);

        foreach ($rows as &$r) {
            $r['points'] = (int)$r['points'];
            $r['permissions'] = $r['permissions'] ? json_decode($r['permissions'], true) : [];
        }

        ok(['total' => (int)$total, 'page' => $page, 'pageSize' => $pageSize, 'list' => $rows]);
        break;

    // 新增用户
    case 'create':
        $email = trim($input['email'] ?? '');
        $name = trim($input['name'] ?? '');
        $password = $input['password'] ?? 'kj123456';
        $role = $input['role'] ?? 'member';
        $points = (int)($input['points'] ?? 0);
        $expire_at = $input['expire_at'] ?? null;
        $permissions = $input['permissions'] ?? [];

        if (!$email) fail('邮箱不能为空');
        if (!$name) fail('姓名不能为空');

        $exist = DB::fetch('SELECT id FROM users WHERE email = ?', [$email]);
        if ($exist) fail('该邮箱已注册');

        $id = 'u' . (DB::fetch('SELECT COUNT(*) as c FROM users')['c'] + 1);
        $hash = password_hash($password ?: 'kj123456', PASSWORD_DEFAULT);

        DB::insert('users', [
            'id' => $id,
            'email' => $email,
            'password' => $hash,
            'name' => $name,
            'role' => $role,
            'points' => $points,
            'expire_at' => $expire_at ?: null,
            'permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
        ]);

        if ($points > 0) {
            add_order($id, 'recharge', $points, 0, '管理员充值积分', ['admin' => $admin['name']]);
        }

        ok(['id' => $id], '用户创建成功');
        break;

    // 更新用户
    case 'update':
        $id = $input['id'] ?? '';
        if (!$id) fail('用户ID不能为空');

        $user = DB::fetch('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) fail('用户不存在');

        $update = [];
        if (isset($input['name'])) $update['name'] = $input['name'];
        if (isset($input['email'])) $update['email'] = $input['email'];
        if (isset($input['role'])) $update['role'] = $input['role'];
        if (isset($input['expire_at'])) $update['expire_at'] = $input['expire_at'] ?: null;
        if (isset($input['permissions'])) $update['permissions'] = json_encode($input['permissions'], JSON_UNESCAPED_UNICODE);
        if (!empty($input['password'])) $update['password'] = password_hash($input['password'], PASSWORD_DEFAULT);

        if ($update) {
            DB::update('users', $update, 'id = :id', ['id' => $id]);
        }
        ok(null, '用户更新成功');
        break;

    // 删除用户
    case 'delete':
        $id = $input['id'] ?? '';
        if (!$id) fail('用户ID不能为空');
        if ($id === $admin['id']) fail('不能删除自己');
        if ($id === 'u1') fail('不能删除超级管理员');

        DB::delete('users', 'id = ?', [$id]);
        DB::delete('tokens', 'user_id = ?', [$id]);
        ok(null, '用户已删除');
        break;

    // 充值积分
    case 'recharge':
        $id = $input['id'] ?? '';
        $points = (int)($input['points'] ?? 0);
        if (!$id) fail('用户ID不能为空');
        if ($points <= 0) fail('积分必须大于0');

        $user = DB::fetch('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) fail('用户不存在');

        add_order($id, 'recharge', $points, 0, '管理员充值' . $points . '积分', ['admin' => $admin['name']]);
        ok(['points' => $user['points'] + $points], '充值成功');
        break;

    // 设置玩法权限
    case 'permissions':
        $id = $input['id'] ?? '';
        $permissions = $input['permissions'] ?? [];
        if (!$id) fail('用户ID不能为空');

        DB::update('users', ['permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => $id]);
        ok(null, '权限设置成功');
        break;

    // 用户订单/消费明细
    case 'orders':
        $userId = $input['user_id'] ?? '';
        if (!$userId) fail('用户ID不能为空');

        $type = $input['type'] ?? 'all';
        $where = 'WHERE user_id = ?';
        $params = [$userId];
        if ($type !== 'all') {
            $where .= ' AND type = ?';
            $params[] = $type;
        }

        $rows = DB::fetchAll("SELECT * FROM orders $where ORDER BY created_at DESC LIMIT 500", $params);
        foreach ($rows as &$r) {
            $r['points'] = (int)$r['points'];
            $r['amount'] = (float)$r['amount'];
        }
        ok(['list' => $rows]);
        break;

    default:
        fail('未知操作：' . $action);
}
