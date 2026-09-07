# 开发手册

> 本手册是开发与部署的权威来源；改动核心行为时请同步更新对应章节。

**目录**

- [项目边界](#项目边界)
- [安装](#安装)
- [开发与构建](#开发与构建)
- [系统规则](#系统规则) — 上传存储、品牌数据、派生列与搜索、分页与直查、布局与导航、后台表单与控件、权限系统、站点设置与邮箱验证、安全加固、路由边界、错误页
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
| `public/build/` | 后台构建产物（**随代码提交**） |
| `public/frontend/` | 前台构建产物（**随代码提交**） |
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

**构建产物随代码提交**（`.gitignore` 里 `public/build/`、`public/frontend/` 是特意没被忽略的），这样服务器 `git pull` 就能上线，不需要装 Node。代价是：**改了 `resources/` 或 `frontend/` 下的任何资源，都要重新 `npm run build` 并把产物一起提交**，否则线上跑的还是旧资源。`.gitattributes` 的 `* text=auto eol=lf` 保证 Windows 上构建的产物和 Linux 上一致，不会因为换行符产生假 diff。

### 前端性能

- **路由懒加载**：`frontend/src/router/index.js` 中除首屏 `Home` 外，`PhoneDetail`、`Category`、`BrandPhoneList` 均为动态 `import()`，各自单独打包按需加载（22 个品牌路由共用同一 `BrandPhoneList` chunk），降低首屏 JS。
- **Bootstrap JS 按需**：`main.js` 不再 `import 'bootstrap'` 整包；唯一需要 JS 的首页轮播在 `Home.vue` 里 `import 'bootstrap/js/dist/carousel'` 只引入 Carousel 插件（含其 data-api），移动端菜单为纯 Vue。构建产物已核实不含 Modal/Dropdown/Tooltip/Offcanvas 等未用插件。
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
- 缺失或加载失败的图片统一回退到站点 Logo `/assets/logo.png`：服务端 `Product::safeImageUrl()` 判断引用是否安全——放行站内相对路径与任意 host 的 http(s) 外链图片，但拒绝降级为 http 的混合内容、`javascript:`/`data:` 等非法 scheme、协议相对 `//host` 与反斜杠路径；前台 `@/utils/image.js` 的 `imageOrPlaceholder()` 做同样判断，`applyImageFallback()` 再兜住运行时 404（`<img @error>`），后台列表与轮播图预览用等价的 `onerror`。旧的 `/assets/phone-placeholder.svg` 已删除。品牌 Logo 例外：加载失败时隐藏或显示品牌名，不套用站点 Logo（那会显示成错误的品牌）。

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

### 布局与导航规则

- **导航样式单一来源**：`resources/css/shared-navigation.css` 定义两行导航（`.shared-top-nav` 72px / 移动端 60px，`.shared-main-nav` 54px）及品牌、菜单、用户按钮的全部尺寸与配色。后台 `resources/views/layouts/navigation.blade.php` 与前台 `frontend/src/components/NavBar.vue` **复用同一套 class**，且都通过 `resources/css/app.css` / `App.vue` 引入该文件，因此高度与样式天然一致。**不要在任何布局里用内联 `<style>` 或 `!important` 覆盖 `--shared-nav-*`**——那正是此前前后台顶栏对不齐的原因。
- **后台没有侧边栏**：`layouts/app.blade.php` 的结构（`.admin-root` flex 纵向 → 顶栏 → `.admin-main` flex:1）与前台 `App.vue` 的 `.app-container` 一致，顶栏第二行是唯一导航。
- **容器宽度统一**：`.admin-container`（后台）与 `.app-container .container`（前台）都取 `--shared-nav-container-width`（`min(1760px, 100% - clamp(24px, 4vw, 80px))`，≤991.98px 时为 `calc(100% - 32px)`），保证导航、页头与内容左右边距在前后台完全对齐。
- **顶栏动作区左右一致**：桌面端与移动端折叠菜单里都是「用户名按钮 + 退出登录」两个控件、同一顺序；前台的退出按钮是一个真实的 `POST /logout` 表单，token 来自首屏注入的 `window.__SMARTPHONE_CATALOG_AUTH__.csrfToken`（`FrontendController`）并由 `/api/me` 刷新（`Api\SessionController`），没有 token 时按钮不渲染（避免 419）。

### 后台表单与控件规则

- **控件几何只有一套变量**：`resources/css/app.css` 顶部的 `--admin-control-height`（2.5rem）、`--admin-control-radius`、`--admin-label-font-size` 等驱动 `.admin-input` / `.admin-select` / `.admin-file-input` / `.admin-button*` / `.admin-pagination-*`，所以输入框、下拉框、文件选择器和按钮在同一行天然等高。改高度只改变量，别在页面里写死。
- **字段宽度是被限制的，不是被拉满的**：页面外壳最宽 1760px，因此表单再套一层 `.admin-form-shell`（72rem）或 `.admin-form-shell-narrow`（44rem）；`.admin-form-grid` 用 `repeat(auto-fill, minmax(15rem, 24rem))`，短字段加 `.admin-field-narrow`（11rem），长字段加 `.admin-field-wide` / `.admin-field-full`。列表页筛选条用 `.admin-filter-bar`，关键词框 `.admin-field-keyword` 最宽 26rem，按钮紧跟其后。
- **限宽的表单是居中的**：两个 shell class 都带 `margin-inline: auto`，并且**页头要套同一个 class**（`<x-slot name="header">` 里那一层 div），否则标题贴着 1760px 容器的左边、面板在中间，看起来像布局坏了。`tests/Feature/AdminUiConsistencyTest.php` 会检查这两处成对出现。
- **一个字段 = label + 控件 + 说明/报错**：说明文字用 `.admin-hint`（不要塞进 placeholder），报错统一 `.admin-field-error`（`<x-input-error>` 也走这个 class）。与输入框同排的复选框用 `.admin-checkbox-field`，它在栅格里按「一行 label 的高度」下移，正好与输入框对齐。
- **颜色只用主题变量**：`--admin-danger*` / `--admin-warning*` / `--admin-text` / `--admin-muted` / `--admin-border*`。后台页面（含登录/注册与分页、模态框）**不再出现 `text-gray-*`、`bg-gray-*`、`border-gray-*`、`text-red-*`、`indigo-*` 这类固定色 class**，也不再需要 `[data-bs-theme='dark']` 的 `!important` 补丁；`tests/Feature/AdminUiConsistencyTest.php` 会守住这条线，同时禁止后台页面出现内联 `<style>`。

### 主题规则

- 主题不走服务端接口，不写入数据库。
- 前后台只跟随系统的 `prefers-color-scheme` 自动切换浅色/深色，不提供手动设置，也不读取或写入浏览器本地存储。
- 浅色模式主色固定为 `#007bff`（RGB `0, 123, 255`），hover 色为 `#0069d9`；深色模式固定为低饱和蓝 `#3b82c4`，hover 色为 `#4c94d3`，避免高饱和蓝在深色背景中过于突兀。

### 权限系统

- 账号有两个受控字段，均由数据库默认值保证，且**不在 `User::$fillable`**，无法通过注册、资料更新或伪造请求写入：
  - `role`：`user`（默认）、`editor`、`admin`、`owner`，对应 `App\Enums\UserRole`。
  - `status`：`active`（默认）、`suspended`，对应 `App\Enums\UserStatus`。
- 角色能力自低到高继承：

| 角色 | 能力 |
| --- | --- |
| `user` | 浏览公开页面与 API；访问只读控制台；仅能修改/删除本人资料；无权访问 `/admin/*` |
| `editor` | 继承 user；访问控制台；管理手机、批量导入、首页热门与轮播图 |
| `admin` | 继承 editor；查看用户列表；停用/恢复 user、editor；在 user 与 editor 间调整角色；**不能**修改或授予 admin/owner |
| `owner` | 全部权限；授予/撤销 admin；管理其他 owner；但不能停用、删除或降级**最后一个 active owner**，Web 界面也禁止修改自己的角色或停用自己 |

- 服务端强制手段（不只依赖前端隐藏菜单，每个写操作都授权）：
  - 中间件别名在 `bootstrap/app.php` 注册：`active`（`EnsureUserIsActive`，停用即登出并拦截）、`role`（`EnsureUserHasRole`，如 `role:editor,admin,owner`）。
  - Policy 授权覆盖每个写操作：`ProductPolicy`、`HomepageSlidePolicy`、`HomepageFeaturedPhonePolicy`（editor 及以上），`UserPolicy`（owner/admin 精细规则、自我保护、最后一个 active owner 保护）。
  - **菜单可见性仅为 UX**：后台顶栏（`navigation.blade.php`）与前台 `NavBar.vue` 按角色能力渲染菜单——后台没有侧边栏，顶栏是唯一导航；user 使用与管理员相同的后台布局，只见控制台/个人资料/退出，editor 增管理项，admin/owner 再增用户管理。能力标志由 `/api/me` 与首屏注入的 `user.canAccessAdmin` 提供，但**隐藏菜单不等于授权**，上述中间件与 Policy 仍是真正关卡。
  - **前后台切换**：右上角用户名按钮（`.shared-user-chip`）是前后台的唯一切换入口——前台已登录时指向 `/dashboard`，后台指向 `/`（`route('home')`）。它旁边跟着「退出登录」按钮（`.shared-nav-logout`），前后台、桌面端与移动端折叠菜单都是同样的两个控件、同样的顺序。
  - **最后一个 active owner 不变量**集中在 `App\Services\OwnerGuard::mutate()`：任何改角色/停用/删除 owner 的路径（`ProfileController::destroy`、`UserController`、`user:promote` 命令）都在事务内加行锁重读、变更后提交前复核“至少保留一名 active owner”，否则抛 `LastActiveOwnerException` 回滚。并发降级/停用不会同时通过（MySQL 行锁串行化，SQLite 亦通过）；从 0 owner 初始化第一个 owner 仍可用。`ProfileController::destroy` 先校验不变量、再登出，拒绝时账号与会话保持不变。
- 认证流程：
  - **邮箱验证是后台开关**，不是编译期决定：`User` 始终实现 `MustVerifyEmail`（保证验证路由与通知可用），是否真的拦截未验证账号由 `App\Http\Middleware\EnsureEmailIsVerified`（覆盖框架的 `verified` 别名）读取站点设置 `registration_email_verification` 决定。详见[站点设置与邮箱验证](#站点设置与邮箱验证)。
  - 开关关闭（默认）时保持开放注册：注册即把 `email_verified_at` 记为当前时间，不发信，直接进入 `/dashboard`。
  - 开关开启时注册后进入 `/verify-email`，点击邮件里的签名链接后回到 `/dashboard?verified=1`。
  - 登录后统一进入 `/dashboard`；普通用户看到只读控制台，editor 及以上按角色显示管理入口。
  - `suspended` 用户禁止登录；已登录后被停用会在下一次访问受保护路由时被登出。
  - 注册接口限流 `throttle:5,1`（每 IP 每分钟 5 次），登录沿用原有防暴力破解限制，重发验证邮件限流 `throttle:6,1`。

### 用户管理与初始化 owner

- `/admin/users`（仅 admin/owner）提供用户列表、搜索、分页、改角色、改邮箱验证状态、停用/恢复；角色、状态与验证状态变更会写入日志（操作者、目标用户、旧值、新值），不记录密码、`remember_token` 或会话。
- **邮箱验证状态可以手改**，这是注册验证开关的逃生舱：地址已经收不到信、或账号早于开关存在时，从这里放行而不是把所有人一起重新标记。规则由 `UserPolicy::updateEmailVerification` 定：不能改自己（否则任何 admin 都能自行绕过验证），不能改 owner（owner 恒为已验证），admin 只能改 user/editor，owner 谁都能改。取消验证后，若开关是开着的，该账号在下一次请求时被退出登录。
- 系统不会自动产生 owner（不在 Seeder 创建，也不按固定邮箱在每次请求赋权）。初始化流程是先正常注册，再用服务器 CLI 提升：

```bash
php artisan user:promote owner@example.com --role=owner
php artisan user:promote owner@example.com --role=owner --force   # 非交互环境
```

- 用户不存在会报错；默认需要交互确认，`--force` 仅跳过交互确认，**不能绕过最后 owner 保护**——目标是唯一 active owner 时任何降级都会失败（非 0 退出码，数据库不变）；存在第二个 active owner 时允许降级；`--role` 支持 `user|editor|admin|owner`。命令名为历史兼容保留，实际支持任意角色调整（见 `--role`）。

### 站点设置与邮箱验证

- `/admin/settings`（仅 admin/owner）是运行时开关页，读写 `site_settings` 表。统一入口是 `App\Support\SiteSettings`；它**有意不做缓存**——每次读只是一次带索引的单行查询，而缓存过期会让一扇已经关上的门继续放行。
- 目前只有一个开关：`registration_email_verification`，默认关闭（迁移 `2026_08_30_000001`）。默认关闭是因为一套已经在跑的部署未必配了可用邮件服务，静默开启会让所有新注册直接失败。
- **开关只决定是否强制，不改任何人的验证状态**：已验证就是已验证，未验证就是未验证。开启时不会把旧账号标记为已验证，代价是未验证的账号会失去会话（下一次请求即被 `EnsureEmailIsVerified` 退出登录，并在登录时被挡回验证码页）。设置页会顺带报出受影响的账号数，并在**操作者本人未验证**时提前警告。
- **未验证的账号不持有会话**：这是整套流程的不变量。注册后不自动登录、登录被挡回、中途开启则被踢出，因此验证码页（`/verify-email`）是一个**游客页**，靠会话里的 `email_verification.user_id` 认得「谁在验证」——这个 id 只在刚刚证明过密码（注册成功，或一次被挡回的登录）之后才写入。
- **验证码而不是链接**：`App\Services\EmailVerification` 负责发码与校验。6 位数字存在缓存里（带 TTL，10 分钟，自动过期不需要清理任务），只存哈希并绑定当时的邮箱地址；同一个码最多允许 5 次错误尝试，之后作废。发信走 `App\Notifications\VerifyEmailCode`，由 `User::sendEmailVerificationNotification()` 接上框架的 `Registered` 监听器，所以注册、登录被挡回、页面上的「重新发送」三条路都是同一段代码。
- **一分钟最多一封**：用框架的 `RateLimiter` 记在 `send()` 里——唯一发信出口，所以三条路都受同一预算约束。计数在邮件发出**之后**才记，发信失败不会白占一分钟。页面上的重发按钮服务端就是 disabled 的，Alpine 只负责把剩余秒数倒数出来。
- **所有者永远算已验证**：`User::hasVerifiedEmail()` 对 owner 直接返回 true（中间件、登录检查、用户列表都问这个方法，所以豁免只有一处）。能开关这个要求的人，不该是被它锁在门外的人。
- **拦截范围**：`verified` 挂在 `/dashboard`、`/profile` 与两个 `/admin/*` 路由组上——既然未验证的账号会被直接退出登录，就不存在需要半登录状态服务的页面。写错邮箱的补救办法有两条：重新登录后验证新地址，或让 admin/owner 在[用户管理](#用户管理与初始化-owner)里直接标记。`/logout` 与验证码页本身不挂。
- **邮件文案**：验证码信是自己写的中文通知；密码重置信走框架自带通知，中文译文在 `lang/zh_CN.json`（键就是框架 `Lang::get()` 里的英文原文）。重置流程本身完全是 Laravel 的 `Password` broker，其 60 秒节流由 `config/auth.php` 的 `passwords.users.throttle` 提供，没有另写一套。
- **发信失败不丢注册**：`RegisteredUserController::store()`、登录挡回与重发接口都会捕获邮件异常，`report()` 后把「发送失败，请重试」显示在页面上，账号本身已经建好。`MAIL_MAILER` 为 `log`/`array`/`null` 时设置页会直接警告邮件不会真正投递。

### 安全加固

- **轮播图上传**：文件名随机（`Str::random`，不含原始名），扩展名由服务端 MIME（`finfo`）决定，仅接受 jpg/jpeg/png/webp/gif，并经 GD 重新解码编码以剥离元数据与潜在的 polyglot/脚本内容；限制单边像素、总像素与文件大小。`/storage` 目录须在 Web 服务器层禁止执行 PHP（见[部署](#部署)）。
- **URL 安全**：会渲染到 href 的字段（轮播图 `link_url`、机型规格 `official`）经 `App\Support\SafeUrl` 校验/净化，仅允许站内相对路径与 http(s)，拒绝 `javascript:`/`data:`/`vbscript:`、协议相对 `//host` 与控制字符；前台 `@/utils/url.js` 的 `safeExternalUrl` 为第二层防护。
- **JSON 导入**：限制文件数、总大小、记录总数、字符串长度与 JSON 嵌套深度，根节点必须是对象数组；错误信息只含用户文件名，不含服务器路径。
- **公开 API 限流**：`/api/*` 显式 `throttle:120,1`；搜索关键词长度、`ids`/`names` 数量、`limit`（默认与上限 500）均有上限，错误沿用统一 `422` 格式。
- **Seeder**：`DatabaseSeeder` 仅在 `local`/`testing` 生成测试账号；生产不创建固定测试账号，管理员通过 `user:promote` 提升。

### 路由边界

- `/api/*`：Laravel API（公开只读目录数据，无需鉴权）
- `/dashboard`：Laravel 后台面板，要求 `auth + active + verified`；普通用户可访问只读面板
- `/admin/*`：数据管理后台，要求 `auth + active + verified + role`（`/admin/users`、`/admin/settings` 需 admin/owner，其余需 editor 及以上）
- `/profile`：登录用户本人资料，要求 `auth + active + verified`
- `/verify-email`（GET 显示、POST 交验证码）、`/email/verification-notification`：邮箱验证是**游客流程**，挂 `guest`（外加 `throttle`），见[站点设置与邮箱验证](#站点设置与邮箱验证)
- `/login`、`/logout`、`/register` 等：Laravel 认证
- `/storage/*`、`/assets/*`、`/build/*`、`/frontend/*`：静态或构建资源
- 其他公开页面：Vue SPA fallback

### 错误页

- **`resources/views/errors/404.blade.php`**：请求进到 PHP 时 Laravel 渲染的 404。刻意做成自包含的单文件（内联样式、不引 Vite 产物、不用共享布局）——错误页最需要出场的时刻，恰恰是构建产物缺失或应用没完全启动的时刻，任何额外依赖都会把 404 变成 500。
- **`public/404.html`**：同一张页面的静态孪生，给「请求根本到不了 PHP」的情况用。开箱即用的 Nginx vhost 常见写法是 `try_files $uri $uri/ =404;`，未知路径由 Nginx 自己回 404，此时配 `error_page 404 /404.html;` 就能拿到同样的页面。`docker/nginx/default.conf` 已经这么配；`fastcgi_intercept_errors` 保持关闭，应用自己渲染的 404 会原样透传。
- 两份文件的一致性由 `tests/Feature/ErrorPageTest.php` 守着（同样的文案、同样的 Logo、静态那份不含任何 Blade 语法）。
- 前台 SPA 的 `NotFound.vue` 是另一层：`{any}` fallback 命中的未知路径由前端路由展示，不经过这里。

## 测试与检查

```bash
composer test
vendor/bin/phpunit
vendor/bin/pint --test
php artisan route:list --except-vendor
npm run check
npm run build
```

两套测试各自的范围：

- **PHP**：`composer test` 跑 `tests/`（PHPUnit），Feature 测试直接用 `Product::create()` 建数据——目录下只有 `UserFactory`，其余模型没有工厂。邮箱验证的验证码流程（发码、校验、过期、错误次数、一分钟一封、owner 豁免、开关中途开启踢人）由 `tests/Feature/Auth/EmailVerificationTest.php` 覆盖；后台开关页与「开关不改验证状态」由 `tests/Feature/SiteSettingsTest.php` 覆盖；用户管理里的手改验证状态由 `tests/Feature/UserEmailVerificationTest.php` 覆盖；两张 404 页由 `tests/Feature/ErrorPageTest.php` 覆盖。
- **前端**：`npm run test:frontend` 跑 `frontend/tests/`（Vitest）。默认 environment 是 `node`，纯函数安全测试（`image-url-safety.test.mjs`，同时覆盖 `applyImageFallback` 的一次性回退）自带最小 `window` stub；组件测试在文件首行用 `// @vitest-environment jsdom` 单独切到 jsdom，覆盖 `Home.vue`/`Category/BrandPhoneList.vue`/`PhoneDetail.vue` 的 AbortController 取消、requestId 竞态守卫与 250ms 搜索防抖，以及 `NavBar.vue` 用户名按钮的前后台切换目标与退出登录表单（`navbar-user-chip.test.mjs`，后台那一半由 `tests/Feature/MenuVisibilityTest.php` 断言）。

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
- **当前上游约束**：`mockery/mockery` 1.6.15 起接受 `hamcrest/hamcrest-php ^2.0 || ^3.0`，Hamcrest 已随之升到 3.0.0（此前被 1.6.12 的 `^2.0.1` 卡在 2.1.1）。仍留在旧主版本的只有 `brick/math` 0.18——**它已经是当前依赖图允许的最高版本**：`ramsey/uuid` 4.9.3（最新版，`laravel/framework` 的硬依赖）要求 `brick/math >=0.8.16 <=0.18`，`laravel/framework` v13.29.0 要求 `^0.14.2 || ... || ^0.19`，两者的交集就是 0.18.0。等 ramsey/uuid 放宽约束（5.x 目前只有 dev 分支）再升，不要用 `--ignore-platform-reqs` 或改别人的约束硬塞。复核命令：

```bash
composer why-not brick/math 0.20
composer update brick/math laravel/framework ramsey/uuid -W --dry-run   # Nothing to modify in lock file
```

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

构建产物已经在仓库里，所以服务器上没有 Node 也能上线；上面两条 npm 命令只在你要在服务器上重新构建时才需要，纯拉取更新的最小流程是：

```bash
git pull --ff-only && composer install --no-dev -o && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache
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
| `MAIL_*` | 真实邮件服务 | 开启注册邮箱验证前必须配好，`log`/`array` 不会真正投递 |
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

    # Nginx 自己回的 404（缺失的静态文件，或 `location /` 用 `=404` 而没有转给
    # PHP 的写法）也用同一张错误页。不要开 fastcgi_intercept_errors，
    # 否则应用自己渲染的 404 会被这张静态页替掉。
    error_page 404 /404.html;

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

当前 `Content-Security-Policy` 在 `script-src` 保留 `'unsafe-inline'` 与 `'unsafe-eval'`，以兼容后台 Alpine.js 与前台 SPA 的内联引导脚本；`default-src 'self'` 与 `object-src 'none'`、`base-uri 'self'`、`frame-ancestors 'self'`、`form-action 'self'` 仍能阻断外站脚本注入与点击劫持。`img-src` 除 `'self' data:` 外另放行 `https: http:`，以便目录机型的 `image_url` 显示外站图片（服务端 `safeImageUrl()` 与前台仍会拦截 http 降级到 https 页面）；如不需要外链，可把 `img-src` 收紧回 `'self' data:`。若要进一步收紧，去掉这两个开关：为内联脚本改用每请求 nonce（在 `SecurityHeaders` 中生成并注入到脚本标签与 CSP），后台改用 Alpine 的 CSP 构建版本。

### 容器化部署（Docker）

镜像部署要求 Docker Compose v2（建议保持当前稳定版）；旧版 `docker-compose` v1 不支持本文使用的启动条件与 `--wait`。

仓库提供两条端到端部署路径：[`compose.yml`](../compose.yml) 从源码构建镜像，适合开发和 CI；[`compose.deploy.yml`](../compose.deploy.yml) 只拉取 Docker Hub 预构建镜像，适合生产主机一键部署。两者共用多阶段 [`Dockerfile`](../Dockerfile)、[`docker/`](../docker) 配置与 [`.env.docker.example`](../.env.docker.example)。CI（`.github/workflows/ci.yml` 的 `docker` job）先构建本地镜像，再用生产同款 `compose.deploy.yml` 自动迁移、启动并冒烟 `/up`；`mysql-test` job 另在真实 MySQL 8 上跑迁移与全套测试。

**镜像分阶段：**

- **阶段 1（`node:24-alpine`）**：`npm ci` + `npm run build`，产出 `public/build`（后台）与 `public/frontend`（前台）。
- **阶段 2（`php:8.5-cli` + composer）**：`composer install --no-dev` + `dump-autoload --optimize --classmap-authoritative`。
- **阶段 3 `runtime`（`php:8.5-fpm`）**：安装运行期扩展 `pdo_mysql`、`gd`、`zip`、`bcmath`、`opcache`（`fileinfo` 官方镜像自带并校验存在），启用 OPcache（`docker/php/opcache.ini`，`validate_timestamps=0`，改代码需重建镜像）与上传限制（`docker/php/php.ini`，`upload_max_filesize`/`post_max_size` 24M）。`package:discover` **不**再 `|| true`，发现失败即构建失败。以 `www-data` 非 root 运行，`ENTRYPOINT` 每容器缓存 config/route/view 后起 `php-fpm`。
- **阶段 4 `web`（`nginx:1.27-alpine`）**：烤入 `public/`，`docker/nginx/default.conf` 直出静态资源、`/storage` 上传目录（禁执行脚本）、`fastcgi_pass app:9000`。
- `.dockerignore` 排除 `.git`、`.env`、`vendor`、`node_modules`、`public/build`、`public/frontend`、测试数据库（`database/*.sqlite`）、`tests`、文档等，避免把本地密钥、依赖或数据打进镜像。

**编排：** 两份 Compose 都包含 `db`（MySQL 8，`db-data` 持久卷，healthcheck）、`app`（php-fpm）、`web`（nginx，发布 `${WEB_PORT:-8080}:80`，healthcheck 打 `/up`）和一次性 `migrate`。源码构建版的 `migrate` 位于 `tools` profile，供 CI/开发显式调用；镜像部署版会在每次发布时自动启动它，且 `app` 以 `service_completed_successfully` 为条件等待迁移成功，失败时不会启动应用。`app` 与 `web` 共享 `uploads` 卷，nginx 以只读方式直出上传文件。

**Docker Hub 镜像发布（只需配置一次）：**

1. 在 Docker Hub 的 `generalpeople` 命名空间创建公开仓库 `smartphone-catalog`。
2. 在 Docker Hub 创建仅用于 CI 的 Access Token；不要使用账号密码，也不要把 Token 写进仓库或聊天。
3. 在 GitHub 仓库 Actions secrets 中添加 `DOCKERHUB_USERNAME`（值为 `generalpeople`）和 `DOCKERHUB_TOKEN`。
4. 推送到 `main` 后会自动运行 `Publish Docker Hub images`；也可手动运行或推送 `v*` Git tag。工作流会发布 amd64/arm64 两套角色标签：`runtime`、`web`，以及可选的 `runtime-v1.0.0`、`web-v1.0.0`。

仓库使用两个标签而不是一个 `latest`，因为 PHP-FPM 和 Nginx 是职责、内容和运行用户均不同的镜像；部署文件会自动选用正确标签。

**生产主机首次配置（只做一次）：**

```bash
cp .env.docker.example .env
# 生成 APP_KEY（命令会拉取 runtime 镜像，不启动数据库）：
docker compose -f compose.deploy.yml run --rm --no-deps app php artisan key:generate --show
# 将输出的 base64:... 写入 .env，并填写 APP_URL 与两个随机强数据库密码。
```

若以 HTTPS 域名访问，保持 `SESSION_SECURE_COOKIE=true`；仅在本机 HTTP 验证时才改为 `false`。镜像仓库应设为公开，这样部署主机无需保存 Docker Hub 登录凭据。

**以后部署、升级或恢复服务均为同一条命令：**

```bash
docker compose -f compose.deploy.yml up -d --pull always --wait
```

它会拉取镜像 → 等 MySQL 健康 → 运行一次 `migrate --force` → 迁移成功后启动 app/web → 等 `/up` 健康。失败会返回非零退出码，可立即用 `docker compose -f compose.deploy.yml logs --no-color` 查看原因。`--pull always` 更新可变的 `runtime`/`web` 标签；正式版本建议把 `.env` 的 `DOCKER_APP_IMAGE` 与 `DOCKER_WEB_IMAGE` 同时固定到匹配的版本标签，以支持确定性回滚。

**从源码构建、迁移与首个 owner：**

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
