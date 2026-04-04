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

- **前端**：Vue 3 + TypeScript + Vite + Naive UI（现代化 UI 组件，响应式设计）
- **后端**：纯PHP 7.2+（兼容性强，无框架依赖）
- **数据库**：MySQL 5.6+（自动建表，简单可靠）

## 🚀 5分钟本地跑起来

### Docker 一键运行

如果你想把前后端和 MySQL 一起跑起来，可以直接用 Docker Compose：

```bash
# 可选：先复制默认环境变量配置
cp .env.example .env

# 启动（支持自定义 .env 文件覆盖默认环境变量）
docker compose up -d --build
```

启动后默认访问：
- 前端：http://127.0.0.1:8001
- 接口健康检查：http://127.0.0.1:8001/api?s=system/Status/health

### Docker 环境变量

默认提供了一个示例文件：[.env.example](.env.example)

可配置项：

- `APP_PORT`：本机映射端口，默认 `8001`
- `MYSQL_DATABASE`：默认 `game_feedback`
- `MYSQL_USER`：默认 `game_feedback`
- `MYSQL_PASSWORD`：默认 `game_feedback`
- `MYSQL_ROOT_PASSWORD`：默认 `root`
- `APP_DB_HOST` / `APP_DB_PORT` / `APP_DB_DATABASE` / `APP_DB_USERNAME` / `APP_DB_PASSWORD`：可选覆盖已安装系统实际使用的数据库连接参数
- `NGINX_CLIENT_MAX_BODY_SIZE`：Nginx 请求体大小限制，默认 `20m`
- `PHP_POST_MAX_SIZE`：PHP `post_max_size`，默认 `20M`
- `PHP_UPLOAD_MAX_FILESIZE`：PHP `upload_max_filesize`，默认 `20M`
- `PHP_MAX_FILE_UPLOADS`：PHP `max_file_uploads`，默认 `20`
- `APP_CORS_ALLOWED_ORIGINS`：逗号分隔的跨域白名单，如 `https://a.com,https://b.com`
- `APP_ALLOW_LOCALHOST_CORS`：是否允许 localhost 跨域，支持 `true/false`
- `APP_TIMEZONE`：运行时时区覆盖
- `APP_UPLOAD_MODE`：附件模式，支持 `off/local/qiniu`
- `APP_UPLOAD_MAX_BYTES`：业务层附件大小限制，单位字节
- `APP_ATTACHMENT_CLEANUP_ENABLED`：是否启用附件自动/手动清理，支持 `true/false`
- `APP_ATTACHMENT_CLEANUP_RETENTION_DAYS`：已解决/已关闭工单附件的保留天数（天）
- `APP_ATTACHMENT_CLEANUP_INTERVAL_SECONDS`：自动清理检查间隔（秒）
- `APP_ATTACHMENT_CLEANUP_BATCH_LIMIT`：单次自动清理上限（条）
- `APP_QINIU_ACCESS_KEY` / `APP_QINIU_SECRET_KEY` / `APP_QINIU_BUCKET` / `APP_QINIU_DOMAIN`
- `APP_QINIU_DOWNLOAD_DOMAIN`：可选下载域名覆盖
- `APP_QINIU_DIRECT_ACCESS`：是否直连七牛，支持 `true/false`
- `APP_QINIU_UPLOAD_HOST`：自定义上传节点，支持逗号分隔
- `APP_QINIU_CONNECT_TIMEOUT` / `APP_QINIU_UPLOAD_TIMEOUT`：上传超时，单位秒
- `APP_CURL_VERIFY_SSL` / `APP_CURL_USE_NATIVE_CA`：cURL SSL 相关开关，支持 `true/false`
- `APP_CURL_CA_FILE` / `APP_CURL_CA_PATH`：自定义 CA 文件或目录

如果你想修改端口、数据库账号、上传限制或运行时业务配置，可以先复制后再启动：

