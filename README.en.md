# 🎮 Game Feedback Ticket System

A **lightweight feedback ticket system** designed specifically for game projects.

It helps you easily solve three core things:

1. **Players submit feedback** — BUGs, optimization suggestions, feature requests, submit with one click
2. **Players self-service query** — Enter ticket ID or keywords to check processing progress anytime
3. **Admins efficient processing** — Backend unified management, assign to developers, record resolution conclusions

### Interface Preview

![Player Submit Page](./images/1.png)
![Ticket Query Page](./images/2.png)
![Admin Backend](./images/3.png)

**Simple, intuitive, quick to start** — Whether small team or medium project, quick to deploy and use.

## Suitable For

If you are in one of the following situations, this project is especially suitable:

- **Small and medium game teams**: Need a simple and easy-to-use feedback collection and processing system
- **Indie developers or studios**: Don't want to rely on expensive SaaS services, want full data control
- **PHP/MySQL tech stack**: Like frontend-backend separation, self-deployable lightweight solution

No complex configuration, ready to use out of the box.

## Tech Stack

- **Frontend**: Vue 3 + TypeScript + Vite + Naive UI (modern UI components, responsive design)
- **Backend**: Pure PHP 7.2+ (strong compatibility, no framework dependency)
- **Database**: MySQL 5.6+ (auto table creation, simple and reliable)

## 🚀 5 Minutes to Run Locally

### Docker One-Click Run

If you want to run frontend, backend, and MySQL together, use Docker Compose directly:

```bash
# Optional: Copy default environment config first
cp .env.example .env

# Start (supports custom .env to override default env vars)
docker compose up -d --build
```

After start, default access:
- Frontend: http://127.0.0.1:8001
- API health check: http://127.0.0.1:8001/api?s=system/Status/health

### Docker Environment Variables

Default example file provided: [.env.example](.env.example)

Configurable items:

- `APP_PORT`: Local mapped port, default `8001`
- `MYSQL_DATABASE`: Default `game_feedback`
- `MYSQL_USER`: Default `game_feedback`
- `MYSQL_PASSWORD`: Default `game_feedback`
- `MYSQL_ROOT_PASSWORD`: Default `root`
- `APP_DB_HOST` / `APP_DB_PORT` / `APP_DB_DATABASE` / `APP_DB_USERNAME` / `APP_DB_PASSWORD`: Optional override for actual DB connection params used by installed system
- `NGINX_CLIENT_MAX_BODY_SIZE`: Nginx request body size limit, default `20m`
- `PHP_POST_MAX_SIZE`: PHP `post_max_size`, default `20M`
- `PHP_UPLOAD_MAX_FILESIZE`: PHP `upload_max_filesize`, default `20M`
- `PHP_MAX_FILE_UPLOADS`: PHP `max_file_uploads`, default `20`
- `APP_CORS_ALLOWED_ORIGINS`: Comma-separated CORS whitelist, e.g. `https://a.com,https://b.com`
- `APP_ALLOW_LOCALHOST_CORS`: Allow localhost CORS, `true/false`
- `APP_TIMEZONE`: Runtime timezone override
- `APP_UPLOAD_MODE`: Attachment mode, `off/local/qiniu`
- `APP_UPLOAD_MAX_BYTES`: Business layer attachment size limit, bytes
- `APP_ATTACHMENT_CLEANUP_ENABLED`: Enable attachment auto/manual cleanup, `true/false`
- `APP_ATTACHMENT_CLEANUP_RETENTION_DAYS`: Retention days for resolved/closed ticket attachments (days)
- `APP_ATTACHMENT_CLEANUP_INTERVAL_SECONDS`: Auto cleanup check interval (seconds)
- `APP_ATTACHMENT_CLEANUP_BATCH_LIMIT`: Single auto cleanup limit (items)
- `APP_QINIU_ACCESS_KEY` / `APP_QINIU_SECRET_KEY` / `APP_QINIU_BUCKET` / `APP_QINIU_DOMAIN`
- `APP_QINIU_DOWNLOAD_DOMAIN`: Optional download domain override
- `APP_QINIU_DIRECT_ACCESS`: Direct Qiniu access, `true/false`
- `APP_QINIU_UPLOAD_HOST`: Custom upload node, comma-separated
- `APP_QINIU_CONNECT_TIMEOUT` / `APP_QINIU_UPLOAD_TIMEOUT`: Upload timeout, seconds
- `APP_CURL_VERIFY_SSL` / `APP_CURL_USE_NATIVE_CA`: cURL SSL switches, `true/false`
- `APP_CURL_CA_FILE` / `APP_CURL_CA_PATH`: Custom CA file or dir

If you want to modify port, DB account, upload limits or runtime business config, copy first then start:

```bash
cp .env.example .env
docker compose up -d --build
```

### First Install (Docker)

