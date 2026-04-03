# 🎮 游戏反馈工单系统

一个专为游戏项目打造的**轻量级反馈工单系统**。

它能帮你轻松解决三件核心事情：

1. **玩家提交反馈** —— BUG、优化建议、功能需求，一键提交
2. **玩家自助查询** —— 输入工单号或关键词，随时查看处理进度
3. **管理员高效处理** —— 后台统一管理、指派给开发、记录处理结论

### 界面预览

![玩家提交页面](./images/1.png)
![工单查询页面](./images/2.png)
![管理后台](./images/3.png)

**简单、直观、上手快** —— 无论是小团队还是中型项目，都能快速部署使用。

## 适合谁用

如果你是以下情况之一，这个项目会特别适合你：

- **小中型游戏团队**：需要一个简单好用的反馈收集和处理系统
- **独立开发者或工作室**：不想依赖昂贵的SaaS服务，想完全掌控数据
- **PHP/MySQL 技术栈**：喜欢前后端分离、可自行部署的轻量方案

无需复杂配置，开箱即用。

## 技术栈

- **前端**：Vue 3 + TypeScript + Vite + Element Plus（现代、响应式界面）
- **后端**：纯PHP 7.2+（兼容性强，无框架依赖）
- **数据库**：MySQL 5.6+（自动建表，简单可靠）

## 🚀 5分钟本地跑起来

### 准备工作

1. **环境要求**：
   - Node.js 18+
   - PHP 7.2+
   - MySQL 5.6+

2. **创建数据库**（只需执行一次）：

```sql
CREATE DATABASE game_feedback DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

> 项目会自动创建表结构，但数据库需要你手动先建好。

### 启动后端服务

```bash
# 进入后端目录
cd server/public

# 启动PHP内置服务器
php -S 127.0.0.1:8000 router.php
```

**验证后端是否正常**：
- 访问 [http://127.0.0.1:8000/index.php](http://127.0.0.1:8000/index.php)
- 或健康检查： [http://127.0.0.1:8000/index.php?action=system.status](http://127.0.0.1:8000/index.php?action=system.status)

### 启动前端

```bash
# 新开终端
cd web
npm install          # 首次需要
npm run dev
```

前端默认运行在 ** http://127.0.0.1:5173 **，开发模式下会自动代理API请求到后端。

## 第一次打开页面会发生什么

第一次访问前端时，如果系统还未安装，会自动弹出**安装向导面板**。

按照提示填写数据库连接信息和管理员账号密码，点击安装按钮，系统会自动完成以下操作：

1. 初始化所有数据库表结构
2. 生成 `server/config/database.php` 配置文件
3. 记录当前的 schema 版本号

安装成功后即可立即使用！

## 日常开发命令

```bash
# 前端开发（推荐，带热更新）
cd web
npm run dev

# 前端打包生产版本
npm run build

# 启动后端本地服务器
cd server/public
php -S 127.0.0.1:8000 router.php
```

## 📦 升级说明（很重要！）

本项目采用**版本化数据库迁移**机制。

**核心逻辑**：
- 版本信息保存在 `server/config/database.php` 的 `schema_version`
- 系统启动时自动检测版本差异
- 仅在需要升级时执行迁移脚本
- 成功后自动更新版本号

**推荐升级流程**：
1. 备份数据库
2. 备份 `server/config/database.php`
3. 替换新代码
4. 访问前端页面触发迁移
5. 检查版本是否更新
6. 测试提交、查询和后台功能

## ❓ 常见问题

### 一直提示“系统未安装”？

常见原因：
- `server/config/database.php` 文件不存在或权限问题
- 数据库连接失败（主机、账号、密码错误）
- 没有提前创建数据库

### 接口返回 500 错误？

请查看后端日志，重点检查：
- 数据库用户是否拥有 `CREATE`、`ALTER`、`INDEX` 权限
- `schema_version` 是否与代码版本一致

### 附件上传/下载有问题？

确认以下几点：
- `storage/uploads/` 目录有写入权限
- PHP 配置中 `upload_max_filesize` 和 `post_max_size` 足够大
- 云存储配置（七牛等）填写正确

## 项目目录结构

```text
.
├── web/                    # 前端（Vue 3 + TypeScript）
├── server/                 # 后端核心
│   ├── public/             # 入口文件（index.php）
│   ├── src/                # 业务逻辑（API、Repository、工具类）
│   └── config/             # 配置文件
├── nginx.example.conf      # Nginx 部署示例配置
├── images/                 # 文档截图
└── README.md
```

## 生产环境部署建议

- **前端**：运行 `npm run build` 后部署静态文件
- **后端**：推荐使用 Nginx + PHP-FPM
- **安全**：限制 `server/config/` 目录的外部访问
- **重要**：**不要**将包含敏感信息的 `database.php` 提交到 Git 仓库

---

**项目维护中，欢迎提出 Issue 和 PR！**

祝使用愉快，收集到更多宝贵的玩家反馈 ✨