# 开发手册

## 项目边界

- 单仓结构：Laravel 提供后台、认证和 `/api/*`，`frontend/` 提供公开前台。
- 生产环境只公开 `public/`；上传文件通过 `/storage/*` 暴露。
- 主要目录：

| 路径 | 作用 |
| --- | --- |
| `app/` | Laravel 控制器、模型、命令和业务逻辑 |
| `routes/` | Web、API、认证和控制台路由 |
| `resources/` | Blade 后台视图、样式和脚本 |
| `frontend/` | Vue 前台源码和 Vite 配置 |
| `public/assets/` | 公开静态资源，例如品牌 Logo 和占位图 |
| `public/build/` | 后台构建产物 |
| `public/frontend/` | 前台构建产物 |
| `storage/app/public/` | 公开上传文件 |
| `tests/` | PHPUnit 测试 |

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

安装后按实际环境补齐 `.env` 中的 `APP_URL`、数据库、邮件和队列配置。仓库默认提供 `database/database.sqlite` 作为本地 SQLite 起点。

## 开发与构建

一键启动本地开发：

```bash
composer run dev
```

如需分开运行：

```bash
php artisan serve
npm run dev:admin
npm run dev:frontend
```

前台默认请求同域 `/api`。前台独立开发时，可在 `frontend/.env.local` 设置：

```dotenv
VITE_API_PROXY_TARGET=http://127.0.0.1:8000
```

构建命令：

```bash
npm run build:admin
npm run build:frontend
npm run build
```

- `build:admin` 输出到 `public/build/`
- `build:frontend` 输出到 `public/frontend/`
- `build` 顺序执行后台和前台构建

## 系统规则

### 上传与存储

- 首页轮播图上传保存到 `storage/app/public/homepage/`
- 公开访问路径为 `/storage/homepage/...`
- 旧轮播图路径可用以下命令迁移：

```bash
php artisan homepage-slides:migrate-storage
php artisan homepage-slides:migrate-storage --delete-source
```

- `--delete-source` 只会在复制、校验和数据库引用更新都成功后删除旧文件。

### 品牌与数据规则

- 品牌定义以 `app/Support/PhoneCatalog.php` 为唯一来源。
- 数据库存储和内部逻辑使用英文 canonical 品牌名，例如 `Apple`、`Huawei`、`Xiaomi`、`Lenovo`。
- Lenovo 兼容旧路径码 `/LENOVO_XIAOXIN`、`/LIANXIANG`。
- 缺失图片时前台使用本地占位图 `/assets/phone-placeholder.svg`。

### 主题规则

- 主题不走服务端接口，不写入数据库。
- 前后台共用浏览器本地存储键 `localStorage.smartphone_catalog_theme`。
- 默认值：

```json
{"mode":"light","primaryColor":"blue"}
```

### 路由边界

- `/api/*`：Laravel API
- `/admin/*`、`/dashboard`、`/profile`：Laravel 管理后台
- `/login`、`/logout`、`/register` 等：Laravel 认证
- `/storage/*`、`/assets/*`、`/build/*`、`/frontend/*`：静态或构建资源
- 其他公开页面：Vue SPA fallback

## 测试与检查

```bash
composer test
vendor/bin/phpunit
vendor/bin/pint --test
php artisan route:list --except-vendor
npm run check
npm run build
```

依赖与平台检查：

```bash
composer validate --strict
composer audit
composer check-platform-reqs
npm audit --audit-level=high
npm --prefix frontend audit --audit-level=high
```

`.github/workflows/ci.yml` 会执行同一套核心检查。

## 部署

1. 将 Web 根目录指向 `public/`。
2. 配置生产 `.env`，至少设置 `APP_ENV=production`、`APP_DEBUG=false` 和真实数据库连接。
3. 安装生产依赖：

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm --prefix frontend ci
npm run build
```

4. 执行迁移并创建公开上传链接：

```bash
php artisan migrate --force
php artisan storage:link
```

5. 优化 Laravel 缓存：

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

6. 确保 `storage/` 与 `bootstrap/cache/` 可写，并持久化 `storage/app/public/`。
7. Web 服务器应优先返回真实静态文件，再将其他请求交给 `public/index.php`。Nginx 最小规则：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```
