-- 跨境电商工作台 数据库建表脚本
-- 字符集：utf8mb4，排序：utf8mb4_unicode_ci

CREATE DATABASE IF NOT EXISTS `kuajing_work` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `kuajing_work`;

-- 1. 用户表
CREATE TABLE IF NOT EXISTS `users` (
  `id` VARCHAR(32) NOT NULL COMMENT '用户ID，如u1/u2',
  `email` VARCHAR(128) NOT NULL COMMENT '邮箱',
  `password` VARCHAR(255) NOT NULL COMMENT '密码（password_hash加密）',
  `name` VARCHAR(64) NOT NULL COMMENT '姓名',
  `role` VARCHAR(16) NOT NULL DEFAULT 'member' COMMENT '角色：admin/member',
  `points` INT NOT NULL DEFAULT 0 COMMENT '可用积分',
  `expire_at` DATETIME NULL COMMENT '账号有效期，NULL表示永久',
  `permissions` TEXT NULL COMMENT '玩法权限JSON，如["pack_wanneng","v_videopromo"]',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- 2. 订单/消费明细表
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(32) NOT NULL COMMENT '用户ID',
  `type` VARCHAR(16) NOT NULL COMMENT '类型：recharge充值/exchange兑换/consume消费',
  `points` INT NOT NULL DEFAULT 0 COMMENT '积分变动，正为增加，负为扣除',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '金额（元）',
  `title` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '明细标题/动作描述',
  `detail` TEXT NULL COMMENT '详细信息JSON',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单/消费明细表';

-- 3. 历史记录表
CREATE TABLE IF NOT EXISTS `history` (
  `id` VARCHAR(64) NOT NULL COMMENT '记录ID',
  `user_id` VARCHAR(32) NOT NULL COMMENT '用户ID',
  `type` VARCHAR(16) NOT NULL COMMENT '类型：image/video/ops',
  `pack` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '主玩法名称',
  `mode` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '细分玩法',
  `title` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '用户输入提示词',
  `prompt` TEXT NULL COMMENT '完整提示词',
  `images` TEXT NULL COMMENT '图片/视频URL数组JSON',
  `count` INT NOT NULL DEFAULT 1 COMMENT '生成数量',
  `size` VARCHAR(32) NULL COMMENT '尺寸',
  `platform` VARCHAR(32) NULL COMMENT '目标平台',
  `language` VARCHAR(32) NULL COMMENT '语言',
  `duration` INT NULL COMMENT '视频时长（秒）',
  `status` VARCHAR(16) NOT NULL DEFAULT '成功' COMMENT '状态：成功/失败/生成中/已取消/部分成功',
  `cost_points` INT NOT NULL DEFAULT 0 COMMENT '消耗积分',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='历史记录表';

-- 4. 系统配置表
CREATE TABLE IF NOT EXISTS `settings` (
  `skey` VARCHAR(64) NOT NULL COMMENT '配置键',
  `svalue` TEXT NULL COMMENT '配置值（JSON）',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- 5. 兑换码表
CREATE TABLE IF NOT EXISTS `codes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(32) NOT NULL COMMENT '兑换码',
  `points` INT NOT NULL DEFAULT 0 COMMENT '兑换积分',
  `used` TINYINT NOT NULL DEFAULT 0 COMMENT '是否已使用：0否1是',
  `used_by` VARCHAR(32) NULL COMMENT '使用用户ID',
  `used_at` DATETIME NULL COMMENT '使用时间',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='兑换码表';

-- 6. 登录token表（可选，用于多设备登录）
CREATE TABLE IF NOT EXISTS `tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(32) NOT NULL COMMENT '用户ID',
  `token` VARCHAR(128) NOT NULL COMMENT '登录token',
  `expire_at` DATETIME NOT NULL COMMENT '过期时间',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='登录token表';

-- 初始化默认管理员（密码：admin123）
INSERT INTO `users` (`id`, `email`, `password`, `name`, `role`, `points`, `expire_at`, `permissions`) VALUES
('u1', 'owner@crossborder.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '跨境运营者', 'admin', 99999, NULL, '[]')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 初始化默认会员（密码：kj123456）
INSERT INTO `users` (`id`, `email`, `password`, `name`, `role`, `points`, `expire_at`, `permissions`) VALUES
('u3', 'kevin@crossborder.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kevin', 'member', 100, NULL, '[]')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 初始化系统配置
INSERT INTO `settings` (`skey`, `svalue`) VALUES
('models', '{"text":{"endpoint":"","model":"","apiKey":""},"image":{"endpoint":"","apiKey":""},"video":{"endpoint":"","model":"","apiKey":""},"sorftime":{"endpoint":"","apiKey":"","status":"off"}}'),
('pack_toggle', '{"image":{},"video":{},"ops":{}}'),
('plans', '[{"id":1,"name":"20元套餐","price":20,"points":100,"bonus":0},{"id":2,"name":"50元套餐","price":50,"points":250,"bonus":30},{"id":3,"name":"100元套餐","price":100,"points":500,"bonus":50}]'),
('point_rules', '{"pointRate":0.2,"imgCost":1,"videoCost":2,"opsCosts":{"竞品分析":5,"买家心声":5,"选品自动化":10,"Listing优化":10}}'),
('sys_cfg', '{"loginContact":{"wechat":"","qrCode":""},"loginContactSub":"添加时，请备注「开通账号」","contact":{"wechat":"","qrCode":""},"contactSub":"7年海外仓经验，欢迎添加备用"}')
ON DUPLICATE KEY UPDATE `svalue`=VALUES(`svalue`);
