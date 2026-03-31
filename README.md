# game-feedback

这是一个给游戏项目用的反馈工单系统。

它解决三件事：

1. 玩家提交反馈（BUG、建议、优化等）
2. 玩家按工单号/关键词查询进度
3. 管理员在后台处理、指派、记录结论

页面截图：

![玩家端](./images/1.png)
![查询页](./images/2.png)
![管理端](./images/3.png)

## 适合谁用

- 小中型游戏团队，需要一个能快速落地的反馈系统
- 希望前后端分离、可自行部署、不依赖第三方 SaaS
- 运维环境是常见的 PHP + MySQL

## 技术栈

- 前端：Vue 3 + TypeScript + Vite + Element Plus
- 后端：PHP 7.2+
- 数据库：MySQL 5.6+

## 5 分钟本地跑起来

### 1. 准备环境

- Node.js 18+
- PHP 7.2+
- MySQL 5.6+

### 2. 先建一个空数据库

```sql
CREATE DATABASE game_feedback DEFAULT CHARACTER SET utf8mb4;
```

注意：项目会自动建表，但不会自动创建数据库本身。

### 3. 启动后端

```bash
cd server/public
php -S 127.0.0.1:8000 router.php
```

可用性检查：

- 后端入口：<http://127.0.0.1:8000/index.php>
- 健康检查：<http://127.0.0.1:8000/index.php?s=system/Status/health>

### 4. 启动前端

```bash
cd web
npm install
npm run dev
```

默认地址：<http://127.0.0.1:5173>

开发模式下，前端 `/api` 会代理到 `http://127.0.0.1:8000/index.php`。

## 第一次打开页面会发生什么

如果系统还没安装，页面会出现安装面板。

安装成功后会自动完成：

1. 初始化数据库结构
2. 生成 `server/config/database.php`
3. 写入 `schema_version`

## 日常开发最常用命令

```bash
# 前端开发
cd web
npm run dev

# 前端打包
npm run build

# 后端本地服务
cd ../server/public
php -S 127.0.0.1:8000 router.php
```

## 升级说明（务必看）

本项目是“版本化迁移”，不是“每次请求都扫表结构”。

核心逻辑：

- 当前版本记录在 `server/config/database.php` 的 `schema_version`
- 程序启动后对比版本
- 仅在版本落后时执行迁移
- 迁移成功后回写版本号

推荐升级步骤：

1. 备份数据库
2. 备份 `server/config/database.php`
3. 部署新代码
4. 触发一次请求（打开前端或访问 health）
5. 确认 `schema_version` 已更新
6. 回归验证提交、查询、后台处理流程

## 常见问题

### 一直提示“系统未安装”

通常是这三类问题：

1. `server/config/database.php` 不存在或不可读
2. 数据库连不上
3. 只建了代码，没提前创建数据库

### 接口返回 500

优先检查：

1. 后端日志里的真实异常
2. 数据库用户是否有 `CREATE` / `ALTER` / `INDEX` 权限
3. `schema_version` 与当前代码是否匹配

### 上传/附件异常

请确认：

1. 上传目录有写权限
2. PHP 上传大小限制满足需求
3. 相关云存储配置（如有）填写正确

## 目录一览

```text
.
├── web/                    # 前端工程
├── server/                 # 后端工程
│   ├── public/             # HTTP 入口
│   ├── src/                # 业务代码
│   └── config/             # 应用配置
├── nginx.example.conf      # Nginx 配置参考
└── README.md
```

## 部署建议

- 前端：先 `npm run build`，部署静态资源
- 后端：建议 Nginx/Apache + PHP-FPM
- 配置文件：限制 `server/config` 目录访问权限
- 安全：不要把 `server/config/database.php` 提交到公开仓库