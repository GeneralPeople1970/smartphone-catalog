# 智能手机目录框架

Laravel + Vue 单仓项目，包含公开前台、`/api` 接口和 Blade 管理后台。

## 功能

- **前台（Vue SPA）**：首页轮播与推荐、品牌目录、机型详情、全文搜索、明暗主题切换
- **后台（Blade，需登录）**：机型增删改查与批量导入、首页运营（轮播与推荐）、账号管理
- **接口（`/api`）**：品牌、机型列表/详情、搜索、首页数据，统一 `fields` 裁剪与别名兼容

## 环境要求

- PHP `>=8.4.1 <9.0`、Composer `>=2.2`
- Node.js `^22.18.0 || >=24.11.0`、npm
- SQLite、MySQL 或其他 Laravel 支持的数据库

## 快速开始

```bash
composer install && npm ci && npm --prefix frontend ci
cp .env.example .env
php artisan key:generate && php artisan migrate && php artisan storage:link
composer run dev
```

## 部署

配置生产 `.env`，Web 根目录指向 `public/`：

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm --prefix frontend ci && npm run build
php artisan migrate --force && php artisan storage:link
```

缓存与 Nginx 配置见[开发手册](docs/DEVELOPMENT.md#部署)。

## 文档

- [开发手册](docs/DEVELOPMENT.md)：安装、开发、构建、系统规则与部署
- [API 手册](docs/api.md)：接口约定与清单
