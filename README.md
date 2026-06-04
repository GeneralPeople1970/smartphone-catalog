# 智能手机参数站

Laravel 单仓应用，包含：

- Laravel API：为公开前台提供手机、品牌、首页轮播图等数据。
- Laravel Blade 管理端：后台管理、认证页面和用户资料页面。
- Vue 公开前台：源码位于 `frontend/`，生产构建输出到 `public/frontend/`。

## 技术栈与要求

- PHP `>=8.5.5 <9.0`
- Laravel 13
- PHPUnit 13
- Node.js 22.12+
- npm
- Vue 3、Vue Router 4、Vite 6
- Blade、Tailwind CSS 4、Alpine.js 3、Vite 8

生产环境的 Web 根目录必须指向 `public/`。

## 目录职责

| 目录 | 职责 |
| --- | --- |
| `frontend/` | Vue 公开前台源码、依赖和 Vite 配置 |
| `resources/` | Laravel Blade 管理端、认证页面及其 CSS/JavaScript |
| `public/frontend/` | Vue 前台构建产物；生成目录，不提交、不手动编辑 |
| `public/build/` | Laravel Blade 管理端构建产物 |
| `public/assets/` | Laravel 与 Vue 共用的公开静态资源，仅包含站点 Logo 和品牌 Logo |
| `storage/app/public/` | 用户公开上传文件 |
| `public/storage` | 指向 `storage/app/public` 的软链接 |
| `storage/app/private/phone-data/` | 私有手机数据源；默认被 Git 忽略，不能由 Web 直接访问 |

## 安装

```bash
composer install
npm ci
npm --prefix frontend ci
php artisan key:generate
php artisan migrate
php artisan storage:link
```

首次安装前，将 `.env.example` 复制为 `.env` 并配置数据库、邮件和应用地址。不要提交 `.env` 或真实凭据。

## 开发

启动 Laravel、队列、日志和 Blade 管理端 Vite：

```bash
composer run dev
```

单独启动服务：

```bash
php artisan serve
npm run dev:admin
npm run dev:frontend
```

Vue 默认通过同域 `/api` 请求 Laravel。单独运行前台 Vite 时，可在 `frontend/.env.local` 设置：

```dotenv
VITE_API_PROXY_TARGET=http://127.0.0.1:8000
```

也可用 `VITE_API_BASE_URL` 覆盖默认的 `/api` 基础路径。不要硬编码开发机地址。

如需 canonical SEO 地址，可在 `frontend/.env.local` 或部署环境设置 `VITE_SITE_URL=https://example.com`。该值表示网站对搜索引擎公开的正式域名；未设置时前端不会输出 canonical 地址。项目不包含统计脚本。

## 构建

```bash
npm run build:admin
npm run build:frontend
npm run build
```

- `build:admin` 仅构建 `resources/` 到 `public/build/`。
- `build:frontend` 构建 Vue 到 `public/frontend/`。
- 前台输出目录只保存生成产物，每次构建会清理旧文件，避免残留失效的哈希资源。
- 安装前台依赖使用 `npm run install:frontend`，构建命令不会隐式修改依赖。

## 数据与上传文件

真实手机数据属于运行时私有数据，不属于开源源码。默认从 Git 忽略且无法被 Web 直接访问的 `storage/app/private/phone-data/` 导入：

```bash
php artisan phones:import
```

也可在 `.env` 设置绝对目录 `PHONE_DATA_PATH`，或临时运行 `php artisan phones:import --path=/private/path`。不要把真实目录数据、数据库、导出文件或上传文件提交到 Git。

`--fresh` 会删除之前由数据文件导入的产品，执行前必须确认环境。

轮播图上传到 `storage/app/public/homepage/`，公开 URL 为 `/storage/homepage/...`。旧版本轮播图可安全迁移：

```bash
php artisan homepage-slides:migrate-storage
php artisan homepage-slides:migrate-storage --delete-source
```

命令会先复制并校验文件，再更新数据库。只有目标文件校验通过且旧路径不再被数据库引用时，`--delete-source` 才会删除旧文件。

## 路由边界

- `/api/*`：Laravel API
- `/admin/*`、`/dashboard`、`/profile`：Laravel 管理端
- `/login`、`/logout` 及其他认证路径：Laravel 认证
- `/storage/*`：公开上传文件
- `/assets/*`：Laravel 与 Vue 共用的稳定静态资源
- `/build/*`：Blade 管理端构建产物
- `/frontend/*`：Vue 前台构建产物
- `/dist/*`：旧轮播图迁移兼容路径，不作为当前构建目录
- `/phone/:id`：Vue SPA 手机详情深链接；原始手机数据不会通过 `public/` 暴露
- 其他公开页面：Vue Router history 模式，由 Laravel 最后的 SPA fallback 提供

Nginx/Apache 应先尝试返回真实静态文件，再将请求交给 `public/index.php`。Nginx 可参考 `docs/nginx.conf.example`：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## 测试与验证

Laravel 13 当前项目不提供 `php artisan test`，正式测试入口为：

```bash
composer test
vendor/bin/phpunit
vendor/bin/pint --test
php artisan route:list
```

依赖和构建验证：

```bash
composer validate --no-check-publish
composer install --dry-run
composer audit
composer check-platform-reqs
npm ci
npm audit
npm run check
npm run build:admin
npm --prefix frontend ci
npm --prefix frontend audit
npm --prefix frontend run build
```

## 部署

1. 将 Web 根目录指向 `public/`，配置生产 `.env`，确保 PHP 版本不低于 8.5.5。
2. 运行 `composer install --no-dev --optimize-autoloader`。
3. 运行 `npm ci`、`npm --prefix frontend ci` 和 `npm run build`，分别生成 `public/build/` 与 `public/frontend/`。
4. 运行 `php artisan migrate --force` 和 `php artisan storage:link`。
5. 确保 `storage/` 与 `bootstrap/cache/` 可写，并确认 `/storage/*`、`/build/*`、`/frontend/*` 静态资源可访问。

用户上传文件必须持久化 `storage/app/public/`，不能依赖或写入任何构建输出目录。

## 代码质量与 GitHub

- PHP 使用 Pint 格式化并由 PHPUnit 覆盖核心行为。
- Vue 使用 ESLint 与 Prettier；运行 `npm run check` 检查，运行 `npm run format:frontend` 格式化。
- `.github/workflows/ci.yml` 会在推送和拉取请求时验证后端、前端、审计和构建。
- Dependabot 每周检查 Composer、根 npm 和 `frontend/` npm 依赖。
- `.env`、SQLite、日志、缓存、上传文件、依赖目录和构建产物均不会提交到 Git。
- `npm run check:open-source` 会阻止私有手机数据目录、数据库、数据导出文件或 `.env` 被 Git 跟踪。
- 品牌 Logo 与站点 Logo 是公开静态图片；图片内不包含手机目录记录。API 文档和测试仅使用合成示例。