```bash
cp .env.example .env
docker compose up -d --build
```

### 首次安装（Docker）

第一次启动时，打开首页会进入安装向导。

数据库连接请填写 Compose 内置的 MySQL 服务信息：

- Host：优先使用 `.env` 里的 `APP_DB_HOST`；未设置时填写 `mysql`
- Port：优先使用 `.env` 里的 `APP_DB_PORT`；未设置时填写 `3306`
- Database：优先使用 `.env` 里的 `APP_DB_DATABASE`；未设置时填写 `.env` 中的 `MYSQL_DATABASE`
- Username：优先使用 `.env` 里的 `APP_DB_USERNAME`；未设置时填写 `.env` 中的 `MYSQL_USER`
- Password：优先使用 `.env` 里的 `APP_DB_PASSWORD`；未设置时填写 `.env` 中的 `MYSQL_PASSWORD`

安装完成后，系统会自动写入运行时数据库配置，并保存在 Docker 卷中；之后重启容器不会丢失安装状态。

说明：`app.php` / `database.php` 仍然是默认值与持久化来源；`.env` 里的 `APP_*` 变量只做运行时覆盖。删除这些环境变量后，系统会自动回退到文件中的配置值。即使设置了环境变量，没有 `database.php` 也仍然视为“未安装”。数据库连接参数也支持通过 `APP_DB_*` 可选覆盖，但这同样只影响运行时，不会改写 `database.php`。

常用命令：

```bash
# 启动
docker compose up -d --build

# 查看日志
docker compose logs -f

# 停止（保留数据库和运行数据）
docker compose down

# 停止并清空 MySQL / 安装状态 / 上传文件等所有卷数据
docker compose down -v
```

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
- 或健康检查： [http://127.0.0.1:8000/api?s=system/Status/health](http://127.0.0.1:8000/api?s=system/Status/health)

### 启动前端

```bash
# 新开终端
cd web
npm install          # 首次需要
npm run dev
```

前端默认运行在 http://127.0.0.1:5173 ，开发模式下会自动代理API请求到后端。

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

### 数据库兼容说明

- 系统安装和启动自动迁移前，会先检查数据库服务端版本。
- 当前最低支持：**MySQL 5.6+**。
- `schema_version` 只表示表结构版本，不表示数据库引擎能力；即使 `schema_version` 正常，数据库版本过低也会被拦截。
- `server/migrate.sql` 已与当前代码内的最新 schema 版本保持一致，可作为手工升级脚本使用。


本项目采用**版本化数据库迁移**机制。

**核心逻辑**：
- 版本信息保存在 `server/config/database.php` 的 `schema_version`
- 系统启动时自动检测版本差异
- 仅在需要升级时执行迁移脚本
- 成功后自动更新版本号
- 系统启动时还会按节流策略顺带执行一次附件清理检查（默认 10 分钟最多触发一次，单次最多处理 100 条，可通过配置覆盖）

**推荐升级流程**：
1. 备份数据库
2. 备份 `server/config/database.php`
3. 替换新代码
4. 访问前端页面触发迁移
5. 检查版本是否更新
6. 测试提交、查询和后台功能

### 手动执行附件清理

如果待清理附件很多，除了系统自动检查外，也可以在后台手动执行：

1. 使用**超级管理员**登录后台
2. 打开“附件清理”页签
3. 先确认以下配置是否正确：
   - 是否启用清理
   - 附件保留时长（天）
   - 自动清理检查间隔（秒）
   - 单次自动清理上限（条）
4. 点击“立即执行清理”

说明：
- 支持清理本地存储和七牛云附件
- 只会清理已解决 / 已关闭且超过保留天数的附件
- 若已禁用附件清理，手动执行也会被阻止
- 若积压很多，可多次手动执行推进清理
- 自动清理的触发间隔和单次批量上限可通过环境变量或 `server/config/database.php` 配置

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