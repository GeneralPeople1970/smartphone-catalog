# 开发手册

> 本手册是开发与部署的权威来源；改动核心行为时请同步更新对应章节。

**目录**

- [项目边界](#项目边界)
- [安装](#安装)
- [开发与构建](#开发与构建)
- [系统规则](#系统规则) — 上传存储、品牌数据、派生列与搜索、分页与直查、权限系统、安全加固、路由边界
- [测试与检查](#测试与检查)
- [供应链与仓库安全](#供应链与仓库安全)
- [部署](#部署) — 服务器要求、构建发布、生产 `.env`、运维、Nginx、CSP、Docker

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
touch database/database.sqlite
php artisan migrate
php artisan storage:link
```

安装后按实际环境补齐 `.env` 中的 `APP_URL`、数据库、邮件和队列配置。默认使用 SQLite，需先创建空数据库文件（上面的 `touch database/database.sqlite`，或运行 `composer run setup` 自动创建并迁移）；仓库不提交数据库文件（`database/.gitignore` 忽略 `*.sqlite*`）。

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

### 前端性能

- **路由懒加载**：`frontend/src/router/index.js` 中除首屏 `Home` 外，`PhoneDetail`、`Category`、`BrandPhoneList` 均为动态 `import()`，各自单独打包按需加载（22 个品牌路由共用同一 `BrandPhoneList` chunk），降低首屏 JS。
- **Bootstrap JS 按需**：`main.js` 不再 `import 'bootstrap'` 整包；唯一需要 JS 的首页轮播在 `Home.vue` 里 `import 'bootstrap/js/dist/carousel'` 只引入 Carousel 插件（含其 data-api），移动端菜单与主题面板为纯 Vue。构建产物已核实不含 Modal/Dropdown/Tooltip/Offcanvas 等未用插件。
- **Bootstrap CSS 裁剪（评估结论）**：前台用到的 Bootstrap 面较广——栅格/容器、**完整 utilities API**、按钮、表单、alert、carousel、图标字体，外加暗色模式所需的 `--bs-*` 变量。可行的裁剪方式是引入 `sass` 后自建 `custom-bootstrap.scss`，仅 `@import` `functions/variables/variables-dark/maps/mixins/utilities/root/reboot/containers/grid/buttons/forms/alert/carousel/helpers/utilities/api`，剔除未用组件（modal/dropdown/nav/navbar/card/accordion/table/toast/tooltip/popover/offcanvas/pagination/badge/progress/list-group 等）。**因需新增构建依赖并做视觉回归验证，暂缓落地**，此处仅记录方案；`utilities/api` 会重新生成全部工具类，故裁剪不会丢失用到的工具类，风险集中在组件。
- **图片**：列表/卡片图统一 `loading="lazy"` + `decoding="async"`，首页轮播与详情主图保持即时加载并 `fetchpriority="high"`；卡片图给出 `width`/`height`（配合固定尺寸容器预留版面，减少 CLS）。静态图片与字体的浏览器缓存由 Web 服务器设置：`public/.htaccess` 与 [Nginx 示例](#nginx-示例)对 `/build`、`/frontend` 哈希产物设 `immutable`，对图片/字体设 30 天缓存。
- **请求竞态**：搜索类请求（首页、搜索页、品牌页搜索框）用 `AbortController` 取消上一笔在途请求，配合既有的 `requestId` 守卫，避免慢响应覆盖新结果。

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

### 派生列与搜索

- `products` 表的 `release_date`、`search_text` 与 `slug_key` 是**派生列**，由 `App\Models\Product` 的 `saving` 钩子在每次保存时从 `specs`、`slug` 与主字段自动生成，**请勿手动赋值或加入 `$fillable`**。
  - `release_date`：取自 `specs.saledate`（无有效日期为 `null`），带索引，用于列表与首页排序。
  - `search_text`：拼接型号、品牌、SoC、来源 ID 及 `specs` 的 `phonename/company/socname/cpu/gpu/feature`（含去空格 compact 形式），统一小写，作为搜索的单列来源。
  - `slug_key`：`Product::normalizeSlug(slug ?: name)` 的规范化结果（小写、空白/斜杠归一为 `-`），带**非唯一**索引，作为机型详情的直查键（见下）。唯一的 `slug` 列不变。
- 关键词搜索统一走 `Product::scopeSearch($keyword)`，前台 API（`PhoneController`）与后台列表（`ProductController`）共用；内部用 `PhoneCatalog::expandSearchKeywords()` 扩展品牌与芯片别名后匹配 `search_text`。新增搜索入口请复用此 scope，不要再写多字段 `JSON_EXTRACT`。
- 新增需要参与搜索/排序的手机字段时：更新 `Product::deriveSearchText()` / `deriveReleaseDate()`，并补一条回填迁移（参照 `2026_06_28_000001_add_search_columns_to_products_table`，用 `chunkById` + `save()` 触发钩子回填，保持跨 SQLite/MySQL 兼容）。

### 列表分页与详情直查

- `GET /api/phones`（含 `/search`）在**数据库层**排序并分页：先按 `release_date`（有日期的降序在前、无日期的按 `name`、`id` 兜底）排序，再取当页，公开请求不会一次性把整表读入内存。取回当页后，`PhoneController::sortPhoneList()` 只在当页内做同日机型的系列/变体细排。
- **两种分页模式**：
  - `page`（默认，兼容模式）：`?page=N&limit=M` OFFSET 分页，页码统一校验并硬上限 `100000`（防止超大页码造成溢出或异常深扫描）；响应头返回 `X-Total-Count`、`X-Per-Page`、`X-Current-Page`。
  - `cursor`（键集分页）：`?paginate=cursor`，游标编码排序键元组（无日期标志, release_date, name, id），`id` 为唯一 tie-breaker，保证深翻页稳定且恒为 `O(limit)`；响应体 `{data, meta:{nextCursor, hasMore, perPage, total}}`。游标由 `App\Support\ListCursor` 编解码，无效游标返回 `422`。前台品牌页 `getPhonesByBrand()` 已用 cursor 模式循环取全量，品牌超过 500 台机型不会静默丢失。
- 公开 API 查询参数统一走 `ValidatesApiQuery` trait 校验（brand/q/slug/page/cursor/limit/ids/name/names/fields/paginate）：字符串位收到数组/对象、非法整数、越界页码一律统一 `422` JSON，杜绝 500 与 PHP warning。
- `GET /api/phones/detail?slug=` 用 `where('slug_key', normalizeSlug($slug))` **单条直查**（配合品牌过滤），不再加载品牌全部机型后在 PHP 里逐条比对。入参与存储值用同一个 `Product::normalizeSlug()` 归一，因此按接口返回的 `slug` 生成的旧链接仍可命中；`slug_key` 非唯一，命中多条时取最小 `id`，与旧“取第一条”一致。

### 搜索与性能

- 搜索驱动由 `config/catalog.php`（`CATALOG_SEARCH_DRIVER`）切换，统一入口仍是 `Product::scopeSearch()`：
  - **`like`（默认）**：对单列 `search_text` 做 `LIKE '%关键词%'`。**前缀带 `%` 的 LIKE 属全表扫描，任何 B-Tree 索引都无法加速**，所以 `search_text` 不加普通索引（加了也无用）。小数据量下完全够用，并有公开接口限流兜底。
  - **`fulltext`（生产 MySQL 大数据量）**：迁移 `2026_07_16_000002` 仅在 MySQL 上创建 **ngram 解析器的 FULLTEXT 索引**（MySQL 5.7+ 自带 ngram，默认 token 2，天然支持中文），查询用 `MATCH ... AGAINST('"词"' IN BOOLEAN MODE)` 短语匹配。已在 MySQL 5.7.26 实测：`骁龙` 等中文关键词结果与 LIKE 完全一致，EXPLAIN 走 fulltext 索引。
  - **降级策略**：driver 为 `fulltext` 时，非 MySQL 连接（如 SQLite 测试）与短于 2 字符的关键词自动逐项回退 LIKE——搜索永远可用，只会降速不会报错。品牌/芯片别名扩展（`PhoneCatalog::expandSearchKeywords`，含“骁龙、闪充”等语义）在两种驱动下都生效。
  - 启用步骤：跑迁移（自动建索引）→ `.env` 设 `CATALOG_SEARCH_DRIVER=fulltext`；需要整表重建索引时执行 `OPTIMIZE TABLE products;`。
- 复合索引：`2026_07_16_000001` 增加 `(status, brand, release_date)`——经 MySQL 5.7 EXPLAIN 实测品牌页由单列索引换到该复合索引（覆盖扫描、检查行数降到品牌行数）。曾评估 `(status, release_date)` 但**放弃**：默认列表 ORDER BY 以 CASE 表达式开头（无日期排后），B-Tree 无法服务，EXPLAIN 仍 filesort 且优化器不选它，加了只是冗余。
- 更大规模的后续路径：Laravel Scout + Meilisearch/Elasticsearch，把 `search_text` 作为索引文档；无论哪种，仅替换 `Product::scopeSearch()` 的实现，保持前台/后台共用同一入口。

### API 字段与计数

- 四个 `app/Http/Controllers/Api/*` 控制器的 `fields` 解析、别名映射、字段裁剪和价格格式化统一在 `App\Http\Controllers\Api\Concerns\ResolvesApiFields` trait，新增 API 控制器请复用，不要重复实现。
- 统计计数优先用聚合查询，避免逐项 `count()` 造成 N+1：品牌数量用一次 `groupBy('brand','source_file')`（`BrandController`），产品状态统计用 `Product::statusCounts()`（一次 `groupBy('status')`）。

### 主题规则

- 主题不走服务端接口，不写入数据库。
- 前后台共用浏览器本地存储键 `localStorage.smartphone_catalog_theme`。
- 默认值：

```json
{"mode":"light","primaryColor":"blue"}
```

### 权限系统

- 账号有两个受控字段，均由数据库默认值保证，且**不在 `User::$fillable`**，无法通过注册、资料更新或伪造请求写入：
  - `role`：`user`（默认）、`editor`、`admin`、`owner`，对应 `App\Enums\UserRole`。
  - `status`：`active`（默认）、`suspended`，对应 `App\Enums\UserStatus`。
- 角色能力自低到高继承：

| 角色 | 能力 |
| --- | --- |
| `user` | 浏览公开页面与 API；仅能修改/删除本人资料；无权访问 `/dashboard` 和 `/admin/*` |
| `editor` | 继承 user；访问控制台；管理手机、批量导入、首页热门与轮播图 |
| `admin` | 继承 editor；查看用户列表；停用/恢复 user、editor；在 user 与 editor 间调整角色；**不能**修改或授予 admin/owner |
| `owner` | 全部权限；授予/撤销 admin；管理其他 owner；但不能停用、删除或降级**最后一个 active owner**，Web 界面也禁止修改自己的角色或停用自己 |

- 服务端强制手段（不只依赖前端隐藏菜单，每个写操作都授权）：
  - 中间件别名在 `bootstrap/app.php` 注册：`active`（`EnsureUserIsActive`，停用即登出并拦截）、`role`（`EnsureUserHasRole`，如 `role:editor,admin,owner`）。
  - Policy 授权覆盖每个写操作：`ProductPolicy`、`HomepageSlidePolicy`、`HomepageFeaturedPhonePolicy`（editor 及以上），`UserPolicy`（owner/admin 精细规则、自我保护、最后一个 active owner 保护）。
  - **菜单可见性仅为 UX**：后台侧栏/顶栏（`sidebar.blade.php`、`navigation.blade.php`）与前台 `NavBar.vue` 按角色能力渲染菜单——user 只见首页/个人资料/退出，editor 见管理项，admin/owner 增用户管理；前台用户名 user 指向 `/profile`、editor 及以上指向 `/dashboard`。能力标志由 `/api/me` 与首屏注入的 `user.canAccessAdmin` 提供，但**隐藏菜单不等于授权**，上述中间件与 Policy 仍是真正关卡。
  - **最后一个 active owner 不变量**集中在 `App\Services\OwnerGuard::mutate()`：任何改角色/停用/删除 owner 的路径（`ProfileController::destroy`、`UserController`、`user:promote` 命令）都在事务内加行锁重读、变更后提交前复核“至少保留一名 active owner”，否则抛 `LastActiveOwnerException` 回滚。并发降级/停用不会同时通过（MySQL 行锁串行化，SQLite 亦通过）；从 0 owner 初始化第一个 owner 仍可用。`ProfileController::destroy` 先校验不变量、再登出，拒绝时账号与会话保持不变。
- 认证流程：
  - 保持开放注册，不启用邮箱验证（`User` 不实现 `MustVerifyEmail`），后台路由不再使用 `verified` 中间件。邮箱验证的路由、控制器（`EmailVerification*`、`VerifyEmail`）与页面均已移除；`users.email_verified_at` 列仅为架构兼容保留，不参与任何权限或路由判断。
  - 注册后普通用户重定向到 `/profile`；登录后按角色跳转（editor 及以上到控制台，其余到 `/profile`）。
  - `suspended` 用户禁止登录；已登录后被停用会在下一次访问受保护路由时被登出。
  - 注册接口限流 `throttle:5,1`（每 IP 每分钟 5 次），登录沿用原有防暴力破解限制。

### 用户管理与初始化 owner

- `/admin/users`（仅 admin/owner）提供用户列表、搜索、分页、改角色、停用/恢复；角色与状态变更会写入日志（操作者、目标用户、旧值、新值），不记录密码、`remember_token` 或会话。
- 系统不会自动产生 owner（不在 Seeder 创建，也不按固定邮箱在每次请求赋权）。初始化流程是先正常注册，再用服务器 CLI 提升：

```bash
php artisan user:promote owner@example.com --role=owner
php artisan user:promote owner@example.com --role=owner --force   # 非交互环境
```

- 用户不存在会报错；默认需要交互确认，`--force` 仅跳过交互确认，**不能绕过最后 owner 保护**——目标是唯一 active owner 时任何降级都会失败（非 0 退出码，数据库不变）；存在第二个 active owner 时允许降级；`--role` 支持 `user|editor|admin|owner`。命令名为历史兼容保留，实际支持任意角色调整（见 `--role`）。

### 安全加固

- **轮播图上传**：文件名随机（`Str::random`，不含原始名），扩展名由服务端 MIME（`finfo`）决定，仅接受 jpg/jpeg/png/webp/gif，并经 GD 重新解码编码以剥离元数据与潜在的 polyglot/脚本内容；限制单边像素、总像素与文件大小。`/storage` 目录须在 Web 服务器层禁止执行 PHP（见[部署](#部署)）。
- **URL 安全**：会渲染到 href 的字段（轮播图 `link_url`、机型规格 `official`）经 `App\Support\SafeUrl` 校验/净化，仅允许站内相对路径与 http(s)，拒绝 `javascript:`/`data:`/`vbscript:`、协议相对 `//host` 与控制字符；前台 `@/utils/url.js` 的 `safeExternalUrl` 为第二层防护。
- **JSON 导入**：限制文件数、总大小、记录总数、字符串长度与 JSON 嵌套深度，根节点必须是对象数组；错误信息只含用户文件名，不含服务器路径。
- **公开 API 限流**：`/api/*` 显式 `throttle:120,1`；搜索关键词长度、`ids`/`names` 数量、`limit`（默认与上限 500）均有上限，错误沿用统一 `422` 格式。
- **Seeder**：`DatabaseSeeder` 仅在 `local`/`testing` 生成测试账号；生产不创建固定测试账号，管理员通过 `user:promote` 提升。

### 路由边界

- `/api/*`：Laravel API（公开只读目录数据，无需鉴权）
- `/dashboard`、`/admin/*`：Laravel 管理后台，要求 `auth + active + role`（`/admin/users` 需 admin/owner，其余需 editor 及以上）
- `/profile`：登录用户本人资料，要求 `auth + active`
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

## 供应链与仓库安全

- **开源边界检查**：`npm run check` 会跑 `scripts/check-open-source-boundary.mjs`，拒绝把私有/敏感文件纳入版本库。覆盖：私有目录、`.env`（放行 `.env.example`）、数据库与导出（`csv/db/sqlite/sql/xls...`）、密钥与证书（`*.pem`、`*.key`、`*.p12`、`*.pfx`、`id_rsa`/`id_ed25519` 等）、凭据（`.npmrc`、`auth.json`、`credentials`）、日志与备份（`*.log`、`*.bak`、`*.tar.gz` 等）。`.gitignore` 也补了同类模式作纵深防御。
- **依赖更新（Dependabot）**：`.github/dependabot.yml` 覆盖四个生态并按周更新——Composer、根 npm、`frontend` npm、GitHub Actions；小版本/补丁分组以减少 PR 噪声。
- **依赖解析与锁定**：直接依赖使用当前主版本的 `^` 范围，三份 lock 文件（`composer.lock`、`package-lock.json`、`frontend/package-lock.json`）必须随更新一起提交，以固定经测试的完整依赖图。更新时使用 Composer/npm 的正常解析流程，不使用 `*`、`latest`、`--force`、`--ignore-platform-reqs` 或 npm overrides；上游约束不允许的传递依赖保留其最新兼容版本。
- **当前上游约束**：`mockery/mockery` 1.6.12 要求 `hamcrest/hamcrest-php ^2.0.1`，因此 Hamcrest 维持在 2.1.1，不强行升级到不兼容的 3.x。
- **CI 加固**（`.github/workflows/ci.yml`）：
  - 顶层 `permissions: contents: read`（最小权限），`concurrency` 取消同 ref 的旧运行，各 job 设 `timeout-minutes`。
  - 所有 Action 固定到**完整 commit SHA**并注释版本号（Dependabot 的 github-actions 生态会保持 SHA 更新）。
  - `secret-scan` job 用 Gitleaks 扫描秘密（`fetch-depth: 0` 全量）。
- **npm registry**：`frontend/package-lock.json` 的 `resolved` 已统一为官方 `registry.npmjs.org`（`integrity` 为包内容哈希，与镜像无关，`npm ci` 校验通过）。仓库不提交 `.npmrc`。
- **镜像使用**：阿里云镜像仅可作为一次性下载加速；若版本同步滞后或下载失败，应回退官方 Packagist、npm Registry 与 GitHub。仓库及全局配置均不保留镜像或临时超时设置。
- **需在 GitHub 后台手动开启（无法由代码配置）**：仓库 Settings → Code security and analysis 中开启 **Secret scanning** 与 **Push protection**（推送即拦截疑似密钥），作为 Gitleaks 之外的平台级第二道防线。

## 部署

### 服务器要求

- PHP `>=8.5 <9.0`，并启用扩展：`pdo`、`pdo_mysql`（或 `pdo_sqlite`）、`mbstring`、`openssl`、`tokenizer`、`xml`、`ctype`、`json`、`bcmath`、`curl`、`fileinfo`（上传 MIME 检测）、`gd`（轮播图重编码）。
- Composer 2.x、Node.js `^24.11.0`（含 npm 11，用于构建阶段），MySQL 或其他受支持数据库。
- Web 根目录必须指向 `public/`，切勿指向项目根目录（否则 `.env`、`storage/` 等会被公开）。
- PHP 上传配置需不低于应用限制：`upload_max_filesize` 与 `post_max_size` ≥ 24M（轮播图上限 20M，另需容纳表单其它字段与 multipart 开销），并适当提高 `memory_limit`（GD 重编码大图较耗内存）。三处上限保持一致的层级：`PHP ≥ 应用限制`、`Nginx client_max_body_size > 应用限制`，让应用校验成为最终、可返回友好提示的关卡。

### 构建与发布

```bash
composer install --no-dev --optimize-autoloader
# 构建依赖 vite 等 devDependencies，务必用 npm ci（不要 --omit=dev）
npm ci && npm --prefix frontend ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### 生产 `.env` 关键项

| 变量 | 生产取值 | 说明 |
| --- | --- | --- |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | 关闭调试，避免泄露堆栈与路径 |
| `APP_URL` | `https://真实域名` | 影响 `/storage` 等绝对 URL |
| `APP_KEY` | 唯一值 | `php artisan key:generate` 生成，切勿复用示例值 |
| `DB_CONNECTION` 等 | 真实数据库 | 独立账号，最小权限 |
| `SESSION_SECURE_COOKIE` | `true` | HTTPS 下仅经安全连接发送会话 Cookie |
| `SESSION_DRIVER` | `database` / `redis` | |
| `LOG_CHANNEL` / `LOG_LEVEL` | `stack` / `warning` | 生产降低日志级别，避免噪声与敏感信息 |
| `MAIL_*` | 真实邮件服务 | 如需发信 |
| `FILESYSTEM_DISK` | 按需 | 上传默认走 `public` 磁盘 |

### 权限、持久化与运维

- `storage/` 与 `bootstrap/cache/` 需 Web 进程可写。
- 持久化 `storage/app/public/`（用户上传）并保留 `php artisan storage:link` 产生的软链。
- 数据库定期备份（如 `mysqldump` 定时任务）；**迁移前先备份**。部分迁移包含数据回填/结构转换，回滚 `php artisan migrate:rollback` 前务必确认可逆并已备份。
- 负载均衡/探活使用内置健康检查端点 `/up`。
- 应用通过 `App\Http\Middleware\SecurityHeaders`（web 中间件组）为动态响应输出安全头（`X-Content-Type-Options`、`X-Frame-Options`、`Referrer-Policy`、`Permissions-Policy`、`Content-Security-Policy`，HTTPS 下附带 HSTS）。静态文件由 Web 服务器直出，其禁执行与安全头需在服务器层配置（见下方 Nginx 与 `public/.htaccess`）。
- 若应用位于 TLS 终止的反向代理/负载均衡之后，`$request->isSecure()` 需通过可信代理才能识别为 HTTPS：可在 `bootstrap/app.php` 配置 `$middleware->trustProxies(at: ...)`，否则中间件不会输出 HSTS，此时务必保留 Nginx 层的 `Strict-Transport-Security`。

### Nginx 示例

```nginx
server {
    listen 443 ssl http2;
    server_name example.com;

    root /var/www/laravel/public;
    index index.php;

    ssl_certificate     /etc/ssl/certs/example.com.pem;
    ssl_certificate_key /etc/ssl/private/example.com.key;

    # 请求体上限：略高于应用文件上限（20M），为 multipart 开销留余量，
    # 使超限时由应用返回友好提示而非 Nginx 直接 413。
    client_max_body_size 22m;

    # 静态直出补充基础安全头。动态响应由应用中间件设置同名头（取值一致），
    # 为避免对动态响应重复，可将下列 add_header 收敛到仅静态资源的 location，
    # 或改用 ngx_headers_more 的 more_set_headers；切勿只删这里而丢失静态文件（尤其 /storage 上传）的 nosniff。
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # 拒绝隐藏文件与 .env（放行 ACME 校验目录）。
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # 用户上传目录禁止执行脚本（须在 php 处理之前匹配）。
    location ~* ^/storage/.*\.(php[0-9]?|pht|phtml|phps|phar|pl|py|cgi|sh|shtml)$ {
        deny all;
    }

    # 带哈希的构建产物内容寻址，可永久缓存。
    location ~* ^/(build|frontend)/.*\.(css|js|mjs|woff2?)$ {
        add_header X-Content-Type-Options "nosniff" always;
        add_header Cache-Control "public, max-age=31536000, immutable" always;
    }

    # 图片与字体（含 /storage 上传，文件名随机、等同不可变）长缓存。
    # 这里重复声明 nosniff：带自身 add_header 的 location 不会继承上面的 server 级安全头。
    location ~* \.(jpg|jpeg|png|webp|gif|svg|ico|woff2?)$ {
        add_header X-Content-Type-Options "nosniff" always;
        add_header Cache-Control "public, max-age=2592000" always;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_hide_header X-Powered-By;
    }
}

server {
    listen 80;
    server_name example.com;
    return 301 https://$host$request_uri;
}
```

### 关于 CSP

当前 `Content-Security-Policy` 在 `script-src` 保留 `'unsafe-inline'` 与 `'unsafe-eval'`，以兼容后台 Alpine.js 与前台 SPA 的内联引导脚本；`default-src 'self'` 与 `object-src 'none'`、`base-uri 'self'`、`frame-ancestors 'self'`、`form-action 'self'` 仍能阻断外站脚本注入与点击劫持。若要进一步收紧，去掉这两个开关：为内联脚本改用每请求 nonce（在 `SecurityHeaders` 中生成并注入到脚本标签与 CSP），后台改用 Alpine 的 CSP 构建版本。

### 容器化部署（Docker）

仓库提供端到端部署方案：多阶段 [`Dockerfile`](../Dockerfile)、[`compose.yml`](../compose.yml)、[`docker/`](../docker) 配置与 [`.env.docker.example`](../.env.docker.example)。CI（`.github/workflows/ci.yml` 的 `docker` job）会实际 `docker compose build` → 一次性迁移 → `up` → 冒烟 `/up`；`mysql-test` job 另在真实 MySQL 8 上跑迁移与全套测试。

**镜像分阶段：**

- **阶段 1（`node:24-alpine`）**：`npm ci` + `npm run build`，产出 `public/build`（后台）与 `public/frontend`（前台）。
- **阶段 2（`php:8.5-cli` + composer）**：`composer install --no-dev` + `dump-autoload --optimize --classmap-authoritative`。
- **阶段 3 `runtime`（`php:8.5-fpm`）**：安装运行期扩展 `pdo_mysql`、`gd`、`zip`、`bcmath`、`opcache`（`fileinfo` 官方镜像自带并校验存在），启用 OPcache（`docker/php/opcache.ini`，`validate_timestamps=0`，改代码需重建镜像）与上传限制（`docker/php/php.ini`，`upload_max_filesize`/`post_max_size` 24M）。`package:discover` **不**再 `|| true`，发现失败即构建失败。以 `www-data` 非 root 运行，`ENTRYPOINT` 每容器缓存 config/route/view 后起 `php-fpm`。
- **阶段 4 `web`（`nginx:1.27-alpine`）**：烤入 `public/`，`docker/nginx/default.conf` 直出静态资源、`/storage` 上传目录（禁执行脚本）、`fastcgi_pass app:9000`。
- `.dockerignore` 排除 `.git`、`.env`、`vendor`、`node_modules`、`public/build`、`public/frontend`、测试数据库（`database/*.sqlite`）、`tests`、文档等，避免把本地密钥、依赖或数据打进镜像。

**编排（`compose.yml`）：** `db`（MySQL 8，`db-data` 持久卷，healthcheck）+ `app`（php-fpm）+ `web`（nginx，发布 `${WEB_PORT:-8080}:80`，healthcheck 打 `/up`）+ 一次性 `migrate`（`profiles: [tools]`，`release.sh` 等库就绪后 `migrate --force`，只此一个副本迁移，避免多副本并发迁移）。`app` 与 `web` 共享 `uploads` 卷挂到 `storage/app/public`，nginx 只读直出上传文件。

**启动、迁移与首个 owner：**

```bash
cp .env.docker.example .env          # 填 DB_PASSWORD / DB_ROOT_PASSWORD 等
docker compose build
docker compose run --rm app php artisan key:generate --show   # 把 base64:... 写入 .env 的 APP_KEY
docker compose run --rm migrate      # 一次性迁移（建库结构）
docker compose up -d                 # 起 app + web + db
# 浏览器打开 http://localhost:8080 注册账号后，提升首个 owner：
docker compose exec app php artisan user:promote owner@example.com --role=owner --force
```

**运维要点：**

- **生产 `.env` 不打进镜像**：`.dockerignore` 排除 `.env*`（放行 `.env.example`），敏感值通过 `env_file`/环境变量注入（`APP_KEY`、`APP_ENV=production`、`APP_DEBUG=false`、数据库、`SESSION_SECURE_COOKIE=true` 等）。`.env.docker.example` 不含真实密钥。
- **用户上传持久化**：`uploads` 卷挂到 `storage/app/public`，镜像重建不丢文件；`entrypoint.sh` 会 `storage:link`。生产可改对象存储（`FILESYSTEM_DISK`）。
- **发布期命令**（不在构建期，避免把 env 烤进镜像）：迁移随 `migrate` 服务执行；`config:cache`/`route:cache`/`view:cache` 随 `app` 容器 `entrypoint.sh` 执行。
- **健康检查**用内置 `/up`（经 nginx 转发到 fpm），`web` 服务 healthcheck 已内置。
- **搜索驱动**：如需 MySQL 全文检索，`.env` 设 `CATALOG_SEARCH_DRIVER=fulltext`（迁移已在 MySQL 建 ngram 索引）。
