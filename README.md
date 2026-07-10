# 智能手机目录框架

Laravel + Vue 单仓项目，包含公开前台、`/api` 接口和 Blade 管理后台。

## 功能

**公开前台（Vue SPA）**

- 首页：轮播图与热门机型推荐
- 品牌目录：按品牌浏览机型列表
- 机型详情：完整规格参数页
- 搜索：型号、品牌、SoC/CPU/GPU、卖点全文检索，自动扩展品牌与芯片别名
- 明暗模式与主题色切换（纯前端，存浏览器本地）

**管理后台（Blade，需登录）**

- 产品管理：机型增删改查与批量导入
- 首页运营：热门机型推荐与轮播图管理，支持上移/下移排序
- 账号体系：注册、登录、密码重置、邮箱验证与个人资料维护

**开放接口（`/api`）**

- 品牌目录、机型列表与详情、搜索、首页轮播与推荐
- 统一 `fields` 字段裁剪、品牌与旧别名兼容、聚合式计数

## 环境要求

- PHP `>=8.4.1 <9.0`
- Composer `>=2.2`
- Node.js `^22.18.0 || >=24.11.0`
- npm
- SQLite、MySQL 或其他 Laravel 支持的数据库

## 快速开始

```bash
composer install && npm ci && npm --prefix frontend ci
cp .env.example .env
php artisan key:generate && php artisan migrate && php artisan storage:link
composer run dev
```

分离运行、构建产物路径等细节见[开发手册](docs/DEVELOPMENT.md#开发与构建)。

## 部署

配置生产 `.env`（至少 `APP_ENV=production`、`APP_DEBUG=false` 和真实数据库），然后：

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm --prefix frontend ci && npm run build
php artisan migrate --force && php artisan storage:link
```

- 生产环境 Web 根目录必须指向 `public/`。
- 缓存优化、可写目录与 Nginx 配置见[开发手册 · 部署](docs/DEVELOPMENT.md#部署)。

## 文档

- [开发手册](docs/DEVELOPMENT.md)：安装、开发、构建、系统规则与部署
- [API 手册](docs/api.md)：接口约定与清单
