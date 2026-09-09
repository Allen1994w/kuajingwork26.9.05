<?php
/**
 * 订单/消费明细API
 * 路由：backend/api/orders.php?action=xxx
 * action: list / recharge / consume
 */
require_once __DIR__ . '/../db.php';

$action = $_GET['action'] ?? '';
$input = get_input();
$user = require_login();

switch ($action) {

    // 我的订单明细
    case 'list':
        $type = $input['type'] ?? 'all';
        $page = max(1, (int)($input['page'] ?? 1));
        $pageSize = min(200, max(10, (int)($input['pageSize'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $where = 'WHERE user_id = ?';
        $params = [$user['id']];
        if ($type !== 'all') {
            $where .= ' AND type = ?';
            $params[] = $type;
        }

        $total = DB::fetch("SELECT COUNT(*) as c FROM orders $where", $params)['c'];
        $rows = DB::fetchAll("SELECT * FROM orders $where ORDER BY created_at DESC LIMIT $offset,$pageSize", $params);

        foreach ($rows as &$r) {
            $r['points'] = (int)$r['points'];
            $r['amount'] = (float)$r['amount'];
        }

        ok(['total' => (int)$total, 'page' => $page, 'pageSize' => $pageSize, 'list' => $rows]);
        break;

    // 充值（模拟，实际应对接支付）
    case 'recharge':
        $planId = (int)($input['plan_id'] ?? 0);
        $plans = get_setting('plans', []);
        $plan = null;
        foreach ($plans as $p) {
            if ((int)$p['id'] === $planId) {
                $plan = $p;
                break;
            }
        }
        if (!$plan) fail('套餐不存在');

        $points = (int)$plan['points'] + (int)($plan['bonus'] ?? 0);
        $amount = (float)$plan['price'];

        add_order($user['id'], 'recharge', $points, $amount, '充值' . $plan['name'] . '，获得' . $points . '积分', [
            'plan_id' => $planId,
            'plan_name' => $plan['name'],
        ]);

        $updated = DB::fetch('SELECT points FROM users WHERE id = ?', [$user['id']]);
        ok(['points' => (int)$updated['points'], 'gained' => $points], '充值成功');
        break;

    // 消费扣积分
    case 'consume':
        $points = max(0, (int)($input['points'] ?? 0));
        $type = $input['type'] ?? 'image';
        $title = $input['title'] ?? '消费';
        $meta = [
            'gen_type' => $type,
            'count' => $input['count'] ?? 0,
            'duration' => $input['duration'] ?? 0,
            'prompt' => $input['prompt'] ?? '',
            'pack' => $input['pack'] ?? '',
            'mode' => $input['mode'] ?? '',
            'cost' => $points,
        ];

        // 管理员免积分
        if ($user['role'] === 'admin') {
            add_order($user['id'], 'consume', 0, 0, $title, $meta);
            ok(['points' => (int)$user['points'], 'cost' => 0, 'free' => true], '管理员免积分');
            break;
        }

        if ((int)$user['points'] < $points) {
            fail('积分不足，需 ' . $points . ' 积分，当前 ' . $user['points'] . ' 积分');
        }

        DB::execute('UPDATE users SET points = points - ? WHERE id = ?', [$points, $user['id']]);
        add_order($user['id'], 'consume', $points, 0, $title, $meta);

        $updated = DB::fetch('SELECT points FROM users WHERE id = ?', [$user['id']]);
        ok(['points' => (int)$updated['points'], 'cost' => $points], '扣除 ' . $points . ' 积分');
        break;

    default:
        fail('未知操作：' . $action);
}
