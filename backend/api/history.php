<?php
/**
 * 历史记录API
 * 路由：backend/api/history.php?action=xxx
 * action: list / detail / create / update / delete / set_ref
 */
require_once __DIR__ . '/../db.php';

$action = $_GET['action'] ?? '';
$input = get_input();
$user = require_login();

switch ($action) {

    // 历史记录列表（前台只看自己的，后台管理员可看全部）
    case 'list':
        $page = max(1, (int)($input['page'] ?? 1));
        $pageSize = min(200, max(10, (int)($input['pageSize'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        $type = $input['type'] ?? 'all';
        $keyword = $input['keyword'] ?? '';
        $allUsers = ($user['role'] === 'admin') && !empty($input['all_users']);

        $where = [];
        $params = [];
        if (!$allUsers) {
            $where[] = 'user_id = ?';
            $params[] = $user['id'];
        }
        if ($type !== 'all') {
            $where[] = 'type = ?';
            $params[] = $type;
        }
        if ($keyword) {
            $where[] = '(title LIKE ? OR pack LIKE ? OR mode LIKE ?)';
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = DB::fetch("SELECT COUNT(*) as c FROM history $whereSql", $params)['c'];
        $rows = DB::fetchAll("SELECT * FROM history $whereSql ORDER BY created_at DESC LIMIT $offset,$pageSize", $params);

        foreach ($rows as &$r) {
            $r['images'] = $r['images'] ? json_decode($r['images'], true) : [];
            $r['count'] = (int)$r['count'];
            $r['cost_points'] = (int)$r['cost_points'];
        }

        ok(['total' => (int)$total, 'page' => $page, 'pageSize' => $pageSize, 'list' => $rows]);
        break;

    // 详情
    case 'detail':
        $id = $input['id'] ?? $_GET['id'] ?? '';
        if (!$id) fail('记录ID不能为空');

        $row = DB::fetch('SELECT * FROM history WHERE id = ?', [$id]);
        if (!$row) fail('记录不存在');
        if ($row['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
            fail('无权查看此记录', 403);
        }

        $row['images'] = $row['images'] ? json_decode($row['images'], true) : [];
        $row['count'] = (int)$row['count'];
        $row['cost_points'] = (int)$row['cost_points'];
        ok($row);
        break;

    // 新增记录
    case 'create':
        $id = $input['id'] ?? gen_id('h');
        $type = $input['type'] ?? 'image';
        $pack = $input['pack'] ?? '';
        $mode = $input['mode'] ?? '';
        $title = $input['title'] ?? '';
        $prompt = $input['prompt'] ?? '';
        $images = $input['images'] ?? [];
        $count = (int)($input['count'] ?? 1);
        $size = $input['size'] ?? null;
        $platform = $input['platform'] ?? null;
        $language = $input['language'] ?? null;
        $duration = $input['duration'] ?? null;
        $status = $input['status'] ?? '生成中';
        $costPoints = (int)($input['cost_points'] ?? 0);

        DB::insert('history', [
            'id' => $id,
            'user_id' => $user['id'],
            'type' => $type,
            'pack' => $pack,
            'mode' => $mode,
            'title' => $title,
            'prompt' => $prompt,
            'images' => json_encode($images, JSON_UNESCAPED_UNICODE),
            'count' => $count,
            'size' => $size,
            'platform' => $platform,
            'language' => $language,
            'duration' => $duration,
            'status' => $status,
            'cost_points' => $costPoints,
        ]);

        // 扣积分（非管理员）
        if ($user['role'] !== 'admin' && $costPoints > 0) {
            if ($user['points'] < $costPoints) {
                fail('积分不足，需要' . $costPoints . '积分');
            }
            add_order($user['id'], 'consume', -$costPoints, 0, $pack . ' · ' . $mode . ' · ' . $title, [
                'type' => $type,
                'count' => $count,
                'history_id' => $id,
            ]);
        } elseif ($user['role'] === 'admin') {
            add_order($user['id'], 'consume', 0, 0, $pack . ' · ' . $mode . ' · ' . $title . '（管理员免积分）', [
                'type' => $type,
                'count' => $count,
                'history_id' => $id,
            ]);
        }

        ok(['id' => $id], '记录创建成功');
        break;

    // 更新记录（生成完成后更新图片和状态）
    case 'update':
        $id = $input['id'] ?? '';
        if (!$id) fail('记录ID不能为空');

        $row = DB::fetch('SELECT * FROM history WHERE id = ?', [$id]);
        if (!$row) fail('记录不存在');
        if ($row['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
            fail('无权修改此记录', 403);
        }

        $update = [];
        if (isset($input['images'])) $update['images'] = json_encode($input['images'], JSON_UNESCAPED_UNICODE);
        if (isset($input['status'])) $update['status'] = $input['status'];
        if (isset($input['count'])) $update['count'] = (int)$input['count'];
        if (isset($input['prompt'])) $update['prompt'] = $input['prompt'];

        if ($update) {
            DB::update('history', $update, 'id = :id', ['id' => $id]);
        }
        ok(null, '记录更新成功');
        break;

    // 删除记录
    case 'delete':
        $id = $input['id'] ?? '';
        if (!$id) fail('记录ID不能为空');

        $row = DB::fetch('SELECT * FROM history WHERE id = ?', [$id]);
        if (!$row) fail('记录不存在');
        if ($row['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
            fail('无权删除此记录', 403);
        }

        DB::delete('history', 'id = ?', [$id]);
        ok(null, '记录已删除');
        break;

    default:
        fail('未知操作：' . $action);
}
