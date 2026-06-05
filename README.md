# 智能手机目录框架

这是一个 Laravel 单仓项目，包含公开 Vue 前台、Laravel API 和 Blade 管理端。项目适合作为产品目录、参数展示、首页内容管理和后台维护的基础框架。

## 功能

- Vue 3 公开前台，使用 Vue Router history 模式和 Vite 构建。
- Laravel API 提供品牌、列表、搜索、详情、首页轮播图和首页推荐接口。
- Blade 管理端提供登录认证、产品维护、首页推荐维护、轮播图上传和排序。
- 管理端与公开前台独立构建：管理端输出到 `public/build/`，公开前台输出到 `public/frontend/`。
- 公开上传文件保存到 `storage/app/public/`，通过 `/storage/*` 访问。
- GitHub Actions 在 push 和 pull request 时运行后端、前端和构建检查。

## 环境要求

- PHP `>=8.4.1 <9.0`
- Composer `>=2.2`
- Node.js `^22.18.0 || >=24.11.0`
- npm，使用 Node.js 附带版本即可
- SQLite、MySQL 或其他 Laravel 支持的数据库

版本要求来自当前依赖的最低约束交叉验证：Laravel 13 要求 PHP `^8.3`，PHPUnit 13 要求 PHP `>=8.4.1`，前端工具链中 Vue Router 5 的 Babel 8 RC 依赖要求 Node.js `^22.18.0 || >=24.11.0`。

生产环境的 Web 根目录必须指向 `public/`。

## 目录结构

| 目录 | 说明 |
| --- | --- |
| `app/` | Laravel 控制器、模型、命令和业务支持类 |
| `routes/` | Web、API、认证和控制台路由 |
| `resources/` | Blade 管理端视图、样式和脚本 |
| `frontend/` | Vue 公开前台源码、依赖和 Vite 配置 |
| `public/assets/` | 公开静态资源，例如站点 Logo 和品牌 Logo |
| `public/build/` | Blade 管理端构建产物，不提交 |
| `public/frontend/` | Vue 前台构建产物，不提交 |
| `storage/app/public/` | 公开上传文件 |
| `tests/` | PHPUnit 测试 |
| `docs/` | API 和服务器配置参考 |

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

根据实际环境修改 `.env` 中的 `APP_URL`、数据库、邮件和队列配置。不要提交 `.env`、本地数据库、日志、上传文件、依赖目录或构建产物。

## 开发

启动 Laravel、队列、日志和管理端 Vite：

```bash
composer run dev
```

也可以按需分别启动：

```bash
php artisan serve
npm run dev:admin
npm run dev:frontend
```

Vue 前台默认请求同域 `/api`。独立运行前台 Vite 时，可在 `frontend/.env.local` 配置后端代理：

```dotenv
VITE_API_PROXY_TARGET=http://127.0.0.1:8000
```

可选的 SEO canonical 地址：

```dotenv
VITE_SITE_URL=https://example.com
```

未设置 `VITE_SITE_URL` 时，前台不会输出 canonical 链接。项目不包含第三方统计脚本。

## 构建

```bash
npm run build:admin
npm run build:frontend
npm run build
```

- `build:admin` 构建 `resources/` 到 `public/build/`。
- `build:frontend` 构建 `frontend/` 到 `public/frontend/`。
- `build` 顺序执行管理端和公开前台构建。
- 构建目录是生成产物目录，不应手动编辑或保存上传文件。

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

## 路由边界

- `/api/*`：Laravel API
- `/admin/*`、`/dashboard`、`/profile`：Laravel 管理端
- `/login`、`/logout`、`/register` 等：Laravel 认证
- `/storage/*`：公开上传文件
- `/assets/*`：公开静态资源
- `/build/*`：管理端构建产物
- `/frontend/*`：Vue 前台构建产物
- 其他公开页面：由 Vue SPA fallback 接管

Nginx/Apache 应先返回真实静态文件，再将请求交给 `public/index.php`。Nginx 可参考 `docs/nginx.conf.example`。

## 测试与质量检查

```bash
composer test
vendor/bin/phpunit
vendor/bin/pint --test
php artisan route:list --except-vendor
npm run check
npm run build
```

依赖检查：

```bash
composer validate --strict
composer audit
composer check-platform-reqs
npm audit --audit-level=high
npm --prefix frontend audit --audit-level=high
```

`npm run check` 会执行开源边界检查、前台 ESLint 和 Prettier 格式检查。

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

## GitHub

- `.github/workflows/ci.yml` 会在 push 和 pull request 时运行后端与前端检查。
- 仓库只需要维护 `main` 分支；依赖升级由人工执行并验证后提交。
- `.gitignore` 已排除环境文件、本地数据库、日志、缓存、上传文件、依赖目录和构建产物。
