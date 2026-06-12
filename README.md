# 智能手机目录框架

这是一个 Laravel + Vue 单仓项目，包含公开前台、Laravel API 和 Blade 管理后台。项目适合用来搭建产品目录、参数展示、搜索查询、首页推荐和后台内容维护系统。

## 功能概览

- 公开前台：Vue 3、Vue Router history 模式、Vite 构建。
- 后端接口：提供品牌、产品列表、搜索、详情、首页轮播图和首页推荐接口。
- 品牌目录：数据库和内部代码使用英文 canonical 品牌名，公开前台和展示型 API 字段使用中文展示名。
- 本地主题：前后台共用浏览器本地主题设置，支持浅色、深色、跟随系统和 5 套主色调。
- 管理后台：提供登录认证、产品维护、首页推荐维护、轮播图上传和排序；顶部用户名用于前后台快速切换。
- 文件上传：公开上传文件保存到 `storage/app/public/`，通过 `/storage/*` 访问。
- 缺图兜底：手机图片缺失或不可用时使用本地 `public/assets/phone-placeholder.svg`。
- 独立构建：后台资源输出到 `public/build/`，公开前台输出到 `public/frontend/`。
- CI：GitHub Actions 会执行 Composer 校验、审计、Pint、PHPUnit、npm 检查和构建。

## 环境要求

- PHP `>=8.4.1 <9.0`
- Composer `>=2.2`
- Node.js `^22.18.0 || >=24.11.0`
- npm
- SQLite、MySQL 或其他 Laravel 支持的数据库

生产环境的 Web 根目录必须指向 `public/`。

## 目录说明

| 路径 | 说明 |
| --- | --- |
| `app/` | Laravel 控制器、模型、命令和业务代码 |
| `routes/` | Web、API、认证和控制台路由 |
| `resources/` | Blade 管理后台视图、样式和脚本 |
| `frontend/` | Vue 公开前台源码、依赖和 Vite 配置 |
| `public/assets/` | 公开静态资源，例如站点 Logo 和品牌 Logo |
| `public/build/` | 后台构建产物 |
| `public/frontend/` | 前台构建产物 |
| `storage/app/public/` | 公开上传文件 |
| `tests/` | PHPUnit 测试 |
| `docs/` | API 和服务器配置参考 |
| `.github/workflows/ci.yml` | GitHub Actions 检查流程 |

## 安装

```bash
composer install
npm ci
npm --prefix frontend ci
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
```

Windows PowerShell 可用：

```powershell
Copy-Item .env.example .env
```

安装后根据实际环境修改 `.env` 中的 `APP_URL`、数据库、邮件和队列配置。

## 开发

启动 Laravel、队列、日志和后台 Vite：

```bash
composer run dev
```

也可以分别启动：

```bash
php artisan serve
npm run dev:admin
npm run dev:frontend
```

前台默认请求同域 `/api`。独立运行前台 Vite 时，可在 `frontend/.env.local` 配置后端代理：

```dotenv
VITE_API_PROXY_TARGET=http://127.0.0.1:8000
```

## 构建

```bash
npm run build:admin
npm run build:frontend
npm run build
```

- `build:admin` 构建 `resources/` 到 `public/build/`。
- `build:frontend` 构建 `frontend/` 到 `public/frontend/`。
- `build` 顺序执行后台和前台构建。

构建完成后，服务器只需要公开访问 `public/` 目录。

## 后台与上传

登录后台后可维护产品、首页推荐和轮播图。轮播图上传保存到：

```text
storage/app/public/homepage/
```

公开访问路径为：

```text
/storage/homepage/...
```

旧轮播图路径可通过命令迁移：

```bash
php artisan homepage-slides:migrate-storage
php artisan homepage-slides:migrate-storage --delete-source
```

命令会复制并校验文件后再更新数据库；只有校验通过且旧路径不再被数据库引用时，`--delete-source` 才会删除源文件。

## 品牌、数据与主题

品牌定义以 `app/Support/PhoneCatalog.php` 为唯一来源。`products.brand` 和 `products.specs.company` 会被迁移为英文 canonical 名称，例如 `Huawei`、`Xiaomi`、`Lenovo`；中文品牌、旧路径码和旧来源文件名仍作为别名兼容搜索、导入和旧前台路由。公开展示时，`company` 和 `/api/brands.displayName` 使用中文展示名，`companyCode` 和 `/api/brands.name` 保持代码/英文名。

数据库迁移包含一次性数据规范化：补全 slug、统一品牌、清理首页推荐数量设置、整理轮播和热门文案。MySQL 环境会将目录相关表迁移为 InnoDB，并为 `homepage_featured_phones.product_id` 添加外键；迁移前应确认没有孤儿热门记录并备份生产数据。原始品牌和来源文件会尽量保存在 `specs.source_company`、`specs.source_file_original` 里便于追溯。

主题设置不再写入数据库，也不再提供 `/api/site-theme`。前后台都读取同一个本地存储键：

```text
localStorage.smartphone_catalog_theme
```

值格式为：

```json
{"mode":"light","primaryColor":"blue"}
```

`mode` 支持 `light`、`dark`、`system`，`primaryColor` 支持 `blue`、`emerald`、`violet`、`rose`、`amber`。设置仅在同一浏览器和同一站点域名内生效。

## 路由边界

- `/api/*`：Laravel API
- `/admin/*`、`/dashboard`、`/profile`：Laravel 管理后台
- `/login`、`/logout`、`/register` 等：Laravel 认证
- `/storage/*`：公开上传文件
- `/assets/*`：公开静态资源
- `/build/*`：后台构建产物
- `/frontend/*`：前台构建产物
- 其他公开页面：由 Vue SPA fallback 接管

Nginx/Apache 应先返回真实静态文件，再将请求交给 `public/index.php`。Nginx 可参考 `docs/nginx.conf.example`。

## 测试与检查

```bash
composer test
vendor/bin/phpunit
vendor/bin/pint --test
php artisan route:list --except-vendor
npm run check
npm run build
```

同一套检查也会在 `.github/workflows/ci.yml` 中运行。

依赖检查：

```bash
composer validate --strict
composer audit
composer check-platform-reqs
npm audit --audit-level=high
npm --prefix frontend audit --audit-level=high
```

## 部署

1. 将 Web 根目录指向 `public/`。
2. 配置生产 `.env`，设置 `APP_ENV=production`、`APP_DEBUG=false` 和真实数据库。
3. 安装 PHP 依赖：

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

4. 安装并构建前端：

   ```bash
   npm ci
   npm --prefix frontend ci
   npm run build
   ```

5. 运行数据库迁移和公开上传链接：

   ```bash
   php artisan migrate --force
   php artisan storage:link
   ```

6. 优化 Laravel：

   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

7. 确保 `storage/` 和 `bootstrap/cache/` 可写，并持久化 `storage/app/public/`。
