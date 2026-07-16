# 智能手机目录框架

> 单仓（monorepo）手机目录应用：Vue SPA 公开前台 + 只读 `/api` 接口 + Blade 管理后台。

**技术栈**：Laravel 13 · PHP 8.5 · Vue 3 + vue-router 5 · Vite 8 · Bootstrap 5 · MySQL / SQLite

## 特性

| 模块 | 能力 |
| --- | --- |
| **前台**（Vue SPA） | 首页轮播与推荐、品牌目录、机型详情、关键词搜索、明暗主题切换 |
| **后台**（Blade，需登录） | 机型增删改查与批量导入、首页运营（轮播 / 推荐）、用户与权限管理 |
| **接口**（`/api`，公开只读） | 品牌、机型列表 / 详情、搜索、首页数据；统一 `fields` 字段裁剪、别名兼容与限流 |
| **工程** | 四级角色权限、上传 / URL / 导入安全加固、DB 层分页与直查、CI + 供应链检查、多阶段 Docker |

## 环境要求

| 依赖 | 版本 |
| --- | --- |
| PHP | `^8.5`（`>=8.5 <9.0`） |
| Composer | `2.x`（建议使用当前稳定版） |
| Node.js | `^24.11.0` + npm 11 |
| 数据库 | SQLite、MySQL 或其他 Laravel 支持的数据库 |

## 快速开始

```bash
composer install && npm ci && npm --prefix frontend ci
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # 默认 SQLite：先建空库文件（或用 composer run setup 一键完成）
php artisan migrate && php artisan storage:link
composer run dev                 # 同时起 Laravel、后台与前台开发服务
```

## 部署

### Docker 部署（推荐）

只需安装 Docker Engine 与 Docker Compose v2。下面是从克隆仓库到健康检查的**完整首次部署命令**；先把第一行的地址改成你的域名或服务器地址，再整段复制执行：

```bash
APP_URL='http://YOUR_SERVER_IP:8080'
WEB_PORT=8080

git clone https://github.com/GeneralPeople1970/smartphone-catalog.git
cd smartphone-catalog
cp .env.docker.example .env

# 使用项目 runtime 镜像生成 APP_KEY 和两个随机数据库密码。
docker pull generalpeople/smartphone-catalog:runtime
APP_KEY="$(docker run --rm --entrypoint php generalpeople/smartphone-catalog:runtime -r 'echo "base64:".base64_encode(random_bytes(32));')"
DB_PASSWORD="$(docker run --rm --entrypoint php generalpeople/smartphone-catalog:runtime -r 'echo bin2hex(random_bytes(32));')"
DB_ROOT_PASSWORD="$(docker run --rm --entrypoint php generalpeople/smartphone-catalog:runtime -r 'echo bin2hex(random_bytes(32));')"

sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
sed -i "s|^APP_URL=.*|APP_URL=${APP_URL}|" .env
sed -i "s|^WEB_PORT=.*|WEB_PORT=${WEB_PORT}|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
sed -i "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}|" .env

# HTTPS 保持安全 Cookie；直接使用 HTTP/IP 访问时自动关闭该选项。
case "$APP_URL" in
  https://*) sed -i 's|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|' .env ;;
  *)         sed -i 's|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=false|' .env ;;
esac

# 拉取镜像、启动 MySQL、自动迁移并等待 /up 健康检查通过。
docker compose -f compose.deploy.yml up -d --pull always --wait
docker compose -f compose.deploy.yml ps
curl --fail --show-error "http://127.0.0.1:${WEB_PORT}/up"
```

以后更新只需在项目目录执行一条命令：

```bash
git pull --ff-only && docker compose -f compose.deploy.yml up -d --pull always --wait
```

数据库和上传文件保存在 Docker 命名卷中，更新或重启容器不会丢失。常用排障命令为 `docker compose -f compose.deploy.yml logs --no-color --tail=200`；镜像版本固定、回滚和反向代理配置详见[开发手册 · 容器化部署](docs/DEVELOPMENT.md#容器化部署docker)。

### 手动部署

不使用容器时，配置生产 `.env`，并将 Web 根目录指向 `public/`：

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm --prefix frontend ci && npm run build
php artisan migrate --force && php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

服务器要求、缓存头、Nginx 与 Docker 配置详见[开发手册 · 部署](docs/DEVELOPMENT.md#部署)。

## 文档

- **[开发手册](docs/DEVELOPMENT.md)** — 安装、开发、构建、系统规则、安全加固与部署
- **[API 手册](docs/api.md)** — 接口约定、字段裁剪与完整清单

## 许可证与商标

代码以 [MIT 许可证](LICENSE)开源。

本项目为**非官方**开源框架 / 演示，与任何手机厂商无隶属或背书关系：

- 品牌名称、型号、Logo 等均为各自所有者的商标，仅作**指示性识别**，不代表授权或合作。
- 仓库内品牌 Logo（`public/assets/brands/`）来自第三方，**分发权限需再分发者自行确认**；如无把握，请在部署前替换为自有占位图或删除（缺图时前台回退到 `/assets/phone-placeholder.svg`）。
- 手机参数为示例数据，不保证准确、完整或实时，请勿作为购买或商业决策依据。