First start, open homepage enters install wizard.

DB connection fill Compose built-in MySQL info:

- Host: Prefer `.env` `APP_DB_HOST`; unset use `mysql`
- Port: Prefer `.env` `APP_DB_PORT`; unset `3306`
- Database: Prefer `.env` `APP_DB_DATABASE`; unset `.env` `MYSQL_DATABASE`
- Username: Prefer `.env` `APP_DB_USERNAME`; unset `.env` `MYSQL_USER`
- Password: Prefer `.env` `APP_DB_PASSWORD`; unset `.env` `MYSQL_PASSWORD`

After install, system auto writes runtime DB config, saves in Docker volume; restart won't lose state.

Note: `app.php` / `database.php` still default & persistent source; `.env` `APP_*` only runtime override. Delete vars fallback to file config. Even with env vars, no `database.php` still 'uninstalled'. DB params support `APP_DB_*` override, but only runtime, no rewrite `database.php`.

Common commands:

```bash
# Start
docker compose up -d --build

# View logs
docker compose logs -f

# Stop (keep DB & runtime data)
docker compose down

# Stop & clear all volumes (MySQL/install/uploads)
docker compose down -v
```

### Prep Work

1. **Env Req**:
   - Node.js 18+
   - PHP 7.2+
   - MySQL 5.6+

2. **Create DB** (once):

```sql
CREATE DATABASE game_feedback DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

> Project auto creates tables, but DB manual create first.

### Start Backend

```bash
# Enter backend dir
cd server/public

# Start PHP built-in server
php -S 127.0.0.1:8000 router.php
```

**Verify Backend**:
- Visit http://127.0.0.1:8000/index.php
- Or health: http://127.0.0.1:8000/api?s=system/Status/health

### Start Frontend

```bash
# New terminal
cd web
npm install          # First time
npm run dev
```

Frontend defaults http://127.0.0.1:5173, dev mode auto proxy API to backend.

## What Happens First Open

First frontend access, if not installed, auto popup **Install Wizard Panel**.

Fill DB connection & admin account/password, click install, auto completes:

1. Init all DB tables
2. Generate `server/config/database.php`
3. Record current schema version

Install success, use immediately!

## Daily Dev Commands

```bash
# Frontend dev (recommended, hot reload)
cd web
npm run dev

# Frontend build prod
npm run build

# Backend local server
cd server/public
php -S 127.0.0.1:8000 router.php
```

### DB Compatibility Note

- Install/start auto check DB server version.
- Min support: **MySQL 5.6+**.
- `schema_version` only table structure version, not DB engine; low version blocks even if schema ok.
- `server/migrate.sql` sync with latest schema, for manual upgrade.

This project uses **versioned DB migration**.

**Core Logic**:
- Version in `server/config/database.php` `schema_version`
- Startup auto detect diff
- Migrate only if needed
- Auto update version on success
- Startup also throttle attachment cleanup (default 10min max once, 100 batch, configurable)

**Recommended Upgrade**:
1. Backup DB
2. Backup `server/config/database.php`
3. Replace new code
4. Access frontend trigger migrate
5. Check version update
6. Test submit/query/admin

### Manual Attachment Cleanup

If many pending, besides auto, manual in admin:

1. Super admin login backend
2. Open "Attachment Cleanup" tab
3. Confirm configs:
   - Cleanup enabled
   - Retention days
   - Auto interval sec
   - Batch limit
4. Click "Cleanup Now"

Notes:
- Supports local & Qiniu
- Only resolved/closed over retention
- Disabled blocks manual
- Multiple manual for backlog
- Auto interval/batch configurable via env or `server/config/database.php`

## ❓ FAQ

### Always "System Not Installed"?

Common:
- `server/config/database.php` missing/perms
- DB connect fail (host/user/pass wrong)
- No pre-create DB

### API 500 Error?

Check backend logs:
- DB user has CREATE/ALTER/INDEX perms?
- `schema_version` match code?

### Attachment Upload/Download Issues?

Check:
- `storage/uploads/` writable
- PHP `upload_max_filesize` `post_max_size` large enough
- Cloud config (Qiniu) correct

## Project Dir Structure

```
.
├── web/                    # Frontend (Vue 3 + TS)
├── server/                 # Backend core
│   ├── public/             # Entry (index.php)
│   ├── src/                # Business (API, Repo, utils)
│   └── config/             # Configs
├── nginx.example.conf      # Nginx deploy example
├── images/                 # Doc screenshots
└── README.md
```

## Production Deploy Suggestions

- **Frontend**: `npm run build` deploy statics
- **Backend**: Recommend Nginx + PHP-FPM
- **Security**: Block external `server/config/`
- **Important**: **Don't** commit sensitive `database.php` to Git

---

**Project maintained, welcome Issues & PRs!**

Enjoy, collect valuable player feedback! ✨