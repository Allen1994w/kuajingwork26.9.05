# 跨境电商工作台 - PHP后端

## 目录结构

```
backend/
├── config.php          # 配置文件（数据库、上传路径等）
├── db.php              # 数据库连接 + 通用工具函数
├── database.sql        # 数据库建表脚本
├── api/
│   ├── auth.php        # 用户认证（登录/退出/获取信息/改密码）
│   ├── users.php       # 用户管理（增删改查/充值/权限/明细）
│   ├── history.php     # 历史记录（列表/详情/新增/更新/删除）
│   ├── upload.php      # 文件上传（图片/视频）
│   ├── orders.php      # 订单明细（列表/充值）
│   └── settings.php    # 系统配置（模型/玩法/套餐/积分规则/兑换码）
└── uploads/
    ├── images/         # 上传的图片
    └── videos/         # 上传的视频
```

## 宝塔部署步骤

### 1. 创建数据库
- 宝塔面板 → 数据库 → 添加数据库
- 数据库名：`kuajing_work`
- 字符集：`utf8mb4`
- 记录用户名和密码

### 2. 导入数据库
- 宝塔面板 → 数据库 → 管理（phpMyAdmin）
- 选择 `kuajing_work` 数据库
- 导入 `backend/database.sql`

### 3. 修改配置
- 编辑 `backend/config.php`
- 修改 `DB_USER` 和 `DB_PASS` 为你的数据库账号密码
- 如数据库不在本机，修改 `DB_HOST`

### 4. 上传文件
- 将整个项目（index.html + backend目录）上传到网站根目录
- 确保 `backend/uploads` 目录有写入权限（755）

### 5. 配置网站
- 宝塔面板 → 网站 → 添加站点
- 域名指向项目根目录
- PHP版本：7.4+（推荐8.0+）
- 开启：fileinfo、curl、pdo_mysql扩展

### 6. 测试
- 访问 `https://你的域名/backend/api/auth.php?action=me`
- 返回 `{"ok":false,"error":"请先登录"}` 表示部署成功

## 默认账号

| 角色 | 邮箱 | 密码 |
|------|------|------|
| 管理员 | owner@crossborder.com | admin123 |
| 会员 | kevin@crossborder.com | kj123456 |

登录后请及时修改密码。

## API说明

### 认证
所有需要登录的接口，请求头需带：
```
Authorization: Bearer <token>
```

### 接口列表

| 接口 | 方法 | 说明 | 权限 |
|------|------|------|------|
| `api/auth.php?action=login` | POST | 登录 | 公开 |
| `api/auth.php?action=logout` | POST | 退出 | 登录 |
| `api/auth.php?action=me` | GET | 获取当前用户 | 登录 |
| `api/auth.php?action=update_password` | POST | 修改密码 | 登录 |
| `api/users.php?action=list` | POST | 用户列表 | 管理员 |
| `api/users.php?action=create` | POST | 新增用户 | 管理员 |
| `api/users.php?action=update` | POST | 更新用户 | 管理员 |
| `api/users.php?action=delete` | POST | 删除用户 | 管理员 |
| `api/users.php?action=recharge` | POST | 充值积分 | 管理员 |
| `api/users.php?action=permissions` | POST | 设置权限 | 管理员 |
| `api/users.php?action=orders` | POST | 用户明细 | 管理员 |
| `api/history.php?action=list` | POST | 历史记录列表 | 登录 |
| `api/history.php?action=detail` | POST | 记录详情 | 登录 |
| `api/history.php?action=create` | POST | 新增记录 | 登录 |
| `api/history.php?action=update` | POST | 更新记录 | 登录 |
| `api/history.php?action=delete` | POST | 删除记录 | 登录 |
| `api/upload.php?type=image` | POST | 上传图片 | 登录 |
| `api/upload.php?type=video` | POST | 上传视频 | 登录 |
| `api/orders.php?action=list` | POST | 我的明细 | 登录 |
| `api/orders.php?action=recharge` | POST | 充值 | 登录 |
| `api/settings.php?action=get` | GET | 获取配置 | 公开 |
| `api/settings.php?action=models` | POST | 保存模型 | 管理员 |
| `api/settings.php?action=pack_toggle` | POST | 保存玩法开关 | 管理员 |
| `api/settings.php?action=plans` | POST | 保存套餐 | 管理员 |
| `api/settings.php?action=point_rules` | POST | 保存积分规则 | 管理员 |
| `api/settings.php?action=sys_cfg` | POST | 保存系统配置 | 管理员 |
| `api/settings.php?action=codes` | GET | 兑换码列表 | 管理员 |
| `api/settings.php?action=create_code` | POST | 创建兑换码 | 管理员 |
| `api/settings.php?action=delete_code` | POST | 删除兑换码 | 管理员 |
| `api/settings.php?action=redeem` | POST | 兑换码兑换 | 登录 |

### 请求格式
- POST请求：`Content-Type: application/json`，body为JSON
- 文件上传：`Content-Type: multipart/form-data`，field名为`file`

### 响应格式
```json
{
  "ok": true,
  "msg": "success",
  "data": { ... }
}
```

错误响应：
```json
{
  "ok": false,
  "error": "错误信息"
}
```

## 注意事项

1. **安全**：部署后请修改 `config.php` 中的数据库密码，并确保 `config.php` 不可通过URL直接访问（可在宝塔设置禁止访问）
2. **HTTPS**：建议开启HTTPS，保护token和API密钥传输
3. **备份**：定期备份数据库和 `uploads` 目录
4. **本地代理**：Sorftime和新浪汇率仍需要 `local_proxy.py`，可在服务器上运行
