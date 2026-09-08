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

    default:
        fail('未知操作：' . $action);
}
