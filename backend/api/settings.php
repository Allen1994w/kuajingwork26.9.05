<?php
/**
 * 系统配置API
 * 路由：backend/api/settings.php?action=xxx
 * action: get / set / models / pack_toggle / plans / point_rules / sys_cfg / codes
 */
require_once __DIR__ . '/../db.php';

$action = $_GET['action'] ?? '';
$input = get_input();

switch ($action) {

    // 获取所有配置（前台用，不需要管理员）
    case 'get':
        $user = get_current_user();
        $isAdmin = $user && $user['role'] === 'admin';

        $data = [
            'models' => get_setting('models', new stdClass()),
            'pack_toggle' => get_setting('pack_toggle', new stdClass()),
            'plans' => get_setting('plans', []),
            'point_rules' => get_setting('point_rules', new stdClass()),
            'sys_cfg' => get_setting('sys_cfg', new stdClass()),
        ];

        // 非管理员不返回模型API密钥
        if (!$isAdmin && isset($data['models'])) {
            foreach ($data['models'] as &$m) {
                if (isset($m['apiKey'])) $m['apiKey'] = '';
            }
        }

        ok($data);
        break;

    // 保存模型设置（管理员）
    case 'models':
        require_admin();
        $models = $input['models'] ?? null;
        if ($models === null) fail('模型配置不能为空');
        set_setting('models', $models);
        ok(null, '模型设置已保存');
        break;

    // 保存玩法开关（管理员）
    case 'pack_toggle':
        require_admin();
        $packToggle = $input['pack_toggle'] ?? null;
        if ($packToggle === null) fail('玩法开关不能为空');
        set_setting('pack_toggle', $packToggle);
        ok(null, '玩法配置已保存');
        break;

    // 保存套餐设置（管理员）
    case 'plans':
        require_admin();
        $plans = $input['plans'] ?? null;
        if ($plans === null) fail('套餐配置不能为空');
        set_setting('plans', $plans);
        ok(null, '套餐设置已保存');
        break;

    // 保存积分规则（管理员）
    case 'point_rules':
        require_admin();
        $rules = $input['point_rules'] ?? null;
        if ($rules === null) fail('积分规则不能为空');
        set_setting('point_rules', $rules);
        ok(null, '积分规则已保存');
        break;

    // 保存系统配置（联系方式等，管理员）
    case 'sys_cfg':
        require_admin();
        $sysCfg = $input['sys_cfg'] ?? null;
        if ($sysCfg === null) fail('系统配置不能为空');
        set_setting('sys_cfg', $sysCfg);
        ok(null, '系统配置已保存');
        break;

    // 兑换码列表（管理员）
    case 'codes':
        require_admin();
        $rows = DB::fetchAll('SELECT * FROM codes ORDER BY created_at DESC LIMIT 200');
        foreach ($rows as &$r) {
            $r['points'] = (int)$r['points'];
            $r['used'] = (int)$r['used'];
        }
        ok(['list' => $rows]);
        break;

    // 创建兑换码（管理员）
    case 'create_code':
        require_admin();
        $code = strtoupper(trim($input['code'] ?? ''));
        $points = (int)($input['points'] ?? 0);
        if (!$code) fail('兑换码不能为空');
        if ($points <= 0) fail('积分必须大于0');

        $exist = DB::fetch('SELECT id FROM codes WHERE code = ?', [$code]);
        if ($exist) fail('兑换码已存在');

        DB::insert('codes', ['code' => $code, 'points' => $points]);
        ok(null, '兑换码创建成功');
        break;

    // 删除兑换码（管理员）
    case 'delete_code':
        require_admin();
        $id = (int)($input['id'] ?? 0);
        if (!$id) fail('兑换码ID不能为空');
        DB::delete('codes', 'id = ?', [$id]);
        ok(null, '兑换码已删除');
        break;

    // 兑换码兑换（用户）
    case 'redeem':
        $user = require_login();
        $code = strtoupper(trim($input['code'] ?? ''));
        if (!$code) fail('兑换码不能为空');

        $row = DB::fetch('SELECT * FROM codes WHERE code = ?', [$code]);
        if (!$row) fail('兑换码不存在');
        if ($row['used']) fail('兑换码已被使用');

        DB::update('codes', [
            'used' => 1,
            'used_by' => $user['id'],
            'used_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $row['id']]);

        add_order($user['id'], 'exchange', (int)$row['points'], 0, '兑换码兑换' . $row['points'] . '积分', ['code' => $code]);

        ok(['points' => (int)$row['points']], '兑换成功，获得' . $row['points'] . '积分');
        break;

    default:
        fail('未知操作：' . $action);
}
